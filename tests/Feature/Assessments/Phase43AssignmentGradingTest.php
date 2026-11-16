<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentGradingService;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Phase43AssignmentGradingTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected Module $module;

    protected Lesson $lesson;

    protected User $admin;

    protected User $trainer;

    protected User $otherTrainer;

    protected User $student;

    protected User $supportStaff;

    protected Enrollment $enrollment;

    protected Assignment $assignment;

    protected AssignmentSubmissionService $submissionService;

    protected AssignmentGradingService $gradingService;

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
            'title' => 'Technical SEO Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);

        $this->lesson = Lesson::create([
            'academy_id' => $this->academy->id,
            'module_id' => $this->module->id,
            'title' => 'Site Speed Lesson',
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

        $this->otherTrainer = User::factory()->create(['is_active' => true]);
        $this->otherTrainer->assignRole('trainer');

        $this->student = User::factory()->create(['is_active' => true]);
        $this->student->assignRole('student');
        $profile = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-P43-'.Str::upper(Str::random(6)),
        ]);
        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->supportStaff = User::factory()->create(['is_active' => true]);
        $this->supportStaff->assignRole('support_staff');

        $managementService = app(AssignmentManagementService::class);
        $this->assignment = $managementService->createAssignment([
            'course_id' => $this->course->id,
            'module_id' => $this->module->id,
            'title' => 'Sitemap & Robots Audit',
            'max_marks' => 100.0,
            'passing_marks' => 70.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'is_mandatory' => true,
            'rubric_criteria' => [
                ['title' => 'XML Sitemap Analysis', 'max_marks' => 50.0],
                ['title' => 'Robots.txt Directive Check', 'max_marks' => 50.0],
            ],
        ], $this->admin, $this->academy);

        $this->submissionService = app(AssignmentSubmissionService::class);
        $this->gradingService = app(AssignmentGradingService::class);
    }

    public function test_assigned_trainer_can_grade_submission_with_rubric(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Completed robots.txt and sitemap audit. Found no blocking directives.',
        ]);

        $criteria = $this->assignment->rubricCriteria;

        $evaluation = $this->gradingService->grade($submission, $this->trainer, [
            'rubric_evaluations' => [
                $criteria[0]->id => ['awarded_marks' => 45.0, 'feedback' => 'Good sitemap hierarchy.'],
                $criteria[1]->id => ['awarded_marks' => 40.0, 'feedback' => 'Correctly flagged Disallow: /admin.'],
            ],
            'overall_feedback' => 'Great practical deliverable.',
            'internal_notes' => 'Solid technical understanding.',
        ]);

        $this->assertInstanceOf(AssignmentEvaluation::class, $evaluation);
        $this->assertSame('85.00', (string) $evaluation->points_awarded);
        $this->assertSame('100.00', (string) $evaluation->points_possible);
        $this->assertSame('85.00', (string) $evaluation->score_percentage);
        $this->assertTrue($evaluation->is_passed);
        $this->assertSame('Great practical deliverable.', $evaluation->overall_feedback);
        $this->assertSame('Solid technical understanding.', $evaluation->internal_notes);

        // Verify rubric snapshot preserved
        $this->assertIsArray($evaluation->rubric_snapshot);
        $this->assertCount(2, $evaluation->rubric_snapshot);
        $this->assertSame('XML Sitemap Analysis', $evaluation->rubric_snapshot[0]['title']);
        $this->assertSame(45.0, (float) $evaluation->rubric_snapshot[0]['awarded_marks']);

        // Verify submission status updated
        $this->assertSame(AssignmentSubmission::STATUS_GRADED, $submission->fresh()->status);
        $this->assertSame('85.00', (string) $submission->fresh()->grade);
    }

    public function test_unassigned_trainer_cannot_grade_submission(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Deliverable text.',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You are not authorized to grade assignments for this course.');

        $this->gradingService->grade($submission, $this->otherTrainer, [
            'points_awarded' => 80.0,
        ]);
    }

    public function test_support_staff_cannot_grade_submission(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Deliverable text.',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You do not possess grading authority.');

        $this->gradingService->grade($submission, $this->supportStaff, [
            'points_awarded' => 80.0,
        ]);
    }

    public function test_marks_cannot_exceed_maximum(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Deliverable text.',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be between 0 and 100.00');

        $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 120.0, // Exceeds max 100
        ]);
    }

    public function test_late_penalty_deduction_is_applied_during_grading(): void
    {
        $this->assignment->due_date = now()->subDays(2);
        $this->assignment->late_submission_policy = Assignment::POLICY_ALLOWED_PENALTY;
        $this->assignment->late_penalty_percent = 10.00; // 10% penalty
        $this->assignment->save();

        $submission = $this->submissionService->submit(
            $this->assignment,
            $this->student,
            ['content' => 'Late submission deliverable.'],
            now() // Late
        );
        $this->assertTrue($submission->is_late);

        $evaluation = $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 80.0, // 80 - 10%(8.0) = 72.0
        ]);

        $this->assertSame('72.00', (string) $evaluation->points_awarded);
        $this->assertSame('72.00', (string) $evaluation->score_percentage);
    }

    public function test_revision_request_and_regrading_preserves_history(): void
    {
        // 1. Initial Submission
        $v1 = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Incomplete crawl data.',
        ]);

        // 2. Trainer requests revision
        $revEval = $this->gradingService->requestRevision(
            $v1,
            $this->trainer,
            'Missing robots.txt user-agent analysis. Please add section 3 and resubmit.'
        );

        $this->assertSame(AssignmentEvaluation::STATUS_REVISION_REQUESTED, $revEval->status);
        $this->assertSame(AssignmentSubmission::STATUS_REVISION_REQUESTED, $v1->fresh()->status);
        $this->assertFalse($revEval->is_passed);

        // 3. Student submits Version 2
        $v2 = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Complete crawl data with User-Agent Googlebot analysis added.',
        ]);

        $this->assertSame(2, $v2->version_number);
        $this->assertSame($v1->id, $v2->previous_submission_id);

        // 4. Trainer grades Version 2
        $gradeEval = $this->gradingService->grade($v2, $this->trainer, [
            'points_awarded' => 90.0,
            'overall_feedback' => 'Much better, comprehensive analysis.',
        ]);

        $this->assertSame('90.00', (string) $gradeEval->points_awarded);
        $this->assertTrue($gradeEval->is_passed);
        $this->assertSame(AssignmentSubmission::STATUS_GRADED, $v2->fresh()->status);

        // 5. Verify Version 1 evaluation record remains preserved
        $this->assertCount(1, $v1->evaluations);
        $this->assertSame(AssignmentEvaluation::STATUS_REVISION_REQUESTED, $v1->evaluations->first()->status);
        $this->assertCount(1, $v2->evaluations);
        $this->assertSame(AssignmentEvaluation::STATUS_GRADED, $v2->evaluations->first()->status);
    }

    public function test_admin_grade_override_records_explanation_and_audit(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Deliverable text.',
        ]);

        // Trainer grades 65 (fail, passing is 70)
        $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 65.0,
        ]);
        $this->assertFalse($submission->fresh()->latestEvaluation->is_passed);

        // Admin overrides to 75 (pass)
        $overrideEval = $this->gradingService->grade($submission, $this->admin, [
            'points_awarded' => 75.0,
            'is_override' => true,
            'override_reason' => 'Administrative grade appeal granted due to documented client crawl blocker.',
        ]);

        $this->assertTrue($overrideEval->is_override);
        $this->assertSame('Administrative grade appeal granted due to documented client crawl blocker.', $overrideEval->override_reason);
        $this->assertTrue($overrideEval->is_passed);
        $this->assertSame('75.00', (string) $submission->fresh()->grade);

        // Two distinct evaluations must exist
        $this->assertCount(2, $submission->fresh()->evaluations);
    }

    public function test_mandatory_assignment_evaluates_course_completion(): void
    {
        // Mark lesson as completed
        LessonProgress::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Passing deliverable content.',
        ]);

        // Not yet completed before grading
        $this->assertSame(Enrollment::STATUS_ACTIVE, $this->enrollment->fresh()->status);

        // Grade with passing mark (80 >= 70)
        $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 80.0,
        ]);

        // Enrollment should now be completed!
        $this->assertSame(Enrollment::STATUS_COMPLETED, $this->enrollment->fresh()->status);
        $this->assertNotNull($this->enrollment->fresh()->completed_at);
    }
}
