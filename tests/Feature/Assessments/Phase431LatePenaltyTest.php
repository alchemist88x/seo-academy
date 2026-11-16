<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase431LatePenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected User $admin;

    protected User $trainer;

    protected User $student;

    protected Enrollment $enrollment;

    protected AssignmentManagementService $managementService;

    protected AssignmentGradingService $gradingService;

    protected AssignmentSubmissionService $submissionService;

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
            'name' => 'Late Penalty Academy',
            'slug' => 'penalty-'.Str::lower(Str::random(6)),
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
        $this->course->trainers()->attach($this->trainer->id, ['academy_id' => $this->academy->id]);

        $this->student = User::factory()->create(['is_active' => true]);
        $this->student->assignRole('student');
        $profile = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-PEN-'.Str::upper(Str::random(6)),
        ]);
        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->managementService = app(AssignmentManagementService::class);
        $this->gradingService = app(AssignmentGradingService::class);
        $this->submissionService = app(AssignmentSubmissionService::class);
    }

    public function test_no_penalty_when_submitted_before_or_at_due_date(): void
    {
        $dueDate = Carbon::parse('2026-11-20 18:00:00');
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Deadline Boundary Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'due_date' => $dueDate,
            'late_submission_policy' => Assignment::POLICY_DEDUCT_DAILY,
            'late_penalty_percent' => 10.0,
        ], $this->admin, $this->academy);

        // 24 hours before
        $this->assertEquals(0.00, $assignment->calculateLatePenalty(80.0, $dueDate->copy()->subDay()));

        // 1 hour before
        $this->assertEquals(0.00, $assignment->calculateLatePenalty(80.0, $dueDate->copy()->subHour()));

        // Exactly at due date
        $this->assertEquals(0.00, $assignment->calculateLatePenalty(80.0, $dueDate));
    }

    public function test_daily_penalty_24_hour_boundary_calculations(): void
    {
        $dueDate = Carbon::parse('2026-11-20 18:00:00');
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Daily Late Penalty Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'due_date' => $dueDate,
            'late_submission_policy' => Assignment::POLICY_DEDUCT_DAILY,
            'late_penalty_percent' => 15.0, // 15% per day
        ], $this->admin, $this->academy);

        $awarded = 90.0;

        // 1 second late => 1 day penalty (15% of 90 = 13.50)
        $this->assertEquals(13.50, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addSecond()));

        // 1 hour late => 1 day penalty (13.50)
        $this->assertEquals(13.50, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addHour()));

        // 12 hours late => 1 day penalty (13.50)
        $this->assertEquals(13.50, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addHours(12)));

        // Exactly 24 hours (86,400 seconds) late => 1 day penalty (13.50)
        $this->assertEquals(13.50, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDay()));

        // 24 hours and 1 second late => 2 days penalty (30% of 90 = 27.00)
        $this->assertEquals(27.00, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDay()->addSecond()));

        // Exactly 48 hours late => 2 days penalty (27.00)
        $this->assertEquals(27.00, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDays(2)));

        // 48 hours and 1 minute late => 3 days penalty (45% of 90 = 40.50)
        $this->assertEquals(40.50, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDays(2)->addMinute()));

        // 5 days late => 5 * 15% = 75% of 90 = 67.50
        $this->assertEquals(67.50, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDays(5)));
    }

    public function test_late_penalty_capped_at_awarded_points_and_never_negative(): void
    {
        $dueDate = Carbon::parse('2026-11-20 18:00:00');
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Capped Penalty Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'due_date' => $dueDate,
            'late_submission_policy' => Assignment::POLICY_DEDUCT_DAILY,
            'late_penalty_percent' => 20.0, // 20% per day
        ], $this->admin, $this->academy);

        $awarded = 75.0;

        // 10 days late => 10 * 20% = 200%, capped at 100% of awarded = 75.00
        $penalty = $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDays(10));
        $this->assertEquals(75.00, $penalty);

        // Score after penalty must be 0.00, never negative
        $finalScore = max(0.00, $awarded - $penalty);
        $this->assertEquals(0.00, $finalScore);

        // Zero awarded points
        $this->assertEquals(0.00, $assignment->calculateLatePenalty(0.0, $dueDate->copy()->addDays(2)));
    }

    public function test_flat_percentage_penalty_deduction(): void
    {
        $dueDate = Carbon::parse('2026-11-20 18:00:00');
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Flat Penalty Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'due_date' => $dueDate,
            'late_submission_policy' => Assignment::POLICY_DEDUCT_FLAT,
            'late_penalty_percent' => 25.0, // Flat 25%
        ], $this->admin, $this->academy);

        $awarded = 80.0;

        // 1 hour late => 25% of 80 = 20.00
        $this->assertEquals(20.00, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addHour()));

        // 5 days late => still flat 25% of 80 = 20.00
        $this->assertEquals(20.00, $assignment->calculateLatePenalty($awarded, $dueDate->copy()->addDays(5)));
    }

    public function test_policy_no_penalty_or_not_allowed(): void
    {
        $dueDate = Carbon::parse('2026-11-20 18:00:00');
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'No Penalty Policy Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'due_date' => $dueDate,
            'late_submission_policy' => Assignment::POLICY_ALLOWED_NO_PENALTY,
            'late_penalty_percent' => 50.0,
        ], $this->admin, $this->academy);

        // Penalty must be 0 even if submitted late
        $this->assertEquals(0.00, $assignment->calculateLatePenalty(100.0, $dueDate->copy()->addDays(2)));
    }

    public function test_assignment_grading_service_integrates_daily_late_penalty(): void
    {
        $dueDate = Carbon::now()->subDays(2)->subHours(3); // 2 days and 3 hours late = 3 days penalty

        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Integration Penalty Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'due_date' => $dueDate,
            'late_submission_policy' => Assignment::POLICY_DEDUCT_DAILY,
            'late_penalty_percent' => 10.0, // 10% per day x 3 days = 30% penalty
            'allow_multiple_files' => false,
            'allowed_file_types' => ['pdf'],
        ], $this->admin, $this->academy);

        // Submit now (which is 2 days and 3 hours after due date)
        $submission = $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Late submission content.',
        ]);

        $this->assertTrue($submission->is_late);

        // Grade with 90 raw points
        $evaluation = $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 90.0,
            'feedback' => 'Good work but submitted 3 days late.',
        ]);

        // 30% of 90 = 27.00 penalty
        // Final score: 90 - 27 = 63.00
        $this->assertEquals(90.00, (float) $evaluation->raw_score);
        $this->assertEquals(27.00, (float) $evaluation->late_penalty_applied);
        $this->assertEquals(63.00, (float) $evaluation->points_awarded);
        $this->assertEquals(63.00, (float) $evaluation->final_score);
        $this->assertEquals(63.00, (float) $evaluation->score_percentage);
        $this->assertTrue($evaluation->is_passed); // 63 >= 60 passing marks
        $this->assertEquals(63.00, (float) $submission->refresh()->grade);
    }
}
