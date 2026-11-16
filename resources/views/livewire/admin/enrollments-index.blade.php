<div class="space-y-6">
    <x-page-header
        title="Enrollment Management"
        :subtitle="'Manage student course enrollments, statuses, validity dates, and assignments for ' . $academy->name . '.'"
    >
        <x-slot:actions>
            <x-button variant="secondary" wire:click="openBulkAssignModal">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Bulk Assign
            </x-button>

            <x-button variant="primary" wire:click="openSingleAssignModal">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Assign Student
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            label="Total Enrollments"
            :value="(string) $metrics['total']"
            trend="All time"
            trendDirection="neutral"
        />

        <x-stat-card
            label="Active Enrollments"
            :value="(string) $metrics['active']"
            trend="Valid access"
            trendDirection="up"
        />

        <x-stat-card
            label="Pending Enrollments"
            :value="(string) $metrics['pending']"
            trend="Awaiting activation"
            trendDirection="neutral"
        />

        <x-stat-card
            label="Expired / Suspended"
            :value="(string) $metrics['expired_suspended']"
            trend="Needs review"
            trendDirection="down"
        />
    </div>

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <x-input
                name="search"
                label="Search"
                wire:model.live.debounce.300ms="search"
                placeholder="Student name, email, code, or course title..."
            />

            <x-select
                name="statusFilter"
                label="Status"
                wire:model.live="statusFilter"
                placeholder="All Statuses"
                :options="[
                    'active' => 'Active',
                    'pending' => 'Pending',
                    'suspended' => 'Suspended',
                    'completed' => 'Completed',
                    'expired' => 'Expired',
                    'cancelled' => 'Cancelled',
                ]"
            />

            <x-select
                name="courseFilter"
                label="Course"
                wire:model.live="courseFilter"
                placeholder="All Courses"
                :options="$courses->pluck('title', 'id')->all()"
            />

            <x-select
                name="trainerFilter"
                label="Trainer"
                wire:model.live="trainerFilter"
                placeholder="All Trainers"
                :options="$trainers->mapWithKeys(fn ($t) => [$t->user_id => $t->user?->name ?? 'Trainer'])->all()"
            />
        </div>

        @if ($search !== '' || $statusFilter !== '' || $courseFilter !== '' || $trainerFilter !== '')
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

    {{-- Enrollments Table --}}
    <x-card :padding="false">
        @if ($enrollments->isEmpty())
            <x-empty-state
                title="No enrollments found"
                message="No student enrollments match your current search or filter criteria."
            >
                <x-slot:action>
                    <x-button variant="primary" size="sm" wire:click="openSingleAssignModal">
                        Assign First Student
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th wire:click="sortBy('student_code')" class="cursor-pointer select-none">
                        Student
                        @if ($sortField === 'student_code')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th>Course</th>
                    <th wire:click="sortBy('starts_at')" class="cursor-pointer select-none">
                        Validity Dates
                        @if ($sortField === 'starts_at')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th wire:click="sortBy('status')" class="cursor-pointer select-none">
                        Status
                        @if ($sortField === 'status')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th>Assigned By</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($enrollments as $enrollment)
                        @php
                            $status = strtolower($enrollment->status ?? 'pending');
                            $badgeColor = match ($status) {
                                'active' => 'green',
                                'completed' => 'blue',
                                'suspended', 'pending' => 'yellow',
                                'cancelled' => 'red',
                                default => 'gray',
                            };
                            $student = $enrollment->studentProfile;
                            $user = $student?->user;
                            $course = $enrollment->course;
                        @endphp
                        <tr wire:key="enrollment-row-{{ $enrollment->id }}">
                            <td>
                                <div class="font-medium text-text-primary">{{ $user?->name ?? 'Unknown Student' }}</div>
                                <div class="text-xs text-text-muted flex items-center gap-2">
                                    <span>{{ $user?->email ?? '—' }}</span>
                                    @if ($student?->student_code)
                                        <span class="font-mono text-slate-400">({{ $student->student_code }})</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="font-medium text-text-primary line-clamp-1">{{ $course?->title ?? 'Unknown Course' }}</div>
                                <div class="text-xs text-text-muted">
                                    @if ($course?->trainers?->isNotEmpty())
                                        <span>Trainer: {{ $course->trainers->first()->name }}</span>
                                    @else
                                        <span>Self-paced</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-xs text-text-secondary whitespace-nowrap">
                                <div>
                                    <span class="text-text-muted">From:</span>
                                    {{ $enrollment->starts_at ? $enrollment->starts_at->format('M d, Y') : ($enrollment->enrolled_at ? $enrollment->enrolled_at->format('M d, Y') : 'Immediate') }}
                                </div>
                                <div>
                                    <span class="text-text-muted">To:</span>
                                    {{ $enrollment->expires_at ? $enrollment->expires_at->format('M d, Y') : 'No Expiry' }}
                                </div>
                            </td>
                            <td>
                                <x-badge :color="$badgeColor">
                                    {{ ucfirst($status) }}
                                </x-badge>
                            </td>
                            <td class="text-xs text-text-secondary">
                                {{ $enrollment->assignedBy?->name ?? 'System' }}
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                    <x-button variant="ghost" size="sm" wire:click="openViewModal('{{ $enrollment->id }}')">
                                        View
                                    </x-button>

                                    @if ($status === 'pending')
                                        <x-button variant="primary" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'activate')">
                                            Activate
                                        </x-button>
                                        <x-button variant="danger" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'cancel')">
                                            Cancel
                                        </x-button>
                                    @elseif ($status === 'active')
                                        <x-button variant="secondary" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'suspend')">
                                            Suspend
                                        </x-button>
                                        <x-button variant="ghost" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'complete')">
                                            Complete
                                        </x-button>
                                        <x-button variant="ghost" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'extend_expiry')">
                                            Extend
                                        </x-button>
                                    @elseif ($status === 'suspended')
                                        <x-button variant="primary" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'reactivate')">
                                            Reactivate
                                        </x-button>
                                        <x-button variant="danger" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'cancel')">
                                            Cancel
                                        </x-button>
                                    @elseif ($status === 'expired')
                                        <x-button variant="primary" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'extend_expiry')">
                                            Extend / Reactivate
                                        </x-button>
                                    @elseif ($status === 'completed' || $status === 'cancelled')
                                        <x-button variant="secondary" size="sm" wire:click="openActionModal('{{ $enrollment->id }}', 'reactivate')">
                                            Reactivate
                                        </x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            @if ($enrollments->hasPages())
                <div class="px-5 py-3 border-t border-border-default">
                    {{ $enrollments->links() }}
                </div>
            @endif
        @endif
    </x-card>

    {{-- 1. Single Assign Modal --}}
    <x-modal name="enrollment-single-modal" title="Assign Student to Course" maxWidth="lg">
        <form wire:submit="saveSingleAssign" class="space-y-4">
            @if ($errors->has('assign_general'))
                <x-alert type="danger">
                    {{ $errors->first('assign_general') }}
                </x-alert>
            @endif

            <x-select
                name="assignStudentId"
                label="Student"
                wire:model.live="assignStudentId"
                placeholder="-- Select a student --"
                :options="$students->mapWithKeys(fn ($s) => [$s->id => ($s->user?->name ?? 'Unknown') . ' (' . ($s->student_code ?: $s->user?->email) . ')'])->all()"
                required
            />
            @error('assign_student')
                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
            @enderror

            <x-select
                name="assignCourseId"
                label="Course"
                wire:model.live="assignCourseId"
                placeholder="-- Select a course --"
                :options="$courses->mapWithKeys(fn ($c) => [$c->id => $c->title . ($c->enrollment_limit ? ' (Max: ' . $c->enrollment_limit . ')' : '')])->all()"
                required
            />
            @error('assign_course')
                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
            @enderror

            {{-- Warnings --}}
            @if ($assignWarningCapacity)
                <div class="p-3 rounded-md bg-amber-500/10 border border-amber-500/20 text-xs text-amber-500 flex items-start gap-2">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ $assignWarningCapacity }}</span>
                </div>
            @endif

            @if ($assignWarningPrerequisite)
                <div class="p-3 rounded-md bg-rose-500/10 border border-rose-500/20 text-xs text-rose-500 flex items-start gap-2">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ $assignWarningPrerequisite }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    type="date"
                    name="assignStartsAt"
                    label="Access Start Date"
                    wire:model="assignStartsAt"
                />

                <x-input
                    type="date"
                    name="assignExpiresAt"
                    label="Expiry Date (Optional)"
                    wire:model="assignExpiresAt"
                />
            </div>

            <x-select
                name="assignStatus"
                label="Initial Status"
                wire:model="assignStatus"
                :options="[
                    'active' => 'Active (Immediate access)',
                    'pending' => 'Pending (Activation required)',
                ]"
                required
            />

            <x-textarea
                name="assignNotes"
                label="Internal Admin Notes (Optional)"
                wire:model="assignNotes"
                rows="2"
                placeholder="Cohort notes, sponsorship details, or enrollment reason..."
            />

            <div class="pt-4 border-t border-border-default flex items-center justify-end gap-2">
                <x-button type="button" variant="secondary" wire:click="$dispatch('close-modal', 'enrollment-single-modal')">
                    Cancel
                </x-button>
                <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                    Assign Enrollment
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- 2. Bulk Assign Modal --}}
    <x-modal name="enrollment-bulk-modal" title="Bulk Enroll Students" maxWidth="xl">
        @if ($bulkSummary)
            <div class="space-y-4">
                <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-sm">
                    <h4 class="font-semibold text-emerald-400">Bulk Assignment Results</h4>
                    <p class="text-text-secondary mt-1">
                        Successfully assigned: <span class="font-bold text-emerald-400">{{ $bulkSummary['assigned_count'] }}</span> students.
                        Skipped: <span class="font-bold text-amber-400">{{ $bulkSummary['skipped_count'] }}</span> students.
                    </p>
                </div>

                @if ($bulkSummary['skipped_count'] > 0)
                    <div class="border border-border-default rounded-lg p-3 max-h-48 overflow-y-auto space-y-2 text-xs">
                        <div class="font-semibold text-text-primary">Skipped details:</div>
                        @foreach ($bulkSummary['skipped_details'] as $detail)
                            <div class="text-text-muted">
                                &bull; <span class="text-text-secondary font-medium">{{ $detail['student_name'] ?? 'Student' }}:</span> {{ $detail['reason'] }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end pt-3">
                    <x-button variant="primary" wire:click="$dispatch('close-modal', 'enrollment-bulk-modal')">
                        Close
                    </x-button>
                </div>
            </div>
        @else
            <form wire:submit="saveBulkAssign" class="space-y-4">
                @if ($errors->has('bulk_general'))
                    <x-alert type="danger">
                        {{ $errors->first('bulk_general') }}
                    </x-alert>
                @endif

                <x-select
                    name="bulkCourseId"
                    label="Target Course"
                    wire:model="bulkCourseId"
                    placeholder="-- Select course to assign --"
                    :options="$courses->pluck('title', 'id')->all()"
                    required
                />

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-text-primary">Select Students ({{ count($bulkStudentIds) }} selected)</label>
                        <button
                            type="button"
                            wire:click="toggleAllBulkStudents(@js($bulkStudents->pluck('id')->all()))"
                            class="text-xs text-primary-600 hover:underline"
                        >
                            {{ count($bulkStudentIds) === $bulkStudents->count() && $bulkStudents->count() > 0 ? 'Deselect All' : 'Select All Filtered' }}
                        </button>
                    </div>

                    <x-input
                        name="bulkStudentSearch"
                        wire:model.live.debounce.300ms="bulkStudentSearch"
                        placeholder="Search students in academy..."
                    />

                    <div class="max-h-48 overflow-y-auto border border-border-default rounded-lg divide-y divide-border-default bg-surface-primary p-2">
                        @forelse ($bulkStudents as $student)
                            <label class="flex items-center gap-2.5 py-1.5 px-2 hover:bg-border-muted/50 rounded cursor-pointer text-xs">
                                <input
                                    type="checkbox"
                                    value="{{ $student->id }}"
                                    wire:model="bulkStudentIds"
                                    class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                                />
                                <div class="flex-1 truncate">
                                    <span class="font-medium text-text-primary">{{ $student->user?->name }}</span>
                                    <span class="text-text-muted">({{ $student->user?->email }})</span>
                                </div>
                                <span class="font-mono text-text-muted">{{ $student->student_code }}</span>
                            </label>
                        @empty
                            <div class="py-4 text-center text-xs text-text-muted">
                                No active students match search.
                            </div>
                        @endforelse
                    </div>
                    @error('bulkStudentIds')
                        <p class="text-xs text-rose-500 mt-1">Please select at least one student.</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input
                        type="date"
                        name="bulkStartsAt"
                        label="Access Start Date"
                        wire:model="bulkStartsAt"
                    />

                    <x-input
                        type="date"
                        name="bulkExpiresAt"
                        label="Expiry Date (Optional)"
                        wire:model="bulkExpiresAt"
                    />
                </div>

                <x-select
                    name="bulkStatus"
                    label="Initial Status"
                    wire:model="bulkStatus"
                    :options="[
                        'active' => 'Active',
                        'pending' => 'Pending',
                    ]"
                    required
                />

                <x-textarea
                    name="bulkNotes"
                    label="Notes for Bulk Batch (Optional)"
                    wire:model="bulkNotes"
                    rows="2"
                    placeholder="Batch name, intake group, or assignment remarks..."
                />

                <div class="pt-4 border-t border-border-default flex items-center justify-end gap-2">
                    <x-button type="button" variant="secondary" wire:click="$dispatch('close-modal', 'enrollment-bulk-modal')">
                        Cancel
                    </x-button>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                        Enroll Selected Students ({{ count($bulkStudentIds) }})
                    </x-button>
                </div>
            </form>
        @endif
    </x-modal>

    {{-- 3. Action Modal (Activate / Suspend / Reactivate / Cancel / Complete / Extend) --}}
    <x-modal name="enrollment-action-modal" :title="'Update Enrollment: ' . ucfirst(str_replace('_', ' ', $actionType))" maxWidth="md">
        @if ($selectedEnrollment)
            <form wire:submit="executeAction" class="space-y-4">
                @if ($errors->has('action_general'))
                    <x-alert type="danger">
                        {{ $errors->first('action_general') }}
                    </x-alert>
                @endif

                <div class="p-3 rounded-lg bg-border-muted/50 text-xs space-y-1">
                    <div><span class="text-text-muted">Student:</span> <span class="font-medium text-text-primary">{{ $selectedEnrollment->studentProfile?->user?->name }}</span></div>
                    <div><span class="text-text-muted">Course:</span> <span class="font-medium text-text-primary">{{ $selectedEnrollment->course?->title }}</span></div>
                    <div><span class="text-text-muted">Current Status:</span> <span class="font-medium text-text-primary">{{ ucfirst($selectedEnrollment->status) }}</span></div>
                </div>

                @if ($actionType === 'suspend')
                    <p class="text-xs text-text-secondary">
                        Suspending this enrollment will immediately block the student from accessing private course videos, materials, and modules.
                    </p>
                    <x-textarea
                        name="actionReason"
                        label="Suspension Reason (Required)"
                        wire:model="actionReason"
                        rows="2"
                        placeholder="State reason for suspension (e.g. tuition hold, policy infraction)..."
                        required
                    />
                    @error('actionReason')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                @elseif ($actionType === 'cancel')
                    <p class="text-xs text-rose-400">
                        Cancelling this enrollment revokes course access.
                    </p>
                    <x-textarea
                        name="actionReason"
                        label="Cancellation Reason (Required)"
                        wire:model="actionReason"
                        rows="2"
                        placeholder="State reason for cancellation..."
                        required
                    />
                    @error('actionReason')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                @elseif ($actionType === 'extend_expiry')
                    <p class="text-xs text-text-secondary">
                        Set a new expiration date for this student. If the enrollment was expired, it will automatically reactivate to active status.
                    </p>
                    <x-input
                        type="date"
                        name="actionNewExpiry"
                        label="New Expiration Date"
                        wire:model="actionNewExpiry"
                        required
                    />
                    @error('actionNewExpiry')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                @elseif ($actionType === 'activate' || $actionType === 'reactivate')
                    <p class="text-xs text-text-secondary">
                        Are you sure you want to activate access for this student? They will receive access to all published course materials and video lessons.
                    </p>
                @elseif ($actionType === 'complete')
                    <p class="text-xs text-text-secondary">
                        Mark this student's enrollment as completed.
                    </p>
                @endif

                <div class="pt-4 border-t border-border-default flex items-center justify-end gap-2">
                    <x-button type="button" variant="secondary" wire:click="$dispatch('close-modal', 'enrollment-action-modal')">
                        Cancel
                    </x-button>
                    <x-button type="submit" :variant="in_array($actionType, ['suspend', 'cancel']) ? 'danger' : 'primary'" wire:loading.attr="disabled">
                        Confirm {{ ucfirst(str_replace('_', ' ', $actionType)) }}
                    </x-button>
                </div>
            </form>
        @endif
    </x-modal>

    {{-- 4. View Details Slide-over / Modal --}}
    <x-modal name="enrollment-view-modal" title="Enrollment Details &amp; Audit Log" maxWidth="xl">
        @if ($viewingEnrollment)
            @php
                $vstatus = strtolower($viewingEnrollment->status ?? 'pending');
                $vbadgeColor = match ($vstatus) {
                    'active' => 'green',
                    'completed' => 'blue',
                    'suspended', 'pending' => 'yellow',
                    'cancelled' => 'red',
                    default => 'gray',
                };
                $vstudent = $viewingEnrollment->studentProfile;
                $vuser = $vstudent?->user;
                $vcourse = $viewingEnrollment->course;
            @endphp
            <div class="space-y-6 text-sm">
                {{-- Header Summary --}}
                <div class="flex items-center justify-between pb-4 border-b border-border-default">
                    <div>
                        <h4 class="text-lg font-bold text-text-primary">{{ $vuser?->name }}</h4>
                        <div class="text-xs text-text-muted font-mono">Code: {{ $vstudent?->student_code ?? '—' }} &bull; {{ $vuser?->email }}</div>
                    </div>
                    <x-badge :color="$vbadgeColor" size="lg">
                        {{ ucfirst($vstatus) }}
                    </x-badge>
                </div>

                {{-- Course & Lifecycle Information Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-3 rounded-lg border border-border-default bg-surface-primary space-y-2">
                        <div class="text-xs font-semibold text-text-muted uppercase tracking-wider">Course Details</div>
                        <div class="font-medium text-text-primary text-base">{{ $vcourse?->title }}</div>
                        <div class="text-xs text-text-muted">
                            Category: {{ $vcourse?->category?->name ?? 'General' }} &bull; Difficulty: {{ $vcourse?->difficulty }}
                        </div>
                        <div class="text-xs text-text-muted">
                            Curriculum: {{ $vcourse?->modules?->count() ?? 0 }} Modules, {{ $vcourse?->modules?->flatMap->lessons?->count() ?? 0 }} Lessons
                        </div>
                    </div>

                    <div class="p-3 rounded-lg border border-border-default bg-surface-primary space-y-2">
                        <div class="text-xs font-semibold text-text-muted uppercase tracking-wider">Lifecycle &amp; Validity</div>
                        <div class="text-xs space-y-1">
                            <div><span class="text-text-muted">Enrolled:</span> <span class="font-medium text-text-primary">{{ $viewingEnrollment->enrolled_at?->format('M d, Y') ?? '—' }}</span></div>
                            <div><span class="text-text-muted">Access Starts:</span> <span class="font-medium text-text-primary">{{ $viewingEnrollment->starts_at?->format('M d, Y') ?? 'Immediate' }}</span></div>
                            <div><span class="text-text-muted">Access Expires:</span> <span class="font-medium text-text-primary">{{ $viewingEnrollment->expires_at?->format('M d, Y') ?? 'No Expiration (Lifetime)' }}</span></div>
                            @if ($viewingEnrollment->completed_at)
                                <div><span class="text-text-muted">Completed:</span> <span class="font-medium text-emerald-400">{{ $viewingEnrollment->completed_at->format('M d, Y') }}</span></div>
                            @endif
                            @if ($viewingEnrollment->suspended_at)
                                <div><span class="text-text-muted">Suspended:</span> <span class="font-medium text-amber-400">{{ $viewingEnrollment->suspended_at->format('M d, Y') }} ({{ $viewingEnrollment->suspension_reason }})</span></div>
                            @endif
                            @if ($viewingEnrollment->cancelled_at)
                                <div><span class="text-text-muted">Cancelled:</span> <span class="font-medium text-rose-400">{{ $viewingEnrollment->cancelled_at->format('M d, Y') }} ({{ $viewingEnrollment->cancellation_reason }})</span></div>
                            @endif
                            <div><span class="text-text-muted">Assigned By:</span> <span class="font-medium text-text-primary">{{ $viewingEnrollment->assignedBy?->name ?? 'System' }}</span></div>
                        </div>
                    </div>
                </div>

                @if ($viewingEnrollment->notes)
                    <div class="p-3 rounded-lg bg-border-muted/40 border border-border-default text-xs">
                        <span class="font-semibold text-text-secondary">Notes:</span>
                        <p class="mt-1 text-text-muted whitespace-pre-line">{{ $viewingEnrollment->notes }}</p>
                    </div>
                @endif

                {{-- Audit History --}}
                <div class="space-y-3">
                    <h5 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Audit Log History</h5>
                    @if ($viewAuditLogs && $viewAuditLogs->isNotEmpty())
                        <div class="border border-border-default rounded-lg divide-y divide-border-default max-h-56 overflow-y-auto">
                            @foreach ($viewAuditLogs as $log)
                                <div class="p-2.5 text-xs space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-primary-600 font-mono">{{ $log->action }}</span>
                                        <span class="text-text-muted">{{ $log->created_at?->format('M d, Y H:i:s') }}</span>
                                    </div>
                                    <div class="text-text-muted flex items-center gap-2">
                                        <span>Actor: <strong class="text-text-secondary">{{ $log->user?->name ?? 'System' }}</strong></span>
                                        @if (! empty($log->properties))
                                            <span class="font-mono text-[11px] truncate text-text-muted">{{ json_encode($log->properties) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 text-center text-xs text-text-muted border border-dashed border-border-default rounded-lg">
                            No audit logs recorded for this enrollment yet.
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-3 border-t border-border-default">
                    <x-button variant="secondary" wire:click="$dispatch('close-modal', 'enrollment-view-modal')">
                        Close
                    </x-button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
