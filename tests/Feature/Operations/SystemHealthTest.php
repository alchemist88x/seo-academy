<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Mail\SmtpTestMail;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\SystemHealthService;
use App\Livewire\Admin\SystemSettingsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    private User $student;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create();

        $this->admin = User::factory()->create(['name' => 'System Admin', 'email' => 'sysadmin@test.local', 'is_active' => true]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin', 'status' => 'active']);

        $this->student = User::factory()->create(['name' => 'Student User', 'is_active' => true]);
        $this->student->assignRole('student');
        $this->student->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        $this->trainer = User::factory()->create(['name' => 'Trainer User', 'is_active' => true]);
        $this->trainer->assignRole('trainer');
        $this->trainer->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
    }

    /**
     * Test system health service reports truth.
     */
    public function test_system_health_service_reporting(): void
    {
        $service = app(SystemHealthService::class);

        $appHealth = $service->getApplicationHealth();
        $this->assertSame(config('app.version', '5.4.0'), $appHealth['app_version']);
        $this->assertNotEmpty($appHealth['php_version']);

        $dbHealth = $service->getDatabaseHealth();
        $this->assertTrue($dbHealth['connected']);
        $this->assertGreaterThan(0, $dbHealth['migrations_ran']);

        $storageHealth = $service->getStorageHealth();
        $this->assertTrue($storageHealth['storage_writable']);

        $queueHealth = $service->getQueueHealth();
        $this->assertNotEmpty($queueHealth['driver']);

        $phpHealth = $service->getPhpHealth();
        $this->assertTrue($phpHealth['all_extensions_passed']);
    }

    /**
     * Test SMTP configuration encrypted at rest and secret preservation.
     */
    public function test_smtp_configuration_encryption_and_preservation(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SystemSettingsIndex::class)
            ->set('mail_host', 'smtp.mailtrap.io')
            ->set('mail_port', 2525)
            ->set('mail_encryption', 'tls')
            ->set('mail_username', 'user123')
            ->set('mail_password', 'MySuperSecretSmtpPass!')
            ->set('mail_from_address', 'academy@example.com')
            ->set('mail_from_name', 'SEO Academy Notifications')
            ->call('saveSettings')
            ->assertHasNoErrors();

        // Check database: password MUST be encrypted, not stored in plaintext
        $passwordSetting = Setting::where('group', 'mail')->where('key', 'password')->first();
        $this->assertNotNull($passwordSetting);
        $this->assertNotSame('MySuperSecretSmtpPass!', $passwordSetting->value);
        $this->assertSame('MySuperSecretSmtpPass!', Crypt::decryptString($passwordSetting->value));

        // Test second save with empty password keeps the existing password
        Livewire::test(SystemSettingsIndex::class)
            ->set('mail_host', 'smtp.updated-host.com')
            ->set('mail_password', '') // Leave empty
            ->call('saveSettings')
            ->assertHasNoErrors();

        $passwordSettingAfter = Setting::where('group', 'mail')->where('key', 'password')->first();
        $this->assertSame('MySuperSecretSmtpPass!', Crypt::decryptString($passwordSettingAfter->value));
    }

    /**
     * Test SMTP test email dispatch.
     */
    public function test_smtp_test_email_dispatch(): void
    {
        Mail::fake();

        $this->actingAs($this->admin);

        Livewire::test(SystemSettingsIndex::class)
            ->set('mail_host', 'smtp.example.com')
            ->set('mail_port', 587)
            ->set('mail_encryption', 'tls')
            ->set('mail_username', 'tester')
            ->set('mail_password', 'secret')
            ->set('mail_from_address', 'test@academy.local')
            ->set('mail_from_name', 'Academy Test')
            ->set('test_recipient', 'recipient@test.local')
            ->call('testConnection')
            ->assertSet('testStatus', 'success');

        Mail::assertSent(SmtpTestMail::class, function ($mail) {
            return $mail->hasTo('recipient@test.local');
        });
    }

    /**
     * Test non-admin authorization boundaries (students & trainers blocked from health & settings).
     */
    public function test_non_admin_cannot_access_health_or_settings(): void
    {
        // Student blocked
        $this->actingAs($this->student);
        $this->get('/admin/system/health')->assertForbidden();
        $this->get('/admin/system/settings')->assertForbidden();

        // Trainer blocked
        $this->actingAs($this->trainer);
        $this->get('/admin/system/health')->assertForbidden();
        $this->get('/admin/system/settings')->assertForbidden();

        // Guest blocked
        auth()->logout();
        $this->get('/admin/system/health')->assertRedirect('/login');
        $this->get('/admin/system/settings')->assertRedirect('/login');
    }
}
