<?php

namespace App\Domain\Quizzes\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuizQuestionPool extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $table = 'quiz_question_pools';

    protected $fillable = [
        'academy_id',
        'quiz_id',
        'question_bank_id',
        'question_count',
        'difficulty',
        'category',
        'question_type',
        'points_per_question',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'points_per_question' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /**
     * Query of active questions in the bank that satisfy all pool criteria.
     */
    public function getEligibleQuestionsQuery(): Builder|BelongsToMany
    {
        $bank = $this->questionBank ?? QuestionBank::find($this->question_bank_id);

        $query = $bank->questions()
            ->where('questions.status', Question::STATUS_ACTIVE);

        if (! empty($this->difficulty)) {
            $query->where('questions.difficulty', $this->difficulty);
        }

        if (! empty($this->category)) {
            $query->where('questions.category', $this->category);
        }

        if (! empty($this->question_type)) {
            $query->where('questions.question_type', $this->question_type);
        }

        return $query;
    }

    public function getAvailableCount(): int
    {
        return $this->getEligibleQuestionsQuery()->count();
    }

    public function hasSufficientQuestions(): bool
    {
        return $this->getAvailableCount() >= (int) $this->question_count;
    }

    public function isSufficient(): bool
    {
        return $this->hasSufficientQuestions();
    }
}
