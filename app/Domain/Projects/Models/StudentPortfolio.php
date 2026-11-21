<?php

namespace App\Domain\Projects\Models;

use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class StudentPortfolio extends Model
{
    use HasUuids;

    protected $table = 'student_portfolios';

    protected $fillable = [
        'student_profile_id',
        'user_id',
        'title',
        'headline',
        'bio',
        'custom_skills',
        'is_public',
        'public_token',
        'public_token_generated_at',
        'public_identity_consent',
        'consent_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_skills' => 'array',
            'is_public' => 'boolean',
            'public_identity_consent' => 'boolean',
            'public_token_generated_at' => 'datetime',
            'consent_recorded_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentPortfolioItem::class, 'portfolio_id')->orderBy('display_order');
    }

    public function visibleItems(): HasMany
    {
        return $this->hasMany(StudentPortfolioItem::class, 'portfolio_id')
            ->where('is_visible', true)
            ->orderBy('display_order');
    }

    public function generatePublicToken(): string
    {
        $token = bin2hex(random_bytes(32)); // 64-char cryptographically secure token
        $this->update([
            'public_token' => $token,
            'public_token_generated_at' => Carbon::now(),
        ]);

        return $token;
    }

    public function revokePublicToken(): void
    {
        $this->update([
            'is_public' => false,
            'public_token' => null,
            'public_token_generated_at' => null,
            'public_identity_consent' => false,
            'consent_recorded_at' => null,
        ]);
    }
}
