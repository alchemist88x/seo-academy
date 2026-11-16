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
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase431RecordProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected User $admin;

    protected User $trainer;

    protected User $student;

    protected Enrollment $enrollment;

    protected AssignmentManagementService $managementService;

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
            'name' => 'Protection Academy',
            'slug' => 'protect-'.Str::lower(Str::random(6)),
            'timezone' => 'America/New_York',
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
            'student_code' => 'STU-PROT-'.Str::upper(Str::random(6)),
        ]);
        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->managementService = app(AssignmentManagementService::class);
        $this->submissionService = app(AssignmentSubmissionService::class);
        $this->gradingService = app(AssignmentGradingService::class);
    }

    public function test_assignment_hard_deletion_prohibited_when_submissions_exist(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Protected Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        // Submit work
        $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Student deliverable',
        ]);

        $this->assertDatabaseHas('assignment_submissions', ['assignment_id' => $assignment->id]);

        // Attempt deletion of assignment
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot delete an assignment with existing student submissions');

        $assignment->delete();
    }

    public function test_assignment_deletion_allowed_when_no_submissions_exist(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Unsubmitted Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $assignmentId = $assignment->id;
        $assignment->delete();

        $this->assertDatabaseMissing('assignments', ['id' => $assignmentId]);
    }

    public function test_student_deletion_prohibited_when_submissions_exist(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Student Submissions Protect User',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Work from student',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot delete a user with recorded assignment submissions');

        $this->student->delete();
    }

    public function test_evaluator_deletion_prohibited_when_evaluations_exist(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Evaluations Protect Trainer User',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $submission = $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Gradable submission',
        ]);

        $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 85.0,
            'feedback' => 'Well done',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot delete an evaluator with recorded grading history');

        $this->trainer->delete();
    }

    public function test_timezone_formatting_and_utc_persistence(): void
    {
        // 18:00 UTC on 2026-11-20
        // In America/New_York (EST, UTC-5), this is 13:00 (1:00 PM)
        $utcDate = Carbon::parse('2026-11-20 18:00:00', 'UTC');

        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Timezone Test Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'due_date' => $utcDate,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        // Academy timezone is America/New_York
        $formatted = $assignment->formatInAcademyTimezone($assignment->due_date);
        $this->assertStringContainsString('Nov 20, 2026 13:00', $formatted);
        $this->assertStringContainsString('(America/New_York)', $formatted);

        // Null deadline handling
        $this->assertEquals('No deadline', $assignment->formatInAcademyTimezone(null));

        // Verify that database storage is strictly UTC
        $rawRow = DB::table('assignments')->where('id', $assignment->id)->first();
        $this->assertEquals('2026-11-20 18:00:00', $rawRow->due_date);
    }

    public function test_database_level_foreign_key_blocks_direct_query_builder_assignment_deletion(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Direct Query Protection Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Deliverable preventing raw SQL delete.',
        ]);

        // Attempt raw SQL / query builder deletion (bypassing Eloquent hooks)
        $caught = false;
        try {
            DB::table('assignments')->where('id', $assignment->id)->delete();
        } catch (QueryException $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Database foreign key constraint must block direct query-builder deletion of an assignment with submissions.');
        $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
    }

    public function test_database_level_foreign_key_blocks_direct_query_builder_user_deletion(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Direct User Delete Protection Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $submission = $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Deliverable from student.',
        ]);

        $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 85.0,
        ]);

        // Attempt raw query-builder deletion of student
        $studentCaught = false;
        try {
            DB::table('users')->where('id', $this->student->id)->delete();
        } catch (QueryException $e) {
            $studentCaught = true;
        }
        $this->assertTrue($studentCaught, 'Database foreign key constraint must block direct deletion of a student user with submissions.');

        // Attempt raw query-builder deletion of trainer/evaluator
        $trainerCaught = false;
        try {
            DB::table('users')->where('id', $this->trainer->id)->delete();
        } catch (QueryException $e) {
            $trainerCaught = true;
        }
        $this->assertTrue($trainerCaught, 'Database foreign key constraint must block direct deletion of an evaluator user with evaluations.');
    }

    public function test_account_deactivation_and_assignment_archiving_remain_functional(): void
    {
        $assignment = $this->managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Legitimate Admin Workflow Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $submission = $this->submissionService->submit($assignment, $this->student, [
            'content' => 'Deliverable content.',
        ]);

        $this->gradingService->grade($submission, $this->trainer, [
            'points_awarded' => 90.0,
        ]);

        // 1. Archiving assignment must succeed cleanly without deletion errors
        $assignment->update(['status' => Assignment::STATUS_ARCHIVED]);
        $this->assertSame(Assignment::STATUS_ARCHIVED, $assignment->fresh()->status);
        $this->assertTrue($assignment->fresh()->isArchived());

        // 2. Deactivating student must succeed cleanly
        $this->student->update(['is_active' => false]);
        $this->assertFalse((bool) $this->student->fresh()->is_active);

        // 3. Deactivating trainer must succeed cleanly
        $this->trainer->update(['is_active' => false]);
        $this->assertFalse((bool) $this->trainer->fresh()->is_active);

        // Submissions and evaluations remain completely intact
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id]);
        $this->assertDatabaseHas('assignment_evaluations', ['submission_id' => $submission->id]);
    }
}
