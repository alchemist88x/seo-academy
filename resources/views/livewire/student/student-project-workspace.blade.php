<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Breadcrumb & Nav -->
    <div class="flex items-center justify-between">
        <a href="{{ route('student.projects.index') }}" class="text-sm font-medium text-text-muted hover:text-text-primary transition flex items-center gap-1.5">
            &larr; Back to My Projects
        </a>

        @if($isApproved)
            <a href="{{ route('student.portfolio.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs rounded-lg shadow-xs transition">
                &check; Project Approved &bull; Add to Portfolio &rarr;
            </a>
        @endif
    </div>

    <!-- Feedback Message -->
    @if(session()->has('message'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 rounded-lg text-sm flex items-center justify-between">
            <span>{{ session('message') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    <!-- Project Overview Card -->
    <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-default pb-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300">
                    {{ $project->categoryLabel() }}
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                    {{ $project->difficulty === 'expert' ? 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300' : ($project->difficulty === 'intermediate' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300') }}">
                    {{ $project->difficultyLabel() }}
                </span>
            </div>
            <div class="text-xs text-text-muted">
                Passing Marks: <strong class="text-text-primary">{{ number_format($project->passing_marks, 0) }} / {{ number_format($project->max_marks, 0) }}</strong>
                @if($project->due_date)
                    &bull; Due: <strong class="{{ $project->isPastDue() ? 'text-red-500' : 'text-text-primary' }}">{{ $project->due_date->format('M d, Y H:i') }}</strong>
                @endif
            </div>
        </div>

        <div>
            <h1 class="text-2xl font-bold text-text-primary">{{ $project->title }}</h1>
            <p class="text-sm text-text-muted mt-2 leading-relaxed">
                {{ $project->description ?: 'Execute realistic hands-on SEO agency deliverables according to client specifications.' }}
            </p>
        </div>

        @if(!empty($project->objectives))
            <div>
                <h4 class="text-xs font-bold uppercase text-text-primary mb-2">Campaign Objectives:</h4>
                <ul class="list-disc list-inside text-xs text-text-muted space-y-1">
                    @foreach($project->objectives as $obj)
                        <li>{{ $obj }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($project->expected_deliverables))
            <div>
                <h4 class="text-xs font-bold uppercase text-text-primary mb-2">Required Deliverables:</h4>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($project->expected_deliverables as $deliv)
                        <span class="inline-flex items-center px-2 py-1 bg-surface-bg border border-border-default rounded text-xs text-text-primary font-medium">
                            &bull; {{ $deliv }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($project->instructions))
            <div class="pt-3 border-t border-border-default">
                <h4 class="text-xs font-bold uppercase text-text-primary mb-1">Detailed Instructions:</h4>
                <div class="text-xs text-text-muted whitespace-pre-line leading-relaxed bg-surface-bg p-3 rounded-lg border border-border-default">
                    {{ $project->instructions }}
                </div>
            </div>
        @endif

        @if($project->criteria->isNotEmpty())
            <div class="pt-3 border-t border-border-default">
                <h4 class="text-xs font-bold uppercase text-text-primary mb-2">Evaluation Rubric & Weighting:</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($project->criteria as $criterion)
                        <div class="p-3 bg-surface-bg border border-border-default rounded-lg text-xs">
                            <div class="flex items-center justify-between font-semibold text-text-primary">
                                <span>{{ $criterion->title }}</span>
                                <span class="text-primary-600 dark:text-primary-400">{{ number_format($criterion->max_marks, 0) }} pts</span>
                            </div>
                            @if($criterion->description)
                                <p class="text-text-muted mt-1 text-[11px]">{{ $criterion->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Submission Form Section (If not approved and not pending review) -->
    @if(! $isApproved)
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-border-default pb-3">
                <h2 class="text-base font-bold text-text-primary">
                    {{ $latestSubmission && $latestSubmission->isRevisionRequested() ? 'Submit Project Revision' : 'Submit Project Work' }}
                </h2>
                @if($latestSubmission)
                    <span class="text-xs text-text-muted">
                        Next submission will be recorded as <strong>v{{ $latestSubmission->version_number + 1 }}</strong>
                    </span>
                @endif
            </div>

            @if($isPending)
                <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-200">
                    <strong>Submission Pending:</strong> Your work is currently under review by the trainer. You will receive a notification as soon as the evaluation is completed.
                </div>
            @else
                <form wire:submit="submitWork" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Target Client / Audited Website URL (Optional)</label>
                        <input wire:model="websiteUrl" type="url" placeholder="https://example.com" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                        @error('websiteUrl') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Executive Summary & Written Methodology *</label>
                        <textarea wire:model="content" rows="5" placeholder="Detail your findings, methodology used (e.g. tools, crawl settings), root cause analyses, and strategic recommendations..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required></textarea>
                        @error('content') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Attachment Category</label>
                            <select wire:model="attachmentType" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @foreach($attachmentTypes as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Upload Project Deliverables (PDF, Spreadsheets, Screenshots)</label>
                            <input wire:model="uploadedFiles" type="file" multiple class="w-full text-xs rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                            <div wire:loading wire:target="uploadedFiles" class="text-xs text-primary-600 mt-1">Uploading files...</div>
                            @error('uploadedFiles.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Student Notes to Evaluator (Optional)</label>
                        <input wire:model="studentNotes" type="text" placeholder="Special instructions, credentials, or notes for the trainer..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                    </div>

                    <div class="flex items-center justify-end pt-2">
                        <button type="submit" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-sm rounded-lg shadow-xs transition">
                            {{ $latestSubmission && $latestSubmission->isRevisionRequested() ? 'Submit Revised Work' : 'Submit Project Work' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <!-- Submission History Timeline -->
    <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
        <h2 class="text-base font-bold text-text-primary border-b border-border-default pb-3">Submission History & Evaluation Feedback</h2>

        <div class="space-y-4">
            @forelse($submissions as $sub)
                @php
                    $eval = $sub->latestEvaluation;
                    $ach = $sub->achievement;
                @endphp
                <div class="p-4 bg-surface-bg border border-border-default rounded-xl space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-text-primary uppercase">Version {{ $sub->version_number }}</span>
                            <span class="text-xs text-text-muted">&bull; {{ $sub->submitted_at->format('M d, Y H:i') }}</span>
                            @if($sub->is_late)
                                <span class="text-[10px] bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 font-semibold px-1.5 py-0.5 rounded">Late</span>
                            @endif
                        </div>

                        <div>
                            @if($sub->status === 'approved')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200">
                                    Approved ({{ number_format((float) ($sub->score_percentage ?? 100), 1) }}%)
                                </span>
                            @elseif($sub->status === 'revision_requested')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200">
                                    Revision Requested
                                </span>
                            @elseif(in_array($sub->status, ['submitted', 'resubmitted', 'under_review']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">
                                    Pending Evaluation
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($sub->website_url)
                        <div class="text-xs">
                            <span class="text-text-muted">Target URL:</span>
                            <a href="{{ $sub->website_url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline font-mono ml-1">{{ $sub->website_url }}</a>
                        </div>
                    @endif

                    <div class="text-xs text-text-muted whitespace-pre-line bg-surface-card p-3 rounded-lg border border-border-default">
                        {{ $sub->content }}
                    </div>

                    <!-- Attachments List -->
                    @if($sub->attachments->isNotEmpty())
                        <div class="space-y-1">
                            <div class="text-[11px] font-semibold text-text-muted uppercase">Attached Files:</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($sub->attachments as $att)
                                    <a href="{{ route('projects.attachments.download', ['submission' => $sub->id, 'attachment' => $att->id]) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-surface-card hover:bg-border-default border border-border-default rounded text-xs text-text-primary transition">
                                        <svg class="w-3.5 h-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        <span>{{ $att->file_name }}</span>
                                        <span class="text-text-muted text-[10px]">({{ $att->getReadableSize() }})</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Evaluation Feedback Display -->
                    @if($eval)
                        <div class="mt-3 p-3 rounded-lg border {{ $eval->status === 'approved' ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-red-50/50 dark:bg-red-950/20 border-red-200 dark:border-red-800' }}">
                            <div class="flex items-center justify-between text-xs font-bold {{ $eval->status === 'approved' ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                                <span>Evaluator Feedback (by {{ $eval->evaluator?->name ?? 'Trainer' }})</span>
                                <span>{{ $eval->points_awarded }} / {{ $eval->points_possible }} pts ({{ $eval->score_percentage }}%)</span>
                            </div>

                            @if($eval->revision_reason)
                                <div class="mt-2 text-xs text-red-700 dark:text-red-300">
                                    <strong>Required Revision:</strong> {{ $eval->revision_reason }}
                                </div>
                            @endif

                            @if($eval->trainer_feedback)
                                <div class="mt-2 text-xs text-text-primary">
                                    <strong>Feedback:</strong> {{ $eval->trainer_feedback }}
                                </div>
                            @endif

                            @if($eval->improvement_recommendations)
                                <div class="mt-2 text-xs text-text-muted">
                                    <strong>Improvement Recommendations:</strong> {{ $eval->improvement_recommendations }}
                                </div>
                            @endif

                            @if(!empty($eval->criteria_feedback))
                                <div class="mt-3 pt-2 border-t border-border-default/50 space-y-1">
                                    <div class="text-[11px] font-semibold uppercase text-text-muted">Rubric Criteria Marks:</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5 text-xs">
                                        @foreach($eval->criteria_feedback as $cf)
                                            <div class="flex items-center justify-between p-1.5 bg-surface-card rounded border border-border-default">
                                                <span>{{ $cf['title'] ?? 'Criterion' }}</span>
                                                <span class="font-bold text-text-primary">{{ $cf['awarded_marks'] }} / {{ $cf['max_marks'] }} pts</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($ach)
                                <div class="mt-3 pt-2 border-t border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200 flex items-center justify-between">
                                    <span>&check; <strong>Official Achievement Issued:</strong> {{ $ach->title }}</span>
                                    <span class="font-mono text-[11px]">{{ $ach->achievement_number }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-6 text-center text-xs text-text-muted">
                    No submissions have been recorded for this project yet. Complete the form above to submit your deliverables.
                </div>
            @endforelse
        </div>
    </div>
</div>
