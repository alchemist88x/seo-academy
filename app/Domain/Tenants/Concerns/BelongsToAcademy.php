<?php

namespace App\Domain\Tenants\Concerns;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAcademy
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function scopeForAcademy(Builder $query, Academy|string|null $academy): Builder
    {
        $academyId = $academy instanceof Academy ? $academy->getKey() : $academy;

        if ($academyId === null) {
            return $query;
        }

        return $query->where($this->qualifyColumn('academy_id'), $academyId);
    }
}
