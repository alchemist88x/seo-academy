<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPage extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'cms_pages';

    protected $fillable = [
        'academy_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'template',
        'status',
        'published_at',
        'display_order',
        'seo_title',
        'seo_description',
        'og_image_path',
        'canonical_url',
        'is_indexable',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'display_order' => 'integer',
            'is_indexable' => 'boolean',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && ($this->published_at === null || $this->published_at->isPast());
    }
}
