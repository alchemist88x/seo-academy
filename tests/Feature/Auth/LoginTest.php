<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_get_login_returns_200(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_post_login_with_valid_credentials_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);
        $user->assignRole('student');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_post_login_with_invalid_credentials_returns_back_with_error(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_post_logout_redirects_to_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_support_staff_login_redirects_to_support_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);
        $user->assignRole('support_staff');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('support.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unauthenticated_get_dashboard_redirects_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
