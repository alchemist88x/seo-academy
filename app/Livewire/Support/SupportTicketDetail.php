<?php

namespace App\Livewire\Support;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Services\SupportTicketService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Support Ticket Details')]
class SupportTicketDetail extends Component
{
    public string $ticketId;

    public string $replyMessage = '';

    public bool $isInternalNote = false;

    public string $selectedStatus = '';

    public string $selectedAssignee = '';

    public function mount(string $ticket, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['admin', 'support_staff'])) {
            abort(403, 'Unauthorized.');
        }

        $ticketModel = SupportTicket::query()
            ->forAcademy($academy)
            ->findOrFail($ticket);

        $this->ticketId = (string) $ticketModel->getKey();
        $this->selectedStatus = (string) $ticketModel->status;
        $this->selectedAssignee = (string) ($ticketModel->assigned_to ?? '');
    }

    public function updateStatus(SupportTicketService $service, TenantContext $tenantContext): void
    {
        $ticket = $this->getTicket($tenantContext);
        $actor = Auth::user();

        $this->validate([
            'selectedStatus' => ['required', 'string', 'in:'.implode(',', SupportTicket::STATUSES)],
        ]);

        $service->updateStatus($ticket, $this->selectedStatus, $actor);
        $this->dispatch('toast', type: 'success', message: 'Ticket status updated.');
    }

    public function updateAssignment(SupportTicketService $service, TenantContext $tenantContext): void
    {
        $ticket = $this->getTicket($tenantContext);
        $actor = Auth::user();

        $assigneeId = $this->selectedAssignee !== '' ? $this->selectedAssignee : null;

        $service->assignTicket($ticket, $assigneeId, $actor);
        $this->dispatch('toast', type: 'success', message: 'Ticket assignment updated.');
    }

    public function assignToMe(SupportTicketService $service, TenantContext $tenantContext): void
    {
        $ticket = $this->getTicket($tenantContext);
        $actor = Auth::user();

        $this->selectedAssignee = (string) $actor->getKey();
        $service->assignTicket($ticket, (string) $actor->getKey(), $actor);
        $this->dispatch('toast', type: 'success', message: 'Ticket assigned to you.');
    }

    public function sendReply(SupportTicketService $service, TenantContext $tenantContext): void
    {
        $this->validate([
            'replyMessage' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $ticket = $this->getTicket($tenantContext);
        $actor = Auth::user();

        $service->addMessage($ticket, $actor, $this->replyMessage, $this->isInternalNote);

        // If ticket was open/pending and this is a public reply, auto-transition to waiting_for_response or keep
        if (! $this->isInternalNote && in_array($ticket->status, [SupportTicket::STATUS_OPEN, 'Pending', SupportTicket::STATUS_IN_PROGRESS])) {
            $service->updateStatus($ticket, SupportTicket::STATUS_WAITING_FOR_RESPONSE, $actor);
            $this->selectedStatus = SupportTicket::STATUS_WAITING_FOR_RESPONSE;
        }

        $this->replyMessage = '';
        $this->isInternalNote = false;

        $this->dispatch('toast', type: 'success', message: $this->isInternalNote ? 'Internal note added.' : 'Reply sent.');
    }

    public function render(TenantContext $tenantContext)
    {
        $academy = $this->resolveAcademy($tenantContext);
        $user = Auth::user();

        $ticket = SupportTicket::query()
            ->forAcademy($academy)
            ->with(['user', 'assignedTo', 'messages.user'])
            ->findOrFail($this->ticketId);

        // Get staff members for assignment
        $staffUserIds = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->whereIn('role', ['admin', 'support_staff'])
            ->pluck('user_id');

        $staffUsers = User::query()
            ->whereIn('id', $staffUserIds)
            ->orderBy('name')
            ->get();

        if (! $staffUsers->contains('id', $user->getKey())) {
            $staffUsers->push($user);
        }

        return view('livewire.support.support-ticket-detail', [
            'academy' => $academy,
            'ticket' => $ticket,
            'staffUsers' => $staffUsers,
        ]);
    }

    private function getTicket(TenantContext $tenantContext): SupportTicket
    {
        $academy = $this->resolveAcademy($tenantContext);

        return SupportTicket::query()
            ->forAcademy($academy)
            ->findOrFail($this->ticketId);
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
