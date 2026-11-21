<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <!-- Student Header Banner -->
    <div class="bg-surface-card border border-border-default rounded-2xl p-6 sm:p-8 shadow-xs space-y-4">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">
                &check; Verified SEO Academy Graduate Portfolio
            </span>
        </div>

        <div>
            <h1 class="text-3xl font-extrabold text-text-primary tracking-tight">
                {{ $portfolio->user?->name ?? 'SEO Specialist' }}
            </h1>
            <p class="text-lg font-medium text-primary-600 dark:text-primary-400 mt-1">
                {{ $portfolio->headline ?? 'Certified SEO Specialist & Practical Campaign Portfolio' }}
            </p>
        </div>

        @if($portfolio->bio)
            <p class="text-sm text-text-muted leading-relaxed">
                {{ $portfolio->bio }}
            </p>
        @endif

        @if(!empty($portfolio->custom_skills))
            <div class="pt-4 border-t border-border-default">
                <div class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Core Demonstrated Capabilities:</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($portfolio->custom_skills as $skill)
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 border border-primary-200/50 dark:border-primary-800/50">
                            {{ $skill }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Verified Practical SEO Projects -->
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-text-primary">Verified Agency SEO Projects</h2>
            <span class="text-xs text-text-muted font-medium">{{ $portfolio->visibleItems->count() }} Projects Showcased</span>
        </div>

        <div class="space-y-6">
            @forelse($portfolio->visibleItems as $item)
                @php
                    $sub = $item->submission;
                    $proj = $sub?->project;
                    $ach = $sub?->achievement;
                @endphp
                <div class="bg-surface-card border border-border-default rounded-2xl p-6 sm:p-8 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-default pb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300">
                                {{ $proj?->categoryLabel() ?? 'Practical SEO' }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300">
                                {{ $proj?->difficultyLabel() ?? 'Intermediate' }}
                            </span>
                        </div>

                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            Evaluated Score: {{ number_format((float) ($sub?->score_percentage ?? 100), 1) }}%
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-text-primary">{{ $item->title }}</h3>
                        <p class="text-sm text-text-muted mt-2 leading-relaxed whitespace-pre-line">
                            {{ $item->case_study }}
                        </p>
                    </div>

                    @if($sub?->website_url)
                        <div class="p-3 bg-surface-bg rounded-xl border border-border-default text-xs flex items-center justify-between">
                            <span class="text-text-muted font-semibold">Audited Website:</span>
                            <a href="{{ $sub->website_url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 font-mono font-bold hover:underline">{{ $sub->website_url }} &nearr;</a>
                        </div>
                    @endif

                    @if(!empty($item->skills_demonstrated))
                        <div class="flex flex-wrap gap-1.5 pt-2">
                            @foreach($item->skills_demonstrated as $skill)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-bg border border-border-default text-text-muted">
                                    {{ $skill }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($ach)
                        <div class="pt-3 border-t border-border-default flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-text-muted">
                            <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-semibold">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ $ach->title }}</span>
                            </div>
                            <div class="font-mono text-[11px] text-text-muted">
                                Code: {{ $ach->achievement_number }}
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-surface-card border border-border-default rounded-xl p-8 text-center text-sm text-text-muted">
                    No public items are currently available in this portfolio.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Footer verification disclaimer -->
    <div class="pt-8 border-t border-border-default text-center text-xs text-text-muted space-y-1">
        <p>This practical portfolio was evaluated and issued by the SEO Academy practical assessment engine.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'SEO Academy') }}. All rights reserved.</p>
    </div>
</div>
