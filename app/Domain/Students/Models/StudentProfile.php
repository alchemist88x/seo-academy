<?php

namespace App\Domain\Students\Models;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentProfile extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids, SoftDeletes;

    public const PROFICIENCY_BEGINNER = 'beginner';

    public const PROFICIENCY_INTERMEDIATE = 'intermediate';

    public const PROFICIENCY_EXPERT = 'expert';

    public const PROFICIENCIES = [
        self::PROFICIENCY_BEGINNER,
        self::PROFICIENCY_INTERMEDIATE,
        self::PROFICIENCY_EXPERT,
    ];

    protected $fillable = [
        'user_id',
        'academy_id',
        'proficiency_level_id',
        'student_code',
        'batch',
        'enrolled_at',
        'level',
        'proficiency',
        'active',
        'notes',
        'pre_deletion_state',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'active' => 'boolean',
            'pre_deletion_state' => 'array',
        ];
    }

    protected static function newFactory(): StudentProfileFactory
    {
        return StudentProfileFactory::new();
    }

    public function proficiencyLevel(): BelongsTo
    {
        return $this->belongsTo(ProficiencyLevel::class, 'proficiency_level_id');
    }

    public static function normalizeProficiency(?string $value, ?string $academyId = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = strtolower($trimmed);

        if (in_array($normalized, ['not assessed', 'not_assessed', 'unassessed', 'none', 'null', '—', '-'], true)) {
            return null;
        }

        if (in_array($normalized, self::PROFICIENCIES, true)) {
            return $normalized;
        }

        // Check if matches an existing ProficiencyLevel slug or ID or name
        $query = ProficiencyLevel::query();
        if ($academyId) {
            $query->where('academy_id', $academyId);
        }
        $match = $query->where(function ($q) use ($normalized, $trimmed) {
            $q->where('slug', $normalized)
                ->orWhere('id', $trimmed)
                ->orWhereRaw('LOWER(name) = ?', [$normalized]);
        })->first();

        if ($match) {
            return $match->slug;
        }

        return match (true) {
            str_contains($normalized, 'inter') => self::PROFICIENCY_INTERMEDIATE,
            str_contains($normalized, 'exp') || str_contains($normalized, 'adv') => self::PROFICIENCY_EXPERT,
            str_contains($normalized, 'beg') => self::PROFICIENCY_BEGINNER,
            default => Str::slug($normalized, '_'),
        };
    }

    /**
     * Keep level and proficiency synchronized for backwards compatibility.
     */
    protected function proficiency(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value !== null && $value !== '') {
                    return $value;
                }

                if ($this->relationLoaded('proficiencyLevel') && $this->proficiencyLevel) {
                    return $this->proficiencyLevel->slug;
                }

                $level = $this->attributes['level'] ?? null;

                return $level ? self::normalizeProficiency($level, $this->academy_id) : null;
            },
            set: function (?string $value) {
                $normalized = self::normalizeProficiency($value, $this->academy_id);

                if ($normalized === null) {
                    $this->attributes['proficiency_level_id'] = null;
                    $this->attributes['level'] = null;

                    return null;
                }

                // If a matching ProficiencyLevel exists, link it
                $levelQuery = ProficiencyLevel::where('slug', $normalized);
                if ($this->academy_id) {
                    $levelQuery->where('academy_id', $this->academy_id);
                }
                $levelModel = $levelQuery->first();

                if ($levelModel) {
                    $this->attributes['proficiency_level_id'] = $levelModel->id;
                    $this->attributes['level'] = $levelModel->name;
                } else {
                    $this->attributes['level'] = ucfirst(str_replace('_', ' ', $normalized));
                }

                return $normalized;
            },
        );
    }

    /**
     * Keep level synchronized with proficiency.
     */
    protected function level(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?: ($this->proficiencyLevel?->name ?? ($this->attributes['proficiency'] ? ucfirst(str_replace('_', ' ', $this->attributes['proficiency'])) : null)),
            set: function (?string $value) {
                $normalized = self::normalizeProficiency($value, $this->academy_id);
                $this->attributes['proficiency'] = $normalized;

                if ($normalized === null) {
                    $this->attributes['proficiency_level_id'] = null;

                    return null;
                }

                $levelQuery = ProficiencyLevel::where('slug', $normalized);
                if ($this->academy_id) {
                    $levelQuery->where('academy_id', $this->academy_id);
                }
                $levelModel = $levelQuery->first();

                if ($levelModel) {
                    $this->attributes['proficiency_level_id'] = $levelModel->id;

                    return $levelModel->name;
                }

                return ucfirst(str_replace('_', ' ', $normalized));
            },
        );
    }

    public function getProficiencyLabelAttribute(): string
    {
        if ($this->relationLoaded('proficiencyLevel') && $this->proficiencyLevel) {
            return $this->proficiencyLevel->name;
        }

        if ($this->proficiency_level_id) {
            $lvl = $this->proficiencyLevel;
            if ($lvl) {
                return $lvl->name;
            }
        }

        if (! empty($this->attributes['level']) && ! in_array(strtolower($this->attributes['level']), ['not assessed', 'null'], true)) {
            return $this->attributes['level'];
        }

        if (! empty($this->proficiency)) {
            return match ($this->proficiency) {
                self::PROFICIENCY_EXPERT => 'Expert',
                self::PROFICIENCY_INTERMEDIATE => 'Intermediate',
                self::PROFICIENCY_BEGINNER => 'Beginner',
                default => ucwords(str_replace(['_', '-'], ' ', $this->proficiency)),
            };
        }

        return 'Not Assessed';
    }

    public function getProficiencyBadgeColorAttribute(): string
    {
        if ($this->relationLoaded('proficiencyLevel') && $this->proficiencyLevel && ! empty($this->proficiencyLevel->color)) {
            return $this->proficiencyLevel->color;
        }

        if ($this->proficiency_level_id) {
            $lvl = $this->proficiencyLevel;
            if ($lvl && ! empty($lvl->color)) {
                return $lvl->color;
            }
        }

        return match ($this->proficiency) {
            self::PROFICIENCY_EXPERT => 'purple',
            self::PROFICIENCY_INTERMEDIATE => 'amber',
            self::PROFICIENCY_BEGINNER => 'blue',
            null, '' => 'gray',
            default => 'blue',
        };
    }

    public function isAssessed(): bool
    {
        return $this->proficiency !== null;
    }

    public function isUnassessed(): bool
    {
        return $this->proficiency === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'user_id', 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForProficiency(Builder $query, ?string $proficiency): Builder
    {
        if ($proficiency === null || in_array($proficiency, ['not_assessed', 'unassessed', 'none', 'null'], true)) {
            return $query->where(function (Builder $q): void {
                $q->whereNull('proficiency')
                    ->orWhere('proficiency', '')
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNull('level')
                            ->orWhere('level', '')
                            ->orWhere('level', 'Not Assessed');
                    });
            });
        }

        return $query->where(function (Builder $q) use ($proficiency): void {
            $q->where('proficiency', $proficiency)
                ->orWhere('level', ucfirst($proficiency))
                ->orWhere('level', strtolower($proficiency))
                ->orWhere('proficiency_level_id', $proficiency)
                ->orWhereHas('proficiencyLevel', function (Builder $lvlQ) use ($proficiency): void {
                    $lvlQ->where('slug', $proficiency)
                        ->orWhere('id', $proficiency)
                        ->orWhere('name', $proficiency);
                });
        });
    }

    public function scopeUnassessed(Builder $query): Builder
    {
        return $this->scopeForProficiency($query, null);
    }
}
