<?php

namespace App\Livewire\Trainer;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Services\CourseCategoryService;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Courses')]
class TrainerCoursesIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $difficulty = '';

    public ?string $editingCourseId = null;

    public string $title = '';

    public string $category_id = '';

    public string $courseDifficulty = 'Beginner';

    public string $duration_months = '0';

    public string $description = '';

    public $thumbnail = null;

    public ?string $existingThumbnailUrl = null;

    public function mount(TenantContext $tenantContext): void
    {
        $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', Course::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDifficulty(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'difficulty']);
        $this->resetPage();
    }

    public function openEditModal(string $courseId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail($courseId);

        $this->authorize('update', $course);

        $this->resetValidation();
        $this->editingCourseId = (string) $course->getKey();
        $this->title = (string) $course->title;
        $this->category_id = (string) ($course->category_id ?? '');
        $this->courseDifficulty = (string) ($course->difficulty ?? 'Beginner');
        $this->duration_months = (string) ($course->duration_months ?? 0);
        $this->description = (string) ($course->description ?? '');
        $this->thumbnail = null;
        $this->existingThumbnailUrl = $course->thumbnailUrl();

        $this->dispatch('open-modal', 'trainer-course-modal');
    }

    public function saveCourse(TenantContext $tenantContext, CourseManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $course = Course::query()
            ->forAcademy($academy)
            ->findOrFail((string) $this->editingCourseId);

        $this->authorize('update', $course);

        if ($this->thumbnail !== null) {
            $this->validate([
                'thumbnail' => ['image', 'max:2048'],
            ]);
        }

        $service->update($course, [
            'title' => $this->title,
            'category_id' => $this->category_id !== '' ? $this->category_id : null,
            'difficulty' => $this->courseDifficulty,
            'duration_months' => $this->duration_months !== '' ? (int) $this->duration_months : 0,
            'description' => $this->description !== '' ? $this->description : null,
        ], $this->thumbnail, Auth::user());

        $this->resetForm();
        $this->dispatch('close-modal', 'trainer-course-modal');
        $this->dispatch('toast', type: 'success', message: 'Course updated.');
    }

    public function render(
        TenantContext $tenantContext,
        CourseManagementService $courseService,
        CourseCategoryService $categoryService,
    ) {
        $academy = $this->resolveAcademy($tenantContext);
        $trainer = Auth::user();

        $courses = $courseService->listForAcademy($academy, [
            'search' => $this->search,
            'status' => $this->status,
            'difficulty' => $this->difficulty,
        ], assignedTrainer: $trainer, perPage: 12);

        $categories = $categoryService->listForAcademy($academy, onlyActive: true);

        return view('livewire.trainer.trainer-courses-index', [
            'academy' => $academy,
            'courses' => $courses,
            'categories' => $categories,
        ]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingCourseId = null;
        $this->title = '';
        $this->category_id = '';
        $this->courseDifficulty = 'Beginner';
        $this->duration_months = '0';
        $this->description = '';
        $this->thumbnail = null;
        $this->existingThumbnailUrl = null;
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
