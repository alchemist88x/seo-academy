<?php

namespace Tests\Feature\Cms;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBlogCategory;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsHomepageSection;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Services\CmsContentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Livewire\Admin\Website\WebsiteManager;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicWebsiteAndCmsTest extends TestCase
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
    // 1. PUBLIC HOMEPAGE ACCESS
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_01_public_homepage_access_without_redirect(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Master Technical SEO');
        $response->assertSee('Explore Courses');
    }

    // ==========================================
    // 2. HOMEPAGE SECTION MANAGEMENT
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_02_homepage_section_management(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        app(CmsContentService::class)->ensureDefaultCmsContentExist($academy);

        $this->actingAs($admin);

        // Fetch hero section
        $hero = CmsHomepageSection::where('academy_id', $academy->id)->where('section_key', 'hero')->first();
        $this->assertNotNull($hero);
        $this->assertTrue((bool) $hero->is_enabled);

        // Toggle visibility via Livewire
        Livewire::test(WebsiteManager::class)
            ->call('toggleSection', $hero->id);

        $this->assertFalse((bool) $hero->fresh()->is_enabled);

        // Public homepage should now omit hero or reflect update
        $res = $this->get('/');
        $res->assertStatus(200);
    }

    // ==========================================
    // 3. PAGE CREATION AND PUBLICATION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_03_page_creation_and_publication(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        app(CmsContentService::class)->ensureDefaultCmsContentExist($academy);

        $this->actingAs($admin);

        Livewire::test(WebsiteManager::class)
            ->set('pageTitle', 'Admissions Policy 2026')
            ->set('pageSlug', 'admissions-policy')
            ->set('pageContent', 'Official admission criteria and scholarship guidelines for applicants.')
            ->set('pageStatus', 'published')
            ->call('savePage');

        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'admissions-policy',
            'status' => 'published',
        ]);

        // Guest can visit page
        $this->post('/logout');
        $res = $this->get('/page/admissions-policy');
        $res->assertStatus(200);
        $res->assertSee('Admissions Policy 2026');
        $res->assertSee('Official admission criteria');
    }

    // ==========================================
    // 4. SERVICE MANAGEMENT
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_04_service_management(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        app(CmsContentService::class)->ensureDefaultCmsContentExist($academy);

        $this->actingAs($admin);

        Livewire::test(WebsiteManager::class)
            ->set('serviceTitle', 'Enterprise Crawl Health Audit')
            ->set('serviceSlug', 'enterprise-crawl-health')
            ->set('serviceSummary', 'Deep log file analysis and crawl budget diagnostics.')
            ->set('serviceDescription', 'Full spectrum evaluation of server logs, rendering performance, and indexation.')
            ->set('serviceBenefitsText', "Log analysis\nRendering audit\nDirect report")
            ->set('serviceDisplayOrder', 1)
            ->set('serviceIsPublished', true)
            ->call('saveService');

        $this->assertDatabaseHas('cms_services', [
            'slug' => 'enterprise-crawl-health',
            'is_published' => 1,
        ]);

        $this->post('/logout');
        $indexRes = $this->get('/services');
        $indexRes->assertStatus(200);
        $indexRes->assertSee('Enterprise Crawl Health Audit');

        $detailRes = $this->get('/services/enterprise-crawl-health');
        $detailRes->assertStatus(200);
        $detailRes->assertSee('Deep log file analysis');
        $detailRes->assertSee('Log analysis');
    }

    // ==========================================
    // 5. COURSE MARKETING VISIBILITY
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_05_course_marketing_visibility(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Secret Enterprise SEO Workshop',
            'slug' => 'secret-seo-workshop',
            'visibility' => 'public',
            'status' => 'Published',
            'is_public_listed' => false, // Initially unlisted
        ]);

        $res = $this->get('/courses');
        $res->assertStatus(200);
        $res->assertDontSee('Secret Enterprise SEO Workshop');

        // Admin toggles course to public listed
        $this->actingAs($admin);
        Livewire::test(WebsiteManager::class)
            ->call('editCourseMarketing', $course->id)
            ->set('courseIsPublicListed', true)
            ->set('courseMarketingDescription', 'Now open for public student discovery.')
            ->call('saveCourseMarketing');

        $this->assertTrue((bool) $course->fresh()->is_public_listed);

        $this->post('/logout');
        $resListed = $this->get('/courses');
        $resListed->assertStatus(200);
        $resListed->assertSee('Secret Enterprise SEO Workshop');
    }

    // ==========================================
    // 6. PRIVATE COURSE CONTENT PROTECTION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_06_private_course_content_protection(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Protected Technical SEO Track',
            'slug' => 'protected-tech-seo',
            'visibility' => 'public',
            'status' => 'Published',
            'is_public_listed' => true,
        ]);

        $module = Module::create([
            'course_id' => $course->id,
            'title' => 'Module 1: Server Directives',
            'display_order' => 1,
            'status' => 'Published',
        ]);

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson 1.1: Analyzing 301 Redirect Chains',
            'display_order' => 1,
            'status' => 'Published',
        ]);

        // Guest can view public course detail page
        $res = $this->get('/courses/protected-tech-seo');
        $res->assertStatus(200);
        $res->assertSee('Protected Technical SEO Track');
        $res->assertSee('Module 1: Server Directives');

        // But guest attempting to access classroom player or lesson preview is redirected to login
        $classroomRes = $this->get("/student/courses/{$course->id}/learn");
        $classroomRes->assertRedirect('/login');
    }

    // ==========================================
    // 7. SEO METADATA RENDERING
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_07_seo_metadata_rendering(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $res = $this->get('/');
        $res->assertStatus(200);
        $res->assertSee('<meta name="description"', false);
        $res->assertSee('<meta name="robots" content="index, follow"', false);
        $res->assertSee('<meta property="og:title"', false);
        $res->assertSee('<meta name="twitter:card"', false);
    }

    // ==========================================
    // 8. CANONICAL URLS
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_08_canonical_urls(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $res = $this->get('/about');
        $res->assertStatus(200);
        $res->assertSee('<link rel="canonical" href="'.url('/about').'">', false);
    }

    // ==========================================
    // 9. SITEMAP GENERATION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_09_sitemap_generation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Sitemap Featured Course',
            'slug' => 'sitemap-course',
            'visibility' => 'public',
            'status' => 'Published',
            'is_public_listed' => true,
        ]);

        $res = $this->get('/sitemap.xml');
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $res->assertSee('<urlset', false);
        $res->assertSee('/courses/sitemap-course', false);
        $res->assertSee('/services', false);
        $res->assertSee('/about', false);
        $res->assertSee('/contact', false);

        // Strictly verify that private LMS routes are NOT in the sitemap
        $res->assertDontSee('/admin');
        $res->assertDontSee('/student');
        $res->assertDontSee('/classroom');
        $res->assertDontSee('/trainer');
    }

    // ==========================================
    // 10. ROBOTS.TXT
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_10_robots_txt_generation(string $engine): void
    {
        $this->useEngine($engine);
        $this->createAcademy();

        $res = $this->get('/robots.txt');
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $res->assertSee('User-agent: *');
        $res->assertSee('Allow: /courses');
        $res->assertSee('Disallow: /admin/');
        $res->assertSee('Disallow: /student/');
        $res->assertSee('Disallow: /classroom/');
        $res->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    // ==========================================
    // 11. STRUCTURED DATA OUTPUT (JSON-LD)
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_11_structured_data_output(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $res = $this->get('/');
        $res->assertStatus(200);
        $res->assertSee('<script type="application/ld+json">', false);
        $res->assertSee('"@type": "EducationalOrganization"', false);
    }

    // ==========================================
    // 12. BLOG PUBLICATION AND FILTERS
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_12_blog_publication_and_filters(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $category = CmsBlogCategory::create([
            'academy_id' => $academy->id,
            'name' => 'Technical Crawling',
            'slug' => 'technical-crawling',
        ]);

        CmsBlogPost::create([
            'academy_id' => $academy->id,
            'category_id' => $category->id,
            'author_id' => $admin->id,
            'title' => 'Mastering Googlebot Rendering and DOM Hydration',
            'slug' => 'googlebot-rendering-hydration',
            'excerpt' => 'An in-depth look at two-wave indexing and headless browser timeouts.',
            'content' => 'Full technical guide explaining how modern search engines handle JavaScript bundles.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $res = $this->get('/blog');
        $res->assertStatus(200);
        $res->assertSee('Mastering Googlebot Rendering');

        // Search test
        $searchRes = $this->get('/blog?search=Hydration');
        $searchRes->assertStatus(200);
        $searchRes->assertSee('Mastering Googlebot Rendering');

        $emptySearch = $this->get('/blog?search=NonExistentKeywordXYZ');
        $emptySearch->assertStatus(200);
        $emptySearch->assertDontSee('Mastering Googlebot Rendering');
    }

    // ==========================================
    // 13. NAVIGATION MANAGEMENT & SAFE URLS
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_13_navigation_management_and_safe_urls(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        app(CmsContentService::class)->ensureDefaultCmsContentExist($academy);

        $this->actingAs($admin);

        // Test safe URL is allowed
        Livewire::test(WebsiteManager::class)
            ->set('navTitle', 'Alumni Network')
            ->set('navUrl', '/alumni')
            ->set('navTarget', '_self')
            ->set('navMenuLocation', 'header')
            ->set('navDisplayOrder', 10)
            ->call('saveNavItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cms_navigation_items', [
            'label' => 'Alumni Network',
            'url' => '/alumni',
        ]);

        // Test dangerous javascript: URL is rejected
        Livewire::test(WebsiteManager::class)
            ->set('navTitle', 'Malicious Link')
            ->set('navUrl', 'javascript:alert(1)')
            ->set('navMenuLocation', 'header')
            ->call('saveNavItem')
            ->assertHasErrors(['navUrl']);
    }

    // ==========================================
    // 14. MEDIA UPLOAD SECURITY
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_14_media_upload_security(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        app(CmsContentService::class)->ensureDefaultCmsContentExist($academy);

        $this->actingAs($admin);
        Storage::fake('public');

        $file = UploadedFile::fake()->image('campus-banner.jpg', 800, 600);

        Livewire::test(WebsiteManager::class)
            ->set('mediaUpload', $file)
            ->set('mediaTitle', 'Campus SEO Lab')
            ->set('mediaAltText', 'Students in campus SEO laboratory')
            ->call('uploadMediaAsset')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cms_media_assets', [
            'title' => 'Campus SEO Lab',
            'disk' => 'public',
        ]);
    }

    // ==========================================
    // 15. CONTACT FORM VALIDATION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_15_contact_form_validation(string $engine): void
    {
        $this->useEngine($engine);
        $this->createAcademy();

        // Submitting with missing required fields fails validation
        $response = $this->post('/contact', []);
        $response->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        // Valid submission succeeds and redirects with success flash
        $validResponse = $this->post('/contact', [
            'name' => 'Alice Student',
            'email' => 'alice@example.com',
            'subject' => 'Course Inquiry',
            'message' => 'I would like to inquire about enrolling in the technical SEO track.',
        ]);
        $validResponse->assertSessionHasNoErrors();
        $validResponse->assertRedirect();

        $this->assertDatabaseHas('cms_contact_inquiries', [
            'email' => 'alice@example.com',
            'subject' => 'Course Inquiry',
        ]);
    }

    // ==========================================
    // 16. CONTACT FORM RATE LIMITING
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_16_contact_form_rate_limiting(string $engine): void
    {
        $this->useEngine($engine);
        $this->createAcademy();

        $data = [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam Message',
            'message' => 'Spam content text here for testing rate limiting thresholds.',
        ];

        // 5 requests allowed
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $data);
        }

        // 6th request within 1 minute must return HTTP 429
        $response = $this->post('/contact', $data);
        $response->assertStatus(429);
    }

    // ==========================================
    // 17. CMS ADMINISTRATOR AUTHORIZATION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_17_cms_administrator_authorization(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createStudent($academy);

        // Guest attempting to access /admin/website is redirected to login
        $guestRes = $this->get('/admin/website');
        $guestRes->assertRedirect('/login');

        // Student attempting to access /admin/website is rejected (403 Forbidden)
        $this->actingAs($student);
        $studentRes = $this->get('/admin/website');
        $studentRes->assertStatus(403);
    }

    // ==========================================
    // 18. DRAFT VISIBILITY PROTECTION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_18_draft_visibility_protection(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Create draft page
        CmsPage::create([
            'academy_id' => $academy->id,
            'title' => 'Unpublished Internal Guide',
            'slug' => 'unpublished-guide',
            'content' => 'Internal faculty instructions.',
            'status' => 'draft',
        ]);

        // Guest requesting draft page gets 404
        $res = $this->get('/page/unpublished-guide');
        $res->assertStatus(404);
    }

    // ==========================================
    // 19. REDIRECT HANDLING (301 ON SLUG CHANGE)
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_19_redirect_handling_on_slug_change(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createAdmin($academy);

        $page = CmsPage::create([
            'academy_id' => $academy->id,
            'title' => 'Old Academic Policy',
            'slug' => 'old-policy',
            'content' => 'Policy details.',
            'status' => 'published',
        ]);

        $this->actingAs($admin);

        // Update page slug from old-policy to new-policy
        Livewire::test(WebsiteManager::class)
            ->call('openPageModal', $page->id)
            ->set('pageSlug', 'new-policy')
            ->call('savePage');

        $this->assertDatabaseHas('cms_redirects', [
            'source_path' => '/page/old-policy',
            'target_path' => '/page/new-policy',
            'status_code' => 301,
        ]);

        // Guest visiting old slug receives 301 redirect to new slug
        $this->post('/logout');
        $res = $this->get('/page/old-policy');
        $res->assertStatus(301);
        $res->assertRedirect('/page/new-policy');
    }

    // ==========================================
    // 20. PUBLIC/PRIVATE ROUTE SEPARATION
    // ==========================================
    #[DataProvider('engineProvider')]
    public function test_20_public_private_route_separation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Public routes accessible without auth
        $this->get('/')->assertStatus(200);
        $this->get('/courses')->assertStatus(200);
        $this->get('/about')->assertStatus(200);
        $this->get('/contact')->assertStatus(200);
        $this->get('/services')->assertStatus(200);
        $this->get('/blog')->assertStatus(200);
        $this->get('/sitemap.xml')->assertStatus(200);
        $this->get('/robots.txt')->assertStatus(200);

        // Private routes strictly redirect guests to /login
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin/students')->assertRedirect('/login');
        $this->get('/student/courses')->assertRedirect('/login');
        $this->get('/trainer/courses')->assertRedirect('/login');
    }
}
