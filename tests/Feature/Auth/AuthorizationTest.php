<?php

namespace Tests\Feature\Auth;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Livewire\Dashboard\SupportDashboard;
use App\Livewire\Dashboard\TrainerDashboard;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        Academy::create([
            'name' => 'SEO Academy',
            'slug' => 'seo-academy',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_trainer_cannot_access_student_only_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('trainer');

        $response = $this->actingAs($user)->get('/student/dashboard');

        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_student_can_access_student_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->actingAs($user)->get('/student/dashboard');

        $response->assertStatus(200);
    }

    public function test_trainer_is_redirected_to_trainer_dashboard_from_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('trainer');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('trainer.dashboard'));
    }

    public function test_trainer_can_access_trainer_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('trainer');

        $response = $this->actingAs($user)->get('/trainer/dashboard');

        $response->assertStatus(200);
        $response->assertSeeLivewire(TrainerDashboard::class);

        Livewire::actingAs($user)
            ->test(TrainerDashboard::class)
            ->assertStatus(200);
    }

    public function test_student_cannot_access_trainer_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->actingAs($user)->get('/trainer/dashboard');

        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_support_staff_is_redirected_to_support_dashboard_from_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('support_staff');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('support.dashboard'));
    }

    public function test_support_staff_can_access_support_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('support_staff');

        $response = $this->actingAs($user)->get('/support/dashboard');

        $response->assertStatus(200);
        $response->assertSeeLivewire(SupportDashboard::class);

        Livewire::actingAs($user)
            ->test(SupportDashboard::class)
            ->assertStatus(200);
    }

    public function test_student_cannot_access_support_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->actingAs($user)->get('/support/dashboard');

        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_demo_route_returns_404(): void
    {
        $this->assertFalse(Route::has('demo'));
        $this->get('/demo')->assertNotFound();

        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)->get('/demo')->assertNotFound();

        $this->app['env'] = 'local';
        $this->get('/demo')->assertNotFound();
        $this->actingAs($user)->get('/demo')->assertNotFound();
    }

    public function test_database_seeder_seeds_only_four_roles_and_no_demo_records(): void
    {
        Academy::query()->delete();
        $this->seed(DatabaseSeeder::class);

        $roles = Role::query()->pluck('name')->sort()->values()->all();
        $this->assertSame(['admin', 'student', 'support_staff', 'trainer'], $roles);
        $this->assertFalse(Role::where('name', 'platform_admin')->exists());
        $this->assertSame(0, User::count());
        $this->assertSame(0, Course::count());
        $this->assertSame(0, Academy::count());
    }

    public function test_all_four_roles_can_only_access_their_own_dashboard_and_receive_403_elsewhere(): void
    {
        $dashboards = [
            'admin' => '/admin/dashboard',
            'trainer' => '/trainer/dashboard',
            'support' => '/support/dashboard',
            'student' => '/student/dashboard',
        ];

        $allowedByRole = [
            'admin' => 'admin',
            'trainer' => 'trainer',
            'support_staff' => 'support',
            'student' => 'student',
        ];

        foreach ($allowedByRole as $role => $allowedKey) {
            $user = User::factory()->create();
            $user->assignRole($role);

            foreach ($dashboards as $key => $uri) {
                $response = $this->actingAs($user)->get($uri);

                if ($key === $allowedKey) {
                    $response->assertStatus(200);
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_legacy_multi_tenant_roles_do_not_exist_and_register_route_exists(): void
    {
        $this->assertFalse(Role::where('name', 'platform_admin')->exists());
        $this->assertFalse(Role::where('name', 'academy_admin')->exists());
        $this->assertTrue(Route::has('register'));
        $this->assertTrue(Route::has('register.attempt'));

        $this->get('/register')->assertOk();
    }
}
