<?php

namespace App\Domain\Courses\Models;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\LiveClasses\Models\LiveClass;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Domain\Video\Models\VideoProgress;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids;

    public const COMPLETION_RULES = ['all_lessons', 'required_count', 'none'];

    protected $fillable = [
        'academy_id',
        'course_id',
        'title',
        'description',
        'display_order',
        'status',
        'completion_rule',
        'required_lessons_count',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'required_lessons_count' => 'integer',
        ];
    }

    protected static function newFactory(): ModuleFactory
    {
        return ModuleFactory::new();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('display_order');
    }

    public function liveClasses(): HasMany
    {
        return $this->hasMany(LiveClass::class)->orderBy('display_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function hasLearnerProgress(): bool
    {
        $lessonIds = $this->lessons()
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($lessonIds === []) {
            return false;
        }

        if (LessonProgress::query()->whereIn('lesson_id', $lessonIds)->exists()) {
            return true;
        }

        $videoKeys = [];
        foreach ($lessonIds as $lessonId) {
            $videoKeys[] = $lessonId;
            $videoKeys[] = "lesson:{$lessonId}";
        }

        return VideoProgress::query()
            ->whereIn('lesson_key', $videoKeys)
            ->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }
}
