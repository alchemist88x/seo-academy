<?php

namespace App\Livewire\Dashboard;

use App\Domain\Academies\Services\AcademySettingsService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Admin Dashboard')]
class AdminDashboard extends Component
{
    public function render(TenantContext $tenantContext, AcademySettingsService $settingsService)
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $academy = $tenantContext->getAcademy()
            ?? $user->academies()->where('academies.is_active', true)->first();

        if ($academy !== null) {
            $metrics = $settingsService->dashboardMetrics($academy);
            $draftCourses = max(0, $metrics['total_courses'] - $metrics['published_courses']);

            $stats = [
                [
                    'label' => 'Active Students',
                    'value' => (string) $metrics['active_students'],
                    'trend' => $metrics['active_enrollments'].' active enrollments',
                    'direction' => $metrics['active_students'] > 0 ? 'up' : 'neutral',
                ],
                [
                    'label' => 'Published Courses',
                    'value' => (string) $metrics['published_courses'],
                    'trend' => $draftCourses.' draft / '.$metrics['total_courses'].' total',
                    'direction' => $metrics['published_courses'] > 0 ? 'up' : 'neutral',
                ],
                [
                    'label' => 'Trainers',
                    'value' => (string) $metrics['trainers_count'],
                    'trend' => 'Assigned instructors',
                    'direction' => 'neutral',
                ],
                [
                    'label' => 'Open Support Tickets',
                    'value' => (string) $metrics['open_tickets'],
                    'trend' => 'Pending & In Progress',
                    'direction' => $metrics['open_tickets'] > 0 ? 'down' : 'neutral',
                ],
            ];

            return view('livewire.dashboard.admin-dashboard', [
                'academy' => $academy,
                'stats' => $stats,
                'recentAuditLogs' => $metrics['recent_audit_logs'],
            ]);
        }

        $stats = [
            ['label' => 'Active Students', 'value' => '0', 'trend' => 'No academy configured', 'direction' => 'neutral'],
            ['label' => 'Published Courses', 'value' => '0', 'trend' => '0 draft / 0 total', 'direction' => 'neutral'],
            ['label' => 'Trainers', 'value' => '0', 'trend' => 'Assigned instructors', 'direction' => 'neutral'],
            ['label' => 'Open Support Tickets', 'value' => '0', 'trend' => 'Pending & In Progress', 'direction' => 'neutral'],
        ];

        return view('livewire.dashboard.admin-dashboard', [
            'academy' => null,
            'stats' => $stats,
            'recentAuditLogs' => collect(),
        ]);
    }
}
