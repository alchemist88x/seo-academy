<?php

namespace App\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Models\AiMessage;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Rewards\Models\RewardEvent;
use App\Domain\Rewards\Models\RewardStreak;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Domain\Video\Models\VideoProgress;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'phone',
        'suspended_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    public function getFirstNameAttribute(): string
    {
        $parts = explode(' ', (string) $this->name, 2);

        return $parts[0] ?? '';
    }

    public function getLastNameAttribute(): string
    {
        $parts = explode(' ', (string) $this->name, 2);

        return $parts[1] ?? '';
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if (AssignmentSubmission::where('user_id', $user->getKey())->exists()) {
                throw new \DomainException('Cannot delete a user with recorded assignment submissions. Deactivate the user instead.');
            }
            if (AssignmentEvaluation::where('evaluator_id', $user->getKey())->exists()) {
                throw new \DomainException('Cannot delete an evaluator with recorded grading history. Deactivate the account instead.');
            }
        });
    }

    public function academies(): BelongsToMany
    {
        return $this->belongsToMany(Academy::class, 'academy_users')
            ->withPivot(['id', 'role', 'status', 'suspended_at', 'created_at', 'updated_at'])
            ->wherePivot('status', 'active');
    }

    public function belongsToAcademy(Academy|string $academy): bool
    {
        $currentAcademy = Academy::current();

        if ($currentAcademy === null || ! $this->is_active) {
            return false;
        }

        $academyId = $academy instanceof Academy ? $academy->getKey() : $academy;

        return $academyId === $currentAcademy->getKey();
    }

    public function academyRole(Academy|string $academy): ?string
    {
        if (! $this->belongsToAcademy($academy)) {
            return null;
        }

        foreach (['admin', 'trainer', 'support_staff', 'student'] as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    public function hasAcademyRole(Academy|string $academy, string|array $roles): bool
    {
        if (! $this->belongsToAcademy($academy)) {
            return false;
        }

        return $this->hasAnyRole((array) $roles);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function trainerProfiles(): HasMany
    {
        return $this->hasMany(TrainerProfile::class);
    }

    public function videoProgress(): HasMany
    {
        return $this->hasMany(VideoProgress::class);
    }

    public function rewardEvents(): HasMany
    {
        return $this->hasMany(RewardEvent::class);
    }

    public function rewardStreak(): HasOne
    {
        return $this->hasOne(RewardStreak::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function aiMessages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
