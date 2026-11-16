<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('admin.assessments.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1 mb-2">
                &larr; Back to Assessments
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Grading: {{ $assessment->title }}</h1>
            <p class="text-sm text-gray-600">Review student attempts and grade open-ended questions.</p>
        </div>
    </div>

    @if($feedbackMessage)
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            {{ $feedbackMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Submissions List -->
        <div class="bg-surface-card rounded-lg shadow overflow-hidden lg:col-span-1 border border-border-default">
            <div class="p-4 border-b border-border-default bg-surface-bg">
                <h3 class="font-semibold text-text-primary">Student Attempts</h3>
            </div>
            <div class="divide-y divide-border-default max-h-[600px] overflow-y-auto">
                @forelse($attempts as $attempt)
                    <button wire:click="selectAttempt('{{ $attempt->id }}')"
                            class="w-full text-left p-4 hover:bg-surface-bg transition {{ $selectedAttempt?->id === $attempt->id ? 'bg-primary-50 dark:bg-primary-950/40 border-l-4 border-primary-600' : '' }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-text-primary text-sm">{{ $attempt->student?->name ?? 'Unknown Student' }}</p>
                                <p class="text-xs text-text-muted">Attempt #{{ $attempt->attempt_number }} &bull; {{ $attempt->submitted_at?->diffForHumans() ?? 'In progress' }}</p>
                            </div>
                            <div>
                                @if($attempt->status === 'submitted' && $attempt->requires_manual_grading)
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300 font-medium">Needs Grading</span>
                                @elseif($attempt->is_passed)
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300 font-medium">Passed ({{ $attempt->score_percentage }}%)</span>
                                @elseif($attempt->status === 'evaluated')
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300 font-medium">Failed ({{ $attempt->score_percentage }}%)</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 dark:bg-slate-800 text-text-secondary font-medium">{{ ucfirst($attempt->status) }}</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="p-6 text-center text-text-muted text-sm">
                        No attempts found for this assessment.
                    </div>
                @endforelse
            </div>
            @if($attempts->hasPages())
                <div class="p-4 border-t border-border-default">
                    {{ $attempts->links() }}
                </div>
            @endif
        </div>

        <!-- Attempt Review and Grading Panel -->
        <div class="bg-surface-card rounded-lg shadow p-6 lg:col-span-2 border border-border-default">
            @if($selectedAttempt)
                <div class="border-b border-border-default pb-4 mb-6 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-text-primary">Attempt Details</h2>
                        <p class="text-sm text-text-secondary">
                            Student: <span class="font-medium text-text-primary">{{ $selectedAttempt->student?->name }}</span> ({{ $selectedAttempt->student?->email }})
                        </p>
                        <p class="text-xs text-text-muted mt-1">
                            Started: {{ $selectedAttempt->started_at?->format('M j, Y H:i') }} &bull; 
                            Submitted: {{ $selectedAttempt->submitted_at?->format('M j, Y H:i') ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold {{ $selectedAttempt->is_passed ? 'text-green-600 dark:text-green-400' : 'text-text-primary' }}">
                            {{ $selectedAttempt->score_awarded ?? 0 }} / {{ $selectedAttempt->score_possible }}
                        </div>
                        <p class="text-xs text-text-muted">{{ $selectedAttempt->score_percentage }}% (Pass mark: {{ $assessment->passing_percentage }}%)</p>
                    </div>
                </div>

                <div class="space-y-6">
                    @foreach($selectedAttempt->answers as $index => $answer)
                        <div class="border border-border-default rounded-lg p-4 bg-surface-bg">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">
                                    Question {{ $index + 1 }} &bull; {{ str_replace('_', ' ', $answer->getQuestionType()) }}
                                </span>
                                <span class="text-xs text-text-secondary font-medium">
                                    Max points: {{ $answer->getPointsPossible() }}
                                </span>
                            </div>

                            <p class="text-sm font-semibold text-text-primary mb-3">
                                {{ $answer->getQuestionText() }}
                            </p>

                            @if($answer->getRubric())
                                <div class="mb-3 p-2 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded text-xs text-primary-900 dark:text-primary-200">
                                    <strong>Grading Rubric:</strong> {{ $answer->getRubric() }}
                                </div>
                            @endif

                            <div class="bg-surface-card p-3 rounded border border-border-default mb-4">
                                <span class="block text-xs font-medium text-text-muted mb-1">Student Answer:</span>
                                @if(is_array($answer->student_answer))
                                    <ul class="list-disc list-inside text-sm text-text-primary">
                                        @foreach($answer->student_answer as $val)
                                            <li>{{ $val }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-sm text-text-primary whitespace-pre-wrap">{{ $answer->student_answer ?? 'No answer provided' }}</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label text-xs">Points Awarded</label>
                                    <input type="number" 
                                           step="0.5" 
                                           min="0" 
                                           max="{{ $answer->getPointsPossible() }}" 
                                           wire:model.defer="manualGrades.{{ $answer->id }}.points_awarded"
                                           class="form-input text-sm">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Item Feedback</label>
                                    <input type="text" 
                                           wire:model.defer="manualGrades.{{ $answer->id }}.feedback"
                                           placeholder="Optional feedback on this question"
                                           class="form-input text-sm">
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="border-t border-border-default pt-4">
                        <label class="form-label">Overall Attempt Feedback</label>
                        <textarea wire:model.defer="attemptFeedback" 
                                  rows="3" 
                                  placeholder="Feedback visible to the student upon review..."
                                  class="form-input text-sm"></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button wire:click="saveGrading" 
                                class="btn btn-primary">
                            Save Evaluation & Finalize
                        </button>
                    </div>
                </div>
            @else
                <div class="h-64 flex flex-col items-center justify-center text-text-muted">
                    <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-sm">Select an attempt from the list to review and grade.</p>
                </div>
            @endif
        </div>
    </div>
</div>

