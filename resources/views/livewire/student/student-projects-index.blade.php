<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">My Practical SEO Projects</h1>
            <p class="text-sm text-text-muted mt-1">Complete realistic SEO agency campaigns, receive trainer evaluations, and earn verified achievements for your portfolio.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('student.portfolio.index') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-border-default hover:bg-surface-card bg-surface-bg text-text-primary font-medium text-sm rounded-lg shadow-xs transition">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Manage My Portfolio
            </a>
        </div>
    </div>

    <!-- Student Performance Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Approved & Completed</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['approved_count'] }}</div>
            <div class="text-xs text-text-muted mt-1">Ready for portfolio</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Pending Evaluation</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['submitted_count'] - $metrics['approved_count'] }}</div>
            <div class="text-xs text-text-muted mt-1">Under trainer review</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Revisions Requested</div>
            <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $metrics['revisions_requested_count'] }}</div>
            <div class="text-xs text-text-muted mt-1">Action required</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Average Score</div>
            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ $metrics['average_score_percent'] }}%</div>
            <div class="text-xs text-text-muted mt-1">{{ count($metrics['skills_demonstrated']) }} skills earned</div>
        </div>
    </div>

    <!-- Filter Tabs & Category Filter -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-border-default pb-3">
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="$set('statusFilter', 'all')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'all' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-card text-text-muted hover:text-text-primary' }}">
                All Projects
            </button>
            <button wire:click="$set('statusFilter', 'in_progress')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'in_progress' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-card text-text-muted hover:text-text-primary' }}">
                Available / To Start
            </button>
            <button wire:click="$set('statusFilter', 'submitted')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'submitted' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-card text-text-muted hover:text-text-primary' }}">
                Submitted
            </button>
            <button wire:click="$set('statusFilter', 'revision_requested')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'revision_requested' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-card text-text-muted hover:text-text-primary' }}">
                Needs Revision
            </button>
            <button wire:click="$set('statusFilter', 'approved')" class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $statusFilter === 'approved' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-card text-text-muted hover:text-text-primary' }}">
                Approved & Badged
            </button>
        </div>

        <div>
            <select wire:model.live="categoryFilter" class="text-xs rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                <option value="all">All SEO Categories</option>
                @foreach($categories as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Projects Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($decoratedProjects as $item)
            @php
                $project = $item['project'];
                $latest = $item['latest_submission'];
                $status = $item['status'];
            @endphp
            <div class="bg-surface-card border border-border-default rounded-xl p-5 shadow-xs flex flex-col justify-between hover:border-primary-500/50 transition">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300">
                            {{ $project->categoryLabel() }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                            {{ $project->difficulty === 'expert' ? 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300' : ($project->difficulty === 'intermediate' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300') }}">
                            {{ $project->difficultyLabel() }}
                        </span>
                    </div>

                    <h3 class="text-base font-bold text-text-primary line-clamp-2">{{ $project->title }}</h3>

                    <p class="text-xs text-text-muted line-clamp-3">
                        {{ $project->description ?: 'Demonstrate realistic agency-level SEO execution including audits, strategy, and client-ready deliverables.' }}
                    </p>

                    <div class="pt-2 border-t border-border-default space-y-1.5 text-xs text-text-muted">
                        <div class="flex items-center justify-between">
                            <span>Max Marks:</span>
                            <span class="font-medium text-text-primary">{{ number_format($project->max_marks, 0) }} pts (Pass: {{ number_format($project->passing_marks, 0) }})</span>
                        </div>
                        @if($project->due_date)
                            <div class="flex items-center justify-between">
                                <span>Deadline:</span>
                                <span class="font-medium {{ $project->isPastDue() ? 'text-red-500' : 'text-text-primary' }}">
                                    {{ $project->due_date->format('M d, Y') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-border-default flex items-center justify-between">
                    <div>
                        @if($status === 'approved')
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                &check; Approved ({{ number_format((float) ($latest->score_percentage ?? 100), 1) }}%)
                            </span>
                        @elseif($status === 'revision_requested')
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-red-600 dark:text-red-400">
                                &excl; Revision Requested
                            </span>
                        @elseif($status === 'submitted')
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                Under Review (v{{ $latest->version_number }})
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-text-muted">
                                Not Submitted
                            </span>
                        @endif
                    </div>

                    <a href="{{ route('student.projects.show', $project->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700">
                        {{ $status === 'approved' ? 'View Results' : ($status === 'revision_requested' ? 'Submit Revision' : ($status === 'submitted' ? 'View Submission' : 'Start Project')) }} &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-text-muted bg-surface-card border border-border-default rounded-xl">
                No practical projects found in this category.
            </div>
        @endforelse
    </div>
</div>
