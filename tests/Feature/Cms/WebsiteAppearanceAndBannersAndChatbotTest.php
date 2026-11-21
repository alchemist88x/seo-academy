<?php

namespace Tests\Feature\Cms;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Models\AiFaq;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsChatbotLog;
use App\Domain\Cms\Models\CmsChatbotSetting;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Cms\Models\CmsWebsiteTheme;
use App\Domain\Cms\Services\BannerService;
use App\Domain\Cms\Services\ChatbotService;
use App\Domain\Cms\Services\ThemeAppearanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\Website\WebsiteAppearanceCustomizer;
use App\Livewire\Admin\Website\WebsiteChatbotManager;
use App\Livewire\Marketing\ChatbotWidget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteAppearanceAndBannersAndChatbotTest extends TestCase
{
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
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
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
                        DB::connection('mysql')->table($table)->truncate();
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        $this->seed(RoleSeeder::class);
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

    private function createAdmin(Academy $academy): User
    {
        $user = User::factory()->create([
            'email' => 'admin_'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $user->assignRole('admin');
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    private function createStudent(Academy $academy): User
    {
        $user = User::factory()->create([
            'email' => 'student_'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $user->assignRole('student');
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    // ==========================================
    // 1-7: Website Appearance & Color Tokens
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_01_theme_defaults_and_initialization(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ThemeAppearanceService::class);
        $draft = $service->getOrCreateDraft($academy->id);

        $this->assertNotNull($draft);
        $this->assertTrue($draft->is_draft);
        $this->assertFalse($draft->is_published);
        $this->assertEquals('#2563eb', $draft->settings['primary_color']);
        $this->assertStringContainsString('--web-color-primary: #2563eb;', $draft->css_cache);
    }

    #[DataProvider('engineProvider')]
    public function test_02_draft_vs_published_isolation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ThemeAppearanceService::class);

        // 1. Initial publish
        $published = $service->publishTheme($academy->id, ['primary_color' => '#112233']);
        $this->assertEquals('#112233', $published->settings['primary_color']);

        // 2. Update draft
        $draft = $service->saveDraft($academy->id, ['primary_color' => '#ff0000']);
        $this->assertEquals('#ff0000', $draft->settings['primary_color']);

        // 3. Published theme must remain isolated until published
        $currentPublished = $service->getPublishedTheme($academy->id);
        $this->assertEquals('#112233', $currentPublished->settings['primary_color']);

        // 4. Publish theme
        $newPublished = $service->publishTheme($academy->id);
        $this->assertEquals('#ff0000', $newPublished->settings['primary_color']);
    }

    #[DataProvider('engineProvider')]
    public function test_03_theme_persistence_and_update(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        app(TenantContext::class)->setAcademy($academy);

        $this->actingAs($admin);

        Livewire::test(WebsiteAppearanceCustomizer::class)
            ->set('settings.primary_color', '#059669')
            ->set('settings.btn_border_radius', '16px')
            ->call('saveDraft')
            ->assertSet('feedbackType', 'success');

        $draft = app(ThemeAppearanceService::class)->getDraftTheme($academy->id);
        $this->assertEquals('#059669', $draft->settings['primary_color']);
        $this->assertEquals('16px', $draft->settings['btn_border_radius']);
    }

    #[DataProvider('engineProvider')]
    public function test_04_color_validation_and_css_variable_generation(string $engine): void
    {
        $this->useEngine($engine);
        $settings = CmsWebsiteTheme::getDefaultSettings();
        $settings['primary_color'] = '#10b981';
        $settings['secondary_color'] = '#0f172a';
        $settings['btn_border_radius'] = '9999px';

        $css = CmsWebsiteTheme::compileCss($settings);

        $this->assertStringContainsString('--web-color-primary: #10b981;', $css);
        $this->assertStringContainsString('--web-color-secondary: #0f172a;', $css);
        $this->assertStringContainsString('--web-btn-radius: 9999px;', $css);
    }

    #[DataProvider('engineProvider')]
    public function test_05_safe_css_compilation_and_injection_prevention(string $engine): void
    {
        $this->useEngine($engine);
        $settings = CmsWebsiteTheme::getDefaultSettings();

        // Attempt CSS injection attack in color
        $settings['primary_color'] = 'blue; } body { background: red; }';
        // Attempt unauthorized font
        $settings['font_family_body'] = 'EvilFont", sans-serif; } * { color: red !important; }';

        $css = CmsWebsiteTheme::compileCss($settings);

        // Sanitize should fallback safely to default hex
        $this->assertStringNotContainsString('background: red', $css);
        $this->assertStringNotContainsString('EvilFont', $css);
        $this->assertStringContainsString('--web-color-primary: #2563eb;', $css);
    }

    #[DataProvider('engineProvider')]
    public function test_06_reset_theme_to_defaults(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(ThemeAppearanceService::class);

        // Save customized values
        $service->publishTheme($academy->id, ['primary_color' => '#123456']);
        $this->assertEquals('#123456', $service->getPublishedTheme($academy->id)->settings['primary_color']);

        // Reset
        $service->resetToDefaults($academy->id);
        $resetTheme = $service->getPublishedTheme($academy->id);

        $this->assertEquals('#2563eb', $resetTheme->settings['primary_color']);
        $this->assertEquals('12px', $resetTheme->settings['btn_border_radius']);
    }

    #[DataProvider('engineProvider')]
    public function test_07_appearance_admin_authorization(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $student = $this->createStudent($academy);

        // Student forbidden
        $this->actingAs($student)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/website/appearance')
            ->assertForbidden();

        // Admin allowed
        $this->actingAs($admin)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/website/appearance')
            ->assertOk();
    }

    // ==========================================
    // 8-14: Promotional Banners & Hero Images
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_08_banner_creation_and_placement_scoping(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        $bannerHero = $service->createBanner($academy->id, [
            'name' => 'Main Hero',
            'placement' => 'homepage_hero',
            'heading' => 'Become an SEO Professional',
            'is_active' => true,
        ]);

        $bannerCourses = $service->createBanner($academy->id, [
            'name' => 'Catalogue Promo',
            'placement' => 'courses_promo',
            'heading' => '30% Discount on Technical Track',
            'is_active' => true,
        ]);

        $heroList = $service->getActiveBanners($academy->id, 'homepage_hero');
        $this->assertCount(1, $heroList);
        $this->assertEquals($bannerHero->id, $heroList->first()->id);

        $coursesList = $service->getActiveBanners($academy->id, 'courses_promo');
        $this->assertCount(1, $coursesList);
        $this->assertEquals($bannerCourses->id, $coursesList->first()->id);
    }

    #[DataProvider('engineProvider')]
    public function test_09_banner_desktop_and_mobile_image_persistence(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        $banner = $service->createBanner($academy->id, [
            'name' => 'Responsive Banner',
            'placement' => 'homepage_hero',
            'heading' => 'Responsive Graphics',
            'desktop_image_path' => '/storage/banners/desktop_sample.jpg',
            'mobile_image_path' => '/storage/banners/mobile_sample.jpg',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('cms_banners', [
            'id' => $banner->id,
            'desktop_image_path' => '/storage/banners/desktop_sample.jpg',
            'mobile_image_path' => '/storage/banners/mobile_sample.jpg',
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_10_banner_display_order_sorting(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        $b2 = $service->createBanner($academy->id, [
            'name' => 'Banner Second',
            'placement' => 'homepage_hero',
            'heading' => 'Second',
            'display_order' => 10,
            'is_active' => true,
        ]);

        $b1 = $service->createBanner($academy->id, [
            'name' => 'Banner First',
            'placement' => 'homepage_hero',
            'heading' => 'First',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $list = $service->getActiveBanners($academy->id, 'homepage_hero');
        $this->assertEquals($b1->id, $list[0]->id);
        $this->assertEquals($b2->id, $list[1]->id);
    }

    #[DataProvider('engineProvider')]
    public function test_11_banner_active_vs_inactive_scoping(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        $service->createBanner($academy->id, [
            'name' => 'Inactive Banner',
            'placement' => 'homepage_hero',
            'heading' => 'Should Not Show',
            'is_active' => false,
        ]);

        $activeList = $service->getActiveBanners($academy->id, 'homepage_hero');
        $this->assertCount(0, $activeList);
    }

    #[DataProvider('engineProvider')]
    public function test_12_banner_scheduling_visibility(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        // 1. Future banner (starts tomorrow) -> excluded
        $service->createBanner($academy->id, [
            'name' => 'Future Banner',
            'placement' => 'homepage_hero',
            'heading' => 'Future',
            'starts_at' => now()->addDay(),
            'is_active' => true,
        ]);

        // 2. Expired banner (ended yesterday) -> excluded
        $service->createBanner($academy->id, [
            'name' => 'Expired Banner',
            'placement' => 'homepage_hero',
            'heading' => 'Expired',
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // 3. Current banner (started yesterday, ends tomorrow) -> included
        $current = $service->createBanner($academy->id, [
            'name' => 'Current Active Banner',
            'placement' => 'homepage_hero',
            'heading' => 'Current',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $list = $service->getActiveBanners($academy->id, 'homepage_hero');
        $this->assertCount(1, $list);
        $this->assertEquals($current->id, $list->first()->id);
    }

    #[DataProvider('engineProvider')]
    public function test_13_banner_unsafe_cta_url_rejection(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        // Service throws InvalidArgumentException for javascript:
        $this->expectException(InvalidArgumentException::class);
        $service->createBanner($academy->id, [
            'name' => 'Malicious CTA',
            'placement' => 'homepage_hero',
            'heading' => 'Hack',
            'primary_cta_url' => 'javascript:alert(1)',
            'is_active' => true,
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_14_banner_duplication_as_inactive_copy(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $service = app(BannerService::class);

        $original = $service->createBanner($academy->id, [
            'name' => 'Spring Enrollment',
            'placement' => 'homepage_hero',
            'heading' => 'Join the Spring Class',
            'is_active' => true,
        ]);

        $replica = $service->duplicateBanner($original);

        $this->assertNotEquals($original->id, $replica->id);
        $this->assertEquals('Spring Enrollment (Copy)', $replica->name);
        $this->assertFalse($replica->is_active);
    }

    // ==========================================
    // 15-26: Chatbot & Visitor Assistance
    // ==========================================

    #[DataProvider('engineProvider')]
    public function test_15_chatbot_enable_disable_toggle(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $setting->update(['is_enabled' => false]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'What courses do you teach?');

        $this->assertEquals('offline', $response['source']);
        $this->assertTrue($response['is_handoff']);
        $this->assertStringContainsString('offline', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_16_chatbot_faq_management_and_scoping(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $faq = AiFaq::create([
            'academy_id' => $academy->id,
            'question' => 'Are your SEO certificates accredited?',
            'answer' => 'Yes, every graduate receives an employer-verifiable digital credential.',
            'category' => 'Certificates',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('ai_faqs', [
            'id' => $faq->id,
            'academy_id' => $academy->id,
            'is_active' => true,
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_17_chatbot_faq_matching(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        AiFaq::create([
            'academy_id' => $academy->id,
            'question' => 'What is the refund policy?',
            'answer' => 'We offer a full 14-day money back guarantee for all enrolled students.',
            'category' => 'Billing',
            'is_active' => true,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Can you tell me about the refund policy?');

        $this->assertEquals('faq', $response['source']);
        $this->assertFalse($response['is_handoff']);
        $this->assertStringContainsString('14-day money back guarantee', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_18_chatbot_course_discovery(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-mastery',
            'description' => 'Master site speed, crawl budgets, and robots indexability.',
            'status' => 'Published',
            'is_public_listed' => true,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'What technical SEO courses do you offer?');

        $this->assertEquals('course_discovery', $response['source']);
        $this->assertFalse($response['is_handoff']);
        $this->assertStringContainsString('Technical SEO Mastery', $response['answer']);
        $this->assertEquals(url('/courses/technical-seo-mastery'), $response['links'][0]['url']);
    }

    #[DataProvider('engineProvider')]
    public function test_19_chatbot_service_discovery(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        CmsService::create([
            'academy_id' => $academy->id,
            'title' => 'Enterprise Site Audits',
            'slug' => 'enterprise-site-audits',
            'summary' => 'Comprehensive algorithmic log crawl and schema analysis.',
            'is_published' => true,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Do you provide website audit consulting services?');

        $this->assertEquals('service_discovery', $response['source']);
        $this->assertFalse($response['is_handoff']);
        $this->assertStringContainsString('Enterprise Site Audits', $response['answer']);
        $this->assertEquals(url('/services/enterprise-site-audits'), $response['links'][0]['url']);
    }

    #[DataProvider('engineProvider')]
    public function test_20_chatbot_blog_discovery(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        CmsBlogPost::create([
            'academy_id' => $academy->id,
            'title' => 'Understanding Core Web Vitals in 2026',
            'slug' => 'core-web-vitals-guide-2026',
            'excerpt' => 'A comprehensive guide to FID and INP optimizations.',
            'content' => 'Full article content here...',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Tell me about Core Web Vitals');

        $this->assertEquals('blog_discovery', $response['source']);
        $this->assertFalse($response['is_handoff']);
        $this->assertStringContainsString('Core Web Vitals', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_21_chatbot_unknown_fallback_to_admissions_handoff(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Where is the nearest spaceship repair depot?');

        $this->assertEquals('fallback', $response['source']);
        $this->assertTrue($response['is_handoff']);
        $this->assertStringContainsString('admissions', strtolower($response['answer']));
        $this->assertEquals(url('/contact'), $response['links'][0]['url']);
    }

    #[DataProvider('engineProvider')]
    public function test_22_chatbot_suggested_questions_interaction(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        app(TenantContext::class)->setAcademy($academy);

        $this->actingAs($admin);

        Livewire::test(WebsiteChatbotManager::class)
            ->set('newSuggestedQuestion', 'How do practical client audits work?')
            ->call('addSuggestedQuestion')
            ->assertSet('feedbackType', 'success');

        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);
        $this->assertContains('How do practical client audits work?', $setting->suggested_questions);
    }

    #[DataProvider('engineProvider')]
    public function test_23_chatbot_interaction_logging(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $service = app(ChatbotService::class);
        $service->respond($academy->id, 'What is the syllabus?', 'test-session-123', '127.0.0.1');

        $this->assertDatabaseHas('cms_chatbot_logs', [
            'academy_id' => $academy->id,
            'session_id' => 'test-session-123',
            'question' => 'What is the syllabus?',
        ]);

        $log = CmsChatbotLog::where('session_id', 'test-session-123')->first();
        $this->assertNotNull($log->ip_hash);
        $this->assertNotEquals('127.0.0.1', $log->ip_hash); // Hashed for privacy
    }

    #[DataProvider('engineProvider')]
    public function test_24_chatbot_data_isolation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Draft / private course should never appear
        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Confidential Internal Staff Training',
            'slug' => 'confidential-internal-training',
            'description' => 'Secret administrative lesson details.',
            'status' => 'Draft',
            'is_public_listed' => false,
        ]);

        $service = app(ChatbotService::class);
        $response = $service->respond($academy->id, 'Confidential Internal Staff Training');

        // Should not discover the draft course
        $this->assertNotEquals('course_discovery', $response['source']);
        $this->assertStringNotContainsString('Confidential Internal Staff Training', $response['answer']);
    }

    #[DataProvider('engineProvider')]
    public function test_25_chatbot_ai_credentials_protection(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $setting = CmsChatbotSetting::getOrCreateForAcademy($academy->id);

        $secretKey = 'sk-proj-super-secret-key-12345';
        $setting->update([
            'ai_enabled' => true,
            'ai_api_key' => $secretKey,
        ]);

        // 1. Raw DB value must NOT be plaintext (encrypted attribute cast)
        $raw = DB::table('cms_chatbot_settings')->where('id', $setting->id)->value('ai_api_key');
        $this->assertNotEquals($secretKey, $raw);

        // 2. ChatbotWidget Livewire component never holds or exposes the secret key
        $widget = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id]);
        $widget->assertDontSee($secretKey);
    }

    #[DataProvider('engineProvider')]
    public function test_26_chatbot_and_banners_admin_authorization(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);
        $student = $this->createStudent($academy);

        // Student forbidden on Banners & Chatbot
        $this->actingAs($student)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/website/banners')
            ->assertForbidden();

        $this->actingAs($student)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/website/chatbot')
            ->assertForbidden();

        // Admin allowed
        $this->actingAs($admin)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/website/banners')
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['current_academy_id' => $academy->id])
            ->get('/admin/website/chatbot')
            ->assertOk();
    }
}
