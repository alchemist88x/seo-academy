<?php

namespace Tests\Feature\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Platform\Models\Setting;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\AcademySettings;
use App\Livewire\Admin\Commerce\CourseBundlesManager;
use App\Livewire\Admin\CoursesIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GlobalAcademyCurrencyTest extends TestCase
{
    private static bool $sqliteMigrated = false;

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
            $sqlitePath = storage_path('framework/testing/test_commerce_currency.sqlite');
            if (! file_exists(dirname($sqlitePath))) {
                mkdir(dirname($sqlitePath), 0755, true);
            }

            DB::purge('sqlite');
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $sqlitePath,
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::reconnect('sqlite');
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
            DB::purge('mysql');
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 3306,
                'database.connections.mysql.database' => 'seo_academy_test',
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
            ]);
            DB::reconnect('mysql');
            DB::setDefaultConnection('mysql');

            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }

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

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => $engine]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createAcademy(): Academy
    {
        $academy = Academy::create([
            'name' => 'SEO Currency Test Academy',
            'slug' => 'currency-test-'.uniqid(),
            'subdomain' => 'curr-'.uniqid(),
            'status' => 'active',
        ]);
        app(TenantContext::class)->setAcademy($academy);

        return $academy;
    }

    private function createUser(Academy $academy, string $role = 'admin', string $email = 'admin@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Test '.ucfirst($role),
        ]);
        $user->assignRole($role);

        AcademyUser::create([
            'academy_id' => $academy->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    #[DataProvider('engineProvider')]
    public function test_default_currency_defaults_to_inr_when_no_configuration_exists(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $pricingService = app(CurrencyAndPricingService::class);

        // 1. Returns INR
        $this->assertSame('INR', $pricingService->getAcademyCurrency($academy));

        // 2. format() helper and service
        $formattedService = $pricingService->format(4999);
        $this->assertSame('₹4,999.00', $formattedService);

        $formattedHelper = format_currency(4999);
        $this->assertSame('₹4,999.00', $formattedHelper);

        // 3. Blade directive @currency
        $renderedBlade = Blade::render('@currency(4999)');
        $this->assertSame('₹4,999.00', $renderedBlade);
    }

    #[DataProvider('engineProvider')]
    public function test_admin_can_update_default_academy_currency_in_academy_settings(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin');

        $this->actingAs($admin);

        Livewire::test(AcademySettings::class)
            ->set('activeTab', 'currency')
            ->set('default_currency', 'AED')
            ->set('enable_regional_pricing', false)
            ->set('enable_currency_switcher', true)
            ->set('enabled_currencies', ['AED', 'INR', 'USD'])
            ->call('saveCurrencySettings')
            ->assertDispatched('toast');

        // Check DB persistence
        $settingValue = Setting::where('academy_id', $academy->id)
            ->where('group', 'currency')
            ->where('key', 'default_currency')
            ->value('value');
        $this->assertSame('AED', $settingValue);

        $freshAcademy = $academy->fresh();
        $this->assertSame('AED', $freshAcademy->setting('default_currency'));
        $this->assertFalse((bool) $freshAcademy->setting('enable_regional_pricing'));

        // Service reflects AED
        $pricingService = app(CurrencyAndPricingService::class);
        $this->assertSame('AED', $pricingService->getAcademyCurrency($freshAcademy));
        $this->assertSame('AED 500.00', $pricingService->format(500));
    }

    #[DataProvider('engineProvider')]
    public function test_non_admin_cannot_update_academy_currency(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUser($academy, 'student');

        $this->actingAs($student);

        Livewire::test(AcademySettings::class)
            ->assertForbidden();
    }

    #[DataProvider('engineProvider')]
    public function test_validation_rejects_unsupported_currency_codes(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin');

        $this->actingAs($admin);

        Livewire::test(AcademySettings::class)
            ->set('activeTab', 'currency')
            ->set('default_currency', 'XYZ_FAKE')
            ->call('saveCurrencySettings')
            ->assertHasErrors(['default_currency']);
    }

    #[DataProvider('engineProvider')]
    public function test_new_course_and_bundle_creation_preselects_configured_academy_currency(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin');
        $this->actingAs($admin);

        // Configure default currency to EUR
        Setting::updateOrCreate(
            ['academy_id' => $academy->id, 'group' => 'currency', 'key' => 'default_currency'],
            ['value' => 'EUR', 'type' => 'string', 'is_public' => true]
        );
        $academy->update(['settings' => ['default_currency' => 'EUR']]);

        // 1. CourseManagementService defaults to EUR
        $courseService = app(CourseManagementService::class);
        $course = $courseService->create($academy, [
            'title' => 'Technical SEO Advanced',
            'slug' => 'tech-seo-advanced-'.uniqid(),
            'price' => 199.00,
            'is_free' => false,
        ], null, $admin);
        $this->assertSame('EUR', $course->currency);

        // 2. CoursesIndex Livewire form preselects EUR
        Livewire::test(CoursesIndex::class)
            ->call('openCreateModal')
            ->assertSet('currency', 'EUR');

        // 3. CourseBundlesManager Livewire form preselects EUR
        Livewire::test(CourseBundlesManager::class)
            ->call('openCreateModal')
            ->assertSet('currency', 'EUR');
    }

    #[DataProvider('engineProvider')]
    public function test_regional_detection_and_currency_switcher_precedence(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Default currency = INR
        Setting::updateOrCreate(
            ['academy_id' => $academy->id, 'group' => 'currency', 'key' => 'default_currency'],
            ['value' => 'INR', 'type' => 'string', 'is_public' => true]
        );
        Setting::updateOrCreate(
            ['academy_id' => $academy->id, 'group' => 'currency', 'key' => 'enable_regional_pricing'],
            ['value' => 'true', 'type' => 'boolean', 'is_public' => true]
        );
        Setting::updateOrCreate(
            ['academy_id' => $academy->id, 'group' => 'currency', 'key' => 'enable_currency_switcher'],
            ['value' => 'true', 'type' => 'boolean', 'is_public' => true]
        );

        $pricingService = app(CurrencyAndPricingService::class);

        // Case A: No visitor input -> returns academy default (INR)
        $this->assertSame('INR', $pricingService->resolveCurrency());

        // Case B: GeoIP header CF-IPCountry: AE -> returns AED
        $reqAE = Request::create('/', 'GET');
        $reqAE->headers->set('CF-IPCountry', 'AE');
        $this->assertSame('AED', $pricingService->resolveCurrency($reqAE));

        // Case C: GeoIP disabled -> ignores CF-IPCountry and returns academy default (INR)
        Setting::where('academy_id', $academy->id)->where('key', 'enable_regional_pricing')->update(['value' => 'false']);
        $academy->update(['settings' => array_merge($academy->settings ?? [], ['enable_regional_pricing' => false])]);
        $this->assertSame('INR', $pricingService->resolveCurrency($reqAE));

        // Case D: Explicit visitor currency parameter ?currency=GBP takes highest precedence
        $reqGBP = Request::create('/?currency=GBP', 'GET', ['currency' => 'GBP']);
        $this->assertSame('GBP', $pricingService->resolveCurrency($reqGBP));

        // Case E: Currency switcher disabled -> ignores manual parameter and returns academy default
        Setting::where('academy_id', $academy->id)->where('key', 'enable_currency_switcher')->update(['value' => 'false']);
        $academy->update(['settings' => array_merge($academy->settings ?? [], ['enable_currency_switcher' => false])]);
        $this->assertSame('INR', $pricingService->resolveCurrency($reqGBP));
    }

    #[DataProvider('engineProvider')]
    public function test_historical_orders_and_transactions_preserve_their_original_currency(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin');
        $student = $this->createUser($academy, 'student', 'student2@example.com');

        $this->actingAs($admin);

        // 1. Create a course in USD
        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Legacy SEO Mastery',
            'slug' => 'legacy-seo-'.uniqid(),
            'price' => 250.00,
            'currency' => 'USD',
            'is_free' => false,
            'status' => 'Published',
        ]);

        // 2. Order created in USD
        $orderService = app(OrderAndEnrollmentService::class);
        $order = $orderService->createOrder(
            user: $student,
            item: $course,
            currency: 'USD'
        );

        $this->assertSame('USD', $order->currency);
        $this->assertEquals(250.00, $order->total_amount);

        // 3. Administrator reconfigures academy currency to INR
        Setting::updateOrCreate(
            ['academy_id' => $academy->id, 'group' => 'currency', 'key' => 'default_currency'],
            ['value' => 'INR', 'type' => 'string', 'is_public' => true]
        );
        $academy->update(['settings' => ['default_currency' => 'INR']]);

        // 4. Historical order still preserves USD
        $freshOrder = CommerceOrder::find($order->id);
        $this->assertSame('USD', $freshOrder->currency);

        $pricingService = app(CurrencyAndPricingService::class);
        // Formatter preserves order currency
        $this->assertSame('$250.00', $pricingService->format($freshOrder->total_amount, $freshOrder->currency));
    }

    #[DataProvider('engineProvider')]
    public function test_currency_formatting_supports_iso_metadata_and_decimal_precision(string $engine): void
    {
        $this->useEngine($engine);
        $this->createAcademy();

        $pricingService = app(CurrencyAndPricingService::class);

        // Test each supported currency symbol and precision
        $this->assertSame('₹1,500.50', $pricingService->format(1500.5, 'INR'));
        $this->assertSame('$1,500.50', $pricingService->format(1500.5, 'USD'));
        $this->assertSame('€1,500.50', $pricingService->format(1500.5, 'EUR'));
        $this->assertSame('£1,500.50', $pricingService->format(1500.5, 'GBP'));
        $this->assertSame('AED 1,500.50', $pricingService->format(1500.5, 'AED'));
        $this->assertSame('A$1,500.50', $pricingService->format(1500.5, 'AUD'));
        $this->assertSame('C$1,500.50', $pricingService->format(1500.5, 'CAD'));
        $this->assertSame('S$1,500.50', $pricingService->format(1500.5, 'SGD'));
        $this->assertSame('SAR 1,500.50', $pricingService->format(1500.5, 'SAR'));

        // Test includeCode flag
        $this->assertSame('₹1,500.50 INR', $pricingService->format(1500.5, 'INR', true));
        $this->assertSame('AED 1,500.50 AED', $pricingService->format(1500.5, 'AED', true));
    }
}
