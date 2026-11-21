<?php

namespace App\Livewire\Admin\Projects;

use App\Domain\Academies\Models\Academy;
use App\Domain\Projects\Models\PracticalProject;
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
#[Title('Practical SEO Projects Management')]
class AdminProjectsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = 'all';

    public string $difficultyFilter = 'all';

    public string $statusFilter = 'all';

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function publish(string $id, PracticalProjectService $service): void
    {
        $project = PracticalProject::findOrFail($id);
        $service->publishProject($project, Auth::user());
        session()->flash('message', "Project \"{$project->title}\" has been published.");
    }

    public function archive(string $id, PracticalProjectService $service): void
    {
        $project = PracticalProject::findOrFail($id);
        $service->archiveProject($project, Auth::user());
        session()->flash('message', "Project \"{$project->title}\" has been archived.");
    }

    public function delete(string $id): void
    {
        $project = PracticalProject::findOrFail($id);
        $title = $project->title;
        $project->delete();
        session()->flash('message', "Project \"{$title}\" has been deleted.");
    }

    public function render(ProjectAnalyticsService $analyticsService)
    {
        $academy = Academy::current();
        $kpis = $analyticsService->getAcademyProjectKpis($academy);

        $query = PracticalProject::query()
            ->with(['course', 'criteria'])
            ->withCount(['submissions', 'achievements']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->difficultyFilter !== 'all') {
            $query->where('difficulty', $this->difficultyFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $projects = $query->latest()->paginate(10);

        return view('livewire.admin.projects.admin-projects-index', [
            'projects' => $projects,
            'kpis' => $kpis,
            'categories' => PracticalProject::CATEGORIES,
            'difficulties' => PracticalProject::DIFFICULTIES,
        ]);
    }
}
