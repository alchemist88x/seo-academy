<?php

namespace App\Domain\Students\Models;

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

class StudentProfile extends Model
{
    use BelongsToAcademy, HasFactory, HasUuids;

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
        'student_code',
        'batch',
        'enrolled_at',
        'level',
        'proficiency',
        'active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): StudentProfileFactory
    {
        return StudentProfileFactory::new();
    }

    public static function normalizeProficiency(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return match (true) {
            str_contains($normalized, 'inter') => self::PROFICIENCY_INTERMEDIATE,
            str_contains($normalized, 'exp') || str_contains($normalized, 'adv') => self::PROFICIENCY_EXPERT,
            default => self::PROFICIENCY_BEGINNER,
        };
    }

    /**
     * Keep level and proficiency synchronized for backwards compatibility.
     */
    protected function proficiency(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?: self::normalizeProficiency($this->attributes['level'] ?? null),
            set: function (?string $value) {
                $normalized = self::normalizeProficiency($value);
                $this->attributes['level'] = ucfirst($normalized);

                return $normalized;
            },
        );
    }

    public function getProficiencyLabelAttribute(): string
    {
        return match ($this->proficiency) {
            self::PROFICIENCY_EXPERT => 'Expert',
            self::PROFICIENCY_INTERMEDIATE => 'Intermediate',
            default => 'Beginner',
        };
    }

    public function getProficiencyBadgeColorAttribute(): string
    {
        return match ($this->proficiency) {
            self::PROFICIENCY_EXPERT => 'purple',
            self::PROFICIENCY_INTERMEDIATE => 'amber',
            default => 'blue',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForProficiency(Builder $query, string $proficiency): Builder
    {
        return $query->where(function (Builder $q) use ($proficiency) {
            $q->where('proficiency', $proficiency)
                ->orWhere('level', ucfirst($proficiency))
                ->orWhere('level', strtolower($proficiency));
        });
    }
}
