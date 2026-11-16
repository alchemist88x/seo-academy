<?php

namespace Tests\Feature\Enrollments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Platform\Models\Media;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\EnrollmentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\EnrollmentsIndex;
use App\Livewire\Student\StudentCoursesIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $createdPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            Storage::disk('private_media')->delete($path);
        }

        parent::tearDown();
    }

    private function createAcademyMember(Academy $academy, string $role, array $userOverrides = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $userOverrides));
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    private function createStudentWithProfile(Academy $academy, array $userOverrides = [], array $profileOverrides = []): array
    {
        $user = $this->createAcademyMember($academy, 'student', $userOverrides);
        $profile = StudentProfile::create(array_merge([
            'user_id' => $user->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-'.strtoupper(Str::random(6)),
            'batch' => 'Cohort 2026-A',
            'level' => 'Beginner',
            'active' => true,
        ], $profileOverrides));

        return [$user, $profile];
    }

    private function createPublishedCourse(Academy $academy, array $overrides = []): Course
    {
        return Course::factory()->create(array_merge([
            'academy_id' => $academy->id,
            'status' => 'Published',
            'visibility' => 'public',
            'published_at' => now(),
            'enrollment_limit' => null,
        ], $overrides));
    }

    public function test_admin_can_access_enrollment_management_and_list_enrollments(): void
    {
        $academy = Academy::factory()->create(['name' => 'SEO Academy', 'slug' => 'seo-academy']);
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy, ['title' => 'Technical SEO Mastery']);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertSee('Enrollment Management')
            ->assertSee('Technical SEO Mastery')
            ->assertSee($studentUser->name);

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(EnrollmentsIndex::class)
            ->assertSee('Technical SEO Mastery')
            ->assertSee($studentProfile->student_code)
            ->assertDontSee('No enrollments found')
            ->set('search', 'NonExistentXYZ')
            ->assertSee('No enrollments found');
    }

    public function test_admin_enrollment_single_assignment_via_service_and_livewire(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy, ['title' => 'Keyword Research 101']);

        $service = app(EnrollmentManagementService::class);

        $enrollment = $service->assign($studentProfile, $course, $admin, [
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonths(6),
            'notes' => 'Assigned for Q1 cohort',
        ]);

        $this->assertInstanceOf(Enrollment::class, $enrollment);
        $this->assertSame('active', $enrollment->status);
        $this->assertSame($admin->id, $enrollment->assigned_by);
        $this->assertSame('Assigned for Q1 cohort', $enrollment->notes);
        $this->assertTrue($enrollment->isValid());

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
        ]);

        // Second student via Livewire component
        [$studentUser2, $studentProfile2] = $this->createStudentWithProfile($academy);
        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(EnrollmentsIndex::class)
            ->set('assignStudentId', $studentProfile2->id)
            ->set('assignCourseId', $course->id)
            ->set('assignStatus', 'active')
            ->set('assignStartsAt', now()->toDateString())
            ->set('assignExpiresAt', now()->addMonths(3)->toDateString())
            ->set('assignNotes', 'Assigned via UI')
            ->call('saveSingleAssign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $studentProfile2->id,
            'course_id' => $course->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_admin_bulk_enrollment_assignment(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $course = $this->createPublishedCourse($academy, ['title' => 'Link Building Fundamentals']);

        [$u1, $p1] = $this->createStudentWithProfile($academy);
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        [$u3, $p3] = $this->createStudentWithProfile($academy);

        $service = app(EnrollmentManagementService::class);

        $result = $service->bulkAssign([$p1->id, $p2->id, $p3->id], $course, $admin, [
            'status' => 'active',
            'notes' => 'Bulk batch assign',
        ]);

        $this->assertCount(3, $result['assigned']);
        $this->assertCount(0, $result['skipped']);

        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $p1->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $p2->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $p3->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        // Bulk assign via Livewire
        [$u4, $p4] = $this->createStudentWithProfile($academy);
        [$u5, $p5] = $this->createStudentWithProfile($academy);
        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(EnrollmentsIndex::class)
            ->set('bulkCourseId', $course->id)
            ->set('bulkStudentIds', [$p4->id, $p5->id])
            ->set('bulkStatus', 'pending')
            ->set('bulkStartsAt', now()->toDateString())
            ->call('saveBulkAssign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $p4->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $p5->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_prevention_blocks_second_active_or_pending_enrollment(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        $service = app(EnrollmentManagementService::class);
        $service->assign($studentProfile, $course, $admin, ['status' => 'active']);

        $this->expectException(ValidationException::class);
        $service->assign($studentProfile, $course, $admin, ['status' => 'active']);
    }

    public function test_capacity_enforcement_blocks_enrollments_exceeding_course_limit(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $course = $this->createPublishedCourse($academy, [
            'title' => 'Limited Seats Bootcamp',
            'enrollment_limit' => 2,
        ]);

        [$u1, $p1] = $this->createStudentWithProfile($academy);
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        [$u3, $p3] = $this->createStudentWithProfile($academy);

        $service = app(EnrollmentManagementService::class);
        $service->assign($p1, $course, $admin, ['status' => 'active']);
        $service->assign($p2, $course, $admin, ['status' => 'pending']);

        // 3rd enrollment must be blocked due to capacity limit of 2
        try {
            $service->assign($p3, $course, $admin, ['status' => 'active']);
            $this->fail('Expected ValidationException due to course capacity limit.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('capacity', $e->errors());
        }

        // Bulk assign also skips students when capacity is reached
        [$u4, $p4] = $this->createStudentWithProfile($academy);
        $bulkResult = $service->bulkAssign([$p3->id, $p4->id], $course, $admin);

        $this->assertCount(0, $bulkResult['assigned']);
        $this->assertCount(2, $bulkResult['skipped']);
        $this->assertStringContainsString('limit', $bulkResult['skipped'][0]['reason']);
    }

    public function test_prerequisite_validation_enforcement(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        $prereqCourse = $this->createPublishedCourse($academy, ['title' => 'SEO Level 1']);
        $advancedCourse = $this->createPublishedCourse($academy, ['title' => 'SEO Level 2 Advanced']);

        app(CourseManagementService::class)->syncPrerequisites($advancedCourse, [$prereqCourse->id], $admin);

        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $service = app(EnrollmentManagementService::class);

        // Attempting to enroll in Level 2 without Level 1 must fail
        try {
            $service->assign($studentProfile, $advancedCourse, $admin, ['status' => 'active']);
            $this->fail('Expected ValidationException due to missing prerequisite.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisites', $e->errors());
            $this->assertStringContainsString('SEO Level 1', $e->errors()['prerequisites'][0]);
        }

        // Now satisfy prerequisite with completed enrollment in Level 1
        $service->assign($studentProfile, $prereqCourse, $admin, ['status' => 'active']);
        $level1Enrollment = Enrollment::where('student_profile_id', $studentProfile->id)
            ->where('course_id', $prereqCourse->id)
            ->firstOrFail();

        $service->transitionStatus($level1Enrollment, 'completed', $admin);

        // Enrolling in Level 2 should now succeed
        $advancedEnrollment = $service->assign($studentProfile, $advancedCourse, $admin, ['status' => 'active']);
        $this->assertInstanceOf(Enrollment::class, $advancedEnrollment);
        $this->assertSame('active', $advancedEnrollment->status);
    }

    public function test_lifecycle_state_transitions_succeed_for_valid_paths(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        $service = app(EnrollmentManagementService::class);

        // 1. Initial pending
        $enrollment = $service->assign($studentProfile, $course, $admin, ['status' => 'pending']);
        $this->assertSame('pending', $enrollment->status);

        // 2. pending -> active
        $enrollment = $service->transitionStatus($enrollment, 'active', $admin);
        $this->assertSame('active', $enrollment->status);
        $this->assertNull($enrollment->suspended_at);
        $this->assertNull($enrollment->cancelled_at);

        // 3. active -> suspended
        $enrollment = $service->transitionStatus($enrollment, 'suspended', $admin, [
            'reason' => 'Payment issue',
        ]);
        $this->assertSame('suspended', $enrollment->status);
        $this->assertNotNull($enrollment->suspended_at);
        $this->assertSame('Payment issue', $enrollment->suspension_reason);

        // 4. suspended -> active
        $enrollment = $service->transitionStatus($enrollment, 'active', $admin);
        $this->assertSame('active', $enrollment->status);
        $this->assertNull($enrollment->suspended_at);
        $this->assertNull($enrollment->suspension_reason);

        // 5. active -> completed
        $enrollment = $service->transitionStatus($enrollment, 'completed', $admin);
        $this->assertSame('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);

        // 6. completed -> active (admin reactivation)
        $enrollment = $service->transitionStatus($enrollment, 'active', $admin);
        $this->assertSame('active', $enrollment->status);

        // 7. active -> cancelled
        $enrollment = $service->transitionStatus($enrollment, 'cancelled', $admin, [
            'reason' => 'Student requested drop',
        ]);
        $this->assertSame('cancelled', $enrollment->status);
        $this->assertNotNull($enrollment->cancelled_at);
        $this->assertSame('Student requested drop', $enrollment->cancellation_reason);

        // 8. cancelled -> active (admin reactivation)
        $enrollment = $service->transitionStatus($enrollment, 'active', $admin);
        $this->assertSame('active', $enrollment->status);
        $this->assertNull($enrollment->cancelled_at);
        $this->assertNull($enrollment->cancellation_reason);
    }

    public function test_lifecycle_state_transitions_throw_exception_for_invalid_paths(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        $service = app(EnrollmentManagementService::class);
        $enrollment = $service->assign($studentProfile, $course, $admin, ['status' => 'pending']);

        // Invalid: pending -> completed
        $this->expectException(\DomainException::class);
        $service->transitionStatus($enrollment, 'completed', $admin);
    }

    public function test_access_control_active_enrollment_allows_private_media_while_suspended_expired_cancelled_return_403(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        // Create private media attached to the course
        $content = 'SAMPLE-ENROLLMENT-PRIVATE-VIDEO-CONTENT';
        $path = 'tests/enrollment-media-'.Str::uuid().'.mp4';
        Storage::disk('private_media')->put($path, $content);
        $this->createdPaths[] = $path;

        $media = Media::create([
            'model_type' => Course::class,
            'model_id' => $course->id,
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => $path,
            'filename' => 'course-private-video.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($content),
            'created_at' => now(),
        ]);

        $service = app(EnrollmentManagementService::class);

        // 1. No enrollment yet -> 403 Forbidden
        $this->actingAs($studentUser)->get(route('media.show', $media))->assertForbidden();

        // 2. Active enrollment -> 200 OK
        $enrollment = $service->assign($studentProfile, $course, $admin, ['status' => 'active']);
        $this->actingAs($studentUser)->get(route('media.show', $media))->assertOk();

        // 3. Suspended enrollment -> 403 Forbidden
        $service->transitionStatus($enrollment, 'suspended', $admin, ['reason' => 'Disciplinary hold']);
        $this->actingAs($studentUser)->get(route('media.show', $media))->assertForbidden();

        // 4. Cancelled enrollment -> 403 Forbidden
        $service->transitionStatus($enrollment, 'active', $admin);
        $service->transitionStatus($enrollment, 'cancelled', $admin, ['reason' => 'Cancelled']);
        $this->actingAs($studentUser)->get(route('media.show', $media))->assertForbidden();

        // 5. Expired enrollment -> 403 Forbidden
        $service->transitionStatus($enrollment, 'active', $admin);
        $service->transitionStatus($enrollment, 'expired', $admin);
        $this->actingAs($studentUser)->get(route('media.show', $media))->assertForbidden();
    }

    public function test_free_preview_lessons_still_stream_for_unenrolled_or_suspended_students(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy, [
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $module = Module::create([
            'course_id' => $course->id,
            'title' => 'Free Introduction Module',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => 'Free Preview Lesson',
            'lesson_type' => 'video',
            'is_free_preview' => true,
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $content = 'FREE-PREVIEW-STREAM-SAMPLE-CONTENT';
        $path = 'tests/preview-lesson-'.Str::uuid().'.mp4';
        Storage::disk('private_media')->put($path, $content);
        $this->createdPaths[] = $path;

        $media = Media::create([
            'model_type' => Lesson::class,
            'model_id' => $lesson->id,
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => $path,
            'filename' => 'preview-video.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($content),
            'created_at' => now(),
        ]);

        // Unenrolled student can stream free preview lesson media
        $this->actingAs($studentUser)->get(route('media.show', $media))->assertOk();

        // Even with a suspended enrollment in the course, free preview remains accessible
        $service = app(EnrollmentManagementService::class);
        $enrollment = $service->assign($studentProfile, $course, $admin, ['status' => 'active']);
        $service->transitionStatus($enrollment, 'suspended', $admin, ['reason' => 'Suspended test']);

        $this->actingAs($studentUser)->get(route('media.show', $media))->assertOk();
    }

    public function test_student_my_courses_is_read_only(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy, ['title' => 'On-Page Optimization']);

        app(TenantContext::class)->setAcademy($academy);

        // When not enrolled: shows empty state with no enroll button
        Livewire::actingAs($studentUser)
            ->test(StudentCoursesIndex::class)
            ->assertSee('You are not currently enrolled in any courses')
            ->assertSee('Please contact your academy administrator to get enrolled')
            ->assertDontSee('Enroll Now')
            ->assertDontSee('Add to Cart');

        // Enroll student
        $service = app(EnrollmentManagementService::class);
        $service->assign($studentProfile, $course, $admin, ['status' => 'active']);

        // Now shows enrolled course with status badge and Resume Course
        Livewire::actingAs($studentUser)
            ->test(StudentCoursesIndex::class)
            ->assertSee('On-Page Optimization')
            ->assertSee('Active')
            ->assertSee('Resume Course');

        // Student cannot access admin enrollments management route
        $this->actingAs($studentUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.enrollments.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_assign_or_mutate_enrollments(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => 'active',
        ]);

        // Student authorization checks
        $this->assertFalse(Gate::forUser($studentUser)->allows('create', Enrollment::class));
        $this->assertFalse(Gate::forUser($studentUser)->allows('update', $enrollment));
        $this->assertFalse(Gate::forUser($studentUser)->allows('delete', $enrollment));
        $this->assertFalse(Gate::forUser($studentUser)->allows('viewAny', Enrollment::class));
        $this->assertTrue(Gate::forUser($studentUser)->allows('view', $enrollment));

        // Admin authorization checks
        $this->assertTrue(Gate::forUser($admin)->allows('create', Enrollment::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $enrollment));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $enrollment));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Enrollment::class));
    }

    public function test_extending_expiry_reactivates_expired_enrollment(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        $service = app(EnrollmentManagementService::class);
        $enrollment = $service->assign($studentProfile, $course, $admin, ['status' => 'active']);
        $service->transitionStatus($enrollment, 'expired', $admin);

        $this->assertSame('expired', $enrollment->fresh()->status);

        // Extend expiry into future -> should auto-reactivate to active
        $extended = $service->extendExpiry($enrollment, now()->addMonths(6), $admin);

        $this->assertSame('active', $extended->status);
        $this->assertTrue($extended->isValid());
        $this->assertFalse($extended->isExpired());
    }

    public function test_expiry_processing_command_transitions_expired_active_enrollments(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');

        [$u1, $p1] = $this->createStudentWithProfile($academy);
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        [$u3, $p3] = $this->createStudentWithProfile($academy);

        $course = $this->createPublishedCourse($academy);

        // 1. Expired active enrollment (expires_at in past) -> should transition to expired
        $expiredEnrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p1->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => 'active',
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);

        // 2. Active enrollment (expires_at in future) -> should stay active
        $futureEnrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p2->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        // 3. Active enrollment with no expiration -> should stay active
        $noExpiryEnrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p3->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => null,
        ]);

        $exitCode = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exitCode);

        $this->assertSame('expired', $expiredEnrollment->fresh()->status);
        $this->assertSame('active', $futureEnrollment->fresh()->status);
        $this->assertSame('active', $noExpiryEnrollment->fresh()->status);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.status_changed',
            'subject_id' => $expiredEnrollment->id,
        ]);
    }

    public function test_audit_log_records_acting_administrator_for_all_enrollment_actions(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $course = $this->createPublishedCourse($academy);

        $service = app(EnrollmentManagementService::class);

        // 1. Assign single
        $enrollment = $service->assign($studentProfile, $course, $admin, ['status' => 'active']);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.assigned',
            'user_id' => $admin->id,
            'subject_type' => Enrollment::class,
            'subject_id' => $enrollment->id,
        ]);

        // 2. Transition status
        $service->transitionStatus($enrollment, 'suspended', $admin, ['reason' => 'Audit test suspension']);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.status_changed',
            'user_id' => $admin->id,
            'subject_type' => Enrollment::class,
            'subject_id' => $enrollment->id,
        ]);

        // 3. Extend expiry
        $service->extendExpiry($enrollment, now()->addYear(), $admin);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.dates_updated',
            'user_id' => $admin->id,
            'subject_type' => Enrollment::class,
            'subject_id' => $enrollment->id,
        ]);

        // 4. Bulk assign
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        $course2 = $this->createPublishedCourse($academy);
        $service->bulkAssign([$p2->id], $course2, $admin);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.bulk_assigned',
            'user_id' => $admin->id,
            'subject_type' => Course::class,
            'subject_id' => $course2->id,
        ]);
    }
}
