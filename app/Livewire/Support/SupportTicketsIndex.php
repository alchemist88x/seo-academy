<?php

namespace App\Livewire\Support;

use App\Domain\Academies\Models\Academy;
use App\Domain\Support\Services\SupportTicketService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Support Tickets')]
class SupportTicketsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $priority = '';

    public string $assigned = 'all';

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['admin', 'support_staff'])) {
            abort(403, 'Unauthorized.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPriority(): void
    {
        $this->resetPage();
    }

    public function updatingAssigned(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'priority', 'assigned']);
        $this->resetPage();
    }

    public function render(TenantContext $tenantContext, SupportTicketService $ticketService)
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();

        $tickets = $ticketService->listForAcademy(
            $academy,
            [
                'search' => $this->search,
                'status' => $this->status,
                'priority' => $this->priority,
                'assigned' => $this->assigned,
            ],
            currentUser: $user,
            perPage: 15,
        );

        $metrics = $ticketService->getMetrics($academy, $user);

        return view('livewire.support.support-tickets-index', [
            'academy' => $academy,
            'tickets' => $tickets,
            'metrics' => $metrics,
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
