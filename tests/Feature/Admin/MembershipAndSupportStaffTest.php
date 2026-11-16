<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Identity\Services\MembershipManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\MembersIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipAndSupportStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAcademyMember(Academy $academy, string $role, array $userOverrides = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $userOverrides));
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_admin_can_create_support_staff_and_manage_memberships(): void
    {
        $academy = Academy::factory()->create(['slug' => 'member-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.members.index'))
            ->assertStatus(200)
            ->assertSee('Members & Invitations');

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->set('staffName', 'Sam Support')
            ->set('staffEmail', 'sam.support@example.com')
            ->set('staffPhone', '+15550303')
            ->call('createSupportStaff')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $staffUser = User::where('email', 'sam.support@example.com')->firstOrFail();
        $membership = AcademyUser::where('academy_id', $academy->id)
            ->where('user_id', $staffUser->id)
            ->firstOrFail();

        $this->assertSame('support_staff', $membership->role);
        $this->assertSame('active', $membership->status);
    }

    public function test_single_academy_membership_and_role_helpers(): void
    {
        $academy = Academy::factory()->create(['slug' => 'single-acad']);

        $user = $this->createAcademyMember($academy, 'admin');

        $this->assertTrue($user->belongsToAcademy($academy));
        $this->assertSame('admin', $user->academyRole($academy));
        $this->assertTrue($user->hasAcademyRole($academy, 'admin'));
        $this->assertFalse($user->hasAcademyRole($academy, 'student'));
    }

    public function test_admin_cannot_assign_nonexistent_platform_admin_role(): void
    {
        $academy = Academy::factory()->create(['slug' => 'esc-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');
        $student = $this->createAcademyMember($academy, 'student');

        $membership = AcademyUser::where('academy_id', $academy->id)
            ->where('user_id', $student->id)
            ->firstOrFail();

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('openRoleModal', $membership->id)
            ->set('selectedRole', 'platform_admin')
            ->call('updateRole')
            ->assertHasErrors(['role']);

        $this->assertSame('student', $membership->fresh()->role);

        $this->expectException(ValidationException::class);
        app(MembershipManagementService::class)->assignRole($membership, 'platform_admin', $admin);
    }

    public function test_suspending_membership_blocks_ensure_academy_access_with_403(): void
    {
        $academy = Academy::factory()->create(['slug' => 'susp-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');
        $otherAdmin = $this->createAcademyMember($academy, 'admin');

        $membership = AcademyUser::where('academy_id', $academy->id)
            ->where('user_id', $otherAdmin->id)
            ->firstOrFail();

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('setMembershipStatus', $membership->id, 'suspended')
            ->assertDispatched('toast');

        $this->assertSame('suspended', $membership->fresh()->status);
        $this->assertFalse($otherAdmin->fresh()->belongsToAcademy($academy));

        $this->actingAs($otherAdmin->fresh())
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.students.index'))
            ->assertStatus(403);
    }

    public function test_revoking_membership_blocks_access_and_preserves_global_user(): void
    {
        $academy = Academy::factory()->create(['slug' => 'rev-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');
        $member = $this->createAcademyMember($academy, 'admin', ['email' => 'revoked.member@example.com']);

        $membership = AcademyUser::where('academy_id', $academy->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        app(TenantContext::class)->setAcademy($academy);

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('revokeMembership', $membership->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('academy_users', [
            'id' => $membership->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'email' => 'revoked.member@example.com',
        ]);
    }

    public function test_admin_can_create_user_and_edit_user_details(): void
    {
        $academy = Academy::factory()->create(['slug' => 'user-crud-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->withSession([TenantContext::SESSION_KEY => $academy->id]);
        app(TenantContext::class)->setAcademy($academy);

        // 1. Create a Trainer via User Create Modal
        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->set('userFirstName', 'Taylor')
            ->set('userLastName', 'Trainer')
            ->set('userEmail', 'taylor.trainer@example.com')
            ->set('userPhone', '+15559876')
            ->set('userRole', 'trainer')
            ->set('userStatus', 'active')
            ->call('createUser')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $trainerUser = User::where('email', 'taylor.trainer@example.com')->firstOrFail();
        $this->assertSame('Taylor Trainer', $trainerUser->name);
        $this->assertSame('+15559876', $trainerUser->phone);
        $this->assertTrue($trainerUser->hasRole('trainer'));

        $membership = AcademyUser::where('academy_id', $academy->id)
            ->where('user_id', $trainerUser->id)
            ->firstOrFail();

        // 2. Edit User Details
        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('openEditUserModal', $membership->id)
            ->assertSet('editFirstName', 'Taylor')
            ->assertSet('editLastName', 'Trainer')
            ->assertSet('editEmail', 'taylor.trainer@example.com')
            ->set('editFirstName', 'Taylor')
            ->set('editLastName', 'Senior')
            ->set('editPhone', '+15559999')
            ->set('editStatus', 'active')
            ->call('updateUser')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $trainerUser->refresh();
        $this->assertSame('Taylor Senior', $trainerUser->name);
        $this->assertSame('+15559999', $trainerUser->phone);

        // 3. View Member Modal
        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('openViewMemberModal', $membership->id)
            ->assertSet('viewingMember.id', $membership->id)
            ->assertDispatched('open-modal');
    }
}
