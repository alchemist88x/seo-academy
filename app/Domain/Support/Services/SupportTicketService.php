<?php

namespace App\Domain\Support\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    use RecordsAuditLog;

    public function listForStaff(
        Academy $academy,
        User $staff,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = SupportTicket::query()
            ->forAcademy($academy)
            ->with(['user', 'assignedTo', 'messages']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            if ($status === 'open') {
                $query->whereIn('status', [SupportTicket::STATUS_OPEN, 'Pending', 'open']);
            } elseif ($status === 'in_progress') {
                $query->whereIn('status', [SupportTicket::STATUS_IN_PROGRESS, 'In Progress', 'in_progress']);
            } elseif ($status === 'resolved') {
                $query->whereIn('status', [SupportTicket::STATUS_RESOLVED, 'Resolved', 'resolved']);
            } else {
                $query->where('status', $status);
            }
        }

        $priority = trim((string) ($filters['priority'] ?? ''));
        if ($priority !== '' && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        $assigned = trim((string) ($filters['assigned_to'] ?? $filters['assigned'] ?? 'all'));
        if ($assigned === 'me' || $assigned === 'mine') {
            $query->where('assigned_to', $staff->getKey());
        } elseif ($assigned === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assigned !== 'all' && $assigned !== '') {
            $query->where('assigned_to', $assigned);
        }

        return $query->latest('updated_at')->paginate($perPage);
    }

    public function listForAcademy(
        Academy $academy,
        array $filters = [],
        ?User $currentUser = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->listForStaff($academy, $currentUser ?? new User, $filters, $perPage);
    }

    public function getMetrics(Academy $academy, ?User $currentUser = null): array
    {
        $baseQuery = SupportTicket::query()->forAcademy($academy);

        $totalTickets = (clone $baseQuery)->count();
        $openTickets = (clone $baseQuery)->whereIn('status', [SupportTicket::STATUS_OPEN, 'Pending', 'open'])->count();
        $inProgressTickets = (clone $baseQuery)->whereIn('status', [SupportTicket::STATUS_IN_PROGRESS, 'In Progress', 'in_progress'])->count();
        $waitingTickets = (clone $baseQuery)->where('status', SupportTicket::STATUS_WAITING_FOR_RESPONSE)->count();
        $resolvedToday = (clone $baseQuery)
            ->where(function (Builder $q): void {
                $q->whereIn('status', [SupportTicket::STATUS_RESOLVED, 'Resolved', 'resolved']);
            })
            ->whereDate('resolved_at', Carbon::today())
            ->count();

        $assignedToMe = 0;
        if ($currentUser !== null) {
            $assignedToMe = (clone $baseQuery)
                ->where('assigned_to', $currentUser->getKey())
                ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED, 'Resolved', 'resolved', 'closed'])
                ->count();
        }

        return [
            'total' => $totalTickets,
            'open' => $openTickets,
            'in_progress' => $inProgressTickets,
            'waiting' => $waitingTickets,
            'resolved_today' => $resolvedToday,
            'assigned_to_me' => $assignedToMe,
        ];
    }

    public function assignTicket(
        SupportTicket $ticket,
        User|string|null $assignee,
        User $actor,
    ): SupportTicket {
        $assignedUser = null;
        if ($assignee instanceof User) {
            $assignedUser = $assignee;
        } elseif (is_string($assignee) && $assignee !== '') {
            $assignedUser = User::find($assignee);
        }

        return DB::transaction(function () use ($ticket, $assignedUser, $actor): SupportTicket {
            $previousAssigneeId = $ticket->assigned_to;
            $newAssigneeId = $assignedUser?->getKey();

            $ticket->update([
                'assigned_to' => $newAssigneeId,
                'assigned_at' => $newAssigneeId !== null ? now() : null,
            ]);

            $academy = $ticket->academy ?? Academy::find($ticket->academy_id);

            if ($academy) {
                $auditData = [
                    'previous_assigned_to' => $previousAssigneeId,
                    'assigned_to' => $newAssigneeId,
                    'previous_assigned_to_user_id' => $previousAssigneeId,
                    'assigned_to_user_id' => $newAssigneeId,
                    'assigned_to_name' => $assignedUser?->name ?? 'Unassigned',
                    'assigned_by' => $actor->getKey(),
                ];

                $this->recordAudit(
                    $academy,
                    $actor,
                    'support_ticket.assigned',
                    SupportTicket::class,
                    (string) $ticket->getKey(),
                    $auditData,
                );

                $this->recordAudit(
                    $academy,
                    $actor,
                    'ticket.assigned',
                    SupportTicket::class,
                    (string) $ticket->getKey(),
                    $auditData,
                );

                if ($assignedUser && (string) $assignedUser->getKey() !== (string) $actor->getKey()) {
                    AcademyNotification::create([
                        'academy_id' => $academy->getKey(),
                        'audience' => 'staff',
                        'target' => (string) $assignedUser->getKey(),
                        'category' => 'support',
                        'title' => 'Support Ticket Assigned',
                        'message' => "Support ticket \"{$ticket->subject}\" has been assigned to you by {$actor->name}.",
                        'created_by' => $actor->getKey(),
                        'created_at' => now(),
                    ]);
                }
            }

            return $ticket->fresh(['user', 'assignedTo', 'messages']);
        });
    }

    public function updateStatus(
        SupportTicket $ticket,
        string $newStatus,
        User $actor,
        ?string $reason = null,
    ): SupportTicket {
        $newStatus = strtolower(trim($newStatus));

        if (! in_array($newStatus, SupportTicket::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid status: {$newStatus}",
            ]);
        }

        if (! $ticket->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition ticket from '{$ticket->status}' to '{$newStatus}'.",
            ]);
        }

        $oldStatus = $ticket->status;
        if ($oldStatus === $newStatus) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $newStatus, $oldStatus, $actor, $reason): SupportTicket {
            $updates = ['status' => $newStatus];

            if ($newStatus === SupportTicket::STATUS_RESOLVED) {
                $updates['resolved_at'] = now();
            } elseif ($newStatus === SupportTicket::STATUS_CLOSED) {
                $updates['closed_at'] = now();
            } elseif ($newStatus === SupportTicket::STATUS_OPEN) {
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }

            $ticket->update($updates);

            $academy = $ticket->academy ?? Academy::find($ticket->academy_id);

            if ($academy) {
                $auditData = [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                    'ticket_subject' => $ticket->subject,
                ];

                $this->recordAudit(
                    $academy,
                    $actor,
                    'support_ticket.status_changed',
                    SupportTicket::class,
                    (string) $ticket->getKey(),
                    $auditData,
                );

                $this->recordAudit(
                    $academy,
                    $actor,
                    'ticket.status_changed',
                    SupportTicket::class,
                    (string) $ticket->getKey(),
                    $auditData,
                );

                if ($ticket->user_id) {
                    $isResolved = ($newStatus === SupportTicket::STATUS_RESOLVED);
                    $title = $isResolved ? 'Support Ticket Resolved' : 'Support Ticket Status Updated';
                    $notificationMsg = $isResolved
                        ? "Your ticket \"{$ticket->subject}\" has been resolved by support."
                        : "Your ticket \"{$ticket->subject}\" status has been changed to {$newStatus}.";
                    if ($reason) {
                        $notificationMsg .= " Reason: {$reason}";
                    }

                    AcademyNotification::create([
                        'academy_id' => $academy->getKey(),
                        'audience' => 'student',
                        'target' => (string) $ticket->user_id,
                        'category' => 'support',
                        'title' => $title,
                        'message' => $notificationMsg,
                        'created_by' => $actor->getKey(),
                        'created_at' => now(),
                    ]);
                }
            }

            return $ticket->fresh(['user', 'assignedTo', 'messages']);
        });
    }

    public function addReply(
        SupportTicket $ticket,
        User $author,
        string $message,
        bool $isInternal = false,
    ): SupportTicketMessage {
        $messageText = trim($message);
        if ($messageText === '') {
            throw ValidationException::withMessages([
                'message' => ['Message cannot be empty.'],
            ]);
        }

        // Only staff or admin can mark message as internal
        if ($isInternal && ! $author->hasAnyRole(['admin', 'support_staff'])) {
            $isInternal = false;
        }

        return DB::transaction(function () use ($ticket, $author, $messageText, $isInternal): SupportTicketMessage {
            $createdMessage = SupportTicketMessage::create([
                'support_ticket_id' => $ticket->getKey(),
                'user_id' => $author->getKey(),
                'author_name' => $author->name,
                'message' => $messageText,
                'is_internal' => $isInternal,
            ]);

            $ticket->update([
                'last_replied_at' => now(),
            ]);

            $academy = $ticket->academy ?? Academy::find($ticket->academy_id);

            if ($academy) {
                $auditData = [
                    'message_id' => (string) $createdMessage->getKey(),
                    'is_internal' => $isInternal,
                ];

                if ($isInternal) {
                    $this->recordAudit(
                        $academy,
                        $author,
                        'support_ticket.internal_note_added',
                        SupportTicket::class,
                        (string) $ticket->getKey(),
                        $auditData,
                    );
                    $this->recordAudit(
                        $academy,
                        $author,
                        'ticket.internal_note_added',
                        SupportTicket::class,
                        (string) $ticket->getKey(),
                        $auditData,
                    );
                } else {
                    $this->recordAudit(
                        $academy,
                        $author,
                        'support_ticket.replied',
                        SupportTicket::class,
                        (string) $ticket->getKey(),
                        $auditData,
                    );
                    $this->recordAudit(
                        $academy,
                        $author,
                        'ticket.reply_added',
                        SupportTicket::class,
                        (string) $ticket->getKey(),
                        $auditData,
                    );
                }

                if (! $isInternal) {
                    $isStaff = $author->hasAnyRole(['admin', 'support_staff', 'trainer']);
                    $isOwner = (string) $ticket->user_id === (string) $author->getKey();

                    if ($isStaff && ! $isOwner && $ticket->user_id) {
                        // Notify student
                        AcademyNotification::create([
                            'academy_id' => $academy->getKey(),
                            'audience' => 'student',
                            'target' => (string) $ticket->user_id,
                            'category' => 'support',
                            'title' => 'New Reply to Your Ticket',
                            'message' => "A support staff member replied to \"{$ticket->subject}\".",
                            'created_by' => $author->getKey(),
                            'created_at' => now(),
                        ]);
                    } elseif ($isOwner && $ticket->assigned_to) {
                        // Notify assigned staff
                        AcademyNotification::create([
                            'academy_id' => $academy->getKey(),
                            'audience' => 'staff',
                            'target' => (string) $ticket->assigned_to,
                            'category' => 'support',
                            'title' => 'New Student Reply on Ticket',
                            'message' => "Student {$author->name} replied to ticket \"{$ticket->subject}\".",
                            'created_by' => $author->getKey(),
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            return $createdMessage->fresh('user');
        });
    }

    public function addMessage(
        SupportTicket $ticket,
        User $sender,
        string $message,
        bool $isInternal = false,
    ): SupportTicketMessage {
        return $this->addReply($ticket, $sender, $message, $isInternal);
    }
}
