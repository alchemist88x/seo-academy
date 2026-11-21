<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Practical SEO Projects</h1>
            <p class="text-sm text-text-muted mt-1">Design, publish and manage realistic hands-on SEO projects, evaluate criteria, and oversee portfolio milestones.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.projects.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-sm rounded-lg shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create SEO Project
            </a>
        </div>
    </div>

    <!-- Feedback Message -->
    @if(session()->has('message'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 rounded-lg text-sm flex items-center justify-between">
            <span>{{ session('message') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    <!-- KPIs Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Total Projects</div>
            <div class="text-2xl font-bold text-text-primary mt-1">{{ $kpis['total_projects'] }}</div>
            <div class="text-xs text-text-muted mt-1">{{ $kpis['published_projects'] }} published</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Total Submissions</div>
            <div class="text-2xl font-bold text-text-primary mt-1">{{ $kpis['total_submissions'] }}</div>
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">{{ $kpis['approved_submissions'] }} approved ({{ $kpis['completion_rate_percent'] }}%)</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Average Score</div>
            <div class="text-2xl font-bold text-text-primary mt-1">{{ $kpis['average_score_percent'] }}%</div>
            <div class="text-xs text-text-muted mt-1">Turnaround ~{{ $kpis['average_turnaround_hours'] }}h</div>
        </div>
        <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
            <div class="text-xs font-medium text-text-muted uppercase">Achievements Issued</div>
            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ $kpis['total_achievements_issued'] }}</div>
            <div class="text-xs text-text-muted mt-1">Verified Badges</div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-surface-card border border-border-default rounded-xl p-4 shadow-xs">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search projects..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
            </div>
            <div>
                <select wire:model.live="categoryFilter" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                    <option value="all">All Categories</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="difficultyFilter" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                    <option value="all">All Difficulties</option>
                    @foreach($difficulties as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="statusFilter" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                    <option value="all">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="bg-surface-card border border-border-default rounded-xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-text-primary">
                <thead class="bg-surface-bg border-b border-border-default text-xs uppercase font-semibold text-text-muted tracking-wider">
                    <tr>
                        <th class="px-6 py-3">Project Title</th>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3">Difficulty</th>
                        <th class="px-6 py-3">Course / Scope</th>
                        <th class="px-6 py-3">Submissions</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse($projects as $project)
                        <tr class="hover:bg-surface-bg/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-text-primary">{{ $project->title }}</div>
                                <div class="text-xs text-text-muted mt-0.5">
                                    Max: {{ number_format($project->max_marks, 0) }} pts &bull; Pass: {{ number_format($project->passing_marks, 0) }} pts &bull; Revisions: {{ $project->max_revisions }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300">
                                    {{ $project->categoryLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                    {{ $project->difficulty === 'expert' ? 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300' : ($project->difficulty === 'intermediate' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300') }}">
                                    {{ $project->difficultyLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-text-muted">
                                {{ $project->course?->title ?? 'Global (All Enrolled)' }}
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span class="font-semibold text-text-primary">{{ $project->submissions_count }}</span> submitted
                                @if($project->achievements_count > 0)
                                    &bull; <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $project->achievements_count }} completed</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($project->status === 'published')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">Published</span>
                                @elseif($project->status === 'draft')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">Draft</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">Archived</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.projects.edit', $project->id) }}" class="text-xs text-primary-600 hover:text-primary-700 font-medium">Edit</a>
                                @if($project->status !== 'published')
                                    <button wire:click="publish('{{ $project->id }}')" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Publish</button>
                                @endif
                                @if($project->status === 'published')
                                    <button wire:click="archive('{{ $project->id }}')" class="text-xs text-amber-600 hover:text-amber-700 font-medium">Archive</button>
                                @endif
                                <button wire:click="delete('{{ $project->id }}')" wire:confirm="Are you sure you want to delete this project?" class="text-xs text-red-600 hover:text-red-700 font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-text-muted">
                                No practical SEO projects found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-border-default">
            {{ $projects->links() }}
        </div>
    </div>
</div>
