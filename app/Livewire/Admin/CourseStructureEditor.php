<?php

namespace App\Livewire\Admin;

use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Courses\Services\CourseStructureService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Lessons\Models\LessonMaterial;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Course Structure')]
class CourseStructureEditor extends Component
{
    use WithFileUploads;

    public Course $course;

    // Module modal state
    public ?string $editingModuleId = null;

    public string $moduleTitle = '';

    public string $moduleDescription = '';

    public string $moduleStatus = 'Active';

    public string $moduleCompletionRule = 'all_lessons';

    public string $moduleRequiredLessonsCount = '1';

    // Module safe delete modal state
    public ?string $deletingModuleId = null;

    public string $deletingModuleTitle = '';

    public bool $deletingModuleHasProgress = false;

    public bool $confirmForceDelete = false;

    // Lesson modal state
    public ?string $editingLessonId = null;

    public ?string $targetModuleId = null;

    public string $lessonTitle = '';

    public string $lessonDescription = '';

    public string $lessonType = 'video';

    public string $lessonStatus = 'Active';

    public string $lessonVisibility = 'enrolled';

    public bool $lessonIsFreePreview = false;

    public string $lessonDurationSeconds = '0';

    public string $lessonContent = '';

    public string $lessonResourceProvider = 'private_media';

    public string $lessonResourceReference = '';

    public string $lessonCompletionThresholdPercent = '80';

    public $lessonResourceFile = null;

    public ?string $existingMediaFilename = null;

    /** @var array<int, string> */
    public array $lessonPrerequisiteIds = [];

    // Lesson materials modal state
    public ?string $materialsLessonId = null;

    public string $materialsLessonTitle = '';

    public string $materialTitle = '';

    public string $materialDescription = '';

    public $materialFile = null;

    public function mount(Course $course, TenantContext $tenantContext): void
    {
        $academy = $tenantContext->getAcademy();
        if ($academy !== null && $course->academy_id !== $academy->getKey()) {
            abort(404);
        }

        $this->authorize('update', $course);
        $this->course = $course;
    }

    public function openCreateModuleModal(): void
    {
        $this->authorizeCourseUpdate();
        $this->resetModuleForm();
        $this->dispatch('open-modal', 'module-form-modal');
    }

    public function openEditModuleModal(string $moduleId): void
    {
        $this->authorizeCourseUpdate();

        $module = $this->findCourseModule($moduleId);
        $this->authorize('update', $module);

        $this->resetValidation();
        $this->editingModuleId = (string) $module->getKey();
        $this->moduleTitle = (string) $module->title;
        $this->moduleDescription = (string) ($module->description ?? '');
        $this->moduleStatus = (string) ($module->status ?? 'Active');
        $this->moduleCompletionRule = (string) ($module->completion_rule ?? 'all_lessons');
        $this->moduleRequiredLessonsCount = (string) ($module->required_lessons_count ?? 1);

        $this->dispatch('open-modal', 'module-form-modal');
    }

    public function saveModule(CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();
        $actor = Auth::user();

        $this->validate([
            'moduleTitle' => ['required', 'string', 'max:255'],
            'moduleDescription' => ['nullable', 'string', 'max:5000'],
            'moduleStatus' => ['required', 'string', Rule::in(['Draft', 'Active', 'Archived'])],
            'moduleCompletionRule' => ['required', 'string', Rule::in(Module::COMPLETION_RULES)],
            'moduleRequiredLessonsCount' => ['nullable', 'integer', 'min:1'],
        ]);

        $payload = [
            'title' => $this->moduleTitle,
            'description' => $this->moduleDescription !== '' ? $this->moduleDescription : null,
            'status' => $this->moduleStatus,
            'completion_rule' => $this->moduleCompletionRule,
            'required_lessons_count' => $this->moduleCompletionRule === 'required_count'
                ? max(1, (int) $this->moduleRequiredLessonsCount)
                : null,
        ];

        if ($this->editingModuleId !== null) {
            $module = $this->findCourseModule($this->editingModuleId);
            $this->authorize('update', $module);

            $service->updateModule($module, $payload, $actor);
            $this->dispatch('toast', type: 'success', message: 'Module updated.');
        } else {
            $this->authorize('create', [Module::class, $this->course]);

            $service->createModule($this->course, $payload, $actor);
            $this->dispatch('toast', type: 'success', message: 'Module created.');
        }

        $this->resetModuleForm();
        $this->dispatch('close-modal', 'module-form-modal');
    }

