<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuspendedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_suspended_user_cannot_login_via_login_controller(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'SecretPass123!',
            'is_active' => false,
            'suspended_at' => now(),
        ]);
        $user->assignRole('student');

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'suspended@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login_via_livewire_login_component(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended-lw@example.com',
            'password' => 'SecretPass123!',
            'is_active' => false,
            'suspended_at' => now(),
        ]);
        $user->assignRole('student');

        Livewire::test(Login::class)
            ->set('email', 'suspended-lw@example.com')
            ->set('password', 'SecretPass123!')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_active_user_can_login_via_login_controller_and_livewire(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => 'SecretPass123!',
            'is_active' => true,
        ]);
        $user->assignRole('student');

        $response = $this->post(route('login.attempt'), [
            'email' => 'active@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'));
        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('email', 'active@example.com')
            ->set('password', 'SecretPass123!')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
