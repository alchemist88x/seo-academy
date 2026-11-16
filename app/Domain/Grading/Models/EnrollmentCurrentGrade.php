<?php

namespace App\Domain\Grading\Models;

use App\Domain\Students\Models\Enrollment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentCurrentGrade extends Model
{
    use HasUuids;

    protected $fillable = [
        'enrollment_id',
        'published_final_grade_id',
        'working_draft_grade_id',
        'status',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function publishedGrade(): BelongsTo
    {
        return $this->belongsTo(EnrollmentFinalGrade::class, 'published_final_grade_id');
    }

    public function draftGrade(): BelongsTo
    {
        return $this->belongsTo(EnrollmentGradeDraft::class, 'working_draft_grade_id');
    }
}
