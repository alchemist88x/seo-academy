<?php

namespace Tests\Feature\Projects;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectAchievement;
use App\Domain\Projects\Models\ProjectEvaluation;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Projects\Services\ProjectEvaluationService;
use App\Domain\Projects\Services\ProjectSubmissionService;
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

class ProjectEvaluationAndGradingTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected User $assignedTrainer;

    protected User $unassignedTrainer;

    protected User $studentUser;

    protected StudentProfile $studentProfile;

    protected Course $course;

    protected PracticalProject $project;

    protected ProjectSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Evaluation Academy',
            'subdomain' => 'seo-evaluation',
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

        // Assigned Trainer
        $this->assignedTrainer = User::factory()->create(['is_active' => true]);
        $this->assignedTrainer->assignRole('trainer');
        $this->assignedTrainer->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Unassigned Trainer
        $this->unassignedTrainer = User::factory()->create(['is_active' => true]);
        $this->unassignedTrainer->assignRole('trainer');
        $this->unassignedTrainer->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Course
        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Advanced On-Page & Technical SEO',
            'status' => 'published',
        ]);
        $this->course->trainers()->attach($this->assignedTrainer->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
        ]);

        // Student
        $this->studentUser = User::factory()->create(['is_active' => true]);
        $this->studentUser->assignRole('student');
        $this->studentUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->studentProfile = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentUser->id,
            'academy_id' => $this->academy->id,
            'student_code' => 'STU-EVAL-01',
            'active' => true,
        ]);
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $this->studentProfile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Practical Project with Criteria
        $projectService = app(PracticalProjectService::class);
        $this->project = $projectService->createProject($this->academy, $this->adminUser, [
            'title' => 'Content Optimization & Keyword Clustering Audit',
            'category' => PracticalProject::CATEGORY_CONTENT_OPTIMIZATION,
            'difficulty' => PracticalProject::DIFFICULTY_INTERMEDIATE,
            'course_id' => $this->course->id,
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => PracticalProject::STATUS_PUBLISHED,
            'criteria' => [
                ['title' => 'Search Intent Alignment', 'max_marks' => 50.0, 'weight' => 50.0],
                ['title' => 'Semantic Keyword Coverage', 'max_marks' => 50.0, 'weight' => 50.0],
            ],
        ]);

        // Student Submission
        $submissionService = app(ProjectSubmissionService::class);
        $this->submission = $submissionService->submit($this->project, $this->studentUser, [
            'website_url' => 'https://content-hub-sample.org',
            'content' => 'Comprehensive content strategy optimizing 15 core landing pages for commercial intent.',
        ]);
    }

    public function test_trainer_assignment_scoping_enforced(): void
    {
        $evaluationService = app(ProjectEvaluationService::class);

        // 1. Assigned Trainer CAN evaluate
        $evaluationService->authorizeEvaluator($this->submission, $this->assignedTrainer);
        $this->assertTrue(true);

        // 2. Admin CAN evaluate
        $evaluationService->authorizeEvaluator($this->submission, $this->adminUser);
        $this->assertTrue(true);

        // 3. Unassigned Trainer CANNOT evaluate
        $this->expectException(DomainException::class);
        $evaluationService->authorizeEvaluator($this->submission, $this->unassignedTrainer);
    }

    public function test_student_cannot_evaluate_own_submission(): void
    {
        $this->expectException(DomainException::class);
        $evaluationService = app(ProjectEvaluationService::class);
        $evaluationService->authorizeEvaluator($this->submission, $this->studentUser);
    }

    public function test_evaluation_criteria_and_grading_calculation(): void
    {
        $evaluationService = app(ProjectEvaluationService::class);

        $crit1 = $this->project->criteria[0];
        $crit2 = $this->project->criteria[1];

        $eval = $evaluationService->evaluate($this->submission, $this->assignedTrainer, [
            'decision' => ProjectEvaluation::STATUS_APPROVED,
            'criteria_ratings' => [
                $crit1->id => ['awarded_marks' => 45.0, 'feedback' => 'Exceptional intent mapping.'],
                $crit2->id => ['awarded_marks' => 40.0, 'feedback' => 'Good LSI keyword inclusion.'],
            ],
            'trainer_feedback' => 'Masterful content overhaul.',
            'improvement_recommendations' => 'Consider expanding FAQ schema sections.',
        ]);

        $this->assertEquals(85.0, (float) $eval->points_awarded);
        $this->assertEquals(100.0, (float) $eval->points_possible);
        $this->assertEquals(85.0, (float) $eval->score_percentage);
        $this->assertTrue($eval->is_passed);
        $this->assertEquals('approved', $eval->status);

        $this->submission->refresh();
        $this->assertEquals(85.0, (float) $this->submission->final_score);
        $this->assertTrue($this->submission->is_passed);
        $this->assertEquals(ProjectSubmission::STATUS_APPROVED, $this->submission->status);
    }

    public function test_revision_workflow_requires_reason_and_updates_status(): void
    {
        $evaluationService = app(ProjectEvaluationService::class);

        // Revision without reason fails
        try {
            $evaluationService->evaluate($this->submission, $this->assignedTrainer, [
                'decision' => ProjectEvaluation::STATUS_REVISION_REQUESTED,
                'revision_reason' => '',
            ]);
            $this->fail('Expected InvalidArgumentException for empty revision reason.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        // Revision with valid reason succeeds
        $eval = $evaluationService->evaluate($this->submission, $this->assignedTrainer, [
            'decision' => ProjectEvaluation::STATUS_REVISION_REQUESTED,
            'revision_reason' => 'Missing semantic TF-IDF gap analysis spreadsheet.',
        ]);

        $this->assertEquals(ProjectEvaluation::STATUS_REVISION_REQUESTED, $eval->status);
        $this->assertEquals('Missing semantic TF-IDF gap analysis spreadsheet.', $eval->revision_reason);

        $this->submission->refresh();
        $this->assertEquals(ProjectSubmission::STATUS_REVISION_REQUESTED, $this->submission->status);
    }

    public function test_approval_workflow_generates_achievement_badge(): void
    {
        $evaluationService = app(ProjectEvaluationService::class);

        $crit1 = $this->project->criteria[0];
        $crit2 = $this->project->criteria[1];

        $evaluationService->evaluate($this->submission, $this->assignedTrainer, [
            'decision' => ProjectEvaluation::STATUS_APPROVED,
            'criteria_ratings' => [
                $crit1->id => ['awarded_marks' => 50.0],
                $crit2->id => ['awarded_marks' => 45.0],
            ],
            'trainer_feedback' => 'Superb project execution.',
        ]);

        $this->assertDatabaseHas('project_achievements', [
            'project_id' => $this->project->id,
            'submission_id' => $this->submission->id,
            'user_id' => $this->studentUser->id,
            'status' => ProjectAchievement::STATUS_ISSUED,
        ]);

        $achievement = ProjectAchievement::where('submission_id', $this->submission->id)->first();
        $this->assertNotNull($achievement);
        $this->assertStringStartsWith('ACH-', $achievement->achievement_number);
        $this->assertNotEmpty($achievement->verification_code);
    }

    public function test_evaluation_history_integrity(): void
    {
        $evaluationService = app(ProjectEvaluationService::class);

        // First evaluation: Revision requested
        $eval1 = $evaluationService->evaluate($this->submission, $this->assignedTrainer, [
            'decision' => ProjectEvaluation::STATUS_REVISION_REQUESTED,
            'revision_reason' => 'First attempt requires adjustment.',
        ]);

        // Second evaluation: Approved
        $eval2 = $evaluationService->evaluate($this->submission, $this->assignedTrainer, [
            'decision' => ProjectEvaluation::STATUS_APPROVED,
            'points_awarded' => 90.0,
            'trainer_feedback' => 'Approved on second review.',
        ]);

        $this->submission->refresh();
        $this->assertCount(2, $this->submission->evaluations);
        $this->assertEquals($eval2->id, $this->submission->latestEvaluation->id);
        $this->assertEquals(ProjectEvaluation::STATUS_APPROVED, $this->submission->latestEvaluation->status);
    }
}
