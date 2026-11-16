<?php

namespace App\Domain\Grading\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentWeight extends Model
{
    use HasUuids;

    protected $fillable = [
        'grading_scheme_id',
        'grading_category_id',
        'assessment_type',
        'assessment_id',
        'custom_weight',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'custom_weight' => 'decimal:2',
            'is_mandatory' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CourseGradingScheme::class, 'grading_scheme_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GradingCategory::class, 'grading_category_id');
    }
}
