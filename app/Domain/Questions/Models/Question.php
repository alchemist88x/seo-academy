<?php

namespace App\Domain\Questions\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Question extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $table = 'questions';

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

    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTIES = [
        self::DIFFICULTY_EASY,
        self::DIFFICULTY_MEDIUM,
        self::DIFFICULTY_HARD,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'academy_id',
        'created_by',
        'question_text',
        'question_type',
        'options',
        'correct_answer',
        'explanation',
        'rubric',
        'points',
        'difficulty',
        'category',
        'tags',
        'status',
    ];

    protected $hidden = [
        'correct_answer',
        'explanation',
        'rubric',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'tags' => 'array',
            'points' => 'integer',
        ];
    }

    public function getCorrectAnswerAttribute($value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $value;
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBank::class, 'question_bank_questions', 'question_id', 'question_bank_id')
            ->withPivot('display_order')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeByDifficulty(Builder $query, ?string $difficulty): Builder
    {
        if ($difficulty && in_array($difficulty, self::DIFFICULTIES, true)) {
            return $query->where('difficulty', $difficulty);
        }

        return $query;
    }

    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if ($category) {
            return $query->where('category', $category);
        }

        return $query;
    }

    public function scopeByQuestionType(Builder $query, ?string $type): Builder
    {
        if ($type && in_array($type, self::QUESTION_TYPES, true)) {
            return $query->where('question_type', $type);
        }

        return $query;
    }

    public function isAutoGraded(): bool
    {
        return in_array($this->question_type, [
            self::TYPE_SINGLE_CHOICE,
            self::TYPE_MULTIPLE_CHOICE,
            self::TYPE_TRUE_FALSE,
        ], true);
    }

    public function requiresManualGrading(): bool
    {
        return in_array($this->question_type, [
            self::TYPE_SHORT_ANSWER,
            self::TYPE_LONG_ANSWER,
        ], true);
    }

    /**
     * Sanitized payload for student assessment delivery.
     * Guarantees correct answer, rubric, and explanation are never leaked.
     */
    public function getSanitizedForStudent(?int $displayOrder = null): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'options' => $this->options,
            'points' => (int) ($this->points ?? 1),
            'difficulty' => $this->difficulty ?? self::DIFFICULTY_MEDIUM,
            'category' => $this->category,
            'display_order' => $displayOrder ?? 0,
        ];
    }

    /**
     * Complete immutable snapshot of the question for stable attempt preservation.
     */
    public function toAttemptSnapshot(): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'options' => $this->options,
            'correct_answer' => $this->correct_answer,
            'explanation' => $this->explanation,
            'rubric' => $this->rubric,
            'points' => (int) ($this->points ?? 1),
            'difficulty' => $this->difficulty,
            'category' => $this->category,
            'is_auto_graded' => $this->isAutoGraded(),
            'requires_manual_grading' => $this->requiresManualGrading(),
        ];
    }
}
