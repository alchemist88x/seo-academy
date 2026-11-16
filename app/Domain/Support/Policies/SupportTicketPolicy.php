<?php

namespace App\Domain\Support\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'support_staff']);
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        if (! $user->is_active || $supportTicket->academy_id === null) {
            return false;
        }

        // Admin has full view access
        if ($user->hasRole('admin')) {
            return true;
        }

        // Support staff for tickets in the academy
        if ($user->hasRole('support_staff')) {
            return true;
        }

        // Ticket creator (student)
        if ((string) $supportTicket->user_id === (string) $user->getKey()) {
            return true;
        }

        return false;
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return (bool) $user->is_active;
    }

    public function update(User $user, SupportTicket $supportTicket): bool
    {
        if (! $user->is_active || $supportTicket->academy_id === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'support_staff']);
    }

    public function assign(User $user, SupportTicket $supportTicket): bool
    {
        if (! $user->is_active || $supportTicket->academy_id === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'support_staff']);
    }

    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        if (! $user->is_active || $supportTicket->academy_id === null) {
            return false;
        }

        // Admin and support staff can always reply
        if ($user->hasAnyRole(['admin', 'support_staff'])) {
            return true;
        }

        // Student creator can reply as long as ticket is not closed
        if ((string) $supportTicket->user_id === (string) $user->getKey()) {
            return $supportTicket->status !== SupportTicket::STATUS_CLOSED;
        }

        return false;
    }

    public function addInternalNote(User $user, SupportTicket $supportTicket): bool
    {
        if (! $user->is_active || $supportTicket->academy_id === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'support_staff']);
    }

    /**
     * Determine whether the user can delete the support ticket.
     * Ticket history and messages are permanently retained; only administrators
     * can delete tickets, which executes a soft delete so historical records
     * are never wiped from the database. Students cannot delete tickets.
     */
    public function delete(User $user, SupportTicket $supportTicket): bool
    {
        return (bool) $user->is_active && $user->hasRole('admin');
    }
}
