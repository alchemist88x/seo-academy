<div class="space-y-8">
    {{-- Welcome & Academy Branding Banner --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white p-6 sm:p-8 shadow-sm">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white border border-white/20 backdrop-blur-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ $academyName }} &bull; {{ $academyTagline }}
                    </span>
                    <x-badge :color="$proficiencyBadgeColor" size="sm">
                        {{ $proficiencyLabel === 'Not Assessed' ? 'Not Assessed' : $proficiencyLabel . ' Level' }}
                    </x-badge>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                    Welcome back, {{ $userName }}!
                </h1>
                <p class="text-sm sm:text-base text-indigo-100/90 leading-relaxed">
                    Track your curriculum progression, pick up right where you left off, and complete your SEO training certifications.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <a
                    href="{{ route('student.courses.index') }}"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-indigo-950 font-bold text-sm shadow-md hover:bg-indigo-50 transition-colors"
                >
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>My Learning Library</span>
                </a>
            </div>
        </div>

        {{-- Subtle Background Geometric Accents --}}
        <div class="absolute -right-12 -bottom-16 w-64 h-64 rounded-full bg-indigo-500/10 blur-2xl pointer-events-none"></div>
        <div class="absolute right-1/3 -top-12 w-48 h-48 rounded-full bg-indigo-400/10 blur-xl pointer-events-none"></div>
    </div>

    {{-- Udemy Signature: Continue Learning Hero Card --}}
    @if ($continueLearning && $continueLearning['course'])
        @php
            $clCourse = $continueLearning['course'];
            $clResumeLesson = $continueLearning['resume_lesson'];
            $clProgress = $continueLearning['progress_percent'];
            $clTargetUrl = $clResumeLesson
                ? route('student.courses.learn', [$clCourse, $clResumeLesson->id])
                : route('student.courses.learn', $clCourse);
        @endphp
        <div class="rounded-2xl border border-indigo-100 dark:border-indigo-950/60 bg-gradient-to-r from-indigo-50/70 via-white to-indigo-50/30 dark:from-indigo-950/20 dark:via-surface-card dark:to-indigo-950/10 p-5 sm:p-6 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                {{-- Course Thumbnail & Info --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 min-w-0">
                    <div class="relative w-full sm:w-44 h-28 rounded-xl overflow-hidden shrink-0 shadow-xs bg-slate-900 border border-border-default">
                        @if ($clCourse->thumbnailUrl())
                            <img
                                src="{{ $clCourse->thumbnailUrl() }}"
                                alt="{{ $clCourse->title }}"
                                class="w-full h-full object-cover"
                            />
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-indigo-600 to-indigo-900 flex flex-col items-center justify-center p-3 text-center">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-200">
                                    {{ $clCourse->category?->name ?? 'SEO' }}
                                </span>
                                <span class="text-xs font-bold text-white line-clamp-2 mt-1">
                                    {{ $clCourse->title }}
                                </span>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                            <span class="w-10 h-10 rounded-full bg-white/90 text-indigo-600 flex items-center justify-center shadow-lg">
                                <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2 min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                Jump Back In &bull; {{ $clCourse->category?->name ?? 'Course' }}
                            </span>
                        </div>
                        <h2 class="text-lg sm:text-xl font-bold text-text-primary tracking-tight truncate">
                            {{ $clCourse->title }}
                        </h2>
                        @if ($clResumeLesson)
                            <p class="text-xs sm:text-sm text-text-secondary flex items-center gap-1.5 truncate">
                                <span class="font-semibold text-text-primary">Next lesson:</span>
                                <span class="text-indigo-600 dark:text-indigo-400 truncate">{{ $clResumeLesson->title }}</span>
                            </p>
                        @endif

                        {{-- Progress Bar --}}
                        <div class="space-y-1.5 pt-1 max-w-md">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-text-muted">Overall Course Progress</span>
                                <span class="font-bold text-primary-600 dark:text-primary-400">{{ $clProgress }}%</span>
                            </div>
                            <div class="w-full bg-border-muted rounded-full h-2 overflow-hidden border border-border-default/60">
                                <div
                                    class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                    style="width: {{ $clProgress }}%"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Button --}}
                <div class="shrink-0">
                    <a
                        href="{{ $clTargetUrl }}"
                        class="btn btn-primary btn-md w-full sm:w-auto inline-flex items-center justify-center gap-2 shadow-xs"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                        <span>Continue Learning</span>
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Learning Overview KPI Cards (Real Database Values) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            label="Active Courses"
            :value="(string) $activeCoursesCount"
            :trend="$totalEnrollmentsCount . ' total enrolled'"
            trendDirection="up"
        />

        <x-stat-card
            label="Lessons Completed"
            :value="$allEnrolledLessonsCount > 0 ? \"{$completedLessonsCount} / {$allEnrolledLessonsCount}\" : (string) $completedLessonsCount"
            :trend="$allEnrolledLessonsCount > 0 ? round(($completedLessonsCount / $allEnrolledLessonsCount) * 100) . '% of total' : 'Academic records'"
            trendDirection="up"
        />

        <x-stat-card
            label="Courses Completed"
            :value="(string) $completedCoursesCount"
            :trend="$completedCoursesCount > 0 ? 'Certifications earned' : 'In progress'"
            :trendDirection="$completedCoursesCount > 0 ? 'up' : 'neutral'"
        />

        <x-stat-card
            label="Total Enrollments"
            :value="(string) $totalEnrollmentsCount"
            trend="Active LMS student records"
            trendDirection="neutral"
        />
    </div>

    {{-- Main Courses Library & Secondary Widgets --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- Left Area: Enrolled Courses (8 cols on desktop) --}}
        <div class="lg:col-span-8 space-y-6">
            <x-card>
                <div class="space-y-5">
                    {{-- Section Header & Filter Tabs --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
                        <div>
                            <h2 class="text-lg font-bold text-text-primary">My Courses</h2>
                            <p class="text-xs text-text-secondary mt-0.5">Explore your enrolled curriculum and tracks.</p>
                        </div>

                        {{-- Udemy-Style Status Tabs --}}
                        <div class="flex items-center gap-1.5 p-1 rounded-xl bg-border-muted/70 border border-border-default text-xs font-semibold">
                            <button
                                type="button"
                                wire:click="setTab('all')"
                                class="px-3 py-1.5 rounded-lg transition-colors {{ $filterTab === 'all' ? 'bg-surface-card text-text-primary shadow-xs font-bold' : 'text-text-muted hover:text-text-primary' }}"
                            >
                                All ({{ $totalEnrollmentsCount }})
                            </button>
                            <button
                                type="button"
                                wire:click="setTab('in_progress')"
                                class="px-3 py-1.5 rounded-lg transition-colors {{ $filterTab === 'in_progress' ? 'bg-surface-card text-text-primary shadow-xs font-bold' : 'text-text-muted hover:text-text-primary' }}"
                            >
                                In Progress ({{ $activeCoursesCount }})
                            </button>
                            <button
                                type="button"
                                wire:click="setTab('completed')"
                                class="px-3 py-1.5 rounded-lg transition-colors {{ $filterTab === 'completed' ? 'bg-surface-card text-text-primary shadow-xs font-bold' : 'text-text-muted hover:text-text-primary' }}"
                            >
                                Completed ({{ $completedCoursesCount }})
                            </button>
                        </div>
                    </div>

                    {{-- Search Input Filter --}}
                    @if ($hasAnyEnrollments)
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search enrolled courses by title or category..."
                                class="w-full text-xs sm:text-sm rounded-xl border border-input-border bg-input-bg text-input-text px-4 py-2.5 pl-10 focus:ring-2 focus:ring-primary-500 focus:outline-hidden"
                            />
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-text-muted">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    @endif

                    {{-- Courses Grid --}}
                    @if (! $hasAnyEnrollments)
                        <x-empty-state
                            title="You are not enrolled in any courses yet"
                            message="You currently do not have any active course enrollments. Please contact your academy administrator to assign your curriculum."
                        />
                    @elseif ($filteredCourses->isEmpty())
                        <x-empty-state
                            title="No courses match your filter"
                            message="No courses match the selected tab or search query. Try clearing your search or switching tabs."
                        >
                            <x-slot:action>
                                <button
                                    type="button"
                                    wire:click="$set('search', ''); setTab('all')"
                                    class="btn btn-secondary btn-sm"
                                >
                                    Reset Filters
                                </button>
                            </x-slot:action>
                        </x-empty-state>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            @foreach ($filteredCourses as $item)
                                @php
                                    $course = $item['course'];
                                    $enrollment = $item['enrollment'];
                                    $status = $item['status'];
                                    $progressPercent = $item['progress_percent'];
                                    $resumeLesson = $item['resume_lesson'];
                                    $targetUrl = $resumeLesson
                                        ? route('student.courses.learn', [$course, $resumeLesson->id])
                                        : route('student.courses.learn', $course);
                                @endphp
                                <div class="rounded-xl border border-border-default bg-surface-primary flex flex-col justify-between overflow-hidden shadow-xs hover:border-primary-500/50 transition-colors">
                                    {{-- Card Top Thumbnail --}}
                                    <div class="relative w-full h-36 bg-slate-900 overflow-hidden">
                                        @if ($course && $course->thumbnailUrl())
                                            <img
                                                src="{{ $course->thumbnailUrl() }}"
                                                alt="{{ $course->title }}"
                                                class="w-full h-full object-cover"
                                            />
                                        @else
                                            <div class="w-full h-full bg-gradient-to-br from-indigo-700 to-slate-900 flex flex-col items-center justify-center p-4 text-center">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-300">
                                                    {{ $course?->category?->name ?? 'Course' }}
                                                </span>
                                                <span class="text-sm font-bold text-white line-clamp-2 mt-1">
                                                    {{ $course?->title }}
                                                </span>
                                            </div>
                                        @endif

                                        <div class="absolute top-2.5 right-2.5">
                                            @if ($status === 'active')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white shadow-xs">
                                                    Active
                                                </span>
                                            @elseif ($status === 'completed')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500 text-white shadow-xs">
                                                    Completed
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-600 text-white shadow-xs capitalize">
                                                    {{ $status }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Card Body --}}
                                    <div class="p-4 space-y-3 flex-1 flex flex-col justify-between">
                                        <div class="space-y-1.5">
                                            <div class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">
                                                {{ $course?->category?->name ?? 'Curriculum' }} &bull; {{ $course?->difficulty ?? 'All Levels' }}
                                            </div>
                                            <h3 class="font-bold text-text-primary text-base line-clamp-2">
                                                <a href="{{ route('courses.public.show', $course->slug) }}" class="hover:text-primary-600 transition-colors">
                                                    {{ $course?->title }}
                                                </a>
                                            </h3>
                                            <p class="text-xs text-text-muted">
                                                {{ $item['total_lessons'] }} Lessons &bull; {{ $item['completed_lessons'] }} Completed
                                            </p>
                                        </div>

                                        {{-- Progress Bar --}}
                                        <div class="space-y-1.5 pt-2">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-text-muted">Progress</span>
                                                <span class="font-bold text-primary-600 dark:text-primary-400">{{ $progressPercent }}%</span>
                                            </div>
                                            <div class="w-full bg-border-muted rounded-full h-1.5 overflow-hidden">
                                                <div
                                                    class="bg-primary-600 h-1.5 rounded-full transition-all duration-300"
                                                    style="width: {{ $progressPercent }}%"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Card Bottom Action Bar --}}
                                    <div class="p-4 pt-3 border-t border-border-default bg-surface-card flex items-center justify-between gap-3">
                                        <span class="text-[11px] text-text-muted truncate">
                                            @if ($enrollment->expires_at)
                                                Expires {{ $enrollment->expires_at->format('M d, Y') }}
                                            @else
                                                Lifetime Access
                                            @endif
                                        </span>

                                        <div>
                                            @if ($status === 'active')
                                                <a
                                                    href="{{ $targetUrl }}"
                                                    class="btn btn-primary btn-xs inline-flex items-center gap-1.5 font-bold"
                                                >
                                                    <span>Resume</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </a>
                                            @elseif ($status === 'completed')
                                                <a
                                                    href="{{ route('student.courses.learn', $course) }}"
                                                    class="btn btn-secondary btn-xs font-bold"
                                                >
                                                    Review
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>
        </div>

        {{-- Right Area: Upcoming Assignments, Certificates & Recent Activity (4 cols on desktop) --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Upcoming Assignments Card --}}
            <x-card>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="text-sm font-bold text-text-primary">Upcoming Assignments</h3>
                        </div>
                        <a href="{{ route('student.assignments.index') }}" class="text-xs font-semibold text-primary-600 hover:underline">
                            View All
                        </a>
                    </div>

                    @if ($upcomingAssignments->isEmpty())
                        <p class="text-xs text-text-muted py-2 text-center">No pending assignments due.</p>
                    @else
                        <ul class="divide-y divide-border-default">
                            @foreach ($upcomingAssignments as $asmtItem)
                                @php
                                    $asmt = $asmtItem['assignment'];
                                @endphp
                                <li class="py-3 space-y-1.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h4 class="text-xs font-bold text-text-primary truncate">
                                                {{ $asmt->title }}
                                            </h4>
                                            <span class="text-[11px] text-text-muted block truncate">
                                                {{ $asmt->course?->title }}
                                            </span>
                                        </div>
                                        @if ($asmtItem['is_submitted'])
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 shrink-0">
                                                Submitted
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 shrink-0">
                                                Pending
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-text-muted pt-1">
                                        <span>
                                            Due: {{ $asmt->due_date ? $asmt->due_date->format('M d, Y') : 'No deadline' }}
                                        </span>
                                        <a
                                            href="{{ route('student.courses.assignments.show', [$asmt->course_id, $asmt->id]) }}"
                                            class="font-semibold text-primary-600 hover:underline"
                                        >
                                            Workspace &rarr;
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-card>

            {{-- Recently Earned Certificates Card --}}
            <x-card>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                            <h3 class="text-sm font-bold text-text-primary">Earned Certificates</h3>
                        </div>
                        <a href="{{ route('student.certificates.index') }}" class="text-xs font-semibold text-primary-600 hover:underline">
                            View All
                        </a>
                    </div>

                    @if ($recentCertificates->isEmpty())
                        <div class="py-4 text-center text-xs text-text-muted space-y-1">
                            <p>Complete course curriculums to earn verified certificates.</p>
                        </div>
                    @else
                        <ul class="divide-y divide-border-default">
                            @foreach ($recentCertificates as $cert)
                                <li class="py-3 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h4 class="text-xs font-bold text-text-primary truncate">
                                                {{ $cert->course?->title ?? 'SEO Certification' }}
                                            </h4>
                                            <span class="text-[11px] font-mono text-text-muted block mt-0.5">
                                                {{ $cert->certificate_number }}
                                            </span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            Issued
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] pt-1">
                                        <span class="text-text-muted">
                                            Issued {{ $cert->issued_at ? $cert->issued_at->format('M d, Y') : 'Recently' }}
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <a
                                                href="{{ route('certificates.verify', $cert->verification_code ?: $cert->certificate_number) }}"
                                                target="_blank"
                                                class="text-text-secondary hover:text-text-primary font-semibold"
                                            >
                                                Verify
                                            </a>
                                            <a
                                                href="{{ route('certificates.download', $cert) }}"
                                                class="font-bold text-primary-600 hover:underline"
                                            >
                                                Download PDF
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-card>

            {{-- Recent Activity Feed --}}
            @if ($recentActivity->isNotEmpty())
                <x-card>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="text-sm font-bold text-text-primary">Recent Learning Activity</h3>
                        </div>

                        <ul class="divide-y divide-border-default">
                            @foreach ($recentActivity as $act)
                                <li class="py-2.5 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <span class="font-semibold text-text-primary block truncate">
                                            {{ $act->lesson?->title ?? 'Lesson' }}
                                        </span>
                                        <span class="text-[11px] text-text-muted truncate block">
                                            {{ $act->lesson?->module?->course?->title }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-text-muted shrink-0">
                                        {{ $act->last_accessed_at ? $act->last_accessed_at->diffForHumans() : 'Recently' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</div>
