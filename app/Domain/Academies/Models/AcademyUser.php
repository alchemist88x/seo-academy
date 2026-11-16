<?php

namespace App\Domain\Academies\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyUser extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'academy_id',
        'user_id',
        'role',
        'status',
        'suspended_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
