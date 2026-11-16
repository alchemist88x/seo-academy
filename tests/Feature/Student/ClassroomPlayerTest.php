<?php

namespace Tests\Feature\Student;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Lessons\Models\LessonMaterial;
use App\Domain\Platform\Models\Media;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassroomPlayerTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    /** @var array<int, string> */
    private array $createdMediaPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'subdomain' => 'test-academy',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdMediaPaths as $path) {
            Storage::disk('private_media')->delete($path);
        }

        parent::tearDown();
    }

    private function createStudent(Academy $academy, ?Course $enrollInCourse = null, string $status = 'active', ?array $enrollmentAttrs = []): User
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

        if ($enrollInCourse !== null) {
            Enrollment::create(array_merge([
                'academy_id' => $academy->id,
                'student_profile_id' => $profile->id,
                'course_id' => $enrollInCourse->id,
                'status' => $status,
                'enrolled_at' => now()->toDateString(),
            ], $enrollmentAttrs));
        }

        return $user;
    }

    private function createAdmin(Academy $academy): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    private function createTrainer(Academy $academy, ?Course $assignToCourse = null): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('trainer');
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        if ($assignToCourse !== null) {
            $assignToCourse->trainers()->attach($user->id, [
                'id' => (string) Str::uuid(),
                'academy_id' => $academy->id,
                'is_primary' => true,
                'created_at' => now(),
            ]);
        }

        return $user;
    }

    private function createCourseWithCurriculum(Academy $academy, array $courseAttrs = []): array
    {
        $course = Course::factory()->create(array_merge([
            'academy_id' => $academy->id,
            'status' => 'Published',
            'visibility' => 'public',
            'is_sequential' => false,
        ], $courseAttrs));

        $module1 = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Module 1: Foundations',
            'description' => 'Introduction to core principles',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $lesson1 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module1->id,
            'title' => 'Lesson 1.1: Getting Started',
            'type' => 'text',
            'content' => 'Welcome to the course. Here is the introductory guide.',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'duration_seconds' => 300,
        ]);

        $lesson2 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module1->id,
            'title' => 'Lesson 1.2: Video Deep Dive',
            'type' => 'video',
            'display_order' => 2,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'duration_seconds' => 600,
        ]);

        $module2 = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Module 2: Advanced Topics',
            'description' => 'In-depth tactical training',
            'display_order' => 2,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $lesson3 = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module2->id,
            'title' => 'Lesson 2.1: PDF Case Studies',
            'type' => 'pdf',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'duration_seconds' => 450,
        ]);

        return [$course, $module1, $lesson1, $lesson2, $module2, $lesson3];
    }

    public function test_authorized_enrolled_student_can_access_classroom(): void
    {
        [$course, $module1, $lesson1] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'active');

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertOk();
        $response->assertSee($course->title);
        $response->assertSee($lesson1->title);
        $response->assertSee('Welcome to the course');
    }

    public function test_non_enrolled_student_receives_403_forbidden(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy); // Not enrolled in $course

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_student_without_student_profile_receives_403_forbidden(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('student');
        $user->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_suspended_enrollment_student_receives_403_forbidden(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'suspended', [
            'suspended_at' => now(),
            'suspension_reason' => 'Administrative review',
        ]);

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_cancelled_enrollment_student_receives_403_forbidden(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'cancelled', [
            'cancelled_at' => now(),
            'cancellation_reason' => 'Refunded',
        ]);

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_expired_enrollment_student_receives_403_forbidden(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'active', [
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(), // Expired yesterday
        ]);

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_future_enrollment_student_not_yet_started_receives_403_forbidden(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'active', [
            'starts_at' => now()->addDays(7), // Starts next week
        ]);

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_completed_enrollment_student_retains_permanent_read_only_access(): void
    {
        [$course, $module1, $lesson1, $lesson2] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'completed', [
            'completed_at' => now()->subWeek(),
        ]);

        $response = $this->actingAs($student)->get(route('student.courses.learn', $course));

        $response->assertOk();
        $response->assertSee($course->title);
        $response->assertSee('Course Completed • Read-Only');
        $response->assertSee('Read-only access');
    }

    public function test_cross_course_lesson_access_is_rejected_with_404(): void
    {
        [$courseA, , $lessonA1] = $this->createCourseWithCurriculum($this->academy, ['title' => 'Course Alpha']);
        [$courseB, , $lessonB1] = $this->createCourseWithCurriculum($this->academy, ['title' => 'Course Beta']);

        $student = $this->createStudent($this->academy, $courseA, 'active');

        // Student tries to access lesson from Course B through Course A's classroom route
        $response = $this->actingAs($student)->get(route('student.courses.learn', [$courseA, $lessonB1]));

        $response->assertNotFound();
    }

    public function test_sequential_course_locks_subsequent_lessons_until_preceding_lessons_completed(): void
    {
        [$course, $module1, $lesson1, $lesson2, $module2, $lesson3] = $this->createCourseWithCurriculum($this->academy, [
            'is_sequential' => true,
        ]);

        $student = $this->createStudent($this->academy, $course, 'active');

        // First lesson should be accessible
        $res1 = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson1]));
        $res1->assertOk();

        // Second lesson should be locked (403 forbidden) because lesson 1 is not completed
        $res2 = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson2]));
        $res2->assertForbidden();

        // Third lesson should also be locked
        $res3 = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson3]));
        $res3->assertForbidden();

        // Mark lesson 1 completed
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'user_id' => $student->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        // Second lesson should now be accessible
        $res2Unlocked = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson2]));
        $res2Unlocked->assertOk();

        // Third lesson should still be locked because lesson 2 is not completed
        $res3StillLocked = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson3]));
        $res3StillLocked->assertForbidden();
    }

    public function test_explicit_lesson_prerequisites_block_premature_access(): void
    {
        [$course, $module1, $lesson1, $lesson2] = $this->createCourseWithCurriculum($this->academy, [
            'is_sequential' => false, // Non-sequential course
        ]);

        // Explicitly set lesson 1 as prerequisite for lesson 2
        DB::table('lesson_prerequisites')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'lesson_id' => $lesson2->id,
            'prerequisite_lesson_id' => $lesson1->id,
            'created_at' => now(),
        ]);

        $student = $this->createStudent($this->academy, $course, 'active');

        // Direct navigation to lesson 2 without completing prerequisite lesson 1 should be denied
        $response = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson2]));
        $response->assertForbidden();

        // Complete prerequisite lesson 1
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'user_id' => $student->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        // Direct navigation to lesson 2 should now succeed
        $responseUnlocked = $this->actingAs($student)->get(route('student.courses.learn', [$course, $lesson2]));
        $responseUnlocked->assertOk();
    }

    public function test_assigned_trainer_can_access_classroom(): void
    {
        [$course, $module1, $lesson1] = $this->createCourseWithCurriculum($this->academy);
        $trainer = $this->createTrainer($this->academy, $course);

        $response = $this->actingAs($trainer)->get(route('student.courses.learn', $course));

        $response->assertOk();
        $response->assertSee($course->title);
    }

    public function test_unassigned_trainer_is_denied_classroom_access_with_403(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $unassignedTrainer = $this->createTrainer($this->academy); // Not assigned to $course

        $response = $this->actingAs($unassignedTrainer)->get(route('student.courses.learn', $course));

        $response->assertForbidden();
    }

    public function test_administrator_can_access_classroom(): void
    {
        [$course, $module1, $lesson1] = $this->createCourseWithCurriculum($this->academy);
        $admin = $this->createAdmin($this->academy);

        $response = $this->actingAs($admin)->get(route('student.courses.learn', $course));

        $response->assertOk();
        $response->assertSee($course->title);
    }

    public function test_student_dashboard_resume_link_navigates_to_classroom(): void
    {
        [$course] = $this->createCourseWithCurriculum($this->academy);
        $student = $this->createStudent($this->academy, $course, 'active');

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee(route('student.courses.learn', $course));
    }

    public function test_student_courses_index_resume_and_review_links(): void
    {
        [$courseActive] = $this->createCourseWithCurriculum($this->academy, ['title' => 'Active SEO Course']);
        [$courseCompleted] = $this->createCourseWithCurriculum($this->academy, ['title' => 'Completed SEO Course']);

        $student = $this->createStudent($this->academy, $courseActive, 'active');

        // Enroll in second course as completed
        Enrollment::create([
            'academy_id' => $this->academy->id,
            'student_profile_id' => $student->studentProfile->id,
            'course_id' => $courseCompleted->id,
            'status' => 'completed',
            'enrolled_at' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee('Resume Course');
        $response->assertSee('Review Course');
        $response->assertSee(route('student.courses.learn', $courseActive));
        $response->assertSee(route('student.courses.learn', $courseCompleted));
    }

    public function test_lesson_type_rendering_for_video_pdf_external_and_materials(): void
    {
        $course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $module = Module::create([
            'academy_id' => $this->academy->id,
            'course_id' => $course->id,
            'title' => 'Comprehensive Module',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        // 1. Video Lesson with Private Media
        $videoPath = 'tests/'.Str::uuid().'.mp4';
        Storage::disk('private_media')->put($videoPath, 'VIDEO_BYTES_SAMPLE');
        $this->createdMediaPaths[] = $videoPath;

        $videoMedia = Media::create([
            'model_type' => Course::class,
            'model_id' => $course->id,
            'collection' => 'videos',
            'disk' => 'private_media',
            'path' => $videoPath,
            'filename' => 'strategy.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 18,
            'created_at' => now(),
        ]);

        $videoLesson = Lesson::create([
            'academy_id' => $this->academy->id,
            'module_id' => $module->id,
            'title' => 'Video Strategy Lesson',
            'type' => 'video',
            'media_id' => $videoMedia->id,
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        // Attach Material to Video Lesson
        $pdfMatPath = 'tests/'.Str::uuid().'.pdf';
        Storage::disk('private_media')->put($pdfMatPath, 'PDF_MATERIAL_BYTES');
        $this->createdMediaPaths[] = $pdfMatPath;

        $matMedia = Media::create([
            'model_type' => Course::class,
            'model_id' => $course->id,
            'collection' => 'materials',
            'disk' => 'private_media',
            'path' => $pdfMatPath,
            'filename' => 'handout.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 18,
            'created_at' => now(),
        ]);

        LessonMaterial::create([
            'academy_id' => $this->academy->id,
            'lesson_id' => $videoLesson->id,
            'media_id' => $matMedia->id,
            'title' => 'Executive Summary Handout',
            'description' => 'Key takeaways reference sheet',
            'file_path' => $pdfMatPath,
            'file_size_bytes' => 18,
            'mime_type' => 'application/pdf',
            'display_order' => 1,
            'created_at' => now(),
        ]);

        // 2. External Resource Lesson
        $externalLesson = Lesson::create([
            'academy_id' => $this->academy->id,
            'module_id' => $module->id,
            'title' => 'External Docs Guide',
            'type' => 'external',
            'resource_reference' => 'https://example.com/interactive-guide',
            'display_order' => 2,
            'status' => 'Active',
            'visibility' => 'enrolled',
        ]);

        $student = $this->createStudent($this->academy, $course, 'active');

        // Test Video Lesson rendering
        $videoRes = $this->actingAs($student)->get(route('student.courses.learn', [$course, $videoLesson]));
        $videoRes->assertOk();
        $videoRes->assertSee('Video Strategy Lesson');
        $videoRes->assertSee(route('media.show', $videoMedia->id));
        $videoRes->assertSee('Executive Summary Handout');
        $videoRes->assertSee(route('media.show', $matMedia->id));

        // Test External Lesson rendering
        $externalRes = $this->actingAs($student)->get(route('student.courses.learn', [$course, $externalLesson]));
        $externalRes->assertOk();
        $externalRes->assertSee('External Docs Guide');
        $externalRes->assertSee('https://example.com/interactive-guide');
    }

    public function test_course_with_no_lessons_renders_empty_state_cleanly(): void
    {
        $emptyCourse = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $student = $this->createStudent($this->academy, $emptyCourse, 'active');

        $response = $this->actingAs($student)->get(route('student.courses.learn', $emptyCourse));

        $response->assertOk();
        $response->assertSee('No Curriculum Available');
    }
}
