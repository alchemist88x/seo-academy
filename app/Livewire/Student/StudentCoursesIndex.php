<?php

namespace App\Livewire\Student;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Courses')]
class StudentCoursesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function mount(TenantContext $tenantContext): void
    {
        $this->resolveAcademy($tenantContext);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function render(TenantContext $tenantContext)
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();
        $studentProfile = $user?->studentProfile;

        $hasAnyEnrollments = false;
        $enrollments = collect();

        if ($studentProfile) {
            $baseQuery = $studentProfile->enrollments();
            $hasAnyEnrollments = (clone $baseQuery)->exists();

            $query = $studentProfile->enrollments()
                ->with([
                    'course.category',
                    'course.trainers',
                    'course.modules.lessons',
                ]);

            if ($this->search !== '') {
                $term = trim($this->search);
                $query->whereHas('course', function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            }

            if ($this->status !== '') {
                $query->where('status', $this->status);
            }

            $enrollments = $query->latest('created_at')->paginate(12);
        }

        return view('livewire.student.student-courses-index', [
            'academy' => $academy,
            'enrollments' => $enrollments,
            'hasAnyEnrollments' => $hasAnyEnrollments,
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
