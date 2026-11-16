<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Services\LocalDemoAccountService;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RestoreLocalDemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
    }

    public function test_creates_exactly_five_approved_accounts(): void
    {
        $service = app(LocalDemoAccountService::class);
        $results = $service->restoreDemoAccounts();

        $this->assertCount(5, $results);
        $this->assertSame(5, User::count());

        $expectedEmails = [
            'platform@seo-academy.local',
            'academy-admin@seo-academy.local',
            'trainer@seo-academy.local',
            'student@seo-academy.local',
            'support@seo-academy.local',
        ];

        $actualEmails = User::pluck('email')->sort()->values()->toArray();
        sort($expectedEmails);

        $this->assertEquals($expectedEmails, $actualEmails);
    }

    public function test_assigns_exact_roles_to_each_account(): void
    {
        $service = app(LocalDemoAccountService::class);
        $service->restoreDemoAccounts();

        $platform = User::where('email', 'platform@seo-academy.local')->firstOrFail();
        $this->assertTrue($platform->hasRole('admin'));
        $this->assertFalse($platform->hasRole('trainer'));

        $academyAdmin = User::where('email', 'academy-admin@seo-academy.local')->firstOrFail();
        $this->assertTrue($academyAdmin->hasRole('admin'));

        $trainer = User::where('email', 'trainer@seo-academy.local')->firstOrFail();
        $this->assertTrue($trainer->hasRole('trainer'));
        $this->assertFalse($trainer->hasRole('admin'));

        $student = User::where('email', 'student@seo-academy.local')->firstOrFail();
        $this->assertTrue($student->hasRole('student'));
        $this->assertFalse($student->hasRole('trainer'));

        $support = User::where('email', 'support@seo-academy.local')->firstOrFail();
        $this->assertTrue($support->hasRole('support_staff'));
        $this->assertFalse($support->hasRole('admin'));
    }

    public function test_creates_required_profiles_and_academy_memberships(): void
    {
        $service = app(LocalDemoAccountService::class);
        $service->restoreDemoAccounts();

        $academy = Academy::current();
        $this->assertNotNull($academy);

        // Verify Student Profile
        $studentUser = User::where('email', 'student@seo-academy.local')->firstOrFail();
        $studentProfile = StudentProfile::where('user_id', $studentUser->id)->first();
        $this->assertNotNull($studentProfile);
        $this->assertSame($academy->id, $studentProfile->academy_id);
        $this->assertSame('STU-DEMO-001', $studentProfile->student_code);
        $this->assertTrue($studentProfile->active);

        // Verify Trainer Profile
        $trainerUser = User::where('email', 'trainer@seo-academy.local')->firstOrFail();
        $trainerProfile = TrainerProfile::where('user_id', $trainerUser->id)->first();
        $this->assertNotNull($trainerProfile);
        $this->assertSame($academy->id, $trainerProfile->academy_id);
        $this->assertTrue($trainerProfile->active);

        // Verify all 5 users belong to the academy via academy_users
        $allUsers = User::all();
        $this->assertCount(5, $allUsers);
        foreach ($allUsers as $u) {
            $this->assertTrue($u->belongsToAcademy($academy), "User {$u->email} must belong to current academy");
        }
    }

    public function test_authentication_works_for_all_five_accounts(): void
    {
        $service = app(LocalDemoAccountService::class);
        $results = $service->restoreDemoAccounts();

        foreach ($results as $res) {
            $this->assertNotNull($res['password'], "Newly created account {$res['email']} must have generated password");
            $this->assertTrue(
                Auth::attempt(['email' => $res['email'], 'password' => $res['password']]),
                "Auth::attempt must succeed for {$res['email']}"
            );
            Auth::logout();
        }
    }

    public function test_idempotent_execution_does_not_duplicate_or_reset_passwords(): void
    {
        $service = app(LocalDemoAccountService::class);
        $firstResults = $service->restoreDemoAccounts();

        $initialHashes = [];
        $initialPasswords = [];
        foreach ($firstResults as $res) {
            $u = User::where('email', $res['email'])->firstOrFail();
            $initialHashes[$res['email']] = $u->password;
            $initialPasswords[$res['email']] = $res['password'];
        }

        // Run restoration second time
        $secondResults = $service->restoreDemoAccounts();

        $this->assertSame(5, User::count(), 'User count must remain exactly 5 on re-run');

        foreach ($secondResults as $res) {
            $this->assertSame('Existing (Password Retained)', $res['status']);
            $this->assertNull($res['password']);

            $u = User::where('email', $res['email'])->firstOrFail();
            $this->assertSame(
                $initialHashes[$res['email']],
                $u->password,
                "Password hash for {$res['email']} must not be altered on re-run"
            );

            // Previous password must still authenticate
            $this->assertTrue(
                Auth::attempt(['email' => $res['email'], 'password' => $initialPasswords[$res['email']]]),
                "Authentication must still succeed with original password for {$res['email']}"
            );
            Auth::logout();
        }
    }

    public function test_artisan_command_renders_table_and_fails_in_production(): void
    {
        // 1. In testing/local environment, command succeeds
        $exitCode = Artisan::call('app:restore-demo-accounts');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('platform@seo-academy.local', $output);
        $this->assertStringContainsString('student@seo-academy.local', $output);

        // 2. In production environment, command refuses to execute
        $this->app->detectEnvironment(fn () => 'production');

        $prodExitCode = Artisan::call('app:restore-demo-accounts');
        $prodOutput = Artisan::output();

        $this->assertSame(1, $prodExitCode);
        $this->assertStringContainsString('CRITICAL: Demo accounts cannot be created or restored in a production environment.', $prodOutput);
    }

    public function test_does_not_introduce_forbidden_roles(): void
    {
        $service = app(LocalDemoAccountService::class);
        $service->restoreDemoAccounts();

        $this->assertFalse(Role::where('name', 'platform_admin')->exists());
        $this->assertFalse(Role::where('name', 'academy_admin')->exists());

        $validRoles = ['admin', 'trainer', 'support_staff', 'student'];
        $actualRoles = Role::pluck('name')->toArray();

        foreach ($actualRoles as $role) {
            $this->assertContains($role, $validRoles, "Role '{$role}' is not in approved 4 roles");
        }
    }
}
