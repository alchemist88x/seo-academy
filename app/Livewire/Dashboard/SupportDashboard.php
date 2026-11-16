<?php

namespace App\Livewire\Dashboard;

use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Services\SupportTicketService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Support Dashboard')]
class SupportDashboard extends Component
{
    public function render(TenantContext $tenantContext, SupportTicketService $ticketService)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy();

        $metrics = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'waiting' => 0,
            'resolved_today' => 0,
            'assigned_to_me' => 0,
        ];
        $recentOpenTickets = collect();
        $urgentTickets = collect();

        if ($academy !== null && $user !== null) {
            $metrics = $ticketService->getMetrics($academy, $user);

            $recentOpenTickets = SupportTicket::query()
                ->forAcademy($academy)
                ->whereIn('status', [SupportTicket::STATUS_OPEN, 'Pending'])
                ->with(['user', 'assignedTo'])
                ->latest()
                ->take(5)
                ->get();

            $urgentTickets = SupportTicket::query()
                ->forAcademy($academy)
                ->where('priority', SupportTicket::PRIORITY_URGENT)
                ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED, 'Resolved'])
                ->with(['user', 'assignedTo'])
                ->latest()
                ->take(5)
                ->get();
        }

        $stats = [
            [
                'label' => 'Total Tickets',
                'value' => (string) $metrics['total'],
                'trend' => 'All time in academy',
                'direction' => 'neutral',
            ],
            [
                'label' => 'Open Tickets',
                'value' => (string) $metrics['open'],
                'trend' => "{$metrics['in_progress']} in progress",
                'direction' => $metrics['open'] > 5 ? 'down' : 'up',
            ],
            [
                'label' => 'In Progress',
                'value' => (string) $metrics['in_progress'],
                'trend' => 'Being handled',
                'direction' => 'neutral',
            ],
            [
                'label' => 'Resolved Today',
                'value' => (string) $metrics['resolved_today'],
                'trend' => 'Completed today',
                'direction' => 'up',
            ],
            [
                'label' => 'Assigned to Me',
                'value' => (string) $metrics['assigned_to_me'],
                'trend' => 'Your active tickets',
                'direction' => 'neutral',
            ],
        ];

        return view('livewire.dashboard.support-dashboard', [
            'staffName' => $user?->name ?? 'Support Staff',
            'stats' => $stats,
            'metrics' => $metrics,
            'recentOpenTickets' => $recentOpenTickets,
            'urgentTickets' => $urgentTickets,
        ]);
    }
}
