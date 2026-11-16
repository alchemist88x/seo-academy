<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <h1 class="text-2xl font-bold text-text-primary">Assessments & Examinations</h1>
            <p class="text-xs text-text-secondary mt-1">Manage quizzes, exams, passing scores, attempt limits, question pools, and manual evaluation.</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="btn btn-primary btn-sm inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Create Assessment</span>
        </button>
    </div>

    {{-- Filter bar --}}
    <x-card>
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search assessments by title..."
                class="input w-full sm:w-80"
            >
            <select wire:model.live="statusFilter" class="input w-full sm:w-48">
                <option value="all">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Draft">Draft</option>
            </select>
        </div>
    </x-card>

    {{-- Assessments Table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table w-full text-xs">
                <thead>
                    <tr class="text-left text-text-muted border-b border-border-default">
                        <th class="py-3 px-4">Title</th>
                        <th class="py-3 px-4">Course</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Question Sources</th>
                        <th class="py-3 px-4">Pass Mark</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Pending Grading</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default/60">
                    @forelse ($assessments as $quiz)
                        <tr class="hover:bg-surface-bg/50 transition-colors">
                            <td class="py-3 px-4 font-bold text-text-primary">
                                {{ $quiz->title }}
                                @if ($quiz->is_required)
                                    <span class="badge badge-primary text-[10px] ml-1">Required</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-text-secondary">
                                {{ $quiz->course?->title ?? '-' }}
                            </td>
                            <td class="py-3 px-4 capitalize">
                                {{ $quiz->type }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($quiz->questions->count() > 0)
                                        <span class="badge badge-neutral text-[10px]" title="Directly attached questions">
                                            {{ $quiz->questions->count() }} Direct
                                        </span>
                                    @endif

                                    @if ($quiz->pools->count() > 0)
                                        <span class="badge badge-primary text-[10px]" title="Configured question pools">
                                            {{ $quiz->pools->sum('question_count') }} from {{ $quiz->pools->count() }} Pool{{ $quiz->pools->count() > 1 ? 's' : '' }}
                                        </span>
                                    @endif

                                    @if ($quiz->questions->count() === 0 && $quiz->pools->count() === 0)
                                        <span class="text-text-muted">None configured</span>
                                    @endif

                                    @if ($quiz->randomize_questions)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400" title="Random selection from pools enabled">
                                            Randomized
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 font-bold">
                                {{ $quiz->getPassingScore() }}%
                            </td>
                            <td class="py-3 px-4">
                                <button
                                    type="button"
                                    wire:click="toggleStatus('{{ $quiz->id }}')"
                                    class="badge {{ $quiz->status === 'Active' ? 'badge-success' : 'badge-neutral' }} cursor-pointer"
                                    title="Click to toggle status"
                                >
                                    {{ $quiz->status }}
                                </button>
                            </td>
                            <td class="py-3 px-4">
                                @php $pending = $pendingGradingCounts[$quiz->id] ?? 0; @endphp
                                @if ($pending > 0)
                                    <a
                                        href="{{ auth()->user()?->hasRole('admin') ? route('admin.assessments.grading', $quiz) : route('trainer.assessments.grading', $quiz) }}"
                                        class="badge badge-warning text-[10px] inline-flex items-center gap-1 font-bold hover:underline"
                                    >
                                        <span>{{ $pending }} Pending</span>
                                    </a>
                                @else
                                    <span class="text-text-muted">0</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-1.5">
                                <button
                                    type="button"
                                    wire:click="openPoolsModal('{{ $quiz->id }}')"
                                    class="btn btn-secondary btn-xs"
                                    title="Manage question banks and pools for this assessment"
                                >
                                    Question Pools
                                </button>
                                <a
                                    href="{{ auth()->user()?->hasRole('admin') ? route('admin.assessments.grading', $quiz) : route('trainer.assessments.grading', $quiz) }}"
                                    class="btn btn-secondary btn-xs"
                                >
                                    Grading
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-text-muted">
                                No assessments created yet. Click "Create Assessment" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-4">
            {{ $assessments->links() }}
        </div>
    </x-card>

    {{-- Create Assessment Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-surface-card rounded-2xl border border-border-default max-w-lg w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">Create New Assessment</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-text-muted hover:text-text-primary">
                        &times;
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-text-primary mb-1">Title *</label>
                        <input type="text" wire:model="newTitle" class="input w-full" placeholder="e.g. SEO Fundamentals Final Exam">
                        @error('newTitle') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary mb-1">Course *</label>
                        <select wire:model="newCourseId" class="input w-full">
                            <option value="">Select Course</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->title }}</option>
                            @endforeach
                        </select>
                        @error('newCourseId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Type</label>
                            <select wire:model="newType" class="input w-full">
                                <option value="quiz">Quiz</option>
                                <option value="exam">Examination</option>
                                <option value="assignment">Assignment</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Pass Score (%) *</label>
                            <input type="number" wire:model="newPassingPercentage" class="input w-full" min="1" max="100">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Attempt Limit</label>
                            <input type="number" wire:model="newAttemptLimit" class="input w-full" placeholder="Empty for unlimited">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Time Limit (mins)</label>
                            <input type="number" wire:model="newTimeLimit" class="input w-full" placeholder="Empty for no limit">
                        </div>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-border-default">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="newIsRequired" class="rounded text-primary-600">
                            <span class="text-xs font-semibold text-text-primary">Required for Course Completion</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="newRandomizeQuestions" class="rounded text-primary-600">
                            <span class="text-xs text-text-secondary">Randomly select questions from pools on each attempt</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="newRandomizeOrder" class="rounded text-primary-600">
                            <span class="text-xs text-text-secondary">Randomize presentation order of questions</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 border-t border-border-default flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                    <button type="button" wire:click="createAssessment" class="btn btn-primary btn-sm">Save Assessment</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Question Pools Configuration Modal --}}
    @if ($showPoolModal && $managingQuiz)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-surface-card rounded-2xl border border-border-default max-w-2xl w-full p-6 space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <div>
                        <h3 class="text-lg font-bold text-text-primary">Question Pools Configuration</h3>
                        <p class="text-xs text-text-secondary">{{ $managingQuiz->title }} &bull; {{ $managingQuiz->course?->title }}</p>
                    </div>
                    <button type="button" wire:click="$set('showPoolModal', false)" class="text-text-muted hover:text-text-primary">
                        &times;
                    </button>
                </div>

                @if ($poolErrorMessage)
                    <div class="p-3 rounded-lg border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200 text-xs">
                        {{ $poolErrorMessage }}
                    </div>
                @endif

                @if ($poolSuccessMessage)
                    <div class="p-3 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 text-xs">
                        {{ $poolSuccessMessage }}
                    </div>
                @endif

                {{-- Randomization Toggles --}}
                <div class="p-3 rounded-xl border border-border-default bg-surface-bg/40 space-y-2 text-xs">
                    <span class="font-bold text-text-primary">Assessment Question Selection Behavior:</span>
                    <div class="flex flex-col sm:flex-row gap-4 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:click="toggleQuizRandomization('randomize_questions')"
                                {{ $managingQuiz->randomize_questions ? 'checked' : '' }}
                                class="rounded text-primary-600"
                            >
                            <span class="text-text-primary">Random selection from pools</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:click="toggleQuizRandomization('randomize_order')"
                                {{ $managingQuiz->randomize_order ? 'checked' : '' }}
                                class="rounded text-primary-600"
                            >
                            <span class="text-text-primary">Shuffle question order</span>
                        </label>
                    </div>
                </div>

                {{-- Existing Pools List --}}
                <div class="space-y-2">
                    <h4 class="font-bold text-xs text-text-primary uppercase tracking-wider">Active Question Pools</h4>
                    @forelse ($managingQuiz->pools as $p)
                        <div class="flex items-center justify-between p-3 rounded-lg border border-border-default bg-surface-card text-xs">
                            <div class="space-y-1">
                                <span class="font-bold text-text-primary">{{ $p->questionBank?->name ?? 'Unknown Bank' }}</span>
                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-text-muted">
                                    <span class="font-semibold text-primary-600">{{ $p->question_count }} Questions</span>
                                    @if ($p->difficulty)
                                        <span>&bull;</span>
                                        <span class="capitalize">Difficulty: {{ $p->difficulty }}</span>
                                    @endif
                                    @if ($p->category)
                                        <span>&bull;</span>
                                        <span>Category: {{ $p->category }}</span>
                                    @endif
                                    @if ($p->question_type)
                                        <span>&bull;</span>
                                        <span class="capitalize">{{ str_replace('_', ' ', $p->question_type) }}</span>
                                    @endif
                                    @if ($p->points_per_question)
                                        <span>&bull;</span>
                                        <span>{{ $p->points_per_question }} pts/question</span>
                                    @endif
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="removePool('{{ $p->id }}')"
                                wire:confirm="Remove this question pool from the assessment?"
                                class="text-rose-600 hover:text-rose-800 text-xs font-semibold px-2 py-1 rounded"
                            >
                                Remove
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-text-muted py-2">No question pools attached yet. Use the form below to attach question banks.</p>
                    @endforelse
                </div>

                {{-- Add Pool Form --}}
                <div class="pt-3 border-t border-border-default space-y-3">
                    <h4 class="font-bold text-xs text-text-primary uppercase tracking-wider">Add Pool From Question Bank</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Select Question Bank *</label>
                            <select wire:model="poolBankId" class="input w-full">
                                <option value="">Choose Bank...</option>
                                @foreach ($banks as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Questions To Draw *</label>
                            <input type="number" wire:model="poolQuestionCount" min="1" class="input w-full">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Difficulty Filter</label>
                            <select wire:model="poolDifficulty" class="input w-full">
                                <option value="">Any Difficulty</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Question Type Filter</label>
                            <select wire:model="poolQuestionType" class="input w-full">
                                <option value="">Any Type</option>
                                <option value="single_choice">Single Choice</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True / False</option>
                                <option value="short_answer">Short Answer</option>
                                <option value="long_answer">Long Answer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Category Filter</label>
                            <input type="text" wire:model="poolCategory" class="input w-full" placeholder="Optional category">
                        </div>
                    </div>

                    <div>
                        <button type="button" wire:click="addPool" class="btn btn-primary btn-sm">
                            + Add Question Pool
                        </button>
                    </div>
                </div>

                <div class="pt-4 border-t border-border-default flex items-center justify-end">
                    <button type="button" wire:click="$set('showPoolModal', false)" class="btn btn-secondary btn-sm">
                        Done
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
