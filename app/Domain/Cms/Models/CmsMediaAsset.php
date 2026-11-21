<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CmsMediaAsset extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_media_assets';

    protected $fillable = [
        'academy_id',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_size_bytes',
        'alt_text',
        'title',
        'uploader_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getFileSizeAttribute(): int
    {
        return (int) ($this->file_size_bytes ?? 0);
    }

    public function setFileSizeAttribute($val): void
    {
        $this->attributes['file_size_bytes'] = (int) $val;
    }

    public function getUrl(): string
    {
        return Storage::disk($this->disk ?? 'public')->url($this->file_path);
    }
}
