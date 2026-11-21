<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsBlogPost extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'cms_blog_posts';

    protected $fillable = [
        'academy_id',
        'category_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image_path',
        'status',
        'published_at',
        'reading_time_minutes',
        'seo_title',
        'seo_description',
        'og_image_path',
        'canonical_url',
        'is_indexable',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reading_time_minutes' => 'integer',
            'is_indexable' => 'boolean',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsBlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && ($this->published_at === null || $this->published_at->isPast());
    }
}
