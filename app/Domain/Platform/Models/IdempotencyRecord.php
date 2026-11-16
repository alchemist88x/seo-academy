<?php

namespace App\Domain\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'actor_id',
        'action',
        'idempotency_key',
        'resource_type',
        'resource_id',
        'request_hash',
        'ownership_token',
        'status',
        'response_code',
        'response_body',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'response_body' => 'array',
            'reserved_until' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