    public function openDeleteModuleModal(string $moduleId): void
    {
        $this->authorizeCourseUpdate();

        $module = $this->findCourseModule($moduleId);
        $this->authorize('delete', $module);

        $this->resetValidation();
        $this->deletingModuleId = (string) $module->getKey();
        $this->deletingModuleTitle = (string) $module->title;
        $this->deletingModuleHasProgress = $module->hasLearnerProgress();
        $this->confirmForceDelete = false;

        $this->dispatch('open-modal', 'module-delete-modal');
    }

    public function deleteModule(CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        if ($this->deletingModuleId === null) {
            return;
        }

        $module = $this->findCourseModule($this->deletingModuleId);
        $this->authorize('delete', $module);

        $hasProgress = $module->hasLearnerProgress();
        if ($hasProgress && ! $this->confirmForceDelete) {
            $this->addError('confirmForceDelete', 'Please confirm deletion of this module and its learner progress.');

            return;
        }

        $service->deleteModule($module, Auth::user(), $hasProgress && $this->confirmForceDelete);

        $this->deletingModuleId = null;
        $this->deletingModuleTitle = '';
        $this->deletingModuleHasProgress = false;
        $this->confirmForceDelete = false;

        $this->dispatch('close-modal', 'module-delete-modal');
        $this->dispatch('toast', type: 'success', message: 'Module deleted.');
    }

    /**
     * @param  array<int, string>  $orderedIds
     */
    public function reorderModules(array $orderedIds, CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        $service->reorderModules($this->course, $orderedIds, Auth::user());
        $this->dispatch('toast', type: 'success', message: 'Module order updated.');
    }

    public function moveModuleUp(string $moduleId, CourseStructureService $service): void
    {
        $this->moveModuleByOffset($moduleId, -1, $service);
    }

    public function moveModuleDown(string $moduleId, CourseStructureService $service): void
    {
        $this->moveModuleByOffset($moduleId, 1, $service);
    }

    public function openCreateLessonModal(string $moduleId): void
    {
        $this->authorizeCourseUpdate();

        $module = $this->findCourseModule($moduleId);
        $this->authorize('create', [Lesson::class, $module]);

        $this->resetLessonForm();
        $this->targetModuleId = (string) $module->getKey();

        $this->dispatch('open-modal', 'lesson-form-modal');
    }

