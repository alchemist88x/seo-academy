<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('login:test@example.com|127.0.0.1');
    }

    public function test_login_controller_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->from(route('login'))->post(route('login.attempt'), [
                'email' => 'test@example.com',
                'password' => 'WrongPassword!',
            ]);
            $response->assertSessionHasErrors('email');
        }

        // 6th attempt must be locked out
        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_livewire_login_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', 'test@example.com')
                ->set('password', 'WrongPassword!')
                ->call('authenticate')
                ->assertHasErrors(['email']);
        }

        // 6th attempt must be locked out even with correct password
        $test = Livewire::test(Login::class)
            ->set('email', 'test@example.com')
            ->set('password', 'CorrectPassword123!')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $errors = $test->errors();
        $this->assertStringContainsString('Too many login attempts', $errors->first('email'));

        $this->assertGuest();
    }

    public function test_shared_rate_limiting_across_controller_and_livewire(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        // 3 failed attempts via Livewire
        for ($i = 1; $i <= 3; $i++) {
            Livewire::test(Login::class)
                ->set('email', 'test@example.com')
                ->set('password', 'WrongPassword!')
                ->call('authenticate');
        }

        // 2 failed attempts via Controller
        for ($i = 1; $i <= 2; $i++) {
            $this->from(route('login'))->post(route('login.attempt'), [
                'email' => 'test@example.com',
                'password' => 'WrongPassword!',
            ]);
        }

        // 6th attempt via Livewire must be locked out
        Livewire::test(Login::class)
            ->set('email', 'test@example.com')
            ->set('password', 'CorrectPassword123!')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        // 7th attempt via Controller must also be locked out
        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_successful_login_clears_throttle_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);
        $user->assignRole('student');

        // 3 failed attempts
        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('login.attempt'), [
                'email' => 'test@example.com',
                'password' => 'WrongPassword!',
            ]);
        }

        // 4th attempt succeeds
        $this->post(route('login.attempt'), [
            'email' => 'test@example.com',
            'password' => 'CorrectPassword123!',
        ]);

        $this->assertAuthenticatedAs($user);

        // Throttle key should be cleared
        $this->assertFalse(RateLimiter::tooManyAttempts('login:test@example.com|127.0.0.1', 5));
    }
}
