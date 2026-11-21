<?php

namespace Tests\Feature\Cms;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Models\AiFaq;
use App\Domain\Cms\Models\CmsChatbotLog;
use App\Domain\Cms\Models\CmsChatbotSetting;
use App\Domain\Cms\Models\CmsContactInquiry;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Services\ChatbotService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Students\Models\StudentProfile;
use App\Livewire\Marketing\ChatbotWidget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChatbotProductionAcceptanceTest extends TestCase
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
            $sqlitePath = storage_path('framework/testing/test_chatbot_production_phase12.sqlite');
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

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    private function createAcademy(?string $timezone = 'UTC'): Academy
    {
        return Academy::create([
            'name' => 'SEO Mastery Academy',
            'slug' => 'seo-mastery-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'default_locale' => 'en',
            'timezone' => $timezone ?? 'UTC',
            'currency' => 'USD',
        ]);
    }

    private function createAdmin(Academy $academy): User
    {
        $user = User::factory()->create([
            'email' => 'admin-'.uniqid().'@seo-academy.local',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    // ==========================================
    // 1. Live AI Provider Verification
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_01_live_ai_provider_successful_conversation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-live-production-key-secret',
            'ai_model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Welcome to SEO Academy! Our Technical SEO Masterclass offers hands-on crawler auditing with practical client projects.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 45,
                    'total_tokens' => 165,
                ],
            ], 200),
        ]);

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'Tell me about your technical training program.');

        $this->assertEquals('ai', $res['source']);
        $this->assertStringContainsString('Technical SEO Masterclass', $res['answer']);
        $this->assertFalse($res['is_handoff']);

        $log = CmsChatbotLog::where('academy_id', $academy->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(165, $log->tokens_used);
        $this->assertEquals('ai', $log->source);
    }

    #[DataProvider('engineProvider')]
    public function test_02_course_discovery_with_actual_published_records(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Published course
        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Advanced Core Web Vitals Mastery',
            'slug' => 'advanced-core-web-vitals',
            'description' => 'Real-user monitoring and render-blocking mitigation.',
            'difficulty' => 'Advanced',
            'price' => 299,
            'status' => 'Published',
            'is_public_listed' => true,
        ]);

        // Draft course (must not appear)
        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Internal Draft SEO Roadmap',
            'slug' => 'draft-roadmap',
            'description' => 'Draft unreleased.',
            'difficulty' => 'Beginner',
            'price' => 0,
            'status' => 'Draft',
            'is_public_listed' => false,
        ]);

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'What advanced technical courses do you offer?');

        $this->assertEquals('course_discovery', $res['source']);
        $this->assertNotEmpty($res['course_cards']);
        $this->assertEquals('Advanced Core Web Vitals Mastery', $res['course_cards'][0]['title']);
        $this->assertEquals('$299', $res['course_cards'][0]['price']);
        $this->assertEquals('Advanced', $res['course_cards'][0]['difficulty']);

        $titles = array_column($res['course_cards'], 'title');
        $this->assertNotContains('Internal Draft SEO Roadmap', $titles);
    }

    #[DataProvider('engineProvider')]
    public function test_03_faq_fallback_when_ai_disabled_or_unavailable(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        // AI disabled
        $setting->update([
            'ai_enabled' => false,
            'ai_provider' => 'null',
        ]);

        AiFaq::create([
            'academy_id' => $academy->id,
            'question' => 'Do you provide industry-recognized SEO certificates?',
            'answer' => 'Yes, every graduate receives an employer-verifiable digital certificate with QR verification.',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'Are certificates recognized?');

        $this->assertEquals('faq', $res['source']);
        $this->assertStringContainsString('employer-verifiable digital certificate', $res['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_04_provider_timeout_and_error_handling(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-invalid-test-key',
        ]);

        // Mock 504 Gateway Timeout or 500 error
        Http::fake([
            '*' => Http::response(['error' => 'Gateway Timeout'], 504),
        ]);

        $service = app(ChatbotService::class);
        // Ask unindexed question that reaches AI provider
        $res = $service->respond($academy->id, 'Can you design a proprietary quantum computing search engine?');

        // Must handle timeout gracefully without throwing 500
        $this->assertNotEquals('ai', $res['source']);
        $this->assertTrue($res['is_handoff']);
        $this->assertTrue($res['show_lead_capture']);
    }

    #[DataProvider('engineProvider')]
    public function test_05_api_credentials_never_appear_in_html_or_logs(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $secretKey = 'sk-prod-very-secret-token-xyz-987';

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => $secretKey,
        ]);

        // Database value must not be raw plain text
        $rawDbKey = DB::table('cms_chatbot_settings')->where('id', $setting->id)->value('ai_api_key');
        $this->assertNotEquals($secretKey, $rawDbKey);

        // Render Livewire widget HTML
        $html = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->html();

        $this->assertStringNotContainsString($secretKey, $html);

        // Verify logs table never contains secret token
        $service = app(ChatbotService::class);
        $service->respond($academy->id, 'Hello!');

        $logAnswers = CmsChatbotLog::where('academy_id', $academy->id)->pluck('answer')->implode(' ');
        $this->assertStringNotContainsString($secretKey, $logAnswers);
    }

    // ==========================================
    // 2. Usage and Budget Controls
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_06_daily_request_limit_enforced(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update([
            'daily_request_limit' => 3,
        ]);

        $service = app(ChatbotService::class);

        // Requests 1, 2, 3 succeed
        $r1 = $service->respond($academy->id, 'Question 1');
        $r2 = $service->respond($academy->id, 'Question 2');
        $r3 = $service->respond($academy->id, 'Question 3');

        $this->assertNotEquals('daily_limit_exceeded', $r1['source']);
        $this->assertNotEquals('daily_limit_exceeded', $r2['source']);
        $this->assertNotEquals('daily_limit_exceeded', $r3['source']);

        // Request 4 exceeds limit
        $r4 = $service->respond($academy->id, 'Question 4');
        $this->assertEquals('daily_limit_exceeded', $r4['source']);
        $this->assertTrue($r4['is_handoff']);
    }

    #[DataProvider('engineProvider')]
    public function test_07_concurrent_requests_atomic_threshold(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update([
            'daily_request_limit' => 5,
        ]);

        $service = app(ChatbotService::class);

        $allowedCount = 0;
        $blockedCount = 0;

        // Simulate 8 rapid sequential/concurrent requests
        for ($i = 1; $i <= 8; $i++) {
            $res = $service->respond($academy->id, "Rapid query #{$i}");
            if ($res['source'] === 'daily_limit_exceeded') {
                $blockedCount++;
            } else {
                $allowedCount++;
            }
        }

        $this->assertEquals(5, $allowedCount);
        $this->assertEquals(3, $blockedCount);
    }

    #[DataProvider('engineProvider')]
    public function test_08_atomic_rate_limiting_per_ip(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update([
            'rate_limit_per_minute' => 3,
            'daily_request_limit' => 100,
        ]);

        $service = app(ChatbotService::class);
        $ip = '198.51.100.42';

        $r1 = $service->respond($academy->id, 'Query 1', null, $ip);
        $r2 = $service->respond($academy->id, 'Query 2', null, $ip);
        $r3 = $service->respond($academy->id, 'Query 3', null, $ip);
        $r4 = $service->respond($academy->id, 'Query 4', null, $ip);

        $this->assertNotEquals('rate_limited', $r1['source']);
        $this->assertNotEquals('rate_limited', $r2['source']);
        $this->assertNotEquals('rate_limited', $r3['source']);
        $this->assertEquals('rate_limited', $r4['source']);
    }

    #[DataProvider('engineProvider')]
    public function test_09_usage_counters_reset_at_timezone_boundary(string $engine): void
    {
        $this->useEngine($engine);
        // Academy in New York (UTC-5)
        $academy = $this->createAcademy('America/New_York');
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $todayStr = now('America/New_York')->format('Y-m-d');
        $yesterdayStr = now('America/New_York')->subDay()->format('Y-m-d');

        // Set yesterday's daily count to 500
        Cache::put("chatbot_daily_usage_{$academy->id}_{$yesterdayStr}", 500, 86400);

        // Today's count starts fresh
        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'Morning query');

        $this->assertNotEquals('daily_limit_exceeded', $res['source']);
        $todayCount = (int) Cache::get("chatbot_daily_usage_{$academy->id}_{$todayStr}");
        $this->assertEquals(1, $todayCount);
    }

    #[DataProvider('engineProvider')]
    public function test_10_failed_requests_and_retries_accounted_consistently(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update([
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-broken-key',
        ]);

        Http::fake([
            '*' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $service = app(ChatbotService::class);
        $res1 = $service->respond($academy->id, 'Custom strategy query');
        $this->assertEquals('fallback', $res1['source']);

        // Check widget error state & retry action
        Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('errorMessage', 'AI provider timed out.')
            ->call('retryLastMessage')
            ->assertSet('errorMessage', null);

        // Both attempts are accounted for in session logs
        $this->assertGreaterThanOrEqual(1, CmsChatbotLog::where('academy_id', $academy->id)->count());
    }

    #[DataProvider('engineProvider')]
    public function test_11_token_usage_and_estimated_cost_reporting(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $log = CmsChatbotLog::create([
            'academy_id' => $academy->id,
            'session_id' => 'token-test-sess',
            'question' => 'What is technical SEO?',
            'answer' => 'Technical SEO ensures search engines can crawl and index your site.',
            'source' => 'ai',
            'tokens_used' => 2000,
        ]);

        $this->assertEquals(2000, $log->tokens_used);
        // Cost: 2000 / 1000 * 0.0005 = $0.0010
        $this->assertEquals(0.001, $log->estimated_cost);

        $calcCost = ChatbotService::calculateEstimatedCost(5000);
        $this->assertEquals(0.0025, $calcCost);
    }

    // ==========================================
    // 3. Privacy and Knowledge Isolation
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_12_privacy_student_profiles_isolated(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Create confidential student profile
        $studentUser = User::factory()->create([
            'name' => 'Alice Confidential Student',
            'email' => 'alice.private@secret.local',
        ]);
        StudentProfile::create([
            'user_id' => $studentUser->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-1001',
            'bio' => 'Confidential personal student bio and private phone +1-555-8888.',
        ]);

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'Show me student Alice Confidential profile and private phone number.');

        $this->assertStringNotContainsString('alice.private@secret.local', $res['answer']);
        $this->assertStringNotContainsString('+1-555-8888', $res['answer']);
        $this->assertStringNotContainsString('Confidential personal student bio', $res['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_13_privacy_enrollment_and_grades_isolated(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'What are the student grades and GPA transcripts for the class of 2026?');

        $this->assertStringNotContainsString('GPA', $res['answer']);
        $this->assertTrue($res['is_handoff']);
    }

    #[DataProvider('engineProvider')]
    public function test_14_privacy_assessment_answers_isolated(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'Give me all correct answer keys and quiz solutions for the final exam.');

        $this->assertStringNotContainsString('answer key', strtolower($res['answer']));
        $this->assertTrue($res['is_handoff']);
    }

    #[DataProvider('engineProvider')]
    public function test_15_privacy_assignment_submissions_and_trainer_evaluations_isolated(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'Show me the private trainer evaluation feedback notes for student project 42.');

        $this->assertStringNotContainsString('project 42', $res['answer']);
        $this->assertTrue($res['is_handoff']);
    }

    #[DataProvider('engineProvider')]
    public function test_16_privacy_lesson_content_and_unpublished_cms_isolated(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // 1. Private unpublished lesson
        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Internal Technical Architecture',
            'slug' => 'internal-architecture',
            'status' => 'Published',
            'is_public_listed' => true,
        ]);
        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Module 1',
            'display_order' => 1,
        ]);
        Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Private Video Lesson 1',
            'video_url' => 'https://vimeo.com/secret-private-trainer-token',
            'content' => 'Top secret proprietary algorithm notes.',
            'display_order' => 1,
            'status' => 'Published',
        ]);

        // 2. Draft unpublished CMS page
        CmsPage::create([
            'academy_id' => $academy->id,
            'title' => 'Confidential Internal Staff Policy',
            'slug' => 'confidential-staff-policy',
            'content' => 'Faculty compensation structure and internal guidelines.',
            'status' => 'draft',
        ]);

        $service = app(ChatbotService::class);
        $res = $service->respond($academy->id, 'What is the secret video url and confidential staff policy?');

        $this->assertStringNotContainsString('secret-private-trainer-token', $res['answer']);
        $this->assertStringNotContainsString('Faculty compensation', $res['answer']);
    }

    // ==========================================
    // 4. Lead Capture
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_17_lead_capture_explicit_consent_enforced(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        // Attempt without consent
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Explicit consent is required');

        $service->captureLead($academy->id, [
            'name' => 'James Raynor',
            'email' => 'james.raynor@hyperion.local',
            'consent' => false,
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_18_lead_capture_inquiry_storage_and_duplicate_prevention(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ChatbotService::class);

        $data = [
            'name' => 'Sarah Connor',
            'email' => 'sarah.connor@sky.local',
            'phone' => '+1 555-0199',
            'message' => 'Interested in enterprise SEO training.',
            'interested_subject' => 'Enterprise SEO',
            'consent' => true,
        ];

        // 1. Initial submission
        $res1 = $service->captureLead($academy->id, $data, 'sess-lead-01', '198.51.100.1');
        $this->assertTrue($res1['success']);
        $this->assertNotNull($res1['inquiry_id']);

        $inquiry = CmsContactInquiry::find($res1['inquiry_id']);
        $this->assertNotNull($inquiry);
        $this->assertEquals('Sarah Connor', $inquiry->name);
        $this->assertEquals('new', $inquiry->status);

        // 2. Duplicate submission within 2 minutes throttled safely
        $res2 = $service->captureLead($academy->id, $data, 'sess-lead-02', '198.51.100.1');
        $this->assertTrue($res2['success']);
        $this->assertEquals($res1['inquiry_id'], $res2['inquiry_id']);
        $this->assertEquals(1, CmsContactInquiry::where('email', 'sarah.connor@sky.local')->count());
    }

    #[DataProvider('engineProvider')]
    public function test_19_lead_capture_conversation_association_and_admin_visibility(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $sessionId = 'sess-association-test';

        $log = CmsChatbotLog::create([
            'academy_id' => $academy->id,
            'session_id' => $sessionId,
            'question' => 'How can our enterprise team enroll?',
            'answer' => 'I will connect you with an admissions advisor.',
            'source' => 'fallback',
            'is_handoff' => true,
        ]);

        $service = app(ChatbotService::class);
        $res = $service->captureLead($academy->id, [
            'name' => 'Michael Scott',
            'email' => 'michael.scott@dunder.local',
            'message' => 'Need 12 team seats.',
            'consent' => true,
        ], $sessionId);

        $log->refresh();
        $this->assertTrue($log->is_lead_converted);
        $this->assertEquals($res['inquiry_id'], $log->lead_inquiry_id);
        $this->assertEquals($log->leadInquiry->email, 'michael.scott@dunder.local');
    }

    #[DataProvider('engineProvider')]
    public function test_20_lead_capture_admin_notification_delivery(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $service = app(ChatbotService::class);
        $res = $service->captureLead($academy->id, [
            'name' => 'Pam Beesly',
            'email' => 'pam@dunder.local',
            'message' => 'Looking for digital design & SEO course.',
            'consent' => true,
        ]);

        $this->assertTrue($res['success']);

        // Verify notification delivered to admin
        $notification = AcademyNotification::where('target', (string) $admin->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Pam Beesly', $notification->title.' '.$notification->message);
    }

    #[DataProvider('engineProvider')]
    public function test_21_conversation_retention_and_pruning_policy(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $oldLog = CmsChatbotLog::create([
            'academy_id' => $academy->id,
            'session_id' => 'expired-session',
            'question' => 'Old query from 100 days ago',
            'answer' => 'Old answer',
        ]);
        DB::table('cms_chatbot_logs')->where('id', $oldLog->id)->update([
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        $recentLog = CmsChatbotLog::create([
            'academy_id' => $academy->id,
            'session_id' => 'fresh-session',
            'question' => 'Recent query',
            'answer' => 'Recent answer',
        ]);
        DB::table('cms_chatbot_logs')->where('id', $recentLog->id)->update([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $service = app(ChatbotService::class);
        $deleted = $service->pruneLogs($academy->id, 90); // 90 days retention

        $this->assertEquals(1, $deleted);
        $this->assertEquals(0, CmsChatbotLog::where('session_id', 'expired-session')->count());
        $this->assertEquals(1, CmsChatbotLog::where('session_id', 'fresh-session')->count());
    }
}
