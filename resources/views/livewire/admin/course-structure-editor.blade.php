@php
    use App\Domain\Courses\Models\Module;
    use App\Domain\Lessons\Models\Lesson;
@endphp

<div
    class="space-y-6"
    x-data="{
        draggedModuleId: null,
        draggedLessonId: null,
        draggedLessonModuleId: null,
        dropModule(targetId) {
            if (!this.draggedModuleId || this.draggedModuleId === targetId) return;
            const container = $refs.modulesContainer;
            if (!container) return;
            const items = Array.from(container.querySelectorAll('[data-module-id]'));
            const ids = items.map(el => el.dataset.moduleId);
            const fromIdx = ids.indexOf(this.draggedModuleId);
            const toIdx = ids.indexOf(targetId);
            if (fromIdx === -1 || toIdx === -1) return;
            ids.splice(toIdx, 0, ids.splice(fromIdx, 1)[0]);
            this.draggedModuleId = null;
            $wire.reorderModules(ids);
        },
        dropLesson(moduleId, targetLessonId) {
            if (!this.draggedLessonId || this.draggedLessonId === targetLessonId || this.draggedLessonModuleId !== moduleId) return;
            const container = document.getElementById('lessons-container-' + moduleId);
            if (!container) return;
            const items = Array.from(container.querySelectorAll('[data-lesson-id]'));
            const ids = items.map(el => el.dataset.lessonId);
            const fromIdx = ids.indexOf(this.draggedLessonId);
            const toIdx = ids.indexOf(targetLessonId);
            if (fromIdx === -1 || toIdx === -1) return;
            ids.splice(toIdx, 0, ids.splice(fromIdx, 1)[0]);
            this.draggedLessonId = null;
            this.draggedLessonModuleId = null;
            $wire.reorderLessons(moduleId, ids);
        }
    }"
