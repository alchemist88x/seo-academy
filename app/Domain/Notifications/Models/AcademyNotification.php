<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyNotification extends Model
{
    use BelongsToAcademy, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'academy_id',
        'audience',
        'target',
        'category',
        'title',
        'message',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class, 'notification_id');
    }

    /**
     * Scope notifications visible to a specific user based on direct target or role audience.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        $userId = (string) $user->getKey();
        $roles = $user->roles()->pluck('name')->all();

        return $query->where(function (Builder $q) use ($userId, $roles) {
            $q->where('target', $userId)
                ->orWhere(function (Builder $sub) use ($roles) {
                    $sub->where('audience', 'all');

                    if (in_array('student', $roles, true)) {
                        $sub->orWhere('audience', 'student');
                    }

                    if (in_array('trainer', $roles, true)) {
                        $sub->orWhere('audience', 'trainer')
                            ->orWhere('audience', 'staff');
                    }

                    if (in_array('support_staff', $roles, true)) {
                        $sub->orWhere('audience', 'staff');
                    }

                    if (in_array('admin', $roles, true)) {
                        $sub->orWhere('audience', 'admin')
                            ->orWhere('audience', 'staff');
                    }
                });
        });
    }

    /**
     * Scope unread notifications for a specific user.
     */
    public function scopeUnreadForUser(Builder $query, User $user): Builder
    {
        $userId = (string) $user->getKey();

        return $query->whereDoesntHave('reads', function (Builder $q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Determine if this notification has been read by the given user.
     */
    public function isReadBy(User $user): bool
    {
        $userId = (string) $user->getKey();

        return $this->reads()->where('user_id', $userId)->exists();
    }

    /**
     * Mark this notification as read by the given user.
     */
    public function markAsReadFor(User $user): void
    {
        NotificationRead::firstOrCreate(
            [
                'notification_id' => $this->id,
                'user_id' => (string) $user->getKey(),
            ],
            [
                'read_at' => now(),
            ]
        );
    }
}
