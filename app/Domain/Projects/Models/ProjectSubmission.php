<?php

namespace App\Domain\Projects\Models;

use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectSubmission extends Model
{
    use HasUuids;

    protected $table = 'project_submissions';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    public const STATUS_RESUBMITTED = 'resubmitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'project_id',
        'student_profile_id',
        'user_id',
        'version_number',
        'previous_submission_id',
        'website_url',
        'content',
        'student_notes',
        'status',
        'final_score',
        'score_percentage',
        'is_passed',
        'is_late',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'final_score' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'is_passed' => 'boolean',
            'is_late' => 'boolean',
            'submitted_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function previousSubmission(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_submission_id');
    }

    public function nextSubmissions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_submission_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectSubmissionAttachment::class, 'submission_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ProjectEvaluation::class, 'submission_id')->latest('evaluated_at');
    }

    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(ProjectEvaluation::class, 'submission_id')->latestOfMany('evaluated_at');
    }

    public function achievement(): HasOne
    {
        return $this->hasOne(ProjectAchievement::class, 'submission_id');
    }

    public function portfolioItem(): HasOne
    {
        return $this->hasOne(StudentPortfolioItem::class, 'submission_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRevisionRequested(): bool
    {
        return $this->status === self::STATUS_REVISION_REQUESTED;
    }

    public function scopeForStudent(Builder $query, User $student): Builder
    {
        return $query->where('user_id', $student->getKey());
    }
}
