<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <div class="flex items-center gap-2 text-xs text-text-secondary mb-1">
                <a href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.index') : route('trainer.assignments.index') }}" class="hover:underline">Assignments</a>
                <span>&rsaquo;</span>
                <span>{{ $assignment->course?->title }}</span>
            </div>
            <h1 class="text-2xl font-bold text-text-primary">{{ $assignment->title }} — Submissions Queue</h1>
            <p class="text-xs text-text-secondary mt-1">Review student deliverables, provide rubric-based evaluations, and manage revision requests.</p>
        </div>
        <a
            href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.index') : route('trainer.assignments.index') }}"
            class="btn btn-secondary btn-sm"
        >
            &larr; Back to Assignments
        </a>
    </div>

    {{-- Filter Bar --}}
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search students by name, email, or code..."
                class="input w-full"
            >
            <select wire:model.live="statusFilter" class="input w-full">
                <option value="all">All Submission Statuses</option>
                <option value="submitted">Submitted (Pending Review)</option>
                <option value="resubmitted">Resubmitted (Pending Review)</option>
                <option value="under_review">Under Review</option>
                <option value="revision_requested">Revision Requested</option>
                <option value="graded">Graded</option>
            </select>
        </div>
    </x-card>

    {{-- Submissions Table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table w-full text-xs">
                <thead>
                    <tr class="text-left text-text-muted border-b border-border-default">
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4">Version</th>
                        <th class="py-3 px-4">Submitted At</th>
                        <th class="py-3 px-4">Deliverables</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Grade / Result</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default/60">
                    @forelse ($submissions as $sub)
                        <tr class="hover:bg-surface-bg/50 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-text-primary">{{ $sub->user?->name ?? 'Unknown Student' }}</div>
                                <div class="text-[11px] text-text-secondary">
                                    {{ $sub->user?->studentProfile?->student_code ?? $sub->user?->email }}
                                </div>
                            </td>
                            <td class="py-3 px-4 font-semibold text-text-primary">
                                v{{ $sub->version_number }}
                            </td>
                            <td class="py-3 px-4 text-text-secondary whitespace-nowrap">
                                <div>{{ $sub->submitted_at ? $sub->submitted_at->format('M d, Y H:i') : '-' }}</div>
                                @if ($sub->is_late)
                                    <span class="badge bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 text-[10px] mt-0.5 inline-block">Late</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-text-secondary">
                                {{ $sub->attachments->count() }} {{ Str::plural('file', $sub->attachments->count()) }}
                                @if (! empty($sub->content))
                                    <span class="text-text-muted ml-1">&bull; Written text</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($sub->status === 'graded')
                                    <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Graded</span>
                                @elseif ($sub->status === 'revision_requested')
                                    <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Revision Requested</span>
                                @elseif ($sub->status === 'resubmitted')
                                    <span class="badge bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">Resubmitted</span>
                                @else
                                    <span class="badge bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">Submitted</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($sub->latestEvaluation)
                                    <div class="font-bold {{ $sub->latestEvaluation->is_passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ number_format((float) $sub->latestEvaluation->points_awarded, 2) }} / {{ number_format((float) $sub->latestEvaluation->points_possible, 2) }}
                                        <span class="text-[11px] font-normal">({{ number_format((float) $sub->latestEvaluation->score_percentage, 1) }}%)</span>
                                    </div>
                                    <div class="text-[10px] text-text-muted">
                                        {{ $sub->latestEvaluation->is_passed ? 'Passed' : 'Needs improvement' }}
                                    </div>
                                @else
                                    <span class="text-text-muted">Pending evaluation</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                                <a
                                    href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.grade', ['assignment' => $assignment->id, 'submission' => $sub->id]) : route('trainer.assignments.grade', ['assignment' => $assignment->id, 'submission' => $sub->id]) }}"
                                    class="btn btn-primary btn-xs"
                                >
                                    {{ $sub->latestEvaluation ? 'Review / Regrade' : 'Review & Grade' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-text-muted">
                                No submissions found matching the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-4">
            {{ $submissions->links() }}
        </div>
    </x-card>
</div>
