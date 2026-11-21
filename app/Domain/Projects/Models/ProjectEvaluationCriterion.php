<?php

namespace App\Domain\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEvaluationCriterion extends Model
{
    use HasUuids;

    protected $table = 'practical_project_criteria';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'max_marks',
        'weight',
        'passing_threshold',
        'required_deliverables',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'max_marks' => 'decimal:2',
            'weight' => 'decimal:2',
            'passing_threshold' => 'decimal:2',
            'required_deliverables' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PracticalProject::class, 'project_id');
    }
}
