<?php

namespace App\Domain\Quizzes\Models;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $table = 'quizzes';

    public const TYPE_QUIZ = 'quiz';

    public const TYPE_EXAM = 'exam';

    public const TYPE_ASSIGNMENT = 'assignment';

    public const TYPES = [
        self::TYPE_QUIZ,
        self::TYPE_EXAM,
        self::TYPE_ASSIGNMENT,
    ];

    protected $fillable = [
        'academy_id',
        'course_id',
        'module_id',
        'lesson_id',
        'title',
        'description',
        'type',
        'is_required',
        'attempt_limit',
        'time_limit_minutes',
        'pass_mark',
        'passing_percentage',
        'status',
        'published_at',
        'randomize_questions',
        'randomize_order',
        'question_source',
        'exam_start_at',
        'exam_end_at',
        'late_entry_deadline_at',
        'grace_period_seconds',
        'timezone',
        'is_scheduled_exam',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'attempt_limit' => 'integer',
            'time_limit_minutes' => 'integer',
            'pass_mark' => 'integer',
            'passing_percentage' => 'integer',
            'published_at' => 'datetime',
            'randomize_questions' => 'boolean',
            'randomize_order' => 'boolean',
            'exam_start_at' => 'datetime',
            'exam_end_at' => 'datetime',
            'late_entry_deadline_at' => 'datetime',
            'grace_period_seconds' => 'integer',
            'is_scheduled_exam' => 'boolean',
        ];
    }

    public function getAcademyIdAttribute(): ?string
    {
        return $this->attributes['academy_id'] ?? $this->course?->academy_id ?? $this->module?->course?->academy_id;
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

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('display_order');
    }

    public function pools(): HasMany
    {
        return $this->hasMany(QuizQuestionPool::class, 'quiz_id')->orderBy('display_order');
    }

    public function hasQuestionPools(): bool
    {
        return $this->pools()->exists();
    }

    /**
     * Validate that all configured pools have sufficient eligible questions.
     *
     * @return array<int, array{pool_id: string, bank_name: string, requested: int, available: int}>
     */
    public function validatePoolSufficiency(): array
    {
        $errors = [];

        foreach ($this->pools()->with('questionBank')->get() as $pool) {
            $available = $pool->getAvailableCount();
            if ($available < (int) $pool->question_count) {
                $errors[] = [
                    'pool_id' => $pool->id,
                    'bank_name' => $pool->questionBank?->name ?? 'Unknown Bank',
                    'requested' => (int) $pool->question_count,
                    'available' => $available,
                ];
            }
        }

        return $errors;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(QuizSubmission::class, 'quiz_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class, 'assessment_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function isPublished(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }

    public function isRequired(): bool
    {
        return (bool) $this->is_required;
    }

    public function getPassingScore(): int
    {
        return $this->passing_percentage ?? $this->pass_mark ?? 70;
    }

    public function getTotalPointsAttribute(): float
    {
        return (float) $this->questions()->sum('points');
    }

    public function getRemainingAttempts(Enrollment $enrollment): ?int
    {
        if ($this->attempt_limit === null || $this->attempt_limit <= 0) {
            return null; // Unlimited
        }

        $usedAttempts = $this->attempts()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', [AssessmentAttempt::STATUS_SUBMITTED, AssessmentAttempt::STATUS_EVALUATED])
            ->count();

        return max(0, $this->attempt_limit - $usedAttempts);
    }

    public function hasAttemptsLeft(Enrollment $enrollment): bool
    {
        $remaining = $this->getRemainingAttempts($enrollment);

        return $remaining === null || $remaining > 0;
    }
}
