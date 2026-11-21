<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('password.request:127.0.0.1');
        RateLimiter::clear('password.reset:127.0.0.1');
    }

    public function test_guest_can_view_forgot_password_page(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(ForgotPassword::class);
    }

    public function test_password_reset_link_can_be_requested_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'student@example.com',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'student@example.com',
        ]);

        $response->assertSessionHas('status');
        $this->assertStringContainsString('If an account matches', session('status'));

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_link_request_returns_generic_response_for_non_existent_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHas('status');
        $this->assertStringContainsString('If an account matches', session('status'));

        Notification::assertNothingSent();
    }

    public function test_livewire_forgot_password_returns_generic_message(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'lw.student@example.com',
        ]);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'lw.student@example.com')
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('status', 'If an account matches that email address, we have sent a password reset link.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_guest_can_view_reset_password_page_with_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $response->assertStatus(200);
        $response->assertSeeLivewire(ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token_via_controller(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'student@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_password_can_be_reset_with_valid_token_via_livewire(): void
    {
        $user = User::factory()->create([
            'email' => 'student.lw@example.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'student.lw@example.com')
            ->set('password', 'NewSecurePass123!')
            ->set('password_confirmation', 'NewSecurePass123!')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass123!', $user->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'OldPassword123!',
        ]);

        $response = $this->from(route('password.reset', ['token' => 'invalid-token']))->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'student@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $user->refresh();
        $this->assertTrue(Hash::check('OldPassword123!', $user->password));
    }

    public function test_forgot_password_is_throttled_to_three_attempts_per_minute(): void
    {
        RateLimiter::clear('password.request:127.0.0.1');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('password.email'), [
                'email' => "user{$i}@example.com",
            ]);
        }

        // 4th attempt must be throttled
        $response = $this->post(route('password.email'), [
            'email' => 'user4@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Too many password reset requests', session('errors')->first('email'));
    }
}
