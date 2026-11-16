<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Concerns\ImmutableRecordTrait;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnrollmentFinalGrade extends Model
{
    use HasUuids, ImmutableRecordTrait;

    public $timestamps = false;

    protected $fillable = [
        'enrollment_id',
        'grading_scheme_id',
        'version',
        'final_score',
        'letter_grade',
        'is_passing',
        'academic_status',
        'calculation_snapshot',
        'published_at',
        'published_by',
        'supersedes_grade_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'final_score' => 'decimal:2',
            'is_passing' => 'boolean',
            'calculation_snapshot' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function supersededGrade(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_grade_id');
    }

    public function subsequentGrades(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_grade_id');
    }
}
