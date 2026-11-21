<?php

namespace App\Livewire\Trainer;

use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Projects\Services\ProjectAnalyticsService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Trainer Projects & Evaluations')]
class TrainerProjectsIndex extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';

    public string $search = '';

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('trainer') && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized.');
        }
    }

    public function render(PracticalProjectService $projectService, ProjectAnalyticsService $analyticsService)
    {
        $user = Auth::user();
        $analytics = $analyticsService->getTrainerProjectAnalytics($user);

        // Fetch accessible projects for trainer
        $projectIds = $user->hasRole('admin')
            ? PracticalProject::pluck('id')->all()
            : $projectService->getProjectsForTrainer($user)->pluck('id')->all();

        $query = ProjectSubmission::query()
            ->whereIn('project_id', $projectIds)
            ->with(['project.course', 'studentProfile.user', 'latestEvaluation'])
            ->latest('submitted_at');

        if ($this->statusFilter === 'pending') {
            $query->whereIn('status', [ProjectSubmission::STATUS_SUBMITTED, ProjectSubmission::STATUS_RESUBMITTED, ProjectSubmission::STATUS_UNDER_REVIEW]);
        } elseif ($this->statusFilter === 'revision_requested') {
            $query->where('status', ProjectSubmission::STATUS_REVISION_REQUESTED);
        } elseif ($this->statusFilter === 'approved') {
            $query->where('status', ProjectSubmission::STATUS_APPROVED);
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('studentProfile.user', function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                })->orWhereHas('project', function ($sub) {
                    $sub->where('title', 'like', '%'.$this->search.'%');
                });
            });
        }

        $submissions = $query->paginate(15);

        return view('livewire.trainer.trainer-projects-index', [
            'submissions' => $submissions,
            'analytics' => $analytics,
        ]);
    }
}
