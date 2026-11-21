@component(auth()->check() ? 'layouts.app' : 'layouts.guest', ['title' => $course->title])
    <div class="space-y-8 -mt-4 sm:-mt-6">
        @if ($isPreview ?? false)
            <div class="rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-4 py-3 flex flex-wrap items-center justify-between gap-3 max-w-7xl mx-auto">
                <div class="flex items-center gap-2 text-sm font-medium text-amber-900 dark:text-amber-200">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                    <span>
                        Preview Mode &mdash; Status: <strong>{{ $course->status }}</strong> &bull; Visibility: <strong>{{ $course->visibility }}</strong>
                    </span>
                </div>

                @if (auth()->user()?->hasRole('admin'))
                    <a href="{{ route('admin.courses.index') }}" class="btn btn-secondary btn-sm">
                        Back to Courses
                    </a>
                @elseif (auth()->user()?->hasRole('trainer'))
                    <a href="{{ route('trainer.courses.index') }}" class="btn btn-secondary btn-sm">
                        Back to My Courses
                    </a>
                @endif
            </div>
        @endif

        {{-- Udemy-Style Dark / Indigo Course Hero Section --}}
        <div class="relative bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white py-10 sm:py-14 px-4 sm:px-6 lg:px-8 border-b border-slate-800">
            <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                {{-- Hero Left: Metadata & Titles (8 cols) --}}
                <div class="lg:col-span-8 space-y-4">
                    {{-- Breadcrumbs --}}
                    <nav class="flex items-center gap-2 text-xs text-indigo-300 font-medium">
                        <a href="{{ auth()->check() ? route('student.courses.index') : route('courses.public.index') }}" class="hover:underline">{{ auth()->check() ? 'My Learning' : 'Courses' }}</a>
                        <span>&rsaquo;</span>
                        <span class="text-indigo-200">{{ $course->category?->name ?? 'SEO' }}</span>
                        <span>&rsaquo;</span>
                        <span class="text-slate-400 truncate max-w-[200px]">{{ $course->title }}</span>
                    </nav>

                    <h1 class="text-2xl sm:text-4xl font-extrabold text-white tracking-tight leading-tight">
                        {{ $course->title }}
                    </h1>

                    @if ($course->description)
                        <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-2xl line-clamp-3">
                            {{ $course->description }}
                        </p>
                    @endif

                    {{-- Badges & Duration Metadata --}}
                    <div class="flex flex-wrap items-center gap-4 pt-2 text-xs text-slate-300">
                        <span class="px-2.5 py-1 rounded-full bg-indigo-500/20 text-indigo-300 font-semibold border border-indigo-400/30 uppercase tracking-wider">
                            {{ ucfirst($course->difficulty) }} Level
                        </span>

                        @if ($totalDurationSeconds > 0)
                            @php
                                $hours = floor($totalDurationSeconds / 3600);
                                $minutes = floor(($totalDurationSeconds % 3600) / 60);
                                $durationString = $hours > 0 ? "{$hours}h {$minutes}m total duration" : "{$minutes}m total duration";
                            @endphp
                            <span class="flex items-center gap-1.5 font-medium">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $durationString }}
                            </span>
                        @elseif ($course->duration_months > 0)
                            <span class="flex items-center gap-1.5 font-medium">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ $course->duration_months }} {{ \Illuminate\Support\Str::plural('Month', $course->duration_months) }} Access
                            </span>
                        @endif

                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            {{ $course->modules->count() }} Modules &bull; {{ $course->modules->flatMap->lessons->count() }} Lessons
                        </span>

                        @if ($course->trainers->isNotEmpty())
                            <span class="text-slate-400">
                                Instructors: <span class="text-white font-medium">{{ $course->trainers->pluck('name')->join(', ') }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Hero Right: Floating / Sticky Action Card (4 cols) --}}
                <div class="lg:col-span-4 w-full">
                    <div class="rounded-2xl border border-slate-700 bg-slate-900/90 text-white overflow-hidden shadow-2xl backdrop-blur-md">
                        {{-- Thumbnail --}}
                        <div class="relative w-full h-48 bg-slate-950 overflow-hidden">
                            @if ($course->thumbnailUrl())
                                <img
                                    src="{{ $course->thumbnailUrl() }}"
                                    alt="{{ $course->title }}"
                                    class="w-full h-full object-cover"
                                />
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-indigo-700 via-indigo-900 to-slate-950 flex flex-col items-center justify-center p-4 text-center">
                                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-300">
                                        {{ $course->category?->name ?? 'SEO Academy' }}
                                    </span>
                                    <span class="text-base font-bold text-white mt-1">
                                        {{ $course->title }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Card Details & Primary Action --}}
                        <div class="p-6 space-y-4">
                            @if ($enrollment)
                                @php
                                    $targetUrl = $resumeLesson
                                        ? route('student.courses.learn', [$course, $resumeLesson->id])
                                        : route('student.courses.learn', $course);
                                @endphp

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-slate-400 font-medium">Your Progress</span>
                                        <span class="font-bold text-indigo-400">{{ $progressPercent }}% Complete</span>
                                    </div>
                                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-700">
                                        <div
                                            class="bg-indigo-500 h-2 rounded-full transition-all duration-300"
                                            style="width: {{ $progressPercent }}%"
                                        ></div>
                                    </div>
                                </div>

                                @if ($enrollment->status === 'active')
                                    <a
                                        href="{{ $targetUrl }}"
                                        class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm text-center block shadow-md transition-colors"
                                    >
                                        {{ $progressPercent > 0 ? 'Continue Learning' : 'Start Learning' }}
                                    </a>
                                @elseif ($enrollment->status === 'completed')
                                    <a
                                        href="{{ route('student.courses.learn', $course) }}"
                                        class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm text-center block shadow-md transition-colors"
                                    >
                                        Review Completed Course
                                    </a>
                                @else
                                    <div class="w-full text-center py-2.5 px-4 rounded-xl text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        Enrollment {{ ucfirst($enrollment->status) }}
                                    </div>
                                @endif

                                <div class="text-[11px] text-slate-400 space-y-1.5 pt-2 border-t border-slate-800">
                                    <div class="flex items-center justify-between">
                                        <span>Status:</span>
                                        <span class="font-semibold text-slate-200 capitalize">{{ $enrollment->status }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Access:</span>
                                        <span class="text-slate-300">
                                            @if ($enrollment->expires_at)
                                                Until {{ $enrollment->expires_at->format('M d, Y') }}
                                            @else
                                                Lifetime Enrollment
                                            @endif
                                        </span>
                                    </div>
                                </div>

                            @else
                                <div class="space-y-4">
                                    <div class="flex items-baseline justify-between">
                                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tuition</div>
                                        <div class="text-right">
                                            @if ($course->is_free)
                                                <span class="text-2xl font-black text-emerald-400">Free</span>
                                            @else
                                                <span class="text-2xl font-black text-white">
                                                    @currency($course->effective_price, $course->currency)
                                                </span>
                                                @if ($course->discount_price && $course->discount_price < $course->price)
                                                    <div class="text-xs text-slate-400 line-through">
                                                        @currency($course->price, $course->currency)
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    @if ($course->installmentPlan)
                                        <div class="bg-purple-900/40 border border-purple-500/30 rounded-lg p-2.5 text-xs text-purple-200">
                                            Installment plan: Pay {{ $course->installmentPlan->initial_deposit_value }}% now, rest on reaching {{ $course->installmentPlan->completion_milestone_percentage }}% progress!
                                        </div>
                                    @endif

                                    @if (auth()->check() && ! auth()->user()->hasRole('student'))
                                        <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 text-xs text-amber-300 space-y-2">
                                            <div class="font-bold flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Staff Account Notice
                                            </div>
                                            <p class="leading-relaxed">
                                                You are signed in as an administrator/instructor. Direct purchases are reserved for students.
                                            </p>
                                            @if (auth()->user()->hasRole('admin'))
                                                <a href="{{ route('admin.orders.index') }}" class="inline-block font-semibold text-amber-200 hover:underline">
                                                    Assign courses via Admin Orders &rarr;
                                                </a>
                                            @endif
                                        </div>

                                        <a
                                            href="{{ route('student.courses.learn', $course) }}"
                                            class="w-full py-2.5 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs text-center block transition-colors"
                                        >
                                            Enter Staff Preview
                                        </a>
                                    @else
                                        <a
                                            href="{{ route('checkout.show', ['type' => 'course', 'id' => $course->id]) }}"
                                            class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm text-center block shadow-md transition-colors"
                                        >
                                            {{ $course->is_free ? 'Enroll Free Now' : 'Enroll in Course' }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Course Overview & Curriculum (Max-w-7xl) --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {{-- Left Content Area (8 cols) --}}
            <div class="lg:col-span-8 space-y-8">
                {{-- "What You'll Learn" Box (Udemy Signature) --}}
                <div class="rounded-2xl border border-border-default bg-surface-card p-6 sm:p-8 shadow-xs space-y-4">
                    <h2 class="text-lg font-bold text-text-primary">What You'll Learn</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-text-secondary">
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Enterprise crawl budget analysis and log file evaluation</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Core Web Vitals real user diagnostic and optimization workflows</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Nested JSON-LD schema architecture and rich entity graphs</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Industry standard audit frameworks and executive remediation roadmaps</span>
                        </div>
                    </div>
                </div>

                @php
                    $visibleModules = $course->modules->filter(
                        fn ($m) => ($isPreview ?? false) || in_array(strtolower($m->status), ['active', 'published'])
                    );
                @endphp

                @if (! auth()->check())
                    {{-- Guest View: Brief Curriculum Preview & Registration Prompt --}}
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h2 class="text-xl font-bold text-text-primary">Course Content Preview</h2>
                                <p class="text-xs text-text-muted mt-0.5">
                                    {{ $course->modules->count() }} sections &bull; {{ $course->modules->flatMap->lessons->count() }} lectures overview
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 font-semibold bg-indigo-50 dark:bg-indigo-950/50 px-2.5 py-1 rounded-full border border-indigo-200 dark:border-indigo-800">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Curriculum Preview Mode
                            </span>
                        </div>

                        @if ($visibleModules->isEmpty())
                            <div class="rounded-xl border border-border-default bg-surface-card p-8 text-center text-xs text-text-muted">
                                Curriculum outline is being updated by the instructional staff.
                            </div>
                        @else
                            <div class="rounded-2xl border border-border-default divide-y divide-border-default bg-surface-card overflow-hidden shadow-xs">
                                @foreach ($visibleModules as $modIdx => $module)
                                    @php
                                        $visibleLessons = $module->lessons->filter(
                                            fn ($l) => ($isPreview ?? false) || (in_array(strtolower($l->status), ['active', 'published']) && $l->visibility !== 'hidden')
                                        );
                                        $modDurationSec = (int) $visibleLessons->sum('duration_seconds');
                                        $modDurationText = $modDurationSec > 0 ? gmdate('i:s', $modDurationSec) . ' total' : '';
                                    @endphp
                                    <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-border-muted/20 transition-colors">
                                        <div class="space-y-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                    Section {{ $modIdx + 1 }}
                                                </span>
                                                <h3 class="text-sm font-bold text-text-primary truncate">
                                                    {{ $module->title }}
                                                </h3>
                                            </div>
                                            @if ($module->description)
                                                <p class="text-xs text-text-muted line-clamp-2 max-w-xl">
                                                    {{ $module->description }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="text-xs text-text-muted shrink-0 flex items-center gap-2 font-medium">
                                            <span>{{ $visibleLessons->count() }} {{ \Illuminate\Support\Str::plural('lecture', $visibleLessons->count()) }}</span>
                                            @if ($modDurationText)
                                                <span>&bull; {{ $modDurationText }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Registration / Sign In Prompt Box --}}
                            <div class="rounded-2xl border border-indigo-500/30 bg-gradient-to-br from-indigo-950/40 via-slate-900 to-indigo-900/20 p-6 sm:p-8 text-center space-y-4">
                                <div class="w-12 h-12 mx-auto rounded-full bg-indigo-600/20 text-indigo-400 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-white tracking-tight">Want to explore the complete curriculum?</h3>
                                <p class="text-sm text-slate-300 max-w-lg mx-auto">
                                    Create your free student account to view the full course curriculum and explore enrollment options.
                                </p>
                                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-500 shadow-md transition">
                                        Register Now
                                    </a>
                                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl font-medium text-sm text-slate-200 bg-slate-800 hover:bg-slate-700 border border-slate-700 transition">
                                        Already have an account? Sign In
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                {{-- Course Curriculum Accordion (Udemy Signature) --}}
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <h2 class="text-xl font-bold text-text-primary">Course Content</h2>
                            <p class="text-xs text-text-muted mt-0.5">
                                {{ $course->modules->count() }} sections &bull; {{ $course->modules->flatMap->lessons->count() }} lectures
                            </p>
                        </div>
                    </div>

                    @php
                        $visibleModules = $course->modules->filter(
                            fn ($m) => ($isPreview ?? false) || in_array(strtolower($m->status), ['active', 'published'])
                        );
                    @endphp

                    @if ($visibleModules->isEmpty())
                        <div class="rounded-xl border border-border-default bg-surface-card p-8 text-center text-xs text-text-muted">
                            Curriculum outline is being updated by the instructional staff.
                        </div>
                    @else
                        <div class="rounded-2xl border border-border-default divide-y divide-border-default bg-surface-card overflow-hidden shadow-xs">
                            @foreach ($visibleModules as $modIdx => $module)
                                @php
                                    $visibleLessons = $module->lessons->filter(
                                        fn ($l) => ($isPreview ?? false) || (in_array(strtolower($l->status), ['active', 'published']) && $l->visibility !== 'hidden')
                                    );
                                @endphp
                                <div x-data="{ open: {{ $modIdx === 0 ? 'true' : 'false' }} }">
                                    {{-- Module Header Button --}}
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="w-full px-5 py-4 text-left flex items-center justify-between gap-4 bg-border-muted/30 hover:bg-border-muted/60 transition-colors"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <svg
                                                class="w-4 h-4 text-text-muted transform transition-transform duration-200 shrink-0"
                                                :class="{ 'rotate-180': open }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                            <div class="min-w-0">
                                                <h3 class="text-sm font-bold text-text-primary truncate">
                                                    Section {{ $modIdx + 1 }}: {{ $module->title }}
                                                </h3>
                                            </div>
                                        </div>

                                        <div class="text-xs text-text-muted shrink-0 font-medium">
                                            {{ $visibleLessons->count() }} lectures
                                        </div>
                                    </button>

                                    {{-- Module Lessons List --}}
                                    <ul x-show="open" x-collapse class="divide-y divide-border-default/60">
                                        @foreach ($visibleLessons as $lesson)
                                            @php
                                                $isLessonDone = in_array((string) $lesson->id, $completedLessonIds, true);
                                            @endphp
                                            <li class="px-5 py-3 flex items-center justify-between gap-3 text-xs sm:text-sm">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    {{-- Status Icon: Completed Checkmark or Lesson Type Icon --}}
                                                    <div class="shrink-0">
                                                        @if ($isLessonDone)
                                                            <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center" title="Completed">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </div>
                                                        @elseif ($lesson->type === 'video')
                                                            <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        @elseif ($lesson->type === 'pdf')
                                                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                            </svg>
                                                        @else
                                                            <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        @endif
                                                    </div>

                                                    <span class="font-medium text-text-primary truncate {{ $isLessonDone ? 'text-emerald-900 dark:text-emerald-200' : '' }}">
                                                        {{ $lesson->title }}
                                                    </span>

                                                    @if ($lesson->isFreePreview())
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 shrink-0">
                                                            Free Preview
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="flex items-center gap-3 shrink-0 text-xs text-text-muted">
                                                    @if ($lesson->duration_seconds > 0)
                                                        <span>{{ gmdate('i:s', $lesson->duration_seconds) }}</span>
                                                    @endif

                                                    @if ($lesson->isFreePreview() && (!auth()->check() || !$enrollment))
                                                        <a
                                                            href="{{ route('lessons.preview', [$course, $lesson]) }}"
                                                            class="text-xs font-semibold text-primary-600 hover:underline"
                                                        >
                                                            Preview &rarr;
                                                        </a>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Instructors Profile Section --}}
                @if ($course->trainers->isNotEmpty())
                    <div class="rounded-2xl border border-border-default bg-surface-card p-6 sm:p-8 space-y-4">
                        <h2 class="text-lg font-bold text-text-primary">Instructors</h2>
                        <div class="space-y-4">
                            @foreach ($course->trainers as $trainer)
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-base shrink-0">
                                        {{ strtoupper(substr($trainer->name, 0, 1)) }}
                                    </div>
                                    <div class="space-y-1">
                                        <h3 class="font-bold text-text-primary text-base">{{ $trainer->name }}</h3>
                                        <p class="text-xs text-text-muted">Instructor &bull; SEO Academy</p>
                                        <p class="text-xs text-text-secondary">
                                            Specialized instructor guiding technical SEO, architecture, and organic search growth strategies.
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Sidebar: Prerequisites & Additional Info (4 cols) --}}
            <div class="lg:col-span-4 space-y-6">
                {{-- Prerequisites Card --}}
                <div class="rounded-2xl border border-border-default bg-surface-card p-6 space-y-3 shadow-xs">
                    <h3 class="text-sm font-bold text-text-primary">Requirements & Prerequisites</h3>
                    @if ($course->prerequisites->isEmpty())
                        <p class="text-xs text-text-muted">No prior technical course requirements. Basic understanding of web technologies recommended.</p>
                    @else
                        <ul class="space-y-2 text-xs text-text-secondary">
                            @foreach ($course->prerequisites as $prereq)
                                <li class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-600 shrink-0"></span>
                                    <span>{{ $prereq->title }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endcomponent
