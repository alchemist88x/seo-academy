<?php

namespace App\Livewire\Catalogue;

use App\Domain\Courses\Models\CourseCategory;
use App\Domain\Courses\Services\CourseCategoryService;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PublicCoursesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryId = '';

    public string $difficulty = '';

    public string $sort = 'newest';

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

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryId', 'difficulty']);
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function render(
        TenantContext $tenantContext,
        CourseManagementService $courseService,
        CourseCategoryService $categoryService,
    ) {
        $academy = $tenantContext->getAcademy();

        $courses = $courseService->listPublicCatalogue($academy, [
            'search' => $this->search,
            'category_id' => $this->categoryId,
            'difficulty' => $this->difficulty,
            'sort' => $this->sort,
        ], perPage: 12);

        $categories = $academy !== null
            ? $categoryService->listForAcademy($academy, onlyActive: true)
            : CourseCategory::query()->active()->orderBy('display_order')->orderBy('name')->get();

        return view('livewire.catalogue.public-courses-index', [
            'academy' => $academy,
            'courses' => $courses,
            'categories' => $categories,
        ])->layout(Auth::check() ? 'layouts.app' : 'layouts.guest', [
            'title' => 'Course Catalogue',
        ]);
    }
}
