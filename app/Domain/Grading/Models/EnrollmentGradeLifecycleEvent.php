<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Concerns\ImmutableRecordTrait;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentGradeLifecycleEvent extends Model
{
    use HasUuids, ImmutableRecordTrait;

    public $timestamps = false;

    protected $fillable = [
        'enrollment_id',
        'event_type',
        'grade_snapshot_id',
        'actor_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function gradeSnapshot(): BelongsTo
    {
        return $this->belongsTo(EnrollmentFinalGrade::class, 'grade_snapshot_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
