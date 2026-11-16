<div class="space-y-6">
    <x-page-header
        title="Members &amp; Invitations"
        :subtitle="'Manage academy memberships, support staff, roles, and email invitations for ' . $academy->name . '.'"
    >
        <x-slot:actions>
            @can('create', [\App\Domain\Academies\Models\AcademyUser::class, $academy])
                <x-button variant="primary" wire:click="openCreateUserModal">
                    + Add User
                </x-button>
            @endcan

            @can('create', [\App\Domain\Identity\Models\Invitation::class, $academy])
                <x-button variant="secondary" wire:click="openInviteModal">
                    + Send Invitation
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Tabs --}}
    <div class="border-b border-border-default">
        <nav class="-mb-px flex gap-6" aria-label="Members Tabs">
            <button
                type="button"
                wire:click="setTab('members')"
                class="py-3 px-1 border-b-2 text-sm font-medium transition-colors whitespace-nowrap {{ $activeTab === 'members'
                    ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-text-secondary hover:text-text-primary hover:border-border-default' }}"
            >
                Members &amp; Support Staff
            </button>
            <button
                type="button"
                wire:click="setTab('invitations')"
                class="py-3 px-1 border-b-2 text-sm font-medium transition-colors whitespace-nowrap {{ $activeTab === 'invitations'
                    ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-text-secondary hover:text-text-primary hover:border-border-default' }}"
            >
                Invitations
            </button>
        </nav>
    </div>

    {{-- Tab 1: Members & Support Staff --}}
    @if ($activeTab === 'members')
        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <x-input
                        name="search"
                        label="Search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by member name or email..."
                    />
                </div>

                <x-select
                    name="roleFilter"
                    label="Role"
                    wire:model.live="roleFilter"
                    placeholder="All Roles"
                    :options="$roleOptions"
                />

                <x-select
                    name="statusFilter"
                    label="Status"
                    wire:model.live="statusFilter"
                    placeholder="All Statuses"
                    :options="[
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'revoked' => 'Revoked',
                    ]"
                />
            </div>
        </x-card>

        <x-card :padding="false">
            @if ($members->isEmpty())
                <x-empty-state
                    title="No members found"
                    message="No academy members match your current filters."
                />
            @else
                <x-table :striped="true">
                    <x-slot:header>
                        <th>Member</th>
                        <th>Academy Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-right">Actions</th>
                    </x-slot:header>

                    <x-slot:body>
                        @foreach ($members as $membership)
                            <tr wire:key="membership-{{ $membership->id }}">
                                <td>
                                    <div class="font-medium text-text-primary">{{ $membership->user?->name ?? 'Unknown' }}</div>
                                    <div class="text-xs text-text-muted">{{ $membership->user?->email ?? '—' }}</div>
                                </td>
                                <td>
                                    <x-badge color="blue">
                                        {{ $roleOptions[$membership->role] ?? ucfirst(str_replace('_', ' ', $membership->role)) }}
                                    </x-badge>
                                </td>
                                <td>
                                    @php
                                        $statusColor = match ($membership->status) {
                                            'active' => 'green',
                                            'suspended' => 'yellow',
                                            default => 'red',
                                        };
                                    @endphp
                                    <x-badge :color="$statusColor">
                                        {{ ucfirst($membership->status) }}
                                    </x-badge>
                                </td>
                                <td class="text-sm text-text-secondary">
                                    {{ $membership->created_at?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <x-button variant="ghost" size="sm" wire:click="openViewMemberModal('{{ $membership->id }}')">
                                            View
                                        </x-button>

                                        @can('update', $membership)
                                            <x-button variant="secondary" size="sm" wire:click="openEditUserModal('{{ $membership->id }}')">
                                                Edit
                                            </x-button>
                                        @endcan

                                        @can('assignRole', $membership)
                                            <x-button variant="secondary" size="sm" wire:click="openRoleModal('{{ $membership->id }}')">
                                                Role
                                            </x-button>
                                        @endcan

                                        @can('update', $membership)
                                            @if ($membership->status === 'active')
                                                <x-button
                                                    variant="secondary"
                                                    size="sm"
                                                    wire:click="setMembershipStatus('{{ $membership->id }}', 'suspended')"
                                                    wire:loading.attr="disabled"
                                                >
                                                    Suspend
                                                </x-button>
                                            @else
                                                <x-button
                                                    variant="primary"
                                                    size="sm"
                                                    wire:click="setMembershipStatus('{{ $membership->id }}', 'active')"
                                                    wire:loading.attr="disabled"
                                                >
                                                    Activate
                                                </x-button>
                                            @endif
                                        @endcan

                                        @can('delete', $membership)
                                            <x-button
                                                variant="danger"
                                                size="sm"
                                                wire:click="revokeMembership('{{ $membership->id }}')"
                                                wire:confirm="Revoke this academy membership? The user account will be preserved."
                                                wire:loading.attr="disabled"
                                            >
                                                Revoke
                                            </x-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-table>

                @if ($members->hasPages())
                    <div class="px-5 py-3 border-t border-border-default">
                        {{ $members->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    @endif

    {{-- Tab 2: Invitations --}}
    @if ($activeTab === 'invitations')
        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <x-select
                    name="invitationStatusFilter"
                    label="Invitation Status"
                    wire:model.live="invitationStatusFilter"
                    placeholder="All Invitations"
                    :options="[
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ]"
                />
            </div>
        </x-card>

        <x-card :padding="false">
            @if ($invitations->isEmpty())
                <x-empty-state
                    title="No invitations found"
                    message="Send an email invitation to onboard new students, trainers, support staff, or admins."
                >
                    @can('create', [\App\Domain\Identity\Models\Invitation::class, $academy])
                        <x-slot:action>
                            <x-button variant="primary" size="sm" wire:click="openInviteModal">
                                Send Invitation
                            </x-button>
                        </x-slot:action>
                    @endcan
                </x-empty-state>
            @else
                <x-table :striped="true">
                    <x-slot:header>
                        <th>Recipient</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th class="text-right">Actions</th>
                    </x-slot:header>

                    <x-slot:body>
                        @foreach ($invitations as $invitation)
                            @php
                                $effectiveStatus = ($invitation->status === 'pending' && $invitation->isExpired())
                                    ? 'expired'
                                    : $invitation->status;
                                $inviteColor = match ($effectiveStatus) {
                                    'accepted' => 'green',
                                    'pending' => 'blue',
                                    'expired' => 'yellow',
                                    default => 'red',
                                };
                            @endphp
                            <tr wire:key="invitation-{{ $invitation->id }}">
                                <td>
                                    <div class="font-medium text-text-primary">{{ $invitation->name ?: $invitation->email }}</div>
                                    <div class="text-xs text-text-muted">{{ $invitation->email }}</div>
                                </td>
                                <td>
                                    <x-badge color="gray">
                                        {{ $roleOptions[$invitation->role] ?? ucfirst(str_replace('_', ' ', $invitation->role)) }}
                                    </x-badge>
                                </td>
                                <td>
                                    <x-badge :color="$inviteColor">
                                        {{ ucfirst($effectiveStatus) }}
                                    </x-badge>
                                </td>
                                <td class="text-sm text-text-secondary">
                                    {{ $invitation->expires_at?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="text-right">
                                    @if ($effectiveStatus === 'pending')
                                        @can('revoke', $invitation)
                                            <x-button
                                                variant="danger"
                                                size="sm"
                                                wire:click="revokeInvitation('{{ $invitation->id }}')"
                                                wire:loading.attr="disabled"
                                            >
                                                Revoke
                                            </x-button>
                                        @endcan
                                    @else
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-table>

                @if ($invitations->hasPages())
                    <div class="px-5 py-3 border-t border-border-default">
                        {{ $invitations->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    @endif

    {{-- Create Support Staff Modal --}}
    <x-modal name="support-staff-modal" title="Add Support Staff" maxWidth="md">
        <form wire:submit="createSupportStaff" class="space-y-4">
            <x-input
                name="staffName"
                label="Full Name"
                wire:model="staffName"
                required
            />

            <x-input
                name="staffEmail"
                label="Email Address"
                type="email"
                wire:model="staffEmail"
                required
            />

            <x-input
                name="staffPhone"
                label="Phone"
                wire:model="staffPhone"
            />

            <x-input
                name="staffPassword"
                label="Password (Optional)"
                type="password"
                wire:model="staffPassword"
                hint="Leave blank to auto-generate if new user."
            />

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'support-staff-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    Add Support Staff
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Change Role Modal --}}
    <x-modal name="change-role-modal" title="Change Academy Role" maxWidth="sm">
        <form wire:submit="updateRole" class="space-y-4">
            <p class="text-sm text-text-secondary">
                Update the academy role for <strong class="text-text-primary">{{ $editingMemberName }}</strong>.
            </p>

            <x-select
                name="selectedRole"
                label="Academy Role"
                wire:model="selectedRole"
                :options="$roleOptions"
                required
            />

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'change-role-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    Save Role
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Send Invitation Modal --}}
    <x-modal name="send-invitation-modal" title="Send Academy Invitation" maxWidth="md">
        <form wire:submit="sendInvitation" class="space-y-4">
            <x-input
                name="inviteEmail"
                label="Email Address"
                type="email"
                wire:model="inviteEmail"
                required
            />

            <x-input
                name="inviteName"
                label="Recipient Name (Optional)"
                wire:model="inviteName"
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-select
                    name="inviteRole"
                    label="Role"
                    wire:model="inviteRole"
                    :options="$roleOptions"
                    required
                />

                <x-input
                    name="inviteExpiresInDays"
                    label="Expires In (Days)"
                    type="number"
                    min="1"
                    max="90"
                    wire:model="inviteExpiresInDays"
                    required
                />
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'send-invitation-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    Send Invitation
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Create User Modal --}}
    <x-modal name="user-create-modal" title="Add User to Academy" maxWidth="lg">
        <form wire:submit="createUser" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    name="userFirstName"
                    label="First Name"
                    wire:model="userFirstName"
                    required
                />

                <x-input
                    name="userLastName"
                    label="Last Name"
                    wire:model="userLastName"
                />

                <x-input
                    name="userEmail"
                    label="Email Address"
                    type="email"
                    wire:model="userEmail"
                    required
                />

                <x-input
                    name="userPhone"
                    label="Phone"
                    wire:model="userPhone"
                    placeholder="+1 (555) 000-0000"
                />

                <x-select
                    name="userRole"
                    label="Academy Role"
                    wire:model="userRole"
                    :options="$roleOptions"
                    required
                />

                <x-select
                    name="userStatus"
                    label="Account Status"
                    wire:model="userStatus"
                    :options="[
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]"
                    required
                />

                <div class="sm:col-span-2">
                    <x-input
                        name="userPassword"
                        label="Password (Optional)"
                        type="password"
                        wire:model="userPassword"
                        hint="Leave blank to auto-generate a secure password if user does not exist."
                    />
                </div>
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'user-create-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createUser">Create User</span>
                    <span wire:loading wire:target="createUser">Creating...</span>
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Edit User Modal --}}
    <x-modal name="user-edit-modal" title="Edit Academy Member" maxWidth="lg">
        <form wire:submit="updateUser" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    name="editFirstName"
                    label="First Name"
                    wire:model="editFirstName"
                    required
                />

                <x-input
                    name="editLastName"
                    label="Last Name"
                    wire:model="editLastName"
                />

                <x-input
                    name="editEmail"
                    label="Email Address"
                    type="email"
                    wire:model="editEmail"
                    required
                />

                <x-input
                    name="editPhone"
                    label="Phone"
                    wire:model="editPhone"
                    placeholder="+1 (555) 000-0000"
                />

                <x-select
                    name="editRole"
                    label="Academy Role"
                    wire:model="editRole"
                    :options="$roleOptions"
                    required
                />

                <x-select
                    name="editStatus"
                    label="Account Status"
                    wire:model="editStatus"
                    :options="[
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]"
                    required
                />

                <div class="sm:col-span-2">
                    <x-input
                        name="editPassword"
                        label="Change Password (Optional)"
                        type="password"
                        wire:model="editPassword"
                        hint="Leave blank to keep existing password."
                    />
                </div>
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'user-edit-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updateUser">Save Changes</span>
                    <span wire:loading wire:target="updateUser">Saving...</span>
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- View User Modal --}}
    <x-modal name="user-view-modal" title="User Details" maxWidth="md">
        @if ($viewingMember)
            <div class="space-y-4 text-sm">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <div>
                        <h4 class="text-base font-semibold text-text-primary">{{ $viewingMember->user?->name }}</h4>
                        <p class="text-xs text-text-muted">{{ $viewingMember->user?->email }}</p>
                    </div>
                    <x-badge :color="match($viewingMember->status) { 'active' => 'green', 'suspended' => 'yellow', default => 'red' }">
                        {{ ucfirst($viewingMember->status) }}
                    </x-badge>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <dt class="text-text-muted font-medium">Academy Role</dt>
                        <dd class="text-text-primary mt-0.5 font-semibold">{{ $roleOptions[$viewingMember->role] ?? ucfirst($viewingMember->role) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted font-medium">Phone</dt>
                        <dd class="text-text-primary mt-0.5">{{ $viewingMember->user?->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted font-medium">Joined Academy</dt>
                        <dd class="text-text-primary mt-0.5">{{ $viewingMember->created_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted font-medium">Suspended At</dt>
                        <dd class="text-text-primary mt-0.5">{{ $viewingMember->suspended_at?->format('M d, Y') ?? 'Never' }}</dd>
                    </div>
                </dl>
            </div>
        @endif

        <x-slot:footer>
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'user-view-modal')">
                Close
            </x-button>
        </x-slot:footer>
    </x-modal>
</div>
