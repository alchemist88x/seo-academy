<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <h1 class="text-2xl font-bold text-text-primary">Assignments & Practical Evaluations</h1>
            <p class="text-xs text-text-secondary mt-1">Manage practical assignments, rubrics, due dates, and student submission evaluations.</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="btn btn-primary btn-sm inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Create Assignment</span>
        </button>
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

    {{-- Filter Bar --}}
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search assignments by title..."
                class="input w-full"
            >
            <select wire:model.live="courseFilter" class="input w-full">
                <option value="all">All Courses</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->title }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="input w-full">
                <option value="all">All Statuses</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="archived">Archived</option>
            </select>
        </div>
    </x-card>

    {{-- Assignments Table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table w-full text-xs">
                <thead>
                    <tr class="text-left text-text-muted border-b border-border-default">
                        <th class="py-3 px-4">Title</th>
                        <th class="py-3 px-4">Course</th>
                        <th class="py-3 px-4">Due Date</th>
                        <th class="py-3 px-4">Max Marks</th>
                        <th class="py-3 px-4">Pass Marks</th>
                        <th class="py-3 px-4">Submissions</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default/60">
                    @forelse ($assignments as $assignment)
                        <tr class="hover:bg-surface-bg/50 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-text-primary">{{ $assignment->title }}</div>
                                @if ($assignment->is_mandatory)
                                    <span class="badge badge-primary text-[10px] mt-0.5 inline-block">Mandatory</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-text-secondary">
                                {{ $assignment->course?->title ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-text-secondary whitespace-nowrap">
                                {{ $assignment->due_date ? $assignment->due_date->format('M d, Y H:i') : 'No due date' }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-text-primary">
                                {{ number_format((float) $assignment->max_marks, 2) }}
                            </td>
                            <td class="py-3 px-4 text-text-secondary">
                                {{ number_format((float) $assignment->passing_marks, 2) }}
                            </td>
                            <td class="py-3 px-4">
                                <a
                                    href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.submissions', $assignment) : route('trainer.assignments.submissions', $assignment) }}"
                                    class="text-brand-primary hover:underline font-medium"
                                >
                                    {{ $assignment->submissions_count }} {{ Str::plural('submission', $assignment->submissions_count) }}
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                @if ($assignment->isPublished())
                                    <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Published</span>
                                @elseif ($assignment->isDraft())
                                    <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Draft</span>
                                @else
                                    <span class="badge bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20">Archived</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                                <a
                                    href="{{ auth()->user()?->hasRole('admin') ? route('admin.assignments.submissions', $assignment) : route('trainer.assignments.submissions', $assignment) }}"
                                    class="btn btn-secondary btn-xs"
                                >
                                    Submissions
                                </a>
                                <button
                                    type="button"
                                    wire:click="openEditModal('{{ $assignment->id }}')"
                                    class="btn btn-secondary btn-xs"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    wire:click="togglePublish('{{ $assignment->id }}')"
                                    class="btn btn-secondary btn-xs"
                                >
                                    {{ $assignment->isPublished() ? 'Archive' : 'Publish' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-text-muted">
                                No assignments found. Click "Create Assignment" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-4">
            {{ $assignments->links() }}
        </div>
    </x-card>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-surface-card rounded-2xl border border-border-default max-w-2xl w-full p-6 space-y-4 shadow-xl my-8">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">
                        {{ $editingAssignmentId ? 'Edit Assignment' : 'Create New Assignment' }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)" class="text-text-muted hover:text-text-primary text-xl leading-none">
                        &times;
                    </button>
                </div>

                <div class="space-y-4 max-h-[75vh] overflow-y-auto pr-2">
                    {{-- Basic Info --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-text-primary mb-1">Title *</label>
                            <input type="text" wire:model="title" class="input w-full" placeholder="e.g. Practical Technical Audit">
                            @error('title') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Course *</label>
                            <select wire:model="courseId" class="input w-full">
                                <option value="">Select a course...</option>
                                @foreach ($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->title }}</option>
                                @endforeach
                            </select>
                            @error('courseId') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Assignment Type</label>
                            <select wire:model="assignmentType" class="input w-full">
                                <option value="file_and_text">File Upload & Written Text</option>
                                <option value="file_only">File Upload Only</option>
                                <option value="text_only">Written Text Only</option>
                            </select>
                        </div>
                    </div>

                    {{-- Descriptions & Instructions --}}
                    <div>
                        <label class="block text-xs font-semibold text-text-primary mb-1">Brief Description</label>
                        <input type="text" wire:model="description" class="input w-full" placeholder="Summary of the assignment deliverable">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary mb-1">Detailed Instructions</label>
                        <textarea wire:model="instructions" rows="4" class="input w-full" placeholder="Step-by-step instructions for the student..."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-primary mb-1">Submission Guidelines</label>
                        <textarea wire:model="submissionGuidelines" rows="2" class="input w-full" placeholder="Formatting rules, tool guidelines, file naming..."></textarea>
                    </div>

                    {{-- Scoring --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-border-default pt-4">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Maximum Marks *</label>
                            <input type="number" step="0.5" wire:model="maxMarks" class="input w-full">
                            @error('maxMarks') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Passing Marks *</label>
                            <input type="number" step="0.5" wire:model="passingMarks" class="input w-full">
                            @error('passingMarks') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Deadlines & Availability --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-border-default pt-4">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Available From</label>
                            <input type="datetime-local" wire:model="availableFrom" class="input w-full">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Due Date</label>
                            <input type="datetime-local" wire:model="dueDate" class="input w-full">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Close Date (Available To)</label>
                            <input type="datetime-local" wire:model="availableTo" class="input w-full">
                        </div>
                    </div>

                    {{-- Late Policies --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-border-default pt-4">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Late Submission Policy</label>
                            <select wire:model.live="lateSubmissionPolicy" class="input w-full">
                                <option value="allowed_no_penalty">Allowed (No Penalty)</option>
                                <option value="allowed_penalty_deduction">Allowed with Mark Deduction</option>
                                <option value="allowed_until_hard_deadline">Allowed until Hard Cutoff</option>
                                <option value="not_allowed">Strictly Not Allowed</option>
                            </select>
                        </div>

                        @if ($lateSubmissionPolicy === 'allowed_penalty_deduction')
                            <div>
                                <label class="block text-xs font-semibold text-text-primary mb-1">Late Penalty (%)</label>
                                <input type="number" step="0.5" wire:model="latePenaltyPercent" class="input w-full" placeholder="e.g. 10">
                            </div>
                        @endif

                        @if (in_array($lateSubmissionPolicy, ['allowed_until_hard_deadline', 'allowed_penalty_deduction']))
                            <div>
                                <label class="block text-xs font-semibold text-text-primary mb-1">Late Hard Deadline</label>
                                <input type="datetime-local" wire:model="lateHardDeadline" class="input w-full">
                            </div>
                        @endif
                    </div>

                    {{-- Files and Attempts Rules --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-border-default pt-4">
                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Max Attempts</label>
                            <input type="number" wire:model="maxAttempts" class="input w-full" placeholder="Empty = Unlimited">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Max File Count</label>
                            <input type="number" wire:model="maxFileCount" class="input w-full">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-text-primary mb-1">Max File Size (MB)</label>
                            <input type="number" wire:model="maxFileSizeMb" class="input w-full">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-2">
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-text-primary cursor-pointer">
                            <input type="checkbox" wire:model="allowResubmission" class="rounded border-border-default text-brand-primary">
                            <span>Allow Resubmissions</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-text-primary cursor-pointer">
                            <input type="checkbox" wire:model="allowMultipleFiles" class="rounded border-border-default text-brand-primary">
                            <span>Allow Multiple Files</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-text-primary cursor-pointer">
                            <input type="checkbox" wire:model="isMandatory" class="rounded border-border-default text-brand-primary">
                            <span>Mandatory for Course Completion</span>
                        </label>
                    </div>

                    {{-- Rubric Criteria Builder --}}
                    <div class="border-t border-border-default pt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-text-primary">Grading Rubric Criteria</h4>
                                <p class="text-[11px] text-text-secondary">Configure structured criteria. The sum must match the assignment's maximum marks ({{ number_format($maxMarks, 2) }}).</p>
                            </div>
                            <button type="button" wire:click="addRubricCriterion" class="btn btn-secondary btn-xs">
                                + Add Criterion
                            </button>
                        </div>

                        @if (! empty($rubricCriteria))
                            <div class="space-y-3">
                                @foreach ($rubricCriteria as $index => $crit)
                                    <div class="p-3 rounded-xl border border-border-default bg-surface-bg/40 space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <input
                                                type="text"
                                                wire:model="rubricCriteria.{{ $index }}.title"
                                                class="input text-xs w-2/3"
                                                placeholder="Criterion title (e.g. Keyword Relevance)"
                                            >
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    step="0.5"
                                                    wire:model="rubricCriteria.{{ $index }}.max_marks"
                                                    class="input text-xs w-20"
                                                    placeholder="Marks"
                                                >
                                                <button
                                                    type="button"
                                                    wire:click="removeRubricCriterion({{ $index }})"
                                                    class="text-rose-500 hover:text-rose-600 text-sm px-1.5"
                                                >
                                                    &times;
                                                </button>
                                            </div>
                                        </div>
                                        <input
                                            type="text"
                                            wire:model="rubricCriteria.{{ $index }}.description"
                                            class="input text-xs w-full"
                                            placeholder="Criterion description / grading expectations (optional)"
                                        >
                                    </div>
                                @endforeach

                                <div class="text-right text-xs font-semibold text-text-secondary">
                                    Total Rubric Marks: {{ number_format(array_sum(array_column($rubricCriteria, 'max_marks')), 2) }} / {{ number_format($maxMarks, 2) }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="$set('showModal', false)" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="button" wire:click="save" class="btn btn-primary btn-sm">
                        {{ $editingAssignmentId ? 'Save Changes' : 'Create Assignment' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
