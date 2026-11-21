<?php

namespace App\Livewire\Admin\Chat;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsChatConversation;
use App\Domain\Cms\Models\CmsChatMessage;
use App\Domain\Cms\Services\ChatbotService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Live Chat Inbox & Human Support')]
class ChatConversationsInbox extends Component
{
    public ?string $academyId = null;

    // Filters & Search
    public string $statusFilter = 'all'; // all, waiting_for_support, open, assigned, resolved

    public string $search = '';

    // Selected Conversation
    public ?string $selectedConversationId = null;

    // Composer inputs
    public string $composerTab = 'reply'; // reply, note

    public string $replyMessage = '';

    public string $internalNote = '';

    // Feedback
    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public function mount(?string $conversationId = null): void
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('support_staff'))) {
            abort(403, 'Unauthorized access to live chat support inbox.');
        }

        $academy = Academy::current() ?? Academy::first();
        $this->academyId = $academy?->id;

        $targetId = $conversationId ?: request()->query('conversationId');
        if ($targetId) {
            $this->selectedConversationId = $targetId;
        } else {
            // Auto-select latest active conversation
            $latest = $this->getConversationsQuery()->first();
            $this->selectedConversationId = $latest?->id;
        }

        $this->markSelectedAsRead();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        // Keep selected conversation if still matches, else select first
        $matching = $this->getConversationsQuery()->pluck('id')->toArray();
        if (! in_array($this->selectedConversationId, $matching, true)) {
            $this->selectedConversationId = $matching[0] ?? null;
            $this->markSelectedAsRead();
        }
    }

    public function selectConversation(string $id): void
    {
        $this->selectedConversationId = $id;
        $this->replyMessage = '';
        $this->internalNote = '';
        $this->markSelectedAsRead();
    }

    public function markSelectedAsRead(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        CmsChatMessage::where('conversation_id', $this->selectedConversationId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function sendReply(): void
    {
        $this->validate([
            'replyMessage' => 'required|string|max:3000',
        ]);

        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->sendStaffReply(
                $conversation,
                auth()->user(),
                $this->replyMessage,
                request()->ip()
            );

            $this->replyMessage = '';
            $this->feedbackMessage = 'Reply sent to visitor successfully.';
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Failed to send reply: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function addNote(): void
    {
        $this->validate([
            'internalNote' => 'required|string|max:3000',
        ]);

        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->addInternalNote(
                $conversation,
                auth()->user(),
                $this->internalNote
            );

            $this->internalNote = '';
            $this->feedbackMessage = 'Internal note saved (only visible to team members).';
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Failed to add note: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function updateStatus(string $status): void
    {
        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->updateConversationStatus($conversation, $status, auth()->id());

            $this->feedbackMessage = "Conversation status updated to {$status}.";
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Failed to update status: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function assignToMe(): void
    {
        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->assignConversation($conversation, auth()->id());

            $this->feedbackMessage = 'Conversation assigned to you.';
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Assignment failed: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function assignToUser(string|int $userId): void
    {
        // Non-admin can only assign to themselves
        if (! auth()->user()->hasRole('admin') && auth()->id() !== $userId) {
            $this->feedbackMessage = 'Only administrators can reassign conversations to other team members.';
            $this->feedbackType = 'error';

            return;
        }

        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->assignConversation($conversation, $userId);

            $user = User::find($userId);
            $this->feedbackMessage = "Conversation assigned to {$user?->name}.";
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Assignment failed: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function setOperatingMode(string $mode): void
    {
        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        if (! in_array($mode, ['auto', 'ai_enabled', 'fallback_only', 'human_support'], true)) {
            return;
        }

        $conversation->update(['operating_mode' => $mode]);
        $this->feedbackMessage = "Operating mode for this conversation switched to {$mode}.";
        $this->feedbackType = 'success';
    }

    public function takeOverConversation(): void
    {
        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->takeoverConversation($conversation, auth()->user());

            $this->feedbackMessage = 'You have taken over this conversation. It is now handled by human support.';
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Failed to take over: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function returnToAutoMode(): void
    {
        $conversation = $this->getSelectedConversation();
        if (! $conversation) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $service->returnToAutoMode($conversation, auth()->user());

            $this->feedbackMessage = 'Conversation returned to Auto mode.';
            $this->feedbackType = 'success';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Failed to return to auto mode: '.$e->getMessage();
            $this->feedbackType = 'error';
        }
    }

    public function refreshConversations(): void
    {
        // Polling tick - re-reads without resetting active draft input
        $this->markSelectedAsRead();
    }

    public function getSelectedConversation(): ?CmsChatConversation
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        return CmsChatConversation::with(['messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }, 'assignedTo', 'resolvedBy', 'leadInquiry'])->find($this->selectedConversationId);
    }

    protected function getConversationsQuery()
    {
        $query = CmsChatConversation::query()
            ->with(['assignedTo', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->when($this->academyId, fn ($q) => $q->where('academy_id', $this->academyId));

        // Status or Handler Filter
        if ($this->statusFilter === 'waiting_for_support') {
            $query->where('status', 'waiting_for_support');
        } elseif ($this->statusFilter === 'open') {
            $query->whereIn('status', ['open', 'waiting_for_support', 'assigned', 'waiting_for_visitor']);
        } elseif ($this->statusFilter === 'assigned') {
            $query->where('status', 'assigned');
        } elseif ($this->statusFilter === 'resolved') {
            $query->where('status', 'resolved');
        } elseif ($this->statusFilter === 'ai') {
            $query->where('handler', 'ai');
        } elseif ($this->statusFilter === 'human') {
            $query->where('handler', 'human');
        } elseif ($this->statusFilter === 'fallback') {
            $query->where('handler', 'fallback');
        }

        // Search filter
        if (! empty($this->search)) {
            $cleanSearch = trim($this->search);
            $query->where(function ($q) use ($cleanSearch) {
                $q->where('visitor_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('visitor_email', 'like', "%{$cleanSearch}%")
                    ->orWhere('visitor_phone', 'like', "%{$cleanSearch}%")
                    ->orWhere('session_id', 'like', "%{$cleanSearch}%")
                    ->orWhereHas('messages', function ($mq) use ($cleanSearch) {
                        $mq->where('message', 'like', "%{$cleanSearch}%");
                    });
            });
        }

        return $query->orderBy('last_activity_at', 'desc')->orderBy('created_at', 'desc');
    }

    public function getStaffUsers(): Collection
    {
        return User::role(['admin', 'support_staff'])->orderBy('name', 'asc')->get();
    }

    public function getStatsProperty(): array
    {
        $base = CmsChatConversation::query()
            ->when($this->academyId, fn ($q) => $q->where('academy_id', $this->academyId));

        return [
            'total' => (clone $base)->count(),
            'ai' => (clone $base)->where('handler', 'ai')->count(),
            'human' => (clone $base)->where('handler', 'human')->count(),
            'fallback' => (clone $base)->where('handler', 'fallback')->count(),
            'waiting' => (clone $base)->where('status', 'waiting_for_support')->count(),
            'open' => (clone $base)->whereIn('status', ['open', 'waiting_for_support', 'assigned', 'waiting_for_visitor'])->count(),
            'assigned' => (clone $base)->where('status', 'assigned')->count(),
            'resolved' => (clone $base)->where('status', 'resolved')->count(),
        ];
    }

    public function render(): View
    {
        $conversations = $this->getConversationsQuery()->limit(50)->get();
        $selected = $this->getSelectedConversation();
        $staffUsers = $this->getStaffUsers();

        return view('livewire.admin.chat.chat-conversations-inbox', [
            'conversations' => $conversations,
            'selected' => $selected,
            'staffUsers' => $staffUsers,
            'stats' => $this->stats,
        ]);
    }
}
