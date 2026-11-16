<?php

namespace App\Livewire\Trainer;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Services\TrainerOperationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Enrolled Students')]
class TrainerStudentsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $courseId = '';

    public string $status = '';

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();

        if (! $user || ! $user->hasRole('trainer')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCourseId(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'courseId', 'status']);
        $this->resetPage();
    }

    public function render(TenantContext $tenantContext, TrainerOperationService $trainerService)
    {
        $academy = $this->resolveAcademy($tenantContext);
        $trainer = Auth::user();

        $assignedCourses = Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', function (Builder $q) use ($trainer): void {
                $q->where('users.id', $trainer->getKey());
            })
            ->orderBy('title')
            ->get();

        $enrollments = $trainerService->listAssignedStudents(
            $academy,
            $trainer,
            [
                'search' => $this->search,
                'course_id' => $this->courseId,
                'status' => $this->status,
            ],
            perPage: 15,
        );

        return view('livewire.trainer.trainer-students-index', [
            'academy' => $academy,
            'assignedCourses' => $assignedCourses,
            'enrollments' => $enrollments,
        ]);
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
