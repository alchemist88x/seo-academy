<?php

namespace App\Domain\Assessments\Services;

use App\Domain\Assessments\Events\AssessmentGraded;
use App\Domain\Assessments\Events\AssessmentSubmitted;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Questions\Models\Question;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssessmentFinalizationService
{
    public function __construct(
        protected ?CourseCompletionService $completionService = null
    ) {
        $this->completionService ??= app(CourseCompletionService::class);
    }

    /**
     * Finalize an assessment attempt in a server-authoritative, atomic, and idempotent transaction.
     *
     * @param  string  $trigger  Trigger source ('student_submit', 'timeout_expired', 'admin_forced')
     * @param  array|null  $submittedAnswers  Optional batch of answers submitted at finalization time
     * @param  User|null  $user  The user requesting finalization (if student_submit)
     *
     * @throws AuthorizationException
     */
    public function finalizeAttempt(
        AssessmentAttempt $attempt,
        string $trigger = 'student_submit',
        ?array $submittedAnswers = null,
        ?User $user = null
    ): AssessmentAttempt {
        if ($user !== null && (string) $attempt->user_id !== (string) $user->id) {
            throw new AuthorizationException('You do not have authorization to finalize this attempt.');
        }

        return $this->runWithDatabaseRetries(function () use ($attempt, $trigger, $submittedAnswers) {
            return DB::transaction(function () use ($attempt, $trigger, $submittedAnswers) {
                /** @var AssessmentAttempt $lockedAttempt */
                $lockedAttempt = AssessmentAttempt::query()
                    ->where('id', $attempt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Idempotent guard: if already finalized, return immediately without re-grading or re-firing events
                if (! $lockedAttempt->isInProgress()) {
                    return $lockedAttempt->fresh(['answers.question', 'answers.bankQuestion']);
                }

                $assessment = $lockedAttempt->assessment;
                $questions = $lockedAttempt->getQuestionsForAttempt();
                $now = Carbon::now();

                $isExpired = $lockedAttempt->finalize_after !== null && $now->greaterThanOrEqualTo($lockedAttempt->finalize_after);
                $effectiveTrigger = ($isExpired && $trigger === 'student_submit') ? 'timeout_expired' : $trigger;

                // Load existing persisted answers indexed by canonical_question_id
                $existingAnswers = AssessmentAttemptAnswer::query()
                    ->where('attempt_id', $lockedAttempt->id)
                    ->get()
                    ->keyBy(fn (AssessmentAttemptAnswer $a) => (string) $a->canonical_question_id);

                $earnedPoints = 0.0;
                $totalPoints = 0.0;
                $hasManualGrading = false;

                foreach ($questions as $question) {
                    $questionId = (string) $question['id'];
                    $pointsPossible = (float) ($question['points'] ?? 1);
                    $totalPoints += $pointsPossible;

                    // Resolve answer: prefer persisted answer from autosave; only accept new submittedAnswers if not expired
                    /** @var AssessmentAttemptAnswer|null $existingAnswer */
                    $existingAnswer = $existingAnswers->get($questionId);
                    $studentAnswerValue = null;

                    if ($existingAnswer !== null && $existingAnswer->student_answer !== null) {
                        $studentAnswerValue = $existingAnswer->student_answer;
                    } elseif (! $isExpired && $submittedAnswers !== null && array_key_exists($questionId, $submittedAnswers)) {
                        $submittedVal = $submittedAnswers[$questionId];
                        $studentAnswerValue = is_array($submittedVal)
                            ? json_encode($submittedVal)
                            : ($submittedVal !== null ? (string) $submittedVal : null);
                    }

                    $isAutoGraded = ! empty($question['is_auto_graded']) || in_array($question['question_type'], [
                        QuizQuestion::TYPE_SINGLE_CHOICE,
                        QuizQuestion::TYPE_MULTIPLE_CHOICE,
                        QuizQuestion::TYPE_TRUE_FALSE,
                    ], true);

                    $isCorrect = null;
                    $pointsAwarded = 0.0;

                    if ($isAutoGraded) {
                        // Decode raw answer for evaluation if it was stored as JSON or string
                        $evalValue = $studentAnswerValue;
                        if (is_string($studentAnswerValue)) {
                            $decoded = json_decode($studentAnswerValue, true);
                            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded))) {
                                $evalValue = $decoded;
                            }
                        }

                        $isCorrect = $this->evaluateAutoGradedQuestionFromSnapshot($question, $evalValue);
                        if ($isCorrect) {
                            $pointsAwarded = $pointsPossible;
                            $earnedPoints += $pointsAwarded;
                        }
                    } else {
                        // Subjective questions (short_answer, long_answer) require manual instructor evaluation
                        $hasManualGrading = true;
                        $isCorrect = null;
                        $pointsAwarded = 0.0;
                    }

                    if ($existingAnswer !== null) {
                        $existingAnswer->is_correct = $isCorrect;
                        $existingAnswer->points_awarded = $pointsAwarded;
                        $existingAnswer->points_possible = $pointsPossible;
                        if ($existingAnswer->student_answer === null && $studentAnswerValue !== null) {
                            $existingAnswer->student_answer = $studentAnswerValue;
                            $existingAnswer->saved_at = $now;
                        }
                        $existingAnswer->save();
                    } else {
                        AssessmentAttemptAnswer::create([
                            'attempt_id' => $lockedAttempt->id,
                            'question_id' => QuizQuestion::where('id', $questionId)->exists() ? $questionId : null,
                            'bank_question_id' => Question::where('id', $questionId)->exists() ? $questionId : null,
                            'canonical_question_id' => $questionId,
                            'student_answer' => $studentAnswerValue,
                            'is_correct' => $isCorrect,
                            'points_awarded' => $pointsAwarded,
                            'points_possible' => $pointsPossible,
                            'question_snapshot' => $question,
                            'revision' => 1,
                            'saved_at' => $now,
                        ]);
                    }
                }

                $passingPercentage = $assessment ? $assessment->getPassingScore() : 70;
                $percentage = $totalPoints > 0
                    ? round(($earnedPoints / $totalPoints) * 100, 2)
                    : 0.0;

                $lockedAttempt->submitted_at ??= $now;
                $lockedAttempt->total_points = $totalPoints;
                $lockedAttempt->earned_points = $earnedPoints;
                $lockedAttempt->score_percentage = $percentage;
                $lockedAttempt->finalization_trigger = $effectiveTrigger;

                if ($hasManualGrading) {
                    $lockedAttempt->status = AssessmentAttempt::STATUS_SUBMITTED;
                    $lockedAttempt->requires_manual_grading = true;
                    $lockedAttempt->passed = false;
                } else {
                    $lockedAttempt->status = AssessmentAttempt::STATUS_EVALUATED;
                    $lockedAttempt->requires_manual_grading = false;
                    $lockedAttempt->evaluated_at = $now;
                    $lockedAttempt->passed = ($percentage >= $passingPercentage);
                }

                $lockedAttempt->save();

                // Re-evaluate course completion if attempt passed
                if ($lockedAttempt->isPassed()) {
                    $this->completionService->completeCourse($lockedAttempt->enrollment, $lockedAttempt->user);
                }

                DB::afterCommit(function () use ($lockedAttempt) {
                    event(new AssessmentSubmitted($lockedAttempt));
                    if ($lockedAttempt->isEvaluated()) {
                        event(new AssessmentGraded($lockedAttempt));
                    }
                });

                return $lockedAttempt->fresh(['answers.question', 'answers.bankQuestion']);
            });
        });
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
            } catch (QueryException $e) {
                $attempt++;
                $isLockError = str_contains($e->getMessage(), 'database is locked')
                    || str_contains($e->getMessage(), 'SQLITE_BUSY')
                    || str_contains($e->getMessage(), 'Deadlock found')
                    || str_contains($e->getMessage(), 'Lock wait timeout');

                if ($attempt >= $maxRetries || ! $isLockError) {
                    throw $e;
                }

                usleep($baseSleepMs * 1000 * $attempt);
            }
        }
    }

    /**
     * Evaluate the correctness of an auto-graded question from an immutable snapshot.
     */
    public function evaluateAutoGradedQuestionFromSnapshot(array $question, mixed $studentAnswer): bool
    {
        if ($studentAnswer === null) {
            return false;
        }

        $correctAnswer = $question['correct_answer'] ?? null;
        $type = $question['question_type'] ?? '';

        return match ($type) {
            QuizQuestion::TYPE_SINGLE_CHOICE,
            Question::TYPE_SINGLE_CHOICE => $this->evaluateSingleChoice($studentAnswer, $correctAnswer),

            QuizQuestion::TYPE_TRUE_FALSE,
            Question::TYPE_TRUE_FALSE => $this->evaluateTrueFalse($studentAnswer, $correctAnswer),

            QuizQuestion::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_MULTIPLE_CHOICE => $this->evaluateMultipleChoice($studentAnswer, $correctAnswer),

            default => false,
        };
    }

    private function evaluateSingleChoice(mixed $studentAnswer, mixed $correctAnswer): bool
    {
        if ($correctAnswer === null) {
            return false;
        }

        return strtolower(trim((string) $studentAnswer)) === strtolower(trim((string) $correctAnswer));
    }

    private function evaluateTrueFalse(mixed $studentAnswer, mixed $correctAnswer): bool
    {
        $normalizedStudent = filter_var($studentAnswer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $normalizedCorrect = filter_var($correctAnswer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalizedStudent === null || $normalizedCorrect === null) {
            return strtolower(trim((string) $studentAnswer)) === strtolower(trim((string) $correctAnswer));
        }

        return $normalizedStudent === $normalizedCorrect;
    }

    private function evaluateMultipleChoice(mixed $studentAnswer, mixed $correctAnswer): bool
    {
        $studentKeys = is_array($studentAnswer) ? $studentAnswer : json_decode((string) $studentAnswer, true);
        $correctKeys = is_array($correctAnswer) ? $correctAnswer : json_decode((string) $correctAnswer, true);

        if (! is_array($studentKeys) || ! is_array($correctKeys)) {
            return false;
        }

        $studentKeys = array_map(fn ($k) => strtolower(trim((string) $k)), $studentKeys);
        $correctKeys = array_map(fn ($k) => strtolower(trim((string) $k)), $correctKeys);

        sort($studentKeys);
        sort($correctKeys);

        return $studentKeys === $correctKeys;
    }
}
