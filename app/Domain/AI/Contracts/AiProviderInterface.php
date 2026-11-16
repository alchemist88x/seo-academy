<?php

namespace App\Domain\AI\Contracts;

interface AiProviderInterface
{
    /**
     * Generate a response for the given prompt and optional context.
     *
     * @param  array<string, mixed>  $context
     */
    public function generate(string $prompt, array $context = []): string;

    /**
     * Determine whether the provider is configured and available for requests.
     */
    public function isAvailable(): bool;

    /**
     * Return the canonical driver name for this provider.
     */
    public function driverName(): string;
}
