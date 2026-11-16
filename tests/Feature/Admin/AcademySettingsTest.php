<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\AcademySettings;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AcademySettingsTest extends TestCase
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

    public function test_admin_can_view_academy_settings_page(): void
    {
        $academy = Academy::factory()->create(['name' => 'Alpha SEO Academy']);
        $admin = $this->createAcademyUser($academy, 'admin');

        $response = $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.academy.settings'));

        $response->assertStatus(200);
        $response->assertSee('Academy Settings');
        $response->assertSee('Alpha SEO Academy');
        $response->assertSee('Profile & Contact');
        $response->assertSee('Branding & Logo');
        $response->assertSee('Domain Configuration');
        $response->assertSee('General Settings');
    }

    public function test_admin_can_update_profile_and_contact(): void
    {
        $academy = Academy::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-slug',
        ]);
        $admin = $this->createAcademyUser($academy, 'admin');

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(AcademySettings::class)
            ->set('name', 'Updated SEO Academy')
            ->set('slug', 'updated-seo-academy')
            ->set('description', 'Premier SEO training.')
            ->set('support_email', 'help@updatedseo.test')
            ->set('contact_phone', '+1 555 0199')
            ->set('website_url', 'https://updatedseo.test')
            ->set('address', '42 Search Blvd')
            ->set('timezone', 'America/New_York')
            ->set('locale', 'en_US')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $academy->refresh();
        $this->assertSame('Updated SEO Academy', $academy->name);
        $this->assertSame('updated-seo-academy', $academy->slug);
        $this->assertSame('help@updatedseo.test', $academy->support_email);
        $this->assertSame('America/New_York', $academy->timezone);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'academy.profile_updated',
            'subject_id' => $academy->id,
        ]);
    }

    public function test_admin_can_upload_png_logo_and_update_branding(): void
    {
        Storage::fake('public');

        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        app(TenantContext::class)->setAcademy($academy);

        $logo = UploadedFile::fake()->image('logo.png', 200, 80);

        Livewire::actingAs($admin)
            ->test(AcademySettings::class)
            ->set('primary_color', '#1d4ed8')
            ->set('secondary_color', '#0f172a')
            ->set('logo', $logo)
            ->call('saveBranding')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $academy->refresh();
        $this->assertSame('#1d4ed8', $academy->primary_color);
        $this->assertNotNull($academy->logo_path);
        Storage::disk('public')->assertExists($academy->logo_path);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'academy.branding_updated',
            'subject_id' => $academy->id,
        ]);
    }

    public function test_admin_can_update_domain_settings(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(AcademySettings::class)
            ->set('subdomain', 'my-academy')
            ->set('custom_domain', 'learn.myacademy.com')
            ->call('saveDomain')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $academy->refresh();
        $this->assertSame('my-academy', $academy->subdomain);
        $this->assertSame('learn.myacademy.com', $academy->custom_domain);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'academy.domain_updated',
            'subject_id' => $academy->id,
        ]);
    }

    public function test_admin_can_save_general_settings(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(AcademySettings::class)
            ->set('allow_self_registration', true)
            ->set('default_enrollment_expiry_days', 180)
            ->set('certificate_prefix', 'SEO2026')
            ->set('email_sender_name', 'SEO Academy Team')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $academy->refresh();
        $this->assertTrue($academy->setting('allow_self_registration'));
        $this->assertSame(180, $academy->setting('default_enrollment_expiry_days'));
        $this->assertSame('SEO2026', $academy->setting('certificate_prefix'));
        $this->assertSame('SEO Academy Team', $academy->setting('email_sender_name'));

        $this->assertDatabaseHas('settings', [
            'academy_id' => $academy->id,
            'key' => 'certificate_prefix',
            'value' => 'SEO2026',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'academy.settings_updated',
            'subject_id' => $academy->id,
        ]);
    }

    public function test_suspended_admin_gets_403_accessing_academy_settings(): void
    {
        $academy = Academy::factory()->create(['subdomain' => 'academy-a']);
        $suspendedAdmin = $this->createAcademyUser($academy, 'admin');
        $suspendedAdmin->update(['is_active' => false]);

        $response = $this->actingAs($suspendedAdmin)
            ->get(route('admin.academy.settings'));

        $response->assertStatus(403);
    }

    public function test_non_admin_roles_get_403_on_academy_settings(): void
    {
        $academy = Academy::factory()->create();

        foreach (['trainer', 'support_staff', 'student'] as $role) {
            $user = $this->createAcademyUser($academy, $role);

            $response = $this->actingAs($user)
                ->withSession([TenantContext::SESSION_KEY => $academy->id])
                ->get(route('admin.academy.settings'));

            $response->assertStatus(403);
        }
    }
}
