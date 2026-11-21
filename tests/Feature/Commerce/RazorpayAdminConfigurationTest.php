<?php

namespace Tests\Feature\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\Setting;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\Commerce\PaymentSettingsManager;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RazorpayAdminConfigurationTest extends TestCase
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
            $sqlitePath = storage_path('framework/testing/test_commerce_phase12_6.sqlite');
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
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-'.uniqid(),
            'subdomain' => 'seo-test-'.uniqid(),
            'status' => 'active',
        ]);
        app(TenantContext::class)->setAcademy($academy);

        return $academy;
    }

    private function createUser(Academy $academy, string $role = 'student', string $email = 'student@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Test '.ucfirst($role),
        ]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('engineProvider')]
    public function test_admin_can_access_and_non_admin_is_forbidden(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');
        $student = $this->createUser($academy, 'student', 'student@example.com');

        // Student cannot access
        Livewire::actingAs($student)
            ->test(PaymentSettingsManager::class)
            ->assertForbidden();

        // Admin can access
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->assertOk()
            ->assertSee('Razorpay Gateway Settings')
            ->assertSee('Test Mode (Sandbox)');
    }

    #[DataProvider('engineProvider')]
    public function test_admin_can_configure_and_encrypt_test_mode_settings(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');

        $plainSecret = 'super_secret_test_key_12345';
        $plainWebhook = 'webhook_secret_test_token_98765';

        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('is_enabled', true)
            ->set('mode', 'test')
            ->set('key_id', 'rzp_test_MerchantTest001')
            ->set('key_secret', $plainSecret)
            ->set('webhook_secret', $plainWebhook)
            ->set('currency', 'INR')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertSee('saved and encrypted successfully');

        // Verify settings in database
        $keySecretRow = Setting::where('academy_id', $academy->id)
            ->where('group', 'razorpay')
            ->where('key', 'key_secret')
            ->first();

        $this->assertNotNull($keySecretRow);
        $this->assertEquals('encrypted', $keySecretRow->type);
        // Stored value must NOT be plain text
        $this->assertNotEquals($plainSecret, $keySecretRow->value);
        // Must be decryptable to exact original plain text
        $this->assertEquals($plainSecret, Crypt::decryptString($keySecretRow->value));

        $webhookRow = Setting::where('academy_id', $academy->id)
            ->where('group', 'razorpay')
            ->where('key', 'webhook_secret')
            ->first();

        $this->assertNotNull($webhookRow);
        $this->assertEquals('encrypted', $webhookRow->type);
        $this->assertNotEquals($plainWebhook, $webhookRow->value);
        $this->assertEquals($plainWebhook, Crypt::decryptString($webhookRow->value));

        // Verify public settings
        $this->assertDatabaseHas('settings', [
            'academy_id' => $academy->id,
            'group' => 'razorpay',
            'key' => 'enabled',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('settings', [
            'academy_id' => $academy->id,
            'group' => 'razorpay',
            'key' => 'mode',
            'value' => 'test',
        ]);
        $this->assertDatabaseHas('settings', [
            'academy_id' => $academy->id,
            'group' => 'razorpay',
            'key' => 'key_id',
            'value' => 'rzp_test_MerchantTest001',
        ]);
        $this->assertDatabaseHas('settings', [
            'academy_id' => $academy->id,
            'group' => 'razorpay',
            'key' => 'currency',
            'value' => 'INR',
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_saved_secrets_are_masked_and_never_exposed(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');

        $plainSecret = 'super_secret_test_key_12345';
        $plainWebhook = 'webhook_secret_test_token_98765';

        $service = app(RazorpayPaymentService::class);
        $service->saveSettings($academy, [
            'enabled' => true,
            'mode' => 'test',
            'key_id' => 'rzp_test_MerchantTest001',
            'key_secret' => $plainSecret,
            'webhook_secret' => $plainWebhook,
            'currency' => 'INR',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->assertOk();

        // Ensure sensitive secrets are not held in plain text properties
        $this->assertEquals('', $component->get('key_secret'));
        $this->assertEquals('', $component->get('webhook_secret'));

        // Stored badges and masks are populated correctly
        $this->assertTrue($component->get('hasStoredKeySecret'));
        $this->assertTrue($component->get('hasStoredWebhookSecret'));
        $this->assertStringContainsString('••••••••', $component->get('maskedKeySecret'));
        $this->assertStringEndsWith('2345', $component->get('maskedKeySecret'));
        $this->assertStringEndsWith('8765', $component->get('maskedWebhookSecret'));

        // HTML does NOT expose the unencrypted secret
        $component->assertDontSee($plainSecret);
        $component->assertDontSee($plainWebhook);
    }

    #[DataProvider('engineProvider')]
    public function test_saving_without_new_secret_preserves_existing_encrypted_secrets(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');

        $service = app(RazorpayPaymentService::class);
        $service->saveSettings($academy, [
            'enabled' => true,
            'mode' => 'test',
            'key_id' => 'rzp_test_MerchantTest001',
            'key_secret' => 'original_secret_123',
            'webhook_secret' => 'original_webhook_789',
            'currency' => 'INR',
        ]);

        // Save again changing only currency to USD and leaving secrets blank
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('currency', 'USD')
            ->set('key_secret', '')
            ->set('webhook_secret', '')
            ->call('saveSettings')
            ->assertHasNoErrors();

        // Fresh service should still have the original secrets intact
        $freshService = new RazorpayPaymentService($academy);
        $this->assertEquals('USD', $freshService->getCurrency());
        $this->assertEquals('original_secret_123', Crypt::decryptString(
            Setting::where('academy_id', $academy->id)->where('group', 'razorpay')->where('key', 'key_secret')->value('value')
        ));
        $this->assertEquals('original_webhook_789', Crypt::decryptString(
            Setting::where('academy_id', $academy->id)->where('group', 'razorpay')->where('key', 'webhook_secret')->value('value')
        ));
    }

    #[DataProvider('engineProvider')]
    public function test_live_mode_validation_and_warning_acknowledgment(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');

        // 1. Live mode without acknowledgment must fail validation
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('is_enabled', true)
            ->set('mode', 'live')
            ->set('key_id', 'rzp_live_LiveProduction001')
            ->set('key_secret', 'live_secret_12345')
            ->set('webhook_secret', 'live_webhook_12345')
            ->set('acknowledge_live_mode', false)
            ->call('saveSettings')
            ->assertHasErrors(['acknowledge_live_mode']);

        // 2. Live mode with test key format must fail validation
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('is_enabled', true)
            ->set('mode', 'live')
            ->set('key_id', 'rzp_test_WrongPrefix')
            ->set('key_secret', 'live_secret_12345')
            ->set('webhook_secret', 'live_webhook_12345')
            ->set('acknowledge_live_mode', true)
            ->call('saveSettings')
            ->assertHasErrors(['key_id']);

        // 3. Test mode with live key format must fail validation
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('is_enabled', true)
            ->set('mode', 'test')
            ->set('key_id', 'rzp_live_LiveKeyInTest')
            ->set('key_secret', 'test_secret_12345')
            ->set('webhook_secret', 'test_webhook_12345')
            ->call('saveSettings')
            ->assertHasErrors(['key_id']);

        // 4. Proper live mode with rzp_live_ prefix and acknowledgment succeeds
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('is_enabled', true)
            ->set('mode', 'live')
            ->set('key_id', 'rzp_live_ProductionKey999')
            ->set('key_secret', 'live_secret_real_001')
            ->set('webhook_secret', 'live_webhook_real_001')
            ->set('acknowledge_live_mode', true)
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertSee('saved and encrypted successfully');

        $service = new RazorpayPaymentService($academy);
        $this->assertTrue($service->isLiveMode());
        $this->assertEquals('rzp_live_ProductionKey999', $service->getKeyId());
    }

    #[DataProvider('engineProvider')]
    public function test_connection_test_action(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');

        // Test with empty key fails
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('key_id', '')
            ->call('testConnection')
            ->assertSet('testResultStatus', 'error');

        // Test with mock test sandbox key succeeds
        Livewire::actingAs($admin)
            ->test(PaymentSettingsManager::class)
            ->set('key_id', 'rzp_test_sandbox_123')
            ->set('key_secret', 'dummy_secret')
            ->call('testConnection')
            ->assertSet('testResultStatus', 'success')
            ->assertSee('Test connection successful');
    }

    #[DataProvider('engineProvider')]
    public function test_webhook_endpoint_verification_with_configured_database_secret_and_idempotency(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUser($academy, 'student', 'student_webhook@example.com');

        $webhookSecret = 'super_secure_webhook_signing_secret_999';

        $service = app(RazorpayPaymentService::class);
        $service->saveSettings($academy, [
            'enabled' => true,
            'mode' => 'test',
            'key_id' => 'rzp_test_key_webhook_test',
            'key_secret' => 'key_secret_webhook_test',
            'webhook_secret' => $webhookSecret,
            'currency' => 'INR',
        ]);

        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'E-Commerce SEO Masterclass',
            'slug' => 'ecommerce-seo-masterclass-'.uniqid(),
            'price' => 250.00,
            'currency' => 'INR',
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $orderService = app(OrderAndEnrollmentService::class);
        $order = $orderService->createOrder($student, $course, 'INR');

        $paymentId = 'pay_test_hook_'.uniqid();
        $webhookPayload = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => 'order_rzp_mock_'.uniqid(),
                        'amount' => 25000, // 250 INR
                        'currency' => 'INR',
                        'status' => 'captured',
                        'notes' => [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                        ],
                    ],
                ],
            ],
        ]);

        // 1. Invalid signature should be rejected with 400
        $invalidResponse = $this->withHeaders([
            'X-Razorpay-Signature' => 'fake_tampered_signature',
            'Content-Type' => 'application/json',
        ])->postJson('/api/webhooks/razorpay', json_decode($webhookPayload, true));

        $invalidResponse->assertStatus(400);

        // 2. Valid signature computed using the database-stored webhook secret should succeed with 200
        $validSignature = hash_hmac('sha256', $webhookPayload, $webhookSecret);

        $validResponse = $this->withHeaders([
            'X-Razorpay-Signature' => $validSignature,
            'Content-Type' => 'application/json',
        ])->postJson('/api/webhooks/razorpay', json_decode($webhookPayload, true));

        $validResponse->assertStatus(200);
        $validResponse->assertJson(['status' => 'ok']);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals(250.00, $order->fresh()->paid_amount);
        $this->assertEquals(1, $order->fresh()->transactions()->count());

        // 3. Replayed duplicate webhook payload must be handled idempotently
        $replayResponse = $this->withHeaders([
            'X-Razorpay-Signature' => $validSignature,
            'Content-Type' => 'application/json',
        ])->postJson('/api/webhooks/razorpay', json_decode($webhookPayload, true));

        $replayResponse->assertStatus(200);
        $replayResponse->assertJson(['status' => 'ok']);

        // Transactions count must still be 1 (no duplicate payment recorded)
        $this->assertEquals(1, $order->fresh()->transactions()->count());
    }
}
