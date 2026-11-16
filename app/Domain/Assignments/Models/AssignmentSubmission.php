<?php

namespace App\Domain\Assignments\Models;

use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssignmentSubmission extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    public const STATUS_RESUBMITTED = 'resubmitted';

    public const STATUS_GRADED = 'graded';

    protected $fillable = [
        'assignment_id',
        'enrollment_id',
        'user_id',
        'version_number',
        'previous_submission_id',
        'file_path',
        'content',
        'student_notes',
        'grade',
        'status',
        'is_late',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'grade' => 'decimal:2',
            'is_late' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->hasMany(AssignmentSubmissionAttachment::class, 'submission_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(AssignmentEvaluation::class, 'submission_id')->latest('graded_at');
    }

    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(AssignmentEvaluation::class, 'submission_id')->latestOfMany('graded_at');
    }

    public function scopeForStudent(Builder $query, User $student): Builder
    {
        return $query->where('user_id', $student->getKey());
    }

    public function isGraded(): bool
    {
        return $this->status === self::STATUS_GRADED;
    }

    public function isRevisionRequested(): bool
    {
        return $this->status === self::STATUS_REVISION_REQUESTED;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_RESUBMITTED], true);
    }
}
