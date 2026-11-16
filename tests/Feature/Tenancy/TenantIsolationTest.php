<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Tenants\Services\TenantContext;
use App\Http\Middleware\ResolveAcademyContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function attachToAcademy(User $user, Academy $academy, string $role): void
    {
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
        ]);
    }

    public function test_only_one_academy_can_exist_per_installation(): void
    {
        $first = Academy::factory()->create(['name' => 'Primary Academy', 'is_active' => true]);

        $this->assertSame($first->getKey(), Academy::current()?->getKey());

        $this->expectException(DomainException::class);

        Academy::factory()->create(['name' => 'Second Academy', 'is_active' => true]);
    }

    public function test_academy_switch_and_public_register_routes_do_not_exist(): void
    {
        $academy = Academy::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->attachToAcademy($user, $academy, 'admin');

        $this->assertFalse(Route::has('academy.switch'));
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.attempt'));

        $this->actingAs($user)
            ->post('/academy/switch/'.$academy->getKey())
            ->assertNotFound();

        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_tenant_context_always_resolves_single_local_academy_regardless_of_host_or_session(): void
    {
        $academy = Academy::factory()->create([
            'subdomain' => 'local-academy',
            'custom_domain' => 'academy.example.test',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('student');
        $this->attachToAcademy($user, $academy, 'student');

        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        $this->assertSame($academy->getKey(), $context->getAcademy()?->getKey());
        $this->assertFalse($context->isPlatformScope());
        $this->assertFalse($context->isResolvedFromHost());

        // Spoofed session current_academy_id and arbitrary host header cannot alter resolved academy
        $request = Request::create('http://spoofed.other-domain.test/student/dashboard', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put(TenantContext::SESSION_KEY, (string) Str::uuid());
        $request->setUserResolver(fn () => $user);

        app(ResolveAcademyContext::class)->handle($request, fn () => new Response('ok'));

        $this->assertSame($academy->getKey(), $context->id());
        $this->assertFalse($context->isResolvedFromHost());
    }

    public function test_suspended_user_is_blocked_with_403(): void
    {
        $academy = Academy::factory()->create(['is_active' => true]);

        $suspendedUser = User::factory()->create(['is_active' => false]);
        $suspendedUser->assignRole('student');
        $this->attachToAcademy($suspendedUser, $academy, 'student');

        $this->actingAs($suspendedUser)
            ->get(route('student.courses.index'))
            ->assertForbidden();
    }

    public function test_course_policy_enforces_single_academy_role_boundaries(): void
    {
        $academy = Academy::factory()->create(['is_active' => true]);
        $assignedCourse = Course::factory()->create(['academy_id' => $academy->getKey()]);
        $unassignedCourse = Course::factory()->create(['academy_id' => $academy->getKey()]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $trainer = User::factory()->create();
        $trainer->assignRole('trainer');
        $this->attachToAcademy($trainer, $academy, 'trainer');
        CourseTrainer::create([
            'academy_id' => $academy->getKey(),
            'course_id' => $assignedCourse->getKey(),
            'user_id' => $trainer->getKey(),
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $student = User::factory()->create();
        $student->assignRole('student');

        // Admin can manage all courses in the single academy
        $this->assertTrue($admin->can('view', $assignedCourse));
        $this->assertTrue($admin->can('update', $unassignedCourse));
        $this->assertTrue($admin->can('delete', $unassignedCourse));

        // Assigned trainer can manage assigned course, but not unassigned course
        $this->assertTrue($trainer->can('view', $assignedCourse));
        $this->assertTrue($trainer->can('update', $assignedCourse));
        $this->assertFalse($trainer->can('update', $unassignedCourse));
        $this->assertFalse($trainer->can('delete', $assignedCourse));

        // Student cannot update or delete courses
        $this->assertFalse($student->can('update', $assignedCourse));
        $this->assertFalse($student->can('delete', $assignedCourse));
    }

    public function test_student_profile_and_support_ticket_policies_enforce_role_isolation(): void
    {
        $academy = Academy::factory()->create(['is_active' => true]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $supportStaff = User::factory()->create();
        $supportStaff->assignRole('support_staff');

        $studentA = User::factory()->create();
        $studentA->assignRole('student');

        $studentB = User::factory()->create();
        $studentB->assignRole('student');

        $profileA = StudentProfile::factory()->create([
            'academy_id' => $academy->getKey(),
            'user_id' => $studentA->getKey(),
        ]);
        $profileB = StudentProfile::factory()->create([
            'academy_id' => $academy->getKey(),
            'user_id' => $studentB->getKey(),
        ]);

        $ticketA = SupportTicket::create([
            'academy_id' => $academy->getKey(),
            'user_id' => $studentA->getKey(),
            'subject' => 'Help with lesson',
            'description' => 'Need assistance.',
            'status' => 'Pending',
        ]);

        // Student can view own profile/ticket, never another student's
        $this->assertTrue($studentA->can('view', $profileA));
        $this->assertFalse($studentA->can('view', $profileB));
        $this->assertTrue($studentA->can('view', $ticketA));
        $this->assertFalse($studentB->can('view', $ticketA));

        // Admin can view/update support tickets and student profiles
        $this->assertTrue($admin->can('view', $ticketA));
        $this->assertTrue($admin->can('update', $ticketA));
        $this->assertTrue($admin->can('view', $profileB));
        $this->assertTrue($admin->can('update', $profileB));
        $this->assertFalse($supportStaff->can('update', $profileB));
    }

    public function test_role_seeder_contains_only_four_single_academy_roles(): void
    {
        $roles = Role::query()->orderBy('name')->pluck('name')->all();

        $this->assertSame([
            'admin',
            'student',
            'support_staff',
            'trainer',
        ], $roles);
    }

    public function test_academy_current_returns_null_when_database_is_uninitialized(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('academies')
            ->andReturn(false);

        $this->assertNull(Academy::current());
    }

    public function test_role_normalization_migration_preserves_admins_without_escalating_other_roles(): void
    {
        $legacyAcademyAdminRole = Role::firstOrCreate(['name' => 'academy_admin', 'guard_name' => 'web']);
        $legacyPlatformAdminRole = Role::firstOrCreate(['name' => 'platform_admin', 'guard_name' => 'web']);

        $academyAdmin = User::factory()->create();
        $academyAdmin->assignRole($legacyAcademyAdminRole);

        $platformAdmin = User::factory()->create();
        $platformAdmin->assignRole($legacyPlatformAdminRole);

        $trainer = User::factory()->create();
        $trainer->assignRole('trainer');

        $student = User::factory()->create();
        $student->assignRole('student');

        $migration = require database_path('migrations/2026_11_12_180000_normalize_single_academy_roles_and_data.php');
        $migration->up();

        $this->assertTrue($academyAdmin->fresh()->hasRole('admin'));
        $this->assertTrue($platformAdmin->fresh()->hasRole('admin'));
        $this->assertTrue($trainer->fresh()->hasRole('trainer'));
        $this->assertFalse($trainer->fresh()->hasRole('admin'));
        $this->assertTrue($student->fresh()->hasRole('student'));
        $this->assertFalse($student->fresh()->hasRole('admin'));
        $this->assertDatabaseMissing('roles', ['name' => 'academy_admin']);
        $this->assertDatabaseMissing('roles', ['name' => 'platform_admin']);
    }
}
