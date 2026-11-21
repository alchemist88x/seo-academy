<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Trainer Project Evaluations</h1>
            <p class="text-sm text-text-muted mt-1">Review student practical SEO deliverables, assess rubric criteria, request revisions, and issue project approvals.</p>
        </div>
    </div>

    <!-- Trainer Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Assigned Projects</div>
            <div class="text-2xl font-bold text-text-primary mt-1">{{ $analytics['assigned_projects_count'] }}</div>
            <div class="text-xs text-text-muted mt-1">Teaching Courses</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Pending Reviews</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $analytics['pending_evaluations_count'] }}</div>
            <div class="text-xs text-text-muted mt-1">Action required</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Completed Reviews</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $analytics['completed_evaluations_count'] }}</div>
            <div class="text-xs text-text-muted mt-1">{{ $analytics['approval_rate_percent'] }}% approval rate</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Average Turnaround</div>
            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ $analytics['average_turnaround_hours'] }}h</div>
            <div class="text-xs text-text-muted mt-1">Review speed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="$set('statusFilter', 'pending')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'pending' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-bg text-text-muted hover:text-text-primary' }}">
                Pending Evaluation
            </button>
            <button wire:click="$set('statusFilter', 'revision_requested')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'revision_requested' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-bg text-text-muted hover:text-text-primary' }}">
                Revisions Requested
            </button>
            <button wire:click="$set('statusFilter', 'approved')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'approved' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-bg text-text-muted hover:text-text-primary' }}">
                Approved Submissions
            </button>
            <button wire:click="$set('statusFilter', 'all')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'all' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-bg text-text-muted hover:text-text-primary' }}">
                All Submissions
            </button>
        </div>

        <div class="w-full md:w-64">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search student or project..." class="w-full text-xs rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
        </div>
    </div>

    <!-- Submissions Table -->
    <div class="bg-surface-card border border-border-default rounded-xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-text-primary">
                <thead class="bg-surface-bg border-b border-border-default text-xs uppercase font-semibold text-text-muted tracking-wider">
                    <tr>
                        <th class="px-6 py-3">Student</th>
                        <th class="px-6 py-3">Project Title</th>
                        <th class="px-6 py-3">Course</th>
                        <th class="px-6 py-3">Version</th>
                        <th class="px-6 py-3">Submitted At</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse($submissions as $sub)
                        <tr class="hover:bg-surface-bg/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-text-primary">{{ $sub->studentProfile?->user?->name ?? 'Student' }}</div>
                                <div class="text-xs text-text-muted">{{ $sub->studentProfile?->user?->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-text-primary">{{ $sub->project?->title }}</div>
                                <div class="text-xs text-text-muted">{{ $sub->project?->categoryLabel() }} &bull; Max {{ number_format($sub->project?->max_marks, 0) }} pts</div>
                            </td>
                            <td class="px-6 py-4 text-xs text-text-muted">
                                {{ $sub->project?->course?->title ?? 'Global Project' }}
                            </td>
                            <td class="px-6 py-4 text-xs font-mono">
                                v{{ $sub->version_number }}
                            </td>
                            <td class="px-6 py-4 text-xs text-text-muted">
                                {{ $sub->submitted_at->format('M d, Y H:i') }}
                                @if($sub->is_late)
                                    <span class="text-red-500 font-bold ml-1">(Late)</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($sub->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">
                                        Approved ({{ number_format((float) ($sub->score_percentage ?? 100), 1) }}%)
                                    </span>
                                @elseif($sub->status === 'revision_requested')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200">
                                        Revision Requested
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">
                                        Pending Review
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('trainer.projects.submissions.evaluate', $sub->id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:text-primary-700 bg-primary-50 dark:bg-primary-950/50 px-2.5 py-1.5 rounded-lg transition">
                                    {{ in_array($sub->status, ['submitted', 'resubmitted', 'under_review']) ? 'Evaluate Submission' : 'View Evaluation' }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-text-muted">
                                No submissions found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-border-default">
            {{ $submissions->links() }}
        </div>
    </div>
</div>
