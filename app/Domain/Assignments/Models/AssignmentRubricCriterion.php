<?php

namespace App\Domain\Assignments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentRubricCriterion extends Model
{
    use HasUuids;

    protected $table = 'assignment_rubric_criteria';

    protected $fillable = [
        'assignment_id',
        'title',
        'description',
        'max_marks',
        'display_order',
        'levels',
    ];

    protected function casts(): array
    {
        return [
            'max_marks' => 'decimal:2',
            'display_order' => 'integer',
            'levels' => 'array',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
