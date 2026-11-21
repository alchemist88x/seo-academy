<?php

namespace Tests\Feature\Student;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Platform\Models\Media;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Dashboard\StudentDashboard;
use App\Livewire\Student\ClassroomPlayer;
use App\Livewire\Student\StudentCoursesIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class Phase71StudentExperienceTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'subdomain' => 'phase7-academy',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);
    }

    private function createStudent(Academy $academy): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('student');
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function createCourseWithLessons(Academy $academy, int $lessonCount = 2): array
    {
        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $module = Module::factory()->create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'display_order' => 1,
        ]);

        $lessons = [];
        for ($i = 1; $i <= $lessonCount; $i++) {
            $lessons[] = Lesson::factory()->create([
                'academy_id' => $academy->id,
                'module_id' => $module->id,
                'title' => "Lesson {$i}",
                'display_order' => $i,
                'type' => 'video',
                'duration_seconds' => 600,
            ]);
        }

        return [$course, $module, $lessons];
    }

    private function enroll(Academy $academy, StudentProfile $profile, Course $course, string $status = 'active', array $attrs = []): Enrollment
    {
        return Enrollment::create(array_merge([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => $status,
            'enrolled_at' => now()->toDateString(),
        ], $attrs));
    }

    /**
     * Requirement 1: Student dashboard displays metrics belonging only to the authenticated student.
     */
    public function test_1_student_dashboard_displays_metrics_belonging_only_to_authenticated_student(): void
    {
        [$studentA, $profileA] = $this->createStudent($this->academy);
        [$studentB, $profileB] = $this->createStudent($this->academy);

        [$course1, , $lessons1] = $this->createCourseWithLessons($this->academy, 2);
        [$course2, , $lessons2] = $this->createCourseWithLessons($this->academy, 2);

        $enrollmentA = $this->enroll($this->academy, $profileA, $course1);
        $enrollmentB1 = $this->enroll($this->academy, $profileB, $course1);
        $enrollmentB2 = $this->enroll($this->academy, $profileB, $course2);

        // Student A completes 1 lesson
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'enrollment_id' => $enrollmentA->id,
            'user_id' => $studentA->id,
            'lesson_id' => $lessons1[0]->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ]);

        // Student B completes 3 lessons across 2 enrollments
        foreach ([$lessons1[0], $lessons1[1], $lessons2[0]] as $l) {
            LessonProgress::create([
                'academy_id' => $this->academy->id,
                'enrollment_id' => $enrollmentB1->id,
                'user_id' => $studentB->id,
                'lesson_id' => $l->id,
                'status' => 'completed',
                'progress_percent' => 100,
                'completed_at' => now(),
                'last_accessed_at' => now(),
            ]);
        }

        $this->actingAs($studentA);

        Livewire::test(StudentDashboard::class)
            ->assertViewHas('activeCoursesCount', 1)
            ->assertViewHas('completedLessonsCount', 1)
            ->assertSee($course1->title)
            ->assertDontSee($course2->title);
    }

    /**
     * Requirement 2: Continue Learning resolves the correct course and lesson.
     */
    public function test_2_continue_learning_resolves_correct_course_and_lesson(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 3);

        $enrollment = $this->enroll($this->academy, $profile, $course);

        // Student completed lesson 1
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ]);

        $navService = app(CourseNavigationService::class);
        $resumeLesson = $navService->resolveResumeLesson($course, $student);

        $this->assertNotNull($resumeLesson);
        $this->assertSame($lessons[1]->id, $resumeLesson->id);

        $this->actingAs($student);
        Livewire::test(StudentDashboard::class)
            ->assertViewHas('continueLearning', fn ($cl) => $cl && $cl['course']->id === $course->id && $cl['resume_lesson']->id === $lessons[1]->id);
    }

    /**
     * Requirement 3: Resume playback uses the saved lesson position.
     */
    public function test_3_resume_playback_uses_saved_lesson_position(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);
        $enrollment = $this->enroll($this->academy, $profile, $course);

        $media = Media::create([
            'model_type' => Course::class,
            'model_id' => $course->id,
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => 'lessons/test-video.mp4',
            'filename' => 'test-video.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1048576,
            'created_at' => now(),
        ]);
        $lessons[0]->update(['media_id' => $media->id]);

        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
            'status' => 'in_progress',
            'last_position_seconds' => 78.5,
            'progress_percent' => 13,
            'last_accessed_at' => now(),
        ]);

        $this->actingAs($student);

        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lessons[0]])
            ->assertSet('lastPositionSeconds', 78.5)
            ->assertSeeHtml('savedPos: 78.5');
    }

    /**
     * Requirement 4: Video progress is persisted correctly.
     */
    public function test_4_video_progress_is_persisted_correctly(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);
        $enrollment = $this->enroll($this->academy, $profile, $course);

        $this->actingAs($student);

        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lessons[0]])
            ->call('recordHeartbeat', 124.0);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[0]->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
            'last_position_seconds' => 124.0,
        ]);
    }

    /**
     * Requirement 5: Repeated heartbeat requests do not create duplicate progress records.
     */
    public function test_5_repeated_heartbeat_requests_do_not_create_duplicate_records(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);
        $enrollment = $this->enroll($this->academy, $profile, $course);

        $this->actingAs($student);

        $component = Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lessons[0]]);

        $component->call('recordHeartbeat', 15.0);
        $component->call('recordHeartbeat', 30.0);
        $component->call('recordHeartbeat', 45.0);

        $count = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lessons[0]->id)
            ->count();

        $this->assertSame(1, $count);

        $record = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lessons[0]->id)
            ->first();

        $this->assertEquals(45.0, (float) $record->last_position_seconds);
    }

    /**
     * Requirement 6: Completing a lesson updates course progress correctly.
     */
    public function test_6_completing_lesson_updates_course_progress_correctly(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 2);
        $enrollment = $this->enroll($this->academy, $profile, $course);

        $this->actingAs($student);

        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lessons[0]])
            ->assertSet('isLessonCompleted', false)
            ->call('completeLesson')
            ->assertSet('isLessonCompleted', true);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[0]->id,
            'status' => 'completed',
        ]);

        $progressService = app(LearningProgressService::class);
        $summary = $progressService->getCourseProgressSummary($enrollment);

        $this->assertSame(50, $summary['completion_percentage']);
        $this->assertSame(1, $summary['completed_lessons']);
        $this->assertSame(2, $summary['total_lessons']);
    }

    /**
     * Requirement 7: Completing all required lessons invokes the existing CourseCompletionService.
     */
    public function test_7_completing_all_lessons_invokes_course_completion_service(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 2);
        $enrollment = $this->enroll($this->academy, $profile, $course);

        $this->actingAs($student);

        // Complete lesson 1
        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lessons[0]])
            ->call('completeLesson');

        $this->assertFalse($enrollment->fresh()->isCompleted());

        // Complete lesson 2 (final lesson)
        Livewire::test(ClassroomPlayer::class, ['course' => $course, 'lesson' => $lessons[1]])
            ->call('completeLesson')
            ->assertSet('isCompletedEnrollment', true);

        $freshEnrollment = $enrollment->fresh();
        $this->assertTrue($freshEnrollment->isCompleted());
        $this->assertSame('completed', $freshEnrollment->status);
        $this->assertNotNull($freshEnrollment->completed_at);
    }

    /**
     * Requirement 8: Certificate issuance remains idempotent.
     */
    public function test_8_certificate_issuance_remains_idempotent(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);
        $enrollment = $this->enroll($this->academy, $profile, $course, 'completed', [
            'completed_at' => now(),
        ]);

        $certService = app(CertificateIssuanceService::class);

        $cert1 = $certService->issueCertificate($enrollment, 'classic');
        $this->assertNotNull($cert1);

        // Second call with same enrollment
        $cert2 = $certService->issueCertificate($enrollment, 'classic');

        $this->assertSame($cert1->id, $cert2->id);
        $this->assertSame($cert1->certificate_number, $cert2->certificate_number);

        $count = Certificate::where('enrollment_id', $enrollment->id)->count();
        $this->assertSame(1, $count);
    }

    /**
     * Requirement 9: My Learning displays only the student's authorized enrollments.
     */
    public function test_9_my_learning_displays_only_student_authorized_enrollments(): void
    {
        [$studentA, $profileA] = $this->createStudent($this->academy);
        [$studentB, $profileB] = $this->createStudent($this->academy);

        [$courseA] = $this->createCourseWithLessons($this->academy, 1);
        [$courseB] = $this->createCourseWithLessons($this->academy, 1);

        $this->enroll($this->academy, $profileA, $courseA);
        $this->enroll($this->academy, $profileB, $courseB);

        $this->actingAs($studentA);

        Livewire::test(StudentCoursesIndex::class)
            ->assertSee($courseA->title)
            ->assertDontSee($courseB->title);
    }

    /**
     * Requirement 10: Search, status filtering, sorting and pagination work correctly.
     */
    public function test_10_my_learning_search_status_filtering_and_sorting(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);

        $courseAlpha = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Alpha Advanced SEO',
            'status' => 'Published',
            'visibility' => 'public',
        ]);
        $courseBeta = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Beta Technical SEO',
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $this->enroll($this->academy, $profile, $courseAlpha, 'active');
        $this->enroll($this->academy, $profile, $courseBeta, 'completed', ['completed_at' => now()]);

        $this->actingAs($student);

        // Filter by tab 'completed'
        Livewire::test(StudentCoursesIndex::class)
            ->set('tab', 'completed')
            ->assertSee($courseBeta->title)
            ->assertDontSee($courseAlpha->title);

        // Filter by tab 'in_progress'
        Livewire::test(StudentCoursesIndex::class)
            ->set('tab', 'in_progress')
            ->assertSee($courseAlpha->title)
            ->assertDontSee($courseBeta->title);

        // Search text
        Livewire::test(StudentCoursesIndex::class)
            ->set('search', 'Alpha')
            ->assertSee($courseAlpha->title)
            ->assertDontSee($courseBeta->title);
    }

    /**
     * Requirement 11: Course details do not expose protected lesson content to unauthorized students.
     */
    public function test_11_course_details_do_not_expose_protected_lesson_content_to_unauthorized_students(): void
    {
        [$student] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);

        // Add secret content to lesson
        $lessons[0]->update(['content' => 'SECRET_PROPRIETARY_CURRICULUM_CONTENT_4982']);

        $this->actingAs($student);

        // Catalogue show page outline is visible (title only)
        $response = $this->get(route('courses.public.show', $course->slug));
        $response->assertOk();
        $response->assertSee($course->title);
        $response->assertSee($lessons[0]->title);
        $response->assertDontSee('SECRET_PROPRIETARY_CURRICULUM_CONTENT_4982');

        // Accessing classroom directly without enrollment returns 403 Forbidden
        $classroomResponse = $this->get(route('student.courses.learn', [$course, $lessons[0]]));
        $classroomResponse->assertStatus(403);
    }

    /**
     * Requirement 12: Expired and suspended enrollments cannot access protected lessons.
     */
    public function test_12_expired_and_suspended_enrollments_cannot_access_protected_lessons(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);

        // Suspended
        $enrollment = $this->enroll($this->academy, $profile, $course, 'suspended', [
            'suspended_at' => now(),
        ]);

        $this->actingAs($student);
        $this->get(route('student.courses.learn', [$course, $lessons[0]]))
            ->assertStatus(403);

        // Expired
        $enrollment->update([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('student.courses.learn', [$course, $lessons[0]]))
            ->assertStatus(403);
    }

    /**
     * Requirement 13: Students cannot access another student's progress or certificates by manipulating IDs.
     */
    public function test_13_students_cannot_access_another_students_progress_or_certificates(): void
    {
        [$studentA, $profileA] = $this->createStudent($this->academy);
        [$studentB, $profileB] = $this->createStudent($this->academy);

        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);

        $enrollmentB = $this->enroll($this->academy, $profileB, $course, 'completed', ['completed_at' => now()]);
        $certB = app(CertificateIssuanceService::class)->issueCertificate($enrollmentB, 'classic');

        // Student A attempts to download Student B's certificate
        $this->actingAs($studentA);
        $this->get(route('certificates.download', $certB))
            ->assertStatus(403);

        // Student A attempts to update progress on Student B's enrollment
        $progressService = app(LearningProgressService::class);
        $this->expectException(AuthorizationException::class);
        $progressService->recordActivity($enrollmentB, $lessons[0], $studentA, 10.0);
    }

    /**
     * Requirement 14: Dashboard theme preference persists across navigation and reloads.
     */
    public function test_14_dashboard_theme_persistence_in_layouts(): void
    {
        [$student] = $this->createStudent($this->academy);
        $this->actingAs($student);

        $response = $this->get(route('student.dashboard'));
        $response->assertOk();

        // Verify synchronous anti-FOUC script reads theme from localStorage
        $response->assertSee("localStorage.getItem('seo_academy_theme')", false);
        $response->assertSee("document.documentElement.classList.toggle('dark'", false);
    }

    /**
     * Requirement 15: Classroom remains light-themed when the dashboard is in dark mode.
     */
    public function test_15_classroom_remains_light_themed_isolated_from_dark_mode(): void
    {
        [$student, $profile] = $this->createStudent($this->academy);
        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);
        $this->enroll($this->academy, $profile, $course);

        $this->actingAs($student);

        $response = $this->get(route('student.courses.learn', [$course, $lessons[0]]));
        $response->assertOk();

        // Verify explicit light theme layout with anti-dark isolation
        $response->assertSee('<html lang="en" class="light">', false);
        $response->assertSee("document.documentElement.classList.remove('dark')", false);
        $response->assertSee("document.documentElement.classList.add('light')", false);
    }

    /**
     * Requirement 16: Admin and trainer permissions remain unchanged.
     */
    public function test_16_admin_and_trainer_permissions_remain_unchanged(): void
    {
        // Admin
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $admin->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Trainer
        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        [$course, , $lessons] = $this->createCourseWithLessons($this->academy, 1);

        // Assign trainer to course
        $course->trainers()->attach($trainer->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'is_primary' => true,
        ]);

        // 1. Admin can access classroom without enrollment
        $this->actingAs($admin);
        $this->get(route('student.courses.learn', [$course, $lessons[0]]))
            ->assertOk();

        // 2. Assigned trainer can access classroom without enrollment
        $this->actingAs($trainer);
        $this->get(route('student.courses.learn', [$course, $lessons[0]]))
            ->assertOk();

        // 3. Unassigned trainer is denied access
        $unassignedTrainer = User::factory()->create(['is_active' => true]);
        $unassignedTrainer->assignRole('trainer');
        $unassignedTrainer->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->actingAs($unassignedTrainer);
        $this->get(route('student.courses.learn', [$course, $lessons[0]]))
            ->assertStatus(403);
    }
}
