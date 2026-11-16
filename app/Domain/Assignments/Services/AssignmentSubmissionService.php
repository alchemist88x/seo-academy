<?php

namespace App\Domain\Assignments\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Models\AssignmentSubmissionAttachment;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Platform\Services\AuditLogService;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use DomainException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDOException;

class AssignmentSubmissionService
{
    /**
     * Standard MIME types mapping for allowed assignment file extensions.
     */
    public const EXTENSION_MIME_MAP = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    public function __construct(
        protected AuditLogService $auditLogService,
        protected NotificationDispatcherService $notificationService
    ) {}

    /**
     * Submit work for an assignment (creates a new versioned submission).
     *
     * @param array{
     *     content?: ?string,
     *     student_notes?: ?string,
     *     files?: array<int, UploadedFile|array{name: string, content: string, mime_type?: string}>
     * } $data
     */
    public function submit(Assignment $assignment, User $student, array $data, ?Carbon $at = null): AssignmentSubmission
    {
        $now = $at ?? Carbon::now();

        // 1. Verify student role & enrollment
        if (! $student->is_active || ! $student->hasRole('student')) {
            throw new DomainException('Only active students can submit assignments.');
        }

        $enrollment = Enrollment::query()
            ->where('student_profile_id', $student->studentProfile?->id)
            ->where('course_id', $assignment->course_id)
            ->allowsCourseAccess()
            ->first();

        if (! $enrollment) {
            throw new DomainException('You are not currently enrolled in this course.');
        }

        // 2. Validate Assignment availability & publication status
        if (! $assignment->isPublished()) {
            throw new DomainException('This assignment is not currently published.');
        }

        if (! $assignment->isAvailable($now)) {
            if ($assignment->available_from !== null && $now->lt($assignment->available_from)) {
                throw new DomainException('This assignment is not yet available for submission.');
            }
            throw new DomainException('This assignment is closed and no longer accepting submissions.');
        }

        // 3. Evaluate Deadline & Late submission policy
        $isLate = false;
        if ($assignment->isPastDue($now)) {
            if (! $assignment->isLateSubmissionAllowed($now)) {
                throw new DomainException('The submission deadline for this assignment has passed.');
            }
            $isLate = true;
        }

        // 4. Validate Content requirements
        $content = trim((string) ($data['content'] ?? ''));
        $files = $data['files'] ?? [];

        if ($assignment->assignment_type === Assignment::TYPE_TEXT_ONLY && empty($content)) {
            throw new InvalidArgumentException('Written submission content is required.');
        }

        if ($assignment->assignment_type === Assignment::TYPE_FILE_ONLY && empty($files)) {
            throw new InvalidArgumentException('At least one file attachment is required.');
        }

        if (empty($content) && empty($files)) {
            throw new InvalidArgumentException('You must provide written content or upload a file.');
        }

        // 5. Validate file constraints
        $this->validateFiles($assignment, $files);

        // 6. Execute atomic submission creation with bounded retry and failure compensation
        return $this->runWithDatabaseRetries(function () use ($assignment, $student, $enrollment, $content, $data, $files, $isLate, $now) {
            $attemptWrittenFiles = [];
            $transactionCommitted = false;
            $submission = null;
            $newVersion = 1;

            try {
                $submission = DB::transaction(function () use ($assignment, $student, $enrollment, $content, $data, $files, $isLate, $now, &$attemptWrittenFiles, &$newVersion) {
                    // Lock latest submission row or assignment to serialize version numbering
                    $existingSubmissions = AssignmentSubmission::query()
                        ->where('assignment_id', $assignment->id)
                        ->where('user_id', $student->getKey())
                        ->lockForUpdate()
                        ->orderByDesc('version_number')
                        ->get();

                    $submissionCount = $existingSubmissions->count();

                    // Check max attempts limit
                    if ($assignment->max_attempts !== null && $assignment->max_attempts > 0) {
                        if ($submissionCount >= $assignment->max_attempts) {
                            throw new DomainException('Maximum submission attempts reached for this assignment.');
                        }
                    }

                    // Check resubmission permission
                    if ($submissionCount > 0 && ! $assignment->allow_resubmission) {
                        throw new DomainException('Resubmission is not permitted for this assignment.');
                    }

                    $newVersion = $submissionCount + 1;
                    $previousSubmission = $existingSubmissions->first();

                    $submissionId = (string) Str::uuid();
                    $status = $newVersion === 1 ? AssignmentSubmission::STATUS_SUBMITTED : AssignmentSubmission::STATUS_RESUBMITTED;

                    $submission = AssignmentSubmission::create([
                        'id' => $submissionId,
                        'assignment_id' => $assignment->id,
                        'enrollment_id' => $enrollment->id,
                        'user_id' => $student->getKey(),
                        'version_number' => $newVersion,
                        'previous_submission_id' => $previousSubmission?->id,
                        'content' => $content ?: null,
                        'student_notes' => ! empty($data['student_notes']) ? trim($data['student_notes']) : null,
                        'status' => $status,
                        'is_late' => $isLate,
                        'submitted_at' => $now,
                    ]);

                    // Store attachments in private vault with compensation tracking
                    foreach ($files as $file) {
                        $this->storeAttachment($submission, $file, $now, $attemptWrittenFiles);
                    }

                    return $submission;
                });

                $transactionCommitted = true;
            } catch (\Throwable $e) {
                // Failure compensation: clean up files ONLY if transaction did not commit
                if (! $transactionCommitted) {
                    foreach ($attemptWrittenFiles as $filePath) {
                        try {
                            Storage::disk('private')->delete($filePath);
                        } catch (\Throwable) {
                            // Suppress storage cleanup exceptions to rethrow original error
                        }
                    }
                }
                throw $e;
            }

            // Post-commit operations: audit log and notifications
            try {
                $academy = $assignment->academy ?? Academy::current();
                if ($academy) {
                    $this->auditLogService->record(
                        $academy,
                        $student,
                        $newVersion === 1 ? 'assignment_submitted' : 'assignment_resubmitted',
                        AssignmentSubmission::class,
                        $submission->id,
                        [
                            'assignment_id' => $assignment->id,
                            'version_number' => $newVersion,
                            'is_late' => $isLate,
                            'files_count' => count($files),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $this->notifyTrainers($assignment, $student, $submission, $newVersion);
            } catch (\Throwable $e) {
                report($e);
            }

            return $submission->fresh(['attachments', 'assignment']);
        });
    }

    /**
     * Validate files count, size, and MIME types.
     */
    protected function validateFiles(Assignment $assignment, array $files): void
    {
        if (empty($files)) {
            return;
        }

        if (! $assignment->allow_multiple_files && count($files) > 1) {
            throw new InvalidArgumentException('Only a single file attachment is permitted for this assignment.');
        }

        if (count($files) > $assignment->max_file_count) {
            throw new InvalidArgumentException(sprintf(
                'You may not upload more than %d files.',
                $assignment->max_file_count
            ));
        }

        $maxBytes = $assignment->max_file_size_mb * 1024 * 1024;
        $allowedExtensions = $assignment->allowed_file_types ?: ['pdf', 'docx', 'xlsx', 'pptx', 'zip', 'csv', 'txt', 'png', 'jpg', 'webp'];
        $allowedExtensions = array_map('strtolower', $allowedExtensions);

        foreach ($files as $index => $file) {
            $originalName = '';
            $fileSize = 0;
            $mimeType = '';

            if ($file instanceof UploadedFile) {
                $originalName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            } elseif (is_array($file)) {
                $originalName = $file['name'] ?? ('file_'.($index + 1));
                $fileSize = isset($file['content']) ? strlen($file['content']) : 0;
                $mimeType = $file['mime_type'] ?? 'application/octet-stream';
            }

            if ($fileSize > $maxBytes) {
                throw new InvalidArgumentException(sprintf(
                    'File "%s" exceeds the maximum size limit of %d MB.',
                    $originalName,
                    $assignment->max_file_size_mb
                ));
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowedExtensions, true)) {
                throw new InvalidArgumentException(sprintf(
                    'File "%s" has an unsupported extension (.%s). Allowed extensions: %s',
                    $originalName,
                    $ext,
                    implode(', ', $allowedExtensions)
                ));
            }

            // Verify MIME type if extension is mapped in MIME map
            if (isset(self::EXTENSION_MIME_MAP[$ext])) {
                $expectedMimes = self::EXTENSION_MIME_MAP[$ext];
                if (! in_array(strtolower($mimeType), $expectedMimes, true) && $mimeType !== 'application/octet-stream') {
                    throw new InvalidArgumentException(sprintf(
                        'File "%s" has an invalid or spoofed MIME type (%s).',
                        $originalName,
                        $mimeType
                    ));
                }
            }
        }
    }

    /**
     * Store file in the private storage disk outside webroot.
     */
    protected function storeAttachment(AssignmentSubmission $submission, mixed $file, Carbon $now, array &$writtenFiles = []): AssignmentSubmissionAttachment
    {
        $attachmentId = (string) Str::uuid();
        $disk = 'private';

        $originalName = '';
        $fileSize = 0;
        $mimeType = '';
        $fileContent = '';

        if ($file instanceof UploadedFile) {
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            $fileContent = file_get_contents($file->getRealPath());
        } elseif (is_array($file)) {
            $originalName = $file['name'] ?? ('attachment_'.Str::random(6));
            $fileContent = $file['content'] ?? '';
            $fileSize = strlen($fileContent);
            $mimeType = $file['mime_type'] ?? 'application/octet-stream';
        }

        $safeExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'bin';
        $storagePath = sprintf('assignments/submissions/%s/%s.%s', $submission->id, Str::uuid(), $safeExt);

        Storage::disk($disk)->put($storagePath, $fileContent);
        $writtenFiles[] = $storagePath;

        $checksum = hash('sha256', $fileContent);

        return AssignmentSubmissionAttachment::create([
            'id' => $attachmentId,
            'submission_id' => $submission->id,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'file_name' => $originalName,
            'file_size_bytes' => $fileSize,
            'mime_type' => $mimeType,
            'checksum' => $checksum,
            'created_at' => $now,
        ]);
    }

    /**
     * Dispatch notification to assigned course trainers.
     */
    protected function notifyTrainers(Assignment $assignment, User $student, AssignmentSubmission $submission, int $version): void
    {
        $course = $assignment->course;
        if (! $course) {
            return;
        }

        $trainers = $course->trainers()->get();
        $title = $version === 1 ? 'New Assignment Submission' : 'Revised Assignment Submission';
        $message = sprintf('%s submitted %s for "%s" in %s.', $student->name, $version > 1 ? "version {$version}" : 'their work', $assignment->title, $course->title);
        $link = route('trainer.assignments.index', ['course' => $course->id]);

        foreach ($trainers as $trainer) {
            $this->notificationService->send(
                recipient: $trainer,
                category: 'academic',
                title: $title,
                message: $message,
                link: $link,
                actor: $student,
                dedupKey: "assignment_sub_{$submission->id}_{$trainer->id}",
                dedupWindowSeconds: 300
            );
        }
    }

    /**
     * Run a database operation with bounded retry loop for SQLite lock/busy contention.
     */
    public function runWithDatabaseRetries(callable $callback, int $maxRetries = 5, int $baseSleepMs = 50): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (QueryException|DeadlockException|PDOException $e) {
                $attempt++;
                $isLockError = str_contains($e->getMessage(), 'database is locked')
                    || str_contains($e->getMessage(), 'SQLITE_BUSY')
                    || str_contains($e->getMessage(), 'Deadlock found')
                    || str_contains($e->getMessage(), 'Lock wait timeout');

                if ($attempt >= $maxRetries || ! $isLockError) {
                    throw $e;
                }

                $jitter = random_int(10, 50);
                usleep(($baseSleepMs * $attempt + $jitter) * 1000);
            }
        }
    }
}
