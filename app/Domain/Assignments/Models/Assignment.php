<?php

namespace App\Domain\Assignments\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Assignment extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const POLICY_NOT_ALLOWED = 'not_allowed';

    public const POLICY_ALLOWED_NO_PENALTY = 'allowed_no_penalty';

    public const POLICY_ALLOWED_PENALTY = 'allowed_penalty_deduction';

    public const POLICY_HARD_DEADLINE = 'allowed_until_hard_deadline';

    public const POLICY_DEDUCT_DAILY = 'deduct_percentage_daily';

    public const POLICY_DEDUCT_FLAT = 'deduct_flat_percentage';

    public const TYPE_FILE_ONLY = 'file_only';

    public const TYPE_TEXT_ONLY = 'text_only';

    public const TYPE_FILE_AND_TEXT = 'file_and_text';

    public const TYPE_URL_OR_EMBED = 'url_or_embed';

    protected $fillable = [
        'academy_id',
        'course_id',
        'module_id',
        'lesson_id',
        'title',
        'description',
        'instructions',
        'submission_guidelines',
        'assignment_type',
        'max_marks',
        'passing_marks',
        'due_date',
        'available_from',
        'available_to',
        'late_submission_policy',
        'late_penalty_percent',
        'late_hard_deadline',
        'max_attempts',
        'allow_resubmission',
        'allow_multiple_files',
        'max_file_count',
        'max_file_size_mb',
        'allowed_file_types',
        'rubric_mode',
        'is_mandatory',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'max_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'due_date' => 'datetime',
            'available_from' => 'datetime',
            'available_to' => 'datetime',
            'late_hard_deadline' => 'datetime',
            'late_penalty_percent' => 'decimal:2',
            'max_attempts' => 'integer',
            'allow_resubmission' => 'boolean',
            'allow_multiple_files' => 'boolean',
            'max_file_count' => 'integer',
            'max_file_size_mb' => 'integer',
            'allowed_file_types' => 'array',
            'is_mandatory' => 'boolean',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rubricCriteria(): HasMany
    {
        return $this->hasMany(AssignmentRubricCriterion::class)->orderBy('display_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, 'Active', 'active']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, 'Active', 'active']);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, 'Draft']);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ARCHIVED, 'Archived']);
    }

    public function isPublished(): bool
    {
        return in_array(strtolower((string) $this->status), [self::STATUS_PUBLISHED, 'active'], true);
    }

    public function isDraft(): bool
    {
        return in_array(strtolower((string) $this->status), [self::STATUS_DRAFT], true);
    }

    public function isArchived(): bool
    {
        return in_array(strtolower((string) $this->status), [self::STATUS_ARCHIVED], true);
    }

    public function isAvailable(?Carbon $at = null): bool
    {
        $now = $at ?? Carbon::now();

        if ($this->isArchived()) {
            return false;
        }

        if ($this->available_from !== null && $now->lt($this->available_from)) {
            return false;
        }

        if ($this->available_to !== null && $now->gt($this->available_to)) {
            return false;
        }

        return true;
    }

    public function isPastDue(?Carbon $at = null): bool
    {
        $now = $at ?? Carbon::now();

        if ($this->due_date === null) {
            return false;
        }

        return $now->gte($this->due_date);
    }

    public function isLateSubmissionAllowed(?Carbon $at = null): bool
    {
        $now = $at ?? Carbon::now();

        if (! $this->isPastDue($now)) {
            return true;
        }

        if ($this->late_submission_policy === self::POLICY_NOT_ALLOWED) {
            return false;
        }

        if ($this->late_hard_deadline !== null && $now->gte($this->late_hard_deadline)) {
            return false;
        }

        if ($this->available_to !== null && $now->gte($this->available_to)) {
            return false;
        }

        return true;
    }

    public function canStudentSubmit(User $student, ?Carbon $at = null): bool
    {
        $now = $at ?? Carbon::now();

        if (! $this->isPublished()) {
            return false;
        }

        if (! $this->isAvailable($now)) {
            return false;
        }

        if (! $this->isLateSubmissionAllowed($now)) {
            return false;
        }

        if ($this->max_attempts !== null && $this->max_attempts > 0) {
            $submissionCount = $this->submissions()
                ->where('user_id', $student->getKey())
                ->count();

            if ($submissionCount >= $this->max_attempts) {
                return false;
            }
        }

        // If resubmission is forbidden and student has submitted already
        if (! $this->allow_resubmission) {
            $hasSubmitted = $this->submissions()
                ->where('user_id', $student->getKey())
                ->exists();

            if ($hasSubmitted) {
                return false;
            }
        }

        return true;
    }

    protected static function booted(): void
    {
        static::deleting(function (Assignment $assignment) {
            if ($assignment->submissions()->exists()) {
                throw new \DomainException('Cannot delete an assignment with existing student submissions. Please archive the assignment instead.');
            }
        });
    }

    public function calculateLatePenalty(float $awardedPoints, ?Carbon $submittedAt = null): float
    {
        if ($this->due_date === null || (float) $this->late_penalty_percent <= 0.0 || $awardedPoints <= 0.0) {
            return 0.00;
        }

        if (in_array($this->late_submission_policy, [self::POLICY_NOT_ALLOWED, self::POLICY_ALLOWED_NO_PENALTY, 'prohibited'], true)) {
            return 0.00;
        }

        $submitted = $submittedAt ?? Carbon::now();

        // Non-negative elapsed lateness in seconds
        $elapsedSeconds = $submitted->getTimestamp() - $this->due_date->getTimestamp();
        if ($elapsedSeconds <= 0) {
            return 0.00;
        }

        $basePercent = (float) $this->late_penalty_percent;

        if ($this->late_submission_policy === self::POLICY_DEDUCT_DAILY || $this->late_submission_policy === 'deduct_percentage_daily') {
            // Calculate elapsed 24-hour periods after due date
            // Partial 24-hour period counts as a full day
            $daysLate = (int) ceil($elapsedSeconds / 86400.0);
            if ($daysLate < 1) {
                $daysLate = 1;
            }
            $effectivePercent = min(100.0, $daysLate * $basePercent);
        } else {
            // Flat percentage deduction
            $effectivePercent = min(100.0, $basePercent);
        }

        $penalty = $awardedPoints * ($effectivePercent / 100.0);

        return min($awardedPoints, round($penalty, 2));
    }

    /**
     * Format a timestamp in the academy's configured timezone.
     */
    public function formatInAcademyTimezone(?Carbon $date, string $format = 'M d, Y H:i'): string
    {
        if ($date === null) {
            return 'No deadline';
        }

        $tz = $this->academy?->timezone ?? config('app.timezone', 'UTC');

        return $date->copy()->setTimezone($tz)->format($format).' ('.$tz.')';
    }
}
