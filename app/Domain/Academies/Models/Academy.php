<?php

namespace App\Domain\Academies\Models;

use App\Domain\AI\Models\AiFaq;
use App\Domain\AI\Models\AiMaterial;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Platform\Models\Setting;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;
use Database\Factories\AcademyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Academy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'custom_domain',
        'description',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'support_email',
        'contact_phone',
        'website_url',
        'address',
        'timezone',
        'locale',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (static::query()->exists()) {
                throw new \DomainException('A single-academy installation cannot have more than one academy.');
            }
        });
    }

    public static function current(): ?self
    {
        try {
            if (! Schema::hasTable('academies')) {
                return null;
            }

            return static::query()
                ->where('is_active', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first()
                ?? static::query()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function newFactory(): AcademyFactory
    {
        return AcademyFactory::new();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'academy_users')
            ->withPivot(['id', 'role', 'created_at']);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function academyNotifications(): HasMany
    {
        return $this->hasMany(AcademyNotification::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function aiFaqs(): HasMany
    {
        return $this->hasMany(AiFaq::class);
    }

    public function aiMaterials(): HasMany
    {
        return $this->hasMany(AiMaterial::class);
    }

    public function settingsRecords(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $jsonSettings = $this->settings ?? [];

        if (Arr::has($jsonSettings, $key)) {
            return Arr::get($jsonSettings, $key);
        }

        $record = $this->relationLoaded('settingsRecords')
            ? $this->settingsRecords->firstWhere('key', $key)
            : $this->settingsRecords()->where('key', $key)->first();

        if ($record === null) {
            return $default;
        }

        return match ($record->type) {
            'boolean', 'bool' => filter_var($record->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'integer', 'int' => $record->value !== null ? (int) $record->value : $default,
            'float', 'double' => $record->value !== null ? (float) $record->value : $default,
            'json', 'array' => $record->value !== null ? json_decode($record->value, true) : $default,
            default => $record->value ?? $default,
        };
    }

    public function logoUrl(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        if (empty($this->favicon_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->favicon_path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
