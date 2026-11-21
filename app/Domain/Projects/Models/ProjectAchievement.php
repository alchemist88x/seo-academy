<?php

namespace App\Domain\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAchievement extends Model
{
    use HasUuids;

    protected $table = 'project_achievements';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'project_id',
        'submission_id',
        'user_id',
        'achievement_number',
        'verification_code',
        'title',
        'category',
        'score_percentage',
        'skills_awarded',
        'issued_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'score_percentage' => 'decimal:2',
            'skills_awarded' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PracticalProject::class, 'project_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProjectSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
