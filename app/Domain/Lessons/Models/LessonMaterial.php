<?php

namespace App\Domain\Lessons\Models;

use App\Domain\Platform\Models\Media;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMaterial extends Model
{
    use BelongsToAcademy, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'academy_id',
        'lesson_id',
        'media_id',
        'title',
        'description',
        'file_path',
        'file_size_bytes',
        'mime_type',
        'provider',
        'display_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'display_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
