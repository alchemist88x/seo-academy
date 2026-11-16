<div class="space-y-6">
    <x-page-header
        title="My Courses"
        :subtitle="'Your enrolled courses and learning progress in ' . $academy->name . '.'"
    />

    @if (! $hasAnyEnrollments)
        <x-card>
            <x-empty-state
                title="You are not currently enrolled in any courses"
                message="You are not currently enrolled in any courses. Please contact your academy administrator to get enrolled."
            />
        </x-card>
    @else
        {{-- Filters --}}
        <x-card>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
                <div class="lg:col-span-2">
                    <x-input
                        name="search"
                        label="Search Courses"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by course title or description..."
                    />
                </div>

                <x-select
                    name="status"
                    label="Status"
                    wire:model.live="status"
                    placeholder="All Statuses"
                    :options="[
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'suspended' => 'Suspended',
                        'expired' => 'Expired',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]"
                />
            </div>

            @if ($search !== '' || $status !== '')
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

        {{-- Courses Grid --}}
        @if ($enrollments->isEmpty())
            <x-card>
                <x-empty-state
                    title="No matching courses"
                    message="No enrolled courses match your current search or filter criteria."
                >
                    <x-slot:action>
                        <x-button variant="secondary" size="sm" wire:click="resetFilters">
                            Reset Filters
                        </x-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($enrollments as $enrollment)
                    @php
                        $course = $enrollment->course;
                        $status = strtolower($enrollment->status ?? 'pending');
                        $badgeColor = match ($status) {
                            'active' => 'green',
                            'completed' => 'blue',
                            'suspended', 'pending' => 'yellow',
                            'cancelled' => 'red',
                            default => 'gray',
                        };
                        $moduleCount = $course ? $course->modules->count() : 0;
                        $lessonCount = $course ? $course->modules->flatMap->lessons->count() : 0;
                    @endphp

                    <x-card wire:key="enrollment-card-{{ $enrollment->id }}" class="flex flex-col justify-between">
                        <div class="space-y-3">
                            @if ($course && method_exists($course, 'thumbnailUrl') && $course->thumbnailUrl())
                                <img
                                    src="{{ $course->thumbnailUrl() }}"
                                    alt="{{ $course->title }}"
                                    class="w-full h-44 object-cover rounded-lg border border-border-default"
                                />
                            @else
                                <div class="w-full h-44 rounded-lg bg-border-muted flex items-center justify-center text-sm font-semibold text-text-muted">
                                    {{ $course?->category?->name ?? 'SEO Academy' }}
                                </div>
                            @endif

                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">
                                    {{ $course?->category?->name ?? 'Course' }}
                                </span>
                                <x-badge :color="$badgeColor">
                                    {{ ucfirst($status) }}
                                </x-badge>
                            </div>

                            <h3 class="text-lg font-semibold text-text-primary line-clamp-2">
                                @if ($status === 'active' && $course)
                                    <a href="{{ route('courses.public.show', $course->slug) }}" class="hover:text-primary-600 transition-colors">
                                        {{ $course->title }}
                                    </a>
                                @else
                                    <span>{{ $course?->title ?? 'Course Unavailable' }}</span>
                                @endif
                            </h3>

                            @if ($course?->description)
                                <p class="text-sm text-text-secondary line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit($course->description, 110) }}
                                </p>
                            @endif

                            <div class="pt-2 text-xs text-text-muted space-y-1">
                                <div class="flex items-center justify-between">
                                    <span>Curriculum:</span>
                                    <span class="font-medium text-text-secondary">{{ $moduleCount }} Modules &bull; {{ $lessonCount }} Lessons</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Validity:</span>
                                    <span class="font-medium text-text-secondary">
                                        @if ($enrollment->starts_at && $enrollment->expires_at)
                                            {{ $enrollment->starts_at->format('M d, Y') }} &ndash; {{ $enrollment->expires_at->format('M d, Y') }}
                                        @elseif ($enrollment->expires_at)
                                            Expires {{ $enrollment->expires_at->format('M d, Y') }}
                                        @elseif ($enrollment->starts_at)
                                            Started {{ $enrollment->starts_at->format('M d, Y') }}
                                        @elseif ($enrollment->enrolled_at)
                                            Enrolled {{ $enrollment->enrolled_at->format('M d, Y') }}
                                        @else
                                            Active Access
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-border-default">
                            @if ($status === 'active' && $course)
                                <a
                                    href="{{ route('student.courses.learn', $course) }}"
                                    class="btn btn-primary btn-sm w-full text-center"
                                >
                                    Resume Course
                                </a>
                            @elseif ($status === 'completed' && $course)
                                <div class="space-y-2">
                                    <a
                                        href="{{ route('student.courses.learn', $course) }}"
                                        class="btn btn-secondary btn-sm w-full text-center"
                                    >
                                        Review Course
                                    </a>
                                    @php
                                        $cert = \App\Domain\Certificates\Models\Certificate::where('enrollment_id', $enrollment->id)->orWhere(function($q) use ($enrollment) {
                                            $q->where('user_id', $enrollment->studentProfile?->user_id)->where('course_id', $enrollment->course_id);
                                        })->where('status', 'Issued')->first();
                                    @endphp
                                    @if ($cert)
                                        <div class="flex gap-2">
                                            <a href="{{ route('certificates.download', $cert) }}" class="btn btn-primary btn-xs flex-1 text-center">
                                                Download Certificate
                                            </a>
                                            <a href="{{ route('certificates.verify', $cert->verification_code ?: $cert->certificate_number) }}" target="_blank" class="btn btn-secondary btn-xs flex-1 text-center">
                                                Verify
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($status === 'suspended')
                                <div class="w-full text-center py-2 px-3 rounded-md text-xs font-medium bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                    Access Suspended
                                </div>
                            @elseif ($status === 'expired')
                                <div class="w-full text-center py-2 px-3 rounded-md text-xs font-medium bg-slate-500/10 text-slate-400 border border-slate-500/20">
                                    Enrollment Expired
                                </div>
                            @elseif ($status === 'cancelled')
                                <div class="w-full text-center py-2 px-3 rounded-md text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                    Enrollment Cancelled
                                </div>
                            @else
                                <div class="w-full text-center py-2 px-3 rounded-md text-xs font-medium bg-yellow-500/10 text-yellow-500 border border-yellow-500/20">
                                    Enrollment Pending
                                </div>
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>

            @if ($enrollments->hasPages())
                <div class="pt-2">
                    {{ $enrollments->links() }}
                </div>
            @endif
        @endif
    @endif
</div>
