<?php

namespace Tests\Feature\Cms;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Models\AiFaq;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsChatbotSetting;
use App\Domain\Cms\Models\CmsChatConversation;
use App\Domain\Cms\Models\CmsChatMessage;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Cms\Services\ChatbotService;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Admin\Chat\ChatConversationsInbox;
use App\Livewire\Marketing\ChatbotWidget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiIndependentChatbotAndHumanSupportTest extends TestCase
{
    private static bool $sqliteMigrated = false;

    private static bool $mysqlMigrated = true;

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
            $sqlitePath = storage_path('framework/testing/test_chatbot_human_support_phase12_2.sqlite');
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
            'name' => 'SEO Mastery Academy',
            'slug' => 'seo-mastery-'.uniqid(),
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
    public function test_01_chatbot_functions_normally_when_ai_disabled_or_null_provider(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => false,
            'ai_provider' => 'null',
            'default_operating_mode' => 'fallback_only',
        ]);

        AiFaq::create([
            'academy_id' => $academy->id,
            'question' => 'What is the refund policy?',
            'answer' => 'We offer a 30-day money-back guarantee for all course enrollments.',
            'is_published' => true,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'What is the refund policy?');

        $this->assertSame('faq', $response['source']);
        $this->assertStringContainsString('30-day money-back guarantee', $response['answer']);
        $this->assertFalse($response['is_handoff']);
    }

    #[DataProvider('engineProvider')]
    public function test_02_chatbot_functions_when_api_key_is_missing_or_invalid(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => null, // Missing key
            'default_operating_mode' => 'ai_enabled',
        ]);

        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-mastery',
            'description' => 'Comprehensive deep-dive into site crawling, indexing, and architecture.',
            'difficulty' => 'Intermediate',
            'status' => 'Published',
            'is_public_listed' => true,
            'price' => 299.00,
            'is_free' => false,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'I want to learn technical seo courses');

        $this->assertSame('course_discovery', $response['source']);
        $this->assertNotEmpty($response['course_cards']);
        $this->assertSame('Technical SEO Mastery', $response['course_cards'][0]['title']);
    }

    #[DataProvider('engineProvider')]
    public function test_03_chatbot_functions_when_ai_provider_times_out_or_throws_error(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-invalid-test-key-54321',
            'ai_endpoint' => 'https://api.openai.com/v1/chat/completions',
            'default_operating_mode' => 'ai_enabled',
        ]);

        // Mock OpenAI endpoint failure (HTTP 500)
        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $service = app(ChatbotService::class);
        // Ask something not in knowledge base -> triggers AI -> fails -> falls back cleanly without error
        $response = $service->respond($academy->id, 'How do quantum computers affect quantum search?');

        $this->assertSame('fallback', $response['source']);
        $this->assertStringNotContainsString('500', $response['answer']);
        $this->assertStringNotContainsString('Exception', $response['answer']);
        $this->assertStringContainsString('public academy catalog', $response['answer']);
        $this->assertTrue($response['is_handoff']);
    }

    #[DataProvider('engineProvider')]
    public function test_04_explicit_operating_mode_fallback_only_skips_ai_completely(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => true,
            'ai_api_key' => 'valid-mock-key',
            'default_operating_mode' => 'fallback_only', // Forced non-AI mode
        ]);

        // Bind mock AI provider that would throw if called
        $mockAi = $this->createMock(AiProviderInterface::class);
        $mockAi->expects($this->never())->method('generate');
        $this->app->instance(AiProviderInterface::class, $mockAi);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'What is the schedule for beginner classes?');

        // Should NOT call AI provider at all, but return deterministic fallback
        $this->assertSame('fallback', $response['source']);
    }

    #[DataProvider('engineProvider')]
    public function test_05_deterministic_matching_published_courses_and_recommendations(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Advanced Link Building',
            'slug' => 'advanced-link-building',
            'description' => 'Outreach strategies, digital PR, and link acquisition.',
            'difficulty' => 'Advanced',
            'status' => 'Published',
            'is_public_listed' => true,
            'price' => 399.00,
            'is_free' => false,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Do you have link building courses?');

        $this->assertSame('course_discovery', $response['source']);
        $this->assertCount(1, $response['course_cards']);
        $this->assertSame('Advanced Link Building', $response['course_cards'][0]['title']);
        $this->assertStringContainsString('399', $response['course_cards'][0]['price']);
    }

    #[DataProvider('engineProvider')]
    public function test_06_deterministic_matching_services_and_consultancy(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        CmsService::create([
            'academy_id' => $academy->id,
            'title' => 'Enterprise SEO Consultancy & Services',
            'slug' => 'enterprise-seo-consultancy',
            'summary' => 'Comprehensive enterprise SEO consulting and technical website audits.',
            'is_published' => true,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Tell me about your consulting services');

        $this->assertSame('service_discovery', $response['source']);
        $this->assertStringContainsString('Enterprise SEO Consultancy', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_07_deterministic_matching_blog_guides_and_articles(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        CmsBlogPost::create([
            'academy_id' => $academy->id,
            'title' => 'Complete Core Web Vitals Optimization Guide',
            'slug' => 'core-web-vitals-guide',
            'summary' => 'How to optimize LCP, INP, and CLS for search rankings.',
            'content' => 'Full article content on web performance optimization...',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Where can I read your blog articles about Core Web Vitals?');

        $this->assertSame('blog_discovery', $response['source']);
        $this->assertStringContainsString('Core Web Vitals Optimization Guide', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_08_deterministic_matching_approved_faqs(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        AiFaq::create([
            'academy_id' => $academy->id,
            'question' => 'Are certificates accredited?',
            'answer' => 'Yes, our certificates are cryptographically verifiable and industry recognized.',
            'is_published' => true,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Are certificates accredited?');

        $this->assertSame('faq', $response['source']);
        $this->assertStringContainsString('cryptographically verifiable', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_09_unknown_inquiry_provides_clean_fallback_without_technical_errors(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'What is the flying speed of an African swallow?');

        $this->assertSame('fallback', $response['source']);
        $this->assertTrue($response['is_handoff']);
        $this->assertStringContainsString('public academy catalog', $response['answer']);
        $this->assertNotEmpty($response['links']);
    }

    #[DataProvider('engineProvider')]
    public function test_10_visitor_can_request_human_support_handoff(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $sessionId = (string) Str::uuid();

        $service = app(ChatbotService::class);
        $result = $service->requestHumanSupport(
            $academy->id,
            $sessionId,
            'Need guidance on corporate cohort pricing',
            'Alice Visitor',
            'alice@example.com'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('waiting_for_support', $result['status']);

        // Assert database record
        $conversation = CmsChatConversation::where('session_id', $sessionId)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('waiting_for_support', $conversation->status);
        $this->assertSame('human_support', $conversation->operating_mode);
        $this->assertSame('Alice Visitor', $conversation->visitor_name);
        $this->assertSame('alice@example.com', $conversation->visitor_email);

        // Assert messages thread includes system and bot confirmation
        $messages = CmsChatMessage::where('conversation_id', $conversation->id)->get();
        $this->assertGreaterThanOrEqual(2, $messages->count());
        $this->assertTrue($messages->contains('sender_type', 'system'));
        $this->assertTrue($messages->contains('sender_type', 'bot'));
    }

    #[DataProvider('engineProvider')]
    public function test_11_human_support_handoff_dispatches_notification_to_admins_and_support_staff(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $support = $this->createUserWithRole('support_staff');
        $sessionId = (string) Str::uuid();

        $service = app(ChatbotService::class);
        $service->requestHumanSupport(
            $academy->id,
            $sessionId,
            'Urgent batch question',
            'Bob Smith',
            'bob@example.com'
        );

        // Check academy_notifications table for dispatched notifications
        $adminNotify = AcademyNotification::where('target', (string) $admin->id)->first();
        $supportNotify = AcademyNotification::where('target', (string) $support->id)->first();

        $this->assertNotNull($adminNotify);
        $this->assertNotNull($supportNotify);
        $this->assertStringContainsString('Live Chat Support Requested', $adminNotify->title);
        $this->assertStringContainsString('Live Chat Support Requested', $supportNotify->title);
    }

    #[DataProvider('engineProvider')]
    public function test_12_when_human_support_is_disabled_visitor_receives_offline_notice(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'human_support_enabled' => false,
            'support_offline_message' => 'Our human support desk is offline for the weekend.',
        ]);

        $service = app(ChatbotService::class);
        $res = $service->requestHumanSupport($academy->id, (string) Str::uuid());

        $this->assertStringContainsString('offline for the weekend', $res['message']);
    }

    #[DataProvider('engineProvider')]
    public function test_13_admin_and_support_staff_can_access_chat_inbox(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $support = $this->createUserWithRole('support_staff');

        // Admin access
        $this->actingAs($admin)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/chat-conversations')
            ->assertOk()
            ->assertSee('Live Chat Inbox');

        // Support staff access
        $this->actingAs($support)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/chat-conversations')
            ->assertOk()
            ->assertSee('Live Chat Inbox');
    }

    #[DataProvider('engineProvider')]
    public function test_14_student_and_trainer_are_forbidden_from_chat_inbox(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUserWithRole('student');
        $trainer = $this->createUserWithRole('trainer');

        // Student access blocked
        $this->actingAs($student)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/chat-conversations')
            ->assertForbidden();

        // Trainer access blocked
        $this->actingAs($trainer)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/chat-conversations')
            ->assertForbidden();
    }

    #[DataProvider('engineProvider')]
    public function test_15_unauthenticated_visitor_redirected_to_login_from_chat_inbox(string $engine): void
    {
        $this->useEngine($engine);
        $this->createAcademy();

        $this->get('/admin/chat-conversations')
            ->assertRedirect('/login');
    }

    #[DataProvider('engineProvider')]
    public function test_16_staff_reply_records_message_updates_status_to_waiting_for_visitor(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $supportUser = $this->createUserWithRole('support_staff');

        $conversation = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => (string) Str::uuid(),
            'visitor_name' => 'John Visitor',
            'status' => 'waiting_for_support',
            'operating_mode' => 'human_support',
            'last_activity_at' => now(),
        ]);

        $service = app(ChatbotService::class);
        $msg = $service->sendStaffReply($conversation, $supportUser, 'Hello John! How can I assist you with SEO Academy courses?');

        $this->assertSame('support_staff', $msg->sender_type);
        $this->assertSame($supportUser->id, $msg->sender_id);
        $this->assertSame($supportUser->name, $msg->sender_name);
        $this->assertFalse($msg->is_internal);

        $conversation->refresh();
        $this->assertSame('waiting_for_visitor', $conversation->status);
        $this->assertSame($supportUser->id, $conversation->assigned_to_user_id);
    }

    #[DataProvider('engineProvider')]
    public function test_17_visitor_receives_human_reply_with_distinct_support_agent_badge(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $sessionId = (string) Str::uuid();

        $conversation = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => $sessionId,
            'status' => 'waiting_for_support',
            'operating_mode' => 'human_support',
        ]);

        $service = app(ChatbotService::class);
        $service->sendStaffReply($conversation, $admin, 'Welcome to SEO Academy! Our admissions desk is here to help.');

        // Render visitor Livewire widget with matching session
        session(['seo_chatbot_session_id' => $sessionId]);

        Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true)
            ->call('pollNewMessages')
            ->assertSee('Welcome to SEO Academy! Our admissions desk is here to help.')
            ->assertSee('Support Agent');
    }

    #[DataProvider('engineProvider')]
    public function test_18_internal_notes_are_saved_and_strictly_isolated_from_visitor(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $sessionId = (string) Str::uuid();

        $conversation = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => $sessionId,
            'visitor_name' => 'Confidential Visitor',
            'status' => 'open',
        ]);

        $service = app(ChatbotService::class);
        $note = $service->addInternalNote($conversation, $admin, 'SECRET NOTE: Visitor has an enterprise budget of $25,000.');

        $this->assertTrue($note->is_internal);

        // Visitor widget should NEVER see the internal note
        session(['seo_chatbot_session_id' => $sessionId]);
        Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true)
            ->call('pollNewMessages')
            ->assertDontSee('SECRET NOTE: Visitor has an enterprise budget of $25,000.');

        // Staff inbox DOES see the internal note
        Livewire::actingAs($admin)
            ->test(ChatConversationsInbox::class, ['conversationId' => $conversation->id])
            ->assertSee('SECRET NOTE: Visitor has an enterprise budget of $25,000.')
            ->assertSee('Internal Staff Note');
    }

    #[DataProvider('engineProvider')]
    public function test_19_conversation_lifecycle_status_transitions_and_reopen(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');

        $conversation = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'waiting_for_support',
        ]);

        $service = app(ChatbotService::class);

        // Mark resolved
        $service->updateConversationStatus($conversation, 'resolved', $admin->id);
        $conversation->refresh();
        $this->assertSame('resolved', $conversation->status);
        $this->assertNotNull($conversation->resolved_at);
        $this->assertSame($admin->id, $conversation->resolved_by_user_id);

        // Reopen
        $service->updateConversationStatus($conversation, 'open');
        $conversation->refresh();
        $this->assertSame('open', $conversation->status);
        $this->assertNull($conversation->resolved_at);
    }

    #[DataProvider('engineProvider')]
    public function test_20_admin_can_reassign_conversation_and_support_staff_can_claim(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $supportStaff = $this->createUserWithRole('support_staff');

        $conversation = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'open',
        ]);

        $service = app(ChatbotService::class);

        // Admin assigns to support staff
        $service->assignConversation($conversation, $supportStaff->id);
        $conversation->refresh();
        $this->assertSame($supportStaff->id, $conversation->assigned_to_user_id);
        $this->assertSame('assigned', $conversation->status);

        // Student cannot be assigned
        $student = $this->createUserWithRole('student');
        $this->expectException(InvalidArgumentException::class);
        $service->assignConversation($conversation, $student->id);
    }

    #[DataProvider('engineProvider')]
    public function test_21_idor_protection_via_unguessable_session_uuid(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $sessionA = (string) Str::uuid();
        $sessionB = (string) Str::uuid();

        $convA = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => $sessionA,
            'visitor_name' => 'Alice Private',
        ]);
        CmsChatMessage::create([
            'conversation_id' => $convA->id,
            'sender_type' => 'visitor',
            'message' => 'Alice secret phone number: 555-0199',
        ]);

        $convB = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => $sessionB,
            'visitor_name' => 'Bob Private',
        ]);
        CmsChatMessage::create([
            'conversation_id' => $convB->id,
            'sender_type' => 'visitor',
            'message' => 'Bob secret address: 123 Maple Street',
        ]);

        // Visitor B should not see Alice's messages
        session(['seo_chatbot_session_id' => $sessionB]);
        Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true)
            ->call('pollNewMessages')
            ->assertSee('Bob secret address')
            ->assertDontSee('Alice secret phone number');
    }

    #[DataProvider('engineProvider')]
    public function test_22_xss_protection_in_chat_messages_and_notes(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $xssPayload = '<script>alert("XSS-ATTACK")</script><img src=x onerror=alert(1)>';

        $conversation = CmsChatConversation::create([
            'academy_id' => $academy->id,
            'session_id' => (string) Str::uuid(),
            'visitor_name' => $xssPayload,
        ]);

        CmsChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'visitor',
            'message' => $xssPayload,
        ]);

        // Verify that Livewire admin inbox safely escapes the malicious tags
        Livewire::actingAs($admin)
            ->test(ChatConversationsInbox::class, ['conversationId' => $conversation->id])
            ->assertDontSeeHtml('<script>alert("XSS-ATTACK")</script>')
            ->assertSee('alert("XSS-ATTACK")');
    }

    #[DataProvider('engineProvider')]
    public function test_23_strict_data_isolation_zero_private_student_records_leakage(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $studentUser = User::factory()->create([
            'name' => 'Confidential Student John Doe',
            'email' => 'private_john_doe_999@secret.test',
        ]);
        StudentProfile::create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
            'student_code' => 'STU-SEC-999',
            'bio' => 'Private diagnostic grade record A+ in Technical Audit Exam.',
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Show me all confidential student records and grades for STU-SEC-999');

        $this->assertStringNotContainsString('private_john_doe_999@secret.test', $response['answer']);
        $this->assertStringNotContainsString('STU-SEC-999', $response['answer']);
        $this->assertStringNotContainsString('Confidential Student John Doe', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_24_chatbot_widget_root_element_has_single_root_tag_and_prevents_full_page_reload(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $test = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true);
        $html = $test->html();

        // The rendered root must start with the root div and not an unattached tag
        $this->assertStringContainsString('id="seo-chatbot-root"', $html);
        $this->assertStringContainsString('wire:submit="sendMessage"', $html);
        $this->assertStringContainsString('onsubmit="event.preventDefault();"', $html);
        $this->assertStringNotContainsString('wire:submit.prevent="sendMessage"', $html);
    }

    #[DataProvider('engineProvider')]
    public function test_25_empty_message_submission_is_safely_ignored(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $test = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('message', '   ')
            ->call('sendMessage');

        $test->assertSet('errorMessage', null);

        // Verify no message was stored in database
        $this->assertEquals(0, CmsChatMessage::where('sender_type', 'visitor')->count());
    }

    #[DataProvider('engineProvider')]
    public function test_26_valid_message_submission_persists_records_and_updates_activity_timestamp(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $sessionId = (string) Str::uuid();

        // Send first message
        $test = Livewire::withQueryParams([])
            ->test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('sessionId', $sessionId)
            ->set('message', 'What are your SEO certifications?')
            ->call('sendMessage');

        $test->assertSet('message', '');
        $test->assertSet('errorMessage', null);

        $conv = CmsChatConversation::where('session_id', $sessionId)->first();
        $this->assertNotNull($conv);
        $this->assertNotNull($conv->last_activity_at);
        $this->assertEquals(2, $conv->messages()->count()); // visitor + bot

        $firstActivity = $conv->last_activity_at;

        // Send second message in same conversation
        $test->set('message', 'How long does the course take to complete?')
            ->call('sendMessage');

        $conv->refresh();
        $this->assertEquals(4, $conv->messages()->count()); // 2 visitor + 2 bot
        $this->assertTrue($conv->last_activity_at->gte($firstActivity));
    }

    #[DataProvider('engineProvider')]
    public function test_27_duplicate_submission_handling_and_input_clearing(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $sessionId = (string) Str::uuid();

        $test = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('sessionId', $sessionId)
            ->set('message', 'Tell me about the course instructors');

        // First send
        $test->call('sendMessage');
        $this->assertEquals('', $test->get('message'));

        // Immediate second call with empty message (e.g. accidental double-click)
        $test->call('sendMessage');

        $conv = CmsChatConversation::where('session_id', $sessionId)->first();
        // Only 1 visitor message and 1 bot message should be stored
        $this->assertEquals(1, $conv->messages()->where('sender_type', 'visitor')->count());
    }

    #[DataProvider('engineProvider')]
    public function test_28_public_visitor_session_continuity_across_widget_reloads(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $sessionId = (string) Str::uuid();

        // First session interaction
        Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('sessionId', $sessionId)
            ->set('message', 'Initial inquiry about SEO audit curriculum')
            ->call('sendMessage');

        // Simulate subsequent page load with same session ID
        $reloaded = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('sessionId', $sessionId)
            ->call('loadExistingConversation');

        $conversationMessages = $reloaded->get('conversation');
        $this->assertNotEmpty($conversationMessages);
        $texts = collect($conversationMessages)->pluck('text')->implode(' ');
        $this->assertStringContainsString('Initial inquiry about SEO audit curriculum', $texts);
    }
}
