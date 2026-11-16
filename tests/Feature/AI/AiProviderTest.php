<?php

namespace Tests\Feature\AI;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Models\AcademyAiCredential;
use App\Domain\AI\Services\AiAssistantService;
use App\Domain\AI\Services\Providers\NullAiProvider;
use App\Domain\AI\Services\Providers\OpenAiCompatibleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_provider_is_resolved_when_ai_is_disabled(): void
    {
        config([
            'lms.ai_enabled' => false,
            'lms.ai_driver' => 'openai_compatible',
        ]);

        $provider = app(AiProviderInterface::class);
        $service = app(AiAssistantService::class);

        $this->assertInstanceOf(NullAiProvider::class, $provider);
        $this->assertFalse($provider->isAvailable());
        $this->assertFalse($service->isEnabled());

        $reply = $service->ask('What is technical SEO?');
        $this->assertStringContainsString('disabled', strtolower($reply));
    }

    public function test_ai_assistant_service_enforces_max_input_characters(): void
    {
        config([
            'lms.ai_enabled' => true,
            'lms.ai_driver' => 'openai_compatible',
            'lms.ai_endpoint' => 'https://api.example.test/v1/chat/completions',
            'lms.ai_model' => 'gpt-4.1-mini',
            'lms.max_input_characters' => 20,
        ]);

        $service = app(AiAssistantService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('20');

        $service->ask(str_repeat('a', 21));
    }

    public function test_openai_compatible_driver_works_with_http_fake(): void
    {
        Http::fake([
            'https://api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Use canonical tags to consolidate duplicate URLs.']],
                ],
            ], 200),
        ]);

        config([
            'lms.ai_enabled' => true,
            'lms.ai_driver' => 'openai_compatible',
            'lms.ai_endpoint' => 'https://api.example.test/v1/chat/completions',
            'lms.ai_model' => 'gpt-4.1-mini',
            'lms.ai_api_key' => 'test-key',
            'lms.max_input_characters' => 4000,
        ]);

        $provider = app(AiProviderInterface::class);
        $service = app(AiAssistantService::class);

        $this->assertInstanceOf(OpenAiCompatibleProvider::class, $provider);
        $this->assertSame('openai_compatible', $provider->driverName());
        $this->assertTrue($service->isEnabled());

        $reply = $service->ask('How do canonical tags work?');

        $this->assertSame('Use canonical tags to consolidate duplicate URLs.', $reply);
        Http::assertSentCount(1);
    }

    public function test_ollama_driver_works_with_http_fake(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/*' => Http::response([
                'message' => ['content' => 'Crawlability ensures search engines can access your pages.'],
            ], 200),
        ]);

        config([
            'lms.ai_enabled' => true,
            'lms.ai_driver' => 'ollama',
            'lms.ai_endpoint' => 'http://127.0.0.1:11434/api/chat',
            'lms.ai_model' => 'llama3.2',
            'lms.max_input_characters' => 4000,
        ]);

        $provider = app(AiProviderInterface::class);
        $service = app(AiAssistantService::class);

        $this->assertInstanceOf(OpenAiCompatibleProvider::class, $provider);
        $this->assertSame('ollama', $provider->driverName());
        $this->assertTrue($service->isEnabled());

        $reply = $service->ask('Explain crawlability');

        $this->assertSame('Crawlability ensures search engines can access your pages.', $reply);
        Http::assertSentCount(1);
    }

    public function test_academy_ai_credential_encrypts_api_key_at_rest_and_hides_it_from_serialization(): void
    {
        $academy = Academy::factory()->create();
        $plaintextKey = 'sk-secret-academy-key-12345';

        $credential = AcademyAiCredential::create([
            'academy_id' => $academy->getKey(),
            'driver' => 'openai_compatible',
            'endpoint' => 'https://api.example.test/v1/chat/completions',
            'model' => 'gpt-4.1-mini',
            'api_key' => $plaintextKey,
            'is_active' => false,
        ]);

        $rawValue = DB::table('academy_ai_credentials')
            ->where('id', $credential->getKey())
            ->value('api_key');

        $this->assertNotSame($plaintextKey, $rawValue);
        $this->assertSame($plaintextKey, $credential->fresh()->api_key);

        $array = $credential->toArray();
        $json = $credential->toJson();

        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertStringNotContainsString($plaintextKey, $json);
    }

    public function test_ai_assistant_service_rate_limits_requests_per_user(): void
    {
        Http::fake([
            'https://api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'SEO tip']],
                ],
            ], 200),
        ]);

        config([
            'lms.ai_enabled' => true,
            'lms.ai_driver' => 'openai_compatible',
            'lms.ai_endpoint' => 'https://api.example.test/v1/chat/completions',
            'lms.ai_model' => 'gpt-4.1-mini',
            'lms.ai_api_key' => 'test-key',
            'lms.max_messages_per_minute' => 2,
        ]);

        $userId = 'user-rate-limit-test';
        RateLimiter::clear('ai-assistant:'.$userId);

        $service = app(AiAssistantService::class);

        $this->assertSame('SEO tip', $service->ask('First question', [], $userId));
        $this->assertSame('SEO tip', $service->ask('Second question', [], $userId));

        $this->expectException(TooManyRequestsHttpException::class);
        $service->ask('Third question', [], $userId);
    }

    public function test_inactive_credential_or_disabled_config_prevents_ai_activation(): void
    {
        $academy = Academy::factory()->create();
        $credential = AcademyAiCredential::create([
            'academy_id' => $academy->getKey(),
            'driver' => 'openai_compatible',
            'endpoint' => 'https://api.example.test/v1/chat/completions',
            'model' => 'gpt-4.1-mini',
            'api_key' => 'sk-test',
            'is_active' => false,
        ]);

        config([
            'lms.ai_enabled' => true,
            'lms.ai_driver' => 'openai_compatible',
            'lms.ai_endpoint' => 'https://api.example.test/v1/chat/completions',
            'lms.ai_model' => 'gpt-4.1-mini',
        ]);

        $service = app(AiAssistantService::class);
        $this->assertFalse($service->isEnabled($credential));

        config(['lms.ai_enabled' => false]);
        $credential->is_active = true;
        $this->assertFalse($service->isEnabled($credential));
    }
}
