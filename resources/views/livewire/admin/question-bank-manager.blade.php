<div class="space-y-6">
    {{-- Feedback Alerts --}}
    @if ($successMessage)
        <div class="p-4 rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-sm font-medium">{{ $successMessage }}</span>
            </div>
            <button type="button" wire:click="clearMessages" class="text-emerald-700 dark:text-emerald-300 hover:opacity-75">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ $errorMessage }}</span>
            </div>
            <button type="button" wire:click="clearMessages" class="text-rose-700 dark:text-rose-300 hover:opacity-75">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    @if ($selectedBankId && $currentBank)
        {{-- Bank Detail & Question Management View --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
            <div>
                <button
                    type="button"
                    wire:click="selectBank(null)"
                    class="text-xs text-text-muted hover:text-text-primary inline-flex items-center gap-1.5 mb-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to All Banks
                </button>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-text-primary">{{ $currentBank->name }}</h1>
                    @if ($currentBank->category)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300">
                            {{ $currentBank->category }}
                        </span>
                    @endif
                    @if ($currentBank->isArchived())
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                            Archived
                        </span>
                    @endif
                </div>
                @if ($currentBank->description)
                    <p class="text-xs text-text-secondary mt-1">{{ $currentBank->description }}</p>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="openAddExistingModal"
                    class="btn btn-secondary btn-sm inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <span>Add From Library</span>
                </button>

                <button
                    type="button"
                    wire:click="openCreateQuestionModal"
                    class="btn btn-primary btn-sm inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>New Question</span>
                </button>
            </div>
        </div>

        {{-- Question Filter Bar --}}
        <x-card>
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="questionSearch"
                    placeholder="Search questions in this bank..."
                    class="input w-full sm:w-80 text-xs"
                >
                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <select wire:model.live="questionTypeFilter" class="input w-full sm:w-44 text-xs">
                        <option value="">All Question Types</option>
                        <option value="single_choice">Single Choice</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True / False</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="long_answer">Long Answer</option>
                    </select>

                    <select wire:model.live="difficultyFilter" class="input w-full sm:w-36 text-xs">
                        <option value="">All Difficulties</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>
        </x-card>

        {{-- Questions Table --}}
        <x-card>
            <div class="overflow-x-auto">
                <table class="table w-full text-xs">
                    <thead>
                        <tr class="text-left text-text-muted border-b border-border-default">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">Question Prompt</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Difficulty</th>
                            <th class="py-3 px-4">Points</th>
                            <th class="py-3 px-4">Options</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default/60">
                        @forelse ($questions as $index => $q)
                            <tr class="hover:bg-surface-bg/50 transition-colors">
                                <td class="py-3 px-4 text-text-muted font-mono">
                                    {{ $questions->firstItem() + $index }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-text-primary max-w-md">
                                    <p class="line-clamp-2">{{ $q->question_text }}</p>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ ucfirst(str_replace('_', ' ', $q->question_type)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    @if ($q->difficulty === 'easy')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">Easy</span>
                                    @elseif ($q->difficulty === 'hard')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300">Hard</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">Medium</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-medium">
                                    {{ $q->points }} pt{{ $q->points > 1 ? 's' : '' }}
                                </td>
                                <td class="py-3 px-4 text-text-muted">
                                    @if (is_array($q->options))
                                        {{ count($q->options) }} choice{{ count($q->options) > 1 ? 's' : '' }}
                                    @else
                                        Text input
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <button
                                        type="button"
                                        wire:click="openEditQuestionModal('{{ $q->id }}')"
                                        class="btn btn-secondary btn-xs"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeQuestionFromCurrentBank('{{ $q->id }}')"
                                        wire:confirm="Remove this question from the bank? (The question itself will remain in your question library)."
                                        class="btn btn-secondary btn-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-text-muted">
                                    No questions found in this bank. Click <strong>New Question</strong> or <strong>Add From Library</strong> to populate this bank.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($questions->hasPages())
                <div class="p-4 border-t border-border-default">
                    {{ $questions->links() }}
                </div>
            @endif
        </x-card>

    @else
        {{-- Question Banks Listing View --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
            <div>
                <h1 class="text-2xl font-bold text-text-primary">Question Banks & Question Pools</h1>
                <p class="text-xs text-text-secondary mt-1">Organize reusable questions by subject, category, and difficulty to draw randomized assessment pools.</p>
            </div>
            <button
                type="button"
                wire:click="openCreateBankModal"
                class="btn btn-primary btn-sm inline-flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>New Question Bank</span>
            </button>
        </div>

        {{-- Filter Bar --}}
        <x-card>
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search banks by name or description..."
                    class="input w-full sm:w-80 text-xs"
                >

                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    @if (! empty($categories) && count($categories) > 0)
                        <select wire:model.live="categoryFilter" class="input w-full sm:w-44 text-xs">
                            <option value="">All Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    @endif

                    <select wire:model.live="statusFilter" class="input w-full sm:w-36 text-xs">
                        <option value="active">Active Banks</option>
                        <option value="archived">Archived Banks</option>
                        <option value="all">All Banks</option>
                    </select>
                </div>
            </div>
        </x-card>

        {{-- Banks Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($banks as $bank)
                <div class="card p-5 flex flex-col justify-between hover:border-primary-500/50 transition-colors">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="text-base font-bold text-text-primary line-clamp-1">{{ $bank->name }}</h2>
                            @if ($bank->isArchived())
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                    Archived
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                    Active
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-text-secondary line-clamp-2 min-h-[2rem]">
                            {{ $bank->description ?: 'No description provided.' }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ $bank->questions_count }} Question{{ $bank->questions_count === 1 ? '' : 's' }}
                            </span>

                            @if ($bank->category)
                                <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300">
                                    {{ $bank->category }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="pt-5 mt-4 border-t border-border-default/60 flex items-center justify-between">
                        <button
                            type="button"
                            wire:click="selectBank('{{ $bank->id }}')"
                            class="btn btn-primary btn-xs inline-flex items-center gap-1.5"
                        >
                            <span>Manage Questions</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                wire:click="openEditBankModal('{{ $bank->id }}')"
                                class="p-1.5 text-text-muted hover:text-text-primary rounded-md transition-colors"
                                title="Edit Question Bank"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>

                            <button
                                type="button"
                                wire:click="toggleArchiveBank('{{ $bank->id }}')"
                                class="p-1.5 text-text-muted hover:text-text-primary rounded-md transition-colors"
                                title="{{ $bank->isArchived() ? 'Restore Question Bank' : 'Archive Question Bank' }}"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <x-card>
                        <div class="text-center py-12 space-y-3">
                            <div class="w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-text-primary">No question banks found</h3>
                            <p class="text-xs text-text-secondary max-w-sm mx-auto">Create question banks to organize questions and pull randomized pools into your quizzes.</p>
                            <button type="button" wire:click="openCreateBankModal" class="btn btn-primary btn-sm">
                                Create First Bank
                            </button>
                        </div>
                    </x-card>
                </div>
            @endforelse
        </div>

        @if ($banks->hasPages())
            <div class="p-4 border-t border-border-default">
                {{ $banks->links() }}
            </div>
        @endif
    @endif

    {{-- Question Bank Create/Edit Modal --}}
    @if ($showBankModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="card max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">
                        {{ $editingBankId ? 'Edit Question Bank' : 'Create Question Bank' }}
                    </h3>
                    <button type="button" wire:click="$set('showBankModal', false)" class="text-text-muted hover:text-text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-text-primary">Bank Name *</label>
                        <input type="text" wire:model="bankName" class="input w-full" placeholder="e.g. Technical SEO Essentials">
                        @error('bankName') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-text-primary">Category</label>
                        <input type="text" wire:model="bankCategory" class="input w-full" placeholder="e.g. Crawling & Indexing">
                        @error('bankCategory') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-text-primary">Description</label>
                        <textarea wire:model="bankDescription" rows="3" class="input w-full" placeholder="Summary of question coverage..."></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="$set('showBankModal', false)" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveBank" class="btn btn-primary btn-sm">
                        {{ $editingBankId ? 'Save Changes' : 'Create Bank' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Question Create/Edit Modal --}}
    @if ($showQuestionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="card max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">
                        {{ $editingQuestionId ? 'Edit Question' : 'Create Question' }}
                    </h3>
                    <button type="button" wire:click="$set('showQuestionModal', false)" class="text-text-muted hover:text-text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-text-primary">Question Prompt *</label>
                        <textarea wire:model="questionText" rows="3" class="input w-full" placeholder="Enter question text..."></textarea>
                        @error('questionText') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Question Type *</label>
                            <select wire:model.live="questionType" class="input w-full">
                                <option value="single_choice">Single Choice</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True / False</option>
                                <option value="short_answer">Short Answer</option>
                                <option value="long_answer">Long Answer</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Difficulty *</label>
                            <select wire:model="difficulty" class="input w-full">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Points *</label>
                            <input type="number" wire:model="points" min="1" class="input w-full">
                        </div>
                    </div>

                    {{-- Options Section for Single / Multiple Choice --}}
                    @if (in_array($questionType, ['single_choice', 'multiple_choice']))
                        <div class="space-y-2 pt-2 border-t border-border-default">
                            <div class="flex items-center justify-between">
                                <label class="font-semibold text-text-primary">Available Choices</label>
                                <button type="button" wire:click="addOption" class="btn btn-secondary btn-xs">
                                    + Add Option
                                </button>
                            </div>

                            @foreach ($options as $idx => $opt)
                                <div class="flex items-center gap-2">
                                    <span class="w-7 text-center font-bold text-text-muted">{{ $opt['key'] ?? chr(65 + $idx) }}</span>
                                    <input
                                        type="text"
                                        wire:model="options.{{ $idx }}.text"
                                        class="input flex-1"
                                        placeholder="Option text..."
                                    >
                                    @if (count($options) > 2)
                                        <button
                                            type="button"
                                            wire:click="removeOption({{ $idx }})"
                                            class="text-rose-500 hover:text-rose-700 p-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Correct Answer Definition --}}
                    <div class="pt-2 border-t border-border-default">
                        <label class="block font-semibold mb-1 text-text-primary">Correct Answer</label>
                        @if ($questionType === 'true_false')
                            <select wire:model="correctAnswer" class="input w-full">
                                <option value="true">True</option>
                                <option value="false">False</option>
                            </select>
                        @elseif ($questionType === 'single_choice')
                            <select wire:model="correctAnswer" class="input w-full">
                                @foreach ($options as $idx => $opt)
                                    <option value="{{ $opt['key'] ?? chr(65 + $idx) }}">
                                        {{ $opt['key'] ?? chr(65 + $idx) }} — {{ $opt['text'] ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif ($questionType === 'multiple_choice')
                            <p class="text-[11px] text-text-muted mb-1">Enter correct option keys separated by comma (e.g. A, C):</p>
                            <input type="text" wire:model="correctAnswer" class="input w-full" placeholder="A, C">
                        @else
                            <input type="text" wire:model="correctAnswer" class="input w-full" placeholder="Expected answer or benchmark keywords...">
                        @endif
                    </div>

                    {{-- Explanation & Rubric --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-border-default">
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Explanation / Feedback</label>
                            <textarea wire:model="explanation" rows="2" class="input w-full" placeholder="Shown to students after evaluation..."></textarea>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Grading Rubric (Instructor only)</label>
                            <textarea wire:model="rubric" rows="2" class="input w-full" placeholder="Criteria for manual evaluation..."></textarea>
                        </div>
                    </div>

                    {{-- Category & Tags --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Category</label>
                            <input type="text" wire:model="questionCategory" class="input w-full" placeholder="e.g. Core Web Vitals">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-text-primary">Tags (comma-separated)</label>
                            <input type="text" wire:model="tagsInput" class="input w-full" placeholder="lcp, cls, speed">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="$set('showQuestionModal', false)" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveQuestion" class="btn btn-primary btn-sm">
                        {{ $editingQuestionId ? 'Save Changes' : 'Save Question' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add From Library Modal --}}
    @if ($showAddExistingModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="card max-w-xl w-full p-6 space-y-4 max-h-[85vh] flex flex-col justify-between">
                <div class="flex items-center justify-between pb-3 border-b border-border-default shrink-0">
                    <h3 class="text-lg font-bold text-text-primary">Add Questions From Academy Library</h3>
                    <button type="button" wire:click="$set('showAddExistingModal', false)" class="text-text-muted hover:text-text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 overflow-y-auto text-xs flex-1 pr-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="existingSearch"
                        placeholder="Filter available questions..."
                        class="input w-full text-xs"
                    >

                    @forelse ($availableExistingQuestions as $eq)
                        <label class="flex items-start gap-3 p-2.5 rounded-lg border border-border-default hover:bg-surface-bg/50 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="selectedExistingQuestionIds"
                                value="{{ $eq->id }}"
                                class="mt-0.5 rounded border-border-strong text-primary-600 focus:ring-primary-500"
                            >
                            <div class="flex-1 space-y-1">
                                <p class="font-medium text-text-primary">{{ $eq->question_text }}</p>
                                <div class="flex items-center gap-2 text-[10px] text-text-muted">
                                    <span class="capitalize">{{ str_replace('_', ' ', $eq->question_type) }}</span>
                                    <span>&bull;</span>
                                    <span class="capitalize">{{ $eq->difficulty }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $eq->points }} pt{{ $eq->points > 1 ? 's' : '' }}</span>
                                </div>
                            </div>
                        </label>
                    @empty
                        <p class="py-6 text-center text-text-muted">No unattached questions found.</p>
                    @endforelse
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-border-default shrink-0">
                    <span class="text-xs text-text-muted">{{ count($selectedExistingQuestionIds) }} selected</span>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('showAddExistingModal', false)" class="btn btn-secondary btn-sm">
                            Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="addSelectedQuestionsToBank"
                            class="btn btn-primary btn-sm"
                            @if (empty($selectedExistingQuestionIds)) disabled @endif
                        >
                            Add Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

