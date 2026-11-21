<?php

namespace App\Domain\Projects\Models;

use App\Domain\Students\Models\StudentProfile;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalProjectStudent extends Model
{
    use HasUuids;

    protected $table = 'practical_project_students';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'project_id',
        'student_profile_id',
        'assigned_at',
        'due_date_override',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'due_date_override' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PracticalProject::class, 'project_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }
}
