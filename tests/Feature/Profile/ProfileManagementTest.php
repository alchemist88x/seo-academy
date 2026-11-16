<?php

namespace Tests\Feature\Profile;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Livewire\Profile\ProfileManager;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function createAcademyUser(Academy $academy, string $role): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123!'),
        ]);
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
        ]);

        return $user;
    }

    public function test_all_roles_can_access_profile_page(): void
    {
        $academy = Academy::factory()->create();

        foreach (['admin', 'trainer', 'support_staff', 'student'] as $role) {
            $user = $this->createAcademyUser($academy, $role);

            $response = $this->actingAs($user)
                ->withSession([TenantContext::SESSION_KEY => $academy->id])
                ->get(route('profile'));

            $response->assertStatus(200);
            $response->assertSee('My Profile');
        }
    }

    public function test_user_can_update_personal_information(): void
    {
        $academy = Academy::factory()->create();
        $student = $this->createAcademyUser($academy, 'student');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($student)
            ->test(ProfileManager::class)
            ->set('first_name', 'Sam')
            ->set('last_name', 'Student')
            ->set('email', 'sam.new@example.com')
            ->set('phone', '+15551234')
            ->call('savePersonalInfo')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $student->refresh();
        $this->assertSame('Sam Student', $student->name);
        $this->assertSame('sam.new@example.com', $student->email);
        $this->assertSame('+15551234', $student->phone);
        // Ensure student role was not changed
        $this->assertTrue($student->hasRole('student'));
    }

    public function test_trainer_can_update_specialty_and_bio(): void
    {
        $academy = Academy::factory()->create();
        $trainer = $this->createAcademyUser($academy, 'trainer');
        TrainerProfile::create([
            'academy_id' => $academy->id,
            'user_id' => $trainer->id,
            'specialty' => 'Old Specialty',
            'bio' => 'Old Bio',
            'active' => true,
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        Livewire::actingAs($trainer)
            ->test(ProfileManager::class)
            ->set('specialty', 'Advanced Technical SEO')
            ->set('bio', 'Certified SEO master with 10 years experience.')
            ->call('savePersonalInfo')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $trainerProfile = TrainerProfile::where('user_id', $trainer->id)->firstOrFail();
        $this->assertSame('Advanced Technical SEO', $trainerProfile->specialty);
        $this->assertSame('Certified SEO master with 10 years experience.', $trainerProfile->bio);
    }

    public function test_user_can_change_password_securely(): void
    {
        $academy = Academy::factory()->create();
        $user = $this->createAcademyUser($academy, 'student');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);

        // Wrong current password
        Livewire::actingAs($user)
            ->test(ProfileManager::class)
            ->set('current_password', 'WrongPassword!')
            ->set('new_password', 'NewSecurePass999!')
            ->set('new_password_confirmation', 'NewSecurePass999!')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        // Correct password
        Livewire::actingAs($user)
            ->test(ProfileManager::class)
            ->set('current_password', 'Secret123!')
            ->set('new_password', 'NewSecurePass999!')
            ->set('new_password_confirmation', 'NewSecurePass999!')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass999!', $user->password));
    }
}
