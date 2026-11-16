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
                                <x-badge :color="$trainer->active ? 'green' : 'red'">
                                    {{ $trainer->active ? 'Active' : 'Suspended' }}
                                </x-badge>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('update', $trainer)
                                        <x-button variant="secondary" size="sm" wire:click="openEditModal('{{ $trainer->id }}')">
                                            Edit
                                        </x-button>

                                        <x-button
                                            :variant="$trainer->active ? 'danger' : 'primary'"
                                            size="sm"
                                            wire:click="toggleActive('{{ $trainer->id }}')"
                                            wire:loading.attr="disabled"
                                        >
                                            {{ $trainer->active ? 'Suspend' : 'Activate' }}
                                        </x-button>
                                    @endcan
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
</div>
