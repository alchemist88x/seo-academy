<?php

namespace App\Domain\AI\Services\Providers;

use App\Domain\AI\Contracts\AiProviderInterface;

class NullAiProvider implements AiProviderInterface
{
    public function generate(string $prompt, array $context = []): string
    {
        return 'AI assistant is currently disabled. Please contact your academy administrator to enable it.';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function driverName(): string
    {
        return 'null';
    }
}
