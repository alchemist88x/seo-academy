<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <div class="flex items-center gap-2 text-xs text-text-secondary mb-1">
                <a href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.index') : route('trainer.assignments.index') }}" class="hover:underline">Assignments</a>
                <span>&rsaquo;</span>
                <a href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.submissions', $assignment) : route('trainer.assignments.submissions', $assignment) }}" class="hover:underline">{{ $assignment->title }}</a>
                <span>&rsaquo;</span>
                <span>Grading</span>
            </div>
            <h1 class="text-2xl font-bold text-text-primary">Evaluation: {{ $submission->user?->name }} (v{{ $submission->version_number }})</h1>
            <p class="text-xs text-text-secondary mt-1">
                Submitted on {{ $submission->submitted_at ? $submission->submitted_at->format('M d, Y H:i') : '-' }}
                @if ($submission->is_late)
                    <span class="badge bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 text-[10px] ml-1">Late Submission</span>
                @endif
            </p>
        </div>
        <a
            href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.submissions', $assignment) : route('trainer.assignments.submissions', $assignment) }}"
            class="btn btn-secondary btn-sm"
        >
            &larr; Back to Submissions
        </a>
    </div>

    @if ($successMessage)
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center justify-between">
            <span>{{ $successMessage }}</span>
            <button type="button" wire:click="$set('successMessage', null)" class="text-emerald-500 hover:text-emerald-600">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-rose-500 hover:text-rose-600">&times;</button>
        </div>
    @endif

    {{-- Version Switcher (if multiple versions exist) --}}
    @if ($allVersions->count() > 1)
        <x-card>
            <div class="flex items-center gap-3 overflow-x-auto pb-1 text-xs">
                <span class="font-bold text-text-primary whitespace-nowrap">Student Versions:</span>
                @foreach ($allVersions as $v)
                    <button
                        type="button"
                        wire:click="switchVersion('{{ $v->id }}')"
                        class="px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors whitespace-nowrap {{ $selectedSubmissionId === $v->id ? 'bg-brand-primary text-white border-brand-primary' : 'bg-surface-bg border-border-default text-text-secondary hover:text-text-primary' }}"
                    >
                        Version {{ $v->version_number }}
                        @if ($v->is_late) (Late) @endif
                        @if ($v->status === 'graded') &bull; Graded ({{ $v->grade }}) @endif
                    </button>
                @endforeach
            </div>
        </x-card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 cols: Student Deliverable --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Written Submission Content --}}
            <x-card>
                <div class="border-b border-border-default pb-3 mb-4">
                    <h3 class="text-sm font-bold text-text-primary">Written Submission</h3>
                </div>
                @if (! empty($submission->content))
                    <div class="p-4 rounded-xl bg-surface-bg/60 border border-border-default text-xs text-text-primary whitespace-pre-wrap leading-relaxed font-mono">
                        {{ $submission->content }}
                    </div>
                @else
                    <p class="text-xs text-text-muted italic">No written content submitted.</p>
                @endif

                @if (! empty($submission->student_notes))
                    <div class="mt-4 pt-4 border-t border-border-default">
                        <h4 class="text-xs font-semibold text-text-primary mb-1">Student Notes to Trainer:</h4>
                        <p class="text-xs text-text-secondary italic bg-surface-bg/40 p-3 rounded-lg border border-border-default">
                            "{{ $submission->student_notes }}"
                        </p>
                    </div>
                @endif
            </x-card>

            {{-- Attached Files --}}
            <x-card>
                <div class="border-b border-border-default pb-3 mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-text-primary">Submitted Attachments ({{ $submission->attachments->count() }})</h3>
                    <span class="text-xs text-text-muted">Private Storage Vault</span>
                </div>

                @if ($submission->attachments->isNotEmpty())
                    <div class="divide-y divide-border-default/60">
                        @foreach ($submission->attachments as $att)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-surface-bg flex items-center justify-center text-text-muted">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold text-text-primary">{{ $att->file_name }}</div>
                                        <div class="text-[10px] text-text-muted">{{ $att->formatted_size }} &bull; {{ $att->mime_type }}</div>
                                    </div>
                                </div>
                                <a
                                    href="{{ route('assignments.attachments.download', ['submission' => $submission->id, 'attachment' => $att->id]) }}"
                                    class="btn btn-secondary btn-xs inline-flex items-center gap-1.5"
                                    target="_blank"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Download</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-text-muted italic">No files attached to this version.</p>
                @endif
            </x-card>

            {{-- Evaluation History Timeline --}}
            @if ($evaluations->isNotEmpty())
                <x-card>
                    <div class="border-b border-border-default pb-3 mb-4">
                        <h3 class="text-sm font-bold text-text-primary">Evaluation History ({{ $evaluations->count() }})</h3>
                    </div>

                    <div class="space-y-4">
                        @foreach ($evaluations as $hist)
                            <div class="p-3.5 rounded-xl border border-border-default bg-surface-bg/40 space-y-2 text-xs">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-text-primary">
                                        {{ $hist->evaluator?->name ?? 'Evaluator' }}
                                        @if ($hist->is_override)
                                            <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-[10px] ml-1">Admin Override</span>
                                        @endif
                                    </span>
                                    <span class="text-text-muted">{{ $hist->graded_at ? $hist->graded_at->format('M d, Y H:i') : '-' }}</span>
                                </div>

                                @if ($hist->status === 'revision_requested')
                                    <div class="text-amber-600 dark:text-amber-400 font-semibold">
                                        Revision Requested: <span class="font-normal text-text-secondary">{{ $hist->revision_reason }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold {{ $hist->is_passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            Awarded: {{ number_format((float) $hist->points_awarded, 2) }} / {{ number_format((float) $hist->points_possible, 2) }}
                                            ({{ number_format((float) $hist->score_percentage, 1) }}%)
                                        </span>
                                        <span class="badge {{ $hist->is_passed ? 'badge-primary' : 'bg-rose-500/10 text-rose-600 border border-rose-500/20' }}">
                                            {{ $hist->is_passed ? 'Passed' : 'Failed' }}
                                        </span>
                                    </div>
                                    @if (! empty($hist->overall_feedback))
                                        <div class="text-text-secondary text-xs bg-surface-card p-2.5 rounded-lg border border-border-default/60">
                                            {{ $hist->overall_feedback }}
                                        </div>
                                    @endif
                                @endif

                                @if (! empty($hist->internal_notes) && auth()->user()?->hasAnyRole(['admin', 'trainer']))
                                    <div class="text-[11px] text-text-muted bg-surface-card/60 p-2 rounded border border-border-default/40">
                                        <span class="font-bold">Internal Note (Hidden from Student):</span> {{ $hist->internal_notes }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Right Col: Evaluation / Grading Form --}}
        <div class="space-y-6">
            <x-card>
                <div class="border-b border-border-default pb-3 mb-4">
                    <h3 class="text-sm font-bold text-text-primary">Grading & Feedback</h3>
                    <p class="text-[11px] text-text-secondary mt-0.5">Max Marks: {{ number_format((float) $assignment->max_marks, 2) }} | Pass Mark: {{ number_format((float) $assignment->passing_marks, 2) }}</p>
                </div>

                <div class="space-y-4 text-xs">
                    {{-- Rubric criteria scoring (if criteria exist) --}}
                    @if ($assignment->rubricCriteria->isNotEmpty())
                        <div class="space-y-3">
                            <h4 class="font-bold text-text-primary">Rubric Scoring:</h4>
                            @foreach ($assignment->rubricCriteria as $crit)
                                <div class="p-3 rounded-xl border border-border-default bg-surface-bg/30 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-text-primary">{{ $crit->title }}</span>
                                        <span class="text-text-muted">/ {{ number_format((float) $crit->max_marks, 2) }}</span>
                                    </div>
                                    @if ($crit->description)
                                        <p class="text-[11px] text-text-muted">{{ $crit->description }}</p>
                                    @endif
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="{{ (float) $crit->max_marks }}"
                                        wire:model.live.debounce.300ms="rubricEvaluations.{{ $crit->id }}.awarded_marks"
                                        class="input w-full text-xs"
                                        placeholder="Marks (0 - {{ $crit->max_marks }})"
                                    >
                                    <input
                                        type="text"
                                        wire:model="rubricEvaluations.{{ $crit->id }}.feedback"
                                        class="input w-full text-xs"
                                        placeholder="Criterion feedback (optional)"
                                    >
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Total Marks Awarded --}}
                    <div>
                        <label class="block font-semibold text-text-primary mb-1">Total Marks Awarded *</label>
                        <input
                            type="number"
                            step="0.5"
                            min="0"
                            max="{{ (float) $assignment->max_marks }}"
                            wire:model="pointsAwarded"
                            class="input w-full text-sm font-bold"
                            {{ $assignment->rubricCriteria->isNotEmpty() ? 'readonly' : '' }}
                        >
                        @error('pointsAwarded') <span class="text-rose-500 text-[11px] mt-1 block">{{ $message }}</span> @enderror
                        @if ($submission->is_late && $assignment->late_submission_policy === 'allowed_penalty_deduction')
                            <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                                Note: A late penalty of {{ $assignment->late_penalty_percent }}% will be deducted automatically from this score.
                            </p>
                        @endif
                    </div>

                    {{-- Overall Feedback --}}
                    <div>
                        <label class="block font-semibold text-text-primary mb-1">Overall Student Feedback</label>
                        <textarea
                            wire:model="overallFeedback"
                            rows="4"
                            class="input w-full text-xs"
                            placeholder="Provide constructive feedback, strengths, and areas to improve..."
                        ></textarea>
                    </div>

                    {{-- Internal Trainer Notes --}}
                    <div>
                        <label class="block font-semibold text-text-primary mb-1">
                            Internal Trainer Notes
                            <span class="text-text-muted font-normal text-[10px]">(Strictly hidden from student)</span>
                        </label>
                        <textarea
                            wire:model="internalNotes"
                            rows="2"
                            class="input w-full text-xs"
                            placeholder="Notes for yourself or co-trainers..."
                        ></textarea>
                    </div>

                    {{-- Admin Override Option --}}
                    @if (auth()->user()?->hasRole('admin'))
                        <div class="pt-2 border-t border-border-default space-y-2">
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-amber-600 dark:text-amber-400 cursor-pointer">
                                <input type="checkbox" wire:model.live="isOverride" class="rounded border-border-default text-amber-500">
                                <span>Administrative Grade Override</span>
                            </label>
                            @if ($isOverride)
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-primary mb-1">Audit Explanation for Override *</label>
                                    <input
                                        type="text"
                                        wire:model="overrideReason"
                                        class="input w-full text-xs"
                                        placeholder="State reason for overriding previous evaluation..."
                                    >
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="pt-4 border-t border-border-default space-y-2">
                        <button
                            type="button"
                            wire:click="submitGrade"
                            class="btn btn-primary w-full text-xs font-bold py-2.5"
                        >
                            {{ $submission->latestEvaluation ? 'Save Regraded Score' : 'Submit Grade & Evaluation' }}
                        </button>

                        <button
                            type="button"
                            wire:click="openRevisionModal"
                            class="btn btn-secondary w-full text-xs py-2"
                        >
                            Request Student Revision
                        </button>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Revision Request Modal --}}
    @if ($showRevisionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-surface-card rounded-2xl border border-border-default max-w-md w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-base font-bold text-text-primary">Request Assignment Revision</h3>
                    <button type="button" wire:click="$set('showRevisionModal', false)" class="text-text-muted hover:text-text-primary">
                        &times;
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="text-text-secondary leading-relaxed">
                        Requesting a revision unlocks the assignment workspace for the student to submit version {{ $submission->version_number + 1 }}.
                    </p>

                    <div>
                        <label class="block font-semibold text-text-primary mb-1">Revision Instructions / Reason *</label>
                        <textarea
                            wire:model="revisionReason"
                            rows="4"
                            class="input w-full text-xs"
                            placeholder="Explain exactly what the student needs to fix or resubmit..."
                        ></textarea>
                        @error('revisionReason') <span class="text-rose-500 text-[11px] mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="$set('showRevisionModal', false)" class="btn btn-secondary btn-xs">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitRevisionRequest" class="btn btn-primary btn-xs font-bold">
                        Send Revision Request
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
