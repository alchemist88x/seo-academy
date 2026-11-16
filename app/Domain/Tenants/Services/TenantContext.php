<?php

namespace App\Domain\Tenants\Services;

use App\Domain\Academies\Models\Academy;

class TenantContext
{
    public const SESSION_KEY = 'current_academy_id';

    protected ?Academy $academy = null;

    protected bool $resolved = false;

    protected bool $resolvedFromHost = false;

    public function getAcademy(): ?Academy
    {
        if ($this->resolved && $this->academy !== null) {
            return $this->academy;
        }

        $this->academy = Academy::current();
        $this->resolved = $this->academy !== null;

        return $this->academy;
    }

    public function setAcademy(?Academy $academy, bool $fromHost = false): void
    {
        if ($academy === null) {
            $this->clear();

            return;
        }

        $this->academy = $academy;
        $this->resolved = true;
        $this->resolvedFromHost = false;
    }

    public function isResolvedFromHost(): bool
    {
        return false;
    }

    public function id(): ?string
    {
        return $this->getAcademy()?->getKey();
    }

    public function clear(): void
    {
        $this->academy = null;
        $this->resolved = false;
        $this->resolvedFromHost = false;
    }

    public function isPlatformScope(): bool
    {
        return false;
    }

    public function resolveAcademyFromHost(string $host): ?Academy
    {
        return Academy::current();
    }
}
