<?php

namespace App\Domain\Quizzes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasUuids;

    protected $table = 'quiz_questions';

    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_SHORT_ANSWER = 'short_answer';

    public const TYPE_LONG_ANSWER = 'long_answer';

    public const QUESTION_TYPES = [
        self::TYPE_SINGLE_CHOICE,
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_TRUE_FALSE,
        self::TYPE_SHORT_ANSWER,
        self::TYPE_LONG_ANSWER,
    ];

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'prompt',
        'type',
        'options',
        'correct_answer',
        'answer_key',
        'points',
        'display_order',
        'explanation',
        'rubric',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function isAutoGraded(): bool
    {
        return in_array($this->question_type, [
            self::TYPE_SINGLE_CHOICE,
            self::TYPE_MULTIPLE_CHOICE,
            self::TYPE_TRUE_FALSE,
        ], true);
    }

    public function isObjective(): bool
    {
        return $this->isAutoGraded();
    }

    public function getPromptAttribute(): ?string
    {
        return $this->question_text;
    }

    public function setPromptAttribute(?string $value): void
    {
        $this->attributes['question_text'] = $value;
    }

    public function getTypeAttribute(): ?string
    {
        return $this->question_type;
    }

    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['question_type'] = $value;
    }

    public function getAnswerKeyAttribute(): mixed
    {
        return $this->correct_answer;
    }

    public function setAnswerKeyAttribute(mixed $value): void
    {
        $this->attributes['correct_answer'] = is_array($value) ? json_encode($value) : (string) $value;
    }

    public function requiresManualGrading(): bool
    {
        return in_array($this->question_type, [
            self::TYPE_SHORT_ANSWER,
            self::TYPE_LONG_ANSWER,
        ], true);
    }

    /**
     * Return a sanitized question representation for student delivery.
     * Guarantees correct answers and grading rubrics are never exposed.
     */
    public function getSanitizedForStudent(): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'options' => $this->options,
            'points' => $this->points ?? 1,
            'display_order' => $this->display_order,
        ];
    }
}
