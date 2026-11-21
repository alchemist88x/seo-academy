<?php

namespace App\Livewire\Student;

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

#[Layout('layouts.app')]
#[Title('My SEO Practical Projects')]
class StudentProjectsIndex extends Component
{
    public string $statusFilter = 'all';

    public string $categoryFilter = 'all';

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('student')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function render(PracticalProjectService $projectService, ProjectAnalyticsService $analyticsService)
    {
        $user = Auth::user();
        $profile = $user->studentProfile;

        if (! $profile) {
            return view('livewire.student.student-projects-index', [
                'projects' => collect(),
                'metrics' => [
                    'assigned_count' => 0,
                    'submitted_count' => 0,
                    'approved_count' => 0,
                    'revisions_requested_count' => 0,
                    'average_score_percent' => 0,
                    'skills_demonstrated' => [],
                ],
                'categories' => PracticalProject::CATEGORIES,
            ]);
        }

        $metrics = $analyticsService->getStudentProjectPerformance($profile);

        // Fetch available projects for this student
        $query = $projectService->getAvailableProjectsForStudent($profile)
            ->with(['course', 'criteria']);

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        $projects = $query->latest()->get();

        // Attach latest submission for this student to each project
        $studentSubmissions = ProjectSubmission::query()
            ->where('user_id', $user->getKey())
            ->latest('version_number')
            ->get()
            ->groupBy('project_id');

        $decoratedProjects = $projects->map(function ($project) use ($studentSubmissions) {
            $submissions = $studentSubmissions->get($project->id, collect());
            $latest = $submissions->first();

            $status = 'not_started';
            if ($latest) {
                if ($latest->isApproved()) {
                    $status = 'approved';
                } elseif ($latest->isRevisionRequested()) {
                    $status = 'revision_requested';
                } elseif (in_array($latest->status, [ProjectSubmission::STATUS_SUBMITTED, ProjectSubmission::STATUS_RESUBMITTED, ProjectSubmission::STATUS_UNDER_REVIEW], true)) {
                    $status = 'submitted';
                }
            }

            return [
                'project' => $project,
                'latest_submission' => $latest,
                'status' => $status,
            ];
        });

        // Filter by tab status
        if ($this->statusFilter === 'in_progress') {
            $decoratedProjects = $decoratedProjects->where('status', 'not_started');
        } elseif ($this->statusFilter === 'submitted') {
            $decoratedProjects = $decoratedProjects->where('status', 'submitted');
        } elseif ($this->statusFilter === 'revision_requested') {
            $decoratedProjects = $decoratedProjects->where('status', 'revision_requested');
        } elseif ($this->statusFilter === 'approved') {
            $decoratedProjects = $decoratedProjects->where('status', 'approved');
        }

        return view('livewire.student.student-projects-index', [
            'decoratedProjects' => $decoratedProjects,
            'metrics' => $metrics,
            'categories' => PracticalProject::CATEGORIES,
        ]);
    }
}
