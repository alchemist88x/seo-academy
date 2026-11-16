<?php

namespace App\Domain\LiveClasses\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMaterial extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'live_class_id',
        'title',
        'description',
        'file_path',
        'display_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }
}
