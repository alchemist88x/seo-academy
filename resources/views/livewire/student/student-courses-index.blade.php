<div class="space-y-6">
    {{-- Header & Sub-Tabs Navigation --}}
    <div class="space-y-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-text-primary tracking-tight">My Learning</h1>
            <p class="text-sm text-text-secondary mt-1">
                Access your enrolled SEO curriculum, track progress, and resume your lessons in {{ $academy->name }}.
            </p>
        </div>

        @if ($hasAnyEnrollments)
            {{-- Udemy-Style Sub-Tabs & Control Bar --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border-default pb-3">
                {{-- Tabs --}}
                <div class="flex items-center gap-1 sm:gap-2">
                    <button
                        type="button"
                        wire:click="setTab('all')"
                        class="px-3 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all {{ $tab === 'all' ? 'bg-primary-600 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
                    >
                        All Courses ({{ $totalCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="setTab('in_progress')"
                        class="px-3 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all {{ $tab === 'in_progress' ? 'bg-primary-600 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
                    >
                        In Progress ({{ $inProgressCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="setTab('completed')"
                        class="px-3 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all {{ $tab === 'completed' ? 'bg-primary-600 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
                    >
                        Completed ({{ $completedCount }})
                    </button>
                </div>

                {{-- Sort Dropdown --}}
                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs text-text-muted hidden sm:inline">Sort by:</span>
                    <select
                        wire:model.live="sortBy"
                        class="text-xs font-semibold rounded-xl border border-input-border bg-input-bg text-input-text px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-hidden"
                    >
                        <option value="recent">Recently Accessed</option>
                        <option value="title">Course Title (A-Z)</option>
                        <option value="progress">Highest Progress</option>
                    </select>
                </div>
            </div>
        @endif
    </div>

    @if (! $hasAnyEnrollments)
        <x-card>
            <x-empty-state
                title="You are not enrolled in any courses yet"
                message="You currently do not have any active course enrollments in this academy. Please contact your academy administrator to get enrolled."
            />
        </x-card>
    @else
        {{-- Search Input Bar --}}
        <div class="relative max-w-md">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by course title, topic, or category..."
                class="w-full text-xs sm:text-sm rounded-xl border border-input-border bg-input-bg text-input-text px-4 py-2 pl-10 focus:ring-2 focus:ring-primary-500 focus:outline-hidden"
            />
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            @if ($search !== '')
                <button
                    type="button"
                    wire:click="$set('search', '')"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-text-muted hover:text-text-primary"
                >
                    &times; Clear
                </button>
            @endif
        </div>

        {{-- Courses Grid --}}
        @if ($paginatedEnrollments->isEmpty())
            <x-card>
                <x-empty-state
                    title="No matching courses found"
                    message="No courses match your current search or tab filter. Try adjusting your search query or switching tabs."
                >
                    <x-slot:action>
                        <button
                            type="button"
                            wire:click="resetFilters"
                            class="btn btn-secondary btn-sm"
                        >
                            Reset All Filters
                        </button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($paginatedEnrollments as $item)
                    @php
                        $course = $item['course'];
                        $enrollment = $item['enrollment'];
                        $status = $item['status'];
                        $progressPercent = $item['progress_percent'];
                        $resumeLesson = $item['resume_lesson'];
                        $cert = $item['certificate'];
                        $targetUrl = $resumeLesson
                            ? route('student.courses.learn', [$course, $resumeLesson->id])
                            : route('student.courses.learn', $course);
                    @endphp

                    <div
                        wire:key="learning-card-{{ $enrollment->id }}"
                        class="rounded-2xl border border-border-default bg-surface-card flex flex-col justify-between overflow-hidden shadow-xs hover:shadow-md hover:border-primary-500/40 transition-all duration-200"
                    >
                        {{-- Top Thumbnail --}}
                        <div class="relative w-full h-44 bg-slate-900 overflow-hidden group">
                            @if ($course && $course->thumbnailUrl())
                                <img
                                    src="{{ $course->thumbnailUrl() }}"
                                    alt="{{ $course->title }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-indigo-800 via-indigo-900 to-slate-950 flex flex-col items-center justify-center p-4 text-center">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-300">
                                        {{ $course?->category?->name ?? 'Curriculum' }}
                                    </span>
                                    <span class="text-sm font-bold text-white line-clamp-2 mt-1">
                                        {{ $course?->title }}
                                    </span>
                                </div>
                            @endif

                            {{-- Status Badge Overlay --}}
                            <div class="absolute top-3 right-3">
                                @if ($status === 'active')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-500 text-white shadow-xs">
                                        Active
                                    </span>
                                @elseif ($status === 'completed')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-indigo-600 text-white shadow-xs">
                                        Completed
                                    </span>
                                @elseif ($status === 'expired')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-700 text-slate-200 shadow-xs">
                                        Expired
                                    </span>
                                @elseif ($status === 'suspended')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-600 text-white shadow-xs">
                                        Suspended
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-600 text-white shadow-xs capitalize">
                                        {{ $status }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Card Body --}}
                        <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs text-text-muted">
                                    <span class="font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                        {{ $course?->category?->name ?? 'Course' }}
                                    </span>
                                    <span>{{ ucfirst($course?->difficulty ?? 'All Levels') }}</span>
                                </div>

                                <h3 class="font-bold text-text-primary text-base line-clamp-2">
                                    @if ($status === 'active' || $status === 'completed')
                                        <a href="{{ route('courses.public.show', $course->slug) }}" class="hover:text-primary-600 transition-colors">
                                            {{ $course?->title }}
                                        </a>
                                    @else
                                        <span>{{ $course?->title }}</span>
                                    @endif
                                </h3>

                                {{-- Trainers --}}
                                @if ($course && $course->trainers->isNotEmpty())
                                    <p class="text-xs text-text-secondary truncate">
                                        By {{ $course->trainers->pluck('name')->join(', ') }}
                                    </p>
                                @endif

                                {{-- Resume Lesson / Next Lesson Preview --}}
                                @if ($status === 'active' && $resumeLesson)
                                    <div class="p-2.5 rounded-xl bg-border-muted/50 border border-border-default/60 text-xs">
                                        <span class="font-semibold text-text-primary block truncate">
                                            Next: {{ $resumeLesson->title }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            {{-- Progress Bar Section --}}
                            <div class="space-y-1.5 pt-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-text-muted">
                                        {{ $item['completed_lessons'] }} of {{ $item['total_lessons'] }} lessons completed
                                    </span>
                                    <span class="font-bold text-primary-600 dark:text-primary-400">{{ $progressPercent }}%</span>
                                </div>
                                <div class="w-full bg-border-muted rounded-full h-2 overflow-hidden border border-border-default/60">
                                    <div
                                        class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                        style="width: {{ $progressPercent }}%"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        {{-- Card Bottom Action Bar --}}
                        <div class="p-4 pt-3 border-t border-border-default bg-surface-card/60 flex flex-col gap-2.5">
                            @if ($status === 'active')
                                <a
                                    href="{{ route('student.courses.learn', $course) }}"
                                    class="btn btn-primary btn-sm w-full text-center inline-flex items-center justify-center gap-2 font-bold shadow-xs"
                                >
                                    <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                    <span>Resume Course</span>
                                </a>
                            @elseif ($status === 'completed')
                                <div class="space-y-2">
                                    <a
                                        href="{{ route('student.courses.learn', $course) }}"
                                        class="btn btn-secondary btn-sm w-full text-center font-bold"
                                    >
                                        Review Course
                                    </a>
                                    @if ($cert)
                                        <div class="flex gap-2">
                                            <a
                                                href="{{ route('certificates.download', $cert) }}"
                                                class="btn btn-primary btn-xs flex-1 text-center font-bold"
                                            >
                                                Certificate
                                            </a>
                                            <a
                                                href="{{ route('certificates.verify', $cert->verification_code ?: $cert->certificate_number) }}"
                                                target="_blank"
                                                class="btn btn-secondary btn-xs flex-1 text-center"
                                            >
                                                Verify
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($status === 'suspended')
                                <div class="w-full text-center py-2 px-3 rounded-xl text-xs font-semibold bg-amber-500/10 text-amber-600 border border-amber-500/20">
                                    Enrollment Suspended
                                </div>
                            @elseif ($status === 'expired')
                                <div class="w-full text-center py-2 px-3 rounded-xl text-xs font-semibold bg-slate-500/10 text-slate-500 border border-slate-500/20">
                                    Enrollment Expired
                                </div>
                            @else
                                <div class="w-full text-center py-2 px-3 rounded-xl text-xs font-semibold bg-yellow-500/10 text-yellow-600 border border-yellow-500/20">
                                    Pending Enrollment
                                </div>
                            @endif

                            <div class="text-[11px] text-text-muted flex items-center justify-between">
                                <span>
                                    @if ($enrollment->expires_at)
                                        Expires {{ $enrollment->expires_at->format('M d, Y') }}
                                    @else
                                        Lifetime Access
                                    @endif
                                </span>
                                <a
                                    href="{{ route('courses.public.show', $course->slug) }}"
                                    class="text-primary-600 dark:text-primary-400 hover:underline font-semibold"
                                >
                                    Course Details &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination Links --}}
            @if ($paginatedEnrollments->hasPages())
                <div class="pt-4">
                    {{ $paginatedEnrollments->links() }}
                </div>
            @endif
        @endif
    @endif
</div>
