<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase431FileTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected User $admin;

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
            'name' => 'File Transact Academy',
            'slug' => 'file-transact-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->student = User::factory()->create(['is_active' => true]);
        $this->student->assignRole('student');
        $profile = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-FLT-'.Str::upper(Str::random(6)),
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
            'title' => 'File Compensation Practical',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'status' => Assignment::STATUS_PUBLISHED,
            'allow_multiple_files' => true,
            'max_file_count' => 5,
            'max_file_size_mb' => 10,
            'allowed_file_types' => ['pdf', 'txt'],
        ], $this->admin, $this->academy);

        $this->submissionService = app(AssignmentSubmissionService::class);
    }

    public function test_successful_submission_preserves_all_files(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Delivered 2 files.',
            'files' => [
                ['name' => 'file1.txt', 'content' => 'Content 1', 'mime_type' => 'text/plain'],
                ['name' => 'file2.pdf', 'content' => '%PDF-1.4 Content 2', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $this->assertCount(2, $submission->attachments);

        foreach ($submission->attachments as $att) {
            $this->assertTrue(Storage::disk('private')->exists($att->storage_path));
        }
    }

    public function test_transaction_rollback_cleans_up_orphaned_files(): void
    {
        // Force an exception during transaction commit/insert
        $caught = false;

        // Count files on disk before attempt
        $initialFiles = Storage::disk('private')->allFiles('assignments/submissions');

        try {
            // Provide an invalid payload that causes a database error inside transaction
            // (e.g. mock DB or pass a closure that throws after storeAttachment)
            DB::listen(function ($query) {
                if (str_contains($query->sql, 'assignment_submission_attachments')) {
                    throw new QueryException('default', 'Simulated failure during attachment insert', [], new \Exception('Disk full'));
                }
            });

            $this->submissionService->submit($this->assignment, $this->student, [
                'content' => 'Test file rollback.',
                'files' => [
                    ['name' => 'temp1.txt', 'content' => 'Payload that should be deleted', 'mime_type' => 'text/plain'],
                ],
            ]);
        } catch (\Throwable $e) {
            $caught = true;
        }

        $this->assertTrue($caught);

        // Verify failure compensation: no orphaned files left on private disk
        $filesAfter = Storage::disk('private')->allFiles('assignments/submissions');
        $this->assertEquals($initialFiles, $filesAfter, 'Files created during the aborted transaction must be removed by failure compensation.');
    }

    public function test_historical_submission_files_remain_untouched_on_failed_resubmission(): void
    {
        // 1. Version 1 succeeds
        $v1 = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'V1 deliverable.',
            'files' => [
                ['name' => 'v1_permanent.pdf', 'content' => '%PDF-1.4 Permanent V1 data', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $v1Path = $v1->attachments->first()->storage_path;
        $this->assertTrue(Storage::disk('private')->exists($v1Path));

        // 2. Version 2 fails during database insert
        $failedCaught = false;
        try {
            DB::listen(function ($query) {
                if (str_contains($query->sql, 'assignment_submission_attachments')) {
                    throw new QueryException('default', 'Simulated failure during V2 attachment insert', [], new \Exception('Lock failure'));
                }
            });

            $this->submissionService->submit($this->assignment, $this->student, [
                'content' => 'V2 deliverable.',
                'files' => [
                    ['name' => 'v2_transient.txt', 'content' => 'Transient V2 data', 'mime_type' => 'text/plain'],
                ],
            ]);
        } catch (\Throwable $e) {
            $failedCaught = true;
        }

        $this->assertTrue($failedCaught);

        // Crucial: V1 file must NEVER be deleted by V2 failure compensation!
        $this->assertTrue(Storage::disk('private')->exists($v1Path), 'V1 attachment must remain intact on disk.');
    }

    public function test_retry_after_lock_failure_cleans_prior_attempt_and_commits_cleanly(): void
    {
        $attempt = 0;
        $firstAttemptPaths = [];

        // Simulate 1 lock error on first attempt, success on second
        $submission = $this->submissionService->runWithDatabaseRetries(function () use (&$attempt, &$firstAttemptPaths) {
            $attempt++;
            $attemptFiles = [];

            try {
                return DB::transaction(function () use (&$attempt, &$attemptFiles) {
                    $subId = (string) Str::uuid();
                    $path = 'assignments/submissions/'.$subId.'/test.txt';
                    Storage::disk('private')->put($path, 'Content attempt '.$attempt);
                    $attemptFiles[] = $path;

                    if ($attempt === 1) {
                        throw new QueryException('default', 'SELECT 1', [], new \Exception('SQLITE_BUSY: database is locked'));
                    }

                    return $path;
                });
            } catch (\Throwable $e) {
                foreach ($attemptFiles as $f) {
                    Storage::disk('private')->delete($f);
                }
                throw $e;
            }
        });

        $this->assertSame(2, $attempt);
        $this->assertTrue(Storage::disk('private')->exists($submission));
    }

    public function test_successful_submission_followed_by_notification_failure_preserves_files(): void
    {
        // Mock notification service to fail
        $mockNotification = \Mockery::mock(NotificationDispatcherService::class);
        $mockNotification->shouldReceive('send')->andThrow(new \RuntimeException('Mail/notification transport failure'));
        $this->app->instance(NotificationDispatcherService::class, $mockNotification);

        $submission = $this->submissionService->submit($this->assignment, $this->student, [
            'content' => 'Content with attachment where notification fails.',
            'files' => [
                ['name' => 'surviving_file.pdf', 'content' => '%PDF-1.4 Data that must survive', 'mime_type' => 'application/pdf'],
            ],
        ]);

        // Submission must be committed in DB
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id]);

        // The file must NOT be deleted by failure compensation because DB transaction committed!
        $att = $submission->attachments->first();
        $this->assertNotNull($att);
        $this->assertTrue(Storage::disk('private')->exists($att->storage_path), 'Committed submission file must survive post-commit notification errors.');
    }

    public function test_partial_multi_file_upload_failure_cleans_up_orphaned_files(): void
    {
        $initialFiles = Storage::disk('private')->allFiles('assignments/submissions');
        $caught = false;

        try {
            // Second file triggers an exception during attachment insert
            $insertCount = 0;
            DB::listen(function ($query) use (&$insertCount) {
                if (str_contains($query->sql, 'assignment_submission_attachments')) {
                    $insertCount++;
                    if ($insertCount === 2) {
                        throw new QueryException('default', 'Second attachment failed insert', [], new \Exception('Insert crash'));
                    }
                }
            });

            $this->submissionService->submit($this->assignment, $this->student, [
                'content' => 'Multi-file partial failure payload.',
                'files' => [
                    ['name' => 'first_written.txt', 'content' => 'First file written to disk', 'mime_type' => 'text/plain'],
                    ['name' => 'second_failing.txt', 'content' => 'Second file triggering DB error', 'mime_type' => 'text/plain'],
                ],
            ]);
        } catch (\Throwable $e) {
            $caught = true;
        }

        $this->assertTrue($caught);

        // First file must have been purged by failure compensation
        $filesAfter = Storage::disk('private')->allFiles('assignments/submissions');
        $this->assertEquals($initialFiles, $filesAfter, 'Partial upload failure must clean up any files written during the failed attempt.');
    }
}
