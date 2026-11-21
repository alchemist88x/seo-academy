<?php

namespace App\Domain\Students\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Database\Factories\ProficiencyLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProficiencyLevel extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids;

    protected $fillable = [
        'academy_id',
        'name',
        'slug',
        'description',
        'display_order',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProficiencyLevelFactory
    {
        return ProficiencyLevelFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = Str::slug($model->name, '_');
            }
        });
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'proficiency_level_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function getAssignedStudentsCountAttribute(): int
    {
        return $this->studentProfiles()->count();
    }

    public function canBeDeleted(): bool
    {
        return $this->studentProfiles()->count() === 0;
    }
}