    public function openEditLessonModal(string $lessonId): void
    {
        $this->authorizeCourseUpdate();

        $lesson = $this->findCourseLesson($lessonId);
        $this->authorize('update', $lesson);

        $this->resetValidation();
        $this->editingLessonId = (string) $lesson->getKey();
        $this->targetModuleId = (string) $lesson->module_id;
        $this->lessonTitle = (string) $lesson->title;
        $this->lessonDescription = (string) ($lesson->description ?? '');
        $this->lessonType = (string) ($lesson->type ?? 'video');
        $this->lessonStatus = (string) ($lesson->status ?? 'Active');
        $this->lessonVisibility = (string) ($lesson->visibility ?? 'enrolled');
        $this->lessonIsFreePreview = (bool) $lesson->is_free_preview;
        $this->lessonDurationSeconds = (string) ($lesson->duration_seconds ?? 0);
        $this->lessonContent = (string) ($lesson->content ?? '');
        $this->lessonResourceProvider = (string) ($lesson->resource_provider ?? 'private_media');
        $this->lessonResourceReference = (string) ($lesson->resource_reference ?? '');
        $this->lessonCompletionThresholdPercent = (string) ($lesson->completion_threshold_percent ?? 80);
        $this->lessonResourceFile = null;
        $this->existingMediaFilename = $lesson->media?->filename;
        $this->lessonPrerequisiteIds = $lesson->prerequisites()
            ->pluck('lessons.id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $this->dispatch('open-modal', 'lesson-form-modal');
    }

    public function saveLesson(CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();
        $actor = Auth::user();

        $this->validate([
            'lessonTitle' => ['required', 'string', 'max:255'],
            'lessonDescription' => ['nullable', 'string', 'max:10000'],
            'lessonType' => ['required', 'string', Rule::in(Lesson::TYPES)],
            'lessonStatus' => ['required', 'string', Rule::in(['Draft', 'Active', 'Archived'])],
            'lessonVisibility' => ['required', 'string', Rule::in(Lesson::VISIBILITIES)],
            'lessonIsFreePreview' => ['boolean'],
            'lessonDurationSeconds' => ['nullable', 'integer', 'min:0'],
            'lessonContent' => ['nullable', 'string'],
            'lessonResourceProvider' => ['nullable', 'string', Rule::in(Lesson::PROVIDERS)],
            'lessonResourceReference' => ['nullable', 'string', 'max:500'],
            'lessonCompletionThresholdPercent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lessonResourceFile' => ['nullable', 'file', 'max:524288'],
            'lessonPrerequisiteIds' => ['array'],
            'lessonPrerequisiteIds.*' => ['uuid'],
        ]);

        $payload = [
            'title' => $this->lessonTitle,
            'description' => $this->lessonDescription !== '' ? $this->lessonDescription : null,
            'type' => $this->lessonType,
            'status' => $this->lessonStatus,
            'visibility' => $this->lessonVisibility,
            'is_free_preview' => $this->lessonIsFreePreview,
            'duration_seconds' => $this->lessonDurationSeconds !== '' ? (int) $this->lessonDurationSeconds : 0,
            'content' => $this->lessonContent !== '' ? $this->lessonContent : null,
            'resource_provider' => $this->lessonResourceProvider !== '' ? $this->lessonResourceProvider : null,
            'resource_reference' => $this->lessonResourceReference !== '' ? $this->lessonResourceReference : null,
            'completion_threshold_percent' => $this->lessonCompletionThresholdPercent !== ''
                ? (int) $this->lessonCompletionThresholdPercent
                : 80,
            'prerequisite_ids' => $this->lessonPrerequisiteIds,
        ];

        if ($this->editingLessonId !== null) {
            $lesson = $this->findCourseLesson($this->editingLessonId);
            $this->authorize('update', $lesson);

            $service->updateLesson($lesson, $payload, $actor, $this->lessonResourceFile);
            $this->dispatch('toast', type: 'success', message: 'Lesson updated.');
        } else {
            $module = $this->findCourseModule((string) $this->targetModuleId);
            $this->authorize('create', [Lesson::class, $module]);

            $service->createLesson($module, $payload, $actor, $this->lessonResourceFile);
            $this->dispatch('toast', type: 'success', message: 'Lesson created.');
        }

        $this->resetLessonForm();
        $this->dispatch('close-modal', 'lesson-form-modal');
    }

    public function deleteLesson(string $lessonId, CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        $lesson = $this->findCourseLesson($lessonId);
        $this->authorize('delete', $lesson);

        $service->deleteLesson($lesson, Auth::user());
        $this->dispatch('toast', type: 'success', message: 'Lesson deleted.');
    }

    /**
     * @param  array<int, string>  $orderedIds
     */
    public function reorderLessons(string $moduleId, array $orderedIds, CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        $module = $this->findCourseModule($moduleId);
        $this->authorize('update', $module);

        $service->reorderLessons($module, $orderedIds, Auth::user());
        $this->dispatch('toast', type: 'success', message: 'Lesson order updated.');
    }

    public function moveLessonUp(string $lessonId, CourseStructureService $service): void
    {
        $this->moveLessonByOffset($lessonId, -1, $service);
    }

    public function moveLessonDown(string $lessonId, CourseStructureService $service): void
    {
        $this->moveLessonByOffset($lessonId, 1, $service);
    }

    public function openMaterialsModal(string $lessonId): void
    {
        $this->authorizeCourseUpdate();

        $lesson = $this->findCourseLesson($lessonId);
        $this->authorize('update', $lesson);

        $this->resetValidation();
        $this->materialsLessonId = (string) $lesson->getKey();
        $this->materialsLessonTitle = (string) $lesson->title;
        $this->materialTitle = '';
        $this->materialDescription = '';
        $this->materialFile = null;

        $this->dispatch('open-modal', 'lesson-materials-modal');
    }

    public function attachMaterial(CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        if ($this->materialsLessonId === null) {
            return;
        }

        $lesson = $this->findCourseLesson($this->materialsLessonId);
        $this->authorize('update', $lesson);

        $this->validate([
            'materialTitle' => ['required', 'string', 'max:255'],
            'materialDescription' => ['nullable', 'string', 'max:2000'],
            'materialFile' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $service->attachLessonMaterial(
            $lesson,
            $this->materialTitle,
            $this->materialDescription !== '' ? $this->materialDescription : null,
            $this->materialFile,
            Auth::user(),
        );

        $this->materialTitle = '';
        $this->materialDescription = '';
        $this->materialFile = null;

        $this->dispatch('toast', type: 'success', message: 'Material attached.');
    }

    public function deleteMaterial(string $materialId, CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        if ($this->materialsLessonId === null) {
            return;
        }

        $lesson = $this->findCourseLesson($this->materialsLessonId);
        $this->authorize('update', $lesson);

        $material = LessonMaterial::query()
            ->where('lesson_id', $lesson->getKey())
            ->findOrFail($materialId);

        $service->deleteLessonMaterial($material, Auth::user());
        $this->dispatch('toast', type: 'success', message: 'Material deleted.');
    }

    public function render()
    {
        $modules = $this->course->modules()
            ->with([
                'lessons' => fn ($query) => $query->orderBy('display_order')
                    ->with(['media', 'materials.media', 'prerequisites']),
            ])
            ->orderBy('display_order')
            ->get();

        $availablePrerequisites = $this->courseLessonsList($modules)
            ->reject(fn (Lesson $lesson): bool => (string) $lesson->getKey() === $this->editingLessonId)
            ->values();

        $activeMaterials = collect();
        if ($this->materialsLessonId !== null) {
            $activeMaterials = LessonMaterial::query()
                ->with('media')
                ->where('lesson_id', $this->materialsLessonId)
                ->orderBy('display_order')
                ->get();
        }

        return view('livewire.admin.course-structure-editor', [
            'modules' => $modules,
            'availablePrerequisites' => $availablePrerequisites,
            'activeMaterials' => $activeMaterials,
        ]);
    }

    private function authorizeCourseUpdate(): void
    {
        $this->authorize('update', $this->course);
    }

    private function findCourseModule(string $moduleId): Module
    {
        return Module::query()
            ->where('course_id', $this->course->getKey())
            ->findOrFail($moduleId);
    }

    private function findCourseLesson(string $lessonId): Lesson
    {
        return Lesson::query()
            ->whereHas('module', fn ($query) => $query->where('course_id', $this->course->getKey()))
            ->with(['module.course', 'media', 'materials.media', 'prerequisites'])
            ->findOrFail($lessonId);
    }

    private function moveModuleByOffset(string $moduleId, int $offset, CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        $ids = $this->course->modules()
            ->orderBy('display_order')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        $swapped = $this->swapAdjacentId($ids, $moduleId, $offset);
        if ($swapped !== null) {
            $service->reorderModules($this->course, $swapped, Auth::user());
            $this->dispatch('toast', type: 'success', message: 'Module order updated.');
        }
    }

    private function moveLessonByOffset(string $lessonId, int $offset, CourseStructureService $service): void
    {
        $this->authorizeCourseUpdate();

        $lesson = $this->findCourseLesson($lessonId);
        $module = $lesson->module;
        $this->authorize('update', $module);

        $ids = $module->lessons()
            ->orderBy('display_order')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        $swapped = $this->swapAdjacentId($ids, $lessonId, $offset);
        if ($swapped !== null) {
            $service->reorderLessons($module, $swapped, Auth::user());
            $this->dispatch('toast', type: 'success', message: 'Lesson order updated.');
        }
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>|null
     */
    private function swapAdjacentId(array $ids, string $targetId, int $offset): ?array
    {
        $index = array_search($targetId, $ids, true);
        if ($index === false) {
            return null;
        }

        $targetIndex = $index + $offset;
        if ($targetIndex < 0 || $targetIndex >= count($ids)) {
            return null;
        }

        [$ids[$index], $ids[$targetIndex]] = [$ids[$targetIndex], $ids[$index]];

        return array_values($ids);
    }

    private function courseLessonsList(Collection $modules): Collection
    {
        return $modules->flatMap(static fn (Module $module) => $module->lessons);
    }

    private function resetModuleForm(): void
    {
        $this->resetValidation();
        $this->editingModuleId = null;
        $this->moduleTitle = '';
        $this->moduleDescription = '';
        $this->moduleStatus = 'Active';
        $this->moduleCompletionRule = 'all_lessons';
        $this->moduleRequiredLessonsCount = '1';
    }

    private function resetLessonForm(): void
    {
        $this->resetValidation();
        $this->editingLessonId = null;
        $this->targetModuleId = null;
        $this->lessonTitle = '';
        $this->lessonDescription = '';
        $this->lessonType = 'video';
        $this->lessonStatus = 'Active';
        $this->lessonVisibility = 'enrolled';
        $this->lessonIsFreePreview = false;
        $this->lessonDurationSeconds = '0';
        $this->lessonContent = '';
        $this->lessonResourceProvider = 'private_media';
        $this->lessonResourceReference = '';
        $this->lessonCompletionThresholdPercent = '80';
        $this->lessonResourceFile = null;
        $this->existingMediaFilename = null;
        $this->lessonPrerequisiteIds = [];
    }
}
