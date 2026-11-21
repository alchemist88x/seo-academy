<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">
                {{ $projectId ? 'Edit Practical SEO Project' : 'Create Practical SEO Project' }}
            </h1>
            <p class="text-sm text-text-muted mt-1">Configure project scope, rubric evaluation criteria, deadlines, and assigned students.</p>
        </div>
        <a href="{{ route('admin.projects.index') }}" class="text-sm font-medium text-text-muted hover:text-text-primary transition">
            &larr; Back to Projects
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- Core Details Card -->
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <h2 class="text-base font-semibold text-text-primary border-b border-border-default pb-2">1. Project Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Project Title *</label>
                    <input wire:model="title" type="text" placeholder="e.g. Comprehensive Technical SEO & Site Architecture Audit" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                    @error('title') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">URL Slug (Optional)</label>
                    <input wire:model="slug" type="text" placeholder="auto-generated-if-empty" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Category *</label>
                    <select wire:model="category" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Difficulty Level *</label>
                    <select wire:model="difficulty" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                        @foreach($difficulties as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Associated Course</label>
                    <select wire:model="courseId" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                        <option value="">Global / Standalone (Available to all students)</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-text-muted mt-1">If associated with a course, trainers assigned to that course can evaluate student submissions.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Status *</label>
                    <select wire:model="status" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                        <option value="draft">Draft (Hidden from students)</option>
                        <option value="published">Published (Visible & Open)</option>
                        <option value="archived">Archived (Closed)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Project Summary / Brief</label>
                <textarea wire:model="description" rows="2" placeholder="Brief summary of the client scenario, domain context, and business goals..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
            </div>
        </div>

        <!-- Academic & Evaluation Settings -->
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <h2 class="text-base font-semibold text-text-primary border-b border-border-default pb-2">2. Scoring & Deadlines</h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Max Marks *</label>
                    <input wire:model="maxMarks" type="number" step="1" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Passing Marks *</label>
                    <input wire:model="passingMarks" type="number" step="1" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Max Revisions Allowed</label>
                    <input wire:model="maxRevisions" type="number" min="0" max="10" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Submission Deadline</label>
                    <input wire:model="dueDate" type="datetime-local" class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Instructions & Deliverables -->
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <h2 class="text-base font-semibold text-text-primary border-b border-border-default pb-2">3. Instructions & Deliverables</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Key Objectives (One per line)</label>
                    <textarea wire:model="objectivesText" rows="4" placeholder="Crawl entire staging site using Screaming Frog&#10;Identify critical indexability and canonical issues&#10;Audit Core Web Vitals using PageSpeed Insights" class="w-full text-sm font-mono rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Expected Deliverables (One per line)</label>
                    <textarea wire:model="deliverablesText" rows="4" placeholder="Audited Website URL&#10;Executive SEO Audit PDF Report&#10;Technical Crawl Data Spreadsheet (CSV/Excel)&#10;Core Web Vitals Screencast or Screenshots" class="w-full text-sm font-mono rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-text-primary uppercase mb-1">Detailed Student Instructions & Methodology</label>
                <textarea wire:model="instructions" rows="6" placeholder="Provide complete step-by-step guidance, sample scenario background, testing instructions, and deliverable format requirements..." class="w-full text-sm rounded-lg border border-border-default bg-surface-bg text-text-primary px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"></textarea>
            </div>
        </div>

        <!-- Rubric Evaluation Criteria -->
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-border-default pb-2">
                <h2 class="text-base font-semibold text-text-primary">4. Structured Evaluation Rubric Criteria</h2>
                <button type="button" wire:click="addCriterion" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                    + Add Rubric Criterion
                </button>
            </div>

            <div class="space-y-3">
                @foreach($criteria as $index => $crit)
                    <div class="p-4 bg-surface-bg border border-border-default rounded-lg space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-text-primary uppercase">Criterion #{{ $index + 1 }}</span>
                            @if(count($criteria) > 1)
                                <button type="button" wire:click="removeCriterion({{ $index }})" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs text-text-muted mb-1">Criterion Title *</label>
                                <input wire:model="criteria.{{ $index }}.title" type="text" placeholder="e.g. Technical Crawl Quality" class="w-full text-sm rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block text-xs text-text-muted mb-1">Max Marks *</label>
                                <input wire:model="criteria.{{ $index }}.max_marks" type="number" step="0.5" class="w-full text-sm rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-none" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-text-muted mb-1">Evaluation Guidance / Description</label>
                            <input wire:model="criteria.{{ $index }}.description" type="text" placeholder="Evaluation criteria description for trainers and students..." class="w-full text-sm rounded-lg border border-border-default bg-surface-card text-text-primary px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Student Assignment (Optional Individual Scoping) -->
        <div class="bg-surface-card border border-border-default rounded-xl p-6 shadow-xs space-y-4">
            <h2 class="text-base font-semibold text-text-primary border-b border-border-default pb-2">5. Specific Student Assignments (Optional)</h2>
            <p class="text-xs text-text-muted">By default, projects assigned to a course are accessible to all enrolled students. You can also assign this project explicitly to specific individual students:</p>

            <div class="max-h-48 overflow-y-auto space-y-1 border border-border-default rounded-lg p-3 bg-surface-bg">
                @forelse($students as $st)
                    <label class="flex items-center gap-2.5 p-1.5 rounded hover:bg-surface-card cursor-pointer">
                        <input type="checkbox" wire:model="assignedStudentProfileIds" value="{{ $st->id }}" class="rounded text-primary-600 focus:ring-primary-500 border-border-default">
                        <span class="text-xs text-text-primary font-medium">{{ $st->user?->name ?? 'Student' }} ({{ $st->student_code ?? $st->id }})</span>
                        <span class="text-xs text-text-muted ml-auto">{{ ucfirst($st->proficiency ?? 'beginner') }}</span>
                    </label>
                @empty
                    <div class="text-xs text-text-muted">No student profiles found.</div>
                @endforelse
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border-default">
            <a href="{{ route('admin.projects.index') }}" class="px-4 py-2 border border-border-default rounded-lg text-sm text-text-primary hover:bg-surface-bg transition">
                Cancel
            </a>
            <button type="submit" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-sm rounded-lg shadow-sm transition">
                {{ $projectId ? 'Save Changes' : 'Create & Save Project' }}
            </button>
        </div>
    </form>
</div>
