<?php

namespace App\Domain\Assignments\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Platform\Services\AuditLogService;
use App\Models\User;
use DomainException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDOException;

class AssignmentGradingService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected NotificationDispatcherService $notificationService,
        protected ?CourseCompletionService $completionService = null
    ) {
        $this->completionService ??= app(CourseCompletionService::class);
    }

    /**
     * Grade a student's assignment submission.
     *
     * @param array{
     *     points_awarded?: float|int,
     *     overall_feedback?: ?string,
     *     internal_notes?: ?string,
     *     rubric_evaluations?: array<string, array{awarded_marks: float|int, feedback?: ?string}>,
     *     is_override?: bool,
     *     override_reason?: ?string
     * } $data
     */
    public function grade(AssignmentSubmission $submission, User $evaluator, array $data, ?Carbon $at = null): AssignmentEvaluation
    {
        $now = $at ?? Carbon::now();
        $this->authorizeEvaluator($submission, $evaluator);

        $assignment = $submission->assignment;
        if (! $assignment) {
            throw new DomainException('Associated assignment not found.');
        }

        $pointsPossible = (float) $assignment->max_marks;
        $rubricCriteria = $assignment->rubricCriteria;
        $rubricSnapshot = null;
        $rawPoints = 0.0;

        // 1. Process rubric evaluations if rubric criteria exist
        if ($rubricCriteria->isNotEmpty() && ! empty($data['rubric_evaluations'])) {
            $rubricSnapshot = [];
            $totalRubricMarks = 0.0;

            foreach ($rubricCriteria as $criterion) {
                $eval = $data['rubric_evaluations'][$criterion->id] ?? null;
                $awarded = isset($eval['awarded_marks']) ? (float) $eval['awarded_marks'] : 0.0;
                $critMax = (float) $criterion->max_marks;

                if ($awarded < 0.0 || $awarded > $critMax) {
                    throw new InvalidArgumentException(sprintf(
                        'Awarded marks for "%s" must be between 0 and %.2f.',
                        $criterion->title,
                        $critMax
                    ));
                }

                $totalRubricMarks += $awarded;
                $rubricSnapshot[] = [
                    'criterion_id' => $criterion->id,
                    'title' => $criterion->title,
                    'max_marks' => $critMax,
                    'awarded_marks' => $awarded,
                    'feedback' => $eval['feedback'] ?? null,
                ];
            }

            $rawPoints = $totalRubricMarks;
        } else {
            if (! isset($data['points_awarded'])) {
                throw new InvalidArgumentException('Total awarded marks must be provided.');
            }
            $rawPoints = (float) $data['points_awarded'];
        }

        if ($rawPoints < 0.0 || $rawPoints > $pointsPossible) {
            throw new InvalidArgumentException(sprintf(
                'Awarded marks (%.2f) must be between 0 and %.2f.',
                $rawPoints,
                $pointsPossible
            ));
        }

        // 2. Apply late penalty if applicable
        $penaltyDeducted = 0.0;
        if ($submission->is_late) {
            $penaltyDeducted = $assignment->calculateLatePenalty($rawPoints, $submission->submitted_at);
        }
        $finalAwarded = max(0.00, $rawPoints - $penaltyDeducted);
        $finalPercentage = $pointsPossible > 0 ? round(($finalAwarded / $pointsPossible) * 100.0, 2) : 0.00;
        $isPassed = $finalAwarded >= (float) $assignment->passing_marks;

        $isOverride = (bool) ($data['is_override'] ?? false);
        $overrideReason = $data['override_reason'] ?? null;
        if ($isOverride && empty($overrideReason)) {
            throw new InvalidArgumentException('An explanation is required for administrative grade overrides.');
        }

        return $this->runWithDatabaseRetries(function () use ($submission, $evaluator, $rawPoints, $penaltyDeducted, $finalAwarded, $pointsPossible, $finalPercentage, $isPassed, $rubricSnapshot, $data, $isOverride, $overrideReason, $now, $assignment) {
            return DB::transaction(function () use ($submission, $evaluator, $rawPoints, $penaltyDeducted, $finalAwarded, $pointsPossible, $finalPercentage, $isPassed, $rubricSnapshot, $data, $isOverride, $overrideReason, $now, $assignment) {
                // Lock submission
                /** @var AssignmentSubmission $lockedSubmission */
                $lockedSubmission = AssignmentSubmission::query()
                    ->where('id', $submission->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $previousGrade = $lockedSubmission->grade;

                // Create new immutable evaluation record
                $evaluation = AssignmentEvaluation::create([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $lockedSubmission->id,
                    'evaluator_id' => $evaluator->getKey(),
                    'raw_score' => $rawPoints,
                    'late_penalty_applied' => $penaltyDeducted,
                    'points_awarded' => $finalAwarded,
                    'points_possible' => $pointsPossible,
                    'score_percentage' => $finalPercentage,
                    'is_passed' => $isPassed,
                    'rubric_snapshot' => $rubricSnapshot,
                    'overall_feedback' => $data['overall_feedback'] ?? null,
                    'internal_notes' => $data['internal_notes'] ?? null,
                    'status' => AssignmentEvaluation::STATUS_GRADED,
                    'is_override' => $isOverride,
                    'override_reason' => $overrideReason,
                    'graded_at' => $now,
                ]);

                // Update submission status and grade
                $lockedSubmission->status = AssignmentSubmission::STATUS_GRADED;
                $lockedSubmission->grade = $finalAwarded;
                $lockedSubmission->save();

                // Check course completion if mandatory
                if ($assignment->is_mandatory && $isPassed && $lockedSubmission->enrollment) {
                    $this->completionService?->completeCourse($lockedSubmission->enrollment, $evaluator);
                }

                // Audit log
                $academy = $assignment->academy ?? Academy::current();
                if ($academy) {
                    $this->auditLogService->record(
                        $academy,
                        $evaluator,
                        $isOverride ? 'assignment_grade_overridden' : 'assignment_graded',
                        AssignmentEvaluation::class,
                        $evaluation->id,
                        [
                            'submission_id' => $lockedSubmission->id,
                            'assignment_id' => $assignment->id,
                            'student_id' => $lockedSubmission->user_id,
                            'previous_grade' => $previousGrade,
                            'new_grade' => $finalAwarded,
                            'percentage' => $finalPercentage,
                            'is_passed' => $isPassed,
                            'is_override' => $isOverride,
                            'override_reason' => $overrideReason,
                        ]
                    );
                }

                // Notify student
                $student = $lockedSubmission->user;
                if ($student) {
                    $title = 'Assignment Graded';
                    $message = sprintf('Your submission for "%s" has been graded. Result: %.2f / %.2f (%.1f%%).', $assignment->title, $finalAwarded, $pointsPossible, $finalPercentage);
                    $link = route('student.courses.assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment->id]);

                    $this->notificationService->send(
                        recipient: $student,
                        category: 'academic',
                        title: $title,
                        message: $message,
                        link: $link,
                        actor: $evaluator,
                        dedupKey: "assignment_graded_{$evaluation->id}_{$student->id}",
                        dedupWindowSeconds: 300
                    );
                }

                return $evaluation;
            });
        });
    }

    /**
     * Request a revision on a student submission.
     */
    public function requestRevision(AssignmentSubmission $submission, User $evaluator, string $reason, ?string $internalNotes = null, ?Carbon $at = null): AssignmentEvaluation
    {
        $now = $at ?? Carbon::now();
        $this->authorizeEvaluator($submission, $evaluator);

        $reason = trim($reason);
        if (empty($reason)) {
            throw new InvalidArgumentException('A reason explaining the required revision must be provided.');
        }

        $assignment = $submission->assignment;
        if (! $assignment) {
            throw new DomainException('Associated assignment not found.');
        }

        return $this->runWithDatabaseRetries(function () use ($submission, $evaluator, $reason, $internalNotes, $now, $assignment) {
            return DB::transaction(function () use ($submission, $evaluator, $reason, $internalNotes, $now, $assignment) {
                /** @var AssignmentSubmission $lockedSubmission */
                $lockedSubmission = AssignmentSubmission::query()
                    ->where('id', $submission->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $pointsPossible = (float) $assignment->max_marks;

                // Create revision evaluation record
                $evaluation = AssignmentEvaluation::create([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $lockedSubmission->id,
                    'evaluator_id' => $evaluator->getKey(),
                    'points_awarded' => 0.00,
                    'points_possible' => $pointsPossible,
                    'score_percentage' => 0.00,
                    'is_passed' => false,
                    'status' => AssignmentEvaluation::STATUS_REVISION_REQUESTED,
                    'revision_reason' => $reason,
                    'internal_notes' => $internalNotes,
                    'graded_at' => $now,
                ]);

                // Update submission status
                $lockedSubmission->status = AssignmentSubmission::STATUS_REVISION_REQUESTED;
                $lockedSubmission->save();

                // Audit log
                $academy = $assignment->academy ?? Academy::current();
                if ($academy) {
                    $this->auditLogService->record(
                        $academy,
                        $evaluator,
                        'assignment_revision_requested',
                        AssignmentEvaluation::class,
                        $evaluation->id,
                        [
                            'submission_id' => $lockedSubmission->id,
                            'assignment_id' => $assignment->id,
                            'student_id' => $lockedSubmission->user_id,
                            'reason' => $reason,
                        ]
                    );
                }

                // Notify student
                $student = $lockedSubmission->user;
                if ($student) {
                    $title = 'Revision Requested';
                    $message = sprintf('Your trainer requested revisions on your submission for "%s": "%s".', $assignment->title, Str::limit($reason, 120));
                    $link = route('student.courses.assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment->id]);

                    $this->notificationService->send(
                        recipient: $student,
                        category: 'academic',
                        title: $title,
                        message: $message,
                        link: $link,
                        actor: $evaluator,
                        dedupKey: "assignment_rev_{$evaluation->id}_{$student->id}",
                        dedupWindowSeconds: 300
                    );
                }

                return $evaluation;
            });
        });
    }

    /**
     * Authorize that the evaluator has permission to grade this submission.
     */
    protected function authorizeEvaluator(AssignmentSubmission $submission, User $evaluator): void
    {
        if (! $evaluator->is_active) {
            throw new DomainException('Evaluator account is not active.');
        }

        if ($evaluator->hasRole('admin')) {
            return;
        }

        if ($evaluator->hasRole('trainer')) {
            $course = $submission->assignment?->course;
            $isAssigned = $course && $course->trainers()->where('users.id', $evaluator->getKey())->exists();
            if (! $isAssigned) {
                throw new DomainException('You are not authorized to grade assignments for this course.');
            }

            return;
        }

        throw new DomainException('You do not possess grading authority.');
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
