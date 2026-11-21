<div class="space-y-6">
    {{-- Profile Header Banner --}}
    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-xl">
                    {{ strtoupper(substr($data['student']['name'], 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $data['student']['name'] }}</h1>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $data['student']['active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $data['student']['active'] ? 'Active' : 'Suspended' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                        <span>Code: <strong class="text-slate-700 dark:text-slate-300">{{ $data['student']['student_code'] }}</strong></span>
                        <span>&bull;</span>
                        <span>Email: {{ $data['student']['email'] }}</span>
                        @if(!empty($data['student']['batch']))
                            <span>&bull;</span>
                            <span>Batch: {{ $data['student']['batch'] }}</span>
                        @endif
                        @if(!empty($data['student']['level']))
                            <span>&bull;</span>
                            <span>Level: {{ $data['student']['level'] }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if(! $isSelf && auth()->user()->hasRole('admin'))
                <a
                    href="{{ route('admin.students.index', ['search' => $data['student']['student_code']]) }}"
                    class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition-colors"
                >
                    Manage Student Account &rarr;
                </a>
            @endif
        </div>

        {{-- Mini KPI Counters --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-xs font-medium text-slate-500">Enrolled Courses</span>
                <div class="text-xl font-bold text-slate-900 dark:text-white mt-0.5">{{ $data['summary']['total_enrolled_courses'] }}</div>
            </div>
            <div>
                <span class="text-xs font-medium text-slate-500">Active Courses</span>
                <div class="text-xl font-bold text-emerald-600 mt-0.5">{{ $data['summary']['active_courses'] }}</div>
            </div>
            <div>
                <span class="text-xs font-medium text-slate-500">Completed Courses</span>
                <div class="text-xl font-bold text-blue-600 mt-0.5">{{ $data['summary']['completed_courses'] }}</div>
            </div>
            <div>
                <span class="text-xs font-medium text-slate-500">Certificates Earned</span>
                <div class="text-xl font-bold text-amber-600 mt-0.5">{{ $data['summary']['certificates_count'] }}</div>
            </div>
        </div>
    </div>

    {{-- Course Progress Cards --}}
    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Course Progress & Grades</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Current enrollment lifecycle, lesson completion, and official grade statuses.</p>

        <div class="space-y-3">
            @forelse($data['courses'] as $course)
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $course['course_title'] }}</h3>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $course['status'] === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ ucfirst($course['status']) }}
                                </span>
                            </div>
                            <span class="text-xs text-slate-500">
                                Enrolled: {{ $course['enrolled_at'] }} &bull; Completed {{ $course['completed_lessons'] }} of {{ $course['total_lessons'] }} lessons
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-base font-bold text-slate-900 dark:text-white">{{ $course['progress_percent'] }}%</span>
                            @if(!empty($course['grade']))
                                <div class="text-xs font-semibold text-emerald-600">
                                    Grade: {{ $course['grade']['final_score'] }} ({{ $course['grade']['letter_grade'] }})
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                        <div class="bg-primary-600 h-2 rounded-full" style="width: {{ $course['progress_percent'] }}%"></div>
                    </div>
                </div>
            @empty
                <div class="text-xs text-slate-400 py-4 text-center">No enrollments recorded for this student.</div>
            @endforelse
        </div>
    </div>

    {{-- Activity Timeline & Assessment History --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Activity Timeline --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Learning Activity Timeline</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Chronological study events, video heartbeats, and submissions.</p>

            <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                @forelse($data['activity_timeline'] as $event)
                    <div class="relative text-xs">
                        <span class="absolute -left-6 top-0.5 w-2 h-2 rounded-full {{ $event['type'] === 'lesson_completed' ? 'bg-emerald-500' : 'bg-primary-500' }}"></span>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $event['title'] }}</div>
                        <div class="text-slate-500 dark:text-slate-400">{{ $event['description'] }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($event['timestamp'])->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 py-4 text-center">No recent activity recorded.</div>
                @endforelse
            </div>
        </div>

        {{-- Assessments & Assignments Tables --}}
        <div class="space-y-6">
            {{-- Assessments --}}
            <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Assessment Results</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left divide-y divide-slate-100 dark:divide-slate-800">
                        <thead class="text-[10px] uppercase text-slate-400">
                            <tr>
                                <th class="py-1.5">Course</th>
                                <th class="py-1.5 text-center">Score</th>
                                <th class="py-1.5 text-right">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($data['assessment_attempts'] as $att)
                                <tr>
                                    <td class="py-2 text-slate-800 dark:text-slate-200 font-medium">{{ $att['course_title'] }}</td>
                                    <td class="py-2 text-center font-bold text-slate-900 dark:text-white">{{ $att['score_percentage'] }}%</td>
                                    <td class="py-2 text-right">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $att['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $att['passed'] ? 'Passed' : 'Failed' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 text-center text-slate-400">No assessment attempts recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Assignments --}}
            <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Assignment Submissions</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left divide-y divide-slate-100 dark:divide-slate-800">
                        <thead class="text-[10px] uppercase text-slate-400">
                            <tr>
                                <th class="py-1.5">Assignment</th>
                                <th class="py-1.5 text-center">Grade</th>
                                <th class="py-1.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($data['assignment_submissions'] as $sub)
                                <tr>
                                    <td class="py-2 text-slate-800 dark:text-slate-200 font-medium">
                                        {{ $sub['assignment_title'] }}
                                        @if($sub['is_late'])
                                            <span class="text-[10px] text-amber-500 font-bold ml-1">(Late)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-center font-bold text-slate-900 dark:text-white">
                                        {{ $sub['grade'] !== null ? $sub['grade'] : '—' }}
                                    </td>
                                    <td class="py-2 text-right text-slate-500">
                                        {{ ucfirst(str_replace('_', ' ', (string) $sub['status'])) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 text-center text-slate-400">No assignment submissions recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
