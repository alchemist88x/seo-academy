<?php

namespace App\Domain\Assessments\Services;

use App\Domain\Assessments\Events\AssessmentAttemptStarted;
use App\Domain\Assessments\Events\AssessmentGraded;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Services\QuestionBankService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssessmentService
{
    public function __construct(
        protected ?CourseCompletionService $completionService = null,
        protected ?QuestionBankService $questionBankService = null,
        public ?AssessmentFinalizationService $finalizationService = null
    ) {
        $this->completionService ??= app(CourseCompletionService::class);
        $this->questionBankService ??= app(QuestionBankService::class);
        $this->finalizationService ??= app(AssessmentFinalizationService::class);
    }

    /**
     * Create or resume an enrollment-specific assessment attempt.
     *
     * Principles:
     * - Every attempt belongs to an enrollment, assessment, and student user.
     * - Enforces examination scheduling and eligibility (exam_start_at, late_entry_deadline_at, exam_end_at).
     * - Computes server-authoritative expires_at and finalize_after.
     * - Lazily finalizes expired in-progress attempts.
     * - Enforces configured attempt limits.
     * - Returns an in-progress attempt if one already exists and is unexpired (idempotent resume).
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function createAttempt(Enrollment $enrollment, Quiz $assessment, User $user): AssessmentAttempt
    {
        $this->authorizeStudentEnrollment($enrollment, $user);
        $this->validateAssessmentBelongsToCourse($enrollment, $assessment);

        if (! $assessment->isPublished()) {
            throw new DomainException('Assessment is currently not available or published.');
        }

        $now = Carbon::now();

        // 1. Examination scheduling enforcement
        if ($assessment->is_scheduled_exam) {
            if ($assessment->exam_start_at !== null && $now->lessThan($assessment->exam_start_at)) {
                throw new DomainException('Examination has not started yet.');
            }

            if ($assessment->exam_end_at !== null && $now->greaterThan($assessment->exam_end_at)) {
                throw new DomainException('Examination has already ended.');
            }

            if ($assessment->late_entry_deadline_at !== null && $now->greaterThan($assessment->late_entry_deadline_at)) {
                throw new DomainException('Late entry deadline has passed.');
            }
        }

        return DB::transaction(function () use ($enrollment, $assessment, $user, $now) {
            // Check for an existing in-progress attempt
            /** @var AssessmentAttempt|null $existingInProgress */
            $existingInProgress = AssessmentAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('assessment_id', $assessment->id)
                ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
                ->lockForUpdate()
                ->first();

            if ($existingInProgress !== null) {
                // If existing attempt has expired past finalize_after, lazily finalize it
                if ($existingInProgress->finalize_after !== null && $now->greaterThanOrEqualTo($existingInProgress->finalize_after)) {
                    $this->finalizationService->finalizeAttempt($existingInProgress, 'timeout_expired');
                } else {
                    return $existingInProgress;
                }
            }

            // Enforce attempt limits
            if (! $assessment->hasAttemptsLeft($enrollment)) {
                throw new InvalidArgumentException('Attempt limit reached for this assessment.');
            }

            $attemptCount = AssessmentAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('assessment_id', $assessment->id)
                ->count();

            $resolved = $this->questionBankService->resolveQuestionsForAttempt($assessment);
            $selectedIds = $resolved['selected_ids'];
            $snapshots = $resolved['snapshots'];
            $totalPoints = (float) array_sum(array_column($snapshots, 'points'));

            // 2. Compute authoritative deadlines
            $timeLimitMinutes = $assessment->time_limit_minutes;
            $limitExpiry = ($timeLimitMinutes !== null && $timeLimitMinutes > 0)
                ? $now->copy()->addMinutes($timeLimitMinutes)
                : null;

            if ($assessment->is_scheduled_exam && $assessment->exam_end_at !== null) {
                $expiresAt = $limitExpiry
                    ? ($limitExpiry->lessThan($assessment->exam_end_at) ? $limitExpiry : $assessment->exam_end_at)
                    : $assessment->exam_end_at;
            } else {
                $expiresAt = $limitExpiry;
            }

            $gracePeriodSeconds = (int) ($assessment->grace_period_seconds ?? 60);
            $finalizeAfter = $expiresAt !== null
                ? $expiresAt->copy()->addSeconds($gracePeriodSeconds)
                : null;

            $configSnapshot = [
                'time_limit_minutes' => $assessment->time_limit_minutes,
                'exam_start_at' => $assessment->exam_start_at?->toIso8601String(),
                'exam_end_at' => $assessment->exam_end_at?->toIso8601String(),
                'late_entry_deadline_at' => $assessment->late_entry_deadline_at?->toIso8601String(),
                'grace_period_seconds' => $gracePeriodSeconds,
                'timezone' => $assessment->timezone ?? config('app.timezone'),
                'is_scheduled_exam' => (bool) $assessment->is_scheduled_exam,
                'passing_percentage' => $assessment->getPassingScore(),
            ];

            $attempt = AssessmentAttempt::create([
                'academy_id' => $enrollment->academy_id,
                'assessment_id' => $assessment->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'attempt_number' => $attemptCount + 1,
                'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
                'started_at' => $now,
                'expires_at' => $expiresAt,
                'finalize_after' => $finalizeAfter,
                'config_snapshot' => $configSnapshot,
                'total_points' => $totalPoints > 0 ? $totalPoints : 1.0,
                'earned_points' => 0,
                'score_percentage' => 0,
                'passed' => false,
                'requires_manual_grading' => false,
                'selected_question_ids' => $selectedIds,
                'question_snapshots' => $snapshots,
            ]);

            DB::afterCommit(function () use ($attempt) {
                event(new AssessmentAttemptStarted($attempt));
            });

            return $attempt;
        });
    }

    /**
     * Submit an assessment attempt and perform automatic grading via the finalization service.
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function submitAttempt(AssessmentAttempt $attempt, array $answers, ?User $user = null): AssessmentAttempt
    {
        if ($attempt->exists) {
            $attempt->refresh();
        }

        if (! $attempt->isInProgress()) {
            throw new InvalidArgumentException('This attempt has already been submitted.');
        }

        return $this->finalizationService->finalizeAttempt($attempt, 'student_submit', $answers, $user);
    }

    /**
     * Persist an individual question answer atomically with revision tracking and idempotency control.
     *
     * @throws AuthorizationException
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function saveAttemptAnswer(
        AssessmentAttempt $attempt,
        string $questionId,
        mixed $studentAnswer,
        int $revision = 1,
        ?string $idempotencyKey = null,
        ?User $user = null
    ): AssessmentAttemptAnswer {
        if ($user !== null && (string) $attempt->user_id !== (string) $user->id) {
            throw new AuthorizationException('You do not have authorization to modify this attempt.');
        }

        if (! $attempt->isInProgress()) {
            throw new DomainException('This attempt is already finalized and cannot be modified.');
        }

        $now = Carbon::now();

        // Lazy finalization check: accept writes strictly earlier than finalize_after
        if ($attempt->finalize_after !== null && $now->greaterThanOrEqualTo($attempt->finalize_after)) {
            $this->finalizationService->finalizeAttempt($attempt, 'timeout_expired');
            throw new DomainException('This assessment attempt has expired and was finalized.');
        }

        // Validate question belongs to attempt snapshot
        $questions = $attempt->getQuestionsForAttempt();
        $matchedQuestion = collect($questions)->first(fn ($q) => (string) ($q['id'] ?? '') === (string) $questionId);

        if (! $matchedQuestion) {
            throw new InvalidArgumentException("Question [{$questionId}] does not belong to this attempt.");
        }

        $rawAnswer = is_array($studentAnswer)
            ? json_encode($studentAnswer)
            : ($studentAnswer !== null ? (string) $studentAnswer : null);

        return $this->finalizationService->runWithDatabaseRetries(function () use (
            $attempt,
            $questionId,
            $rawAnswer,
            $matchedQuestion,
            $revision,
            $idempotencyKey,
            $now
        ) {
            return DB::transaction(function () use (
                $attempt,
                $questionId,
                $rawAnswer,
                $matchedQuestion,
                $revision,
                $idempotencyKey,
                $now
            ) {
                // Ensure attempt is still in progress inside lock
                $lockedAttempt = AssessmentAttempt::query()
                    ->where('id', $attempt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedAttempt->isInProgress()) {
                    throw new DomainException('This attempt is already finalized and cannot be modified.');
                }

                if ($lockedAttempt->finalize_after !== null && $now->greaterThanOrEqualTo($lockedAttempt->finalize_after)) {
                    $this->finalizationService->finalizeAttempt($lockedAttempt, 'timeout_expired');
                    throw new DomainException('This assessment attempt has expired and was finalized.');
                }

                /** @var AssessmentAttemptAnswer|null $existing */
                $existing = AssessmentAttemptAnswer::query()
                    ->where('attempt_id', $lockedAttempt->id)
                    ->where('canonical_question_id', (string) $questionId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $existingRevision = (int) $existing->revision;

                    if ($revision < $existingRevision) {
                        throw new DomainException("Stale answer revision rejected. Current revision is {$existingRevision}.");
                    }

                    if ($revision === $existingRevision) {
                        if ($idempotencyKey !== null && $existing->last_idempotency_key === $idempotencyKey) {
                            return $existing; // Idempotent replay
                        }
                        throw new DomainException("Conflicting revision {$revision} without matching idempotency key.");
                    }

                    // Newer revision: update answer
                    $existing->student_answer = $rawAnswer;
                    $existing->revision = $revision;
                    $existing->last_idempotency_key = $idempotencyKey;
                    $existing->saved_at = $now;
                    $existing->save();

                    return $existing;
                }

                // First save for this question
                return AssessmentAttemptAnswer::create([
                    'attempt_id' => $lockedAttempt->id,
                    'question_id' => QuizQuestion::where('id', $questionId)->exists() ? $questionId : null,
                    'bank_question_id' => Question::where('id', $questionId)->exists() ? $questionId : null,
                    'canonical_question_id' => (string) $questionId,
                    'student_answer' => $rawAnswer,
                    'question_snapshot' => $matchedQuestion,
                    'revision' => max(1, $revision),
                    'last_idempotency_key' => $idempotencyKey,
                    'saved_at' => $now,
                    'points_possible' => (float) ($matchedQuestion['points'] ?? 1),
                    'points_awarded' => 0.0,
                    'is_correct' => null,
                ]);
            });
        });
    }

    /**
     * Manually grade student answers in an assessment attempt.
     *
     * Principles:
     * - Only authorized evaluators (instructors / admins) can grade.
     * - Trainers can only grade courses assigned to them.
     * - Support staff cannot evaluate.
     * - Evaluator identity and timestamps are recorded.
     * - Updates total earned points and recalculates pass/fail status.
     * - Passing triggers course completion evaluation.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function gradeAttempt(
        AssessmentAttempt $attempt,
        array $manualScores = [],
        ?User $evaluator = null,
        ?string $notes = null,
        ?array $grades = null,
        ?string $overallFeedback = null
    ): AssessmentAttempt {
        $manualScores = $grades ?? $manualScores;
        $notes = $overallFeedback ?? $notes;
        $evaluator ??= Auth::user();
        if (! $evaluator instanceof User) {
            throw new AuthorizationException('Authenticated evaluator required.');
        }

        $course = $attempt->assessment?->course ?? $attempt->enrollment?->course;
        $this->authorizeEvaluator($evaluator, $course);

        return $this->finalizationService->runWithDatabaseRetries(function () use ($attempt, $manualScores, $evaluator, $notes) {
            return DB::transaction(function () use ($attempt, $manualScores, $evaluator, $notes) {
                /** @var AssessmentAttempt $lockedAttempt */
                $lockedAttempt = AssessmentAttempt::query()
                    ->where('id', $attempt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $now = Carbon::now();

                foreach ($manualScores as $questionId => $grading) {
                    $answer = $lockedAttempt->answers()->where(function ($q) use ($questionId) {
                        $q->where('question_id', $questionId)
                            ->orWhere('canonical_question_id', (string) $questionId);
                    })->first();

                    if ($answer === null) {
                        continue;
                    }

                    $points = isset($grading['points']) ? (float) $grading['points'] : (isset($grading['points_awarded']) ? (float) $grading['points_awarded'] : 0.0);
                    $points = max(0.0, min((float) $answer->points_possible, $points));

                    $answer->points_awarded = $points;
                    $answer->is_correct = ($points >= (float) $answer->points_possible);
                    $answer->evaluator_feedback = $grading['feedback'] ?? null;
                    $answer->evaluated_by = $evaluator->id;
                    $answer->evaluated_at = $now;
                    $answer->save();
                }

                // Check if any answers remain un-evaluated
                $hasPendingAnswers = $lockedAttempt->answers()->whereNull('is_correct')->exists();

                $totalEarned = (float) $lockedAttempt->answers()->sum('points_awarded');
                $totalPossible = (float) $lockedAttempt->answers()->sum('points_possible');

                $lockedAttempt->earned_points = $totalEarned;
                $lockedAttempt->total_points = $totalPossible;
                $percentage = $totalPossible > 0
                    ? round(($totalEarned / $totalPossible) * 100, 2)
                    : 0;
                $lockedAttempt->score_percentage = $percentage;

                if (! $hasPendingAnswers) {
                    $lockedAttempt->requires_manual_grading = false;
                    $lockedAttempt->status = AssessmentAttempt::STATUS_EVALUATED;
                    $lockedAttempt->evaluated_by = $evaluator->id;
                    $lockedAttempt->evaluated_at = $now;
                    $lockedAttempt->evaluation_notes = $notes;
                    $lockedAttempt->passed = ($percentage >= $lockedAttempt->assessment->getPassingScore());
                }

                $lockedAttempt->save();

                // Re-evaluate course completion if attempt is now passed
                if ($lockedAttempt->isPassed()) {
                    $this->completionService->completeCourse($lockedAttempt->enrollment, $lockedAttempt->user);
                }

                DB::afterCommit(function () use ($lockedAttempt, $evaluator) {
                    event(new AssessmentGraded($lockedAttempt, $evaluator));
                });

                return $lockedAttempt->fresh(['answers.question']);
            });
        });
    }

    /**
     * Retrieve student assessment data with answer keys strictly stripped.
     */
    public function getStudentAssessmentData(Quiz $assessment, Enrollment $enrollment, User $user): array
    {
        $this->authorizeStudentEnrollment($enrollment, $user);
        $this->validateAssessmentBelongsToCourse($enrollment, $assessment);

        $now = Carbon::now();

        /** @var AssessmentAttempt|null $inProgressAttempt */
        $inProgressAttempt = AssessmentAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('assessment_id', $assessment->id)
            ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
            ->first();

        // Lazy finalization if expired
        if ($inProgressAttempt !== null && $inProgressAttempt->finalize_after !== null && $now->greaterThanOrEqualTo($inProgressAttempt->finalize_after)) {
            $this->finalizationService->finalizeAttempt($inProgressAttempt, 'timeout_expired');
            $inProgressAttempt = null; // No longer in progress
        }

        if ($inProgressAttempt !== null) {
            $questions = $inProgressAttempt->getSanitizedQuestionsForStudent();
        } elseif ($assessment->hasQuestionPools()) {
            $questions = [];
        } else {
            $questions = $assessment->questions->map->getSanitizedForStudent()->values()->all();
        }

        $completedAttempts = AssessmentAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('assessment_id', $assessment->id)
            ->whereIn('status', [AssessmentAttempt::STATUS_SUBMITTED, AssessmentAttempt::STATUS_EVALUATED])
            ->orderByDesc('attempt_number')
            ->get();

        $hasPassed = $completedAttempts->contains(fn (AssessmentAttempt $a) => $a->isPassed());

        $persistedAnswers = [];
        $remainingSeconds = null;

        if ($inProgressAttempt !== null) {
            $answers = $inProgressAttempt->answers()
                ->get(['canonical_question_id', 'student_answer', 'revision']);

            foreach ($answers as $ans) {
                $raw = $ans->student_answer;
                $decoded = json_decode((string) $raw, true);
                $persistedAnswers[(string) $ans->canonical_question_id] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded)))
                    ? $decoded
                    : $raw;
            }

            if ($inProgressAttempt->expires_at !== null) {
                $remainingSeconds = max(0, $now->diffInSeconds($inProgressAttempt->expires_at, false));
            }
        }

        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'type' => $assessment->type ?? 'quiz',
            'is_required' => (bool) $assessment->is_required,
            'attempt_limit' => $assessment->attempt_limit,
            'remaining_attempts' => $assessment->getRemainingAttempts($enrollment),
            'time_limit_minutes' => $assessment->time_limit_minutes,
            'passing_percentage' => $assessment->getPassingScore(),
            'total_questions' => count($questions),
            'questions' => $questions,
            'in_progress_attempt' => $inProgressAttempt ? [
                'id' => $inProgressAttempt->id,
                'attempt_number' => $inProgressAttempt->attempt_number,
                'started_at' => $inProgressAttempt->started_at->toIso8601String(),
                'expires_at' => $inProgressAttempt->expires_at?->toIso8601String(),
                'finalize_after' => $inProgressAttempt->finalize_after?->toIso8601String(),
                'remaining_seconds' => $remainingSeconds,
                'answers' => $persistedAnswers,
            ] : null,
            'completed_attempts' => $completedAttempts->map(fn (AssessmentAttempt $a) => [
                'id' => $a->id,
                'attempt_number' => $a->attempt_number,
                'status' => $a->status,
                'score_percentage' => (float) $a->score_percentage,
                'passed' => (bool) $a->passed,
                'requires_manual_grading' => (bool) $a->requires_manual_grading,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
            ]),
            'has_passed' => $hasPassed,
        ];
    }

    public function authorizeStudentEnrollment(Enrollment $enrollment, User $user): void
    {
        if (! $user->is_active) {
            throw new AuthorizationException('User account is inactive.');
        }

        $studentProfile = $enrollment->studentProfile;
        if ($studentProfile === null || (string) $studentProfile->user_id !== (string) $user->id) {
            throw new AuthorizationException('You do not own this course enrollment.');
        }

        if (! $enrollment->allowsCourseAccess()) {
            throw new AuthorizationException('Enrollment does not permit course access.');
        }
    }

    public function validateAssessmentBelongsToCourse(Enrollment $enrollment, Quiz $assessment): void
    {
        $courseId = (string) $enrollment->course_id;

        if ($assessment->course_id !== null && (string) $assessment->course_id === $courseId) {
            return;
        }

        if ($assessment->module !== null && (string) $assessment->module->course_id === $courseId) {
            return;
        }

        if ($assessment->lesson?->module !== null && (string) $assessment->lesson->module->course_id === $courseId) {
            return;
        }

        throw new InvalidArgumentException('Assessment does not belong to the enrolled course curriculum.');
    }

    public function authorizeEvaluator(User $user, ?Course $course = null): void
    {
        if (! $user->is_active || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            throw new AuthorizationException('Only academy administrators and instructors can evaluate assessments.');
        }

        if ($user->hasRole('trainer') && ! $user->hasRole('admin') && $course !== null) {
            $isAssigned = $course->trainers()->where('users.id', $user->id)->exists();
            if (! $isAssigned) {
                throw new AuthorizationException('Trainers can only evaluate assessments for courses assigned to them.');
            }
        }
    }
}
