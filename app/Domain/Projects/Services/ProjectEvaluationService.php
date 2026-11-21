<?php

namespace App\Domain\Projects\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Platform\Services\AuditLogService;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\PracticalProjectStudent;
use App\Domain\Projects\Models\ProjectAchievement;
use App\Domain\Projects\Models\ProjectEvaluation;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Models\User;
use DomainException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDOException;

class ProjectEvaluationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected NotificationDispatcherService $notificationService
    ) {}

    /**
     * Evaluate a project submission, awarding marks against project criteria or overall score.
     *
     * @param array{
     *     decision: 'approved'|'revision_requested'|'rejected',
     *     points_awarded?: float|int,
     *     criteria_ratings?: array<string, array{awarded_marks: float|int, feedback?: ?string}>,
     *     trainer_feedback?: ?string,
     *     improvement_recommendations?: ?string,
     *     revision_reason?: ?string
     * } $data
     */
    public function evaluate(ProjectSubmission $submission, User $evaluator, array $data, ?Carbon $at = null): ProjectEvaluation
    {
        $now = $at ?? Carbon::now();
        $this->authorizeEvaluator($submission, $evaluator);

        $project = $submission->project;
        if (! $project) {
            throw new DomainException('Associated practical project not found.');
        }

        $decision = $data['decision'] ?? 'approved';
        if (! in_array($decision, [ProjectEvaluation::STATUS_APPROVED, ProjectEvaluation::STATUS_REVISION_REQUESTED, ProjectEvaluation::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException(sprintf('Invalid evaluation decision "%s".', $decision));
        }

        if ($decision === ProjectEvaluation::STATUS_REVISION_REQUESTED && empty(trim($data['revision_reason'] ?? ''))) {
            throw new InvalidArgumentException('A reason explaining the required revision must be provided.');
        }

        $pointsPossible = (float) $project->max_marks;
        $criteria = $project->criteria;
        $criteriaFeedback = null;
        $rawPoints = 0.0;

        if ($decision === ProjectEvaluation::STATUS_REVISION_REQUESTED) {
            $rawPoints = 0.0;
            $isPassed = false;
        } else {
            if ($criteria->isNotEmpty() && ! empty($data['criteria_ratings'])) {
                $criteriaFeedback = [];
                $totalAwarded = 0.0;

                foreach ($criteria as $criterion) {
                    $rating = $data['criteria_ratings'][$criterion->id] ?? null;
                    $awarded = isset($rating['awarded_marks']) ? (float) $rating['awarded_marks'] : 0.0;
                    $critMax = (float) $criterion->max_marks;

                    if ($awarded < 0.0 || $awarded > $critMax) {
                        throw new InvalidArgumentException(sprintf(
                            'Awarded marks for "%s" must be between 0 and %.2f.',
                            $criterion->title,
                            $critMax
                        ));
                    }

                    $totalAwarded += $awarded;
                    $criteriaFeedback[] = [
                        'criterion_id' => $criterion->id,
                        'title' => $criterion->title,
                        'max_marks' => $critMax,
                        'awarded_marks' => $awarded,
                        'feedback' => $rating['feedback'] ?? null,
                    ];
                }

                $rawPoints = $totalAwarded;
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

            $isPassed = $rawPoints >= (float) $project->passing_marks;
        }

        $finalPercentage = $pointsPossible > 0 ? round(($rawPoints / $pointsPossible) * 100.0, 2) : 0.00;

        return $this->runWithDatabaseRetries(function () use ($submission, $evaluator, $project, $decision, $rawPoints, $pointsPossible, $finalPercentage, $isPassed, $criteriaFeedback, $data, $now) {
            return DB::transaction(function () use ($submission, $evaluator, $project, $decision, $rawPoints, $pointsPossible, $finalPercentage, $isPassed, $criteriaFeedback, $data, $now) {
                /** @var ProjectSubmission $lockedSubmission */
                $lockedSubmission = ProjectSubmission::query()
                    ->where('id', $submission->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Create immutable evaluation record
                $evaluation = ProjectEvaluation::create([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $lockedSubmission->id,
                    'evaluator_id' => $evaluator->getKey(),
                    'points_awarded' => $rawPoints,
                    'points_possible' => $pointsPossible,
                    'score_percentage' => $finalPercentage,
                    'is_passed' => $isPassed,
                    'status' => $decision,
                    'criteria_feedback' => $criteriaFeedback,
                    'trainer_feedback' => $data['trainer_feedback'] ?? null,
                    'improvement_recommendations' => $data['improvement_recommendations'] ?? null,
                    'revision_reason' => $data['revision_reason'] ?? null,
                    'evaluated_at' => $now,
                ]);

                // 2. Update submission status and marks
                $lockedSubmission->status = $decision;
                $lockedSubmission->final_score = $rawPoints;
                $lockedSubmission->score_percentage = $finalPercentage;
                $lockedSubmission->is_passed = $isPassed;
                $lockedSubmission->save();

                // 3. If approved, issue project achievement and mark student status complete
                if ($decision === ProjectEvaluation::STATUS_APPROVED) {
                    $this->issueProjectAchievement($project, $lockedSubmission, $finalPercentage);

                    PracticalProjectStudent::query()
                        ->where('project_id', $project->id)
                        ->where('student_profile_id', $lockedSubmission->student_profile_id)
                        ->update(['status' => PracticalProjectStudent::STATUS_COMPLETED]);
                } elseif ($decision === ProjectEvaluation::STATUS_REVISION_REQUESTED) {
                    PracticalProjectStudent::query()
                        ->where('project_id', $project->id)
                        ->where('student_profile_id', $lockedSubmission->student_profile_id)
                        ->update(['status' => PracticalProjectStudent::STATUS_IN_PROGRESS]);
                }

                // 4. Audit Log
                $academy = $project->academy ?? Academy::current();
                if ($academy) {
                    $this->auditLogService->record(
                        $academy,
                        $evaluator,
                        'project_evaluated',
                        ProjectEvaluation::class,
                        $evaluation->id,
                        [
                            'submission_id' => $lockedSubmission->id,
                            'project_id' => $project->id,
                            'student_id' => $lockedSubmission->user_id,
                            'decision' => $decision,
                            'points_awarded' => $rawPoints,
                            'percentage' => $finalPercentage,
                            'is_passed' => $isPassed,
                        ]
                    );
                }

                // 5. Notify student
                $student = $lockedSubmission->user;
                if ($student) {
                    $title = $decision === ProjectEvaluation::STATUS_APPROVED
                        ? 'Project Approved!'
                        : ($decision === ProjectEvaluation::STATUS_REVISION_REQUESTED
                            ? 'Project Revision Requested'
                            : 'Project Evaluation Completed');

                    $message = $decision === ProjectEvaluation::STATUS_APPROVED
                        ? sprintf('Congratulations! Your practical project "%s" has been approved (Score: %.2f%%).', $project->title, $finalPercentage)
                        : ($decision === ProjectEvaluation::STATUS_REVISION_REQUESTED
                            ? sprintf('Your trainer requested revisions for "%s": "%s".', $project->title, Str::limit($data['revision_reason'] ?? '', 120))
                            : sprintf('Your submission for "%s" has been evaluated. Score: %.2f%%.', $project->title, $finalPercentage));

                    $this->notificationService->send(
                        recipient: $student,
                        category: 'academic',
                        title: $title,
                        message: $message,
                        link: route('student.projects.show', $project->id),
                        actor: $evaluator,
                        dedupKey: "project_eval_{$evaluation->id}_{$student->id}",
                        dedupWindowSeconds: 300
                    );
                }

                return $evaluation;
            });
        });
    }

    /**
     * Authorize that the evaluator has permission to evaluate this submission.
     */
    public function authorizeEvaluator(ProjectSubmission $submission, User $evaluator): void
    {
        if (! $evaluator->is_active) {
            throw new DomainException('Inactive users cannot evaluate submissions.');
        }

        if ((string) $evaluator->getKey() === (string) $submission->user_id) {
            throw new DomainException('Students cannot evaluate their own submissions.');
        }

        if ($evaluator->hasRole('admin')) {
            return;
        }

        if ($evaluator->hasRole('trainer')) {
            $course = $submission->project?->course;
            if (! $course) {
                throw new DomainException('Only administrators can evaluate academy-wide projects.');
            }

            $isAssignedTrainer = $course->trainers()->where('users.id', $evaluator->getKey())->exists();
            if (! $isAssignedTrainer) {
                throw new DomainException('You are not an assigned trainer for the course associated with this project.');
            }

            return;
        }

        throw new DomainException('You do not have permission to evaluate project submissions.');
    }

    /**
     * Issue an authentic project achievement badge/record upon approval.
     */
    protected function issueProjectAchievement(PracticalProject $project, ProjectSubmission $submission, float $scorePercentage): ProjectAchievement
    {
        $existing = ProjectAchievement::query()
            ->where('submission_id', $submission->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $achievementNumber = sprintf('ACH-%s-%s-%s', strtoupper($project->category), date('Y'), strtoupper(Str::random(6)));
        $verificationCode = bin2hex(random_bytes(16));

        $skills = [$project->categoryLabel()];
        if (! empty($project->expected_deliverables) && is_array($project->expected_deliverables)) {
            $skills = array_values(array_unique(array_merge($skills, $project->expected_deliverables)));
        }

        return ProjectAchievement::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'submission_id' => $submission->id,
            'user_id' => $submission->user_id,
            'achievement_number' => $achievementNumber,
            'verification_code' => $verificationCode,
            'title' => sprintf('Practical SEO Achievement: %s', $project->title),
            'category' => $project->category,
            'score_percentage' => $scorePercentage,
            'skills_awarded' => $skills,
            'issued_at' => Carbon::now(),
            'status' => ProjectAchievement::STATUS_ISSUED,
        ]);
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
