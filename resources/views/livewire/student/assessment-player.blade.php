<div class="space-y-6 max-w-4xl mx-auto py-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <div class="flex items-center gap-2 text-xs text-text-muted mb-1">
                <a href="{{ route('student.courses.learn', $course) }}" class="hover:text-text-primary transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Classroom
                </a>
                <span>&bull;</span>
                <span>{{ $course->title }}</span>
            </div>
            <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                <span>{{ $assessment->title }}</span>
                @if ($assessment->isRequired())
                    <span class="badge badge-primary text-xs">Required</span>
                @else
                    <span class="badge badge-neutral text-xs">Optional</span>
                @endif
            </h1>
        </div>
    </div>

    @if ($errorMessage)
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800 text-sm">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Expired Attempt Notice --}}
    @if ($isExpired)
        <div class="p-5 rounded-2xl border border-amber-300 bg-amber-50/80 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 flex items-start gap-3">
            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h3 class="text-sm font-bold">Examination Time Limit Expired</h3>
                <p class="text-xs text-amber-800 dark:text-amber-300 mt-1">
                    The allotted time window for this examination has ended. Your answers have been automatically collected and finalized.
                </p>
            </div>
        </div>
    @endif

    {{-- Submission Result Banner --}}
    @if ($submissionResult)
        <div class="p-6 rounded-2xl border {{ $submissionResult['requires_manual_grading'] ? 'border-amber-300 bg-amber-50/70 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200' : ($submissionResult['passed'] ? 'border-emerald-300 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200' : 'border-rose-300 bg-rose-50/70 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200') }} space-y-2">
            <div class="flex items-center gap-3">
                @if ($submissionResult['requires_manual_grading'])
                    <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/60 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold">Attempt Submitted • Pending Instructor Evaluation</h3>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                            Your open-ended responses have been recorded and sent to your instructor for evaluation. Final score will be updated upon completion.
                        </p>
                    </div>
                @elseif ($submissionResult['passed'])
                    <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold">Assessment Passed! ({{ $submissionResult['score_percentage'] }}%)</h3>
                        <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-0.5">
                            Congratulations! You achieved {{ $submissionResult['earned_points'] }} / {{ $submissionResult['total_points'] }} points and met the passing requirement.
                        </p>
                    </div>
                @else
                    <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/60 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold">Assessment Not Passed ({{ $submissionResult['score_percentage'] }}%)</h3>
                        <p class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">
                            You scored {{ $submissionResult['earned_points'] }} / {{ $submissionResult['total_points'] }} points. Passing score required: {{ $assessmentData['passing_percentage'] }}%.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- In-Progress Attempt Form --}}
    @if ($currentAttempt)
        <div
            class="space-y-6"
            x-data="{
                remaining: @entangle('remainingSeconds'),
                init() {
                    setInterval(() => {
                        if (this.remaining !== null && this.remaining > 0) {
                            this.remaining--;
                            if (this.remaining === 0) {
                                $wire.checkTimer();
                            }
                        }
                    }, 1000);
                },
                formatTime(sec) {
                    if (sec === null || sec === undefined) return '';
                    if (sec <= 0) return '00:00';
                    const h = Math.floor(sec / 3600);
                    const m = Math.floor((sec % 3600) / 60);
                    const s = sec % 60;
                    if (h > 0) {
                        return `${h}:${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
                    }
                    return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
                }
            }"
            wire:poll.15s="checkTimer"
        >
            <x-card>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-border-default gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-text-muted uppercase">
                            Attempt #{{ $currentAttempt->attempt_number }} in Progress
                        </span>
                        {{-- Autosave status indicator --}}
                        @if ($saveStatus === 'saving')
                            <span class="inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                                <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Saving...</span>
                            </span>
                        @elseif ($saveStatus === 'saved')
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>All answers saved</span>
                            </span>
                        @elseif ($saveStatus === 'error')
                            <span class="inline-flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400 font-medium">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Error saving</span>
                            </span>
                        @endif
                    </div>

                    {{-- Timer badge --}}
                    @if ($remainingSeconds !== null)
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border-default bg-surface-bg/70 text-xs font-mono font-semibold" :class="remaining <= 300 ? 'text-rose-600 dark:text-rose-400 border-rose-300 dark:border-rose-800 bg-rose-50/50' : 'text-text-primary'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Time Remaining:</span>
                            <span x-text="formatTime(remaining)"></span>
                        </div>
                    @endif
                </div>

                <div class="space-y-8 pt-4">
                    @foreach ($assessmentData['questions'] as $index => $q)
                        <div class="space-y-3 pb-6 border-b border-border-default/60 last:border-0">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-sm font-semibold text-text-primary">
                                    <span class="text-primary-600 mr-1">{{ $index + 1 }}.</span>
                                    {{ $q['question_text'] }}
                                </h3>
                                <span class="badge badge-neutral text-xs shrink-0">
                                    {{ $q['points'] }} {{ \Illuminate\Support\Str::plural('point', $q['points']) }}
                                </span>
                            </div>

                            {{-- Single Choice & True/False --}}
                            @if ($q['question_type'] === 'single_choice' || $q['question_type'] === 'true_false')
                                <div class="space-y-2 pt-1">
                                    @foreach ($q['options'] ?? [] as $opt)
                                        @php
                                            $optKey = is_array($opt) ? ($opt['key'] ?? '') : $opt;
                                            $optText = is_array($opt) ? ($opt['text'] ?? $optKey) : $opt;
                                        @endphp
                                        <label class="flex items-center gap-3 p-3 rounded-lg border border-border-default hover:bg-surface-bg/60 cursor-pointer transition-colors">
                                            <input
                                                type="radio"
                                                wire:model.live="answers.{{ $q['id'] }}"
                                                value="{{ $optKey }}"
                                                name="question_{{ $q['id'] }}"
                                                class="text-primary-600 focus:ring-primary-500"
                                            >
                                            <span class="text-sm text-text-primary">{{ $optText }}</span>
                                        </label>
                                    @endforeach
                                </div>

                            {{-- Multiple Choice (Checkboxes) --}}
                            @elseif ($q['question_type'] === 'multiple_choice')
                                <div class="space-y-2 pt-1">
                                    @foreach ($q['options'] ?? [] as $opt)
                                        @php
                                            $optKey = is_array($opt) ? ($opt['key'] ?? '') : $opt;
                                            $optText = is_array($opt) ? ($opt['text'] ?? $optKey) : $opt;
                                        @endphp
                                        <label class="flex items-center gap-3 p-3 rounded-lg border border-border-default hover:bg-surface-bg/60 cursor-pointer transition-colors">
                                            <input
                                                type="checkbox"
                                                wire:model.live="answers.{{ $q['id'] }}"
                                                value="{{ $optKey }}"
                                                class="rounded text-primary-600 focus:ring-primary-500"
                                            >
                                            <span class="text-sm text-text-primary">{{ $optText }}</span>
                                        </label>
                                    @endforeach
                                </div>

                            {{-- Short Answer --}}
                            @elseif ($q['question_type'] === 'short_answer')
                                <div class="pt-1">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.750ms="answers.{{ $q['id'] }}"
                                        placeholder="Type your concise answer..."
                                        class="input w-full"
                                    >
                                </div>

                            {{-- Long Answer --}}
                            @elseif ($q['question_type'] === 'long_answer')
                                <div class="pt-1">
                                    <textarea
                                        wire:model.live.debounce.750ms="answers.{{ $q['id'] }}"
                                        rows="4"
                                        placeholder="Type your detailed response..."
                                        class="input w-full"
                                    ></textarea>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="pt-6 border-t border-border-default flex items-center justify-between">
                    <span class="text-xs text-text-muted">
                        Answers are automatically saved. Submissions are final.
                    </span>
                    <button
                        type="button"
                        wire:click="submitAttempt"
                        wire:loading.attr="disabled"
                        class="btn btn-primary btn-md inline-flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="submitAttempt">Submit Assessment</span>
                        <span wire:loading wire:target="submitAttempt">Submitting & Grading...</span>
                    </button>
                </div>
            </x-card>
        </div>

    {{-- Overview / Start Attempt Screen --}}
    @else
        <div class="space-y-6">
            <x-card>
                <div class="space-y-4">
                    @if ($assessment->description)
                        <p class="text-sm text-text-secondary leading-relaxed">
                            {{ $assessment->description }}
                        </p>
                    @endif

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-surface-bg/50 border border-border-default">
                        <div>
                            <div class="text-xs text-text-muted">Passing Score</div>
                            <div class="text-lg font-bold text-text-primary">{{ $assessmentData['passing_percentage'] }}%</div>
                        </div>
                        <div>
                            <div class="text-xs text-text-muted">Total Questions</div>
                            <div class="text-lg font-bold text-text-primary">{{ $assessmentData['total_questions'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-text-muted">Attempt Limit</div>
                            <div class="text-lg font-bold text-text-primary">
                                {{ $assessmentData['attempt_limit'] ?? 'Unlimited' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-text-muted">Remaining Attempts</div>
                            <div class="text-lg font-bold {{ $assessmentData['remaining_attempts'] === 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $assessmentData['remaining_attempts'] ?? 'Unlimited' }}
                            </div>
                        </div>
                    </div>

                    @if ($assessmentData['has_passed'])
                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>You have already passed this assessment!</span>
                        </div>
                    @endif

                    <div class="pt-4 flex items-center justify-between">
                        <div>
                            @if ($assessmentData['remaining_attempts'] === 0)
                                <span class="text-xs text-rose-600 font-medium">No attempts remaining for this assessment.</span>
                            @endif
                        </div>

                        @if ($assessmentData['remaining_attempts'] === null || $assessmentData['remaining_attempts'] > 0)
                            <button
                                type="button"
                                wire:click="startAttempt"
                                wire:loading.attr="disabled"
                                class="btn btn-primary btn-md inline-flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Start Assessment Attempt</span>
                            </button>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- Previous Attempts Table --}}
            @if (! empty($assessmentData['completed_attempts']) && count($assessmentData['completed_attempts']) > 0)
                <x-card>
                    <h3 class="text-sm font-bold text-text-primary mb-3">Attempt History</h3>
                    <div class="overflow-x-auto">
                        <table class="table w-full text-xs">
                            <thead>
                                <tr class="text-left text-text-muted border-b border-border-default">
                                    <th class="py-2">Attempt</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Score</th>
                                    <th class="py-2">Result</th>
                                    <th class="py-2">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-default/60">
                                @foreach ($assessmentData['completed_attempts'] as $att)
                                    <tr>
                                        <td class="py-2 font-medium text-text-primary">#{{ $att['attempt_number'] }}</td>
                                        <td class="py-2 capitalize">{{ str_replace('_', ' ', $att['status']) }}</td>
                                        <td class="py-2 font-bold">{{ $att['score_percentage'] }}%</td>
                                        <td class="py-2">
                                            @if ($att['requires_manual_grading'])
                                                <span class="badge badge-warning text-[10px]">Pending Evaluation</span>
                                            @elseif ($att['passed'])
                                                <span class="badge badge-success text-[10px]">Passed</span>
                                            @else
                                                <span class="badge badge-danger text-[10px]">Failed</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-text-muted">
                                            {{ $att['submitted_at'] ? \Illuminate\Support\Carbon::parse($att['submitted_at'])->format('M j, Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>
    @endif
</div>
