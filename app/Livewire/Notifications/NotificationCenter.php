<?php

namespace App\Livewire\Notifications;

use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Notifications\Models\NotificationRead;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notification Center')]
class NotificationCenter extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all'; // all, unread, enrollment, support, course, system

    #[Url]
    public string $search = '';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function markAsRead(string $notificationId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        // Verify the notification belongs to this user or their audience
        $notification = AcademyNotification::query()
            ->forUser($user)
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->markAsReadFor($user);
            $this->dispatch('notification-marked-read');
        }
    }

    public function markAllAsRead(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $unreadNotifications = AcademyNotification::query()
            ->forUser($user)
            ->unreadForUser($user)
            ->pluck('id');

        $now = now();
        $inserts = [];
        foreach ($unreadNotifications as $id) {
            $inserts[] = [
                'notification_id' => $id,
                'user_id' => (string) $user->getKey(),
                'read_at' => $now,
            ];
        }

        if (! empty($inserts)) {
            // Bulk insert reads
            foreach (array_chunk($inserts, 100) as $chunk) {
                NotificationRead::upsert($chunk, ['notification_id', 'user_id'], ['read_at']);
            }
        }

        $this->dispatch('notifications-updated');
    }

    public function render(): View
    {
        $user = Auth::user();

        $baseQuery = AcademyNotification::query()
            ->forUser($user)
            ->orderBy('created_at', 'desc');

        $unreadCount = (clone $baseQuery)->unreadForUser($user)->count();

        if ($this->filter === 'unread') {
            $baseQuery->unreadForUser($user);
        } elseif ($this->filter !== 'all') {
            $baseQuery->where('category', $this->filter);
        }

        if (trim($this->search) !== '') {
            $baseQuery->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('message', 'like', '%'.$this->search.'%');
            });
        }

        $notifications = $baseQuery->paginate(12);

        // Preload read status for the current user
        $readNotificationIds = NotificationRead::query()
            ->where('user_id', (string) $user->getKey())
            ->whereIn('notification_id', $notifications->pluck('id'))
            ->pluck('notification_id')
            ->flip()
            ->all();

        return view('livewire.notifications.notification-center', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'readNotificationIds' => $readNotificationIds,
        ]);
    }
}
