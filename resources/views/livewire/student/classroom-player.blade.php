<div class="flex-1 flex flex-col bg-slate-50 min-h-screen">
    {{-- Unified Classroom Header --}}
    <header class="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-xs px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
        {{-- Left: Back to Courses, Academy Branding & Course Title --}}
        <div class="flex items-center gap-3 min-w-0">
            <a
                href="{{ route('student.courses.index') }}"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors shrink-0"
                title="Return to My Courses"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span class="hidden sm:inline">My Courses</span>
            </a>

            <div class="h-4 w-px bg-slate-200 shrink-0"></div>

            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 truncate">
                        {{ $course->academy?->name ?? 'SEO Academy' }}
                    </span>
                    <span class="text-slate-300">&bull;</span>
                    <span class="text-[11px] font-medium text-slate-500 truncate">
                        {{ $course->category?->name ?? 'Course' }}
                    </span>
                    @if ($isCompletedEnrollment)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            Course Completed • Read-Only
                        </span>
                    @endif
                </div>
                <h1 class="text-sm sm:text-base font-bold text-slate-900 truncate" title="{{ $course->title }}">
                    {{ $course->title }}
                </h1>
            </div>
        </div>

        {{-- Center / Right: Progress, PDF Quick Download & Mobile Drawer Toggle --}}
        <div class="flex items-center gap-3 shrink-0">
            {{-- Quick PDF Download in Header (Desktop) --}}
            @if ($currentLesson)
                <a
                    href="{{ route('student.courses.lessons.material-pdf', [$course, $currentLesson]) }}"
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-colors shadow-xs"
                    target="_blank"
                    title="Download branded one-page PDF lesson notes"
                >
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Lesson PDF</span>
                </a>
            @endif

            {{-- Course Progress Widget (Desktop) --}}
            <div class="hidden md:flex items-center gap-3 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200">
                <div class="text-right">
                    <div class="text-xs font-semibold text-slate-800">
                        {{ $curriculum['progress_percent'] }}% <span class="text-slate-400 font-normal">Complete</span>
                    </div>
                    <div class="text-[11px] text-slate-500">
                        {{ $curriculum['completed_lessons'] }}/{{ $curriculum['total_lessons'] }} lessons
                    </div>
                </div>
                <div class="w-20 bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div
                        class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                        style="width: {{ $curriculum['progress_percent'] }}%"
                    ></div>
                </div>
            </div>

            {{-- Certificate Pill (If Completed) --}}
            @if ($isCompletedEnrollment && $certificateNumber)
                <a
                    href="{{ route('student.certificates.index') }}"
                    class="hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors"
                >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                    </svg>
                    <span>Certificate: {{ $certificateNumber }}</span>
                </a>
            @endif

            {{-- Mobile Drawer Trigger --}}
            <button
                type="button"
                wire:click="toggleMobileCurriculum"
                class="lg:hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-colors"
                aria-label="Toggle curriculum outline"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                </svg>
                <span>Curriculum</span>
            </button>
        </div>
    </header>

    @if ($isCompletedEnrollment)
        <div class="bg-emerald-50 border-b border-emerald-200 px-4 sm:px-8 py-2.5 flex items-center justify-between text-xs text-emerald-900">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><strong>Course Completed</strong> &bull; You retain permanent Read-only access to all lessons and materials.</span>
            </div>
            @if ($certificateNumber)
                <a
                    href="{{ route('student.certificates.index') }}"
                    class="font-semibold text-emerald-700 hover:text-emerald-800 underline ml-2 shrink-0"
                >
                    View Certificate
                </a>
            @endif
        </div>
    @endif

    {{-- Main Workspace Grid --}}
    <div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- Left Area: Player & Lesson Workspace (8 cols) --}}
        <div class="lg:col-span-8 space-y-6">
            @if ($currentLesson)
                {{-- Video or Content Screen --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                    @if ($currentLesson->type === 'video')
                        @if ($currentLesson->media_id)
                            <div
                                class="bg-slate-950 aspect-video w-full relative flex items-center justify-center overflow-hidden"
                                x-data="{
                                    savedPos: {{ (float) $lastPositionSeconds }},
                                    hasResumed: false,
                                    playbackError: false
                                }"
                                x-init="
                                    $nextTick(() => {
                                        const vid = $refs.lessonVideo;
                                        if (vid && savedPos > 2) {
                                            const applySavedPosition = () => {
                                                if (!hasResumed) {
                                                    hasResumed = true;
                                                    try {
                                                        if (vid.duration && savedPos < vid.duration) {
                                                            vid.currentTime = savedPos;
                                                        } else if (!vid.duration) {
                                                            vid.currentTime = savedPos;
                                                        }
                                                    } catch (e) {}
                                                }
                                            };
                                            if (vid.readyState >= 1) {
                                                applySavedPosition();
                                            } else {
                                                vid.addEventListener('loadedmetadata', applySavedPosition, { once: true });
                                            }
                                        }
                                    });
                                "
                            >
                                <video
                                    x-ref="lessonVideo"
                                    controls
                                    controlsList="nodownload"
                                    class="w-full h-full object-contain"
                                    preload="metadata"
                                    @timeupdate.throttle.15000ms="$wire.recordHeartbeat($event.target.currentTime)"
                                    x-on:error="playbackError = true"
                                >
                                    <source
                                        src="{{ route('media.show', $currentLesson->media_id) }}"
                                        type="{{ $currentLesson->media?->mime_type ?? 'video/mp4' }}"
                                    >
                                    Your browser does not support HTML5 video streaming.
                                </video>

                                {{-- Playback Error Overlay --}}
                                <div
                                    x-show="playbackError"
                                    x-cloak
                                    class="absolute inset-0 bg-slate-950/90 flex flex-col items-center justify-center p-6 text-center text-white"
                                >
                                    <svg class="w-10 h-10 text-amber-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <h4 class="text-sm font-semibold">Video Stream Unavailable</h4>
                                    <p class="text-xs text-slate-300 mt-1 max-w-sm">
                                        The requested media file could not be loaded. Please verify your connection or contact your trainer.
                                    </p>
                                    <button
                                        type="button"
                                        @click="playbackError = false; $refs.lessonVideo?.load()"
                                        class="mt-3 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white transition-colors"
                                    >
                                        Retry Playback
                                    </button>
                                </div>
                            </div>
                        @elseif ($currentLesson->resource_reference)
                            <div class="p-8 sm:p-10 bg-slate-900 text-white aspect-video flex flex-col items-center justify-center text-center">
                                <div class="w-14 h-14 rounded-full bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-indigo-400 mb-4">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold">External Video Lesson</h3>
                                <p class="text-xs text-slate-300 mt-1 max-w-md">
                                    This lesson is hosted on an external video resource ({{ $currentLesson->resource_provider ?? 'External' }}).
                                </p>
                                <a
                                    href="{{ $currentLesson->resource_reference }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-semibold text-xs text-white shadow-sm transition-colors"
                                >
                                    <span>Watch Lesson on External Host</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                        @else
                            <div class="p-12 text-center bg-slate-100 text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <p class="text-sm font-medium">No video media has been uploaded for this lesson yet.</p>
                            </div>
                        @endif

                    @elseif ($currentLesson->type === 'pdf')
                        <div class="p-8 sm:p-10 bg-slate-50 border-b border-slate-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs uppercase shadow-xs">
                                        PDF
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">
                                            {{ $currentLesson->media?->filename ?? $currentLesson->title . ' (Document)' }}
                                        </h3>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            {{ $currentLesson->media?->size_bytes ? number_format($currentLesson->media->size_bytes / 1024, 1) . ' KB' : 'Standard PDF Material' }}
                                        </p>
                                    </div>
                                </div>

                                @if ($currentLesson->media_id)
                                    <a
                                        href="{{ route('media.show', $currentLesson->media_id) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <span>Download PDF Document</span>
                                    </a>
                                @endif
                            </div>
                        </div>

                    @elseif ($currentLesson->type === 'external')
                        <div class="p-8 sm:p-10 bg-slate-50 border-b border-slate-200">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </div>
                                <div class="space-y-2">
                                    <h3 class="text-base font-bold text-slate-900">External Interactive Resource</h3>
                                    <p class="text-xs text-slate-600">
                                        This lesson incorporates an external interactive tool, documentation repository, or sandbox environment.
                                    </p>
                                    @if ($currentLesson->resource_reference)
                                        <a
                                            href="{{ $currentLesson->resource_reference }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors"
                                        >
                                            <span>Open Resource in New Tab</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Lesson Meta Header Bar --}}
                    <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ ucfirst(str_replace('_', ' ', $currentLesson->type)) }}
                                </span>
                                @if ($currentLesson->duration_seconds > 0)
                                    <span class="text-xs text-slate-500 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ gmdate($currentLesson->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $currentLesson->duration_seconds) }}
                                    </span>
                                @endif
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                                {{ $currentLesson->title }}
                            </h2>
                        </div>

                        {{-- Mark as Complete Reactive CTA --}}
                        <div class="shrink-0">
                            @if ($enrollment && ! $isCompletedEnrollment)
                                @if ($isLessonCompleted)
                                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-xs">
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Lesson Completed</span>
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="completeLesson"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span wire:loading.remove wire:target="completeLesson">Mark as Complete</span>
                                        <span wire:loading wire:target="completeLesson">Saving...</span>
                                    </button>
                                @endif
                            @elseif ($isCompletedEnrollment)
                                <span class="text-xs text-slate-500 italic">Course completed • Read-only review</span>
                            @endif
                        </div>
                    </div>

                    {{-- Tab Navigation (Overview, Materials) & Actions --}}
                    <div class="border-b border-slate-200 px-6 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-6">
                            <button
                                type="button"
                                wire:click="setActiveTab('overview')"
                                class="py-3 text-xs font-bold border-b-2 transition-colors {{ $activeTab === 'overview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                            >
                                Overview & Notes
                            </button>
                            <button
                                type="button"
                                wire:click="setActiveTab('materials')"
                                class="py-3 text-xs font-bold border-b-2 transition-colors flex items-center gap-1.5 {{ $activeTab === 'materials' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                            >
                                <span>Downloads & Resources</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700 font-semibold">
                                    {{ $currentLesson->materials->count() + 1 }}
                                </span>
                            </button>
                        </div>

                        {{-- Direct PDF Download Trigger --}}
                        <div class="shrink-0">
                            <a
                                href="{{ route('student.courses.lessons.material-pdf', [$course, $currentLesson]) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-colors shadow-xs"
                                title="Download branded one-page PDF study notes"
                            >
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Download Notes (PDF)</span>
                            </a>
                        </div>
                    </div>

                    {{-- Tab Content --}}
                    <div class="p-6 sm:p-8">
                        <div x-show="$wire.activeTab === 'overview'">
                            <div class="space-y-5">
                                @if ($currentLesson->description)
                                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm font-medium text-slate-700">
                                        <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 mb-1">Lesson Summary</div>
                                        {{ $currentLesson->description }}
                                    </div>
                                @endif

                                @if ($currentLesson->content)
                                    <div class="prose prose-slate max-w-none text-slate-800 text-sm sm:text-base leading-relaxed pt-1">
                                        {!! app(\App\Services\Pdf\LessonMaterialPdfService::class)->sanitizeAndFormatContent($currentLesson->content) !!}
                                    </div>
                                @else
                                    <p class="text-xs text-slate-400 italic">No additional reading notes provided for this lesson.</p>
                                @endif
                            </div>
                        </div>

                        <div x-show="$wire.activeTab === 'materials'" class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Lesson Resources & Downloads</h4>

                            {{-- Official One-Page PDF Study Notes Card --}}
                            <div class="p-4 rounded-xl border border-indigo-200 bg-indigo-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs uppercase shadow-xs shrink-0">
                                        PDF
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-indigo-900 truncate">
                                                Official Lesson Study Notes (1-Page PDF)
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">
                                                Branded Handout
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 mt-0.5">
                                            Formatted single-page study guide with lesson overview, key takeaways, and references.
                                        </div>
                                    </div>
                                </div>

                                <a
                                    href="{{ route('student.courses.lessons.material-pdf', [$course, $currentLesson]) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors shrink-0 justify-center"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Download One-Page PDF</span>
                                </a>
                            </div>

                            @if ($currentLesson->materials->isNotEmpty())
                                <ul class="divide-y divide-slate-100 pt-2">
                                    @foreach ($currentLesson->materials as $mat)
                                        <li class="py-3 flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold text-slate-900 truncate">
                                                        {{ $mat->title }}
                                                    </div>
                                                    @if ($mat->file_size_bytes)
                                                        <div class="text-[11px] text-slate-400">
                                                            {{ number_format($mat->file_size_bytes / 1024, 1) }} KB
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($mat->media_id)
                                                <a
                                                    href="{{ route('media.show', $mat->media_id) }}"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition-colors shrink-0"
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
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Bottom Previous and Next Stepper Navigation Bar --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex items-center justify-between gap-4 shadow-xs">
                    <div>
                        @if ($navigation['previous'])
                            <a
                                href="{{ route('student.courses.learn', [$course, $navigation['previous']->id]) }}"
                                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors"
                                wire:navigate
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span class="hidden sm:inline">Previous:</span>
                                <span class="truncate max-w-[120px] sm:max-w-[180px]">{{ $navigation['previous']->title }}</span>
                            </a>
                        @else
                            <button disabled class="px-3.5 py-2 rounded-xl border border-slate-200 text-slate-300 text-xs font-medium cursor-not-allowed">
                                &larr; First Lesson
                            </button>
                        @endif
                    </div>

                    <div>
                        @if ($navigation['next'])
                            @if ($navigation['next_unlocked'])
                                <a
                                    href="{{ route('student.courses.learn', [$course, $navigation['next']->id]) }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors"
                                    wire:navigate
                                >
                                    <span class="hidden sm:inline">Next:</span>
                                    <span class="truncate max-w-[120px] sm:max-w-[180px]">{{ $navigation['next']->title }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @else
                                <button
                                    disabled
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-slate-400 text-xs font-semibold cursor-not-allowed"
                                    title="Complete prerequisite lessons to unlock this lesson"
                                >
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <span>Next Lesson Locked</span>
                                </button>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>End of Course Curriculum</span>
                            </span>
                        @endif
                    </div>
                </div>

            @else
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="text-base font-bold text-slate-900">No Curriculum Available</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">There are currently no published lessons in this course curriculum.</p>
                </div>
            @endif
        </div>

        {{-- Right Area: Udemy-Style Sticky Curriculum Sidebar (4 cols on Desktop) --}}
        <aside class="hidden lg:block lg:col-span-4 sticky top-20 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                {{-- Header --}}
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Course Content</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ $curriculum['completed_lessons'] }}/{{ $curriculum['total_lessons'] }} completed ({{ $curriculum['progress_percent'] }}%)
                        </p>
                    </div>
                </div>

                {{-- Modules Accordion List --}}
                <div class="divide-y divide-slate-100 max-h-[calc(100vh-180px)] overflow-y-auto">
                    @foreach ($curriculum['modules'] as $moduleIdx => $module)
                        @php
                            $containsActive = collect($module['lessons'])->contains('is_current', true);
                        @endphp
                        <div x-data="{ open: {{ $containsActive ? 'true' : ($moduleIdx === 0 ? 'true' : 'false') }} }">
                            {{-- Module Header Button --}}
                            <button
                                type="button"
                                @click="open = !open"
                                class="w-full p-3.5 text-left flex items-center justify-between gap-3 bg-slate-50/70 hover:bg-slate-100/70 transition-colors"
                            >
                                <div class="min-w-0">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                        Section {{ $moduleIdx + 1 }}
                                    </div>
                                    <h4 class="text-xs font-bold text-slate-900 truncate">
                                        {{ $module['title'] }}
                                    </h4>
                                    <span class="text-[10px] text-slate-500">
                                        {{ count($module['lessons']) }} lessons
                                    </span>
                                </div>
                                <svg
                                    class="w-4 h-4 text-slate-400 transform transition-transform duration-200 shrink-0"
                                    :class="{ 'rotate-180': open }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            {{-- Lessons List in Module --}}
                            <ul x-show="open" x-collapse class="divide-y divide-slate-100 bg-white">
                                @foreach ($module['lessons'] as $lessonItem)
                                    @php
                                        $isCurrent = $lessonItem['is_current'] ?? false;
                                        $isCompleted = $lessonItem['is_completed'] ?? false;
                                        $isUnlocked = $lessonItem['is_unlocked'] ?? false;
                                    @endphp
                                    <li>
                                        @if ($isUnlocked)
                                            <a
                                                href="{{ route('student.courses.learn', [$course, $lessonItem['id']]) }}"
                                                wire:navigate
                                                class="p-3 text-left flex items-start gap-3 transition-colors {{ $isCurrent ? 'bg-indigo-50/90 border-l-4 border-indigo-600' : 'hover:bg-slate-50' }}"
                                            >
                                                {{-- Status Icon --}}
                                                <div class="mt-0.5 shrink-0">
                                                    @if ($isCompleted)
                                                        <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </div>
                                                    @elseif ($isCurrent)
                                                        <div class="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M8 5v14l11-7z" />
                                                            </svg>
                                                        </div>
                                                    @else
                                                        <div class="w-4 h-4 rounded-full border border-slate-300"></div>
                                                    @endif
                                                </div>

                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs font-semibold truncate {{ $isCurrent ? 'text-indigo-950 font-bold' : 'text-slate-800' }}">
                                                        {{ $lessonItem['title'] }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                                                        <span class="capitalize">{{ $lessonItem['type'] ?? 'lesson' }}</span>
                                                        @if (!empty($lessonItem['duration_seconds']))
                                                            <span>&bull;</span>
                                                            <span>{{ gmdate('i:s', $lessonItem['duration_seconds']) }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </a>
                                        @else
                                            <div class="p-3 text-left flex items-start gap-3 opacity-60 bg-slate-50/40 cursor-not-allowed">
                                                <div class="mt-0.5 shrink-0 text-slate-400">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs font-medium text-slate-500 truncate">
                                                        {{ $lessonItem['title'] }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5">Locked &bull; Complete prerequisites</div>
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>

    {{-- Mobile Curriculum Slide-Over Drawer --}}
    <div
        x-show="$wire.mobileCurriculumOpen"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden flex justify-end"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            x-show="$wire.mobileCurriculumOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="$wire.toggleMobileCurriculum"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
        ></div>

        {{-- Slide-over Panel --}}
        <div
            x-show="$wire.mobileCurriculumOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="relative w-full max-w-xs sm:max-w-sm bg-white h-full shadow-2xl flex flex-col z-10"
        >
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Course Curriculum</h3>
                    <p class="text-[11px] text-slate-500">{{ $curriculum['completed_lessons'] }}/{{ $curriculum['total_lessons'] }} completed</p>
                </div>
                <button
                    type="button"
                    wire:click="toggleMobileCurriculum"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-200 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2">
                @foreach ($curriculum['modules'] as $moduleIdx => $module)
                    <div class="mb-3">
                        <div class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50 rounded-lg">
                            Section {{ $moduleIdx + 1 }}: {{ $module['title'] }}
                        </div>
                        <ul class="mt-1 space-y-1">
                            @foreach ($module['lessons'] as $lessonItem)
                                @php
                                    $isCurrent = $lessonItem['is_current'] ?? false;
                                    $isCompleted = $lessonItem['is_completed'] ?? false;
                                    $isUnlocked = $lessonItem['is_unlocked'] ?? false;
                                @endphp
                                <li>
                                    @if ($isUnlocked)
                                        <a
                                            href="{{ route('student.courses.learn', [$course, $lessonItem['id']]) }}"
                                            @click="$wire.toggleMobileCurriculum"
                                            wire:navigate
                                            class="p-2.5 rounded-xl flex items-center gap-2.5 text-xs font-semibold {{ $isCurrent ? 'bg-indigo-50 text-indigo-900 border border-indigo-200' : 'text-slate-800 hover:bg-slate-50' }}"
                                        >
                                            @if ($isCompleted)
                                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            @elseif ($isCurrent)
                                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z" />
                                                </svg>
                                            @else
                                                <div class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0"></div>
                                            @endif
                                            <span class="truncate">{{ $lessonItem['title'] }}</span>
                                        </a>
                                    @else
                                        <div class="p-2.5 rounded-xl flex items-center gap-2.5 text-xs text-slate-400 opacity-60">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            <span class="truncate">{{ $lessonItem['title'] }}</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
