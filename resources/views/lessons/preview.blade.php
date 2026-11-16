@component('layouts.app', ['title' => $lesson->title . ' — Preview'])
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-sm font-medium text-amber-900 dark:text-amber-200">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                <span>
                    Lesson Preview &mdash; {{ $course->title }} &bull; {{ $lesson->module->title }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                @if (auth()->user()?->hasRole('admin'))
                    <a href="{{ route('admin.courses.structure', $course) }}" class="btn btn-secondary btn-sm">
                        Back to Structure
                    </a>
                @elseif (auth()->user()?->hasRole('trainer') && auth()->user()?->can('update', $course))
                    <a href="{{ route('trainer.courses.structure', $course) }}" class="btn btn-secondary btn-sm">
                        Back to Structure
                    </a>
                @endif

                <a href="{{ route('courses.public.show', $course->slug) }}" class="btn btn-secondary btn-sm">
                    Course Overview
                </a>
            </div>
        </div>

        <x-card>
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge color="blue">{{ ucfirst(str_replace('_', ' ', $lesson->type)) }}</x-badge>
                    @if ($lesson->isFreePreview())
                        <x-badge color="green">Free Preview</x-badge>
                    @endif
                    @if ($lesson->duration_seconds > 0)
                        <span class="text-xs text-text-muted">
                            Duration: {{ gmdate($lesson->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $lesson->duration_seconds) }}
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl font-bold text-text-primary">{{ $lesson->title }}</h1>

                @if ($lesson->description)
                    <p class="text-sm text-text-secondary">{{ $lesson->description }}</p>
                @endif

                {{-- Type-specific Content --}}
                @if ($lesson->type === 'video')
                    @if ($lesson->media_id)
                        <div class="rounded-lg overflow-hidden border border-border-default bg-black">
                            <video controls class="w-full max-h-[480px]" preload="metadata">
                                <source src="{{ route('media.show', $lesson->media_id) }}" type="{{ $lesson->media?->mime_type ?? 'video/mp4' }}">
                                Your browser does not support HTML5 video playback.
                            </video>
                        </div>
                    @elseif ($lesson->resource_reference)
                        <div class="rounded-lg border border-border-default bg-border-muted/30 p-4 text-sm space-y-2">
                            <div class="font-medium text-text-primary">
                                External Video Provider ({{ $lesson->resource_provider ?? 'external' }})
                            </div>
                            <a
                                href="{{ $lesson->resource_reference }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary-600 hover:underline break-all"
                            >
                                {{ $lesson->resource_reference }}
                            </a>
                        </div>
                    @else
                        <div class="rounded-lg border border-border-default bg-border-muted/30 p-4 text-sm text-text-muted">
                            No video source has been attached to this lesson yet.
                        </div>
                    @endif
                @elseif ($lesson->type === 'pdf')
                    @if ($lesson->media_id)
                        <div class="space-y-3">
                            <div class="flex items-center justify-between rounded-lg border border-border-default p-4 bg-border-muted/20">
                                <span class="text-sm font-medium text-text-primary">
                                    {{ $lesson->media?->filename ?? 'Lesson PDF Document' }}
                                </span>
                                <a
                                    href="{{ route('media.show', $lesson->media_id) }}"
                                    target="_blank"
                                    class="btn btn-primary btn-sm"
                                >
                                    Open / Download PDF
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-border-default bg-border-muted/30 p-4 text-sm text-text-muted">
                            No PDF document has been uploaded for this lesson yet.
                        </div>
                    @endif
                @elseif ($lesson->type === 'external')
                    <div class="rounded-lg border border-border-default bg-border-muted/30 p-4 text-sm space-y-2">
                        <div class="font-medium text-text-primary">External Learning Resource</div>
                        @if ($lesson->resource_reference)
                            <a
                                href="{{ $lesson->resource_reference }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-primary btn-sm inline-flex"
                            >
                                Open External Resource
                            </a>
                        @else
                            <p class="text-text-muted">No external URL configured yet.</p>
                        @endif
                    </div>
                @elseif (in_array($lesson->type, ['live_class', 'assignment'], true))
                    <div class="rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-950/30 dark:border-blue-800 p-4 text-sm text-blue-900 dark:text-blue-200">
                        <strong>{{ $lesson->type === 'live_class' ? 'Live Class Session' : 'Course Assignment' }}:</strong>
                        This activity will be available to enrolled learners inside the learning workspace.
                        @if ($lesson->resource_reference)
                            <div class="mt-2">
                                Reference: <span class="font-mono text-xs">{{ $lesson->resource_reference }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Rich Text / Article Content --}}
                @if ($lesson->content)
                    <div class="pt-4 border-t border-border-default prose prose-sm max-w-none text-text-secondary whitespace-pre-line">
                        {{ $lesson->content }}
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Downloadable Materials --}}
        @if ($lesson->materials->isNotEmpty())
            <x-card>
                <h2 class="text-base font-semibold text-text-primary mb-3">Downloadable Materials</h2>
                <ul class="divide-y divide-border-default">
                    @foreach ($lesson->materials as $material)
                        <li class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-text-primary">{{ $material->title }}</div>
                                @if ($material->description)
                                    <div class="text-xs text-text-muted">{{ $material->description }}</div>
                                @endif
                            </div>
                            @if ($material->media_id)
                                <a
                                    href="{{ route('media.show', $material->media_id) }}"
                                    target="_blank"
                                    class="btn btn-secondary btn-sm"
                                >
                                    Download PDF
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
@endcomponent
