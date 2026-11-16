<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Mail\AcademyNotificationMail;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationDispatcherService
{
    /**
     * Categories where notification delivery cannot be turned off by users (security-critical).
     *
     * @var array<int, string>
     */
    private const CRITICAL_CATEGORIES = [
        'system',
        'security',
        'account',
    ];

    /**
     * Check if a notification channel is enabled for a user in a given category.
     */
    public function isChannelEnabled(User $user, string $channel, string $category): bool
    {
        if (in_array(strtolower($category), self::CRITICAL_CATEGORIES, true)) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', (string) $user->getKey())
            ->where('channel', $channel)
            ->where('category', $category)
            ->first();

        return $preference ? (bool) $preference->enabled : true;
    }

    /**
     * Dispatch an in-app and/or email notification to a recipient with preference enforcement & deduplication.
     */
    public function send(
        User $recipient,
        string $category,
        string $title,
        string $message,
        ?string $link = null,
        ?User $actor = null,
        ?string $dedupKey = null,
        int $dedupWindowSeconds = 300,
    ): ?AcademyNotification {
        $academyId = Academy::current()?->getKey() ?? Academy::query()->value('id');

        // 1. Deduplication check
        if ($dedupKey !== null || $dedupWindowSeconds > 0) {
            $threshold = Carbon::now()->subSeconds($dedupWindowSeconds);
            $alreadySent = AcademyNotification::query()
                ->where('target', (string) $recipient->getKey())
                ->where('category', $category)
                ->where('title', $title)
                ->where('created_at', '>=', $threshold)
                ->exists();

            if ($alreadySent) {
                return null;
            }
        }

        $notification = null;

        // 2. In-App Notification Delivery
        if ($this->isChannelEnabled($recipient, 'in_app', $category)) {
            $notification = AcademyNotification::create([
                'academy_id' => $academyId,
                'audience' => 'target',
                'target' => (string) $recipient->getKey(),
                'category' => $category,
                'title' => $title,
                'message' => $message,
                'created_by' => $actor?->getKey(),
                'created_at' => now(),
            ]);
        }

        // 3. Email Delivery (Safe & Non-blocking)
        if ($this->isChannelEnabled($recipient, 'email', $category) && ! empty($recipient->email)) {
            try {
                Mail::to($recipient->email)->send(
                    new AcademyNotificationMail(
                        notificationTitle: $title,
                        notificationMessage: $message,
                        actionUrl: $link,
                        actionText: $link ? 'View Notification' : null,
                    )
                );
            } catch (Throwable $e) {
                // Log delivery failure without breaking business transaction or leaking secrets
                Log::warning('Failed delivering notification email to recipient.', [
                    'recipient_id' => $recipient->getKey(),
                    'category' => $category,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    /**
     * Update or create a user's notification preference.
     */
    public function setPreference(User $user, string $channel, string $category, bool $enabled): void
    {
        if (in_array(strtolower($category), self::CRITICAL_CATEGORIES, true)) {
            return; // Critical categories are immutable
        }

        NotificationPreference::updateOrCreate(
            [
                'user_id' => (string) $user->getKey(),
                'channel' => $channel,
                'category' => $category,
            ],
            [
                'enabled' => $enabled,
            ]
        );
    }
}
