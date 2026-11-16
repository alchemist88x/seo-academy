<?php

namespace App\Domain\Video\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VideoProgress extends Model
{
    protected $table = 'video_progress';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'lesson_key',
        'position_seconds',
        'duration_seconds',
        'view_seconds',
        'completed',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'position_seconds' => 'decimal:2',
            'duration_seconds' => 'decimal:2',
            'view_seconds' => 'decimal:2',
            'completed' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
