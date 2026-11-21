<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class CmsNavigationItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_navigation_items';

    protected $fillable = [
        'academy_id',
        'menu_location', // header, footer
        'label',
        'url',
        'route_name',
        'target',
        'display_order',
        'is_active',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTitleAttribute(): string
    {
        return $this->label ?? '';
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['label'] = $value;
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    public function getEffectiveUrl(): string
    {
        if ($this->route_name && Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?? '#';
    }
}
