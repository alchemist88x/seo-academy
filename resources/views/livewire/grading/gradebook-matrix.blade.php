<div class="p-6 max-w-7xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ auth()->user()->hasRole('admin') ? route('admin.courses.index') : route('trainer.courses.index') }}"
                   class="text-sm font-medium text-indigo-400 hover:text-indigo-300 transition-colors">
                    &larr; Back to Courses
                </a>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">Course Gradebook</h1>
            <p class="text-sm text-slate-300 mt-1">
                {{ $course->title }} &bull; Passing Grade: {{ $scheme ? $scheme->passing_grade . '%' : 'Not configured' }}
            </p>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-lg bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm font-medium flex items-center justify-between" role="alert">
            <span>{{ $feedbackMessage }}</span>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-lg bg-rose-950/80 border border-rose-500/50 text-rose-200 text-sm font-medium flex items-center justify-between" role="alert">
            <span>{{ $errorMessage }}</span>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-white">&times;</button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between bg-slate-900 border border-slate-800 p-4 rounded-xl">
        <div class="w-full sm:w-72">
            <label for="gradebook-search" class="sr-only">Search Students</label>
            <input
                id="gradebook-search"
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search students by name or email..."
                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
            >
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <label for="status-filter" class="text-xs text-slate-300 font-medium">Status:</label>
            <select
                id="status-filter"
                wire:model.live="statusFilter"
                class="px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all">All Records</option>
                <option value="published">Published</option>
                <option value="draft">Draft / In Progress</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-200">
                <thead class="bg-slate-950/70 text-slate-400 uppercase text-xs border-b border-slate-800 font-semibold tracking-wider">
                    <tr>
                        <th scope="col" class="px-6 py-4">Student</th>
                        <th scope="col" class="px-6 py-4">Course Status</th>
                        <th scope="col" class="px-6 py-4">Draft Score</th>
                        <th scope="col" class="px-6 py-4">Published Final Grade</th>
                        <th scope="col" class="px-6 py-4">Academic Status</th>
                        <th scope="col" class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($enrollments as $enrollment)
                        @php
                            $student = $enrollment->studentProfile?->user;
                            $draft = $enrollment->gradeDraft;
                            $currentGrade = $enrollment->currentGrade;
                            $published = $currentGrade?->publishedGrade;
                            $isPublished = $currentGrade && $currentGrade->status === 'published';
                        @endphp
                        <tr class="hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $student?->name ?? 'Unknown Student' }}</div>
                                <div class="text-xs text-slate-400">{{ $student?->email ?? 'No email' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $enrollment->status === 'completed' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-slate-800 text-slate-300 border border-slate-700' }}">
                                    {{ ucfirst($enrollment->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($draft)
                                    <div class="font-semibold text-white">{{ $draft->current_score }}%</div>
                                    <div class="text-xs text-slate-400">Grade: {{ $draft->current_letter_grade }}</div>
                                @else
                                    <span class="text-slate-500 text-xs italic">Not calculated</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($isPublished && $published)
                                    <div class="font-bold text-emerald-400">{{ $published->final_score }}% ({{ $published->letter_grade }})</div>
                                    <div class="text-xs text-slate-400">v{{ $published->version }} &bull; {{ $published->published_at?->format('M d, Y') }}</div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-800 text-slate-400">
                                        Unpublished
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $status = $published ? $published->academic_status : ($draft ? $draft->academic_status : 'in_progress');
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if ($status === 'passed') bg-emerald-950 text-emerald-300 border border-emerald-800
                                    @elseif ($status === 'incomplete') bg-amber-950 text-amber-300 border border-amber-800
                                    @elseif ($status === 'failed') bg-rose-950 text-rose-300 border border-rose-800
                                    @else bg-slate-800 text-slate-300 border border-slate-700 @endif">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="calculateDraft('{{ $enrollment->id }}')"
                                    class="px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors"
                                    title="Recalculate draft"
                                >
                                    Recalculate
                                </button>
                                <button
                                    type="button"
                                    wire:click="viewBreakdown('{{ $enrollment->id }}')"
                                    class="px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-300 border border-slate-700 transition-colors"
                                >
                                    Breakdown
                                </button>
                                <button
                                    type="button"
                                    wire:click="openPublishModal('{{ $enrollment->id }}')"
                                    class="px-2.5 py-1 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-colors"
                                >
                                    Publish
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                No enrolled students found for this course.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($enrollments->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $enrollments->links() }}
            </div>
        @endif
    </div>

    {{-- Publish Grade Modal --}}
    @if ($showPublishModal && $publishingEnrollment)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="publish-modal-title">
            <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl">
                <h3 id="publish-modal-title" class="text-lg font-bold text-white">Publish Official Final Grade</h3>
                <p class="text-sm text-slate-300">
                    Publishing an official final grade creates an immutable academic snapshot for
                    <strong class="text-white">{{ $publishingEnrollment->studentProfile?->user?->name }}</strong>.
                    Published records cannot be deleted or overwritten.
                </p>

                <div>
                    <label for="pub-reason" class="block text-xs font-medium text-slate-300 mb-1">Reason for Publication</label>
                    <input
                        id="pub-reason"
                        type="text"
                        wire:model="publicationReason"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="e.g. End of term official evaluation"
                    >
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="closeModals"
                        class="px-4 py-2 text-sm font-medium rounded-lg text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="publishGrade"
                        class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
                    >
                        Confirm & Publish
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Breakdown Modal --}}
    @if ($showBreakdownModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="breakdown-modal-title">
            <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-2xl w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 id="breakdown-modal-title" class="text-lg font-bold text-white">Calculation Breakdown &bull; {{ $activeStudentName }}</h3>
                    <button type="button" wire:click="closeModals" class="text-slate-400 hover:text-white text-lg">&times;</button>
                </div>

                @if ($activeBreakdown)
                    <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-950 p-4 rounded-lg border border-slate-800">
                            <div>
                                <span class="block text-xs text-slate-400">Final Score</span>
                                <span class="text-lg font-bold text-white">{{ $activeBreakdown['final_score'] ?? '0.00' }}%</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-400">Letter Grade</span>
                                <span class="text-lg font-bold text-indigo-400">{{ $activeBreakdown['letter_grade'] ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-400">Academic Status</span>
                                <span class="text-sm font-semibold capitalize text-emerald-400">{{ $activeBreakdown['academic_status'] ?? 'in_progress' }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-400">Passing Status</span>
                                <span class="text-sm font-semibold {{ !empty($activeBreakdown['is_passing']) ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ !empty($activeBreakdown['is_passing']) ? 'Passed' : 'Not Passed' }}
                                </span>
                            </div>
                        </div>

                        <div class="text-xs text-slate-400">
                            <span class="font-medium text-slate-300">Unrounded 16-decimal Score:</span>
                            <code>{{ $activeBreakdown['unrounded_score'] ?? '0' }}</code>
                        </div>

                        @if (!empty($activeBreakdown['categories']))
                            <div class="space-y-2">
                                <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Category Performance</h4>
                                @foreach ($activeBreakdown['categories'] as $cat)
                                    <div class="p-3 bg-slate-950 rounded-lg border border-slate-800 space-y-1">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-medium text-white">{{ $cat['name'] ?? 'Category' }}</span>
                                            <span class="text-slate-300">Weight: {{ $cat['normalized_weight'] ?? $cat['weight'] ?? 0 }}%</span>
                                        </div>
                                        <div class="flex items-center justify-between text-xs text-slate-400">
                                            <span>Score: {{ $cat['unrounded_score'] ?? 0 }}%</span>
                                            <span>Contribution: {{ $cat['course_contribution'] ?? 0 }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-slate-400 py-6 text-center">No calculation snapshot available.</p>
                @endif

                <div class="flex justify-end pt-2">
                    <button
                        type="button"
                        wire:click="closeModals"
                        class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-slate-800 hover:bg-slate-700 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
