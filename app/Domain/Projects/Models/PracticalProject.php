<?php

namespace App\Domain\Projects\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PracticalProject extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $table = 'practical_projects';

    public const CATEGORY_TECHNICAL_SEO = 'technical_seo';

    public const CATEGORY_ON_PAGE_SEO = 'on_page_seo';

    public const CATEGORY_KEYWORD_RESEARCH = 'keyword_research';

    public const CATEGORY_CONTENT_OPTIMIZATION = 'content_optimization';

    public const CATEGORY_LINK_BUILDING = 'link_building';

    public const CATEGORY_LOCAL_SEO = 'local_seo';

    public const CATEGORY_SEO_AUDITING = 'seo_auditing';

    public const CATEGORY_ANALYTICS_REPORTING = 'analytics_and_reporting';

    public const CATEGORY_CORE_WEB_VITALS = 'core_web_vitals';

    public const CATEGORIES = [
        self::CATEGORY_TECHNICAL_SEO => 'Technical SEO',
        self::CATEGORY_ON_PAGE_SEO => 'On-page SEO',
        self::CATEGORY_KEYWORD_RESEARCH => 'Keyword Research',
        self::CATEGORY_CONTENT_OPTIMIZATION => 'Content Optimization',
        self::CATEGORY_LINK_BUILDING => 'Link Building',
        self::CATEGORY_LOCAL_SEO => 'Local SEO',
        self::CATEGORY_SEO_AUDITING => 'SEO Auditing',
        self::CATEGORY_ANALYTICS_REPORTING => 'Analytics & Reporting',
        self::CATEGORY_CORE_WEB_VITALS => 'Core Web Vitals',
    ];

    public const DIFFICULTY_BEGINNER = 'beginner';

    public const DIFFICULTY_INTERMEDIATE = 'intermediate';

    public const DIFFICULTY_EXPERT = 'expert';

    public const DIFFICULTIES = [
        self::DIFFICULTY_BEGINNER => 'Beginner',
        self::DIFFICULTY_INTERMEDIATE => 'Intermediate',
        self::DIFFICULTY_EXPERT => 'Expert',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'academy_id',
        'course_id',
        'title',
        'slug',
        'category',
        'difficulty',
        'description',
        'objectives',
        'expected_deliverables',
        'instructions',
        'start_date',
        'due_date',
        'max_marks',
        'passing_marks',
        'max_revisions',
        'reference_materials',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'expected_deliverables' => 'array',
            'reference_materials' => 'array',
            'start_date' => 'datetime',
            'due_date' => 'datetime',
            'max_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'max_revisions' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PracticalProject $project) {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->title).'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ProjectEvaluationCriterion::class, 'project_id')->orderBy('display_order');
    }

    public function assignedStudents(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'practical_project_students', 'project_id', 'student_profile_id')
            ->withPivot(['id', 'assigned_at', 'due_date_override', 'status'])
            ->withTimestamps();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class, 'project_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(ProjectAchievement::class, 'project_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isAvailable(?Carbon $at = null): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        $now = $at ?? Carbon::now();

        if ($this->start_date !== null && $now->lt($this->start_date)) {
            return false;
        }

        return true;
    }

    public function isPastDue(?Carbon $at = null): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        $now = $at ?? Carbon::now();

        return $now->gt($this->due_date);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function difficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? ucfirst($this->difficulty);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeDifficulty(Builder $query, string $difficulty): Builder
    {
        return $query->where('difficulty', $difficulty);
    }
}
