<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Services\AssignmentGradeService;
use App\Domain\Assignments\Services\AssignmentGradingService;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase431GradingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected User $admin;

    protected User $trainer;

    protected User $student;

    protected Enrollment $enrollment;

    protected Assignment $assignment;

    protected AssignmentSubmissionService $submissionService;

    protected AssignmentGradingService $gradingService;

    protected AssignmentGradeService $gradeService;

    protected AssignmentManagementService $managementService;

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
            'name' => 'Snapshot Test Academy',
            'slug' => 'snapshot-test-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
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
            'student_code' => 'STU-SNAP-'.Str::upper(Str::random(6)),
        ]);
        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->managementService = app(AssignmentManagementService::class);
        $this->assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Technical Crawl Deliverable',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'is_mandatory' => true,
            'rubric_criteria' => [
                ['title' => 'Robots Directives', 'max_marks' => 60.0, 'description' => 'Disallow tags and sitemaps'],
                ['title' => 'Canonical Tags', 'max_marks' => 40.0, 'description' => 'Cross-domain canonicals'],
            ],
        ], $this->admin, $this->academy);

        $this->submissionService = app(AssignmentSubmissionService::class);
        $this->gradingService = app(AssignmentGradingService::class);
        $this->gradeService = app(AssignmentGradeService::class);
    }

    public function test_grading_snapshot_remains_consistent_when_assignment_max_marks_changes(): void
    {
        // 1. Student submits work
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Completed technical crawl deliverable.',
        ]);

        // 2. Grade assignment out of 100 with rubric
        $criteria = $this->assignment->rubricCriteria;
        $eval1 = $this->gradingService->grade($submission, $this->trainer, [
            'rubric_evaluations' => [
                $criteria[0]->id => ['awarded_marks' => 50.0, 'feedback' => 'Good robots.txt check.'],
                $criteria[1]->id => ['awarded_marks' => 35.0, 'feedback' => 'Accurate canonicals.'],
            ],
            'overall_feedback' => 'Solid technical work.',
            'internal_notes' => 'Trainer note: Very diligent student.',
        ]);

        // Assert initial evaluation values
        $this->assertSame('85.00', (string) $eval1->points_awarded);
        $this->assertSame('100.00', (string) $eval1->points_possible);
        $this->assertSame('85.00', (string) $eval1->score_percentage);
        $this->assertTrue($eval1->is_passed);
        $this->assertSame('Trainer note: Very diligent student.', $eval1->internal_notes);

        // 3. Admin alters assignment max marks to 50 (and passing marks to 30)
        $this->managementService->updateAssignment($this->assignment, [
            'max_marks' => 50.0,
            'passing_marks' => 30.0,
            'rubric_criteria' => [
                ['title' => 'Revised Single Criterion', 'max_marks' => 50.0],
            ],
        ], $this->admin);

        $this->assertSame('50.00', (string) $this->assignment->fresh()->max_marks);

        // 4. Verify historical evaluation retains 100 possible marks and 85% score percentage
        $evalFresh = $eval1->fresh();
        $this->assertSame('85.00', (string) $evalFresh->points_awarded);
        $this->assertSame('100.00', (string) $evalFresh->points_possible);
        $this->assertSame('85.00', (string) $evalFresh->score_percentage);

        // Rubric snapshot should retain original criteria and descriptions
        $this->assertCount(2, $evalFresh->rubric_snapshot);
        $this->assertSame('Robots Directives', $evalFresh->rubric_snapshot[0]['title']);
        $this->assertSame(60.0, (float) $evalFresh->rubric_snapshot[0]['max_marks']);
        $this->assertSame(50.0, (float) $evalFresh->rubric_snapshot[0]['awarded_marks']);

        // 5. Verify Gradebook integration (AssignmentGradeService) reads historical evaluation
        $summary = $this->gradeService->getEnrollmentAssignmentSummary($this->enrollment);
        $record = $summary->firstWhere('assignment_id', $this->assignment->id);

        $this->assertNotNull($record);
        $this->assertSame(100.0, $record['max_marks'], 'Gradebook summary must report the historical points possible.');
        $this->assertSame(85.0, $record['grade']);
        $this->assertSame(85.0, $record['score_percentage']);
        $this->assertTrue($record['is_passed']);

        // 6. Regrade the submission and verify both evaluations remain intact
        $submission->refresh();
        $eval2 = $this->gradingService->grade($submission, $this->admin, [
            'points_awarded' => 45.0,
            'is_override' => true,
            'override_reason' => 'Adjusted after curriculum resizing.',
        ]);

        $this->assertCount(2, $submission->fresh()->evaluations);
        $this->assertSame('45.00', (string) $eval2->points_awarded);
        $this->assertSame('50.00', (string) $eval2->points_possible);
        $this->assertSame('90.00', (string) $eval2->score_percentage);
    }
}
