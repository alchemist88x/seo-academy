<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Services\InvitationService;
use App\Domain\Identity\Services\MembershipManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Members & Invitations')]
class MembersIndex extends Component
{
    use WithPagination;

    public string $activeTab = 'members';

    // Members filters
    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    // Support Staff / Member create modal
    public string $staffName = '';

    public string $staffEmail = '';

    public string $staffPhone = '';

    public string $staffPassword = '';

    // General User Create modal state
    public string $userName = '';

    public string $userFirstName = '';

    public string $userLastName = '';

    public string $userEmail = '';

    public string $userPhone = '';

    public string $userPassword = '';

    public string $userRole = 'student';

    public string $userStatus = 'active';

    // General User Edit modal state
    public ?string $editingUserId = null;

    public string $editFirstName = '';

    public string $editLastName = '';

    public string $editName = '';

    public string $editEmail = '';

    public string $editPhone = '';

    public string $editPassword = '';

    public string $editRole = 'student';

    public string $editStatus = 'active';

    // View Member Modal state
    public ?AcademyUser $viewingMember = null;

    // Role change modal
    public ?string $editingMembershipId = null;

    public string $editingMemberName = '';

    public string $selectedRole = 'support_staff';

    // Invitation create modal
    public string $inviteEmail = '';

    public string $inviteName = '';

    public string $inviteRole = 'student';

    public int $inviteExpiresInDays = 7;

    // Invitation status filter
    public string $invitationStatusFilter = '';

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', [AcademyUser::class, $academy]);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['members', 'invitations'], true)) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingInvitationStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateUserModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [AcademyUser::class, $academy]);

