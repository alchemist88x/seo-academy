<div class="space-y-6">
    <x-page-header
        title="Enrolled Students"
        subtitle="View students enrolled in your assigned courses across {{ $academy->name }}."
    >
        <x-slot:actions>
            <a href="{{ route('trainer.dashboard') }}">
                <x-button variant="secondary" size="md">
                    &larr; Trainer Workspace
                </x-button>
            </a>
            <a href="{{ route('trainer.courses.index') }}">
                <x-button variant="secondary" size="md">
                    My Courses
                </x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <x-input
                    name="search"
                    label="Search Student"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by student name, email, or student code..."
                />
            </div>

            <div>
                <label for="course-filter" class="form-label block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Assigned Course
                </label>
                <select
                    id="course-filter"
                    wire:model.live="courseId"
                    class="form-select w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">All Assigned Courses</option>
                    @foreach ($assignedCourses as $course)
                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status-filter" class="form-label block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Enrollment Status
                </label>
                <select
                    id="status-filter"
                    wire:model.live="status"
                    class="form-select w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="suspended">Suspended</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        @if ($search !== '' || $courseId !== '' || $status !== '')
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                >
                    Clear filters
                </button>
            </div>
        @endif
    </x-card>

    {{-- Students Table --}}
    <x-card :padding="false">
        @if ($enrollments->isEmpty())
            <x-empty-state
                title="No students found"
                message="No students found matching your criteria or enrolled in your courses."
            />
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th>Student Name &amp; Email</th>
                    <th>Student Code</th>
                    <th>Course Title</th>
                    <th>Enrollment Status</th>
                    <th>Start Date</th>
                    <th>Expiry Date</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($enrollments as $enrollment)
                        <tr wire:key="enrollment-{{ $enrollment->id }}">
                            <td>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ $enrollment->studentProfile?->user?->name ?? 'Unknown Student' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $enrollment->studentProfile?->user?->email ?? '—' }}
                                </div>
                            </td>
                            <td class="font-mono text-xs text-slate-600 dark:text-slate-300">
                                {{ $enrollment->studentProfile?->student_code ?? '—' }}
                            </td>
                            <td>
                                <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ $enrollment->course?->title ?? '—' }}
                                </div>
                                @if ($enrollment->course?->category)
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $enrollment->course->category->name }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badgeColor = match ($enrollment->status) {
                                        'active' => 'green',
                                        'pending' => 'yellow',
                                        'completed' => 'blue',
                                        'suspended', 'cancelled', 'expired' => 'red',
                                        default => 'gray',
                                    };
                                @endphp
                                <x-badge :color="$badgeColor">
                                    {{ ucfirst($enrollment->status) }}
                                </x-badge>
                            </td>
                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                {{ $enrollment->starts_at ? $enrollment->starts_at->format('M d, Y') : ($enrollment->enrolled_at ? $enrollment->enrolled_at->format('M d, Y') : '—') }}
                            </td>
                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                {{ $enrollment->expires_at ? $enrollment->expires_at->format('M d, Y') : 'Lifetime' }}
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $enrollments->links() }}
            </div>
        @endif
    </x-card>
</div>
