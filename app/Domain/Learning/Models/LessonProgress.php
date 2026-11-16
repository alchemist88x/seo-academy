<?php

namespace App\Domain\Learning\Models;

use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $table = 'lesson_progress';

    public const STATUSES = ['not_started', 'in_progress', 'completed'];

    protected $fillable = [
        'academy_id',
        'enrollment_id',
        'user_id',
        'lesson_id',
        'status',
        'last_position_seconds',
        'watch_seconds',
        'progress_percent',
        'completed_at',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_position_seconds' => 'decimal:2',
            'watch_seconds' => 'decimal:2',
            'progress_percent' => 'integer',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function scopeForEnrollment(Builder $query, Enrollment|string $enrollment): Builder
    {
        $id = $enrollment instanceof Enrollment ? $enrollment->id : $enrollment;

        return $query->where('enrollment_id', $id);
    }
}
