<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ auth()->user()->hasRole('admin') ? route('admin.analytics.index') : route('trainer.analytics.index') }}" class="hover:underline flex items-center gap-1">
                    &larr; Back to Analytics
                </a>
                <span>&bull;</span>
                <span>Course Deep-Dive</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                <x-icon name="book-open" class="w-6 h-6 text-primary-600" />
                {{ $course->title }}
            </h1>
            <div class="flex items-center gap-2 mt-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300">
                    {{ ucfirst((string) $course->status) }}
                </span>
                <span class="text-xs text-slate-400">
                    {{ $metrics['total_lessons'] }} total lessons in curriculum
                </span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="toggleAccessibleTables"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition-colors cursor-pointer"
            >
                {{ $showAccessibleTables ? 'Hide Detailed Tables' : 'Show Detailed Tables' }}
            </button>
            <a
                href="{{ route('admin.reports.index', ['course_id' => $course->id]) }}"
                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow-xs transition-colors"
            >
                Export Course Report
            </a>
        </div>
    </div>

    {{-- Overview KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Enrolled Students</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $metrics['total_enrolled'] }}</div>
            <div class="text-xs text-emerald-600 mt-1 font-medium">{{ $metrics['active_enrollments'] }} active &bull; {{ $metrics['completed_enrollments'] }} completed</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Completion Rate</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $metrics['completion_rate'] }}%</div>
            <div class="mt-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ min(100, $metrics['completion_rate']) }}%"></div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Average Progress</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $metrics['average_progress'] }}%</div>
            <div class="mt-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-primary-500 h-1.5 rounded-full" style="width: {{ min(100, $metrics['average_progress']) }}%"></div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Certificates Awarded</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $metrics['certificates_issued'] }}</div>
            <div class="text-xs text-amber-600 mt-1 font-medium">Fully accredited completions</div>
        </div>
    </div>

    {{-- Module Breakdown --}}
    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Curriculum Module Progress</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Progression velocity and completion rates by module.</p>

        <div class="space-y-4">
            @foreach($metrics['modules_breakdown'] as $mod)
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <span class="text-xs font-bold text-slate-400 uppercase">Module {{ $mod['display_order'] }}</span>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $mod['title'] }}</h3>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $mod['average_completion_rate'] }}%</span>
                            <div class="text-[11px] text-slate-500">{{ $mod['total_completions'] }} lesson completions across {{ $mod['total_lessons'] }} lessons</div>
                        </div>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                        <div class="bg-primary-600 h-2 rounded-full" style="width: {{ min(100, $mod['average_completion_rate']) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Actionable Drop-off & Diagnostics --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- High Abandonment Lessons --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center gap-2 mb-3">
                <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-900/40 text-rose-600">
                    <x-icon name="activity" class="w-4 h-4" />
                </span>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Highest Abandonment Lessons</h3>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                Lessons where students start watching or working but frequently stop without completing.
            </p>

            <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                @forelse($metrics['highest_abandonment_lessons'] as $hl)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $hl['title'] }}</div>
                            <span class="text-[11px] text-slate-400">{{ $hl['module_title'] }} &bull; {{ $hl['completed_count'] }} completed</span>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300">
                                {{ $hl['in_progress_count'] }} stalled
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-400 text-xs">No drop-off anomalies detected.</div>
                @endforelse
            </div>
        </div>

        {{-- Assessment Questions Diagnostic --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center gap-2 mb-3">
                <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-600">
                    <x-icon name="file-question" class="w-4 h-4" />
                </span>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Questions with High Error Rates</h3>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                Assessment items where students fail &ge; 40% of the time, suggesting difficult or ambiguous material.
            </p>

            <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                @forelse($metrics['poor_performing_questions'] as $q)
                    <div class="py-2.5 flex items-center justify-between">
                        <div class="max-w-xs pr-2">
                            <div class="font-medium text-slate-900 dark:text-white truncate">{{ $q['question_text'] }}</div>
                            <span class="text-[11px] text-slate-400">{{ $q['incorrect_count'] }} errors out of {{ $q['total_answers'] }} attempts</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">
                            {{ $q['failure_rate'] }}% failure
                        </span>
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-400 text-xs">All assessment questions show healthy pass rates.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Stalled Students List --}}
    @if($metrics['stalled_students_count'] > 0)
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Stalled Enrolled Students (No Activity in &ge; 14 Days)
                </h3>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
                    {{ $metrics['stalled_students_count'] }} students
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-left divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-800 text-slate-600 font-semibold">
                        <tr>
                            <th class="px-3 py-2">Student Name</th>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Days Inactive</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($metrics['stalled_students'] as $st)
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-900 dark:text-white">{{ $st['student_name'] }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $st['student_code'] }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $st['student_email'] }}</td>
                                <td class="px-3 py-2 text-amber-600 font-semibold">{{ $st['days_inactive'] }} days</td>
                                <td class="px-3 py-2 text-right">
                                    <span class="text-slate-400 text-xs">Flagged for follow-up</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
