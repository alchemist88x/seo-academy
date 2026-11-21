<?php

namespace App\Domain\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEvaluation extends Model
{
    use HasUuids;

    protected $table = 'project_evaluations';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'submission_id',
        'evaluator_id',
        'points_awarded',
        'points_possible',
        'score_percentage',
        'is_passed',
        'status',
        'criteria_feedback',
        'trainer_feedback',
        'improvement_recommendations',
        'revision_reason',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'points_awarded' => 'decimal:2',
            'points_possible' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'is_passed' => 'boolean',
            'criteria_feedback' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProjectSubmission::class, 'submission_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
