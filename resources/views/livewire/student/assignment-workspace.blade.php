<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <div class="flex items-center gap-2 text-xs text-text-secondary mb-1">
                <a href="{{ route('student.courses.learn', $course) }}" class="hover:underline">{{ $course->title }}</a>
                <span>&rsaquo;</span>
                <a href="{{ route('student.assignments.index') }}" class="hover:underline">Assignments</a>
                <span>&rsaquo;</span>
                <span>{{ $assignment->title }}</span>
            </div>
            <h1 class="text-2xl font-bold text-text-primary">{{ $assignment->title }}</h1>
            <p class="text-xs text-text-secondary mt-1">
                Due: {{ $assignment->formatInAcademyTimezone($assignment->due_date) }}
                &bull; Maximum Marks: {{ number_format((float) $assignment->max_marks, 2) }}
                &bull; Passing Threshold: {{ number_format((float) $assignment->passing_marks, 2) }}
            </p>
        </div>
        <a href="{{ route('student.assignments.index') }}" class="btn btn-secondary btn-sm">
            &larr; Back to Assignments
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

    {{-- Status Banner --}}
    @if ($latestSubmission)
        @if ($latestSubmission->status === 'revision_requested')
            @php $revEval = $latestSubmission->evaluations->where('status', 'revision_requested')->first(); @endphp
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-700 dark:text-amber-300 space-y-1.5">
                <div class="flex items-center gap-2 font-bold text-sm">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Revision Requested by Trainer for Version {{ $latestSubmission->version_number }}</span>
                </div>
                <p class="leading-relaxed pl-7">
                    <strong>Trainer Notes:</strong> "{{ $revEval?->revision_reason ?? 'Please revise your deliverables and resubmit.' }}"
                </p>
                <p class="text-[11px] text-amber-600/80 dark:text-amber-400/80 pl-7">
                    You may submit your revised work below. This will create Version {{ $latestSubmission->version_number + 1 }}.
                </p>
            </div>
        @elseif ($latestSubmission->status === 'graded')
            @php $gradeEval = $latestSubmission->evaluations->where('status', 'graded')->first(); @endphp
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-700 dark:text-emerald-300 flex items-center justify-between">
                <div>
                    <div class="font-bold text-sm">Assignment Graded (Version {{ $latestSubmission->version_number }})</div>
                    <p class="mt-0.5">
                        Awarded Score: <strong>{{ number_format((float) $gradeEval?->points_awarded, 2) }} / {{ number_format((float) ($gradeEval?->points_possible ?? $assignment->max_marks), 2) }}</strong>
                        ({{ number_format((float) $gradeEval?->score_percentage, 1) }}%) &bull;
                        {{ $gradeEval?->is_passed ? 'Passing Grade Achieved' : 'Did Not Meet Passing Mark' }}
                    </p>
                </div>
                <span class="badge {{ $gradeEval?->is_passed ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/20 text-rose-700 dark:text-rose-300' }}">
                    {{ $gradeEval?->is_passed ? 'Passed' : 'Failed' }}
                </span>
            </div>
        @else
            <div class="p-4 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-xs text-blue-700 dark:text-blue-300 flex items-center justify-between">
                <div>
                    <span class="font-bold">Version {{ $latestSubmission->version_number }} Submitted</span>
                    <p class="text-[11px] text-blue-600/80 dark:text-blue-400/80 mt-0.5">
                        Your deliverable was submitted on {{ $latestSubmission->submitted_at?->format('M d, Y H:i') }} and is currently queued for trainer review.
                    </p>
                </div>
                <span class="badge bg-blue-500/20 text-blue-700 dark:text-blue-300">Under Review</span>
            </div>
        @endif
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 cols: Instructions & Submission Form --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Assignment Instructions --}}
            <x-card>
                <div class="border-b border-border-default pb-3 mb-4">
                    <h2 class="text-base font-bold text-text-primary">Assignment Brief & Instructions</h2>
                </div>

                @if ($assignment->description)
                    <p class="text-xs text-text-secondary leading-relaxed mb-4">
                        {{ $assignment->description }}
                    </p>
                @endif

                @if ($assignment->instructions)
                    <div class="space-y-2 mb-4">
                        <h4 class="text-xs font-bold text-text-primary">Deliverable Requirements:</h4>
                        <div class="p-4 rounded-xl bg-surface-bg/60 border border-border-default text-xs text-text-primary whitespace-pre-wrap leading-relaxed">
                            {{ $assignment->instructions }}
                        </div>
                    </div>
                @endif

                @if ($assignment->submission_guidelines)
                    <div class="p-3 rounded-xl bg-surface-bg/40 border border-border-default text-xs text-text-secondary space-y-1">
                        <span class="font-bold text-text-primary">Submission Guidelines:</span>
                        <p>{{ $assignment->submission_guidelines }}</p>
                    </div>
                @endif
            </x-card>

            {{-- Submission Form (shown if student can submit) --}}
            @if ($canSubmit || ($latestSubmission && $latestSubmission->status === 'revision_requested'))
                <x-card>
                    <div class="border-b border-border-default pb-3 mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-text-primary">
                                {{ $latestSubmission ? 'Submit Revision (Version ' . ($latestSubmission->version_number + 1) . ')' : 'Submit Your Work (Version 1)' }}
                            </h2>
                            <p class="text-xs text-text-secondary mt-0.5">Upload your practical files and provide written explanations or URLs below.</p>
                        </div>
                    </div>

                    <form wire:submit.prevent="submitWork" class="space-y-4 text-xs">
                        {{-- Written Answer --}}
                        @if ($assignment->assignment_type !== 'file_only')
                            <div>
                                <label class="block font-semibold text-text-primary mb-1">
                                    Written Deliverable / Solution Text
                                    @if ($assignment->assignment_type === 'text_only') * @endif
                                </label>
                                <textarea
                                    wire:model="writtenContent"
                                    rows="6"
                                    class="input w-full text-xs font-mono"
                                    placeholder="Type your response, website URLs, campaign notes, or technical audit findings here..."
                                ></textarea>
                                @error('writtenContent') <span class="text-rose-500 text-[11px] mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        {{-- File Upload --}}
                        @if ($assignment->assignment_type !== 'text_only')
                            <div>
                                <label class="block font-semibold text-text-primary mb-1">
                                    Upload File Attachments
                                    @if ($assignment->assignment_type === 'file_only') * @endif
                                    <span class="text-text-muted font-normal text-[11px]">
                                        (Allowed: {{ implode(', ', $assignment->allowed_file_types ?: ['pdf', 'docx', 'xlsx', 'pptx', 'zip', 'png', 'jpg']) }} &bull; Max {{ $assignment->max_file_size_mb }} MB each)
                                    </span>
                                </label>

                                <input
                                    type="file"
                                    wire:model="uploadedFiles"
                                    {{ $assignment->allow_multiple_files ? 'multiple' : '' }}
                                    class="input w-full text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-primary file:text-white hover:file:opacity-90"
                                >
                                <div wire:loading wire:target="uploadedFiles" class="text-xs text-brand-primary mt-1">
                                    Uploading files, please wait...
                                </div>
                                @error('uploadedFiles.*') <span class="text-rose-500 text-[11px] mt-1 block">{{ $message }}</span> @enderror

                                {{-- Uploaded Files Staging List --}}
                                @if (! empty($uploadedFiles))
                                    <div class="mt-3 space-y-1.5">
                                        <span class="text-[11px] font-semibold text-text-secondary">Selected Files:</span>
                                        @foreach ($uploadedFiles as $index => $file)
                                            <div class="flex items-center justify-between p-2 rounded-lg bg-surface-bg/50 border border-border-default">
                                                <span class="text-xs text-text-primary truncate">{{ $file->getClientOriginalName() }} ({{ round($file->getSize() / 1024, 1) }} KB)</span>
                                                <button type="button" wire:click="removeFile({{ $index }})" class="text-rose-500 hover:text-rose-600 text-xs px-2">
                                                    Remove
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Notes to Trainer --}}
                        <div>
                            <label class="block font-semibold text-text-primary mb-1">
                                Notes to Trainer <span class="text-text-muted font-normal text-[11px]">(Optional)</span>
                            </label>
                            <input
                                type="text"
                                wire:model="studentNotes"
                                class="input w-full text-xs"
                                placeholder="Any specific context or notes you want your trainer to see..."
                            >
                        </div>

                        {{-- Submit Button --}}
                        <div class="pt-3 border-t border-border-default flex items-center justify-between">
                            <span class="text-[11px] text-text-muted">
                                Once submitted, a new immutable version will be created for review.
                            </span>
                            <button
                                type="submit"
                                class="btn btn-primary btn-sm px-6 font-bold inline-flex items-center gap-2"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="submitWork">Submit Deliverable</span>
                                <span wire:loading wire:target="submitWork">Submitting...</span>
                            </button>
                        </div>
                    </form>
                </x-card>
            @endif

            {{-- Submission History Timeline --}}
            @if ($submissions->isNotEmpty())
                <x-card>
                    <div class="border-b border-border-default pb-3 mb-4">
                        <h2 class="text-base font-bold text-text-primary">Submission History & Trainer Feedback</h2>
                    </div>

                    <div class="space-y-6">
                        @foreach ($submissions as $sub)
                            <div class="p-4 rounded-2xl border border-border-default bg-surface-bg/30 space-y-3 text-xs">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border-default/60 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-text-primary">Version {{ $sub->version_number }}</span>
                                        @if ($sub->is_late)
                                            <span class="badge bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 text-[10px]">Submitted Late</span>
                                        @endif
                                        <span class="badge {{ $sub->status === 'graded' ? 'bg-emerald-500/10 text-emerald-600' : ($sub->status === 'revision_requested' ? 'bg-amber-500/10 text-amber-600' : 'bg-blue-500/10 text-blue-600') }}">
                                            {{ ucfirst(str_replace('_', ' ', $sub->status)) }}
                                        </span>
                                    </div>
                                    <span class="text-text-muted">{{ $sub->submitted_at ? $sub->submitted_at->format('M d, Y H:i') : '-' }}</span>
                                </div>

                                {{-- Submitted Text --}}
                                @if (! empty($sub->content))
                                    <div>
                                        <span class="font-semibold text-text-secondary block mb-1">Your Written Answer:</span>
                                        <div class="p-3 rounded-xl bg-surface-card border border-border-default/60 whitespace-pre-wrap font-mono text-xs">
                                            {{ $sub->content }}
                                        </div>
                                    </div>
                                @endif

                                {{-- Submitted Attachments --}}
                                @if ($sub->attachments->isNotEmpty())
                                    <div>
                                        <span class="font-semibold text-text-secondary block mb-1">Your Uploaded Files:</span>
                                        <div class="space-y-1.5">
                                            @foreach ($sub->attachments as $att)
                                                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-card border border-border-default/60">
                                                    <span class="text-text-primary font-medium">{{ $att->file_name }} ({{ $att->formatted_size }})</span>
                                                    <a
                                                        href="{{ route('assignments.attachments.download', ['submission' => $sub->id, 'attachment' => $att->id]) }}"
                                                        class="btn btn-secondary btn-xs"
                                                        target="_blank"
                                                    >
                                                        Download
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Evaluations & Feedback on this version --}}
                                @foreach ($sub->evaluations as $eval)
                                    <div class="mt-3 p-3.5 rounded-xl border {{ $eval->status === 'revision_requested' ? 'bg-amber-500/5 border-amber-500/30' : 'bg-surface-card border-border-default' }} space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-text-primary">
                                                Feedback from {{ $eval->evaluator?->name ?? 'Trainer' }}
                                            </span>
                                            <span class="text-[11px] text-text-muted">{{ $eval->graded_at ? $eval->graded_at->format('M d, Y H:i') : '' }}</span>
                                        </div>

                                        @if ($eval->status === 'revision_requested')
                                            <div class="text-amber-700 dark:text-amber-300 font-semibold">
                                                Revision Requested: <span class="font-normal">{{ $eval->revision_reason }}</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold {{ $eval->is_passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                    Score: {{ number_format((float) $eval->points_awarded, 2) }} / {{ number_format((float) $eval->points_possible, 2) }}
                                                    ({{ number_format((float) $eval->score_percentage, 1) }}%)
                                                </span>
                                                <span class="badge {{ $eval->is_passed ? 'badge-primary' : 'bg-rose-500/10 text-rose-600' }}">
                                                    {{ $eval->is_passed ? 'Passed' : 'Needs Work' }}
                                                </span>
                                            </div>

                                            @if (! empty($eval->overall_feedback))
                                                <p class="text-text-secondary leading-relaxed bg-surface-bg/50 p-2.5 rounded-lg border border-border-default/40">
                                                    {{ $eval->overall_feedback }}
                                                </p>
                                            @endif

                                            {{-- Rubric Breakdown --}}
                                            @if (is_array($eval->rubric_snapshot))
                                                <div class="pt-2 border-t border-border-default/60 space-y-1.5">
                                                    <span class="font-semibold text-text-secondary">Rubric Breakdown:</span>
                                                    @foreach ($eval->rubric_snapshot as $r)
                                                        <div class="flex items-center justify-between text-[11px] p-1.5 rounded bg-surface-bg/40">
                                                            <span class="text-text-primary">{{ $r['title'] ?? 'Criterion' }}</span>
                                                            <span class="font-bold text-text-primary">{{ $r['awarded_marks'] ?? 0 }} / {{ $r['max_marks'] ?? 0 }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Right Col: Grading Rubric & Deadlines Card --}}
        <div class="space-y-6">
            {{-- Deadlines & Policy Card --}}
            <x-card>
                <div class="border-b border-border-default pb-3 mb-3">
                    <h3 class="text-sm font-bold text-text-primary">Deadlines & Rules</h3>
                </div>
                <div class="space-y-2.5 text-xs text-text-secondary">
                    <div class="flex justify-between">
                        <span>Due Date:</span>
                        <span class="font-semibold text-text-primary">{{ $assignment->formatInAcademyTimezone($assignment->due_date) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Late Submissions:</span>
                        <span class="font-semibold text-text-primary">
                            @if ($assignment->late_submission_policy === 'allowed_no_penalty')
                                Permitted (No penalty)
                            @elseif ($assignment->late_submission_policy === 'allowed_penalty_deduction' || $assignment->late_submission_policy === 'deduct_flat_percentage')
                                Permitted ({{ $assignment->late_penalty_percent }}% flat deduction)
                            @elseif ($assignment->late_submission_policy === 'deduct_percentage_daily')
                                Permitted ({{ $assignment->late_penalty_percent }}% per day deduction)
                            @elseif ($assignment->late_submission_policy === 'allowed_until_hard_deadline')
                                Allowed until {{ $assignment->late_hard_deadline ? $assignment->formatInAcademyTimezone($assignment->late_hard_deadline) : 'cutoff' }}
                            @else
                                Strictly not allowed
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Attempts Allowed:</span>
                        <span class="font-semibold text-text-primary">
                            {{ $assignment->max_attempts ? $assignment->max_attempts : 'Unlimited' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Resubmission:</span>
                        <span class="font-semibold text-text-primary">
                            {{ $assignment->allow_resubmission ? 'Allowed upon review' : 'Single attempt only' }}
                        </span>
                    </div>
                </div>
            </x-card>

            {{-- Rubric Criteria Overview --}}
            @if ($assignment->rubricCriteria->isNotEmpty())
                <x-card>
                    <div class="border-b border-border-default pb-3 mb-3">
                        <h3 class="text-sm font-bold text-text-primary">Grading Rubric</h3>
                    </div>
                    <div class="space-y-3">
                        @foreach ($assignment->rubricCriteria as $crit)
                            <div class="p-3 rounded-xl border border-border-default bg-surface-bg/40 space-y-1 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-text-primary">{{ $crit->title }}</span>
                                    <span class="badge badge-primary text-[10px]">{{ number_format((float) $crit->max_marks, 1) }} pts</span>
                                </div>
                                @if ($crit->description)
                                    <p class="text-[11px] text-text-secondary leading-relaxed">{{ $crit->description }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</div>
