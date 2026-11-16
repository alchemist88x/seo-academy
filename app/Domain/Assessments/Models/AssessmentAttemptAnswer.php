<?php

namespace App\Domain\Assessments\Models;

use App\Domain\Questions\Models\Question;
use App\Domain\Quizzes\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttemptAnswer extends Model
{
    use HasUuids;

    protected $table = 'assessment_attempt_answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'bank_question_id',
        'student_answer',
        'is_correct',
        'points_awarded',
        'points_possible',
        'evaluator_feedback',
        'evaluated_by',
        'evaluated_at',
        'question_snapshot',
        'canonical_question_id',
        'revision',
        'last_idempotency_key',
        'saved_at',
    ];

    protected $hidden = [
        'question_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
            'points_possible' => 'decimal:2',
            'evaluated_at' => 'datetime',
            'question_snapshot' => 'array',
            'revision' => 'integer',
            'saved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AssessmentAttemptAnswer $answer) {
            $answer->canonical_question_id = static::validateAndResolveCanonicalId($answer);
        });
    }

    /**
     * Validate and resolve canonical question identity.
     *
     * @throws \InvalidArgumentException
     */
    public static function validateAndResolveCanonicalId(array|AssessmentAttemptAnswer $attributes): string
    {
        $get = function (string $key) use ($attributes) {
            if ($attributes instanceof AssessmentAttemptAnswer) {
                return $attributes->getAttribute($key);
            }

            return $attributes[$key] ?? null;
        };

        $canonicalId = $get('canonical_question_id');
        $questionId = $get('question_id');
        $bankQuestionId = $get('bank_question_id');
        $snapshot = $get('question_snapshot');

        $snapshotId = null;
        if (! empty($snapshot)) {
            $parsed = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;
            if (is_array($parsed) && ! empty($parsed['id'])) {
                $snapshotId = (string) $parsed['id'];
            }
        }

        $hasQuestionId = ! empty($questionId);
        $hasBankQuestionId = ! empty($bankQuestionId);

        // 1. If both question_id and bank_question_id are provided, they must match
        if ($hasQuestionId && $hasBankQuestionId && (string) $questionId !== (string) $bankQuestionId) {
            throw new \InvalidArgumentException(
                "Conflicting question references: question_id [{$questionId}] does not match bank_question_id [{$bankQuestionId}]."
            );
        }

        // 2. If snapshot has an id and foreign keys are provided, snapshot id must match
        if ($snapshotId !== null) {
            if ($hasQuestionId && (string) $questionId !== $snapshotId) {
                throw new \InvalidArgumentException(
                    "Conflicting question references: question_id [{$questionId}] does not match snapshot id [{$snapshotId}]."
                );
            }
            if ($hasBankQuestionId && (string) $bankQuestionId !== $snapshotId) {
                throw new \InvalidArgumentException(
                    "Conflicting question references: bank_question_id [{$bankQuestionId}] does not match snapshot id [{$snapshotId}]."
                );
            }
        }

        // 3. If explicit canonical_question_id is supplied, it must match any provided reference
        if (! empty($canonicalId)) {
            $canonicalStr = (string) $canonicalId;
            if ($hasQuestionId && (string) $questionId !== $canonicalStr) {
                throw new \InvalidArgumentException(
                    "Conflicting canonical identity: canonical_question_id [{$canonicalStr}] does not match question_id [{$questionId}]."
                );
            }
            if ($hasBankQuestionId && (string) $bankQuestionId !== $canonicalStr) {
                throw new \InvalidArgumentException(
                    "Conflicting canonical identity: canonical_question_id [{$canonicalStr}] does not match bank_question_id [{$bankQuestionId}]."
                );
            }
            if ($snapshotId !== null && $snapshotId !== $canonicalStr) {
                throw new \InvalidArgumentException(
                    "Conflicting canonical identity: canonical_question_id [{$canonicalStr}] does not match snapshot id [{$snapshotId}]."
                );
            }

            return $canonicalStr;
        }

        // 4. Resolve canonical ID when canonical_question_id is not explicitly provided
        if ($hasQuestionId) {
            return (string) $questionId;
        }

        if ($hasBankQuestionId) {
            return (string) $bankQuestionId;
        }

        if ($snapshotId !== null) {
            return $snapshotId;
        }

        throw new \InvalidArgumentException(
            'Cannot resolve canonical question identity: missing question_id, bank_question_id, and question_snapshot id.'
        );
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function bankQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'bank_question_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function getQuestionText(): string
    {
        if (! empty($this->question_snapshot['question_text'])) {
            return $this->question_snapshot['question_text'];
        }

        if (! empty($this->question_snapshot['prompt'])) {
            return $this->question_snapshot['prompt'];
        }

        return $this->bankQuestion?->question_text ?? $this->question?->prompt ?? $this->question?->question_text ?? 'Unknown Question';
    }

    public function getQuestionType(): string
    {
        if (! empty($this->question_snapshot['question_type'])) {
            return $this->question_snapshot['question_type'];
        }

        if (! empty($this->question_snapshot['type'])) {
            return $this->question_snapshot['type'];
        }

        return $this->bankQuestion?->question_type ?? $this->question?->type ?? $this->question?->question_type ?? 'single_choice';
    }

    public function getPointsPossible(): float
    {
        if ($this->points_possible !== null) {
            return (float) $this->points_possible;
        }

        if (isset($this->question_snapshot['points'])) {
            return (float) $this->question_snapshot['points'];
        }

        return (float) ($this->bankQuestion?->points ?? $this->question?->points ?? 1);
    }

    public function getRubric(): ?string
    {
        if (isset($this->question_snapshot['rubric'])) {
            return $this->question_snapshot['rubric'];
        }

        return $this->bankQuestion?->rubric ?? $this->question?->rubric ?? null;
    }
}
