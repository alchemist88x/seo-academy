<?php

namespace Tests\Feature\Auth;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Livewire\Auth\Register;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('register:127.0.0.1');

        $this->academy = Academy::create([
            'name' => 'SEO Academy Test',
            'slug' => 'seo-academy-test',
            'is_active' => true,
            'settings' => ['allow_self_registration' => true],
        ]);
    }

    public function test_guest_can_view_registration_page(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(Register::class);
    }

    public function test_authenticated_user_cannot_view_registration_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_student_can_self_register_via_controller(): void
    {
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $user = User::where('email', 'jane.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->suspended_at);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        // Exactly one role: student
        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('trainer'));
        $this->assertFalse($user->hasRole('support_staff'));
        $this->assertCount(1, $user->roles);

        // Academy membership created
        $this->assertTrue($user->belongsToAcademy($this->academy->getKey()));
        $membership = AcademyUser::where('user_id', $user->getKey())->where('academy_id', $this->academy->getKey())->first();
        $this->assertNotNull($membership);
        $this->assertEquals('student', $membership->role);
        $this->assertEquals('active', $membership->status);

        // Student profile created
        $profile = $user->studentProfile;
        $this->assertNotNull($profile);
        $this->assertNotEmpty($profile->student_code);
        $this->assertNull($profile->proficiency);
        $this->assertNull($profile->level);
        $this->assertSame('Not Assessed', $profile->proficiency_label);
        $this->assertSame('gray', $profile->proficiency_badge_color);
        $this->assertTrue($profile->active);

        // No automatic course enrollments
        $this->assertEquals(0, $profile->enrollments()->count());

        $this->assertAuthenticatedAs($user);
    }

    public function test_student_can_self_register_via_livewire_component(): void
    {
        Livewire::test(Register::class)
            ->set('first_name', 'Alex')
            ->set('last_name', 'Smith')
            ->set('email', 'alex.smith@example.com')
            ->set('password', 'SecretPass123!')
            ->set('password_confirmation', 'SecretPass123!')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('student.dashboard'));

        $user = User::where('email', 'alex.smith@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_email_registration_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_registration_requires_minimum_password_length_of_8(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Short',
            'last_name' => 'Pass',
            'email' => 'shortpass@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_registration_requires_password_confirmation_match(): void
    {
        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Mismatched',
            'last_name' => 'Pass',
            'email' => 'mismatch@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_registration_is_blocked_when_disabled_by_academy_setting(): void
    {
        $this->academy->update(['settings' => ['allow_self_registration' => false]]);

        $response = $this->from(route('register'))->post(route('register.attempt'), [
            'first_name' => 'Blocked',
            'last_name' => 'Student',
            'email' => 'blocked@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email']);
        $this->assertNull(User::where('email', 'blocked@example.com')->first());
        $this->assertGuest();
    }

    public function test_registration_prevents_role_and_privilege_escalation(): void
    {
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Sneaky',
            'last_name' => 'User',
            'email' => 'sneaky@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => false,
            'proficiency' => 'expert',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $user = User::where('email', 'sneaky@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertNull($user->studentProfile->proficiency);
    }

    public function test_registration_rate_limiting_enforces_maximum_three_attempts_per_minute(): void
    {
        RateLimiter::clear('register:127.0.0.1');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('register.attempt'), [
                'first_name' => "User{$i}",
                'email' => "invalid-email-{$i}",
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);
        }

        // 4th attempt must be throttled
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Throttled',
            'email' => 'throttled@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Too many registration attempts', session('errors')->first('email'));
    }
}
