<?php

namespace App\Domain\Trainers\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainerProfile extends Model
{
    use BelongsToAcademy, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'academy_id',
        'specialty',
        'bio',
        'active',
        'pre_deletion_state',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'pre_deletion_state' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
