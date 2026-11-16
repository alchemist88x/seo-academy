<div class="space-y-6">
    <x-page-header
        title="Welcome back, {{ $userName }}"
        subtitle="Track your SEO learning progress, daily streaks, and upcoming lessons."
    >
        @if ($activeEnrollments->isNotEmpty())
            <x-slot:actions>
                <a href="{{ route('student.courses.index') }}" class="btn btn-primary btn-md">
                    Continue Learning
                </a>
            </x-slot:actions>
        @endif
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

    <x-card title="My Courses" description="Pick up right where you left off.">
        @if (! $hasAnyEnrollments)
            <x-empty-state
                title="You are not currently enrolled in any courses"
                message="You are not currently enrolled in any courses. Please contact your academy administrator to get enrolled."
            />
        @elseif ($activeEnrollments->isEmpty())
            <x-empty-state
                title="No active enrollments"
                message="You have no courses in active status right now. Please check your course list or contact your administrator."
            >
                <x-slot:action>
                    <a href="{{ route('student.courses.index') }}" class="btn btn-secondary btn-sm">
                        View Enrolled Courses
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($activeEnrollments as $enrollment)
                    <div class="p-4 rounded-lg border border-border-default bg-surface-primary flex flex-col justify-between space-y-3">
                        <div>
                            <span class="text-xs font-semibold text-primary-600 uppercase tracking-wider">
                                {{ $enrollment->course?->category?->name ?? 'Course' }}
                            </span>
                            <h4 class="font-semibold text-text-primary text-base mt-1 line-clamp-1">
                                {{ $enrollment->course?->title }}
                            </h4>
                            <p class="text-xs text-text-muted mt-1">
                                {{ $enrollment->course?->modules?->count() ?? 0 }} Modules &bull; {{ $enrollment->course?->modules?->flatMap->lessons?->count() ?? 0 }} Lessons
                            </p>
                        </div>
                        <div class="pt-3 border-t border-border-default flex items-center justify-between">
                            <span class="text-xs text-text-muted">
                                @if ($enrollment->expires_at)
                                    Expires {{ $enrollment->expires_at->format('M d, Y') }}
                                @else
                                    Lifetime Access
                                @endif
                            </span>
                            @if ($enrollment->course)
                                <a href="{{ route('student.courses.learn', $enrollment->course) }}" class="btn btn-primary btn-xs">
                                    Resume
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
