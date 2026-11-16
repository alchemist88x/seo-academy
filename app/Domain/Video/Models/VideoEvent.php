<?php

namespace App\Domain\Video\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_id',
        'lesson_key',
        'event_type',
        'position_seconds',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'position_seconds' => 'decimal:2',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
