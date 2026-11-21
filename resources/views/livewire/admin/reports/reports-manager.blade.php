<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                <x-icon name="file-question" class="w-7 h-7 text-primary-600" />
                Reports & Analytics Exports
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Generate, filter, and securely export professional CSV datasets and PDF documents.
            </p>
        </div>

        <div class="flex items-center gap-3">
            {{-- CSV Export --}}
            <a
                href="{{ route('admin.reports.export.csv') }}?{{ $exportQuery }}"
                target="_blank"
                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition-colors flex items-center gap-1.5 shadow-xs"
            >
                <x-icon name="clipboard-check" class="w-4 h-4 text-emerald-600" />
                Download CSV
            </a>

            {{-- PDF Export --}}
            <a
                href="{{ route('admin.reports.export.pdf') }}?{{ $exportQuery }}"
                target="_blank"
                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow-xs transition-colors flex items-center gap-1.5"
            >
                <x-icon name="award" class="w-4 h-4" />
                Download PDF
            </a>
        </div>
    </div>

    {{-- Report Type Selection Tabs --}}
    <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        @php
            $reportTabs = [
                \App\Domain\Reports\Services\ReportingService::REPORT_COURSE_PERFORMANCE => 'Course Performance',
                \App\Domain\Reports\Services\ReportingService::REPORT_STUDENT_PROGRESS => 'Student Progress',
                \App\Domain\Reports\Services\ReportingService::REPORT_ENROLLMENTS => 'Enrollment History',
                \App\Domain\Reports\Services\ReportingService::REPORT_TRAINER_PERFORMANCE => 'Trainer Performance',
                \App\Domain\Reports\Services\ReportingService::REPORT_ASSESSMENT_ASSIGNMENT => 'Assessments & Assignments',
            ];
        @endphp

        @foreach($reportTabs as $typeKey => $typeLabel)
            <button
                type="button"
                wire:click="$set('reportType', '{{ $typeKey }}')"
                class="px-4 py-2 text-xs font-semibold rounded-lg transition-colors cursor-pointer {{ $reportType === $typeKey ? 'bg-primary-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700' }}"
            >
                {{ $typeLabel }}
            </button>
        @endforeach
    </div>

    {{-- Filter Panel --}}
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            {{-- Course Filter --}}
            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-400 mb-1">Course</label>
                <select
                    wire:model.live="courseId"
                    class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-1.5 px-2.5"
                >
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}">{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Filter --}}
            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-400 mb-1">Status</label>
                <select
                    wire:model.live="status"
                    class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-1.5 px-2.5"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            {{-- Start Date --}}
            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-400 mb-1">Start Date</label>
                <input
                    type="date"
                    wire:model.live="startDate"
                    class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-1.5 px-2.5"
                />
            </div>

            {{-- End Date --}}
            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-400 mb-1">End Date</label>
                <input
                    type="date"
                    wire:model.live="endDate"
                    class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-1.5 px-2.5"
                />
            </div>

            {{-- Reset Button --}}
            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="w-full px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Reset Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Live Report Preview Table --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                {{ $reportData['title'] }} Preview
                <span class="text-xs font-normal text-slate-500">({{ $totalRows }} total records)</span>
            </h2>
            <div class="text-xs text-slate-400">
                Generated {{ $reportData['generated_at'] }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs text-left divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold">
                    <tr>
                        @foreach($reportData['headers'] as $header)
                            <th class="px-4 py-3">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @forelse($paginatedRows as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            @foreach($row as $cell)
                                <td class="px-4 py-2.5 whitespace-nowrap">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($reportData['headers']) }}" class="px-4 py-8 text-center text-slate-400">
                                No records found matching the specified report criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($totalPages > 1)
            <div class="p-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-500">
                    Showing page {{ $currentPage }} of {{ $totalPages }}
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousPage"
                        @disabled($currentPage <= 1)
                        class="px-2.5 py-1 rounded-md border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40"
                    >
                        &larr; Previous
                    </button>
                    <button
                        type="button"
                        wire:click="nextPage"
                        @disabled($currentPage >= $totalPages)
                        class="px-2.5 py-1 rounded-md border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40"
                    >
                        Next &rarr;
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
