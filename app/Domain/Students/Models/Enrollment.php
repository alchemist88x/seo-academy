<?php

namespace App\Domain\Students\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Models\EnrollmentCurrentGrade;
use App\Domain\Grading\Models\EnrollmentFinalGrade;
use App\Domain\Grading\Models\EnrollmentGradeDraft;
use App\Domain\Grading\Models\EnrollmentGradeLifecycleEvent;
use App\Domain\Grading\Models\GradeOverrideEvent;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_COMPLETED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_ACTIVE => [self::STATUS_SUSPENDED, self::STATUS_COMPLETED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_SUSPENDED => [self::STATUS_ACTIVE, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_COMPLETED => [self::STATUS_ACTIVE],
        self::STATUS_EXPIRED => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [self::STATUS_ACTIVE],
    ];

    protected $fillable = [
        'academy_id',
        'student_profile_id',
        'course_id',
        'assigned_by',
        'last_active_lesson_id',
        'last_active_at',
        'enrolled_at',
        'starts_at',
        'expires_at',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'suspended_at',
        'suspension_reason',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Enrollment $enrollment): void {
            if (empty($enrollment->academy_id)) {
                $enrollment->academy_id = $enrollment->course?->academy_id
                    ?? $enrollment->studentProfile?->academy_id
                    ?? Academy::current()?->id;
            }

            if (empty($enrollment->status)) {
                $enrollment->status = self::STATUS_PENDING;
            }
        });
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = $value !== null ? strtolower($value) : self::STATUS_PENDING;
    }

    protected static function newFactory(): EnrollmentFactory
    {
        return EnrollmentFactory::new();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'enrollment_id');
    }

    public function lastActiveLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_active_lesson_id');
    }

    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class, 'enrollment_id');
    }

    public function currentGrade(): HasOne
    {
        return $this->hasOne(EnrollmentCurrentGrade::class, 'enrollment_id');
    }

    public function gradeDraft(): HasOne
    {
        return $this->hasOne(EnrollmentGradeDraft::class, 'enrollment_id');
    }

    public function finalGrades(): HasMany
    {
        return $this->hasMany(EnrollmentFinalGrade::class, 'enrollment_id');
    }

    public function overrideEvents(): HasMany
    {
        return $this->hasMany(GradeOverrideEvent::class, 'enrollment_id');
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(EnrollmentGradeLifecycleEvent::class, 'enrollment_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'original_enrollment_id')->orderBy('version', 'desc');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'enrollment_id')->latestOfMany('issued_at');
    }

    public function activeCertificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'enrollment_id');
    }

    /**
     * Check stored lifecycle state.
     * Determines whether the enrollment is marked with an active status.
     * Note: This checks stored lifecycle state only, not temporal eligibility (effective window).
     */
    public function isActive(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_ACTIVE;
    }

    /**
     * Check temporal eligibility (effective window).
     * Determines whether the enrollment is in active status AND currently within its effective window:
     * - isActive() is true (status === 'active')
     * - starts_at is null or <= now()
     * - expires_at is null or >= now()
     */
    public function isValid(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if (strtolower((string) $this->status) === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_COMPLETED;
    }

    /**
     * Determine whether the enrollment permits course learning or review access.
     * Active enrollments within their effective window have full access.
     * Completed enrollments retain permanent read-only review access.
     * Suspended, cancelled, and un-completed expired enrollments are denied.
     */
    public function allowsCourseAccess(): bool
    {
        return $this->isValid() || $this->isCompleted();
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $current = strtolower((string) $this->status);
        $target = strtolower($newStatus);

        $allowed = self::TRANSITIONS[$current] ?? [];

        return in_array($target, $allowed, true);
    }

    /**
     * Scope query to enrollments matching the active lifecycle status.
     * Filters by stored lifecycle state (status = 'active') only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope query to enrollments that are active and temporally valid (within effective window).
     * Filters by stored lifecycle state (status = 'active') AND effective window:
     * - starts_at is null or <= now()
     * - expires_at is null or >= now()
     */
    public function scopeValid(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Scope query to enrollments that permit course access (valid active or completed).
     */
    public function scopeAllowsCourseAccess(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $q) use ($now): void {
            $q->where(function (Builder $activeQ) use ($now): void {
                $activeQ->where('status', self::STATUS_ACTIVE)
                    ->where(function (Builder $sub) use ($now): void {
                        $sub->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function (Builder $sub) use ($now): void {
                        $sub->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', $now);
                    });
            })->orWhere('status', self::STATUS_COMPLETED);
        });
    }
}
