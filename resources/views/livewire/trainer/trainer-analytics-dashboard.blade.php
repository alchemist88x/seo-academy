<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                <x-icon name="activity" class="w-7 h-7 text-primary-600" />
                Teaching Performance & Analytics
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Student progress, evaluation workload, and course health across your assigned courses.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('trainer.reports.index') }}"
                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow-xs transition-colors"
            >
                Generate Teaching Reports
            </a>
        </div>
    </div>

    {{-- Overview KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Assigned Courses</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $analytics['total_assigned_courses'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Active instructional curriculum</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Students</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $analytics['total_distinct_students'] }}</div>
            <div class="text-xs text-emerald-600 mt-1 font-medium">{{ $analytics['active_enrollments'] }} currently active</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Completion Rate</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $analytics['overall_completion_rate'] }}%</div>
            <div class="text-xs text-blue-600 mt-1 font-medium">{{ $analytics['completed_enrollments'] }} student graduations</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Evaluation Workload</span>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $analytics['evaluation_workload']['pending_evaluations'] }}</div>
            <div class="text-xs text-amber-600 mt-1 font-medium">Pending student submissions</div>
        </div>
    </div>

    {{-- Course Performance Table --}}
    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Assigned Course Outcomes</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Enrollment distribution and completion metrics for each assigned course.</p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs text-left divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-800 text-slate-600 font-semibold">
                    <tr>
                        <th class="px-4 py-2.5">Course Name</th>
                        <th class="px-4 py-2.5 text-center">Status</th>
                        <th class="px-4 py-2.5 text-center">Total Enrolled</th>
                        <th class="px-4 py-2.5 text-center">Active</th>
                        <th class="px-4 py-2.5 text-center">Completed</th>
                        <th class="px-4 py-2.5 text-center">Completion Rate</th>
                        <th class="px-4 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($analytics['courses'] as $c)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('trainer.analytics.courses.show', $c['course_id']) }}" class="hover:text-primary-600">
                                    {{ $c['title'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary-100 text-primary-700">
                                    {{ ucfirst((string) $c['status']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">{{ $c['total_enrolled'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $c['active_enrollments'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $c['completed_enrollments'] }}</td>
                            <td class="px-4 py-3 text-center font-bold text-emerald-600">{{ $c['completion_rate'] }}%</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('trainer.analytics.courses.show', $c['course_id']) }}"
                                    class="text-xs font-semibold text-primary-600 hover:underline"
                                >
                                    Deep Dive &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">No courses currently assigned to your account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Workload & Recent Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Workload Summary --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Grading Workload & Turnaround</h3>
            <div class="grid grid-cols-2 gap-4 mt-3">
                <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-medium text-slate-400 uppercase">Submissions Graded</span>
                    <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $analytics['evaluation_workload']['graded_evaluations'] }}</div>
                </div>
                <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-medium text-slate-400 uppercase">Avg Turnaround Time</span>
                    <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $analytics['evaluation_workload']['average_turnaround_hours'] }}h</div>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Assessment Pass Rate across your courses:</span>
                    <strong class="text-emerald-600">{{ $analytics['assessment_summary']['pass_rate'] }}%</strong>
                </div>
            </div>
        </div>

        {{-- Recent Activity Feed --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Recent Student Learning Activity</h3>
            <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                @forelse($analytics['recent_student_activity'] as $act)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $act['student_name'] }}</span>
                            <div class="text-[11px] text-slate-400">{{ $act['lesson_title'] }} &bull; {{ $act['course_title'] }}</div>
                        </div>
                        <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($act['last_accessed_at'])->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-400 text-xs">No recent activity detected.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
