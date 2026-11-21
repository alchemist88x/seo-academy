<?php

namespace Tests\Feature\Analytics;

use App\Domain\Academies\Models\Academy;
use App\Domain\Analytics\Services\CourseAnalyticsService;
use App\Domain\Analytics\Services\StudentAnalyticsService;
use App\Domain\Analytics\Services\TrainerAnalyticsService;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Livewire\Admin\Analytics\CourseAnalyticsView;
use App\Livewire\Trainer\TrainerAnalyticsDashboard;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CourseAndStudentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected User $trainerUser;

    protected Course $assignedCourse;

    protected Course $unassignedCourse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Analytics Academy',
            'subdomain' => 'course-analytics',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        // Admin
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Trainer
        $this->trainerUser = User::factory()->create(['is_active' => true]);
        $this->trainerUser->assignRole('trainer');
        $this->trainerUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);
        TrainerProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->trainerUser->id,
            'academy_id' => $this->academy->id,
            'active' => true,
        ]);

        // Assigned Course
        $this->assignedCourse = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Assigned Trainer Course',
            'status' => 'Published',
        ]);
        $this->assignedCourse->trainers()->attach($this->trainerUser->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        // Unassigned Course
        $this->unassignedCourse = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Unassigned Admin Course',
            'status' => 'Published',
        ]);
    }

    protected function createStudentWithEnrollment(Course $course): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('student');
        $user->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $this->academy->id,
            'user_id' => $user->id,
            'active' => true,
        ]);

        $enrollment = Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return [$user, $profile, $enrollment];
    }

    public function test_course_level_analytics_and_abandonment_identification(): void
    {
        $module = Module::factory()->create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->assignedCourse->id,
            'display_order' => 1,
        ]);

        $lesson1 = Lesson::factory()->create([
            'academy_id' => $this->academy->id,
            'module_id' => $module->id,
            'title' => 'Easy Lesson',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $lesson2 = Lesson::factory()->create([
            'academy_id' => $this->academy->id,
            'module_id' => $module->id,
            'title' => 'Hard Lesson Dropoff',
            'display_order' => 2,
            'status' => 'Active',
        ]);

        [$s1User, $s1Profile, $e1] = $this->createStudentWithEnrollment($this->assignedCourse);
        [$s2User, $s2Profile, $e2] = $this->createStudentWithEnrollment($this->assignedCourse);

        // Lesson 1: both completed
        LessonProgress::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'enrollment_id' => $e1->id,
            'user_id' => $s1User->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ]);
        LessonProgress::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'enrollment_id' => $e2->id,
            'user_id' => $s2User->id,
            'lesson_id' => $lesson1->id,
            'status' => 'completed',
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ]);

        // Lesson 2: both in-progress (abandoned / dropoff)
        LessonProgress::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'enrollment_id' => $e1->id,
            'user_id' => $s1User->id,
            'lesson_id' => $lesson2->id,
            'status' => 'in_progress',
            'last_accessed_at' => now()->subDays(5),
        ]);
        LessonProgress::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'enrollment_id' => $e2->id,
            'user_id' => $s2User->id,
            'lesson_id' => $lesson2->id,
            'status' => 'in_progress',
            'last_accessed_at' => now()->subDays(5),
        ]);

        $service = app(CourseAnalyticsService::class);
        $metrics = $service->getCourseMetrics($this->assignedCourse);

        $this->assertSame(2, $metrics['total_enrolled']);
        $this->assertSame(2, $metrics['active_enrollments']);
        $this->assertCount(1, $metrics['modules_breakdown']);
        $this->assertCount(2, $metrics['lessons_breakdown']);

        // Check abandonment
        $this->assertNotEmpty($metrics['highest_abandonment_lessons']);
        $this->assertSame($lesson2->title, $metrics['highest_abandonment_lessons'][0]['title']);
        $this->assertSame(2, $metrics['highest_abandonment_lessons'][0]['in_progress_count']);
    }

    public function test_question_performance_diagnostic(): void
    {
        [$s1User, $s1Profile, $e1] = $this->createStudentWithEnrollment($this->assignedCourse);
        [$s2User, $s2Profile, $e2] = $this->createStudentWithEnrollment($this->assignedCourse);

        $module = Module::factory()->create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->assignedCourse->id,
            'display_order' => 1,
        ]);

        $quiz = Quiz::create([
            'id' => (string) Str::uuid(),
            'course_id' => $this->assignedCourse->id,
            'module_id' => $module->id,
            'title' => 'Technical Quiz',
            'status' => 'published',
            'passing_percentage' => 70,
        ]);

        // Attempt 1
        $att1 = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $e1->id,
            'user_id' => $s1User->id,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'score_percentage' => 50,
            'passed' => false,
            'started_at' => now(),
            'submitted_at' => now(),
            'total_points' => 10,
            'earned_points' => 5,
        ]);

        // Attempt 2
        $att2 = AssessmentAttempt::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'assessment_id' => $quiz->id,
            'enrollment_id' => $e2->id,
            'user_id' => $s2User->id,
            'status' => AssessmentAttempt::STATUS_EVALUATED,
            'score_percentage' => 40,
            'passed' => false,
            'started_at' => now(),
            'submitted_at' => now(),
            'total_points' => 10,
            'earned_points' => 4,
        ]);

        $qId = (string) Str::uuid();

        // Both failed this question
        AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $att1->id,
            'canonical_question_id' => $qId,
            'is_correct' => false,
            'question_snapshot' => ['title' => 'What is Canonical Tag?'],
        ]);

        AssessmentAttemptAnswer::create([
            'id' => (string) Str::uuid(),
            'attempt_id' => $att2->id,
            'canonical_question_id' => $qId,
            'is_correct' => false,
            'question_snapshot' => ['title' => 'What is Canonical Tag?'],
        ]);

        $service = app(CourseAnalyticsService::class);
        $poorQuestions = $service->getPoorPerformingQuestions($this->assignedCourse);

        $this->assertCount(1, $poorQuestions);
        $this->assertSame(100.0, $poorQuestions[0]['failure_rate']);
        $this->assertSame('What is Canonical Tag?', $poorQuestions[0]['question_text']);
    }

    public function test_student_level_analytics_and_timeline(): void
    {
        [$sUser, $sProfile, $enrollment] = $this->createStudentWithEnrollment($this->assignedCourse);

        $service = app(StudentAnalyticsService::class);

        // Admin can view student analytics
        $data = $service->getStudentAnalytics($sProfile, $this->adminUser);

        $this->assertSame($sProfile->id, $data['student']['id']);
        $this->assertSame($sUser->name, $data['student']['name']);
        $this->assertCount(1, $data['courses']);
        $this->assertSame($this->assignedCourse->title, $data['courses'][0]['course_title']);
    }

    public function test_trainer_scoping_and_cross_student_isolation(): void
    {
        // Student A: enrolled in assigned course
        [$sAUser, $sAProfile, $eA] = $this->createStudentWithEnrollment($this->assignedCourse);

        // Student B: enrolled ONLY in unassigned course
        [$sBUser, $sBProfile, $eB] = $this->createStudentWithEnrollment($this->unassignedCourse);

        $studentService = app(StudentAnalyticsService::class);

        // 1. Trainer CAN view Student A
        $dataA = $studentService->getStudentAnalytics($sAProfile, $this->trainerUser);
        $this->assertSame($sAUser->name, $dataA['student']['name']);
        $this->assertCount(1, $dataA['courses']);
        $this->assertSame($this->assignedCourse->title, $dataA['courses'][0]['course_title']);

        // 2. Trainer CANNOT view Student B (unassigned) -> throws AuthorizationException
        $this->expectException(AuthorizationException::class);
        $studentService->getStudentAnalytics($sBProfile, $this->trainerUser);
    }

    public function test_student_cannot_view_another_student_analytics(): void
    {
        [$sAUser, $sAProfile] = $this->createStudentWithEnrollment($this->assignedCourse);
        [$sBUser, $sBProfile] = $this->createStudentWithEnrollment($this->assignedCourse);

        $studentService = app(StudentAnalyticsService::class);

        // Student A trying to view Student B's analytics
        $this->expectException(AuthorizationException::class);
        $studentService->getStudentAnalytics($sBProfile, $sAUser);
    }

    public function test_trainer_analytics_dashboard_scoping(): void
    {
        // 1 student in assigned course
        $this->createStudentWithEnrollment($this->assignedCourse);

        // 1 student in unassigned course
        $this->createStudentWithEnrollment($this->unassignedCourse);

        $trainerService = app(TrainerAnalyticsService::class);
        $metrics = $trainerService->getTrainerAnalytics($this->trainerUser, $this->academy);

        // Trainer should only see 1 course and 1 student
        $this->assertSame(1, $metrics['total_assigned_courses']);
        $this->assertSame(1, $metrics['total_distinct_students']);
        $this->assertSame('Assigned Trainer Course', $metrics['courses'][0]['title']);
    }

    public function test_course_analytics_livewire_component_unauthorized_rejection(): void
    {
        // Trainer trying to access unassigned course
        $this->actingAs($this->trainerUser);

        Livewire::test(CourseAnalyticsView::class, ['course' => $this->unassignedCourse])
            ->assertStatus(403);
    }

    public function test_course_analytics_livewire_component_renders_for_authorized_trainer(): void
    {
        $this->actingAs($this->trainerUser);

        Livewire::test(CourseAnalyticsView::class, ['course' => $this->assignedCourse])
            ->assertStatus(200)
            ->assertSee('Assigned Trainer Course')
            ->assertSee('Completion Rate')
            ->assertSee('Average Progress');
    }

    public function test_trainer_dashboard_livewire_component_renders(): void
    {
        $this->actingAs($this->trainerUser);

        Livewire::test(TrainerAnalyticsDashboard::class)
            ->assertStatus(200)
            ->assertSee('Teaching Performance')
            ->assertSee('Assigned Courses')
            ->assertSee('Total Students');
    }
}
