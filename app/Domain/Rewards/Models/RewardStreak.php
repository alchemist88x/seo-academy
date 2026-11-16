<?php

namespace App\Domain\Rewards\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardStreak extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'current_streak',
        'best_streak',
        'last_active_date',
    ];

    protected function casts(): array
    {
        return [
            'current_streak' => 'integer',
            'best_streak' => 'integer',
            'last_active_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
