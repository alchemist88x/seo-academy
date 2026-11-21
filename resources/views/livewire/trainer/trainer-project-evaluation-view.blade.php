<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header & Navigation -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Evaluate Project Submission</h1>
            <p class="text-sm text-text-muted mt-1">
                Student: <strong>{{ $submission->studentProfile?->user?->name }}</strong> &bull;
                Project: <strong>{{ $submission->project?->title }}</strong> &bull;
                Version: <strong>v{{ $submission->version_number }}</strong>
            </p>
        </div>
        <a href="{{ auth()->user()->hasRole('admin') ? route('admin.projects.index') : route('trainer.projects.index') }}" class="text-sm font-medium text-text-muted hover:text-text-primary transition">
            &larr; Back to Submissions
        </a>
    </div>

    <!-- Student Deliverables Card -->
    <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-border-default pb-3">
            <h2 class="text-base font-bold text-text-primary">1. Student Deliverables & Methodology</h2>
            <div class="text-xs text-text-muted">
                Submitted on {{ $submission->submitted_at->format('M d, Y H:i') }}
                @if($submission->is_late)
                    <span class="text-red-500 font-bold ml-1">(Submitted after deadline)</span>
                @endif
            </div>
        </div>

        @if($submission->website_url)
            <div class="p-3 bg-surface-bg border border-border-default rounded-lg text-xs">
                <span class="text-text-muted font-semibold uppercase">Audited Website URL:</span>
                <a href="{{ $submission->website_url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline font-mono ml-2 font-bold">{{ $submission->website_url }} &nearr;</a>
            </div>
        @endif

        <div>
            <h4 class="text-xs font-semibold text-text-muted uppercase mb-1">Executive Summary & Written Methodology:</h4>
            <div class="text-xs text-text-primary whitespace-pre-line bg-surface-bg p-4 rounded-lg border border-border-default leading-relaxed">
                {{ $submission->content ?: 'No written text provided.' }}
            </div>
        </div>

        @if($submission->student_notes)
            <div class="p-3 bg-surface-bg/50 border border-border-default rounded-lg text-xs text-text-muted">
                <strong>Student Notes:</strong> {{ $submission->student_notes }}
            </div>
        @endif

        <!-- Attached Files -->
        <div>
            <h4 class="text-xs font-semibold text-text-muted uppercase mb-2">Submitted Deliverables & Attachments:</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @forelse($submission->attachments as $att)
                    <div class="flex items-center justify-between p-3 bg-surface-bg border border-border-default rounded-lg text-xs">
                        <div class="flex items-center gap-2 truncate">
                            <svg class="w-4 h-4 text-primary-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <div class="truncate">
                                <div class="font-medium text-text-primary truncate">{{ $att->file_name }}</div>
                                <div class="text-[11px] text-text-muted">{{ $att->getReadableSize() }} &bull; {{ ucfirst(str_replace('_', ' ', $att->attachment_type)) }}</div>
                            </div>
                        </div>
                        <a href="{{ route('projects.attachments.download', ['submission' => $submission->id, 'attachment' => $att->id]) }}" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded font-medium text-xs transition shrink-0 ml-2">
                            Download
                        </a>
                    </div>
                @empty
                    <div class="col-span-full text-xs text-text-muted italic py-2">
                        No files attached to this submission.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Evaluation Form -->
    <form wire:submit="submitEvaluation" class="space-y-6">
        <!-- Rubric Criteria Evaluation Card -->
        @if($submission->project && $submission->project->criteria->isNotEmpty())
            <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-border-default pb-3">
                    <h2 class="text-base font-bold text-text-primary">2. Rubric Evaluation Criteria</h2>
                    <div class="text-xs text-text-muted">
                        Max Project Marks: <strong>{{ number_format($submission->project->max_marks, 0) }}</strong> &bull;
                        Passing Marks: <strong>{{ number_format($submission->project->passing_marks, 0) }}</strong>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach($submission->project->criteria as $criterion)
                        <div class="p-4 bg-surface-bg border border-border-default rounded-lg space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-xs text-text-primary">{{ $criterion->title }}</span>
                                <span class="text-xs text-text-muted font-mono">Max: {{ number_format($criterion->max_marks, 1) }} pts</span>
                            </div>
                            @if($criterion->description)
                                <p class="text-[11px] text-text-muted">{{ $criterion->description }}</p>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-2">
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-muted uppercase mb-1">Awarded Marks</label>
                                    <input wire:model="criteriaRatings.{{ $criterion->id }}.awarded_marks" type="number" step="0.5" min="0" max="{{ $criterion->max_marks }}" class="w-full text-sm rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-[11px] font-semibold text-text-muted uppercase mb-1">Criterion Feedback / Notes (Optional)</label>
                                    <input wire:model="criteriaRatings.{{ $criterion->id }}.feedback" type="text" placeholder="Constructive guidance for this criterion..." class="w-full text-sm rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <!-- Non-rubric direct marks -->
            <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-text-primary border-b border-border-default pb-3">2. Award Marks</h2>
                <div class="max-w-xs">
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Total Marks Awarded (out of {{ number_format($submission->project?->max_marks ?? 100, 0) }})</label>
                    <input wire:model="pointsAwarded" type="number" step="0.5" min="0" max="{{ $submission->project?->max_marks ?? 100 }}" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                </div>
            </div>
        @endif

        <!-- Decision & Structured Feedback Card -->
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-text-primary border-b border-border-default pb-3">3. Evaluation Decision & Detailed Feedback</h2>

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-2">Evaluation Decision *</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="flex items-center gap-2 p-3 rounded-lg border border-border-default bg-surface-bg cursor-pointer hover:border-emerald-500 transition">
                        <input type="radio" wire:model.live="decision" value="approved" class="text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Approve Project</div>
                            <div class="text-[11px] text-text-muted">Passes criteria & issues portfolio achievement</div>
                        </div>
                    </label>

                    <label class="flex items-center gap-2 p-3 rounded-lg border border-border-default bg-surface-bg cursor-pointer hover:border-amber-500 transition">
                        <input type="radio" wire:model.live="decision" value="revision_requested" class="text-amber-600 focus:ring-amber-500">
                        <div>
                            <div class="text-xs font-bold text-amber-600 dark:text-amber-400">Request Revision</div>
                            <div class="text-[11px] text-text-muted">Requires student to address specific flaws</div>
                        </div>
                    </label>

                    <label class="flex items-center gap-2 p-3 rounded-lg border border-border-default bg-surface-bg cursor-pointer hover:border-red-500 transition">
                        <input type="radio" wire:model.live="decision" value="rejected" class="text-red-600 focus:ring-red-500">
                        <div>
                            <div class="text-xs font-bold text-red-600 dark:text-red-400">Reject Work</div>
                            <div class="text-[11px] text-text-muted">Unacceptable / Plagiarized / Unrevisable</div>
                        </div>
                    </label>
                </div>
            </div>

            @if($decision === 'revision_requested')
                <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg space-y-2">
                    <label class="block text-xs font-bold text-amber-800 dark:text-amber-200 uppercase">Reason for Revision Request *</label>
                    <textarea wire:model="revisionReason" rows="3" placeholder="Explain clearly what the student must correct, re-crawl, or resubmit before approval..." class="w-full text-sm rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none" required></textarea>
                    @error('revisionReason') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Overall Trainer Feedback</label>
                <textarea wire:model="trainerFeedback" rows="3" placeholder="Commend strong execution points, campaign viability, and professional polish..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Improvement Recommendations</label>
                <textarea wire:model="improvementRecommendations" rows="2" placeholder="Actionable tips for continued professional growth or agency best practices..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                <button type="submit" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-medium text-sm rounded-lg shadow-xs transition">
                    Commit & Save Evaluation
                </button>
            </div>
        </div>
    </form>
</div>
