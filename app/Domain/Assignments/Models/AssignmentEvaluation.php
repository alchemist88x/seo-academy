<?php

namespace App\Domain\Assignments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentEvaluation extends Model
{
    use HasUuids;

    public const STATUS_GRADED = 'graded';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    protected $table = 'assignment_evaluations';

    protected $fillable = [
        'submission_id',
        'evaluator_id',
        'raw_score',
        'late_penalty_applied',
        'points_awarded',
        'points_possible',
        'score_percentage',
        'is_passed',
        'rubric_snapshot',
        'overall_feedback',
        'internal_notes',
        'status',
        'revision_reason',
        'is_override',
        'override_reason',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:2',
            'late_penalty_applied' => 'decimal:2',
            'points_awarded' => 'decimal:2',
            'points_possible' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'is_passed' => 'boolean',
            'rubric_snapshot' => 'array',
            'is_override' => 'boolean',
            'graded_at' => 'datetime',
        ];
    }

    public function getFinalScoreAttribute(): ?float
    {
        return $this->points_awarded !== null ? (float) $this->points_awarded : null;
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
