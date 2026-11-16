<?php

namespace App\Domain\Questions\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestionPool;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QuestionBankService
{
    /**
     * Create a new question bank within the academy.
     */
    public function createBank(Academy $academy, array $data, ?User $creator = null): QuestionBank
    {
        if (empty(trim($data['name'] ?? ''))) {
            throw new InvalidArgumentException('Question bank name is required.');
        }

        return QuestionBank::create([
            'academy_id' => $academy->id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'category' => ! empty($data['category']) ? trim($data['category']) : null,
            'created_by' => $creator?->id,
            'status' => $data['status'] ?? QuestionBank::STATUS_ACTIVE,
        ]);
    }

    /**
     * Update an existing question bank.
     */
    public function updateBank(QuestionBank $bank, array $data): QuestionBank
    {
        if (isset($data['name']) && empty(trim($data['name']))) {
            throw new InvalidArgumentException('Question bank name cannot be empty.');
        }

        $bank->update([
            'name' => isset($data['name']) ? trim($data['name']) : $bank->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $bank->description,
            'category' => array_key_exists('category', $data) ? (! empty($data['category']) ? trim($data['category']) : null) : $bank->category,
            'status' => $data['status'] ?? $bank->status,
        ]);

        return $bank;
    }

    /**
     * Archive a question bank.
     */
    public function archiveBank(QuestionBank $bank): void
    {
        $bank->update(['status' => QuestionBank::STATUS_ARCHIVED]);
    }

    /**
     * Restore an archived question bank.
     */
    public function restoreBank(QuestionBank $bank): void
    {
        $bank->update(['status' => QuestionBank::STATUS_ACTIVE]);
    }

    /**
     * Delete a question bank and its pool references.
     * Note: Questions attached to this bank remain intact in the academy questions repository.
     */
    public function deleteBank(QuestionBank $bank): void
    {
        DB::transaction(function () use ($bank) {
            $bank->questions()->detach();
            $bank->pools()->delete();
            $bank->delete();
        });
    }

    /**
     * Create a question within the academy, optionally attaching it directly to a question bank.
     */
    public function createQuestion(Academy $academy, array $data, ?QuestionBank $bank = null, ?User $creator = null): Question
    {
        if (empty(trim($data['question_text'] ?? ''))) {
            throw new InvalidArgumentException('Question text is required.');
        }

        $type = $data['question_type'] ?? Question::TYPE_SINGLE_CHOICE;
        if (! in_array($type, Question::QUESTION_TYPES, true)) {
            throw new InvalidArgumentException("Invalid question type: {$type}");
        }

        $difficulty = $data['difficulty'] ?? Question::DIFFICULTY_MEDIUM;
        if (! in_array($difficulty, Question::DIFFICULTIES, true)) {
            $difficulty = Question::DIFFICULTY_MEDIUM;
        }

        return DB::transaction(function () use ($academy, $data, $bank, $creator, $type, $difficulty) {
            $question = Question::create([
                'academy_id' => $academy->id,
                'created_by' => $creator?->id,
                'question_text' => trim($data['question_text']),
                'question_type' => $type,
                'options' => $data['options'] ?? null,
                'correct_answer' => is_array($data['correct_answer'] ?? null)
                    ? json_encode($data['correct_answer'])
                    : ($data['correct_answer'] ?? null),
                'explanation' => $data['explanation'] ?? null,
                'rubric' => $data['rubric'] ?? null,
                'points' => max(1, (int) ($data['points'] ?? 1)),
                'difficulty' => $difficulty,
                'category' => ! empty($data['category']) ? trim($data['category']) : null,
                'tags' => $data['tags'] ?? null,
                'status' => $data['status'] ?? Question::STATUS_ACTIVE,
            ]);

            if ($bank !== null && (string) $bank->academy_id === (string) $academy->id) {
                $maxOrder = (int) $bank->questions()->max('question_bank_questions.display_order');
                $bank->questions()->attach($question->id, [
                    'id' => (string) Str::uuid(),
                    'display_order' => $maxOrder + 1,
                ]);
            }

            return $question;
        });
    }

