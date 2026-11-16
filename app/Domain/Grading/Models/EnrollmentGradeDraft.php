<?php

namespace App\Domain\Grading\Models;

use App\Domain\Students\Models\Enrollment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentGradeDraft extends Model
{
    use HasUuids;

    protected $fillable = [
        'enrollment_id',
        'grading_scheme_id',
        'current_score',
        'current_letter_grade',
        'calculation_breakdown',
        'is_passing',
        'academic_status',
        'missing_mandatory_assessments',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'current_score' => 'decimal:2',
            'calculation_breakdown' => 'array',
            'is_passing' => 'boolean',
            'missing_mandatory_assessments' => 'boolean',
            'last_calculated_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CourseGradingScheme::class, 'grading_scheme_id');
    }
}
