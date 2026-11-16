<div class="space-y-6">
    {{-- Top Classroom Header & Action Bar --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('student.courses.index') }}"
                    class="text-xs font-medium text-text-muted hover:text-text-primary inline-flex items-center gap-1 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to My Courses
                </a>
                @if ($course->category)
                    <span class="text-xs text-text-muted">&bull;</span>
                    <span class="text-xs text-text-muted">{{ $course->category->name }}</span>
                @endif
            </div>

            <h1 class="text-xl sm:text-2xl font-bold text-text-primary flex items-center gap-3">
                <span>{{ $course->title }}</span>
                @if ($isCompletedEnrollment)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Course Completed • Read-Only
                    </span>
                @endif
            </h1>
        </div>

        {{-- Mobile Curriculum Drawer Toggle --}}
        <div class="flex items-center gap-3 lg:hidden">
            <button
                type="button"
                wire:click="toggleMobileCurriculum"
                class="btn btn-secondary btn-sm inline-flex items-center gap-2 w-full justify-center"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span>Curriculum ({{ $curriculum['completed_lessons'] }}/{{ $curriculum['total_lessons'] }})</span>
            </button>
        </div>
    </div>

    @if ($isCompletedEnrollment)
        <div class="p-4 rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold">Congratulations! Course Completed</h3>
                    <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-0.5">
                        You have completed all curriculum requirements. You retain permanent read-only access to all lessons and materials.
                    </p>
                </div>
            </div>
            @if ($certificateNumber)
                <div class="sm:text-right shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-200/60 dark:bg-emerald-900/80 text-emerald-900 dark:text-emerald-100">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                        </svg>
                        <span>Certificate: {{ $certificateNumber }}</span>
                    </span>
                </div>
            @endif
        </div>
    @endif

    {{-- Main Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Lesson Player Area (8 Cols on Desktop) --}}
        <div
            class="lg:col-span-8 space-y-6"
            x-data="{ timer: null }"
            x-init="timer = setInterval(() => { $wire.recordHeartbeat(); }, 45000)"
            x-on:beforeunload.window="if (timer) clearInterval(timer)"
        >
            @if ($currentLesson)
                <x-card>
                    <div class="space-y-4">
                        {{-- Lesson Meta Header --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-default">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-primary text-xs uppercase font-semibold">
                                    {{ ucfirst(str_replace('_', ' ', $currentLesson->type)) }}
                                </span>
                                @if ($currentLesson->isFreePreview())
                                    <span class="badge badge-neutral text-xs">Free Preview</span>
                                @endif
                                @if ($currentLesson->duration_seconds > 0)
                                    <span class="text-xs text-text-muted flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ gmdate($currentLesson->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $currentLesson->duration_seconds) }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-3">
                                @if ($enrollment && ! $isCompletedEnrollment)
                                    @if ($isLessonCompleted)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Completed</span>
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="completeLesson"
                                            wire:loading.attr="disabled"
                                            class="btn btn-primary btn-sm inline-flex items-center gap-1.5"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span wire:loading.remove wire:target="completeLesson">Mark as Complete</span>
                                            <span wire:loading wire:target="completeLesson">Saving...</span>
                                        </button>
                                    @endif
                                @elseif ($isCompletedEnrollment)
                                    <span class="text-xs text-text-muted italic">
                                        Read-only access
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Lesson Title & Description --}}
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-text-primary">
                                {{ $currentLesson->title }}
                            </h2>
                            @if ($currentLesson->description)
                                <p class="text-sm text-text-secondary mt-1">
                                    {{ $currentLesson->description }}
                                </p>
                            @endif
                        </div>

                        {{-- Type-specific Content Rendering --}}
                        @if ($currentLesson->type === 'video')
                            @if ($currentLesson->media_id)
                                <div class="rounded-xl overflow-hidden border border-border-default bg-black aspect-video flex items-center justify-center">
                                    <video
                                        controls
                                        controlsList="nodownload"
                                        class="w-full h-full"
                                        preload="metadata"
                                        @timeupdate.throttle.15000ms="$wire.recordHeartbeat($event.target.currentTime)"
                                    >
                                        <source
                                            src="{{ route('media.show', $currentLesson->media_id) }}"
                                            type="{{ $currentLesson->media?->mime_type ?? 'video/mp4' }}"
                                        >
                                        Your browser does not support HTML5 video streaming.
                                    </video>
                                </div>
                            @elseif ($currentLesson->resource_reference)
                                <div class="p-6 rounded-xl border border-border-default bg-surface-card space-y-3">
                                    <div class="text-sm font-semibold text-text-primary flex items-center gap-2">
                                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>External Video Resource ({{ $currentLesson->resource_provider ?? 'External' }})</span>
                                    </div>
                                    <p class="text-xs text-text-secondary">
                                        This video is hosted externally. Click below to view the lesson material:
                                    </p>
                                    <a
                                        href="{{ $currentLesson->resource_reference }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-primary btn-sm inline-flex items-center gap-2"
                                    >
                                        <span>Watch on Provider Site</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            @else
                                <div class="p-8 rounded-xl border border-dashed border-border-default text-center text-sm text-text-muted">
                                    No video media file has been attached to this lesson yet.
                                </div>
                            @endif

                        @elseif ($currentLesson->type === 'pdf')
                            @if ($currentLesson->media_id)
                                <div class="p-6 rounded-xl border border-border-default bg-surface-card space-y-4">
                                    <div class="flex items-center justify-between gap-4 flex-wrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 flex items-center justify-center font-bold text-xs uppercase">
                                                PDF
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-text-primary">
                                                    {{ $currentLesson->media?->filename ?? 'Lesson Document (PDF)' }}
                                                </div>
                                                <div class="text-xs text-text-muted">
                                                    {{ $currentLesson->media?->size_bytes ? number_format($currentLesson->media->size_bytes / 1024, 1) . ' KB' : 'Standard Document' }}
                                                </div>
                                            </div>
                                        </div>

                                        <a
                                            href="{{ route('media.show', $currentLesson->media_id) }}"
                                            target="_blank"
                                            class="btn btn-primary btn-sm inline-flex items-center gap-2"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                            <span>Open / Download PDF</span>
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="p-8 rounded-xl border border-dashed border-border-default text-center text-sm text-text-muted">
                                    No PDF document has been uploaded for this lesson yet.
                                </div>
                            @endif

                        @elseif ($currentLesson->type === 'external')
                            <div class="p-6 rounded-xl border border-border-default bg-surface-card space-y-3">
                                <div class="text-sm font-semibold text-text-primary flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    <span>External Learning Resource</span>
                                </div>
                                <p class="text-xs text-text-secondary">
                                    This lesson links to an external learning guide, interactive sandbox, or documentation resource.
                                </p>
                                @if ($currentLesson->resource_reference)
                                    <a
                                        href="{{ $currentLesson->resource_reference }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-primary btn-sm inline-flex items-center gap-2"
                                    >
                                        <span>Open External Link</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                @else
                                    <p class="text-xs text-text-muted italic">No external URL configured.</p>
                                @endif
                            </div>
                        @endif

                        {{-- Rich Text / Reading Material --}}
                        @if ($currentLesson->content)
                            <div class="pt-4 border-t border-border-default">
                                <div class="prose prose-slate dark:prose-invert max-w-none text-text-primary text-sm sm:text-base leading-relaxed whitespace-pre-line">
                                    {{ $currentLesson->content }}
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Downloadable Lesson Materials --}}
                @if ($currentLesson->materials->isNotEmpty())
                    <x-card>
                        <div class="space-y-3">
                            <h3 class="text-sm font-semibold text-text-primary flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Lesson Materials & Downloads ({{ $currentLesson->materials->count() }})</span>
                            </h3>

                            <ul class="divide-y divide-border-default">
                                @foreach ($currentLesson->materials as $material)
                                    <li class="py-3 flex items-center justify-between gap-3">
                                        <div class="space-y-0.5">
                                            <div class="text-sm font-medium text-text-primary">
                                                {{ $material->title }}
                                            </div>
                                            @if ($material->description)
                                                <div class="text-xs text-text-secondary">
                                                    {{ $material->description }}
                                                </div>
                                            @endif
                                            @if ($material->file_size_bytes)
                                                <div class="text-[11px] text-text-muted">
                                                    {{ number_format($material->file_size_bytes / 1024, 1) }} KB
                                                </div>
                                            @endif
                                        </div>

                                        @if ($material->media_id)
                                            <a
                                                href="{{ route('media.show', $material->media_id) }}"
                                                target="_blank"
                                                class="btn btn-secondary btn-sm inline-flex items-center gap-1.5"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                                <span>Download</span>
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </x-card>
                @endif

                {{-- Previous and Next Stepper Navigation Bar --}}
                <div class="flex items-center justify-between gap-4 pt-4 border-t border-border-default">
                    <div>
                        @if ($navigation['previous'])
                            <a
                                href="{{ route('student.courses.learn', [$course, $navigation['previous']->id]) }}"
                                class="btn btn-secondary btn-sm inline-flex items-center gap-2"
                                wire:navigate
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span class="hidden sm:inline">Previous:</span>
                                <span class="truncate max-w-[140px] sm:max-w-[200px]">{{ $navigation['previous']->title }}</span>
                            </a>
                        @else
                            <button disabled class="btn btn-secondary btn-sm opacity-50 cursor-not-allowed">
                                &larr; First Lesson
                            </button>
                        @endif
                    </div>

                    <div>
                        @if ($navigation['next'])
                            @if ($navigation['next_unlocked'])
                                <a
                                    href="{{ route('student.courses.learn', [$course, $navigation['next']->id]) }}"
                                    class="btn btn-primary btn-sm inline-flex items-center gap-2"
                                    wire:navigate
                                >
                                    <span class="hidden sm:inline">Next:</span>
                                    <span class="truncate max-w-[140px] sm:max-w-[200px]">{{ $navigation['next']->title }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @else
                                <button
                                    disabled
                                    class="btn btn-secondary btn-sm opacity-60 cursor-not-allowed inline-flex items-center gap-2"
                                    title="Complete prerequisite lessons to unlock this lesson"
                                >
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <span>Next Lesson Locked</span>
                                </button>
                            @endif
                        @else
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>End of Course Curriculum</span>
                            </span>
                        @endif
                    </div>
                </div>

            @else
                <x-card>
                    <div class="p-12 text-center space-y-3">
                        <svg class="w-12 h-12 mx-auto text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <h3 class="text-base font-semibold text-text-primary">No Curriculum Available</h3>
                        <p class="text-sm text-text-secondary max-w-md mx-auto">
                            There are currently no active lessons published in this course. Please check back soon or contact your trainer.
                        </p>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Curriculum Sidebar (4 Cols on Desktop, hidden on mobile unless toggled) --}}
        <div class="lg:col-span-4 space-y-6 {{ $mobileCurriculumOpen ? 'block' : 'hidden lg:block' }}">
            {{-- Course Progress Card --}}
            <x-card>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-text-primary">Course Progress</span>
                        <span class="font-bold text-primary-600 dark:text-primary-400">
                            {{ $curriculum['progress_percent'] }}%
                        </span>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="w-full bg-border-muted/50 rounded-full h-2 overflow-hidden border border-border-default">
                        <div
                            class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                            style="width: {{ $curriculum['progress_percent'] }}%"
                        ></div>
                    </div>

                    <div class="text-xs text-text-muted flex items-center justify-between">
                        <span>{{ $curriculum['completed_lessons'] }} of {{ $curriculum['total_lessons'] }} completed</span>
                        @if ($isCompletedEnrollment)
                            <span class="text-emerald-600 font-medium">Completed</span>
                        @endif
                    </div>

                    @if (!empty($curriculum['course_assessments']))
                        <div class="pt-2 border-t border-border-default/60 space-y-1">
                            <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider block mb-1">Final / Course Assessments</span>
                            @foreach ($curriculum['course_assessments'] as $cAssessment)
                                <a href="{{ route('student.courses.assessments.show', [$course, $cAssessment['id']]) }}"
                                   class="flex items-center justify-between p-2 rounded bg-surface-bg/40 hover:bg-surface-bg text-xs font-medium transition">
                                    <div class="flex items-center gap-2">
                                        <span class="w-4 h-4 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px]">E</span>
                                        <span class="text-text-primary">{{ $cAssessment['title'] }}</span>
                                        @if($cAssessment['is_required'])
                                            <span class="text-[10px] bg-red-100 text-red-700 px-1 rounded">Required</span>
                                        @endif
                                    </div>
                                    <div>
                                        @if($cAssessment['is_passed'])
                                            <span class="text-emerald-600 font-bold">&check; Passed</span>
                                        @else
                                            <span class="text-indigo-600">Start &rarr;</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Module & Lesson Curriculum Tree --}}
            <div class="space-y-4">
                @forelse ($curriculum['modules'] as $moduleIndex => $module)
                    <div class="rounded-xl border border-border-default bg-surface-card overflow-hidden">
                        {{-- Module Header --}}
                        <div class="px-4 py-3 bg-surface-bg/60 border-b border-border-default">
                            <div class="text-xs font-semibold text-text-muted uppercase tracking-wider">
                                Module {{ $moduleIndex + 1 }}
                            </div>
                            <h4 class="text-sm font-bold text-text-primary">
                                {{ $module['title'] }}
                            </h4>
                            @if ($module['description'])
                                <p class="text-xs text-text-secondary mt-0.5 line-clamp-1">
                                    {{ $module['description'] }}
                                </p>
                            @endif
                        </div>

                        {{-- Module Assessments (if any) --}}
                        @if (!empty($module['assessments']))
                            <div class="bg-indigo-50/40 dark:bg-indigo-950/20 px-3 py-2 border-b border-border-default space-y-1">
                                @foreach ($module['assessments'] as $mAssessment)
                                    <a href="{{ route('student.courses.assessments.show', [$course, $mAssessment['id']]) }}"
                                       class="flex items-center justify-between p-2 rounded text-xs font-medium hover:bg-white dark:hover:bg-surface-card transition">
                                        <div class="flex items-center gap-2">
                                            <span class="w-4 h-4 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px]">Q</span>
                                            <span class="text-text-primary">{{ $mAssessment['title'] }}</span>
                                            @if($mAssessment['is_required'])
                                                <span class="text-[10px] bg-red-100 text-red-700 px-1 rounded">Required</span>
                                            @endif
                                        </div>
                                        <div>
                                            @if($mAssessment['is_passed'])
                                                <span class="text-emerald-600 font-bold">&check; Passed</span>
                                            @else
                                                <span class="text-indigo-600">Take Assessment &rarr;</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        {{-- Lessons List inside Module --}}
                        <div class="divide-y divide-border-default/60">
                            @forelse ($module['lessons'] as $mIndex => $lessonItem)
                                @if ($lessonItem['is_unlocked'])
                                    <a
                                        href="{{ route('student.courses.learn', [$course, $lessonItem['id']]) }}"
                                        wire:navigate
                                        class="p-3 flex items-start gap-3 transition-colors {{ $lessonItem['is_current'] ? 'bg-primary-50/60 dark:bg-primary-950/40 border-l-4 border-primary-600' : 'hover:bg-surface-bg/50' }}"
                                    >
                                        {{-- Completion / Status Icon --}}
                                        <div class="mt-0.5 shrink-0">
                                            @if ($lessonItem['is_completed'])
                                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 flex items-center justify-center">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </span>
                                            @elseif ($lessonItem['is_current'])
                                                <span class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300 flex items-center justify-center font-bold text-[10px]">
                                                    &bull;
                                                </span>
                                            @else
                                                <span class="w-5 h-5 rounded-full border-2 border-border-default flex items-center justify-center text-[10px] text-text-muted font-medium">
                                                    {{ $mIndex + 1 }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Lesson Details --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-medium {{ $lessonItem['is_current'] ? 'text-primary-700 dark:text-primary-300 font-bold' : 'text-text-primary' }} line-clamp-2">
                                                {{ $lessonItem['title'] }}
                                            </div>
                                            <div class="flex items-center gap-2 mt-1 text-[11px] text-text-muted">
                                                <span class="capitalize">{{ $lessonItem['type'] }}</span>
                                                @if ($lessonItem['duration_seconds'] > 0)
                                                    <span>&bull;</span>
                                                    <span>{{ gmdate($lessonItem['duration_seconds'] >= 3600 ? 'H:i:s' : 'i:s', $lessonItem['duration_seconds']) }}</span>
                                                @endif
                                                @if ($lessonItem['materials_count'] > 0)
                                                    <span>&bull;</span>
                                                    <span>{{ $lessonItem['materials_count'] }} {{ \Illuminate\Support\Str::plural('file', $lessonItem['materials_count']) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @else
                                    {{-- Locked Lesson Item --}}
                                    <div class="p-3 flex items-start gap-3 opacity-60 bg-surface-bg/30 cursor-not-allowed">
                                        <div class="mt-0.5 shrink-0 text-text-muted">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-medium text-text-muted line-clamp-2 flex items-center gap-1.5">
                                                <span>{{ $lessonItem['title'] }}</span>
                                                <span class="badge badge-neutral text-[9px] py-0 px-1">Locked</span>
                                            </div>
                                            <div class="text-[11px] text-text-muted mt-0.5 italic">
                                                Requires prerequisite completion
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div class="p-3 text-xs text-text-muted italic">
                                    No lessons published in this module yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="p-4 rounded-xl border border-dashed border-border-default text-center text-xs text-text-muted">
                        No modules created yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
