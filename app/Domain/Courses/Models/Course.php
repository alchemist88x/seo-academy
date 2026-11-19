<?php

namespace App\Domain\Courses\Models;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids, SoftDeletes;

    public const STATUSES = ['Draft', 'Published', 'Unpublished', 'Archived'];

    public const DIFFICULTIES = ['Beginner', 'Intermediate', 'Advanced'];

    public const VISIBILITIES = ['public', 'academy_only', 'private'];

    protected $fillable = [
        'academy_id',
        'category_id',
        'active_grading_scheme_id',
        'title',
        'slug',
        'description',
        'difficulty',
        'price',
        'currency',
        'is_free',
        'visibility',
        'enrollment_limit',
        'thumbnail_path',
        'duration_months',
        'is_sequential',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'is_sequential' => 'boolean',
            'price' => 'decimal:2',
            'is_free' => 'boolean',
            'enrollment_limit' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function activeGradingScheme(): BelongsTo
    {
        return $this->belongsTo(CourseGradingScheme::class, 'active_grading_scheme_id');
    }

    public function gradingSchemes(): HasMany
    {
        return $this->hasMany(CourseGradingScheme::class, 'course_id');
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_trainers', 'course_id', 'user_id')
            ->using(CourseTrainer::class)
            ->withPivot(['id', 'academy_id', 'is_primary', 'created_at']);
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_course_id')
            ->using(CoursePrerequisite::class)
            ->withPivot(['id', 'academy_id', 'created_at']);
    }

    public function requiredFor(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'prerequisite_course_id', 'course_id')
            ->using(CoursePrerequisite::class)
            ->withPivot(['id', 'academy_id', 'created_at']);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(CourseLevel::class)->orderBy('display_order');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('display_order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function thumbnailUrl(): ?string
    {
        if (empty($this->thumbnail_path)) {
            return null;
        }

        if (str_starts_with($this->thumbnail_path, 'http://') || str_starts_with($this->thumbnail_path, 'https://')) {
            return $this->thumbnail_path;
        }

        return Storage::disk('public')->url($this->thumbnail_path);
    }

    public function isPublished(): bool
    {
        return in_array($this->status, ['Published', 'Active'], true);
    }

    public function isArchived(): bool
    {
        return $this->status === 'Archived';
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'Archived');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['Published', 'Active']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['Active', 'Published']);
    }

    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->published()->whereIn('visibility', ['public', 'academy_only']);
    }

    public function scopeAssignedToTrainer(Builder $query, User $trainer): Builder
    {
        return $query->whereHas('trainers', function (Builder $q) use ($trainer): void {
            $q->where('users.id', $trainer->getKey());
        });
    }
}
