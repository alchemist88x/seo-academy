<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsBlogCategory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_blog_categories';

    protected $fillable = [
        'academy_id',
        'name',
        'slug',
        'description',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CmsBlogPost::class, 'category_id');
    }
}
