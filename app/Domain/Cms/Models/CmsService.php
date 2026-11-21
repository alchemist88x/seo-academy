<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsService extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_services';

    protected $fillable = [
        'academy_id',
        'title',
        'slug',
        'icon',
        'short_description',
        'full_description',
        'summary',
        'description',
        'benefits',
        'cta_label',
        'cta_url',
        'display_order',
        'is_published',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'display_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function getSummaryAttribute(): string
    {
        return $this->short_description ?? '';
    }

    public function setSummaryAttribute(?string $val): void
    {
        $this->attributes['short_description'] = $val;
    }

    public function getDescriptionAttribute(): string
    {
        return $this->full_description ?? '';
    }

    public function setDescriptionAttribute(?string $val): void
    {
        $this->attributes['full_description'] = $val;
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }
}
