<div class="space-y-6">
    <x-page-header
        title="Students"
        :subtitle="'Manage enrolled students, profiles, and bulk CSV imports for ' . $academy->name . '.'"
    >
        <x-slot:actions>
            @can('import', [\App\Domain\Students\Models\StudentProfile::class, $academy])
                <x-button variant="secondary" wire:click="openImportModal">
                    Bulk CSV Import
                </x-button>
            @endcan

            @can('create', [\App\Domain\Students\Models\StudentProfile::class, $academy])
                <x-button variant="primary" wire:click="openCreateModal">
                    + Add Student
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <x-input
                    name="search"
                    label="Search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, email, student code, or batch..."
                />
            </div>

            <x-select
                name="status"
                label="Status"
                wire:model.live="status"
                placeholder="All Statuses"
                :options="[
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ]"
            />

            <x-select
                name="level"
                label="Level"
                wire:model.live="level"
                placeholder="All Levels"
                :options="array_combine($levels, $levels)"
            />
        </div>

        @if ($search !== '' || $status !== '' || $level !== '')
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

    {{-- Students Table --}}
    <x-card :padding="false">
        @if ($students->isEmpty())
            <x-empty-state
                title="No students found"
                message="No students match your current search or filter criteria."
            >
                @can('create', [\App\Domain\Students\Models\StudentProfile::class, $academy])
                    <x-slot:action>
                        <x-button variant="primary" size="sm" wire:click="openCreateModal">
                            Add First Student
                        </x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th>Student</th>
                    <th>Contact</th>
                    <th>Student Code</th>
                    <th>Batch &amp; Level</th>
                    <th>Enrollments</th>
                    <th>Enrolled</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($students as $student)
                        <tr wire:key="student-{{ $student->id }}">
                            <td>
                                <div class="font-medium text-text-primary">{{ $student->user?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-text-muted">{{ $student->user?->email ?? '—' }}</div>
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $student->user?->phone ?: '—' }}
                            </td>
                            <td class="font-mono text-xs text-text-secondary">
                                {{ $student->student_code }}
                            </td>
                            <td>
                                <div class="text-sm text-text-primary">{{ $student->level ?: 'Beginner' }}</div>
                                @if ($student->batch)
                                    <div class="text-xs text-text-muted">{{ $student->batch }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                    {{ $student->enrollments_count ?? 0 }} {{ \Illuminate\Support\Str::plural('course', $student->enrollments_count ?? 0) }}
                                </span>
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $student->enrolled_at?->format('M d, Y') ?? '—' }}
                            </td>
                            <td>
                                <x-badge :color="$student->active ? 'green' : 'red'">
                                    {{ $student->active ? 'Active' : 'Suspended' }}
                                </x-badge>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('view', $student)
                                        <x-button variant="ghost" size="sm" wire:click="openViewModal('{{ $student->id }}')">
                                            View
                                        </x-button>
                                    @endcan

                                    @can('update', $student)
                                        <x-button variant="secondary" size="sm" wire:click="openEditModal('{{ $student->id }}')">
                                            Edit
                                        </x-button>

                                        <x-button
                                            :variant="$student->active ? 'danger' : 'primary'"
                                            size="sm"
                                            wire:click="toggleActive('{{ $student->id }}')"
                                            wire:loading.attr="disabled"
                                        >
                                            {{ $student->active ? 'Suspend' : 'Activate' }}
                                        </x-button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            @if ($students->hasPages())
                <div class="px-5 py-3 border-t border-border-default">
                    {{ $students->links() }}
                </div>
            @endif
        @endif
    </x-card>

    {{-- Create / Edit Student Modal --}}
    <x-modal name="student-form-modal" :title="$editingStudentId ? 'Edit Student' : 'Add Student'" maxWidth="lg">
        <form wire:submit="saveStudent" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    name="first_name"
                    label="First Name"
                    wire:model="first_name"
                    required
                />

                <x-input
                    name="last_name"
                    label="Last Name"
                    wire:model="last_name"
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
                    placeholder="+1 (555) 000-0000"
                />

                <x-input
                    name="password"
                    :label="$editingStudentId ? 'Change Password (Optional)' : 'Password (Optional)'"
                    type="password"
                    wire:model="password"
                    :hint="$editingStudentId ? 'Leave blank to keep current password.' : 'Leave blank to auto-generate if new user.'"
                />

                <x-select
                    name="account_status"
                    label="Account Status"
                    wire:model="account_status"
                    :options="[
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]"
                    required
                />

                <x-input
                    name="student_code"
                    label="Student Code"
                    wire:model="student_code"
                    hint="Leave blank to auto-generate."
                />

                <x-input
                    name="batch"
                    label="Batch"
                    wire:model="batch"
                    placeholder="e.g. Cohort 2026-A"
                />

                <x-select
                    name="formLevel"
                    label="Level"
                    wire:model="formLevel"
                    :options="array_combine($levels, $levels)"
                    required
                />

                <x-input
                    name="enrolled_at"
                    label="Enrolled Date"
                    type="date"
                    wire:model="enrolled_at"
                />
            </div>

            <div>
                <label for="student-notes" class="form-label">Notes</label>
                <textarea
                    id="student-notes"
                    rows="3"
                    wire:model="notes"
                    class="form-input @error('notes') error @enderror"
                    placeholder="Optional internal notes..."
                ></textarea>
                @error('notes')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'student-form-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveStudent">
                        {{ $editingStudentId ? 'Save Changes' : 'Create Student' }}
                    </span>
                    <span wire:loading wire:target="saveStudent">Saving...</span>
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- View Student Modal --}}
    <x-modal name="student-view-modal" title="Student Details &amp; Academic History" maxWidth="lg">
        @if ($viewingStudent)
            <div class="space-y-4 text-sm">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <div>
                        <h4 class="text-base font-semibold text-text-primary">{{ $viewingStudent->user?->name }}</h4>
                        <p class="text-xs text-text-muted">{{ $viewingStudent->user?->email }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge :color="$viewingStudent->active ? 'green' : 'red'">
                            {{ $viewingStudent->active ? 'Active' : 'Suspended' }}
                        </x-badge>
                        <x-button variant="secondary" size="sm" wire:click="openEditModal('{{ $viewingStudent->id }}')">
                            Edit Profile
                        </x-button>
                    </div>
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <dt class="text-xs text-text-muted">Student Code</dt>
                        <dd class="font-mono text-sm text-text-primary font-medium">{{ $viewingStudent->student_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Phone Number</dt>
                        <dd class="text-sm text-text-primary">{{ $viewingStudent->user?->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Level</dt>
                        <dd class="text-sm text-text-primary">{{ $viewingStudent->level ?: 'Beginner' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Batch</dt>
                        <dd class="text-sm text-text-primary">{{ $viewingStudent->batch ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Enrolled Since</dt>
                        <dd class="text-sm text-text-primary">{{ $viewingStudent->enrolled_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-muted">Total Enrollments</dt>
                        <dd class="text-sm text-text-primary font-medium">{{ $viewingStudent->enrollments_count ?? $viewingStudent->enrollments->count() }} courses</dd>
                    </div>
                </dl>

                @if ($viewingStudent->notes)
                    <div class="p-3 rounded-lg bg-surface-bg border border-border-default text-xs">
                        <p class="font-semibold text-text-primary mb-1">Internal Notes</p>
                        <p class="text-text-secondary whitespace-pre-line">{{ $viewingStudent->notes }}</p>
                    </div>
                @endif

                {{-- Course Enrollments Section --}}
                <div class="pt-3 border-t border-border-default space-y-2">
                    <div class="flex items-center justify-between">
                        <h5 class="text-xs font-semibold uppercase tracking-wider text-text-muted">Course Enrollments</h5>
                        <a href="{{ route('admin.enrollments.index') }}" class="text-xs text-primary-600 hover:underline">
                            Manage All Enrollments &rarr;
                        </a>
                    </div>

                    @if ($viewingStudent->enrollments->isEmpty())
                        <p class="text-xs text-text-muted italic py-2">No course enrollments found for this student.</p>
                    @else
                        <div class="divide-y divide-border-default rounded-lg border border-border-default overflow-hidden text-xs">
                            @foreach ($viewingStudent->enrollments as $enrollment)
                                <div class="p-3 flex items-center justify-between bg-surface-card">
                                    <div>
                                        <p class="font-medium text-text-primary">{{ $enrollment->course?->title ?? 'Course #' . $enrollment->course_id }}</p>
                                        <p class="text-text-muted">Enrolled: {{ $enrollment->enrolled_at?->format('M d, Y') ?? '—' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-badge :color="match($enrollment->status) { 'active' => 'green', 'completed' => 'blue', 'suspended' => 'yellow', default => 'gray' }">
                                            {{ ucfirst($enrollment->status) }}
                                        </x-badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Certificates Section --}}
                @if ($viewingStudent->user && $viewingStudent->user->certificates->isNotEmpty())
                    <div class="pt-3 border-t border-border-default space-y-2">
                        <h5 class="text-xs font-semibold uppercase tracking-wider text-text-muted">Earned Certificates</h5>
                        <div class="divide-y divide-border-default rounded-lg border border-border-default overflow-hidden text-xs">
                            @foreach ($viewingStudent->user->certificates as $cert)
                                <div class="p-3 flex items-center justify-between bg-surface-card">
                                    <div>
                                        <p class="font-medium text-text-primary">{{ $cert->certificate_number }}</p>
                                        <p class="text-text-muted">Issued {{ $cert->issued_at?->format('M d, Y') ?? '—' }}</p>
                                    </div>
                                    <x-badge color="purple">Verified</x-badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                <a href="{{ route('admin.enrollments.index') }}" class="btn btn-secondary btn-sm">
                    Manage Enrollments
                </a>
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'student-view-modal')">
                    Close
                </x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    {{-- CSV Bulk Import Modal --}}
    <x-modal name="student-import-modal" title="Bulk CSV Student Import" maxWidth="xl">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-lg bg-surface-bg border border-border-default">
                <div>
                    <p class="text-sm font-medium text-text-primary">CSV Template</p>
                    <p class="text-xs text-text-muted">Download the formatted template with required headers (`name`, `email`).</p>
                </div>
                <a
                    href="{{ route('admin.students.csv-template') }}"
                    class="btn btn-secondary btn-sm shrink-0"
                >
                    Download Template
                </a>
            </div>

            <form wire:submit="previewCsvImport" class="space-y-3">
                <div>
                    <label for="csvFile" class="form-label">Upload CSV File</label>
                    <input
                        id="csvFile"
                        type="file"
                        wire:model="csvFile"
                        accept=".csv,text/csv,text/plain"
                        class="form-input"
                    />
                    @error('csvFile')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <x-button variant="secondary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="previewCsvImport">Preview CSV</span>
                        <span wire:loading wire:target="previewCsvImport">Parsing...</span>
                    </x-button>
                </div>
            </form>

            @if ($importResult !== null)
                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm">
                    Successfully imported <strong>{{ $importResult['imported'] ?? 0 }}</strong> student(s).
                </div>
            @endif

            @if ($importPreview !== null)
                <div class="space-y-4 pt-2 border-t border-border-default">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-2.5 rounded-lg border border-border-default">
                            <div class="text-xs text-text-muted">Total Rows</div>
                            <div class="text-lg font-bold text-text-primary">{{ $importPreview['summary']['total'] ?? 0 }}</div>
                        </div>
                        <div class="p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                            <div class="text-xs text-emerald-700 dark:text-emerald-300">Valid</div>
                            <div class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ $importPreview['summary']['valid'] ?? 0 }}</div>
                        </div>
                        <div class="p-2.5 rounded-lg border border-red-200 dark:border-red-800">
                            <div class="text-xs text-red-700 dark:text-red-300">Errors</div>
                            <div class="text-lg font-bold text-red-700 dark:text-red-300">{{ $importPreview['summary']['errors'] ?? 0 }}</div>
                        </div>
                    </div>

                    @if (! empty($importPreview['error_rows']))
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-red-600 mb-2">Validation Errors</h4>
                            <div class="max-h-40 overflow-y-auto border border-red-200 dark:border-red-900 rounded-lg divide-y divide-border-default">
                                @foreach ($importPreview['error_rows'] as $errRow)
                                    <div class="p-2.5 text-xs">
                                        <span class="font-semibold text-text-primary">Row {{ $errRow['row_number'] }}:</span>
                                        <span class="text-red-600 dark:text-red-400">{{ implode(' ', $errRow['errors']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($importPreview['valid_rows']))
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-emerald-600 mb-2">Valid Rows Preview</h4>
                            <div class="max-h-40 overflow-y-auto border border-border-default rounded-lg">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-border-default text-left text-text-muted">
                                            <th class="p-2">Row</th>
                                            <th class="p-2">Name</th>
                                            <th class="p-2">Email</th>
                                            <th class="p-2">Level</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-default">
                                        @foreach ($importPreview['valid_rows'] as $validRow)
                                            <tr>
                                                <td class="p-2">{{ $validRow['row_number'] }}</td>
                                                <td class="p-2">{{ $validRow['data']['name'] }}</td>
                                                <td class="p-2">{{ $validRow['data']['email'] }}</td>
                                                <td class="p-2">{{ $validRow['data']['level'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-button variant="primary" type="button" wire:click="confirmCsvImport" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="confirmCsvImport">
                                    Confirm Import ({{ count($importPreview['valid_rows']) }})
                                </span>
                                <span wire:loading wire:target="confirmCsvImport">Importing...</span>
                            </x-button>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <x-slot:footer>
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'student-import-modal')">
                Close
            </x-button>
        </x-slot:footer>
    </x-modal>
</div>
