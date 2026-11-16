<?php

namespace App\Domain\LiveClasses\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassVideo extends Model
{
    use HasUuids;

    protected $fillable = [
        'live_class_id',
        'title',
        'description',
        'video_path',
        'thumbnail_path',
        'duration_seconds',
        'display_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }
}
