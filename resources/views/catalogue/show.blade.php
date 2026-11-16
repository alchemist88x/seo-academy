@component(auth()->check() ? 'layouts.app' : 'layouts.guest', ['title' => $course->title])
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @if ($isPreview ?? false)
            <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
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

        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-1">
                    @if ($course->thumbnailUrl())
                        <img
                            src="{{ $course->thumbnailUrl() }}"
                            alt="{{ $course->title }}"
                            class="w-full h-52 object-cover rounded-lg border border-border-default"
                        />
                    @else
                        <div class="w-full h-52 rounded-lg bg-border-muted flex items-center justify-center text-sm font-semibold text-text-muted">
                            {{ $course->category?->name ?? 'SEO Academy' }}
                        </div>
                    @endif
                </div>

                <div class="md:col-span-2 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge color="blue">{{ $course->difficulty }}</x-badge>
                        @if ($course->category)
                            <x-badge color="gray">{{ $course->category->name }}</x-badge>
                        @endif
                        @if ($course->academy)
                            <span class="text-xs text-text-muted">{{ $course->academy->name }}</span>
                        @endif
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-bold text-text-primary">{{ $course->title }}</h1>

                    <div class="flex flex-wrap items-center gap-4 text-sm text-text-secondary">
                        <div>
                            <span class="text-text-muted">Price:</span>
                            <span class="font-semibold text-text-primary">
                                {{ $course->is_free ? 'Free' : $course->currency . ' ' . number_format((float) $course->price, 2) }}
                            </span>
                        </div>
                        @if ($course->duration_months > 0)
                            <div>
                                <span class="text-text-muted">Duration:</span>
                                <span class="font-semibold text-text-primary">{{ $course->duration_months }} {{ \Illuminate\Support\Str::plural('month', $course->duration_months) }}</span>
                            </div>
                        @endif
                        @if ($course->enrollment_limit)
                            <div>
                                <span class="text-text-muted">Capacity:</span>
                                <span class="font-semibold text-text-primary">{{ $course->enrollment_limit }} seats</span>
                            </div>
                        @endif
                    </div>

                    @if ($course->description)
                        <div class="prose prose-sm max-w-none text-text-secondary whitespace-pre-line">
                            {{ $course->description }}
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Trainers --}}
            <x-card>
                <h2 class="text-base font-semibold text-text-primary mb-3">Instructors</h2>
                @if ($course->trainers->isEmpty())
                    <p class="text-sm text-text-muted">No trainers assigned yet.</p>
                @else
                    <ul class="space-y-2.5">
                        @foreach ($course->trainers as $trainer)
                            <li class="flex items-center justify-between text-sm">
                                <span class="font-medium text-text-primary">{{ $trainer->name }}</span>
                                @if ($trainer->pivot?->is_primary)
                                    <x-badge color="blue">Primary</x-badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Prerequisites --}}
            <x-card>
                <h2 class="text-base font-semibold text-text-primary mb-3">Prerequisites</h2>
                @if ($course->prerequisites->isEmpty())
                    <p class="text-sm text-text-muted">No prerequisites required.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($course->prerequisites as $prereq)
                            <li class="text-sm text-text-secondary flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-600 shrink-0"></span>
                                <span>{{ $prereq->title }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Course Curriculum Outline --}}
        <x-card>
            <h2 class="text-lg font-semibold text-text-primary mb-4">Course Curriculum</h2>
            @php
                $visibleModules = $course->modules->filter(
                    fn ($m) => ($isPreview ?? false) || $m->status === 'Active'
                );
            @endphp

            @if ($visibleModules->isEmpty())
                <p class="text-sm text-text-muted">Curriculum outline will be published soon.</p>
            @else
                <div class="space-y-4">
                    @foreach ($visibleModules as $moduleIndex => $module)
                        @php
                            $visibleLessons = $module->lessons->filter(
                                fn ($l) => ($isPreview ?? false) || ($l->status === 'Active' && $l->visibility !== 'hidden')
                            );
                        @endphp
                        <div class="rounded-lg border border-border-default overflow-hidden">
                            <div class="px-4 py-3 bg-border-muted/40 border-b border-border-default flex items-center justify-between gap-2">
                                <div class="font-semibold text-sm text-text-primary">
                                    Module {{ $moduleIndex + 1 }}: {{ $module->title }}
                                </div>
                                <span class="text-xs text-text-muted">
                                    {{ $visibleLessons->count() }} {{ \Illuminate\Support\Str::plural('lesson', $visibleLessons->count()) }}
                                </span>
                            </div>

                            @if ($visibleLessons->isEmpty())
                                <div class="px-4 py-3 text-xs text-text-muted">No lessons available in this module.</div>
                            @else
                                <ul class="divide-y divide-border-default">
                                    @foreach ($visibleLessons as $lesson)
                                        <li class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-sm">
                                            <div class="flex flex-wrap items-center gap-2 min-w-0">
                                                <span class="font-medium text-text-primary">{{ $lesson->title }}</span>
                                                <x-badge color="blue">{{ ucfirst(str_replace('_', ' ', $lesson->type)) }}</x-badge>
                                                @if ($lesson->isFreePreview())
                                                    <x-badge color="green">Free Preview</x-badge>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-3 shrink-0">
                                                @if ($lesson->duration_seconds > 0)
                                                    <span class="text-xs text-text-muted">
                                                        {{ gmdate($lesson->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $lesson->duration_seconds) }}
                                                    </span>
                                                @endif

                                                @if (auth()->check() && auth()->user()->can('preview', $lesson))
                                                    <a
                                                        href="{{ route('lessons.preview', [$course, $lesson]) }}"
                                                        class="btn btn-secondary btn-sm"
                                                    >
                                                        Preview
                                                    </a>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
@endcomponent
