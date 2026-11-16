<?php

namespace App\Domain\Grading\Models;

use App\Domain\Courses\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseGradingScheme extends Model
{
    use HasUuids;

    protected $fillable = [
        'course_id',
        'name',
        'version',
        'passing_grade',
        'calculation_method',
        'status',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'passing_grade' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(GradingCategory::class, 'grading_scheme_id')->orderBy('display_order');
    }

    public function assessmentWeights(): HasMany
    {
        return $this->hasMany(AssessmentWeight::class, 'grading_scheme_id');
    }
}
