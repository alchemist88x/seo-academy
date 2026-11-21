<?php

namespace App\Livewire\Student;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Learning')]
class StudentCoursesIndex extends Component
{
    use WithPagination;

    public string $tab = 'all'; // all | in_progress | completed

    public string $search = '';

    public string $sortBy = 'recent'; // recent | title | progress

    public function mount(TenantContext $tenantContext): void
    {
        $this->resolveAcademy($tenantContext);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['all', 'in_progress', 'completed'], true)) {
            $this->tab = $tab;
            $this->resetPage();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'tab', 'sortBy']);
        $this->resetPage();
    }

    public function render(TenantContext $tenantContext, CourseNavigationService $navigationService)
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();
        $studentProfile = $user?->studentProfile;

        $hasAnyEnrollments = false;
        $totalCount = 0;
        $inProgressCount = 0;
        $completedCount = 0;

        if ($studentProfile) {
            $baseEnrollments = $studentProfile->enrollments()
                ->with([
                    'course.category',
                    'course.trainers',
                    'course.modules.lessons',
                ])
                ->get();

            $hasAnyEnrollments = $baseEnrollments->isNotEmpty();
            $totalCount = $baseEnrollments->count();
            $inProgressCount = $baseEnrollments->where('status', 'active')->count();
            $completedCount = $baseEnrollments->where('status', 'completed')->count();

            // Enrich enrollments with calculated progress and resume lesson
            $enriched = $baseEnrollments->map(function (Enrollment $enrollment) use ($user, $navigationService) {
                $course = $enrollment->course;
                $totalLessons = $course ? $course->modules->flatMap->lessons->count() : 0;

                $completedLessons = LessonProgress::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('status', 'completed')
                    ->count();

                $progressPercent = $totalLessons > 0 ? (int) round(($completedLessons / $totalLessons) * 100) : 0;
                if ($enrollment->status === 'completed') {
                    $progressPercent = 100;
                }

                $resumeLesson = null;
                if ($course && $user && $enrollment->status === 'active') {
                    $resumeLesson = $navigationService->resolveResumeLesson($course, $user);
                }

                $cert = null;
                if ($enrollment->status === 'completed') {
                    $cert = Certificate::query()
                        ->where('user_id', $user?->id)
                        ->where('course_id', $enrollment->course_id)
                        ->where('status', 'Issued')
                        ->first();
                }

                return [
                    'enrollment' => $enrollment,
                    'course' => $course,
                    'status' => strtolower((string) ($enrollment->status ?? 'pending')),
                    'total_lessons' => $totalLessons,
                    'completed_lessons' => $completedLessons,
                    'progress_percent' => $progressPercent,
                    'resume_lesson' => $resumeLesson,
                    'certificate' => $cert,
                ];
            });

            // Filter by Tab
            if ($this->tab === 'in_progress') {
                $enriched = $enriched->filter(fn ($item) => $item['status'] === 'active');
            } elseif ($this->tab === 'completed') {
                $enriched = $enriched->filter(fn ($item) => $item['status'] === 'completed');
            }

            // Filter by Search Query
            if ($this->search !== '') {
                $term = strtolower(trim($this->search));
                $enriched = $enriched->filter(function ($item) use ($term) {
                    $title = strtolower($item['course']?->title ?? '');
                    $desc = strtolower($item['course']?->description ?? '');
                    $cat = strtolower($item['course']?->category?->name ?? '');

                    return str_contains($title, $term) || str_contains($desc, $term) || str_contains($cat, $term);
                });
            }

            // Sort
            if ($this->sortBy === 'title') {
                $enriched = $enriched->sortBy(fn ($item) => strtolower($item['course']?->title ?? ''));
            } elseif ($this->sortBy === 'progress') {
                $enriched = $enriched->sortByDesc('progress_percent');
            } else {
                // 'recent': sort by last_active_at, then updated_at
                $enriched = $enriched->sortByDesc(fn ($item) => $item['enrollment']->last_active_at ?? $item['enrollment']->updated_at);
            }

            // Paginate manually
            $page = LengthAwarePaginator::resolveCurrentPage('page');
            $perPage = 9;
            $items = $enriched->values();
            $paginated = new LengthAwarePaginator(
                $items->forPage($page, $perPage),
                $items->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']
            );
        } else {
            $paginated = new LengthAwarePaginator([], 0, 9);
        }

        return view('livewire.student.student-courses-index', [
            'academy' => $academy,
            'paginatedEnrollments' => $paginated,
            'hasAnyEnrollments' => $hasAnyEnrollments,
            'totalCount' => $totalCount,
            'inProgressCount' => $inProgressCount,
            'completedCount' => $completedCount,
        ]);
    }

    private function resolveAcademy(TenantContext $tenantContext): Academy
    {
        $academy = $tenantContext->getAcademy() ?? Academy::current();

        if ($academy === null) {
            abort(404, 'No active academy selected.');
        }

        return $academy;
    }
}
