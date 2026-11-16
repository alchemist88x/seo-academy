<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Mail\SmtpTestMail;
use App\Domain\Platform\Models\Setting;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\SystemSettingsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function createAcademyUser(Academy $academy, string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
        ]);

        return $user;
    }

    public function test_admin_can_view_system_settings(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        $response = $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.system.settings'));

        $response->assertStatus(200);
        $response->assertSee('System &amp; Application Settings', false);
    }

    public function test_non_admin_cannot_access_system_settings(): void
    {
        $academy = Academy::factory()->create();
        $student = $this->createAcademyUser($academy, 'student');

        $response = $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.system.settings'));

        $response->assertStatus(403);
    }

    public function test_admin_can_save_smtp_configuration_securely(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($admin)
            ->test(SystemSettingsIndex::class)
            ->set('mail_host', 'smtp.mailtrap.io')
            ->set('mail_port', 2525)
            ->set('mail_encryption', 'tls')
            ->set('mail_username', 'user123')
            ->set('mail_password', 'secret_pass_123')
            ->set('mail_from_address', 'notifications@academy.example.com')
            ->set('mail_from_name', 'Academy Alerts')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $savedHost = Setting::query()->where('group', 'mail')->where('key', 'host')->value('value');
        $this->assertSame('smtp.mailtrap.io', $savedHost);

        $savedEncryptedPass = Setting::query()->where('group', 'mail')->where('key', 'password')->value('value');
        $this->assertNotSame('secret_pass_123', $savedEncryptedPass);
        $this->assertSame('secret_pass_123', Crypt::decryptString($savedEncryptedPass));
    }

    public function test_admin_can_save_general_and_notification_settings(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($admin)
            ->test(SystemSettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('date_format', 'd/m/Y')
            ->set('time_format', 'g:i A')
            ->set('session_lifetime', 180)
            ->call('saveGeneralSettings')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('d/m/Y', Setting::query()->where('group', 'general')->where('key', 'date_format')->value('value'));
        $this->assertSame('g:i A', Setting::query()->where('group', 'general')->where('key', 'time_format')->value('value'));

        Livewire::actingAs($admin)
            ->test(SystemSettingsIndex::class)
            ->set('activeTab', 'notifications')
            ->set('email_notifications_enabled', false)
            ->set('in_app_notifications_enabled', true)
            ->call('saveNotificationSettings')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('0', Setting::query()->where('group', 'notifications')->where('key', 'email_notifications_enabled')->value('value'));
    }

    public function test_test_email_connection_action_runs_safely(): void
    {
        Mail::fake();

        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($admin)
            ->test(SystemSettingsIndex::class)
            ->set('mail_host', 'localhost')
            ->set('mail_port', 1025)
            ->set('mail_encryption', 'none')
            ->set('mail_from_address', 'sender@example.com')
            ->set('mail_from_name', 'Sender')
            ->set('test_recipient', 'test@example.com')
            ->call('testConnection');

        Mail::assertSent(SmtpTestMail::class);
    }
}
