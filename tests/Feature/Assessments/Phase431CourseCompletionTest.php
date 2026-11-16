<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentGradingService;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase431CourseCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected Module $module;

    protected Lesson $lesson;

    protected User $admin;

    protected User $trainer;

    protected User $student;

    protected Enrollment $enrollment;

    protected Assignment $assignment1;

    protected AssignmentSubmissionService $submissionService;

    protected AssignmentGradingService $gradingService;

    protected CourseCompletionService $completionService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);

        $this->seed(RoleSeeder::class);

        $this->academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $this->module = Module::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'title' => 'Technical SEO Fundamentals',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $this->lesson = Lesson::create([
            'academy_id' => $this->academy->id,
            'module_id' => $this->module->id,
            'title' => 'Crawl Optimization',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->trainer = User::factory()->create(['is_active' => true]);
        $this->trainer->assignRole('trainer');
        $this->course->trainers()->attach($this->trainer->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $this->student = User::factory()->create(['is_active' => true]);
        $this->student->assignRole('student');
        $profile = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-P431-'.Str::upper(Str::random(6)),
        ]);
        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        // Complete lesson progress baseline
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $managementService = app(AssignmentManagementService::class);
        $this->assignment1 = $managementService->createAssignment([
            'course_id' => $this->course->id,
            'module_id' => $this->module->id,
            'title' => 'Mandatory Site Audit Practical',
            'max_marks' => 100.0,
            'passing_marks' => 70.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'is_mandatory' => true,
        ], $this->admin, $this->academy);

        $this->submissionService = app(AssignmentSubmissionService::class);
        $this->gradingService = app(AssignmentGradingService::class);
        $this->completionService = app(CourseCompletionService::class);
    }

    public function test_no_submission_course_incomplete(): void
    {
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
        $this->assertFalse($this->completionService->completeCourse($this->enrollment));
        $this->assertSame(Enrollment::STATUS_ACTIVE, $this->enrollment->fresh()->status);
    }

    public function test_submission_exists_but_no_evaluation_course_incomplete(): void
    {
        $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'First attempt work.']);
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
        $this->assertSame(Enrollment::STATUS_ACTIVE, $this->enrollment->fresh()->status);
    }

    public function test_latest_submission_awaiting_grading_course_incomplete(): void
    {
        $sub = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'First attempt work.']);
        $this->assertSame(AssignmentSubmission::STATUS_SUBMITTED, $sub->status);
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
    }

    public function test_version_1_passed_version_2_ungraded_course_incomplete(): void
    {
        // 1. Version 1 submitted and passed
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work.']);
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 85.0]);
        $this->assertTrue($this->completionService->evaluateCourseCompletion($this->enrollment));

        // 2. Student resubmits as Version 2 (e.g. for extra credit or revised findings), currently ungraded
        $v2 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V2 revised work.']);
        $this->assertSame(2, $v2->version_number);
        $this->assertSame(AssignmentSubmission::STATUS_RESUBMITTED, $v2->status);

        // Crucial test: V1 passed, but latest version V2 is ungraded -> COURSE MUST BE INCOMPLETE!
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
    }

    public function test_version_1_passed_version_2_failed_course_incomplete(): void
    {
        // 1. Version 1 passed
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work.']);
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 85.0]);

        // 2. Version 2 submitted and failed
        $v2 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V2 broken work.']);
        $this->gradingService->grade($v2, $this->trainer, ['points_awarded' => 50.0]); // Passing mark is 70

        // Crucial test: V1 passed, but latest version V2 failed -> COURSE MUST BE INCOMPLETE!
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
    }

    public function test_version_1_passed_version_2_passed_course_completes(): void
    {
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work.']);
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 80.0]);

        $v2 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V2 improved work.']);
        $this->gradingService->grade($v2, $this->trainer, ['points_awarded' => 95.0]);

        $this->assertTrue($this->completionService->evaluateCourseCompletion($this->enrollment));
        $this->assertTrue($this->completionService->completeCourse($this->enrollment));
        $this->assertSame(Enrollment::STATUS_COMPLETED, $this->enrollment->fresh()->status);
    }

    public function test_latest_version_has_revision_requested_course_incomplete(): void
    {
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'Draft work.']);
        $this->gradingService->requestRevision($v1, $this->trainer, 'Please expand section 2.');

        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
    }

    public function test_latest_evaluation_is_failed_admin_override_course_incomplete(): void
    {
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work.']);

        // Trainer passed student with 75
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 75.0]);
        $this->assertTrue($this->completionService->evaluateCourseCompletion($this->enrollment));

        // Admin override downgrades to 60 (failed after academic audit)
        $this->gradingService->grade($v1, $this->admin, [
            'points_awarded' => 60.0,
            'is_override' => true,
            'override_reason' => 'Plagiarism in section 3 verified.',
        ]);

        // Crucial test: Older evaluation was passing, but latest evaluation failed -> COURSE INCOMPLETE!
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));
    }

    public function test_latest_evaluation_is_passing_regrade_course_completes(): void
    {
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work.']);

        // Initial grade 65 (fail)
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 65.0]);
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));

        // Regrade to 78 (pass)
        $this->gradingService->grade($v1, $this->trainer, [
            'points_awarded' => 78.0,
            'overall_feedback' => 'Rechecked crawl analysis; criteria met.',
        ]);

        $this->assertTrue($this->completionService->evaluateCourseCompletion($this->enrollment));
        $this->assertTrue($this->completionService->completeCourse($this->enrollment));
        $this->assertSame(Enrollment::STATUS_COMPLETED, $this->enrollment->fresh()->status);
    }

    public function test_multiple_mandatory_assignments_all_must_satisfy_rule(): void
    {
        $managementService = app(AssignmentManagementService::class);
        $assignment2 = $managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Second Mandatory Practical',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'is_mandatory' => true,
        ], $this->admin, $this->academy);

        // Assignment 1 passed
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'A1 work.']);
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 85.0]);

        // Assignment 2 unsubmitted -> Incomplete
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));

        // Assignment 2 submitted but failed -> Incomplete
        $v2 = $this->submissionService->submit($assignment2, $this->student, ['content' => 'A2 work.']);
        $this->gradingService->grade($v2, $this->trainer, ['points_awarded' => 40.0]);
        $this->assertFalse($this->completionService->evaluateCourseCompletion($this->enrollment));

        // Assignment 2 regraded and passed -> Complete
        $this->gradingService->grade($v2, $this->trainer, ['points_awarded' => 75.0]);
        $this->assertTrue($this->completionService->evaluateCourseCompletion($this->enrollment));
    }

    public function test_course_completion_and_certificate_issuance_idempotency(): void
    {
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work.']);
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 90.0]);

        // First completion
        $this->assertTrue($this->completionService->completeCourse($this->enrollment));
        $completedAt = $this->enrollment->fresh()->completed_at;
        $this->assertNotNull($completedAt);

        // Certificate should exist
        $cert1 = Certificate::where('enrollment_id', $this->enrollment->id)->first();
        $this->assertNotNull($cert1);

        // Second completion call (idempotent)
        $this->assertTrue($this->completionService->completeCourse($this->enrollment));
        $this->assertSame($completedAt->toIso8601String(), $this->enrollment->fresh()->completed_at->toIso8601String());

        // Assert strictly no duplicate certificate
        $this->assertSame(1, Certificate::where('enrollment_id', $this->enrollment->id)->count());
    }

    public function test_exact_enrollment_isolation_and_database_uniqueness(): void
    {
        // 1. Complete assignment in enrollment A
        $v1 = $this->submissionService->submit($this->assignment1, $this->student, ['content' => 'V1 work for Enrollment A.']);
        $this->gradingService->grade($v1, $this->trainer, ['points_awarded' => 90.0]);

        $this->assertTrue($this->completionService->evaluateCourseCompletion($this->enrollment));

        // 2. Prove database-level guarantee: cannot create duplicate enrollment for same student and course
        $duplicateAttemptCaught = false;
        try {
            Enrollment::create([
                'academy_id' => $this->academy->id,
                'course_id' => $this->course->id,
                'student_profile_id' => $this->enrollment->student_profile_id,
                'status' => Enrollment::STATUS_ACTIVE,
                'enrolled_at' => now(),
            ]);
        } catch (QueryException $e) {
            $duplicateAttemptCaught = true;
        }
        $this->assertTrue($duplicateAttemptCaught, 'Database unique constraint must prohibit multiple enrollments for the same student profile and course.');

        // 3. Create a second course with a separate enrollment B
        $courseB = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);
        $moduleB = Module::create([
            'academy_id' => $this->academy->id,
            'course_id' => $courseB->id,
            'title' => 'Course B Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);
        $lessonB = Lesson::create([
            'academy_id' => $this->academy->id,
            'module_id' => $moduleB->id,
            'title' => 'Course B Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);
        $enrollmentB = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $courseB->id,
            'student_profile_id' => $this->enrollment->student_profile_id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'enrollment_id' => $enrollmentB->id,
            'lesson_id' => $lessonB->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $assignmentB = app(AssignmentManagementService::class)->createAssignment([
            'course_id' => $courseB->id,
            'module_id' => $moduleB->id,
            'title' => 'Course B Mandatory Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 70.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'is_mandatory' => true,
        ], $this->admin, $this->academy);

        // Crucial: Enrollment B must NOT inherit Enrollment A's assignment completion
        $this->assertFalse($this->completionService->evaluateCourseCompletion($enrollmentB));
    }
}
