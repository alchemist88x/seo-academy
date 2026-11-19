<div class="space-y-6">
    <x-page-header
        title="Trainers"
        :subtitle="'Manage instructors and trainers for ' . $academy->name . '.'"
    >
        <x-slot:actions>
            @can('create', [\App\Domain\Trainers\Models\TrainerProfile::class, $academy])
                <x-button variant="primary" wire:click="openCreateModal">
                    + Add Trainer
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <x-input
                    name="search"
                    label="Search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, email, or specialty..."
                />
            </div>

            <x-select
                name="status"
                label="Status"
                wire:model.live="status"
                placeholder="All Statuses"
                :options="[
                    'active' => 'Active',
                    'inactive' => 'Suspended',
                    'deleted' => 'Deleted',
                ]"
            />
        </div>

        @if ($search !== '' || $status !== '')
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-medium text-primary-600 hover:underline"
                >
                    Clear filters
                </button>
            </div>
        @endif
    </x-card>

    {{-- Trainers Table --}}
    <x-card :padding="false">
        @if ($trainers->isEmpty())
            <x-empty-state
                title="No trainers found"
                message="No trainers match your current search or filter criteria."
            >
                @can('create', [\App\Domain\Trainers\Models\TrainerProfile::class, $academy])
                    <x-slot:action>
                        <x-button variant="primary" size="sm" wire:click="openCreateModal">
                            Add First Trainer
                        </x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th>Trainer</th>
                    <th>Specialty</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($trainers as $trainer)
                        <tr wire:key="trainer-{{ $trainer->id }}">
                            <td>
                                <div class="font-medium text-text-primary">{{ $trainer->user?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-text-muted">{{ $trainer->user?->email ?? '—' }}</div>
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $trainer->specialty ?: '—' }}
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $trainer->user?->phone ?: '—' }}
                            </td>
                            <td>
                                @if ($trainer->trashed())
                                    <x-badge color="red">
                                        Deleted
                                    </x-badge>
                                @else
                                    <x-badge :color="$trainer->active ? 'green' : 'red'">
                                        {{ $trainer->active ? 'Active' : 'Suspended' }}
                                    </x-badge>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if ($trainer->trashed())
                                        @can('restore', $trainer)
                                            <x-button
                                                variant="primary"
                                                size="sm"
                                                wire:click="restoreTrainer('{{ $trainer->id }}')"
                                                wire:loading.attr="disabled"
                                            >
                                                Restore
                                            </x-button>
                                        @endcan
                                    @else
                                        @can('update', $trainer)
                                            <x-button variant="secondary" size="sm" wire:click="openEditModal('{{ $trainer->id }}')">
                                                Edit
                                            </x-button>

                                            <x-button
                                                :variant="$trainer->active ? 'ghost' : 'primary'"
                                                size="sm"
                                                wire:click="toggleActive('{{ $trainer->id }}')"
                                                wire:loading.attr="disabled"
                                                class="{{ $trainer->active ? 'text-amber-500 hover:text-amber-600' : '' }}"
                                            >
                                                {{ $trainer->active ? 'Suspend' : 'Activate' }}
                                            </x-button>
                                        @endcan

                                        @can('delete', $trainer)
                                            <x-button
                                                variant="danger"
                                                size="sm"
                                                wire:click="openDeleteModal('{{ $trainer->id }}')"
                                                wire:loading.attr="disabled"
                                            >
                                                Delete
                                            </x-button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            @if ($trainers->hasPages())
                <div class="px-5 py-3 border-t border-border-default">
                    {{ $trainers->links() }}
                </div>
            @endif
        @endif
    </x-card>

    {{-- Create / Edit Trainer Modal --}}
    <x-modal name="trainer-form-modal" :title="$editingTrainerId ? 'Edit Trainer' : 'Add Trainer'" maxWidth="lg">
        <form wire:submit="saveTrainer" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    name="name"
                    label="Full Name"
                    wire:model="name"
                    required
                />

                <x-input
                    name="email"
                    label="Email Address"
                    type="email"
                    wire:model="email"
                    required
                />

                <x-input
                    name="phone"
                    label="Phone"
                    wire:model="phone"
                />

                <x-input
                    name="password"
                    :label="$editingTrainerId ? 'Change Password (Optional)' : 'Password (Optional)'"
                    type="password"
                    wire:model="password"
                    :hint="$editingTrainerId ? 'Leave blank to keep current password.' : 'Leave blank to auto-generate if new user.'"
                />

                <div class="sm:col-span-2">
                    <x-input
                        name="specialty"
                        label="Specialty"
                        wire:model="specialty"
                        placeholder="e.g. Technical SEO, Link Building, Local SEO"
                    />
                </div>
            </div>

            <div>
                <label for="trainer-bio" class="form-label">Bio</label>
                <textarea
                    id="trainer-bio"
                    rows="3"
                    wire:model="bio"
                    class="form-input @error('bio') error @enderror"
                    placeholder="Short instructor biography..."
                ></textarea>
                @error('bio')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'trainer-form-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveTrainer">
                        {{ $editingTrainerId ? 'Save Changes' : 'Create Trainer' }}
                    </span>
                    <span wire:loading wire:target="saveTrainer">Saving...</span>
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Delete Trainer Confirmation Modal --}}
    <x-modal
        name="trainer-delete-modal"
        title="Delete Trainer Account"
        description="Safely deactivate and soft-delete trainer profile while preserving assigned courses and authorship."
        maxWidth="md"
    >
        @if ($deletingTrainerProfile)
            <div class="space-y-4">
                <div class="p-3.5 rounded-lg bg-surface-elevated border border-border-default space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Trainer:</span>
                        <span class="font-semibold text-text-primary">{{ $deletingTrainerProfile->user?->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Email:</span>
                        <span class="font-mono text-text-secondary">{{ $deletingTrainerProfile->user?->email }}</span>
                    </div>
                    @if ($deletingTrainerProfile->specialty)
                        <div class="flex items-center justify-between">
                            <span class="text-text-muted">Specialty:</span>
                            <span class="text-text-primary">{{ $deletingTrainerProfile->specialty }}</span>
                        </div>
                    @endif
                </div>

                {{-- Related Record Impact Summary --}}
                <div class="p-3.5 rounded-lg border border-amber-500/30 bg-amber-500/10 space-y-2.5">
                    <div class="flex items-center gap-2 text-xs font-semibold text-amber-500">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Associated Records &amp; Course History Impact:
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2 rounded bg-surface-card border border-border-default/60">
                            <div class="text-text-muted text-[11px]">Assigned Courses</div>
                            <div class="font-bold text-text-primary text-sm">{{ $deletingTrainerImpact['assigned_courses_count'] ?? 0 }}</div>
                        </div>
                        <div class="p-2 rounded bg-surface-card border border-border-default/60">
                            <div class="text-text-muted text-[11px]">Evaluations Recorded</div>
                            <div class="font-bold text-text-primary text-sm">{{ $deletingTrainerImpact['evaluations_count'] ?? 0 }}</div>
                        </div>
                    </div>

                    @if (!empty($deletingTrainerImpact['assigned_courses']))
                        <div class="pt-1">
                            <div class="text-[11px] font-semibold text-text-muted mb-1">Assigned Courses:</div>
                            <ul class="space-y-1 text-xs">
                                @foreach ($deletingTrainerImpact['assigned_courses'] as $c)
                                    <li class="flex items-center justify-between text-text-secondary">
                                        <span>• {{ $c['title'] }}</span>
                                        <span class="text-[10px] text-text-muted uppercase">({{ $c['status'] }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <p class="text-[11px] text-text-secondary leading-relaxed pt-1">
                        <strong>Policy Note:</strong> Reassigning courses to another trainer must be done as a separate explicit action. Deleting this account will deactivate authentication access while preserving historical authorship, course content, evaluations, grade publications, and audit logs.
                    </p>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2.5 w-full">
                <x-button variant="secondary" type="button" wire:click="cancelDelete">
                    Cancel
                </x-button>
                <x-button variant="danger" type="button" wire:click="confirmDeleteTrainer" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirmDeleteTrainer">Delete Trainer Account</span>
                    <span wire:loading wire:target="confirmDeleteTrainer">Deleting...</span>
                </x-button>
            </div>
        </x-slot:footer>
    </x-modal>
</div>
