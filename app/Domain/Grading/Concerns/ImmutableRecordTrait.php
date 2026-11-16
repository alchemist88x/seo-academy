<?php

namespace App\Domain\Grading\Concerns;

use DomainException;

trait ImmutableRecordTrait
{
    public static function bootImmutableRecordTrait(): void
    {
        static::updating(function ($model) {
            throw new DomainException('Immutable record violation: updates are strictly prohibited on '.static::class);
        });

        static::deleting(function ($model) {
            throw new DomainException('Immutable record violation: deletions are strictly prohibited on '.static::class);
        });
    }
}
