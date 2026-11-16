<?php

namespace Tests\Feature\Enrollments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Media;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\EnrollmentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentSecurityAuditTest extends TestCase
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

        Carbon::setTestNow();

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

    /**
     * @return array{0: User, 1: StudentProfile}
     */
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

    /**
     * @return array{0: Course, 1: Module, 2: Lesson, 3: Media}
     */
    private function createCourseHierarchy(
        Academy $academy,
        array $courseOverrides = [],
        array $lessonOverrides = [],
        string $mediaContent = 'STREAMABLE_MEDIA_PAYLOAD_123456789'
    ): array {
        $course = Course::factory()->create(array_merge([
            'academy_id' => $academy->id,
            'status' => 'Published',
            'visibility' => 'public',
            'published_at' => now(),
            'enrollment_limit' => null,
        ], $courseOverrides));

        $module = Module::factory()->create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Module 1: Foundations',
            'status' => 'Active',
        ]);

        $path = 'tests/audit/'.Str::uuid().'.mp4';
        Storage::disk('private_media')->put($path, $mediaContent);
        $this->createdPaths[] = $path;

        $lesson = Lesson::factory()->create(array_merge([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Core Architecture Lesson',
            'visibility' => 'enrolled',
            'is_free_preview' => false,
            'status' => 'Active',
        ], $lessonOverrides));

        $media = Media::create([
            'model_type' => Lesson::class,
            'model_id' => $lesson->id,
            'collection' => 'lesson_videos',
            'disk' => 'private_media',
            'path' => $path,
            'filename' => 'lesson-stream.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($mediaContent),
            'created_at' => now(),
        ]);

        $lesson->update(['media_id' => $media->id]);

        return [$course, $module, $lesson, $media];
    }

    // =========================================================================
    // TASK 1: Lesson Access Authorization
    // =========================================================================

    public function test_unauthenticated_users_are_redirected_from_preview_and_media_routes(): void
    {
        $academy = Academy::factory()->create();
        [$course, $module, $lesson, $media] = $this->createCourseHierarchy($academy);

        $previewResponse = $this->get(route('lessons.preview', ['course' => $course->id, 'lesson' => $lesson->id]));
        $previewResponse->assertStatus(302);
        $previewResponse->assertRedirect(route('login'));

        $mediaResponse = $this->get(route('media.show', $media));
        $mediaResponse->assertStatus(302);
        $mediaResponse->assertRedirect(route('login'));

        $jsonPreviewResponse = $this->getJson(route('lessons.preview', ['course' => $course->id, 'lesson' => $lesson->id]));
        $jsonPreviewResponse->assertStatus(401);

        $jsonMediaResponse = $this->getJson(route('media.show', $media));
        $jsonMediaResponse->assertStatus(401);
    }

    public function test_active_enrolled_student_can_view_lesson_preview_and_stream_media(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        $mediaPayload = 'ACTIVE_STUDENT_STREAMABLE_MEDIA_BYTES';
        [$course, $module, $lesson, $media] = $this->createCourseHierarchy($academy, [], [], $mediaPayload);

        Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue(Gate::forUser($studentUser)->allows('view', $lesson));
        $this->assertTrue(Gate::forUser($studentUser)->allows('preview', $lesson));

        $previewResponse = $this->actingAs($studentUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('lessons.preview', ['course' => $course->id, 'lesson' => $lesson->id]));

        $previewResponse->assertOk();
        $previewResponse->assertViewIs('lessons.preview');
        $previewResponse->assertSee('Core Architecture Lesson');

        $mediaResponse = $this->actingAs($studentUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('media.show', $media));

        $mediaResponse->assertOk();
        $mediaResponse->assertHeader('Content-Type', 'video/mp4');
        $this->assertSame($mediaPayload, $mediaResponse->streamedContent());
    }

    public function test_pending_suspended_expired_and_cancelled_enrollments_cannot_view_non_free_lesson(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);
        [$course, $module, $lesson, $media] = $this->createCourseHierarchy($academy, [], [
            'is_free_preview' => false,
            'visibility' => 'enrolled',
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_PENDING,
            'starts_at' => now()->subDay(),
        ]);

        $invalidStatuses = [
            Enrollment::STATUS_PENDING,
            Enrollment::STATUS_SUSPENDED,
            Enrollment::STATUS_EXPIRED,
            Enrollment::STATUS_CANCELLED,
        ];

        foreach ($invalidStatuses as $status) {
            $enrollment->update(['status' => $status]);

            $this->assertFalse(
                Gate::forUser($studentUser)->allows('view', $lesson),
                "LessonPolicy::view should deny access for enrollment status: {$status}"
            );
            $this->assertFalse(
                Gate::forUser($studentUser)->allows('preview', $lesson),
                "LessonPolicy::preview should deny access for enrollment status: {$status}"
            );

            $this->actingAs($studentUser)
                ->withSession([TenantContext::SESSION_KEY => $academy->id])
                ->get(route('lessons.preview', ['course' => $course->id, 'lesson' => $lesson->id]))
                ->assertForbidden();

            $this->actingAs($studentUser)
                ->withSession([TenantContext::SESSION_KEY => $academy->id])
                ->get(route('media.show', $media))
                ->assertForbidden();
        }
    }

    public function test_eligible_free_preview_lessons_remain_accessible_to_unenrolled_and_suspended_students(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        $mediaPayload = 'FREE_PREVIEW_STREAM_BYTES_SEO';
        [$course, $module, $lesson, $media] = $this->createCourseHierarchy($academy, [
            'visibility' => 'public',
            'status' => 'Published',
        ], [
            'is_free_preview' => true,
            'visibility' => 'preview',
        ], $mediaPayload);

        // 1. Unenrolled student in the academy
        [$unenrolledUser, $unenrolledProfile] = $this->createStudentWithProfile($academy);

        $this->assertTrue(Gate::forUser($unenrolledUser)->allows('view', $lesson));
        $this->assertTrue(Gate::forUser($unenrolledUser)->allows('preview', $lesson));

        $this->actingAs($unenrolledUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('lessons.preview', ['course' => $course->id, 'lesson' => $lesson->id]))
            ->assertOk()
            ->assertSee('Core Architecture Lesson');

        $unenrolledMedia = $this->actingAs($unenrolledUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('media.show', $media));

        $unenrolledMedia->assertOk();
        $this->assertSame($mediaPayload, $unenrolledMedia->streamedContent());

        // 2. Suspended student enrolled in this course
        [$suspendedUser, $suspendedProfile] = $this->createStudentWithProfile($academy);
        Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $suspendedProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => 'Administrative review',
        ]);

        $this->assertTrue(Gate::forUser($suspendedUser)->allows('view', $lesson));
        $this->assertTrue(Gate::forUser($suspendedUser)->allows('preview', $lesson));

        $this->actingAs($suspendedUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('lessons.preview', ['course' => $course->id, 'lesson' => $lesson->id]))
            ->assertOk();

        $suspendedMedia = $this->actingAs($suspendedUser)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('media.show', $media));

        $suspendedMedia->assertOk();
        $this->assertSame($mediaPayload, $suspendedMedia->streamedContent());
    }

    public function test_students_cannot_view_another_students_enrollment_or_progress(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);

        [$studentA, $profileA] = $this->createStudentWithProfile($academy);
        [$studentB, $profileB] = $this->createStudentWithProfile($academy);

        $enrollmentA = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profileA->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $enrollmentB = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profileB->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        // Student A can view their own enrollment
        $this->assertTrue(Gate::forUser($studentA)->allows('view', $enrollmentA));
        // Student A CANNOT view Student B's enrollment
        $this->assertFalse(Gate::forUser($studentA)->allows('view', $enrollmentB));

        // Student B can view their own enrollment
        $this->assertTrue(Gate::forUser($studentB)->allows('view', $enrollmentB));
        // Student B CANNOT view Student A's enrollment
        $this->assertFalse(Gate::forUser($studentB)->allows('view', $enrollmentA));

        // Admin can view both
        $this->assertTrue(Gate::forUser($admin)->allows('view', $enrollmentA));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $enrollmentB));
    }

    // =========================================================================
    // TASK 2: Course Capacity & Concurrency
    // =========================================================================

    public function test_course_assignment_locks_course_and_enforces_enrollment_limit(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy, [
            'enrollment_limit' => 2,
        ]);

        [$u1, $p1] = $this->createStudentWithProfile($academy);
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        [$u3, $p3] = $this->createStudentWithProfile($academy);

        $service = app(EnrollmentManagementService::class);

        $e1 = $service->assign($p1, $course, $admin, ['status' => 'active']);
        $this->assertInstanceOf(Enrollment::class, $e1);

        $e2 = $service->assign($p2, $course, $admin, ['status' => 'active']);
        $this->assertInstanceOf(Enrollment::class, $e2);

        $this->assertSame(2, Enrollment::query()->where('course_id', $course->id)->count());

        try {
            $service->assign($p3, $course, $admin, ['status' => 'active']);
            $this->fail('Expected ValidationException was not thrown when exceeding enrollment_limit.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('capacity', $e->errors());
            $this->assertStringContainsString('limit of 2 has been reached', $e->errors()['capacity'][0]);
        }

        $this->assertSame(2, Enrollment::query()->where('course_id', $course->id)->count());
        $this->assertDatabaseMissing('enrollments', [
            'student_profile_id' => $p3->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_duplicate_active_or_pending_assignment_blocked_with_validation_exception(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $service = app(EnrollmentManagementService::class);

        // 1. Duplicate active blocked
        $service->assign($studentProfile, $course, $admin, ['status' => 'active']);

        try {
            $service->assign($studentProfile, $course, $admin, ['status' => 'active']);
            $this->fail('Expected ValidationException when duplicate active enrollment is assigned.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment', $e->errors());
            $this->assertStringContainsString('already has an active or pending enrollment', $e->errors()['enrollment'][0]);
        }

        // 2. Duplicate pending blocked
        [$studentUser2, $studentProfile2] = $this->createStudentWithProfile($academy);
        $service->assign($studentProfile2, $course, $admin, ['status' => 'pending']);

        try {
            $service->assign($studentProfile2, $course, $admin, ['status' => 'pending']);
            $this->fail('Expected ValidationException when duplicate pending enrollment is assigned.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment', $e->errors());
            $this->assertStringContainsString('already has an active or pending enrollment', $e->errors()['enrollment'][0]);
        }
    }

    public function test_bulk_assignment_with_partial_failures_assigns_eligible_and_reports_skipped(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy, ['enrollment_limit' => 5]);

        // Student 1: eligible
        [$u1, $p1] = $this->createStudentWithProfile($academy);

        // Student 2: user account is inactive
        [$u2, $p2] = $this->createStudentWithProfile($academy, ['is_active' => false]);

        // Student 3: already enrolled actively
        [$u3, $p3] = $this->createStudentWithProfile($academy);
        Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p3->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $service = app(EnrollmentManagementService::class);

        $result = $service->bulkAssign([$p1->id, $p2->id, $p3->id], $course, $admin, [
            'status' => 'active',
            'notes' => 'Bulk partial test',
        ]);

        $this->assertCount(1, $result['assigned']);
        $this->assertSame($p1->id, $result['assigned'][0]->student_profile_id);

        $this->assertCount(2, $result['skipped']);
        $skippedStudentIds = array_column($result['skipped'], 'student_profile_id');
        $this->assertContains($p2->id, $skippedStudentIds);
        $this->assertContains($p3->id, $skippedStudentIds);

        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $p1->id,
            'course_id' => $course->id,
            'notes' => 'Bulk partial test',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'student_profile_id' => $p2->id,
            'course_id' => $course->id,
        ]);
    }

    // =========================================================================
    // TASK 3: Audit History Integrity
    // =========================================================================

    public function test_ordinary_students_cannot_update_or_delete_enrollment_records(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $this->assertFalse(Gate::forUser($studentUser)->allows('create', Enrollment::class));
        $this->assertFalse(Gate::forUser($studentUser)->allows('update', $enrollment));
        $this->assertFalse(Gate::forUser($studentUser)->allows('delete', $enrollment));

        // Admin policy allows create and update, but delete is disabled for all users (immutable audit trail)
        $this->assertTrue(Gate::forUser($admin)->allows('create', Enrollment::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $enrollment));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $enrollment));
        $this->assertFalse($admin->can('delete', $enrollment));
        $this->assertFalse($studentUser->can('delete', $enrollment));
    }

    public function test_enrollment_policy_delete_returns_false_for_both_students_and_administrators(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        // EnrollmentPolicy::delete returns false for both students AND administrators
        $this->assertFalse($studentUser->can('delete', $enrollment));
        $this->assertFalse($admin->can('delete', $enrollment));
        $this->assertFalse(Gate::forUser($studentUser)->allows('delete', $enrollment));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $enrollment));
    }

    public function test_ordinary_administrative_workflows_only_change_status_without_hard_deleting_rows(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ]);

        $service = app(EnrollmentManagementService::class);
        $initialCount = Enrollment::count();
        $this->assertSame(1, $initialCount);

        // 1. Suspension workflow updates status and reason without deleting row
        $service->transitionStatus($enrollment, Enrollment::STATUS_SUSPENDED, $admin, [
            'reason' => 'Administrative suspension for review',
        ]);
        $this->assertSame(1, Enrollment::count());
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $enrollment->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => Enrollment::STATUS_SUSPENDED,
            'suspension_reason' => 'Administrative suspension for review',
        ]);

        // 2. Cancellation workflow updates status and reason without deleting row
        $service->transitionStatus($enrollment, Enrollment::STATUS_CANCELLED, $admin, [
            'reason' => 'Student requested cancellation',
        ]);
        $this->assertSame(1, Enrollment::count());
        $this->assertSame(Enrollment::STATUS_CANCELLED, $enrollment->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => Enrollment::STATUS_CANCELLED,
            'cancellation_reason' => 'Student requested cancellation',
        ]);

        // 3. Reactivation workflow updates status without deleting row
        $service->transitionStatus($enrollment, Enrollment::STATUS_ACTIVE, $admin);
        $service->extendExpiry($enrollment, now()->addDays(45), $admin);
        $this->assertSame(1, Enrollment::count());
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_suspension_records_reason_timestamp_and_audit_log(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $service = app(EnrollmentManagementService::class);
        $reason = 'Academic code violation: plagiarism in SEO assignment 2';

        $service->transitionStatus($enrollment, Enrollment::STATUS_SUSPENDED, $admin, [
            'reason' => $reason,
        ]);

        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $enrollment->status);
        $this->assertNotNull($enrollment->suspended_at);
        $this->assertSame($reason, $enrollment->suspension_reason);

        $auditLog = AuditLog::query()
            ->where('action', 'enrollment.status_changed')
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', (string) $enrollment->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $auditLog->properties['from'] ?? null);
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $auditLog->properties['to'] ?? null);
        $this->assertSame($reason, $auditLog->properties['reason'] ?? null);
    }

    public function test_cancellation_records_reason_timestamp_and_audit_log(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $service = app(EnrollmentManagementService::class);
        $reason = 'Student requested withdrawal and tuition refund';

        $service->transitionStatus($enrollment, Enrollment::STATUS_CANCELLED, $admin, [
            'reason' => $reason,
        ]);

        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_CANCELLED, $enrollment->status);
        $this->assertNotNull($enrollment->cancelled_at);
        $this->assertSame($reason, $enrollment->cancellation_reason);

        $auditLog = AuditLog::query()
            ->where('action', 'enrollment.status_changed')
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', (string) $enrollment->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $auditLog->properties['from'] ?? null);
        $this->assertSame(Enrollment::STATUS_CANCELLED, $auditLog->properties['to'] ?? null);
        $this->assertSame($reason, $auditLog->properties['reason'] ?? null);
    }

    public function test_reactivation_records_previous_status_and_acting_admin_in_audit_log(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $service = app(EnrollmentManagementService::class);

        // 1. Reactivate suspended enrollment
        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $service->transitionStatus($enrollment, Enrollment::STATUS_SUSPENDED, $admin, ['reason' => 'Temp suspension']);
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $enrollment->fresh()->status);

        // Advance timestamp so audit log created_at is distinct
        Carbon::setTestNow(now()->addSeconds(5));

        $service->transitionStatus($enrollment, Enrollment::STATUS_ACTIVE, $admin);

        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertNull($enrollment->suspended_at);
        $this->assertNull($enrollment->suspension_reason);

        $reactivateAudit = AuditLog::query()
            ->where('action', 'enrollment.status_changed')
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', (string) $enrollment->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($reactivateAudit);
        $this->assertSame($admin->id, $reactivateAudit->user_id);
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $reactivateAudit->properties['from'] ?? null);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $reactivateAudit->properties['to'] ?? null);

        // 2. Reinstate cancelled enrollment
        Carbon::setTestNow(now()->addSeconds(5));
        $service->transitionStatus($enrollment, Enrollment::STATUS_CANCELLED, $admin, ['reason' => 'Cancelled']);
        $this->assertSame(Enrollment::STATUS_CANCELLED, $enrollment->fresh()->status);

        Carbon::setTestNow(now()->addSeconds(5));
        $service->transitionStatus($enrollment, Enrollment::STATUS_ACTIVE, $admin);

        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertNull($enrollment->cancelled_at);
        $this->assertNull($enrollment->cancellation_reason);

        $reinstateAudit = AuditLog::query()
            ->where('action', 'enrollment.status_changed')
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', (string) $enrollment->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($reinstateAudit);
        $this->assertSame($admin->id, $reinstateAudit->user_id);
        $this->assertSame(Enrollment::STATUS_CANCELLED, $reinstateAudit->properties['from'] ?? null);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $reinstateAudit->properties['to'] ?? null);
    }

    public function test_expiry_extension_preserves_old_and_new_expiry_dates_in_audit_log(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $oldExpiry = now()->addDays(30);
        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'expires_at' => $oldExpiry,
        ]);

        $service = app(EnrollmentManagementService::class);
        $newExpiry = now()->addDays(90);

        $service->extendExpiry($enrollment, $newExpiry, $admin);

        $enrollment->refresh();
        $this->assertEquals($newExpiry->timestamp, $enrollment->expires_at->timestamp);

        $auditLog = AuditLog::query()
            ->where('action', 'enrollment.dates_updated')
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', (string) $enrollment->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame($oldExpiry->toIso8601String(), $auditLog->properties['old_expires_at'] ?? null);
        $this->assertSame($newExpiry->toIso8601String(), $auditLog->properties['new_expires_at'] ?? null);
    }

    // =========================================================================
    // TASK 4: Lifecycle Edge Cases & Status Consistency
    // =========================================================================

    public function test_future_start_dates_is_active_is_true_but_is_valid_is_false(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->addDays(7),
            'expires_at' => now()->addDays(60),
        ]);

        $this->assertTrue($enrollment->isActive());
        $this->assertFalse($enrollment->isValid());
    }

    public function test_exact_expiry_boundary_is_valid_inclusive_then_false_once_past(): void
    {
        $fixedNow = Carbon::parse('2026-06-01 12:00:00');
        Carbon::setTestNow($fixedNow);

        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => $fixedNow->copy()->subDays(10),
            'expires_at' => $fixedNow,
        ]);

        // Exactly at expiry (expires_at == now()): isValid is true (valid inclusive)
        $this->assertTrue($enrollment->isValid());

        // 1 second past expiry (expires_at < now()): isValid is false
        Carbon::setTestNow($fixedNow->copy()->addSecond());
        $this->assertFalse($enrollment->fresh()->isValid());
    }

    public function test_expired_record_is_active_is_false_and_is_valid_is_false(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_EXPIRED,
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($enrollment->isActive());
        $this->assertFalse($enrollment->isValid());
        $this->assertFalse(Enrollment::active()->where('id', $enrollment->id)->exists());
        $this->assertFalse(Enrollment::valid()->where('id', $enrollment->id)->exists());
    }

    public function test_suspended_record_is_active_is_false_and_is_valid_is_false(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_SUSPENDED,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertFalse($enrollment->isActive());
        $this->assertFalse($enrollment->isValid());
        $this->assertFalse(Enrollment::active()->where('id', $enrollment->id)->exists());
        $this->assertFalse(Enrollment::valid()->where('id', $enrollment->id)->exists());
    }

    public function test_active_valid_record_is_active_is_true_and_is_valid_is_true(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($enrollment->isActive());
        $this->assertTrue($enrollment->isValid());
        $this->assertTrue(Enrollment::active()->where('id', $enrollment->id)->exists());
        $this->assertTrue(Enrollment::valid()->where('id', $enrollment->id)->exists());
    }

    public function test_suspended_enrollment_with_extended_expiry_preserves_suspended_status(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_SUSPENDED,
            'suspended_at' => now()->subDay(),
            'suspension_reason' => 'Policy investigation',
            'expires_at' => now()->addDays(15),
        ]);

        $service = app(EnrollmentManagementService::class);
        $service->extendExpiry($enrollment, now()->addYear(), $admin);

        $enrollment->refresh();
        // Suspended status must be preserved; cannot accidentally reactivate without explicit transitionStatus
        $this->assertSame(Enrollment::STATUS_SUSPENDED, $enrollment->status);
        $this->assertFalse($enrollment->isValid());
    }

    public function test_completed_enrollment_reactivation_via_admin(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now()->subDays(5),
        ]);

        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_ACTIVE));

        $service = app(EnrollmentManagementService::class);
        $service->transitionStatus($enrollment, Enrollment::STATUS_ACTIVE, $admin);

        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertTrue($enrollment->isActive());
    }

    public function test_cancelled_enrollment_reinstatement_via_admin(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_CANCELLED,
            'cancelled_at' => now()->subDays(5),
            'cancellation_reason' => 'Administrative error',
        ]);

        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_ACTIVE));

        $service = app(EnrollmentManagementService::class);
        $service->transitionStatus($enrollment, Enrollment::STATUS_ACTIVE, $admin);

        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertTrue($enrollment->isActive());
    }

    public function test_consistency_between_is_active_is_valid_and_eloquent_scopes(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);

        // 1. Active with current date window -> active=true, valid=true
        [$u1, $p1] = $this->createStudentWithProfile($academy);
        $e1 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p1->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(30),
        ]);

        // 2. Active without any dates -> active=true, valid=true
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        $e2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p2->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        // 3. Active with future start date -> active=true, valid=false
        [$u3, $p3] = $this->createStudentWithProfile($academy);
        $e3 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p3->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->addDays(5),
            'expires_at' => now()->addDays(60),
        ]);

        // 4. Active with past expiry date -> active=true, valid=false
        [$u4, $p4] = $this->createStudentWithProfile($academy);
        $e4 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p4->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        // 5. Suspended -> active=false, valid=false
        [$u5, $p5] = $this->createStudentWithProfile($academy);
        $e5 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p5->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);

        // 6. Pending -> active=false, valid=false
        [$u6, $p6] = $this->createStudentWithProfile($academy);
        $e6 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p6->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_PENDING,
        ]);

        // 7. Expired -> active=false, valid=false
        [$u7, $p7] = $this->createStudentWithProfile($academy);
        $e7 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p7->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        // 8. Cancelled -> active=false, valid=false
        [$u8, $p8] = $this->createStudentWithProfile($academy);
        $e8 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p8->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        // Assert scopeActive matches isActive() across all records
        $scopeActiveIds = Enrollment::active()->pluck('id')->sort()->values()->all();
        $modelActiveIds = Enrollment::all()->filter(fn (Enrollment $e) => $e->isActive())->pluck('id')->sort()->values()->all();
        $this->assertSame($modelActiveIds, $scopeActiveIds);
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id, $e3->id, $e4->id], $scopeActiveIds);

        // Assert scopeValid matches isValid() across all records
        $scopeValidIds = Enrollment::valid()->pluck('id')->sort()->values()->all();
        $modelValidIds = Enrollment::all()->filter(fn (Enrollment $e) => $e->isValid())->pluck('id')->sort()->values()->all();
        $this->assertSame($modelValidIds, $scopeValidIds);
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id], $scopeValidIds);
    }

    // =========================================================================
    // TASK 5: Expiry Command Idempotency & Duplicate Notifications
    // =========================================================================

    public function test_process_expired_command_is_idempotent_and_does_not_emit_duplicate_notifications(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);

        [$u1, $p1] = $this->createStudentWithProfile($academy);
        [$u2, $p2] = $this->createStudentWithProfile($academy);
        [$u3, $p3] = $this->createStudentWithProfile($academy);

        // 2 expired active enrollments
        $expired1 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p1->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(3),
            'expires_at' => now()->subHours(2),
        ]);

        $expired2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p2->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(3),
            'expires_at' => now()->subMinutes(10),
        ]);

        // 1 non-expired active enrollment
        $activeStill = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $p3->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);

        // First run: processes exactly 2 enrollments and generates 2 notifications
        $exit1 = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit1);
        $this->assertStringContainsString('Processed 2 expired enrollments.', Artisan::output());

        $this->assertSame(Enrollment::STATUS_EXPIRED, $expired1->fresh()->status);
        $this->assertSame(Enrollment::STATUS_EXPIRED, $expired2->fresh()->status);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $activeStill->fresh()->status);

        $notificationCountAfterFirstRun = AcademyNotification::query()
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count();
        $this->assertSame(2, $notificationCountAfterFirstRun);

        // Second run: idempotent, processes 0 records, emits 0 new notifications
        $exit2 = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit2);
        $this->assertStringContainsString('Processed 0 expired enrollments.', Artisan::output());

        $notificationCountAfterSecondRun = AcademyNotification::query()
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count();
        $this->assertSame(2, $notificationCountAfterSecondRun);

        // Third run: remains strictly idempotent
        $exit3 = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit3);
        $this->assertStringContainsString('Processed 0 expired enrollments.', Artisan::output());

        $notificationCountAfterThirdRun = AcademyNotification::query()
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count();
        $this->assertSame(2, $notificationCountAfterThirdRun);
    }

    public function test_reactivation_with_new_future_expiry_generates_new_notification_on_subsequent_expiry(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin');
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $fixedStart = Carbon::parse('2026-06-01 10:00:00');
        Carbon::setTestNow($fixedStart);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'assigned_by' => $admin->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => $fixedStart->copy()->subDays(30),
            'expires_at' => $fixedStart->copy()->subMinutes(10),
        ]);

        // 1. First expiry: transitions status to expired and sends exactly 1 notification
        $exit1 = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit1);
        $this->assertSame(Enrollment::STATUS_EXPIRED, $enrollment->fresh()->status);

        $notificationsAfterFirstExpiry = AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->get();
        $this->assertCount(1, $notificationsAfterFirstExpiry);
        $this->assertStringContainsString($course->title, $notificationsAfterFirstExpiry->first()->message);

        // Repeated command execution sends 0 duplicates
        Artisan::call('enrollments:process-expired');
        $this->assertSame(1, AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count());

        // 2. Reactivate with a new future expiry
        $enrollment->refresh();
        $this->assertSame(Enrollment::STATUS_EXPIRED, $enrollment->status);
        $service = app(EnrollmentManagementService::class);
        $newExpiry = $fixedStart->copy()->addDays(30);
        $service->transitionStatus($enrollment, Enrollment::STATUS_ACTIVE, $admin);
        $service->extendExpiry($enrollment, $newExpiry, $admin);

        $fresh = $enrollment->fresh();
        $this->assertSame(Enrollment::STATUS_ACTIVE, $fresh->status);
        $this->assertTrue($fresh->expires_at->equalTo($newExpiry));

        // Command running before new expiry does not touch the active enrollment
        Carbon::setTestNow($fixedStart->copy()->addDays(15));
        Artisan::call('enrollments:process-expired');
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->fresh()->status);
        $this->assertSame(1, AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count());

        // 3. Fast-forward past the new expiry date
        Carbon::setTestNow($newExpiry->copy()->addDay());

        // 4. Command executes second expiry: generates a NEW notification for the new expiry event!
        $exit2 = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit2);
        $this->assertSame(Enrollment::STATUS_EXPIRED, $enrollment->fresh()->status);

        $notificationsAfterSecondExpiry = AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->orderBy('created_at')
            ->get();
        $this->assertCount(2, $notificationsAfterSecondExpiry);

        // Repeated execution produces 0 additional notifications
        $exit3 = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit3);
        $this->assertCount(2, AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->get());
    }

    public function test_concurrency_skips_enrollment_already_processed_in_another_transaction_without_duplicates(): void
    {
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subHour(),
        ]);

        // Simulate another concurrent transaction that processed this enrollment first
        DB::transaction(function () use ($enrollment, $academy, $studentUser, $course): void {
            $locked = Enrollment::query()
                ->where('id', $enrollment->id)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            $this->assertNotNull($locked);
            $locked->status = Enrollment::STATUS_EXPIRED;
            $locked->save();

            AcademyNotification::create([
                'academy_id' => $academy->id,
                'audience' => 'student',
                'target' => (string) $studentUser->id,
                'category' => 'enrollment',
                'title' => 'Enrollment Expired',
                'message' => "Your enrollment in {$course->title} has expired.",
                'created_by' => null,
                'created_at' => now(),
            ]);
        });

        $this->assertSame(1, AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count());

        // When command runs, it detects the enrollment is no longer active (or already processed), skips it with 0 duplicates
        $exit = Artisan::call('enrollments:process-expired');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Processed 0 expired enrollments.', Artisan::output());

        $this->assertSame(1, AcademyNotification::query()
            ->where('target', (string) $studentUser->id)
            ->where('category', 'enrollment')
            ->where('title', 'Enrollment Expired')
            ->count());
    }

    // =========================================================================
    // TASK 6: Fresh DB Migration Compatibility
    // =========================================================================

    public function test_fresh_db_migration_60_compatibility_without_preexisting_academies(): void
    {
        // 1. Delete all academies records to simulate a completely fresh DB before migration 60
        DB::table('academies')->delete();
        $this->assertSame(0, DB::table('academies')->count());

        // 2. Roll back migration 60
        $rollbackSteps = DB::table('migrations')
            ->where('id', '>=', function ($q) {
                $q->from('migrations')
                    ->where('migration', 'like', '%update_enrollments_table_for_phase25%')
                    ->select('id');
            })
            ->count();
        $exitRollback = Artisan::call('migrate:rollback', [
            '--step' => max(1, $rollbackSteps),
            '--force' => true,
        ]);
        $this->assertSame(0, $exitRollback);

        // Verify enrollments table is currently missing migration 60 columns
        $this->assertFalse(Schema::hasColumn('enrollments', 'cancellation_reason'));
        $this->assertFalse(Schema::hasColumn('enrollments', 'suspension_reason'));

        // 3. Re-run migration #60 on database with 0 academies rows
        $exitMigrate = Artisan::call('migrate', [
            '--force' => true,
        ]);
        $this->assertSame(0, $exitMigrate);

        // 4. Verify all columns and indices from migration 60 exist cleanly
        $expectedColumns = [
            'academy_id',
            'assigned_by',
            'starts_at',
            'expires_at',
            'cancelled_at',
            'cancellation_reason',
            'suspended_at',
            'suspension_reason',
            'completed_at',
            'notes',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('enrollments', $column),
                "Column '{$column}' should exist on enrollments table after migration #60."
            );
        }

        // Verify migration record is recorded in migrations table
        $this->assertTrue(
            DB::table('migrations')
                ->where('migration', 'like', '%update_enrollments_table_for_phase25%')
                ->exists()
        );
    }

    public function test_existing_database_migration_with_existing_enrollments_assigns_academy_id(): void
    {
        // 1. Ensure an academy exists
        $academy = Academy::factory()->create();
        [$course] = $this->createCourseHierarchy($academy);
        [$studentUser, $studentProfile] = $this->createStudentWithProfile($academy);

        // 2. Roll back migration #60
        $rollbackSteps = DB::table('migrations')
            ->where('id', '>=', function ($q) {
                $q->from('migrations')
                    ->where('migration', 'like', '%update_enrollments_table_for_phase25%')
                    ->select('id');
            })
            ->count();
        $exitRollback = Artisan::call('migrate:rollback', [
            '--step' => max(1, $rollbackSteps),
            '--force' => true,
        ]);
        $this->assertSame(0, $exitRollback);
        $this->assertFalse(Schema::hasColumn('enrollments', 'academy_id'));

        // 3. Insert an existing enrollment without academy_id (as present in pre-Phase 2.5 DB)
        $enrollmentId = (string) Str::uuid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'student_profile_id' => $studentProfile->id,
            'course_id' => $course->id,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(DB::table('enrollments')->where('id', $enrollmentId)->exists());

        // 4. Re-run migration #60
        $exitMigrate = Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_11_12_190000_update_enrollments_table_for_phase25.php',
            '--force' => true,
        ]);
        $this->assertSame(0, $exitMigrate);

        // 5. Verify academy_id was backfilled and status normalized
        $this->assertTrue(Schema::hasColumn('enrollments', 'academy_id'));
        $record = DB::table('enrollments')->where('id', $enrollmentId)->first();
        $this->assertSame($academy->id, $record->academy_id);
        $this->assertSame('active', $record->status);
    }
}
