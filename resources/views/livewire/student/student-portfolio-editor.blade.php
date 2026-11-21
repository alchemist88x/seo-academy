<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">SEO Practical Portfolio</h1>
            <p class="text-sm text-text-muted mt-1">Showcase approved SEO campaigns, technical audits, and demonstrated skills to employers and clients.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('student.portfolio.export.pdf') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-border-default hover:bg-surface-card bg-surface-bg text-text-primary font-medium text-xs rounded-lg shadow-xs transition">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export as PDF
            </a>

            @if($isPublic && $publicToken)
                <a href="{{ route('portfolio.public', $publicToken) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-xs rounded-lg shadow-xs transition">
                    Preview Public Link &nearr;
                </a>
                <button wire:click="revokeSharing" wire:confirm="Are you sure you want to revoke public access? Past shared links will immediately stop working." class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-50 dark:bg-red-950/40 hover:bg-red-100 text-red-700 dark:text-red-300 font-medium text-xs rounded-lg transition">
                    Revoke Public Access
                </button>
            @else
                <button wire:click="openShareModal" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs rounded-lg shadow-xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Enable Public Sharing
                </button>
            @endif
        </div>
    </div>

    <!-- Feedback Message -->
    @if(session()->has('message'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 rounded-lg text-sm flex items-center justify-between">
            <span>{{ session('message') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    <!-- Privacy Banner -->
    <div class="p-4 rounded-xl border {{ $isPublic ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-surface-card border-border-default' }}">
        <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2.5 w-2.5 rounded-full {{ $isPublic ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                <span class="font-bold text-text-primary">
                    {{ $isPublic ? 'Public Sharing Active' : 'Private by Default' }}
                </span>
                <span class="text-text-muted">
                    {{ $isPublic ? 'Your portfolio can be accessed via unique token link.' : 'Only you can view your portfolio items.' }}
                </span>
            </div>
            @if($isPublic && $publicToken)
                <div class="font-mono text-[11px] text-primary-600 bg-surface-bg px-2 py-1 rounded border border-border-default select-all">
                    {{ route('portfolio.public', $publicToken) }}
                </div>
            @endif
        </div>
    </div>

    <!-- Portfolio Profile & Bio Card -->
    <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
        <h2 class="text-base font-bold text-text-primary border-b border-border-default pb-3">1. Portfolio Overview & Headline</h2>

        <form wire:submit="saveHeader" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Portfolio Title *</label>
                    <input wire:model="title" type="text" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Professional Headline</label>
                    <input wire:model="headline" type="text" placeholder="Certified SEO Specialist & Technical Auditor" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Executive Bio / Summary</label>
                <textarea wire:model="bio" rows="3" placeholder="Highlight your specialization, tools used (Screaming Frog, Ahrefs, GSC), and project experience..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Core Competencies & Skills (Comma separated)</label>
                <input wire:model="customSkillsText" type="text" placeholder="Technical SEO, Keyword Research, Schema Markup, Core Web Vitals" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-xs rounded-lg shadow-xs transition">
                    Save Portfolio Profile
                </button>
            </div>
        </form>
    </div>

    <!-- Showcased Projects Section -->
    <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-border-default pb-3">
            <div>
                <h2 class="text-base font-bold text-text-primary">2. Verified Practical SEO Projects</h2>
                <p class="text-xs text-text-muted mt-0.5">Showcase completed projects evaluated and approved by SEO Academy trainers.</p>
            </div>
            @if($availableApprovedSubmissions->isNotEmpty())
                <button wire:click="openAddItemModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white font-medium text-xs rounded-lg transition">
                    + Add Approved Project
                </button>
            @endif
        </div>

        @if($availableApprovedSubmissions->isNotEmpty() && $portfolioItems->isEmpty())
            <div class="p-4 bg-primary-50/50 dark:bg-primary-950/20 border border-primary-200 dark:border-primary-800 rounded-lg flex items-center justify-between text-xs">
                <div>
                    <strong class="text-primary-800 dark:text-primary-200">You have {{ $availableApprovedSubmissions->count() }} approved project(s) ready to showcase!</strong>
                    <div class="text-text-muted">Click the button to add them into your portfolio presentation.</div>
                </div>
                <button wire:click="openAddItemModal" class="px-3 py-1.5 bg-primary-600 text-white rounded font-medium text-xs">
                    Add Project
                </button>
            </div>
        @endif

        <div class="space-y-4">
            @forelse($portfolioItems as $item)
                @php
                    $sub = $item->submission;
                    $proj = $sub?->project;
                    $ach = $sub?->achievement;
                @endphp
                <div class="p-4 bg-surface-bg border border-border-default rounded-xl space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-text-primary">{{ $item->title }}</h3>
                            @if($item->is_featured)
                                <span class="text-[10px] bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 font-bold px-1.5 py-0.5 rounded">Featured</span>
                            @endif
                            @if(! $item->is_visible)
                                <span class="text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-600 font-semibold px-1.5 py-0.5 rounded">Hidden</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">
                                Score: {{ number_format((float) ($sub?->score_percentage ?? 100), 1) }}%
                            </span>

                            <button wire:click="openEditItemModal('{{ $item->id }}')" class="text-xs text-primary-600 hover:text-primary-700 font-medium">Edit</button>
                            <button wire:click="toggleItemVisibility('{{ $item->id }}')" class="text-xs text-text-muted hover:text-text-primary">
                                {{ $item->is_visible ? 'Hide' : 'Unhide' }}
                            </button>
                            <button wire:click="removeItem('{{ $item->id }}')" wire:confirm="Remove this project from your portfolio?" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </div>
                    </div>

                    <p class="text-xs text-text-muted leading-relaxed">
                        {{ $item->case_study }}
                    </p>

                    @if($sub?->website_url)
                        <div class="text-xs">
                            <span class="text-text-muted font-semibold">Target URL:</span>
                            <a href="{{ $sub->website_url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline font-mono ml-1">{{ $sub->website_url }}</a>
                        </div>
                    @endif

                    @if(!empty($item->skills_demonstrated))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($item->skills_demonstrated as $skill)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-surface-card border border-border-default text-text-primary">
                                    {{ $skill }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($ach)
                        <div class="text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">
                            &check; Verified Achievement Code: <span class="font-mono">{{ $ach->achievement_number }}</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-12 text-center text-xs text-text-muted">
                    No practical projects have been included in your portfolio yet. As soon as your project submissions are evaluated and approved, they will appear here.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Public Sharing Consent Modal -->
    @if($showConsentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-surface-card border border-border-default rounded-xl p-6 max-w-lg w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-border-default pb-3">
                    <h3 class="text-base font-bold text-text-primary">Enable Public Portfolio Sharing</h3>
                    <button wire:click="$set('showConsentModal', false)" class="text-text-muted hover:text-text-primary">&times;</button>
                </div>

                <div class="text-xs text-text-muted space-y-3 leading-relaxed">
                    <p>
                        Publishing your portfolio allows prospective employers, clients, and partners to view your verified practical SEO deliverables through a secure, opaque token URL.
                    </p>
                    <div class="p-3 bg-surface-bg rounded-lg border border-border-default space-y-2">
                        <strong class="text-text-primary">Privacy & Security Guarantees:</strong>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Internal database IDs and student account numbers are never exposed.</li>
                            <li>Course classroom videos and internal lectures are never made public.</li>
                            <li>You retain full control and can revoke public access instantly at any time.</li>
                        </ul>
                    </div>
                </div>

                <label class="flex items-start gap-2.5 p-2 rounded cursor-pointer hover:bg-surface-bg">
                    <input type="checkbox" wire:model="consentAgreed" class="mt-0.5 rounded text-primary-600 focus:ring-primary-500 border-border-default">
                    <span class="text-xs text-text-primary font-medium">
                        I give explicit consent to generate a public link displaying my name, portfolio headline, case studies, and verified project achievement codes.
                    </span>
                </label>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                    <button wire:click="$set('showConsentModal', false)" class="px-4 py-2 border border-border-default rounded-lg text-xs text-text-primary hover:bg-surface-bg">
                        Cancel
                    </button>
                    <button wire:click="confirmEnableSharing" {{ ! $consentAgreed ? 'disabled' : '' }} class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-medium text-xs rounded-lg shadow-xs transition">
                        Generate & Enable Public Link
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Add / Edit Item Modal -->
    @if($showItemModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-surface-card border border-border-default rounded-xl p-6 max-w-lg w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-border-default pb-3">
                    <h3 class="text-base font-bold text-text-primary">
                        {{ $editingItemId ? 'Edit Portfolio Project' : 'Add Approved Project to Portfolio' }}
                    </h3>
                    <button wire:click="$set('showItemModal', false)" class="text-text-muted hover:text-text-primary">&times;</button>
                </div>

                <form wire:submit="saveItem" class="space-y-4">
                    @if(! $editingItemId)
                        <div>
                            <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Select Approved Project *</label>
                            <select wire:model="selectedSubmissionId" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                                <option value="">Select an approved project...</option>
                                @foreach($availableApprovedSubmissions as $sub)
                                    <option value="{{ $sub->id }}">
                                        {{ $sub->project?->title }} (Score: {{ number_format((float) ($sub->score_percentage ?? 100), 1) }}%)
                                    </option>
                                @endforeach
                            </select>
                            @error('selectedSubmissionId') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Display Title *</label>
                        <input wire:model="itemTitle" type="text" placeholder="e.g. Technical SEO Site Audit & Core Web Vitals Optimization" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Case Study / Project Description *</label>
                        <textarea wire:model="itemCaseStudy" rows="4" placeholder="Summarize your role, methodology used, challenges identified, and key results achieved..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Skills Demonstrated (Comma separated)</label>
                        <input wire:model="itemSkillsText" type="text" placeholder="Technical Crawl, Screaming Frog, Canonicalization, LCP Optimization" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="itemFeatured" class="rounded text-primary-600 focus:ring-primary-500 border-border-default">
                            <span class="text-xs text-text-primary">Featured Project</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="itemVisible" class="rounded text-primary-600 focus:ring-primary-500 border-border-default">
                            <span class="text-xs text-text-primary">Visible in Portfolio</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                        <button type="button" wire:click="$set('showItemModal', false)" class="px-4 py-2 border border-border-default rounded-lg text-xs text-text-primary hover:bg-surface-bg">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-xs rounded-lg shadow-xs transition">
                            Save to Portfolio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
