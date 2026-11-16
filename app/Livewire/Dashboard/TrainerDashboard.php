<?php

namespace App\Livewire\Dashboard;

use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Services\TrainerOperationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Trainer Dashboard')]
class TrainerDashboard extends Component
{
    public function render(TenantContext $tenantContext, TrainerOperationService $trainerService)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy();

        $metrics = [
            'total_assigned_courses' => 0,
            'published_courses' => 0,
            'total_active_students' => 0,
            'active_cohorts' => 0,
        ];
        $recentCourses = collect();
        $recentEnrollments = collect();

        if ($academy !== null && $user !== null) {
            $metrics = $trainerService->getMetrics($academy, $user);
            $recentCourses = $trainerService->getRecentCourseAssignments($academy, $user, 5);
            $recentEnrollments = $trainerService->getRecentEnrollmentActivity($academy, $user, 5);
        }

        $stats = [
            [
                'label' => 'Total Assigned Courses',
                'value' => (string) $metrics['total_assigned_courses'],
                'trend' => "{$metrics['published_courses']} published",
                'direction' => 'up',
            ],
            [
                'label' => 'Published Courses',
                'value' => (string) $metrics['published_courses'],
                'trend' => 'Live in catalogue',
                'direction' => 'up',
            ],
            [
                'label' => 'Active Enrolled Students',
                'value' => (string) $metrics['total_active_students'],
                'trend' => 'Currently studying',
                'direction' => 'up',
            ],
            [
                'label' => 'Active Cohorts',
                'value' => (string) $metrics['active_cohorts'],
                'trend' => 'Distinct batches',
                'direction' => 'neutral',
            ],
        ];

        return view('livewire.dashboard.trainer-dashboard', [
            'trainerName' => $user?->name ?? 'Trainer',
            'stats' => $stats,
            'metrics' => $metrics,
            'recentCourses' => $recentCourses,
            'recentEnrollments' => $recentEnrollments,
        ]);
    }
}
