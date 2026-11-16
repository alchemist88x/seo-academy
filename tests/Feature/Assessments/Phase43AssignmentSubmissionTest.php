<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Phase43AssignmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected Course $otherCourse;

    protected User $admin;

    protected User $trainer;

    protected User $student;

    protected Enrollment $enrollment;

    protected Assignment $assignment;

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
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $this->otherCourse = Course::factory()->create([
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
            'student_code' => 'STU-P43-'.Str::upper(Str::random(6)),
        ]);
        $this->enrollment = Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $managementService = app(AssignmentManagementService::class);
        $this->assignment = $managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Core Web Vitals Audit',
            'description' => 'Analyze LCP, FID, and CLS for the client domain.',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'due_date' => Carbon::now()->addDays(5)->toDateTimeString(),
            'status' => Assignment::STATUS_PUBLISHED,
            'max_attempts' => 3,
            'allow_resubmission' => true,
            'max_file_count' => 3,
            'max_file_size_mb' => 10,
            'allowed_file_types' => ['pdf', 'docx', 'xlsx', 'txt'],
        ], $this->admin, $this->academy);

        $this->submissionService = app(AssignmentSubmissionService::class);
    }

    public function test_student_can_submit_valid_work_and_creates_version_1(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Completed Lighthouse audit. LCP is 2.1s, CLS is 0.05.',
            'student_notes' => 'Please check the spreadsheet attachment.',
            'files' => [
                [
                    'name' => 'audit_report.pdf',
                    'content' => '%PDF-1.4 audit report test data',
                    'mime_type' => 'application/pdf',
                ],
            ],
        ]);

        $this->assertInstanceOf(AssignmentSubmission::class, $submission);
        $this->assertSame(1, $submission->version_number);
        $this->assertNull($submission->previous_submission_id);
        $this->assertSame(AssignmentSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertFalse($submission->is_late);
        $this->assertNotNull($submission->submitted_at);
        $this->assertCount(1, $submission->attachments);

        $att = $submission->attachments->first();
        $this->assertSame('audit_report.pdf', $att->file_name);
        $this->assertSame('application/pdf', $att->mime_type);
        $this->assertNotNull($att->checksum);
    }

    public function test_resubmission_creates_version_2_preserving_history(): void
    {
        $v1 = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Initial Version 1 text.',
            'files' => [
                ['name' => 'v1.txt', 'content' => 'Version 1 notes', 'mime_type' => 'text/plain'],
            ],
        ]);

        $this->assertSame(1, $v1->version_number);

        $v2 = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Revised Version 2 text with fixes.',
            'files' => [
                ['name' => 'v2.pdf', 'content' => '%PDF-1.4 Version 2 PDF', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $this->assertSame(2, $v2->version_number);
        $this->assertSame($v1->id, $v2->previous_submission_id);
        $this->assertSame(AssignmentSubmission::STATUS_RESUBMITTED, $v2->status);

        // Verify Version 1 was untouched
        $v1Fresh = $v1->fresh(['attachments']);
        $this->assertSame('Initial Version 1 text.', $v1Fresh->content);
        $this->assertSame('v1.txt', $v1Fresh->attachments->first()->file_name);
        $this->assertSame(1, $v1Fresh->version_number);
    }

    public function test_student_cannot_submit_to_course_they_are_not_enrolled_in(): void
    {
        $managementService = app(AssignmentManagementService::class);
        $otherAssignment = $managementService->createAssignment([
            'course_id' => $this->otherCourse->id,
            'title' => 'Other Course Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You are not currently enrolled in this course.');

        $this->submissionService->submit($otherAssignment, $this->student, [
            'content' => 'Trying to submit to an un-enrolled course.',
        ]);
    }

    public function test_student_cannot_submit_to_unpublished_assignment(): void
    {
        $this->assignment->status = Assignment::STATUS_DRAFT;
        $this->assignment->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This assignment is not currently published.');

        $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Attempt on draft assignment.',
        ]);
    }

    public function test_max_attempts_is_strictly_enforced(): void
    {
        $this->assignment->max_attempts = 2;
        $this->assignment->save();

        // Attempt 1
        $this->submissionService->submit($this->assignment, $this->student, ['content' => 'Attempt 1']);
        // Attempt 2
        $this->submissionService->submit($this->assignment, $this->student, ['content' => 'Attempt 2']);

        // Attempt 3 must be rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Maximum submission attempts reached for this assignment.');

        $this->submissionService->submit($this->assignment, $this->student, ['content' => 'Attempt 3']);
    }

    public function test_resubmission_disallowed_setting_is_enforced(): void
    {
        $this->assignment->allow_resubmission = false;
        $this->assignment->save();

        // First submission succeeds
        $this->submissionService->submit($this->assignment, $this->student, ['content' => 'Initial Attempt']);

        // Second submission rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Resubmission is not permitted for this assignment.');

        $this->submissionService->submit($this->assignment, $this->student, ['content' => 'Second Attempt']);
    }

    public function test_late_submission_handling_and_hard_deadline_cutoff(): void
    {
        $this->assignment->due_date = Carbon::parse('2026-11-20 12:00:00');
        $this->assignment->late_submission_policy = Assignment::POLICY_HARD_DEADLINE;
        $this->assignment->late_hard_deadline = Carbon::parse('2026-11-22 12:00:00');
        $this->assignment->save();

        // 1. On-time submission: before due date
        $onTime = $this->submissionService->submit(
            $this->assignment,
            $this->student,
            ['content' => 'On time submission.'],
            Carbon::parse('2026-11-20 10:00:00')
        );
        $this->assertFalse($onTime->is_late);

        // 2. Late submission within window: after due date, before hard cutoff
        $late = $this->submissionService->submit(
            $this->assignment,
            $this->student,
            ['content' => 'Late submission within grace.'],
            Carbon::parse('2026-11-21 15:00:00')
        );
        $this->assertTrue($late->is_late);

        // 3. Past hard cutoff: must be strictly rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The submission deadline for this assignment has passed.');

        $this->submissionService->submit(
            $this->assignment,
            $this->student,
            ['content' => 'Submission after hard cutoff.'],
            Carbon::parse('2026-11-22 12:01:00')
        );
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has an unsupported extension (.exe)');

        $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Uploading executable.',
            'files' => [
                ['name' => 'malicious.exe', 'content' => 'MZ binary header', 'mime_type' => 'application/x-msdownload'],
            ],
        ]);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->assignment->max_file_size_mb = 1; // 1 MB limit
        $this->assignment->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the maximum size limit of 1 MB.');

        $largeContent = str_repeat('A', 1024 * 1024 + 100); // > 1MB

        $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Large file upload.',
            'files' => [
                ['name' => 'large.pdf', 'content' => $largeContent, 'mime_type' => 'application/pdf'],
            ],
        ]);
    }

    public function test_file_count_limit_is_enforced(): void
    {
        $this->assignment->max_file_count = 2;
        $this->assignment->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You may not upload more than 2 files.');

        $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Too many files.',
            'files' => [
                ['name' => 'f1.pdf', 'content' => '%PDF-1', 'mime_type' => 'application/pdf'],
                ['name' => 'f2.pdf', 'content' => '%PDF-2', 'mime_type' => 'application/pdf'],
                ['name' => 'f3.pdf', 'content' => '%PDF-3', 'mime_type' => 'application/pdf'],
            ],
        ]);
    }
}
