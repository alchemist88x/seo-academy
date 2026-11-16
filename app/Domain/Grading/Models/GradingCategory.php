<?php

namespace App\Domain\Grading\Models;

use App\Domain\Courses\Models\Course;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'course_id',
        'grading_scheme_id',
        'name',
        'weight',
        'drop_lowest_count',
        'is_mandatory',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'drop_lowest_count' => 'integer',
            'is_mandatory' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CourseGradingScheme::class, 'grading_scheme_id');
    }

    public function assessmentWeights(): HasMany
    {
        return $this->hasMany(AssessmentWeight::class, 'grading_category_id');
    }
}
