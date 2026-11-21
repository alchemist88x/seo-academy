<?php

namespace App\Domain\AI\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiFaq extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $fillable = [
        'academy_id',
        'question',
        'answer',
        'category',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
