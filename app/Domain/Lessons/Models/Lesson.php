<?php

namespace App\Domain\Lessons\Models;

use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Platform\Models\Media;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids;

    public const TYPES = ['video', 'text', 'pdf', 'external', 'live_class', 'assignment'];

    public const VISIBILITIES = ['enrolled', 'preview', 'hidden'];

    public const PROVIDERS = ['private_media', 'external_url', 'peertube', 'jitsi', 'bigbluebutton', 'internal'];

    protected $fillable = [
        'academy_id',
        'module_id',
        'title',
        'type',
        'description',
        'content',
        'resource_provider',
        'resource_reference',
        'media_id',
        'display_order',
        'video_path',
        'thumbnail_path',
        'duration_seconds',
        'status',
        'visibility',
        'is_free_preview',
        'completion_threshold_percent',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'duration_seconds' => 'integer',
            'is_free_preview' => 'boolean',
            'completion_threshold_percent' => 'integer',
        ];
    }

    protected static function newFactory(): LessonFactory
    {
        return LessonFactory::new();
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class)->orderBy('display_order');
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_prerequisites', 'lesson_id', 'prerequisite_lesson_id')
            ->using(LessonPrerequisite::class)
            ->withPivot(['id', 'academy_id', 'created_at']);
    }

    public function requiredFor(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_prerequisites', 'prerequisite_lesson_id', 'lesson_id')
            ->using(LessonPrerequisite::class)
            ->withPivot(['id', 'academy_id', 'created_at']);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function isFreePreview(): bool
    {
        return ($this->is_free_preview || $this->visibility === 'preview')
            && $this->visibility !== 'hidden';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }
}
