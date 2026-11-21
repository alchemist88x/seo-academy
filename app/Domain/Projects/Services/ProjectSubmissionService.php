<?php

namespace App\Domain\Projects\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Platform\Services\AuditLogService;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\PracticalProjectStudent;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Models\ProjectSubmissionAttachment;
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

class ProjectSubmissionService
{
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'zip',
    ];

    public const MAX_FILE_SIZE_BYTES = 26214400; // 25 MB

    public function __construct(
        protected AuditLogService $auditLogService,
        protected NotificationDispatcherService $notificationService,
        protected PracticalProjectService $projectService
    ) {}

    /**
     * Submit practical SEO project work (creates a new versioned submission).
     *
     * @param array{
     *     website_url?: ?string,
     *     content?: ?string,
     *     student_notes?: ?string,
     *     attachments?: array<int, array{file: UploadedFile|array{name: string, content: string, mime_type?: string}, type?: string}>
     * } $data
     */
    public function submit(PracticalProject $project, User $student, array $data, ?Carbon $at = null): ProjectSubmission
    {
        $now = $at ?? Carbon::now();

        // 1. Verify student role & profile
        if (! $student->is_active || ! $student->hasRole('student')) {
            throw new DomainException('Only active students can submit practical SEO projects.');
        }

        $studentProfile = $student->studentProfile;
        if (! $studentProfile) {
            throw new DomainException('Student profile not found.');
        }

        // 2. Verify project availability
        if (! $project->isPublished()) {
            throw new DomainException('This project is not currently published.');
        }

        if (! $project->isAvailable($now)) {
            throw new DomainException('This project is not yet available for submissions.');
        }

        // 3. Verify student eligibility (individually assigned or enrolled in project's course or global)
        $isEligible = $this->projectService->getAvailableProjectsForStudent($studentProfile)
            ->where('practical_projects.id', $project->id)
            ->exists();

        if (! $isEligible) {
            throw new DomainException('You are not authorized to submit this project.');
        }

        // 4. Validate Website URL if provided
        $websiteUrl = ! empty($data['website_url']) ? trim($data['website_url']) : null;
        if ($websiteUrl !== null && ! filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Please provide a valid website URL including http:// or https://.');
        }

        // 5. Check existing submissions and revision limits
        $latestSubmission = ProjectSubmission::query()
            ->where('project_id', $project->id)
            ->where('user_id', $student->getKey())
            ->orderByDesc('version_number')
            ->first();

        $versionNumber = 1;
        $previousSubmissionId = null;

        if ($latestSubmission) {
            if ($latestSubmission->isApproved()) {
                throw new DomainException('This project has already been approved and completed.');
            }

            if (in_array($latestSubmission->status, [ProjectSubmission::STATUS_SUBMITTED, ProjectSubmission::STATUS_UNDER_REVIEW, ProjectSubmission::STATUS_RESUBMITTED], true)) {
                throw new DomainException('Your previous submission is currently pending evaluation.');
            }

            // Check max revisions
            if ($latestSubmission->version_number > $project->max_revisions) {
                throw new DomainException(sprintf('Maximum revision limit (%d) reached for this project.', $project->max_revisions));
            }

            $versionNumber = $latestSubmission->version_number + 1;
            $previousSubmissionId = $latestSubmission->id;
        }

        $isLate = $project->isPastDue($now);

        // 6. Execute atomic creation with concurrency retries
        return $this->runWithDatabaseRetries(function () use ($project, $student, $studentProfile, $versionNumber, $previousSubmissionId, $websiteUrl, $data, $isLate, $now) {
            return DB::transaction(function () use ($project, $student, $studentProfile, $versionNumber, $previousSubmissionId, $websiteUrl, $data, $isLate, $now) {
                // Prevent duplicate version submission race
                $conflict = ProjectSubmission::query()
                    ->where('project_id', $project->id)
                    ->where('user_id', $student->getKey())
                    ->where('version_number', $versionNumber)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new DomainException('A submission with this version is already being processed.');
                }

                $submission = ProjectSubmission::create([
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'student_profile_id' => $studentProfile->id,
                    'user_id' => $student->getKey(),
                    'version_number' => $versionNumber,
                    'previous_submission_id' => $previousSubmissionId,
                    'website_url' => $websiteUrl,
                    'content' => $data['content'] ?? null,
                    'student_notes' => $data['student_notes'] ?? null,
                    'status' => $versionNumber > 1 ? ProjectSubmission::STATUS_RESUBMITTED : ProjectSubmission::STATUS_SUBMITTED,
                    'is_late' => $isLate,
                    'submitted_at' => $now,
                ]);

                // Process Attachments
                if (! empty($data['attachments']) && is_array($data['attachments'])) {
                    $this->storeAttachments($submission, $data['attachments']);
                }

                // Update PracticalProjectStudent status if record exists
                PracticalProjectStudent::query()
                    ->where('project_id', $project->id)
                    ->where('student_profile_id', $studentProfile->id)
                    ->update([
                        'status' => PracticalProjectStudent::STATUS_SUBMITTED,
                    ]);

                // Audit Log
                $academy = $project->academy ?? Academy::current();
                if ($academy) {
                    $this->auditLogService->record(
                        $academy,
                        $student,
                        'project_submitted',
                        ProjectSubmission::class,
                        $submission->id,
                        [
                            'project_id' => $project->id,
                            'project_title' => $project->title,
                            'version_number' => $versionNumber,
                            'is_late' => $isLate,
                        ]
                    );
                }

                // Notifications
                $this->notificationService->send(
                    recipient: $student,
                    category: 'academic',
                    title: 'Project Submission Received',
                    message: sprintf('Your submission (v%d) for "%s" has been successfully uploaded and is pending evaluation.', $versionNumber, $project->title),
                    link: route('student.projects.show', $project->id),
                    actor: $student,
                    dedupKey: "project_sub_conf_{$submission->id}_{$student->id}",
                    dedupWindowSeconds: 300
                );

                $this->notifyEvaluators($project, $student, $submission);

                return $submission;
            });
        });
    }

    /**
     * Store attachments securely on private disk.
     */
    protected function storeAttachments(ProjectSubmission $submission, array $attachmentItems): void
    {
        $storageDir = sprintf('projects/%s/submissions/%s', $submission->project_id, $submission->id);

        foreach ($attachmentItems as $item) {
            $fileObj = $item['file'] ?? null;
            $type = $item['type'] ?? ProjectSubmissionAttachment::TYPE_SUPPORTING_ATTACHMENT;

            if (! in_array($type, array_keys(ProjectSubmissionAttachment::TYPES), true)) {
                $type = ProjectSubmissionAttachment::TYPE_SUPPORTING_ATTACHMENT;
            }

            if ($fileObj instanceof UploadedFile) {
                $extension = strtolower($fileObj->getClientOriginalExtension());
                if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    throw new InvalidArgumentException(sprintf('File extension ".%s" is not permitted.', $extension));
                }

                if ($fileObj->getSize() > self::MAX_FILE_SIZE_BYTES) {
                    throw new InvalidArgumentException(sprintf('File "%s" exceeds maximum limit of 25MB.', $fileObj->getClientOriginalName()));
                }

                $filename = Str::slug(pathinfo($fileObj->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(8).'.'.$extension;
                $path = Storage::disk('private')->putFileAs($storageDir, $fileObj, $filename);

                ProjectSubmissionAttachment::create([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $submission->id,
                    'attachment_type' => $type,
                    'storage_disk' => 'private',
                    'storage_path' => $path,
                    'file_name' => $fileObj->getClientOriginalName(),
                    'file_size_bytes' => $fileObj->getSize(),
                    'mime_type' => $fileObj->getMimeType() ?: 'application/octet-stream',
                    'checksum' => hash_file('sha256', $fileObj->getRealPath()),
                    'created_at' => Carbon::now(),
                ]);
            } elseif (is_array($fileObj) && isset($fileObj['name'], $fileObj['content'])) {
                // In-memory file content
                $extension = strtolower(pathinfo($fileObj['name'], PATHINFO_EXTENSION));
                if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    throw new InvalidArgumentException(sprintf('File extension ".%s" is not permitted.', $extension));
                }

                $bytes = strlen($fileObj['content']);
                if ($bytes > self::MAX_FILE_SIZE_BYTES) {
                    throw new InvalidArgumentException('File exceeds maximum limit of 25MB.');
                }

                $filename = Str::slug(pathinfo($fileObj['name'], PATHINFO_FILENAME)).'-'.Str::random(8).'.'.$extension;
                $relativePath = $storageDir.'/'.$filename;
                Storage::disk('private')->put($relativePath, $fileObj['content']);

                ProjectSubmissionAttachment::create([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $submission->id,
                    'attachment_type' => $type,
                    'storage_disk' => 'private',
                    'storage_path' => $relativePath,
                    'file_name' => $fileObj['name'],
                    'file_size_bytes' => $bytes,
                    'mime_type' => $fileObj['mime_type'] ?? 'application/octet-stream',
                    'checksum' => hash('sha256', $fileObj['content']),
                    'created_at' => Carbon::now(),
                ]);
            }
        }
    }

    protected function notifyEvaluators(PracticalProject $project, User $student, ProjectSubmission $submission): void
    {
        $evaluators = collect();

        if ($project->course) {
            $evaluators = $evaluators->merge($project->course->trainers);
        }

        if ($evaluators->isEmpty()) {
            $evaluators = User::role('admin')->where('is_active', true)->get();
        }

        foreach ($evaluators as $evaluator) {
            $this->notificationService->send(
                recipient: $evaluator,
                category: 'academic',
                title: 'New Project Submission Awaiting Review',
                message: sprintf('Student %s submitted work (v%d) for "%s".', $student->name, $submission->version_number, $project->title),
                link: route('trainer.projects.submissions.evaluate', $submission->id),
                actor: $student,
                dedupKey: "project_sub_eval_{$submission->id}_{$evaluator->id}",
                dedupWindowSeconds: 300
            );
        }
    }

    protected function runWithDatabaseRetries(callable $operation, int $maxAttempts = 3): mixed
    {
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                return $operation();
            } catch (DeadlockException|QueryException|PDOException $e) {
                if ($attempts >= $maxAttempts) {
                    throw $e;
                }
                usleep(50000 * $attempts);
            }
        }

        throw new DomainException('Database retry exhausted.');
    }
}
