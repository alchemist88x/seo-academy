<div class="space-y-6">
    {{-- Header & Filters --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                <x-icon name="activity" class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                Learning Analytics & Insights
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Real database-backed intelligence on student engagement, course outcomes, and academic health.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Time Range Presets --}}
            <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-slate-800">
                <button
                    type="button"
                    wire:click="$set('timeRange', 7)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $timeRange == 7 ? 'bg-white dark:bg-slate-700 text-primary-600 dark:text-primary-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
                >
                    7 Days
                </button>
                <button
                    type="button"
                    wire:click="$set('timeRange', 30)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $timeRange == 30 ? 'bg-white dark:bg-slate-700 text-primary-600 dark:text-primary-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
                >
                    30 Days
                </button>
                <button
                    type="button"
                    wire:click="$set('timeRange', 90)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $timeRange == 90 ? 'bg-white dark:bg-slate-700 text-primary-600 dark:text-primary-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
                >
                    90 Days
                </button>
                <button
                    type="button"
                    wire:click="$set('timeRange', 365)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $timeRange == 365 ? 'bg-white dark:bg-slate-700 text-primary-600 dark:text-primary-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
                >
                    1 Year
                </button>
            </div>

            {{-- Course Filter Dropdown --}}
            <select
                wire:model.live="selectedCourseId"
                class="text-xs font-medium rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-1.5 px-3 focus:ring-primary-500 focus:border-primary-500"
            >
                <option value="">All Courses</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->title }}</option>
                @endforeach
            </select>

            {{-- Accessible Table Toggle --}}
            <button
                type="button"
                wire:click="toggleAccessibleTables"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750 transition-colors flex items-center gap-1.5 cursor-pointer"
                title="Toggle tabular representations for all charts"
                aria-pressed="{{ $showAccessibleTables ? 'true' : 'false' }}"
            >
                <x-icon name="clipboard-check" class="w-4 h-4 text-slate-500" />
                {{ $showAccessibleTables ? 'Hide Data Tables' : 'Show Data Tables' }}
            </button>

            {{-- Reports Link --}}
            <a
                href="{{ route('admin.reports.index') }}"
                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow-xs transition-colors flex items-center gap-1.5"
            >
                <x-icon name="file-question" class="w-4 h-4" />
                Reports & Exports
            </a>
        </div>
    </div>

    {{-- Top Overview KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Students --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Students</span>
                <span class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400">
                    <x-icon name="users" class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ number_format($overview['total_students']) }}</span>
                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $overview['active_students'] }} active</span>
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex justify-between">
                <span>Suspended: {{ $overview['suspended_students'] }}</span>
                <span>Trainers: {{ $overview['total_trainers'] }}</span>
            </div>
        </div>

        {{-- Card 2: Enrollments --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Enrollments</span>
                <span class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400">
                    <x-icon name="book-open" class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ number_format($overview['total_enrollments']) }}</span>
                <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">{{ $overview['active_enrollments'] }} currently active</span>
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex justify-between">
                <span>Completed: {{ $overview['completed_enrollments'] }}</span>
                <span>Avg Progress: {{ $avgProgress }}%</span>
            </div>
        </div>

        {{-- Card 3: Completion Rate --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Completion Rate</span>
                <span class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <x-icon name="award" class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $overview['course_completion_rate'] }}%</span>
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Overall academic pass</span>
            </div>
            <div class="mt-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ min(100, $overview['course_completion_rate']) }}%"></div>
            </div>
        </div>

        {{-- Card 4: Certificates Issued --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Certificates Issued</span>
                <span class="p-2 rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                    <x-icon name="shield-check" class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ number_format($overview['certificates_issued']) }}</span>
                <span class="text-xs font-medium text-amber-600 dark:text-amber-400">Verified credentials</span>
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex justify-between">
                <span>Published Courses: {{ $overview['published_courses'] }}</span>
                <span>Total: {{ $overview['total_courses'] }}</span>
            </div>
        </div>
    </div>

    {{-- Interactive SVG Charts Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Chart 1: Enrollment Trends --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Enrollment Trends</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">New enrollments over the selected {{ $timeRange }} days.</p>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">
                    {{ array_sum(array_column($enrollmentTrends, 'count')) }} total
                </span>
            </div>

            {{-- SVG Bar Chart --}}
            @php
                $maxEnrollment = max(1, max(array_column($enrollmentTrends, 'count') ?: [1]));
                $barCount = count($enrollmentTrends);
            @endphp
            <div class="h-44 w-full flex items-end gap-1 pt-4 pb-2 border-b border-slate-100 dark:border-slate-800" role="img" aria-label="Enrollment trends chart over {{ $timeRange }} days">
                @foreach($enrollmentTrends as $point)
                    @php
                        $heightPercent = ($point['count'] / $maxEnrollment) * 100;
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                        <div
                            class="w-full bg-primary-500 dark:bg-primary-600 hover:bg-primary-600 dark:hover:bg-primary-500 rounded-t-xs transition-all duration-200"
                            style="height: {{ max(4, $heightPercent) }}%;"
                            title="{{ $point['label'] }}: {{ $point['count'] }} enrollments"
                        ></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                <span>{{ $enrollmentTrends[0]['label'] ?? '' }}</span>
                <span>{{ $enrollmentTrends[intdiv($barCount, 2)]['label'] ?? '' }}</span>
                <span>{{ end($enrollmentTrends)['label'] ?? '' }}</span>
            </div>

            {{-- Accessible Table Representation --}}
            @if($showAccessibleTables)
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800 overflow-x-auto max-h-48">
                    <table class="min-w-full text-xs text-left text-slate-600 dark:text-slate-300" aria-label="Enrollment trends tabular data">
                        <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-400 sticky top-0">
                            <tr>
                                <th class="px-3 py-1.5">Date</th>
                                <th class="px-3 py-1.5 text-right">New Enrollments</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($enrollmentTrends as $pt)
                                <tr>
                                    <td class="px-3 py-1">{{ $pt['date'] }} ({{ $pt['label'] }})</td>
                                    <td class="px-3 py-1 text-right font-medium">{{ $pt['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Chart 2: Daily Active Students --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Active Learning Activity</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Daily active students engaged in course lessons.</p>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                    Study Heartbeats
                </span>
            </div>

            @php
                $maxActive = max(1, max(array_column($dailyActiveStudents, 'count') ?: [1]));
                $activeCount = count($dailyActiveStudents);
            @endphp
            <div class="h-44 w-full flex items-end gap-1 pt-4 pb-2 border-b border-slate-100 dark:border-slate-800" role="img" aria-label="Daily active students chart">
                @foreach($dailyActiveStudents as $point)
                    @php
                        $heightPercent = ($point['count'] / $maxActive) * 100;
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                        <div
                            class="w-full bg-emerald-500 dark:bg-emerald-600 hover:bg-emerald-600 dark:hover:bg-emerald-500 rounded-t-xs transition-all duration-200"
                            style="height: {{ max(4, $heightPercent) }}%;"
                            title="{{ $point['label'] }}: {{ $point['count'] }} active students"
                        ></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                <span>{{ $dailyActiveStudents[0]['label'] ?? '' }}</span>
                <span>{{ $dailyActiveStudents[intdiv($activeCount, 2)]['label'] ?? '' }}</span>
                <span>{{ end($dailyActiveStudents)['label'] ?? '' }}</span>
            </div>

            {{-- Accessible Table Representation --}}
            @if($showAccessibleTables)
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800 overflow-x-auto max-h-48">
                    <table class="min-w-full text-xs text-left text-slate-600 dark:text-slate-300" aria-label="Daily active students tabular data">
                        <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-400 sticky top-0">
                            <tr>
                                <th class="px-3 py-1.5">Date</th>
                                <th class="px-3 py-1.5 text-right">Active Students</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($dailyActiveStudents as $pt)
                                <tr>
                                    <td class="px-3 py-1">{{ $pt['date'] }} ({{ $pt['label'] }})</td>
                                    <td class="px-3 py-1 text-right font-medium">{{ $pt['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Academic Performance & Enrollment Status Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Status Breakdown --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Enrollment Status</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Distribution across all enrollment states.</p>

            @php
                $totalStatus = max(1, array_sum($statusDistribution));
            @endphp
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-xs font-medium mb-1">
                        <span class="text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Active
                        </span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $statusDistribution['active'] }} ({{ round(($statusDistribution['active'] / $totalStatus) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ ($statusDistribution['active'] / $totalStatus) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium mb-1">
                        <span class="text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Completed
                        </span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $statusDistribution['completed'] }} ({{ round(($statusDistribution['completed'] / $totalStatus) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ ($statusDistribution['completed'] / $totalStatus) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium mb-1">
                        <span class="text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Suspended
                        </span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $statusDistribution['suspended'] }} ({{ round(($statusDistribution['suspended'] / $totalStatus) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div class="bg-amber-500 h-2 rounded-full" style="width: {{ ($statusDistribution['suspended'] / $totalStatus) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium mb-1">
                        <span class="text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Cancelled / Expired
                        </span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $statusDistribution['cancelled'] + $statusDistribution['expired'] }}</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div class="bg-rose-500 h-2 rounded-full" style="width: {{ (($statusDistribution['cancelled'] + $statusDistribution['expired']) / $totalStatus) * 100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Academic Outcomes --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs lg:col-span-2">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Academic Performance Overview</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Assessment results, grades, and assignment completion.</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Avg Assessment Score</span>
                    <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $academicMetrics['average_assessment_score'] }}%</div>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">{{ $academicMetrics['assessment_pass_rate'] }}% pass rate</span>
                </div>

                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Assessment Attempts</span>
                    <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($academicMetrics['total_assessment_attempts']) }}</div>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $academicMetrics['passed_assessment_attempts'] }} passed</span>
                </div>

                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Avg Published Grade</span>
                    <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $academicMetrics['average_grade_score'] }}</div>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $academicMetrics['total_published_grades'] }} official grades</span>
                </div>

                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Assignments</span>
                    <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $academicMetrics['graded_assignment_submissions'] }} / {{ $academicMetrics['total_assignment_submissions'] }}</div>
                    <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">{{ $academicMetrics['pending_assignment_evaluations'] }} pending review</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Course Engagement Ranking --}}
    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Course Engagement & Outcomes</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Detailed breakdown of active students, completion rates, and average progress per course.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs text-left divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-400 font-semibold">
                    <tr>
                        <th class="px-4 py-2.5">Course Name</th>
                        <th class="px-4 py-2.5 text-center">Active Enrolled</th>
                        <th class="px-4 py-2.5 text-center">Completed</th>
                        <th class="px-4 py-2.5 text-center">Completion Rate</th>
                        <th class="px-4 py-2.5 text-center">Avg Progress</th>
                        <th class="px-4 py-2.5 text-center">Active (30d)</th>
                        <th class="px-4 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @forelse($courseRankings as $cr)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('admin.analytics.courses.show', $cr['course_id']) }}" class="hover:text-primary-600 transition-colors">
                                    {{ $cr['title'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">{{ $cr['active_enrollments'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $cr['completed_enrollments'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold {{ $cr['completion_rate'] >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ $cr['completion_rate'] }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-primary-500 h-1.5 rounded-full" style="width: {{ $cr['average_progress'] }}%"></div>
                                    </div>
                                    <span class="text-[11px]">{{ $cr['average_progress'] }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-medium">{{ $cr['active_students_count'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.analytics.courses.show', $cr['course_id']) }}"
                                    class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
                                >
                                    Course Analytics &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">No course data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actionable Operational Insights --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Inactive Students Card --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-600">
                        <x-icon name="activity" class="w-4 h-4" />
                    </span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Inactive Students (&ge; 14 Days)</h3>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300">
                    {{ $insights['inactive_students_count'] }} flagged
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                Students with active enrollments who have ceased studying or logging heartbeat activity.
            </p>

            <div class="space-y-2">
                @forelse($insights['inactive_students'] as $stu)
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 text-xs">
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $stu['student_name'] }}</span>
                            <span class="text-slate-400">({{ $stu['student_code'] }}) &bull; {{ $stu['course_title'] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-amber-600 dark:text-amber-400 font-medium">{{ $stu['days_inactive'] }}d inactive</span>
                            @if(!empty($stu['student_profile_id']))
                                <a
                                    href="{{ route('admin.analytics.students.show', $stu['student_profile_id']) }}"
                                    class="text-primary-600 hover:underline font-semibold"
                                >
                                    Inspect &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 text-center py-4">No inactive students detected. Excellent engagement!</div>
                @endforelse
            </div>
        </div>

        {{-- Pending Evaluations & Deadlines --}}
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-900/40 text-rose-600">
                        <x-icon name="clipboard-check" class="w-4 h-4" />
                    </span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pending Evaluations (&gt; 48 Hours)</h3>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300">
                    {{ $insights['pending_evaluations_count'] }} awaiting
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                Student assignment submissions awaiting grading beyond standard evaluation window.
            </p>

            <div class="space-y-2">
                @forelse($insights['pending_evaluations'] as $pe)
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 text-xs">
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $pe['assignment_title'] }}</span>
                            <span class="text-slate-400">by {{ $pe['student_name'] }} ({{ $pe['course_title'] }})</span>
                        </div>
                        <span class="text-slate-500">Submitted {{ \Carbon\Carbon::parse($pe['submitted_at'])->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 text-center py-4">No assignment evaluations pending beyond 48 hours.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
