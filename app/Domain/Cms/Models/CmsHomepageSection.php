<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsHomepageSection extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_homepage_sections';

    protected $fillable = [
        'academy_id',
        'section_key',
        'title',
        'subtitle',
        'content',
        'display_order',
        'is_enabled',
        'background_color',
        'background_image_path',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'display_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }
}
