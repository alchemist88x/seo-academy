<?php

namespace App\Livewire\Notifications;

use App\Domain\Notifications\Services\NotificationDispatcherService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Notification Preferences')]
class NotificationPreferences extends Component
{
    /**
     * @var array<string, array<string, bool>>
     */
    public array $preferences = [];

    public function mount(NotificationDispatcherService $dispatcher): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $categories = ['enrollment', 'support', 'course'];
        $channels = ['in_app', 'email'];

        foreach ($categories as $category) {
            foreach ($channels as $channel) {
                $this->preferences[$category][$channel] = $dispatcher->isChannelEnabled($user, $channel, $category);
            }
        }
    }

    public function toggle(string $category, string $channel, NotificationDispatcherService $dispatcher): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $current = $this->preferences[$category][$channel] ?? true;
        $newValue = ! $current;

        $this->preferences[$category][$channel] = $newValue;
        $dispatcher->setPreference($user, $channel, $category, $newValue);

        $this->dispatch('preference-saved');
    }

    public function render(): View
    {
        return view('livewire.notifications.notification-preferences');
    }
}