    /**
     * Update question content, options, answer key, points, or metadata.
     */
    public function updateQuestion(Question $question, array $data): Question
    {
        if (isset($data['question_text']) && empty(trim($data['question_text']))) {
            throw new InvalidArgumentException('Question text cannot be empty.');
        }

        $payload = [];

        if (isset($data['question_text'])) {
            $payload['question_text'] = trim($data['question_text']);
        }

        if (isset($data['question_type']) && in_array($data['question_type'], Question::QUESTION_TYPES, true)) {
            $payload['question_type'] = $data['question_type'];
        }

        if (array_key_exists('options', $data)) {
            $payload['options'] = $data['options'];
        }

        if (array_key_exists('correct_answer', $data)) {
            $payload['correct_answer'] = is_array($data['correct_answer'])
                ? json_encode($data['correct_answer'])
                : $data['correct_answer'];
        }

        if (array_key_exists('explanation', $data)) {
            $payload['explanation'] = $data['explanation'];
        }

        if (array_key_exists('rubric', $data)) {
            $payload['rubric'] = $data['rubric'];
        }

        if (isset($data['points'])) {
            $payload['points'] = max(1, (int) $data['points']);
        }

        if (isset($data['difficulty']) && in_array($data['difficulty'], Question::DIFFICULTIES, true)) {
            $payload['difficulty'] = $data['difficulty'];
        }

        if (array_key_exists('category', $data)) {
            $payload['category'] = ! empty($data['category']) ? trim($data['category']) : null;
        }

        if (array_key_exists('tags', $data)) {
            $payload['tags'] = $data['tags'];
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        $question->update($payload);

        return $question;
    }

    /**
     * Attach an existing question to a bank without duplicating question data.
     */
    public function addQuestionToBank(QuestionBank $bank, Question $question, ?int $displayOrder = null): void
    {
        if ((string) $bank->academy_id !== (string) $question->academy_id) {
            throw new DomainException('Cannot attach a question from a different academy.');
        }

        if ($bank->questions()->where('questions.id', $question->id)->exists()) {
            return; // Idempotent
        }

        $order = $displayOrder ?? ((int) $bank->questions()->max('question_bank_questions.display_order') + 1);
        $bank->questions()->attach($question->id, [
            'id' => (string) Str::uuid(),
            'display_order' => $order,
        ]);
    }

    /**
     * Remove a question from a bank without deleting the question record.
     */
    public function removeQuestionFromBank(QuestionBank $bank, Question $question): void
    {
        $bank->questions()->detach($question->id);
    }

    /**
     * Add a configurable question pool to a quiz.
     */
    public function addPoolToQuiz(Quiz $quiz, QuestionBank $bank, int $questionCount, array $filters = []): QuizQuestionPool
    {
        $quizAcademyId = $quiz->academy_id ?? $quiz->course?->academy_id ?? $bank->academy_id;
        if ((string) $quizAcademyId !== (string) $bank->academy_id) {
            throw new DomainException('Quiz and question bank must belong to the same academy.');
        }

        if ($questionCount <= 0) {
            throw new InvalidArgumentException('Question count must be at least 1.');
        }

        $difficulty = $filters['difficulty'] ?? null;
        $category = $filters['category'] ?? null;
        $type = $filters['question_type'] ?? null;
        $pointsPerQuestion = isset($filters['points_per_question']) ? (int) $filters['points_per_question'] : null;

        // Count eligible questions
        $eligibleQuery = $bank->questions()->where('status', Question::STATUS_ACTIVE);
        if (! empty($difficulty)) {
            $eligibleQuery->where('difficulty', $difficulty);
        }
        if (! empty($category)) {
            $eligibleQuery->where('category', $category);
        }
        if (! empty($type)) {
            $eligibleQuery->where('question_type', $type);
        }

        $availableCount = $eligibleQuery->count();
        if ($availableCount < $questionCount) {
            throw new InvalidArgumentException("Insufficient questions in bank '{$bank->name}'. Requested {$questionCount}, but only {$availableCount} eligible questions are available.");
        }

        $maxOrder = (int) $quiz->pools()->max('display_order');

        return QuizQuestionPool::create([
            'academy_id' => $quizAcademyId,
            'quiz_id' => $quiz->id,
            'question_bank_id' => $bank->id,
            'question_count' => $questionCount,
            'difficulty' => $difficulty,
            'category' => $category,
            'question_type' => $type,
            'points_per_question' => $pointsPerQuestion,
            'display_order' => $maxOrder + 1,
        ]);
    }

    /**
     * Resolve and snapshot the questions for an assessment attempt.
     *
     * Principles:
     * - Respects quiz pool configurations and direct questions.
     * - Randomizes selection from pools when randomize_questions is enabled.
     * - Prevents question duplicates if multiple pools overlap.
     * - Randomizes presentation order if randomize_order is enabled.
     * - Produces immutable snapshots preserving question content, points, and keys for stable attempts.
     *
     * @return array{selected_ids: array<int, string>, snapshots: array<int, array>}
     */
    public function resolveQuestionsForAttempt(Quiz $quiz): array
    {
        $selectedQuestions = collect();
        $usedQuestionIds = [];

        // 1. Resolve from question pools
        $pools = $quiz->pools()->with('questionBank')->orderBy('display_order')->get();

        foreach ($pools as $pool) {
            $eligibleQuery = $pool->getEligibleQuestionsQuery();

            if (! empty($usedQuestionIds)) {
                $eligibleQuery->whereNotIn('questions.id', $usedQuestionIds);
            }

            if ($quiz->randomize_questions) {
                // Random question selection from pool
                $questions = $eligibleQuery->inRandomOrder()->limit($pool->question_count)->get();
            } else {
                // Deterministic selection from pool
                $questions = $eligibleQuery->orderBy('question_bank_questions.display_order')->limit($pool->question_count)->get();
            }

            if ($questions->count() < $pool->question_count) {
                $bankName = $pool->questionBank?->name ?? 'Question Bank';
                throw new InvalidArgumentException("Insufficient unique questions in pool for '{$bankName}'. Required {$pool->question_count}, but only {$questions->count()} unique eligible questions available.");
            }

            foreach ($questions as $q) {
                $snapshot = $q->toAttemptSnapshot();
                if ($pool->points_per_question !== null && $pool->points_per_question > 0) {
                    $snapshot['points'] = (int) $pool->points_per_question;
                }
                $selectedQuestions->push($snapshot);
                $usedQuestionIds[] = $q->id;
            }
        }

        // 2. Resolve direct attached quiz questions (legacy compatibility & hybrid quizzes)
        $directQuestions = $quiz->questions()->orderBy('display_order')->get();
        foreach ($directQuestions as $dq) {
            if (! in_array($dq->id, $usedQuestionIds, true)) {
                $selectedQuestions->push([
                    'id' => $dq->id,
                    'question_text' => $dq->question_text,
                    'question_type' => $dq->question_type,
                    'options' => $dq->options,
                    'correct_answer' => $dq->correct_answer,
                    'explanation' => $dq->explanation,
                    'rubric' => $dq->rubric,
                    'points' => (int) ($dq->points ?? 1),
                    'difficulty' => Question::DIFFICULTY_MEDIUM,
                    'category' => null,
                    'is_auto_graded' => $dq->isAutoGraded(),
                    'requires_manual_grading' => $dq->requiresManualGrading(),
                ]);
                $usedQuestionIds[] = $dq->id;
            }
        }

        // 3. Randomize presentation order if configured
        if ($quiz->randomize_order) {
            $selectedQuestions = $selectedQuestions->shuffle();
        }

        $snapshots = $selectedQuestions->values()->all();
        $selectedIds = array_column($snapshots, 'id');

        return [
            'selected_ids' => $selectedIds,
            'snapshots' => $snapshots,
        ];
    }
}
