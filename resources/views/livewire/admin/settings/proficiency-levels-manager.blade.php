<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.academy.settings') }}" class="text-xs text-text-muted hover:text-text-primary transition-colors">Settings</a>
                <span class="text-text-muted text-xs">/</span>
                <span class="text-xs font-semibold text-text-primary">Proficiency Levels</span>
            </div>
            <h1 class="text-2xl font-bold text-text-primary tracking-tight mt-1">
                Student Proficiency Levels
            </h1>
            <p class="text-xs text-text-muted mt-1 max-w-2xl">
                Configure customized student proficiency tiers. Students can exist unassessed (Not Assessed), and levels can be ordered, activated, or deactivated dynamically.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <x-button variant="primary" size="sm" wire:click="openCreateModal">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Proficiency Level
            </x-button>
        </div>
    </div>

    {{-- Main Content Table Card --}}
    <x-card :padding="false">
        <div class="p-4 border-b border-border-default/60 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-text-primary">Configured Levels</span>
                <span class="px-2 py-0.5 text-xs rounded-full bg-surface-elevated text-text-secondary font-mono border border-border-default/60">
                    {{ $levels->count() }}
                </span>
            </div>
            <p class="text-xs text-text-muted">
                Inactive levels are hidden from new student assignments, but historical student assignments are permanently retained.
            </p>
        </div>

        @if ($levels->isEmpty())
            <x-empty-state
                title="No proficiency levels configured"
                message="Add your first proficiency level or reset defaults to enable tiered student skill tracking."
            >
                <x-slot:action>
                    <x-button variant="primary" size="sm" wire:click="openCreateModal">
                        Add First Level
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th class="w-16 text-center">Order</th>
                    <th>Proficiency Level</th>
                    <th>Description</th>
                    <th class="text-center">Assigned Students</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($levels as $level)
                        <tr wire:key="prof-level-row-{{ $level->id }}">
                            {{-- Order controls --}}
                            <td class="text-center">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="moveUp('{{ $level->id }}')"
                                        class="p-1 rounded hover:bg-surface-elevated text-text-muted hover:text-text-primary disabled:opacity-30 cursor-pointer"
                                        title="Move up"
                                        @if ($loop->first) disabled @endif
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                    <span class="font-mono text-xs font-semibold text-text-secondary w-4 text-center">
                                        {{ $level->display_order }}
                                    </span>
                                    <button
                                        type="button"
                                        wire:click="moveDown('{{ $level->id }}')"
                                        class="p-1 rounded hover:bg-surface-elevated text-text-muted hover:text-text-primary disabled:opacity-30 cursor-pointer"
                                        title="Move down"
                                        @if ($loop->last) disabled @endif
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>
                            </td>

                            {{-- Name & Slug --}}
                            <td>
                                <div class="flex items-center gap-2">
                                    <x-badge :color="$level->color ?: 'blue'" size="sm">
                                        {{ $level->name }}
                                    </x-badge>
                                    <span class="text-xs text-text-muted font-mono">({{ $level->slug }})</span>
                                </div>
                            </td>

                            {{-- Description --}}
                            <td class="text-xs text-text-secondary max-w-xs truncate">
                                {{ $level->description ?: '—' }}
                            </td>

                            {{-- Assigned Students Count --}}
                            <td class="text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $level->student_profiles_count > 0 ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                                    {{ $level->student_profiles_count }} {{ \Illuminate\Support\Str::plural('student', $level->student_profiles_count) }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="text-center">
                                @if ($level->is_active)
                                    <x-badge color="green" size="xs">Active</x-badge>
                                @else
                                    <x-badge color="gray" size="xs">Inactive</x-badge>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <x-button variant="ghost" size="xs" wire:click="openEditModal('{{ $level->id }}')" title="Edit Level">
                                        Edit
                                    </x-button>

                                    <x-button
                                        variant="ghost"
                                        size="xs"
                                        wire:click="requestToggleActive('{{ $level->id }}')"
                                        title="{{ $level->is_active ? 'Deactivate Level' : 'Activate Level' }}"
                                        class="{{ $level->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}"
                                    >
                                        {{ $level->is_active ? 'Deactivate' : 'Activate' }}
                                    </x-button>

                                    <x-button
                                        variant="ghost"
                                        size="xs"
                                        wire:click="confirmDeleteLevel('{{ $level->id }}')"
                                        class="text-rose-600 hover:text-rose-700 hover:bg-rose-500/10"
                                        title="Delete Level"
                                    >
                                        Delete
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>
        @endif
    </x-card>

    {{-- Create / Edit Modal --}}
    <x-modal name="proficiency-level-modal" maxWidth="lg">
        <form wire:submit.prevent="saveLevel">
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border-default/60 pb-3">
                    <h3 class="text-base font-bold text-text-primary">
                        {{ $editingLevelId ? 'Edit Proficiency Level' : 'Create New Proficiency Level' }}
                    </h3>
                    <button type="button" wire:click="$dispatch('close-modal', 'proficiency-level-modal')" class="text-text-muted hover:text-text-primary cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Level Name --}}
                <x-input
                    name="name"
                    label="Level Name"
                    wire:model.live="name"
                    placeholder="e.g. Advanced, Specialist, Expert"
                    required
                />

                {{-- Level Slug --}}
                <x-input
                    name="slug"
                    label="Identifier Slug (Unique Code)"
                    wire:model="slug"
                    placeholder="e.g. advanced_practitioner"
                    required
                />

                {{-- Color Badge Theme --}}
                <x-select
                    name="color"
                    label="Badge Color Style"
                    wire:model="color"
                    :options="$colorOptions"
                />

                {{-- Display Order --}}
                <x-input
                    name="display_order"
                    type="number"
                    label="Display Order (Sequence)"
                    wire:model="display_order"
                    min="0"
                />

                {{-- Description --}}
                <x-textarea
                    name="description"
                    label="Description & Learning Expectations"
                    wire:model="description"
                    rows="3"
                    placeholder="Outline the competencies and expectations associated with this proficiency level."
                />

                {{-- Active Status --}}
                <div class="flex items-center gap-2 pt-2">
                    <input
                        id="level-is-active"
                        type="checkbox"
                        wire:model="is_active"
                        class="rounded border-border-default text-primary-600 focus:ring-primary-500/20"
                    />
                    <label for="level-is-active" class="text-xs font-semibold text-text-primary cursor-pointer">
                        Active (available for new student assignments)
                    </label>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface-elevated/40 border-t border-border-default/60 flex items-center justify-end gap-2.5">
                <x-button variant="secondary" size="sm" type="button" wire:click="$dispatch('close-modal', 'proficiency-level-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" size="sm" type="submit">
                    {{ $editingLevelId ? 'Save Changes' : 'Create Level' }}
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Confirm Deactivation Modal --}}
    <x-modal name="confirm-deactivate-modal" maxWidth="md">
        @if ($deactivatingLevel)
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-text-primary">
                            Deactivate "{{ $deactivatingLevel->name }}"?
                        </h3>
                        <p class="text-xs text-text-muted mt-0.5">
                            Please review the impact on student profiles before proceeding.
                        </p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-surface-elevated/60 border border-border-default/60 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-text-muted">Assigned Students:</span>
                        <span class="font-bold text-text-primary font-mono text-sm">
                            {{ $deactivatingStudentsCount }}
                        </span>
                    </div>
                    <p class="text-[11px] text-text-secondary leading-relaxed">
                        Deactivating this level removes it from future student creation and editing dropdowns. All <strong>{{ $deactivatingStudentsCount }}</strong> existing student assignment(s) will remain completely intact and clearly readable.
                    </p>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface-elevated/40 border-t border-border-default/60 flex items-center justify-end gap-2.5">
                <x-button variant="secondary" size="sm" wire:click="$dispatch('close-modal', 'confirm-deactivate-modal')">
                    Keep Active
                </x-button>
                <x-button variant="primary" size="sm" class="!bg-amber-600 hover:!bg-amber-700" wire:click="confirmDeactivation">
                    Confirm Deactivation
                </x-button>
            </div>
        @endif
    </x-modal>

    {{-- Confirm Deletion Modal --}}
    <x-modal name="confirm-delete-modal" maxWidth="md">
        @if ($deletingLevel)
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-text-primary">
                            Delete "{{ $deletingLevel->name }}"?
                        </h3>
                        <p class="text-xs text-text-muted mt-0.5">
                            Permanent deletion verification.
                        </p>
                    </div>
                </div>

                @if ($deletingStudentsCount > 0)
                    <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-700 dark:text-rose-400 space-y-1.5">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            Deletion Blocked ({{ $deletingStudentsCount }} Students Assigned)
                        </div>
                        <p class="text-[11px] leading-relaxed">
                            This level cannot be deleted because <strong>{{ $deletingStudentsCount }}</strong> student(s) are currently classified under it. To retire this level safely, <strong>deactivate</strong> it instead.
                        </p>
                    </div>

                    <div class="px-6 py-4 bg-surface-elevated/40 border-t border-border-default/60 flex items-center justify-end">
                        <x-button variant="secondary" size="sm" wire:click="$dispatch('close-modal', 'confirm-delete-modal')">
                            Close
                        </x-button>
                    </div>
                @else
                    <p class="text-xs text-text-secondary leading-relaxed">
                        Are you sure you want to permanently delete the <strong>"{{ $deletingLevel->name }}"</strong> proficiency level? This action cannot be undone.
                    </p>

                    <div class="px-6 py-4 bg-surface-elevated/40 border-t border-border-default/60 flex items-center justify-end gap-2.5">
                        <x-button variant="secondary" size="sm" wire:click="$dispatch('close-modal', 'confirm-delete-modal')">
                            Cancel
                        </x-button>
                        <x-button variant="danger" size="sm" wire:click="deleteLevel">
                            Confirm Delete
                        </x-button>
                    </div>
                @endif
            </div>
        @endif
    </x-modal>
</div>
