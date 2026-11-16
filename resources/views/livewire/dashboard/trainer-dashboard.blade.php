<div class="space-y-6">
    <x-page-header
        title="Trainer Workspace"
        subtitle="Welcome back, {{ $trainerName }}. Monitor your assigned courses, student enrollments, and cohort progress."
    >
        <x-slot:actions>
            <a href="{{ route('trainer.courses.index') }}">
                <x-button variant="secondary" size="md">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    My Assigned Courses
                </x-button>
            </a>
            <a href="{{ route('trainer.students.index') }}">
                <x-button variant="primary" size="md">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Enrolled Students
                </x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($stats as $stat)
            <x-stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :trend="$stat['trend']"
                :trendDirection="$stat['direction']"
            />
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Assigned Courses --}}
        <x-card title="Assigned Courses" description="Courses you are assigned to teach and manage.">
            @if ($recentCourses->isEmpty())
                <x-empty-state
                    title="No courses assigned"
                    message="You are not currently assigned to teach any courses in this academy."
                />
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($recentCourses as $course)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">
                                    {{ $course->title }}
                                </p>
                                <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span>{{ $course->category?->name ?? 'Uncategorized' }}</span>
                                    <span>&bull;</span>
                                    <span class="capitalize">{{ $course->difficulty }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $course->active_enrollments_count ?? 0 }} active students</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <x-badge :color="$course->status === 'published' ? 'green' : 'yellow'">
                                    {{ ucfirst($course->status) }}
                                </x-badge>
                                <a
                                    href="{{ route('trainer.courses.structure', $course) }}"
                                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors"
                                    title="Edit Structure"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-right">
                    <a href="{{ route('trainer.courses.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        View all courses &rarr;
                    </a>
                </div>
            @endif
        </x-card>

        {{-- Recent Enrollment Activity --}}
        <x-card title="Recent Enrollment Activity" description="Latest student enrollments across your courses.">
            @if ($recentEnrollments->isEmpty())
                <x-empty-state
                    title="No enrollment activity"
                    message="No student enrollments recorded for your assigned courses yet."
                />
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($recentEnrollments as $enrollment)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">
                                    {{ $enrollment->studentProfile?->user?->name ?? 'Student' }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {{ $enrollment->course?->title ?? 'Course' }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
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
                                <p class="text-[11px] text-slate-400 mt-1">
                                    {{ $enrollment->enrolled_at ? $enrollment->enrolled_at->format('M d, Y') : ($enrollment->created_at ? $enrollment->created_at->format('M d, Y') : '') }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-right">
                    <a href="{{ route('trainer.students.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        View all enrolled students &rarr;
                    </a>
                </div>
            @endif
        </x-card>
    </div>
</div>