>
    <x-page-header
        :title="'Structure: ' . $course->title"
        subtitle="Organize modules, lessons, prerequisites, and downloadable materials."
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()?->hasRole('admin'))
                    <a href="{{ route('admin.courses.index') }}" class="btn btn-secondary btn-sm">
                        Back to Courses
                    </a>
                @elseif (auth()->user()?->hasRole('trainer'))
                    <a href="{{ route('trainer.courses.index') }}" class="btn btn-secondary btn-sm">
                        Back to My Courses
                    </a>
                @endif

                <a href="{{ route('admin.courses.preview', $course) }}" class="btn btn-secondary btn-sm">
                    Preview Course
                </a>

                <x-button variant="primary" size="sm" wire:click="openCreateModuleModal">
                    + Add Module
                </x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if ($modules->isEmpty())
        <x-card>
            <x-empty-state
                title="No modules yet"
                message="Start building your course curriculum by creating the first module."
            >
                <x-slot:action>
                    <x-button variant="primary" size="sm" wire:click="openCreateModuleModal">
                        + Create First Module
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="space-y-4" x-ref="modulesContainer">
            @foreach ($modules as $moduleIndex => $module)
                <div
                    wire:key="module-{{ $module->id }}"
                    data-module-id="{{ $module->id }}"
                    draggable="true"
                    x-on:dragstart="draggedModuleId = '{{ $module->id }}'"
                    x-on:dragover.prevent
                    x-on:drop.prevent="dropModule('{{ $module->id }}')"
                    x-data="{ open: true }"
                    class="rounded-xl border border-border-default bg-bg-surface shadow-sm overflow-hidden"
                >
                    {{-- Module Header --}}
                    <div class="px-5 py-4 bg-border-muted/40 border-b border-border-default flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span
                                class="cursor-grab text-text-muted hover:text-text-primary select-none"
                                title="Drag to reorder module"
                                aria-hidden="true"
                            >
                                &#8942;&#8942;
                            </span>

                            <button
                                type="button"
                                @click="open = !open"
                                class="flex items-center gap-2 text-left min-w-0"
                                :aria-expanded="open.toString()"
                            >
                                <svg
                                    class="w-4 h-4 text-text-muted transition-transform shrink-0"
                                    :class="open ? 'rotate-90' : ''"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="font-semibold text-text-primary truncate">
                                    Module {{ $moduleIndex + 1 }}: {{ $module->title }}
                                </span>
                            </button>

                            <x-badge :color="$module->status === 'Active' ? 'green' : ($module->status === 'Draft' ? 'yellow' : 'red')">
                                {{ $module->status }}
                            </x-badge>

                            <span class="text-xs text-text-muted">
                                {{ $module->lessons->count() }} {{ \Illuminate\Support\Str::plural('lesson', $module->lessons->count()) }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="moveModuleUp('{{ $module->id }}')"
                                @disabled($loop->first)
                                class="btn btn-secondary btn-sm disabled:opacity-40"
                                aria-label="Move module up"
                                title="Move Up"
                            >
                                &uarr;
                            </button>
                            <button
                                type="button"
                                wire:click="moveModuleDown('{{ $module->id }}')"
                                @disabled($loop->last)
                                class="btn btn-secondary btn-sm disabled:opacity-40"
                                aria-label="Move module down"
                                title="Move Down"
                            >
                                &darr;
                            </button>

                            <x-button variant="primary" size="sm" wire:click="openCreateLessonModal('{{ $module->id }}')">
                                + Add Lesson
                            </x-button>

                            <x-button variant="secondary" size="sm" wire:click="openEditModuleModal('{{ $module->id }}')">
                                Edit
                            </x-button>

                            <x-button variant="danger" size="sm" wire:click="openDeleteModuleModal('{{ $module->id }}')">
                                Delete
                            </x-button>
                        </div>
                    </div>

                    {{-- Module Body (Lessons) --}}
                    <div x-show="open" x-transition>
                        @if ($module->description)
                            <div class="px-5 py-2.5 text-xs text-text-secondary border-b border-border-default bg-bg-surface">
                                {{ $module->description }}
                            </div>
                        @endif

                        @if ($module->lessons->isEmpty())
                            <div class="px-5 py-6 text-center text-sm text-text-muted">
                                No lessons in this module yet.
                                <button
                                    type="button"
                                    wire:click="openCreateLessonModal('{{ $module->id }}')"
                                    class="text-primary-600 font-medium hover:underline ml-1"
                                >
                                    Add the first lesson
                                </button>
                            </div>
                        @else
                            <div id="lessons-container-{{ $module->id }}" class="divide-y divide-border-default">
                                @foreach ($module->lessons as $lessonIndex => $lesson)
                                    <div
                                        wire:key="lesson-{{ $lesson->id }}"
                                        data-lesson-id="{{ $lesson->id }}"
                                        draggable="true"
                                        x-on:dragstart.stop="draggedLessonId = '{{ $lesson->id }}'; draggedLessonModuleId = '{{ $module->id }}'"
                                        x-on:dragover.prevent.stop
                                        x-on:drop.prevent.stop="dropLesson('{{ $module->id }}', '{{ $lesson->id }}')"
                                        class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 hover:bg-border-muted/20"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span
                                                class="cursor-grab text-text-muted hover:text-text-primary select-none"
                                                title="Drag to reorder lesson"
                                                aria-hidden="true"
                                            >
                                                &#8942;&#8942;
                                            </span>

                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-medium text-text-primary">
                                                        {{ $lessonIndex + 1 }}. {{ $lesson->title }}
                                                    </span>
                                                    <x-badge color="blue">{{ ucfirst(str_replace('_', ' ', $lesson->type)) }}</x-badge>
                                                    <x-badge :color="$lesson->status === 'Active' ? 'green' : ($lesson->status === 'Draft' ? 'yellow' : 'red')">
                                                        {{ $lesson->status }}
                                                    </x-badge>
                                                    @if ($lesson->isFreePreview())
                                                        <x-badge color="green">Free Preview</x-badge>
                                                    @elseif ($lesson->visibility === 'hidden')
                                                        <x-badge color="gray">Hidden</x-badge>
                                                    @endif
                                                </div>

                                                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-text-muted">
                                                    @if ($lesson->duration_seconds > 0)
                                                        <span>{{ gmdate($lesson->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $lesson->duration_seconds) }}</span>
                                                    @endif
                                                    @if ($lesson->resource_provider)
                                                        <span>Provider: {{ $lesson->resource_provider }}</span>
                                                    @endif
                                                    @if ($lesson->materials->isNotEmpty())
                                                        <span>{{ $lesson->materials->count() }} {{ \Illuminate\Support\Str::plural('material', $lesson->materials->count()) }}</span>
                                                    @endif
                                                    @if ($lesson->prerequisites->isNotEmpty())
                                                        <span>{{ $lesson->prerequisites->count() }} {{ \Illuminate\Support\Str::plural('prerequisite', $lesson->prerequisites->count()) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <button
                                                type="button"
                                                wire:click="moveLessonUp('{{ $lesson->id }}')"
                                                @disabled($loop->first)
                                                class="btn btn-secondary btn-sm disabled:opacity-40"
                                                aria-label="Move lesson up"
                                                title="Move Up"
                                            >
                                                &uarr;
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="moveLessonDown('{{ $lesson->id }}')"
                                                @disabled($loop->last)
                                                class="btn btn-secondary btn-sm disabled:opacity-40"
                                                aria-label="Move lesson down"
                                                title="Move Down"
                                            >
                                                &darr;
                                            </button>

                                            <a
                                                href="{{ route('lessons.preview', [$course, $lesson]) }}"
                                                class="btn btn-secondary btn-sm"
                                            >
                                                Preview
                                            </a>

                                            <x-button variant="secondary" size="sm" wire:click="openMaterialsModal('{{ $lesson->id }}')">
                                                Materials
                                            </x-button>

                                            <x-button variant="secondary" size="sm" wire:click="openEditLessonModal('{{ $lesson->id }}')">
                                                Edit
                                            </x-button>

                                            <x-button
                                                variant="danger"
                                                size="sm"
                                                wire:click="deleteLesson('{{ $lesson->id }}')"
                                                wire:confirm="Delete this lesson? This cannot be undone."
                                            >
                                                Delete
                                            </x-button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Module Create/Edit Modal --}}
    <x-modal name="module-form-modal" :title="$editingModuleId ? 'Edit Module' : 'Create Module'" maxWidth="lg">
        <form wire:submit="saveModule" class="space-y-4">
            <x-input
                name="moduleTitle"
                label="Module Title"
                wire:model="moduleTitle"
                required
            />

            <div>
                <label for="module-description" class="form-label">Description</label>
                <textarea
                    id="module-description"
                    rows="3"
                    wire:model="moduleDescription"
                    class="form-input @error('moduleDescription') error @enderror"
                ></textarea>
                @error('moduleDescription')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-select
                    name="moduleStatus"
                    label="Status"
                    wire:model="moduleStatus"
                    :options="['Draft' => 'Draft', 'Active' => 'Active', 'Archived' => 'Archived']"
                />

                <x-select
                    name="moduleCompletionRule"
                    label="Completion Rule"
                    wire:model.live="moduleCompletionRule"
                    :options="[
                        'all_lessons' => 'All Lessons Completed',
                        'required_count' => 'Required Number of Lessons',
                        'none' => 'No Completion Requirement',
                    ]"
                />
            </div>

            @if ($moduleCompletionRule === 'required_count')
                <x-input
                    name="moduleRequiredLessonsCount"
                    label="Required Lessons Count"
                    type="number"
                    min="1"
                    wire:model="moduleRequiredLessonsCount"
                />
            @endif

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'module-form-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    {{ $editingModuleId ? 'Save Module' : 'Create Module' }}
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Module Safe Delete Confirmation Modal --}}
    <x-modal name="module-delete-modal" title="Delete Module" maxWidth="md">
        <div class="space-y-4">
            <p class="text-sm text-text-secondary">
                Are you sure you want to delete module <strong class="text-text-primary">{{ $deletingModuleTitle }}</strong>?
            </p>

            @if ($deletingModuleHasProgress)
                <div class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-950/40 dark:border-red-800 p-3 text-xs text-red-800 dark:text-red-200 space-y-2">
                    <p class="font-semibold">
                        Warning: Learners already have progress recorded in this module.
                    </p>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="confirmForceDelete"
                            class="mt-0.5 rounded border-red-400 text-red-600 focus:ring-red-500"
                        />
                        <span>I understand and want to permanently delete this module and its lessons.</span>
                    </label>
                    @error('confirmForceDelete')
                        <p class="text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'module-delete-modal')">
                    Cancel
                </x-button>
                <x-button variant="danger" type="button" wire:click="deleteModule" wire:loading.attr="disabled">
                    Delete Module
                </x-button>
            </div>
        </div>
    </x-modal>

    {{-- Lesson Create/Edit Modal --}}
    <x-modal name="lesson-form-modal" :title="$editingLessonId ? 'Edit Lesson' : 'Create Lesson'" maxWidth="xl">
        <form wire:submit="saveLesson" class="space-y-4">
            <x-input
                name="lessonTitle"
                label="Lesson Title"
                wire:model="lessonTitle"
                required
            />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-select
                    name="lessonType"
                    label="Lesson Type"
                    wire:model.live="lessonType"
                    :options="[
                        'video' => 'Video',
                        'text' => 'Text / Article',
                        'pdf' => 'PDF Document',
                        'external' => 'External Resource',
                        'live_class' => 'Live Class',
                        'assignment' => 'Assignment',
                    ]"
                />

                <x-select
                    name="lessonStatus"
                    label="Status"
                    wire:model="lessonStatus"
                    :options="['Draft' => 'Draft', 'Active' => 'Active', 'Archived' => 'Archived']"
                />

                <x-select
                    name="lessonVisibility"
                    label="Visibility"
                    wire:model="lessonVisibility"
                    :options="[
                        'enrolled' => 'Enrolled Only',
                        'preview' => 'Free Preview',
                        'hidden' => 'Hidden',
                    ]"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                <x-input
                    name="lessonDurationSeconds"
                    label="Duration (Seconds)"
                    type="number"
                    min="0"
                    wire:model="lessonDurationSeconds"
                />

                <x-input
                    name="lessonCompletionThresholdPercent"
                    label="Completion Threshold (%)"
                    type="number"
                    min="0"
                    max="100"
                    wire:model="lessonCompletionThresholdPercent"
                />

                <label class="flex items-center gap-2 pt-5 text-sm font-medium text-text-primary cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="lessonIsFreePreview"
                        class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                    />
                    <span>Free Preview</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-select
                    name="lessonResourceProvider"
                    label="Resource Provider"
                    wire:model="lessonResourceProvider"
                    :options="[
                        'private_media' => 'Private Media (Uploaded)',
                        'external_url' => 'External URL',
                        'peertube' => 'PeerTube',
                        'jitsi' => 'Jitsi',
                        'bigbluebutton' => 'BigBlueButton',
                        'internal' => 'Internal Content',
                    ]"
                />

                <x-input
                    name="lessonResourceReference"
                    label="Resource Reference / URL"
                    wire:model="lessonResourceReference"
                    placeholder="https://... or provider ID"
                />
            </div>

            @if (in_array($lessonType, ['video', 'pdf'], true))
                <div>
                    <label class="form-label" for="lesson-resource-file">
                        Upload Private {{ strtoupper($lessonType) }} File (MP4, WebM, or PDF)
                    </label>
                    @if ($existingMediaFilename)
                        <p class="text-xs text-text-muted mb-1">Current file: <strong>{{ $existingMediaFilename }}</strong></p>
                    @endif
                    <input
                        id="lesson-resource-file"
                        type="file"
                        accept="{{ $lessonType === 'pdf' ? 'application/pdf' : 'video/mp4,video/webm' }}"
                        wire:model="lessonResourceFile"
                        class="block w-full text-sm text-text-secondary file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                    />
                    @error('lessonResourceFile')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <label for="lesson-description" class="form-label">Short Description</label>
                <textarea
                    id="lesson-description"
                    rows="2"
                    wire:model="lessonDescription"
                    class="form-input @error('lessonDescription') error @enderror"
                ></textarea>
                @error('lessonDescription')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="lesson-content" class="form-label">Lesson Content (Rich Text / Markdown)</label>
                <textarea
                    id="lesson-content"
                    rows="5"
                    wire:model="lessonContent"
                    class="form-input @error('lessonContent') error @enderror"
                ></textarea>
                @error('lessonContent')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            @if ($availablePrerequisites->isNotEmpty())
                <div>
                    <label for="lesson-prerequisites" class="form-label">Lesson Prerequisites</label>
                    <select
                        id="lesson-prerequisites"
                        multiple
                        wire:model="lessonPrerequisiteIds"
                        class="form-input min-h-[100px]"
                    >
                        @foreach ($availablePrerequisites as $prereqLesson)
                            <option value="{{ $prereqLesson->id }}">
                                {{ $prereqLesson->title }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-text-muted mt-1">Hold Ctrl/Cmd to select multiple prerequisite lessons.</p>
                    @error('lessonPrerequisiteIds')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'lesson-form-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    {{ $editingLessonId ? 'Save Lesson' : 'Create Lesson' }}
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Lesson Materials Modal --}}
    <x-modal name="lesson-materials-modal" :title="'Materials: ' . $materialsLessonTitle" maxWidth="lg">
        <div class="space-y-5">
            @if ($activeMaterials->isEmpty())
                <p class="text-sm text-text-muted">No downloadable PDF materials attached to this lesson yet.</p>
            @else
                <ul class="divide-y divide-border-default border border-border-default rounded-lg">
                    @foreach ($activeMaterials as $material)
                        <li wire:key="material-{{ $material->id }}" class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-medium text-text-primary truncate">{{ $material->title }}</div>
                                @if ($material->description)
                                    <div class="text-xs text-text-muted truncate">{{ $material->description }}</div>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if ($material->media_id)
                                    <a
                                        href="{{ route('media.show', $material->media_id) }}"
                                        target="_blank"
                                        class="btn btn-secondary btn-sm"
                                    >
                                        Download
                                    </a>
                                @endif
                                <x-button
                                    variant="danger"
                                    size="sm"
                                    wire:click="deleteMaterial('{{ $material->id }}')"
                                    wire:loading.attr="disabled"
                                >
                                    Remove
                                </x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit="attachMaterial" class="space-y-3 pt-3 border-t border-border-default">
                <h4 class="text-sm font-semibold text-text-primary">Attach Downloadable PDF</h4>

                <x-input
                    name="materialTitle"
                    label="Material Title"
                    wire:model="materialTitle"
                    required
                />

                <x-input
                    name="materialDescription"
                    label="Description (Optional)"
                    wire:model="materialDescription"
                />

                <div>
                    <label class="form-label" for="material-file">PDF File (max 50 MB)</label>
                    <input
                        id="material-file"
                        type="file"
                        accept="application/pdf"
                        wire:model="materialFile"
                        class="block w-full text-sm text-text-secondary file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                    />
                    @error('materialFile')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'lesson-materials-modal')">
                        Close
                    </x-button>
                    <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                        Upload Material
                    </x-button>
                </div>
            </form>
        </div>
    </x-modal>
</div>
