<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseCategory;
use App\Domain\Courses\Services\CourseCategoryService;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Courses')]
class CoursesIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $categoryId = '';

    public string $difficulty = '';

    public string $status = '';

    public string $visibility = '';

    public string $sort = 'newest';

    // Course modal state
    public ?string $editingCourseId = null;

    public string $title = '';

    public string $slug = '';

    public string $category_id = '';

    public string $courseDifficulty = 'Beginner';

    public string $price = '0.00';

    public string $currency = 'USD';

    public bool $is_free = true;

    public string $duration_months = '0';

    public string $enrollment_limit = '';

    public string $courseVisibility = 'public';

    public string $courseStatus = 'Draft';

    public bool $is_sequential = false;

    public string $description = '';

    public $thumbnail = null;

    public ?string $existingThumbnailUrl = null;

    /** @var array<int, string> */
    public array $trainer_ids = [];

    /** @var array<int, string> */
    public array $prerequisite_ids = [];

    // Category modal state
    public ?string $editingCategoryId = null;

    public string $categoryName = '';

    public string $categorySlug = '';

    public string $categoryDescription = '';

    public string $categoryOrder = '0';

    public bool $categoryActive = true;

    #[Url]
    public bool $manage_categories = false;

    // Course deletion state
    public ?string $deletingCourseId = null;

    public string $deletingCourseTitle = '';

    public int $deletingCourseEnrollmentsCount = 0;

    public ?Course $deletingCourse = null;

    /** @var array{enrollments_count?: int, active_enrollments_count?: int, modules_count?: int, lessons_count?: int, quizzes_count?: int, assignments_count?: int, certificates_count?: int, has_academic_history?: bool, can_delete_permanently?: bool, blocking_reasons?: array<int, string>} */
    public array $deletingCourseImpact = [];

    // Course archive state
    public ?string $archivingCourseId = null;

    public ?Course $archivingCourse = null;

    public function mount(TenantContext $tenantContext): void
    {
        $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', Course::class);

        if ($this->manage_categories) {
            $this->openCategoriesModal($tenantContext);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatingDifficulty(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingVisibility(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatedTitle(string $value): void
    {
        if ($this->editingCourseId === null && $this->slug === '') {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedIsFree(bool $value): void
    {
        if ($value) {
            $this->price = '0.00';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryId', 'difficulty', 'status', 'visibility']);
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function openCreateModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [Course::class, $academy]);

        $this->resetCourseForm();
        $this->dispatch('open-modal', 'course-form-modal');
    }

    public function openEditModal(string $courseId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->with(['trainers', 'prerequisites'])
            ->findOrFail($courseId);

        $this->authorize('update', $course);

        $this->resetValidation();
        $this->editingCourseId = (string) $course->getKey();
        $this->title = (string) $course->title;
        $this->slug = (string) $course->slug;
        $this->category_id = (string) ($course->category_id ?? '');
        $this->courseDifficulty = (string) ($course->difficulty ?? 'Beginner');
        $this->price = number_format((float) $course->price, 2, '.', '');
        $this->currency = (string) ($course->currency ?? 'USD');
        $this->is_free = (bool) $course->is_free;
        $this->duration_months = (string) ($course->duration_months ?? 0);
        $this->enrollment_limit = $course->enrollment_limit !== null ? (string) $course->enrollment_limit : '';
        $this->courseVisibility = (string) ($course->visibility ?? 'public');
        $this->courseStatus = (string) ($course->status ?? 'Draft');
        $this->is_sequential = (bool) $course->is_sequential;
        $this->description = (string) ($course->description ?? '');
        $this->thumbnail = null;
        $this->existingThumbnailUrl = $course->thumbnailUrl();
        $this->trainer_ids = $course->trainers->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $this->prerequisite_ids = $course->prerequisites->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        $this->dispatch('open-modal', 'course-form-modal');
    }

    public function saveCourse(TenantContext $tenantContext, CourseManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $actor = Auth::user();

        if ($this->thumbnail !== null) {
            $this->validate([
                'thumbnail' => ['image', 'max:2048'],
            ]);
        }

        $payload = [
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id !== '' ? $this->category_id : null,
            'difficulty' => $this->courseDifficulty,
            'price' => $this->is_free ? 0 : ($this->price !== '' ? $this->price : 0),
            'currency' => $this->currency !== '' ? $this->currency : 'USD',
            'is_free' => $this->is_free,
            'duration_months' => $this->duration_months !== '' ? (int) $this->duration_months : 0,
            'enrollment_limit' => $this->enrollment_limit !== '' ? (int) $this->enrollment_limit : null,
            'visibility' => $this->courseVisibility,
            'status' => $this->courseStatus,
            'is_sequential' => $this->is_sequential,
            'description' => $this->description !== '' ? $this->description : null,
            'trainer_ids' => $this->trainer_ids,
            'prerequisite_ids' => $this->prerequisite_ids,
        ];

        if ($this->editingCourseId !== null) {
            $course = Course::query()
                ->forAcademy($academy)
                ->findOrFail($this->editingCourseId);

            $this->authorize('update', $course);

            if (! $actor->can('assignTrainers', $course)) {
                unset($payload['trainer_ids']);
            }

            $service->update($course, $payload, $this->thumbnail, $actor);
            $this->dispatch('toast', type: 'success', message: 'Course updated.');
        } else {
            $this->authorize('create', [Course::class, $academy]);

            $service->create($academy, $payload, $this->thumbnail, $actor);
            $this->dispatch('toast', type: 'success', message: 'Course created.');
        }

        $this->resetCourseForm();
        $this->dispatch('close-modal', 'course-form-modal');
    }

    public function transitionStatus(string $courseId, string $newStatus, TenantContext $tenantContext, CourseManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail($courseId);

        if ($newStatus === 'Archived') {
            $this->authorize('archive', $course);
        } else {
            $this->authorize('publish', $course);
        }

        $service->transitionStatus($course, $newStatus, Auth::user());

        $this->dispatch('toast', type: 'success', message: "Course status updated to {$newStatus}.");
    }

    public function openCategoriesModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', [CourseCategory::class, $academy]);

        $this->resetCategoryForm();
        $this->dispatch('open-modal', 'course-categories-modal');
    }

    public function editCategory(string $categoryId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $category = CourseCategory::query()
            ->forAcademy($academy)
            ->findOrFail($categoryId);

        $this->authorize('update', $category);

        $this->resetValidation();
        $this->editingCategoryId = (string) $category->getKey();
        $this->categoryName = (string) $category->name;
        $this->categorySlug = (string) $category->slug;
        $this->categoryDescription = (string) ($category->description ?? '');
        $this->categoryOrder = (string) ($category->display_order ?? 0);
        $this->categoryActive = (bool) $category->is_active;
    }

    public function saveCategory(TenantContext $tenantContext, CourseCategoryService $categoryService): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $actor = Auth::user();

        $payload = [
            'name' => $this->categoryName,
            'slug' => $this->categorySlug,
            'description' => $this->categoryDescription !== '' ? $this->categoryDescription : null,
            'display_order' => $this->categoryOrder !== '' ? (int) $this->categoryOrder : 0,
            'is_active' => $this->categoryActive,
        ];

        try {
            if ($this->editingCategoryId !== null) {
                $category = CourseCategory::query()
                    ->forAcademy($academy)
                    ->findOrFail($this->editingCategoryId);

                $this->authorize('update', $category);
                $categoryService->update($category, $payload, $actor);
                $this->dispatch('toast', type: 'success', message: 'Category updated.');
            } else {
                $this->authorize('create', [CourseCategory::class, $academy]);
                $categoryService->create($academy, $payload, $actor);
                $this->dispatch('toast', type: 'success', message: 'Category created.');
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $mappedField = match ($field) {
                    'name' => 'categoryName',
                    'slug' => 'categorySlug',
                    'description' => 'categoryDescription',
                    'display_order' => 'categoryOrder',
                    default => $field,
                };
                $this->addError($mappedField, $messages[0]);
            }

            return;
        }

        $this->resetCategoryForm();
    }

    public function deleteCategory(string $categoryId, TenantContext $tenantContext, CourseCategoryService $categoryService): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $category = CourseCategory::query()
            ->forAcademy($academy)
            ->findOrFail($categoryId);

        $this->authorize('delete', $category);
        $categoryService->delete($category, Auth::user());

        if ($this->editingCategoryId === $categoryId) {
            $this->resetCategoryForm();
        }

        $this->dispatch('toast', type: 'success', message: 'Category deleted.');
    }

    public function openArchiveModal(string $courseId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail($courseId);

        $this->authorize('archive', $course);

        $this->archivingCourseId = $courseId;
        $this->archivingCourse = $course;

        $this->dispatch('open-modal', 'course-archive-modal');
    }

    public function cancelArchive(): void
    {
        $this->archivingCourseId = null;
        $this->archivingCourse = null;
        $this->dispatch('close-modal', 'course-archive-modal');
    }

    public function confirmArchiveCourse(TenantContext $tenantContext, CourseManagementService $service): void
    {
        $targetId = $this->archivingCourseId ?? $this->deletingCourseId;
        if ($targetId === null) {
            return;
        }

        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail($targetId);

        $this->authorize('archive', $course);

        $service->archive($course, Auth::user());
        $this->dispatch('toast', type: 'success', message: "Course '{$course->title}' archived. New enrollments prevented while active student access is preserved.");

        $this->cancelArchive();
        if ($this->deletingCourseId !== null) {
            $this->dispatch('close-modal', 'course-delete-modal');
            $this->deletingCourseId = null;
            $this->deletingCourse = null;
            $this->deletingCourseImpact = [];
        }
        $this->resetPage();
    }

    public function restoreCourse(string $courseId, TenantContext $tenantContext, CourseManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail($courseId);

        $this->authorize('restore', $course);

        $service->restoreArchived($course, Auth::user(), 'Published');
        $this->dispatch('toast', type: 'success', message: "Course '{$course->title}' restored to Published.");
        $this->resetPage();
    }

    public function openDeleteModal(string $courseId, TenantContext $tenantContext, CourseManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->with(['modules', 'enrollments'])
            ->findOrFail($courseId);

        $this->authorize('delete', $course);

        $this->deletingCourseId = (string) $course->getKey();
        $this->deletingCourseTitle = (string) $course->title;
        $this->deletingCourse = $course;
        $this->deletingCourseImpact = $service->getImpactSummary($course);
        $this->deletingCourseEnrollmentsCount = $this->deletingCourseImpact['enrollments_count'];

        $this->dispatch('open-modal', 'course-delete-modal');
    }

    public function cancelDeleteCourse(): void
    {
        $this->deletingCourseId = null;
        $this->deletingCourseTitle = '';
        $this->deletingCourse = null;
        $this->deletingCourseImpact = [];
        $this->deletingCourseEnrollmentsCount = 0;
        $this->dispatch('close-modal', 'course-delete-modal');
    }

    public function deleteCourse(TenantContext $tenantContext, CourseManagementService $service): void
    {
        if ($this->deletingCourseId === null) {
            return;
        }

        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail($this->deletingCourseId);

        $this->authorize('delete', $course);

        try {
            $service->delete($course, Auth::user());
            $this->dispatch('toast', type: 'success', message: "Course '{$this->deletingCourseTitle}' safely deleted.");
            $this->cancelDeleteCourse();
            $this->resetPage();
        } catch (ValidationException $e) {
            $errorMsg = $e->validator->errors()->first('course') ?: $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $errorMsg);
        }
    }

    public function resetCategoryForm(): void
    {
        $this->resetValidation();
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categorySlug = '';
        $this->categoryDescription = '';
        $this->categoryOrder = '0';
        $this->categoryActive = true;
    }

    public function render(
        TenantContext $tenantContext,
        CourseManagementService $courseService,
        CourseCategoryService $categoryService,
    ) {
        $academy = $this->resolveAcademy($tenantContext);

        $courses = $courseService->listForAcademy($academy, [
            'search' => $this->search,
            'category_id' => $this->categoryId,
            'difficulty' => $this->difficulty,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'sort' => $this->sort,
        ], perPage: 12);

        $categories = $categoryService->listForAcademy($academy);

        $availableTrainers = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->where('role', 'trainer')
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy('name')
            ->values();

        $availablePrerequisites = Course::query()
            ->forAcademy($academy)
            ->when($this->editingCourseId !== null, fn ($q) => $q->where('id', '!=', $this->editingCourseId))
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('livewire.admin.courses-index', [
            'academy' => $academy,
            'courses' => $courses,
            'categories' => $categories,
            'availableTrainers' => $availableTrainers,
            'availablePrerequisites' => $availablePrerequisites,
        ]);
    }

    private function resetCourseForm(): void
    {
        $this->resetValidation();
        $this->editingCourseId = null;
        $this->title = '';
        $this->slug = '';
        $this->category_id = '';
        $this->courseDifficulty = 'Beginner';
        $this->price = '0.00';
        $this->currency = 'USD';
        $this->is_free = true;
        $this->duration_months = '0';
        $this->enrollment_limit = '';
        $this->courseVisibility = 'public';
        $this->courseStatus = 'Draft';
        $this->is_sequential = false;
        $this->description = '';
        $this->thumbnail = null;
        $this->existingThumbnailUrl = null;
        $this->trainer_ids = [];
        $this->prerequisite_ids = [];
    }

    private function resolveAcademy(TenantContext $tenantContext): Academy
    {
        $academy = $tenantContext->getAcademy();

        if ($academy === null) {
            abort(404, 'No active academy selected.');
        }

        return $academy;
    }
}
