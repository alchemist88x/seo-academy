<div class="space-y-6">
    <x-page-header
        title="Students"
        :subtitle="'Manage student accounts, proficiency classifications, academic records, and bulk CSV workflows for ' . $academy->name . '.'"
    >
        <x-slot:actions>
            @can('export', [\App\Domain\Students\Models\StudentProfile::class, $academy])
                <x-button variant="outline" wire:click="exportCsv" wire:loading.attr="disabled" class="gap-1.5">
                    <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                    <span wire:loading wire:target="exportCsv">Exporting...</span>
                </x-button>
            @endcan

            @can('import', [\App\Domain\Students\Models\StudentProfile::class, $academy])
                <x-button variant="secondary" wire:click="openImportModal" class="gap-1.5">
                    <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    Import CSV
                </x-button>
            @endcan

            @can('create', [\App\Domain\Students\Models\StudentProfile::class, $academy])
                <x-button variant="primary" wire:click="openCreateModal" class="gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Student
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Search and Filters Card --}}
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            {{-- Search Input --}}
            <div class="lg:col-span-2">
                <x-input
                    name="search"
                    label="Search Students"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, email, phone, ID, or batch..."
                />
            </div>

            {{-- Status Filter --}}
            <div>
                <x-select
                    name="status"
                    label="Account Status"
                    wire:model.live="status"
                    placeholder="All Statuses"
                    :options="[
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'deleted' => 'Deleted',
                    ]"
                />
            </div>

            {{-- Proficiency Filter --}}
            <div>
                <x-select
                    name="proficiency"
                    label="Proficiency Level"
                    wire:model.live="proficiency"
                    placeholder="All Proficiencies"
                    :options="$proficiencies"
                />
            </div>

            {{-- Enrolled Course Filter --}}
            <div>
                <label for="course-filter" class="form-label text-xs">Enrolled Course</label>
                <select
                    id="course-filter"
                    wire:model.live="courseId"
                    class="form-select text-xs w-full"
                >
                    <option value="">All Courses</option>
                    @foreach ($availableCourses as $course)
                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Page Size --}}
            <div>
                <x-select
                    name="perPage"
                    label="Per Page"
                    wire:model.live="perPage"
                    :options="[
                        15 => '15 per page',
                        25 => '25 per page',
                        50 => '50 per page',
                        100 => '100 per page',
                    ]"
                />
            </div>
        </div>

        {{-- Active Filters & Reset Bar --}}
        @if ($search !== '' || $status !== '' || $proficiency !== '' || $level !== '' || $batch !== '' || $courseId !== '' || $perPage !== 15)
            <div class="mt-3 pt-3 border-t border-border-default/60 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-text-muted font-medium">Active Filters:</span>
                    @if ($search !== '')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-surface-elevated text-text-primary text-[11px] border border-border-default">
                            Query: "{{ $search }}"
                        </span>
                    @endif
                    @if ($status !== '')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-surface-elevated text-text-primary text-[11px] border border-border-default capitalize">
                            Status: {{ $status }}
                        </span>
                    @endif
                    @if ($proficiency !== '')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-surface-elevated text-text-primary text-[11px] border border-border-default capitalize">
                            Proficiency: {{ $proficiencies[$proficiency] ?? $proficiency }}
                        </span>
                    @endif
                    @if ($courseId !== '')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-surface-elevated text-text-primary text-[11px] border border-border-default">
                            Course Selected
                        </span>
                    @endif
                </div>

                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-semibold text-primary-600 hover:text-primary-700 hover:underline cursor-pointer"
                >
                    Clear All Filters
                </button>
            </div>
        @endif
    </x-card>

    {{-- Students Table Card --}}
    <x-card :padding="false">
        @if ($students->isEmpty())
            <x-empty-state
                title="No students found"
                message="No student accounts match your current query or filter criteria."
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
                    <th>Student ID</th>
                    <th>Proficiency</th>
                    <th>Batch / Cohort</th>
                    <th>Courses</th>
                    <th>Enrolled</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($students as $student)
                        <tr wire:key="student-row-{{ $student->id }}">
                            <td>
                                <div class="font-semibold text-text-primary">{{ $student->user?->name ?? 'Unknown Student' }}</div>
                                <div class="text-xs text-text-muted font-mono">{{ $student->user?->email ?? '—' }}</div>
                            </td>
                            <td class="text-xs text-text-secondary">
                                {{ $student->user?->phone ?: '—' }}
                            </td>
                            <td class="font-mono text-xs text-text-secondary">
                                {{ $student->student_code }}
                            </td>
                            <td>
                                <x-badge :color="$student->proficiency_badge_color" size="sm">
                                    {{ $student->proficiency_label }}
                                </x-badge>
                            </td>
                            <td class="text-xs text-text-secondary">
                                {{ $student->batch ?: '—' }}
                            </td>
                            <td>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface-elevated text-text-secondary border border-border-default/60">
                                    {{ $student->enrollments_count ?? 0 }} {{ \Illuminate\Support\Str::plural('course', $student->enrollments_count ?? 0) }}
                                </span>
                            </td>
                            <td class="text-xs text-text-secondary">
                                {{ $student->enrolled_at?->format('M d, Y') ?? '—' }}
                            </td>
                            <td>
                                @if ($student->trashed())
                                    <x-badge color="red" size="sm">
                                        Deleted
                                    </x-badge>
                                @else
                                    <x-badge :color="$student->active ? 'green' : 'red'" size="sm">
                                        {{ $student->active ? 'Active' : 'Suspended' }}
                                    </x-badge>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @can('view', $student)
                                        <x-button variant="ghost" size="xs" wire:click="openViewModal('{{ $student->id }}')" title="View Student Profile">
                                            View
                                        </x-button>
                                    @endcan

                                    @if ($student->trashed())
                                        @can('restore', $student)
                                            <x-button
                                                variant="primary"
                                                size="xs"
                                                wire:click="restoreStudent('{{ $student->id }}')"
                                                title="Restore Student Account"
                                            >
                                                Restore
                                            </x-button>
                                        @endcan
                                    @else
                                        @can('update', $student)
                                            <x-button variant="secondary" size="xs" wire:click="openEditModal('{{ $student->id }}')" title="Edit Student Profile">
                                                Edit
                                            </x-button>

                                            @if ($student->active)
                                                <x-button
                                                    variant="ghost"
                                                    size="xs"
                                                    wire:click="promptSuspend('{{ $student->id }}')"
                                                    title="Suspend Account Access"
                                                    class="text-amber-500 hover:text-amber-600"
                                                >
                                                    Suspend
                                                </x-button>
                                            @else
                                                <x-button
                                                    variant="ghost"
                                                    size="xs"
                                                    wire:click="toggleActive('{{ $student->id }}')"
                                                    title="Reactivate Account Access"
                                                    class="text-emerald-500 hover:text-emerald-600"
                                                >
                                                    Reactivate
                                                </x-button>
                                            @endif
                                        @endcan

                                        @can('delete', $student)
                                            <x-button
                                                variant="danger"
                                                size="xs"
                                                wire:click="openDeleteModal('{{ $student->id }}')"
                                                title="Delete Student Account"
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

            <div class="px-5 py-3 border-t border-border-default flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-text-muted">
                <div>
                    Showing <strong>{{ $students->firstItem() ?? 0 }}</strong> to <strong>{{ $students->lastItem() ?? 0 }}</strong> of <strong>{{ $students->total() }}</strong> students
                </div>
                <div>
                    {{ $students->links() }}
                </div>
            </div>
        @endif
    </x-card>

    {{-- Create / Edit Student Modal --}}
    <x-modal
        name="student-form-modal"
        :title="$editingStudentId ? 'Edit Student Profile' : 'Add New Student'"
        :description="$editingStudentId ? 'Update student account details, proficiency tier, and enrollment status.' : 'Register a new student account with course enrollment and proficiency classification.'"
        maxWidth="xl"
    >
        <form id="student-form" wire:submit="saveStudent" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
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
                    label="Phone Number"
                    wire:model="phone"
                    placeholder="+1 (555) 000-0000"
                />

                {{-- Proficiency Classification Selector --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-text-primary mb-1.5">
                        Student Proficiency Classification <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($proficiencies as $key => $label)
                            <label
                                for="prof-opt-{{ $key }}"
                                class="p-2.5 rounded-lg border text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-1
                                    {{ $formProficiency === $key ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover' }}"
                            >
                                <input
                                    id="prof-opt-{{ $key }}"
                                    type="radio"
                                    name="formProficiency"
                                    value="{{ $key }}"
                                    wire:model="formProficiency"
                                    class="sr-only"
                                />
                                <span class="text-xs text-text-primary capitalize">{{ $label }}</span>
                                <span class="text-[10px] text-text-muted">
                                    {{ $key === 'beginner' ? 'Foundational' : ($key === 'intermediate' ? 'Practitioner' : 'Advanced Specialist') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('formProficiency')
                        <p class="text-[11px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

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
                    label="Student ID / Code"
                    wire:model="student_code"
                    placeholder="Auto-generated if empty"
                    hint="Unique student identifier (e.g. ACAD-2026-X89J2)."
                />

                <x-input
                    name="batch_input"
                    label="Batch / Cohort"
                    wire:model="batch_input"
                    placeholder="e.g. Cohort 2026-A"
                />

                <x-input
                    name="enrolled_at"
                    label="Enrolled Date"
                    type="date"
                    wire:model="enrolled_at"
                />

                <div class="sm:col-span-2">
                    <x-input
                        name="password"
                        :label="$editingStudentId ? 'Change Password (Optional)' : 'Initial Password (Optional)'"
                        type="password"
                        wire:model="password"
                        :hint="$editingStudentId ? 'Leave blank to preserve current password.' : 'Leave blank to generate a secure random password.'"
                    />
                </div>
            </div>

            <div>
                <label for="student-notes" class="form-label text-xs">Internal Notes</label>
                <textarea
                    id="student-notes"
                    rows="3"
                    wire:model="notes"
                    class="form-input text-xs @error('notes') error @enderror"
                    placeholder="Administrative observations, background notes, learning goals..."
                ></textarea>
                @error('notes')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        </form>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2.5 w-full">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'student-form-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" form="student-form" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveStudent">
                        {{ $editingStudentId ? 'Save Profile Changes' : 'Create Student Account' }}
                    </span>
                    <span wire:loading wire:target="saveStudent">Saving...</span>
                </x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    {{-- View Student Profile Modal --}}
    <x-modal name="student-view-modal" title="Student Profile &amp; Academic Transcript" maxWidth="lg">
        @if ($viewingStudent)
            <div class="space-y-4 text-sm">
                {{-- Header with Identity & Actions --}}
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-base font-bold text-text-primary">{{ $viewingStudent->user?->name }}</h4>
                            <x-badge :color="$viewingStudent->proficiency_badge_color" size="xs">
                                {{ $viewingStudent->proficiency_label }}
                            </x-badge>
                        </div>
                        <p class="text-xs text-text-muted font-mono">{{ $viewingStudent->user?->email }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge :color="$viewingStudent->active ? 'green' : 'red'">
                            {{ $viewingStudent->active ? 'Active' : 'Suspended' }}
                        </x-badge>
                        <x-button variant="secondary" size="xs" wire:click="openEditModal('{{ $viewingStudent->id }}')">
                            Edit
                        </x-button>
                    </div>
                </div>

                {{-- Personal & Academic Meta Info --}}
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-surface-elevated/40 border border-border-default/60">
                    <div>
                        <dt class="text-[11px] text-text-muted">Student ID</dt>
                        <dd class="font-mono text-xs text-text-primary font-bold">{{ $viewingStudent->student_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-text-muted">Phone Number</dt>
                        <dd class="text-xs text-text-primary">{{ $viewingStudent->user?->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-text-muted">Proficiency</dt>
                        <dd class="text-xs font-semibold text-text-primary capitalize">{{ $viewingStudent->proficiency_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-text-muted">Batch / Cohort</dt>
                        <dd class="text-xs text-text-primary">{{ $viewingStudent->batch ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-text-muted">Enrolled Since</dt>
                        <dd class="text-xs text-text-primary">{{ $viewingStudent->enrolled_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-text-muted">Total Enrollments</dt>
                        <dd class="text-xs text-text-primary font-bold">{{ $viewingStudent->enrollments_count ?? $viewingStudent->enrollments->count() }} courses</dd>
                    </div>
                </dl>

                @if ($viewingStudent->notes)
                    <div class="p-3 rounded-lg bg-surface-elevated/20 border border-border-default text-xs">
                        <p class="font-semibold text-text-primary mb-1">Internal Notes</p>
                        <p class="text-text-secondary whitespace-pre-line">{{ $viewingStudent->notes }}</p>
                    </div>
                @endif

                {{-- Course Enrollments & Progress Section --}}
                <div class="pt-2 border-t border-border-default space-y-2">
                    <div class="flex items-center justify-between">
                        <h5 class="text-xs font-bold uppercase tracking-wider text-text-muted">Enrolled Courses &amp; Progress</h5>
                        <a href="{{ route('admin.enrollments.index') }}" class="text-xs text-primary-600 hover:underline">
                            Manage All Enrollments &rarr;
                        </a>
                    </div>

                    @if ($viewingStudent->enrollments->isEmpty())
                        <p class="text-xs text-text-muted italic py-2">No course enrollments found for this student.</p>
                    @else
                        <div class="divide-y divide-border-default rounded-xl border border-border-default overflow-hidden text-xs">
                            @foreach ($viewingStudent->enrollments as $enrollment)
                                <div class="p-3 bg-surface-card flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <p class="font-bold text-text-primary">{{ $enrollment->course?->title ?? 'Course #' . $enrollment->course_id }}</p>
                                        <p class="text-[11px] text-text-muted">
                                            Status: <span class="capitalize font-medium">{{ $enrollment->status }}</span> &bull;
                                            Enrolled: {{ $enrollment->enrolled_at?->format('M d, Y') ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if ($enrollment->currentGrade)
                                            <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20">
                                                Grade: {{ $enrollment->currentGrade->letter_grade ?? $enrollment->currentGrade->percentage . '%' }}
                                            </span>
                                        @endif
                                        <x-badge :color="match($enrollment->status) { 'active' => 'green', 'completed' => 'blue', 'suspended' => 'red', default => 'gray' }" size="xs">
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
                    <div class="pt-2 border-t border-border-default space-y-2">
                        <h5 class="text-xs font-bold uppercase tracking-wider text-text-muted">Earned Academic Certificates</h5>
                        <div class="divide-y divide-border-default rounded-xl border border-border-default overflow-hidden text-xs">
                            @foreach ($viewingStudent->user->certificates as $cert)
                                <div class="p-3 flex items-center justify-between bg-surface-card">
                                    <div>
                                        <p class="font-mono font-bold text-text-primary">{{ $cert->certificate_number }}</p>
                                        <p class="text-text-muted text-[11px]">Issued {{ $cert->issued_at?->format('M d, Y') ?? '—' }}</p>
                                    </div>
                                    <x-badge color="purple" size="xs">Verified Certificate</x-badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Self-Enrollment & Gateway Compatibility Notice --}}
                <div class="p-3 rounded-lg bg-surface-elevated/30 border border-border-default/60 text-[11px] text-text-secondary flex items-center justify-between">
                    <span>Self-Enrollment Status: <strong class="text-emerald-500 font-semibold">Eligible</strong></span>
                    <span class="text-text-muted">Payment Integration: <strong>Razorpay Ready</strong></span>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                <a href="{{ route('admin.enrollments.index') }}" class="btn btn-secondary btn-sm text-xs">
                    Assign Course
                </a>
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'student-view-modal')">
                    Close
                </x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    {{-- Confirmation Modal for Destructive Suspend Action --}}
    <x-modal name="confirm-suspend-modal" title="Confirm Student Suspension" maxWidth="sm">
        @if ($confirmingSuspendProfile)
            <div class="space-y-3 text-sm">
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 text-xs flex items-start gap-2">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <span class="font-bold block">Access Revocation Notice</span>
                        <span>Suspending this account will immediately block the student from accessing learning rooms, classroom players, quizzes, and assignment workspaces.</span>
                    </div>
                </div>

                <p class="text-xs text-text-secondary">
                    Are you sure you want to suspend <strong class="text-text-primary">{{ $confirmingSuspendProfile->user?->name }}</strong> (<code class="font-mono text-xs">{{ $confirmingSuspendProfile->student_code }}</code>)? All existing enrollment records and grade history will remain safely preserved.
                </p>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2 w-full">
                <x-button variant="secondary" type="button" wire:click="cancelSuspend">
                    Cancel
                </x-button>
                <x-button variant="danger" type="button" wire:click="confirmSuspendAction" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirmSuspendAction">Suspend Student</span>
                    <span wire:loading wire:target="confirmSuspendAction">Processing...</span>
                </x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    {{-- CSV Bulk Import Modal --}}
    <x-modal name="student-import-modal" title="Bulk CSV Student Import" maxWidth="xl">
        <div class="space-y-4">
            {{-- Template Download Callout --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-surface-elevated/40 border border-border-default">
                <div>
                    <p class="text-sm font-bold text-text-primary">1. Download CSV Import Template</p>
                    <p class="text-xs text-text-muted mt-0.5">
                        Includes formatted headers and documented proficiency examples (<code>beginner</code>, <code>intermediate</code>, <code>expert</code>).
                    </p>
                </div>
                <a
                    href="{{ route('admin.students.csv-template') }}"
                    class="btn btn-secondary btn-sm shrink-0 gap-1.5"
                >
                    <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Template
                </a>
            </div>

            {{-- File Upload Form --}}
            <form wire:submit="previewCsvImport" class="space-y-3">
                <div>
                    <label for="csvFile" class="form-label text-xs font-bold text-text-primary">2. Upload Prepared CSV File</label>
                    <input
                        id="csvFile"
                        type="file"
                        wire:model="csvFile"
                        accept=".csv,text/csv,text/plain"
                        class="form-input text-xs"
                    />
                    @error('csvFile')
                        <p class="text-xs text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <x-button variant="secondary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="previewCsvImport">Preview &amp; Validate CSV</span>
                        <span wire:loading wire:target="previewCsvImport">Validating Rows...</span>
                    </x-button>
                </div>
            </form>

            {{-- Success Result Notification --}}
            @if ($importResult !== null)
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Successfully imported <strong>{{ $importResult['imported'] ?? 0 }}</strong> student profile(s) into {{ $academy->name }}.</span>
                </div>
            @endif

            {{-- Preview Stats & Tables --}}
            @if ($importPreview !== null)
                <div class="space-y-4 pt-2 border-t border-border-default">
                    {{-- 3-Box Stats Overview --}}
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-3 rounded-xl border border-border-default bg-surface-card">
                            <div class="text-xs text-text-muted">Total Rows</div>
                            <div class="text-xl font-bold text-text-primary">{{ $importPreview['summary']['total'] ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10">
                            <div class="text-xs text-emerald-400 font-semibold">Valid Rows</div>
                            <div class="text-xl font-bold text-emerald-400">{{ $importPreview['summary']['valid'] ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded-xl border border-rose-500/30 bg-rose-500/10">
                            <div class="text-xs text-rose-400 font-semibold">Errors / Rejected</div>
                            <div class="text-xl font-bold text-rose-400">{{ $importPreview['summary']['errors'] ?? 0 }}</div>
                        </div>
                    </div>

                    {{-- Rejected Rows Section with Download Action --}}
                    @if (! empty($importPreview['error_rows']))
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-rose-400">
                                    Rejected Rows ({{ count($importPreview['error_rows']) }})
                                </h4>
                                <button
                                    type="button"
                                    wire:click="downloadRejectedRows"
                                    class="text-xs font-semibold text-rose-400 hover:text-rose-300 underline cursor-pointer"
                                >
                                    Download Rejected Rows (.csv) &rarr;
                                </button>
                            </div>
                            <div class="max-h-44 overflow-y-auto border border-rose-500/30 rounded-xl divide-y divide-border-default text-xs">
                                @foreach ($importPreview['error_rows'] as $errRow)
                                    <div class="p-2.5 bg-rose-500/5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-text-primary">Row {{ $errRow['row_number'] }}: {{ $errRow['raw']['name'] ?? '—' }} ({{ $errRow['raw']['email'] ?? '—' }})</span>
                                        </div>
                                        <p class="text-rose-400 text-[11px] mt-0.5">{{ implode(' • ', $errRow['errors']) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Valid Rows Preview Section --}}
                    @if (! empty($importPreview['valid_rows']))
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400">
                                Ready to Import ({{ count($importPreview['valid_rows']) }})
                            </h4>
                            <div class="max-h-44 overflow-y-auto border border-border-default rounded-xl">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-border-default text-left text-text-muted bg-surface-elevated/30">
                                            <th class="p-2">Row</th>
                                            <th class="p-2">Name</th>
                                            <th class="p-2">Email</th>
                                            <th class="p-2">Proficiency</th>
                                            <th class="p-2">Batch</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-default">
                                        @foreach ($importPreview['valid_rows'] as $validRow)
                                            <tr class="hover:bg-surface-elevated/20">
                                                <td class="p-2 font-mono text-text-muted">{{ $validRow['row_number'] }}</td>
                                                <td class="p-2 font-semibold text-text-primary">{{ $validRow['data']['name'] }}</td>
                                                <td class="p-2 font-mono text-text-muted">{{ $validRow['data']['email'] }}</td>
                                                <td class="p-2">
                                                    <span class="capitalize px-1.5 py-0.5 rounded text-[10px] bg-primary-500/10 text-primary-400 border border-primary-500/20 font-semibold">
                                                        {{ $validRow['data']['proficiency'] }}
                                                    </span>
                                                </td>
                                                <td class="p-2 text-text-secondary">{{ $validRow['data']['batch'] ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex justify-end pt-2">
                                <x-button variant="primary" type="button" wire:click="confirmCsvImport" wire:loading.attr="disabled" class="gap-1.5">
                                    <span wire:loading.remove wire:target="confirmCsvImport">
                                        Confirm &amp; Import {{ count($importPreview['valid_rows']) }} Student(s)
                                    </span>
                                    <span wire:loading wire:target="confirmCsvImport">Creating Accounts...</span>
                                </x-button>
                            </div>
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

    {{-- Delete Student Confirmation Modal --}}
    <x-modal
        name="student-delete-modal"
        title="Delete Student Account"
        description="Safely deactivate and soft-delete student profile while preserving historical academic records."
        maxWidth="md"
    >
        @if ($deletingStudentProfile)
            <div class="space-y-4">
                <div class="p-3.5 rounded-lg bg-surface-elevated border border-border-default space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Student:</span>
                        <span class="font-semibold text-text-primary">{{ $deletingStudentProfile->user?->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Email:</span>
                        <span class="font-mono text-text-secondary">{{ $deletingStudentProfile->user?->email }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Student ID:</span>
                        <span class="font-mono text-text-secondary">{{ $deletingStudentProfile->student_code }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Proficiency:</span>
                        <span class="capitalize text-text-primary font-medium">{{ $deletingStudentProfile->proficiency_label }}</span>
                    </div>
                </div>

                {{-- Related Record Impact Summary --}}
                <div class="p-3.5 rounded-lg border border-amber-500/30 bg-amber-500/10 space-y-2">
                    <div class="flex items-center gap-2 text-xs font-semibold text-amber-500">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Related Records &amp; Academic History Impact:
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2 rounded bg-surface-card border border-border-default/60">
                            <div class="text-text-muted text-[11px]">Enrollments</div>
                            <div class="font-bold text-text-primary text-sm">{{ $deletingStudentImpact['enrollments_count'] ?? 0 }}</div>
                        </div>
                        <div class="p-2 rounded bg-surface-card border border-border-default/60">
                            <div class="text-text-muted text-[11px]">Assessment Attempts</div>
                            <div class="font-bold text-text-primary text-sm">{{ $deletingStudentImpact['assessment_attempts_count'] ?? 0 }}</div>
                        </div>
                        <div class="p-2 rounded bg-surface-card border border-border-default/60">
                            <div class="text-text-muted text-[11px]">Assignment Submissions</div>
                            <div class="font-bold text-text-primary text-sm">{{ $deletingStudentImpact['assignment_submissions_count'] ?? 0 }}</div>
                        </div>
                        <div class="p-2 rounded bg-surface-card border border-border-default/60">
                            <div class="text-text-muted text-[11px]">Certificates Issued</div>
                            <div class="font-bold text-text-primary text-sm">{{ $deletingStudentImpact['certificates_count'] ?? 0 }}</div>
                        </div>
                    </div>
                    <p class="text-[11px] text-text-secondary leading-relaxed pt-1">
                        <strong>Consequences:</strong> Deleting this student account immediately removes authentication and login access. All enrolled courses, submissions, grades, transcripts, and certificates are permanently preserved. Only administrators can restore this account.
                    </p>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2.5 w-full">
                <x-button variant="secondary" type="button" wire:click="cancelDelete">
                    Cancel
                </x-button>
                <x-button variant="danger" type="button" wire:click="confirmDeleteStudent" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirmDeleteStudent">Delete Student Account</span>
                    <span wire:loading wire:target="confirmDeleteStudent">Deleting...</span>
                </x-button>
            </div>
        </x-slot:footer>
    </x-modal>
</div>
