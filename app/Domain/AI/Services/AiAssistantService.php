<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Models\AcademyAiCredential;
use App\Domain\AI\Services\Providers\NullAiProvider;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AiAssistantService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
    ) {}

    public function isEnabled(?AcademyAiCredential $credential = null): bool
    {
        if (! (bool) config('lms.ai_enabled', false)) {
            return false;
        }

        if ($credential !== null && ! $credential->is_active) {
            return false;
        }

        return $this->provider->isAvailable();
    }

    public function provider(): AiProviderInterface
    {
        return $this->provider;
    }

    /**
     * Validate input constraints and generate an AI response via the bound provider.
     *
     * @param  array<string, mixed>  $context
     */
    public function ask(string $prompt, array $context = [], ?string $userId = null): string
    {
        $cleanPrompt = trim($prompt);

        if ($cleanPrompt === '') {
            throw new InvalidArgumentException('Prompt cannot be empty.');
        }

        $maxCharacters = (int) config('lms.max_input_characters', 4000);

        if ($maxCharacters > 0 && mb_strlen($cleanPrompt) > $maxCharacters) {
            throw new InvalidArgumentException("Prompt exceeds maximum length of {$maxCharacters} characters.");
        }

        if (! (bool) config('lms.ai_enabled', false)) {
            return (new NullAiProvider)->generate($cleanPrompt, $context);
        }

        $resolvedUserId = $userId ?? (isset($context['user_id']) && is_string($context['user_id']) ? $context['user_id'] : null);

        if ($resolvedUserId !== null && $resolvedUserId !== '') {
            $maxPerMinute = (int) config('lms.max_messages_per_minute', 10);

            if ($maxPerMinute > 0) {
                $key = 'ai-assistant:'.$resolvedUserId;

                if (RateLimiter::tooManyAttempts($key, $maxPerMinute)) {
                    throw new TooManyRequestsHttpException(
                        RateLimiter::availableIn($key),
                        'AI rate limit exceeded. Please wait before sending another message.',
                    );
                }

                RateLimiter::hit($key, 60);
            }
        }

        return $this->provider->generate($cleanPrompt, $context);
    }
}
