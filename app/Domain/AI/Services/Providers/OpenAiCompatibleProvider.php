<?php

namespace App\Domain\AI\Services\Providers;

use App\Domain\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleProvider implements AiProviderInterface
{
    public function __construct(
        private readonly string $driver = 'openai_compatible',
        private readonly string $endpoint = '',
        private readonly string $model = 'gpt-4.1-mini',
        private readonly string $apiKey = '',
        private readonly int $timeout = 30,
    ) {}

    public function generate(string $prompt, array $context = []): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI provider endpoint or model is not configured.');
        }

        $messages = $this->buildMessages($prompt, $context);
        $url = $this->resolveEndpoint();

        $request = Http::timeout($this->timeout)->acceptJson();

        if ($this->apiKey !== '') {
            $request = $request->withToken($this->apiKey);
        }

        if ($this->driver === 'ollama' && str_ends_with($url, '/api/generate')) {
            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
            ];
        } else {
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
            ];

            if ($this->driver === 'ollama') {
                $payload['stream'] = false;
            }
        }

        $response = $request->post($url, $payload);
        $response->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Unexpected response format from AI provider.');
        }

        // Standard OpenAI Chat Completions format
        if (isset($data['choices'][0]['message']['content'])) {
            return (string) $data['choices'][0]['message']['content'];
        }

        // OpenAI Responses API format
        if (isset($data['output_text'])) {
            return (string) $data['output_text'];
        }

        if (isset($data['output'][0]['content'][0]['text'])) {
            return (string) $data['output'][0]['content'][0]['text'];
        }

        // Ollama /api/chat format
        if (isset($data['message']['content'])) {
            return (string) $data['message']['content'];
        }

        // Ollama /api/generate format
        if (isset($data['response'])) {
            return (string) $data['response'];
        }

        throw new RuntimeException('AI provider response did not contain generated text.');
    }

    public function isAvailable(): bool
    {
        return trim($this->endpoint) !== '' && trim($this->model) !== '';
    }

    public function driverName(): string
    {
        return $this->driver;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(string $prompt, array $context): array
    {
        $messages = [];

        if (! empty($context['system']) && is_string($context['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $context['system'],
            ];
        }

        if (! empty($context['history']) && is_array($context['history'])) {
            foreach ($context['history'] as $entry) {
                if (isset($entry['role'], $entry['content']) && is_string($entry['role']) && is_string($entry['content'])) {
                    $messages[] = [
                        'role' => $entry['role'],
                        'content' => $entry['content'],
                    ];
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $messages;
    }

    private function resolveEndpoint(): string
    {
        $trimmed = rtrim(trim($this->endpoint), '/');

        if ($this->driver === 'ollama') {
            if (
                str_ends_with($trimmed, '/api/chat')
                || str_ends_with($trimmed, '/api/generate')
                || str_ends_with($trimmed, '/v1/chat/completions')
            ) {
                return $trimmed;
            }

            return $trimmed.'/api/chat';
        }

        if (
            str_ends_with($trimmed, '/chat/completions')
            || str_ends_with($trimmed, '/responses')
        ) {
            return $trimmed;
        }

        return $trimmed.'/v1/chat/completions';
    }
}
