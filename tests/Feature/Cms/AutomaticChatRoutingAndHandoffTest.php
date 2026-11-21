<?php

namespace Tests\Feature\Cms;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\Cms\Models\CmsChatbotSetting;
use App\Domain\Cms\Models\CmsChatConversation;
use App\Domain\Cms\Models\CmsChatMessage;
use App\Domain\Cms\Services\ChatbotService;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Livewire\Admin\Chat\ChatConversationsInbox;
use App\Livewire\Marketing\ChatbotWidget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AutomaticChatRoutingAndHandoffTest extends TestCase
{
    private static bool $sqliteMigrated = false;

    private static bool $mysqlMigrated = false;

    public static function engineProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
    }

    protected function tearDown(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('mysql');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        parent::tearDown();
    }

    protected function useEngine(string $engine): void
    {
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        if ($engine === 'sqlite') {
            $sqlitePath = storage_path('framework/testing/test_chatbot_routing_phase12_3.sqlite');
            if (! file_exists(dirname($sqlitePath))) {
                mkdir(dirname($sqlitePath), 0755, true);
            }

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $sqlitePath,
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');

            if (! static::$sqliteMigrated) {
                if (file_exists($sqlitePath)) {
                    @unlink($sqlitePath);
                }
                touch($sqlitePath);
                Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
                static::$sqliteMigrated = true;
            } else {
                $tables = DB::connection('sqlite')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');
                foreach ($tables as $t) {
                    if ($t->name !== 'migrations') {
                        DB::connection('sqlite')->table($t->name)->delete();
                    }
                }
                DB::connection('sqlite')->statement('PRAGMA foreign_keys = ON;');
            }
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 3306,
                'database.connections.mysql.database' => 'seo_academy_test',
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
            ]);
            DB::purge('mysql');
            DB::setDefaultConnection('mysql');
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }

            if (! static::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                static::$mysqlMigrated = true;
            } else {
                $tables = DB::connection('mysql')->select('SHOW TABLES');
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 0;');
                foreach ($tables as $t) {
                    $vals = array_values((array) $t);
                    $table = $vals[0];
                    if ($table !== 'migrations') {
                        DB::connection('mysql')->table($table)->delete();
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => $engine]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
    }

    private function createAcademy(): Academy
    {
        return Academy::create([
            'name' => 'SEO Routing Academy',
            'slug' => 'seo-routing-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name' => ucfirst($role).' User '.uniqid(),
            'email' => "{$role}_".uniqid().'@seo-academy.test',
        ]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('engineProvider')]
    public function test_01_default_operating_mode_is_auto_for_new_and_persisted_settings(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $this->assertSame('auto', $setting->default_operating_mode);
        $this->assertSame(300, $setting->circuit_breaker_cooldown);
        $this->assertTrue($setting->human_support_enabled);
    }

    #[DataProvider('engineProvider')]
    public function test_02_ai_availability_check_evaluates_enabled_credentials_and_circuit_breaker(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => false,
            'ai_provider' => 'null',
            'ai_api_key' => null,
        ]);

        $this->assertFalse($service->isAiAvailable($setting, $academy->id));

        // Enable AI with valid provider & mock key
        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-mock-key-12345',
        ]);
        $this->assertTrue($service->isAiAvailable($setting, $academy->id));

        // Trip circuit breaker
        $service->tripCircuitBreaker($academy->id, 'Provider timeout', 300);
        $this->assertTrue($service->isCircuitBreakerOpen($academy->id));
        $this->assertFalse($service->isAiAvailable($setting, $academy->id));

        // Reset circuit breaker
        $service->resetCircuitBreaker($academy->id);
        $this->assertFalse($service->isCircuitBreakerOpen($academy->id));
        $this->assertTrue($service->isAiAvailable($setting, $academy->id));
    }

    #[DataProvider('engineProvider')]
    public function test_03_auto_mode_routes_to_ai_provider_when_available(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-key',
        ]);

        // Mock AiProviderInterface
        $mockProvider = \Mockery::mock(AiProviderInterface::class);
        $mockProvider->shouldReceive('isAvailable')->andReturn(true);
        $mockProvider->shouldReceive('generate')->once()->andReturn('SEO Academy offers full-stack technical SEO mastery courses.');
        $this->app->instance(AiProviderInterface::class, $mockProvider);

        $sessionId = 'session-ai-'.uniqid();
        $response = $service->respond($academy->id, 'Tell me about technical SEO training', $sessionId);

        $this->assertSame('ai', $response['source']);
        $this->assertStringContainsString('SEO Academy offers full-stack technical SEO mastery courses', $response['answer']);

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertNotNull($conv);
        $this->assertSame('ai', $conv->handler);
        $this->assertSame('open', $conv->status);
    }

    #[DataProvider('engineProvider')]
    public function test_04_auto_mode_routes_to_human_support_when_ai_is_unavailable(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'ai_enabled' => true, // AI enabled but credentials/provider null
            'ai_provider' => 'null',
            'human_support_enabled' => true,
        ]);

        $sessionId = 'session-unavail-'.uniqid();
        $response = $service->respond($academy->id, 'What is your refund policy?', $sessionId);

        $this->assertSame('human_support', $response['source']);
        $this->assertTrue($response['is_handoff']);
        $this->assertStringContainsString('Our automated assistant is currently unavailable, so I have routed your conversation to our admissions support desk', $response['answer']);

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertNotNull($conv);
        $this->assertSame('human', $conv->handler);
        $this->assertSame('waiting_for_support', $conv->status);
        $this->assertSame('ai_unavailable', $conv->handoff_reason);
        $this->assertNotNull($conv->handoff_at);

        // Verify admin notification
        $notification = AcademyNotification::where('target', (string) $admin->id)->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Live Chat Support Requested', $notification->title);
    }

    #[DataProvider('engineProvider')]
    public function test_05_deterministic_human_intent_detection_triggers_immediate_handoff(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-key',
        ]);

        $testIntentPhrases = [
            'I want to talk to a human please',
            'Can I speak with an admissions advisor?',
            'Connect me to an agent right now',
            'I need customer service for my billing problem',
        ];

        foreach ($testIntentPhrases as $index => $phrase) {
            $sessionId = "session-intent-{$index}-".uniqid();
            $response = $service->respond($academy->id, $phrase, $sessionId);

            $this->assertSame('human_support', $response['source']);
            $this->assertTrue($response['is_handoff']);
            $this->assertStringContainsString('I am connecting you with our admissions and support team', $response['answer']);

            $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
            $this->assertNotNull($conv);
            $this->assertSame('human', $conv->handler);
            $this->assertSame('waiting_for_support', $conv->status);
            $this->assertSame('visitor_intent', $conv->handoff_reason);
            $this->assertNotNull($conv->handoff_at);
        }
    }

    #[DataProvider('engineProvider')]
    public function test_06_false_positive_guard_prevents_handoff_on_academic_terms(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $falsePositives = [
            'What is human resource management in digital agency workflows?',
            'How does human error impact technical SEO crawls?',
            'What role does human psychology play in conversion rate optimization?',
        ];

        foreach ($falsePositives as $query) {
            $this->assertFalse(
                $service->detectHumanIntent($query),
                "False positive triggered for query: {$query}"
            );
        }
    }

    #[DataProvider('engineProvider')]
    public function test_07_explicit_talk_to_human_button_request_human_support(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $service = app(ChatbotService::class);

        $sessionId = 'session-button-'.uniqid();
        $res = $service->requestHumanSupport(
            $academy->id,
            $sessionId,
            'Visitor clicked Talk to a Human button',
            'Jane Doe',
            'jane@example.com'
        );

        $this->assertTrue($res['success']);
        $this->assertSame('waiting_for_support', $res['status']);

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertNotNull($conv);
        $this->assertSame('human', $conv->handler);
        $this->assertSame('waiting_for_support', $conv->status);
        $this->assertSame('visitor_button', $conv->handoff_reason);
        $this->assertNotNull($conv->handoff_at);
        $this->assertSame('Jane Doe', $conv->visitor_name);
        $this->assertSame('jane@example.com', $conv->visitor_email);
    }

    #[DataProvider('engineProvider')]
    public function test_08_handler_state_is_decoupled_from_lifecycle_status(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $staff = $this->createUserWithRole('support_staff');
        $service = app(ChatbotService::class);

        $sessionId = 'session-lifecycle-'.uniqid();
        $service->requestHumanSupport($academy->id, $sessionId);

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertSame('human', $conv->handler);
        $this->assertSame('waiting_for_support', $conv->status);

        // Staff assigned
        $service->assignConversation($conv, $staff->id);
        $conv->refresh();
        $this->assertSame('human', $conv->handler);
        $this->assertSame('assigned', $conv->status);
        $this->assertSame($staff->id, $conv->assigned_to_user_id);
        $this->assertNotNull($conv->assigned_at);

        // Staff replies -> waiting_for_visitor
        $service->sendStaffReply($conv, $staff, 'Hello! How can I assist you with your enrolment today?');
        $conv->refresh();
        $this->assertSame('human', $conv->handler);
        $this->assertSame('waiting_for_visitor', $conv->status);

        // Mark resolved
        $service->updateConversationStatus($conv, 'resolved', $staff->id);
        $conv->refresh();
        $this->assertSame('human', $conv->handler);
        $this->assertSame('resolved', $conv->status);
        $this->assertNotNull($conv->resolved_at);
    }

    #[DataProvider('engineProvider')]
    public function test_09_routing_priority_order_human_assigned_conversation_prevents_ai_interference(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-key',
        ]);

        $sessionId = 'session-priority-'.uniqid();
        $service->requestHumanSupport($academy->id, $sessionId);

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertSame('human', $conv->handler);

        // AiProvider should NEVER be queried when conversation is human-owned
        $mockProvider = \Mockery::mock(AiProviderInterface::class);
        $mockProvider->shouldNotReceive('generate');
        $this->app->instance(AiProviderInterface::class, $mockProvider);

        // Visitor sends follow-up question
        $response = $service->respond($academy->id, 'How much does the advanced SEO batch cost?', $sessionId);

        $this->assertSame('human_support', $response['source']);
        $this->assertTrue($response['is_handoff']);
        $this->assertStringContainsString('Our support staff has received your question and will reply directly', $response['answer']);

        $conv->refresh();
        $this->assertSame('human', $conv->handler);
        $this->assertSame('waiting_for_support', $conv->status);
    }

    #[DataProvider('engineProvider')]
    public function test_10_circuit_breaker_trips_on_ai_failure_and_routes_to_human_in_auto_mode(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-key',
            'circuit_breaker_cooldown' => 240,
            'human_support_enabled' => true,
        ]);

        // Mock AiProvider failure
        $mockProvider = \Mockery::mock(AiProviderInterface::class);
        $mockProvider->shouldReceive('isAvailable')->andReturn(true);
        $mockProvider->shouldReceive('generate')->andThrow(new \RuntimeException('Provider 504 Gateway Timeout'));
        $this->app->instance(AiProviderInterface::class, $mockProvider);

        $sessionId = 'session-cb-trip-'.uniqid();
        $response = $service->respond($academy->id, 'Do you offer weekend batches?', $sessionId);

        $this->assertSame('human_support', $response['source']);
        $this->assertTrue($response['is_handoff']);
        $this->assertStringContainsString('temporary issue processing your question with our AI engine', $response['answer']);

        // Check Circuit Breaker is now open
        $this->assertTrue($service->isCircuitBreakerOpen($academy->id));

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertNotNull($conv);
        $this->assertSame('human', $conv->handler);
        $this->assertSame('ai_error', $conv->handoff_reason);
        $this->assertSame('waiting_for_support', $conv->status);
    }

    #[DataProvider('engineProvider')]
    public function test_11_open_circuit_breaker_blocks_subsequent_ai_calls_during_cooldown(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-key',
            'human_support_enabled' => true,
        ]);

        // Trip the circuit breaker directly
        $service->tripCircuitBreaker($academy->id, 'Upstream rate limit', 300);
        $this->assertTrue($service->isCircuitBreakerOpen($academy->id));

        // Mock provider should not be called
        $mockProvider = \Mockery::mock(AiProviderInterface::class);
        $mockProvider->shouldNotReceive('generate');
        $this->app->instance(AiProviderInterface::class, $mockProvider);

        $sessionId = 'session-cb-subsequent-'.uniqid();
        $response = $service->respond($academy->id, 'What is included in the course syllabus?', $sessionId);

        $this->assertSame('human_support', $response['source']);
        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertSame('human', $conv->handler);
        $this->assertSame('ai_unavailable', $conv->handoff_reason);
    }

    #[DataProvider('engineProvider')]
    public function test_12_circuit_breaker_manual_reset_restores_ai_availability(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-key',
        ]);

        $service->tripCircuitBreaker($academy->id, 'Temporary outage', 300);
        $this->assertTrue($service->isCircuitBreakerOpen($academy->id));

        $service->resetCircuitBreaker($academy->id);
        $this->assertFalse($service->isCircuitBreakerOpen($academy->id));
        $this->assertTrue($service->isAiAvailable($setting, $academy->id));
    }

    #[DataProvider('engineProvider')]
    public function test_13_staff_or_admin_can_takeover_ai_conversation_from_inbox(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $staff = $this->createUserWithRole('support_staff');
        $service = app(ChatbotService::class);

        $conv = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => 'session-takeover-'.uniqid(),
            'handler' => 'ai',
            'status' => 'open',
            'operating_mode' => 'auto',
            'last_activity_at' => now(),
        ]);

        $updated = $service->takeoverConversation($conv, $staff);

        $this->assertSame('human', $updated->handler);
        $this->assertSame('staff_takeover', $updated->handoff_reason);
        $this->assertSame('assigned', $updated->status);
        $this->assertSame($staff->id, $updated->assigned_to_user_id);
        $this->assertNotNull($updated->handoff_at);
        $this->assertNotNull($updated->assigned_at);

        // Verify internal system note
        $note = CmsChatMessage::where('conversation_id', $conv->id)->where('is_internal', true)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('took over this conversation', $note->message);
    }

    #[DataProvider('engineProvider')]
    public function test_14_admin_can_return_conversation_to_auto_mode(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $staff = $this->createUserWithRole('support_staff');
        $service = app(ChatbotService::class);

        $conv = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => 'session-return-'.uniqid(),
            'handler' => 'human',
            'handoff_reason' => 'visitor_intent',
            'status' => 'waiting_for_support',
            'operating_mode' => 'human_support',
            'last_activity_at' => now(),
        ]);

        // Non-admin cannot return to auto
        $this->expectException(AuthorizationException::class);
        $service->returnToAutoMode($conv, $staff);
    }

    #[DataProvider('engineProvider')]
    public function test_14b_admin_successfully_returns_conversation_to_auto_mode(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $service = app(ChatbotService::class);

        $conv = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => 'session-return-ok-'.uniqid(),
            'handler' => 'human',
            'handoff_reason' => 'visitor_intent',
            'status' => 'waiting_for_support',
            'operating_mode' => 'human_support',
            'last_activity_at' => now(),
        ]);

        $updated = $service->returnToAutoMode($conv, $admin);

        $this->assertSame('ai', $updated->handler);
        $this->assertSame('manual_reset', $updated->handoff_reason);
        $this->assertSame('auto', $updated->operating_mode);
        $this->assertSame('open', $updated->status);
        $this->assertNull($updated->assigned_to_user_id);
    }

    #[DataProvider('engineProvider')]
    public function test_15_chat_conversations_inbox_livewire_component_filters_by_handler_and_status(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');

        $cAi = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => 'c-ai-'.uniqid(),
            'visitor_name' => 'AI Visitor',
            'handler' => 'ai',
            'status' => 'open',
            'operating_mode' => 'auto',
            'last_activity_at' => now(),
        ]);

        $cHuman = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => 'c-human-'.uniqid(),
            'visitor_name' => 'Human Support Visitor',
            'handler' => 'human',
            'handoff_reason' => 'visitor_intent',
            'status' => 'waiting_for_support',
            'operating_mode' => 'auto',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ChatConversationsInbox::class, ['conversationId' => $cHuman->id])
            ->assertSee('Human Support Visitor')
            ->assertSee('AI Visitor')
            ->call('setStatusFilter', 'human')
            ->assertSee('Human Support Visitor')
            ->assertDontSee('AI Visitor')
            ->call('setStatusFilter', 'ai')
            ->assertSee('AI Visitor')
            ->assertDontSee('Human Support Visitor')
            ->call('selectConversation', $cAi->id)
            ->call('takeOverConversation')
            ->assertSee('You have taken over this conversation');

        $cAi->refresh();
        $this->assertSame('human', $cAi->handler);
    }

    #[DataProvider('engineProvider')]
    public function test_16_chatbot_widget_livewire_reflects_handler_and_human_support_polling(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'default_operating_mode' => 'auto',
            'human_support_enabled' => true,
        ]);

        $sessionId = 'widget-routing-'.uniqid();
        session()->put('seo_chatbot_session_id', $sessionId);

        Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true)
            ->call('requestHumanSupport')
            ->assertSet('isWaitingForSupport', true)
            ->assertSet('handler', 'human')
            ->assertSee('Connected with Human Support');

        $conv = CmsChatConversation::where('academy_id', $academy->id)->where('session_id', $sessionId)->first();
        $this->assertNotNull($conv);
        $this->assertSame('human', $conv->handler);
        $this->assertSame('visitor_button', $conv->handoff_reason);
    }
}