        $this->resetValidation();
        $this->reset(['userName', 'userFirstName', 'userLastName', 'userEmail', 'userPhone', 'userPassword']);
        $this->userRole = 'student';
        $this->userStatus = 'active';
        $this->dispatch('open-modal', 'user-create-modal');
    }

    public function createUser(TenantContext $tenantContext, MembershipManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [AcademyUser::class, $academy]);

        $payload = [
            'first_name' => $this->userFirstName,
            'last_name' => $this->userLastName,
            'name' => trim("{$this->userFirstName} {$this->userLastName}") ?: $this->userName,
            'email' => $this->userEmail,
            'phone' => $this->userPhone !== '' ? $this->userPhone : null,
            'role' => $this->userRole,
            'status' => $this->userStatus,
        ];

        if ($this->userPassword !== '') {
            $payload['password'] = $this->userPassword;
        }

        $service->createUser($academy, $payload, Auth::user());

        $this->reset(['userName', 'userFirstName', 'userLastName', 'userEmail', 'userPhone', 'userPassword']);
        $this->dispatch('close-modal', 'user-create-modal');
        $this->dispatch('toast', type: 'success', message: 'User created and added to academy.');
    }

    public function openEditUserModal(string $membershipId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->with('user')
            ->findOrFail($membershipId);

        $this->authorize('update', $membership);

        $this->resetValidation();
        $this->editingUserId = (string) $membership->getKey();
        $nameParts = explode(' ', (string) ($membership->user?->name ?? ''), 2);
        $this->editFirstName = $nameParts[0] ?? '';
        $this->editLastName = $nameParts[1] ?? '';
        $this->editName = (string) ($membership->user?->name ?? '');
        $this->editEmail = (string) ($membership->user?->email ?? '');
        $this->editPhone = (string) ($membership->user?->phone ?? '');
        $this->editPassword = '';
        $this->editRole = $membership->role;
        $this->editStatus = $membership->status;

        $this->dispatch('open-modal', 'user-edit-modal');
    }

    public function updateUser(TenantContext $tenantContext, MembershipManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->with('user')
            ->findOrFail($this->editingUserId);

        $this->authorize('update', $membership);

        $payload = [
            'first_name' => $this->editFirstName,
            'last_name' => $this->editLastName,
            'name' => trim("{$this->editFirstName} {$this->editLastName}") ?: $this->editName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone !== '' ? $this->editPhone : null,
            'role' => $this->editRole,
            'status' => $this->editStatus,
        ];

        if ($this->editPassword !== '') {
            $payload['password'] = $this->editPassword;
        }

        $service->updateUser($membership, $payload, Auth::user());

        $this->editingUserId = null;
        $this->dispatch('close-modal', 'user-edit-modal');
        $this->dispatch('toast', type: 'success', message: 'User details updated.');
    }

    public function openViewMemberModal(string $membershipId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->with(['user.studentProfile.enrollments.course', 'user.trainerProfiles', 'user.certificates'])
            ->findOrFail($membershipId);

        $this->authorize('view', $membership);

        $this->viewingMember = $membership;
        $this->dispatch('open-modal', 'user-view-modal');
    }

    public function openSupportStaffModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [AcademyUser::class, $academy]);

        $this->resetValidation();
        $this->reset(['staffName', 'staffEmail', 'staffPhone', 'staffPassword']);
        $this->dispatch('open-modal', 'support-staff-modal');
    }

    public function createSupportStaff(TenantContext $tenantContext, MembershipManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [AcademyUser::class, $academy]);

        $payload = [
            'name' => $this->staffName,
            'email' => $this->staffEmail,
            'phone' => $this->staffPhone !== '' ? $this->staffPhone : null,
        ];

        if ($this->staffPassword !== '') {
            $payload['password'] = $this->staffPassword;
        }

        $service->createSupportStaff($academy, $payload, Auth::user());

        $this->reset(['staffName', 'staffEmail', 'staffPhone', 'staffPassword']);
        $this->dispatch('close-modal', 'support-staff-modal');
        $this->dispatch('toast', type: 'success', message: 'Support staff member added.');
    }

    public function openRoleModal(string $membershipId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->with('user')
            ->findOrFail($membershipId);

        $this->authorize('assignRole', $membership);

        $this->resetValidation();
        $this->editingMembershipId = (string) $membership->getKey();
        $this->editingMemberName = (string) ($membership->user?->name ?? $membership->user?->email ?? 'Member');
        $this->selectedRole = in_array($membership->role, MembershipManagementService::ACADEMY_ROLES, true)
            ? $membership->role
            : 'student';

        $this->dispatch('open-modal', 'change-role-modal');
    }

    public function updateRole(TenantContext $tenantContext, MembershipManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->findOrFail($this->editingMembershipId);

        $this->authorize('assignRole', $membership);

        $service->assignRole($membership, $this->selectedRole, Auth::user());

        $this->editingMembershipId = null;
        $this->dispatch('close-modal', 'change-role-modal');
        $this->dispatch('toast', type: 'success', message: 'Member role updated.');
    }

    public function setMembershipStatus(string $membershipId, string $status, TenantContext $tenantContext, MembershipManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->findOrFail($membershipId);

        $this->authorize('update', $membership);

        $service->setStatus($membership, $status, Auth::user());

        $this->dispatch('toast', type: 'success', message: "Membership status set to {$status}.");
    }

    public function revokeMembership(string $membershipId, TenantContext $tenantContext, MembershipManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $membership = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->findOrFail($membershipId);

        $this->authorize('delete', $membership);

        $service->revoke($membership, Auth::user());

        $this->dispatch('toast', type: 'success', message: 'Membership revoked. User account preserved.');
    }

    public function openInviteModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [Invitation::class, $academy]);

        $this->resetValidation();
        $this->inviteEmail = '';
        $this->inviteName = '';
        $this->inviteRole = 'student';
        $this->inviteExpiresInDays = 7;

        $this->dispatch('open-modal', 'send-invitation-modal');
    }

    public function sendInvitation(TenantContext $tenantContext, InvitationService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [Invitation::class, $academy]);

        $service->create($academy, [
            'email' => $this->inviteEmail,
            'name' => $this->inviteName !== '' ? $this->inviteName : null,
            'role' => $this->inviteRole,
            'expires_in_days' => $this->inviteExpiresInDays,
        ], Auth::user());

        $this->reset(['inviteEmail', 'inviteName']);
        $this->inviteRole = 'student';
        $this->inviteExpiresInDays = 7;

        $this->dispatch('close-modal', 'send-invitation-modal');
        $this->dispatch('toast', type: 'success', message: 'Invitation sent.');
    }

    public function revokeInvitation(string $invitationId, TenantContext $tenantContext, InvitationService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $invitation = Invitation::query()
            ->where('academy_id', $academy->getKey())
            ->findOrFail($invitationId);

        $this->authorize('revoke', $invitation);

        $service->revoke($invitation, Auth::user());

        $this->dispatch('toast', type: 'success', message: 'Invitation revoked.');
    }

    public function render(TenantContext $tenantContext, MembershipManagementService $membershipService)
    {
        $academy = $this->resolveAcademy($tenantContext);

        $members = $membershipService->listForAcademy($academy, [
            'search' => $this->search,
            'role' => $this->roleFilter,
            'status' => $this->statusFilter,
        ], 15);

        $invitationsQuery = Invitation::query()
            ->where('academy_id', $academy->getKey())
            ->with('inviter')
            ->latest();

        if ($this->invitationStatusFilter !== '') {
            if ($this->invitationStatusFilter === 'expired') {
                $invitationsQuery->where(function ($q): void {
                    $q->where('status', 'expired')
                        ->orWhere(function ($sub): void {
                            $sub->where('status', 'pending')
                                ->where('expires_at', '<=', now());
                        });
                });
            } elseif ($this->invitationStatusFilter === 'pending') {
                $invitationsQuery->where('status', 'pending')
                    ->where('expires_at', '>', now());
            } else {
                $invitationsQuery->where('status', $this->invitationStatusFilter);
            }
        }

        $invitations = $invitationsQuery->paginate(15, ['*'], 'invitationsPage');

        $roleOptions = [
            'admin' => 'Admin',
            'trainer' => 'Trainer',
            'support_staff' => 'Support Staff',
            'student' => 'Student',
        ];

        return view('livewire.admin.members-index', [
            'academy' => $academy,
            'members' => $members,
            'invitations' => $invitations,
            'roleOptions' => $roleOptions,
        ]);
    }

    private function resolveAcademy(TenantContext $tenantContext): Academy
    {
        $academy = $tenantContext->getAcademy();
        if ($academy === null) {
            abort(404, 'No active academy selected.');
        }

        return $academy;
    }
}
