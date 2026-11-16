<?php

namespace App\Domain\Assessments\Models;

use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAttempt extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $table = 'assessment_attempts';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_EVALUATED = 'evaluated';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_SUBMITTED,
        self::STATUS_EVALUATED,
        self::STATUS_ABANDONED,
    ];

    protected $fillable = [
        'academy_id',
        'assessment_id',
        'enrollment_id',
        'user_id',
        'attempt_number',
        'status',
        'started_at',
        'submitted_at',
        'total_points',
        'earned_points',
        'score_percentage',
        'passed',
        'requires_manual_grading',
        'evaluated_by',
        'evaluated_at',
        'evaluation_notes',
        'selected_question_ids',
        'question_snapshots',
        'expires_at',
        'finalize_after',
        'finalization_trigger',
        'config_snapshot',
        'has_conflicting_historical_answers',
    ];

    protected $hidden = [
        'question_snapshots',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'total_points' => 'decimal:2',
            'earned_points' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'passed' => 'boolean',
            'requires_manual_grading' => 'boolean',
            'evaluated_at' => 'datetime',
            'selected_question_ids' => 'array',
            'question_snapshots' => 'array',
            'expires_at' => 'datetime',
            'finalize_after' => 'datetime',
            'config_snapshot' => 'array',
            'has_conflicting_historical_answers' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'assessment_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAttemptAnswer::class, 'attempt_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_EVALUATED], true);
    }

    public function isEvaluated(): bool
    {
        return $this->status === self::STATUS_EVALUATED;
    }

    public function isPendingManualGrading(): bool
    {
        return $this->status === self::STATUS_SUBMITTED && (bool) $this->requires_manual_grading;
    }

    public function isPassed(): bool
    {
        return (bool) $this->passed && $this->isEvaluated();
    }

    public function getScorePossibleAttribute(): float
    {
        return (float) $this->total_points;
    }

    public function getScoreAwardedAttribute(): float
    {
        return (float) $this->earned_points;
    }

    public function getIsPassedAttribute(): bool
    {
        return $this->isPassed();
    }

    public function getEvaluatorIdAttribute(): ?string
    {
        return $this->evaluated_by;
    }

    public function getFeedbackAttribute(): ?string
    {
        return $this->evaluation_notes;
    }

    /**
     * Retrieve the persistent, immutable questions snapshot for this attempt.
     * Guaranteed stable: does not change if bank questions are edited or archived.
     *
     * @return array<int, array>
     */
    public function getQuestionsForAttempt(): array
    {
        if (! empty($this->question_snapshots) && is_array($this->question_snapshots)) {
            return $this->question_snapshots;
        }

        // Legacy fallback for attempts created before snapshots
        if ($this->assessment) {
            return $this->assessment->questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'question_type' => $q->question_type,
                    'options' => $q->options,
                    'correct_answer' => $q->correct_answer,
                    'explanation' => $q->explanation,
                    'rubric' => $q->rubric,
                    'points' => (int) ($q->points ?? 1),
                    'is_auto_graded' => $q->isAutoGraded(),
                    'requires_manual_grading' => $q->requiresManualGrading(),
                ];
            })->values()->all();
        }

        return [];
    }

    /**
     * Return student-safe questions array with correct answers and rubrics strictly stripped.
     */
    public function getSanitizedQuestionsForStudent(): array
    {
        $questions = $this->getQuestionsForAttempt();

        return array_map(function ($q, $index) {
            return [
                'id' => $q['id'],
                'question_text' => $q['question_text'] ?? '',
                'question_type' => $q['question_type'] ?? 'single_choice',
                'options' => $q['options'] ?? [],
                'points' => (int) ($q['points'] ?? 1),
                'difficulty' => $q['difficulty'] ?? 'medium',
                'category' => $q['category'] ?? null,
                'display_order' => $index + 1,
            ];
        }, $questions, array_keys($questions));
    }
}
