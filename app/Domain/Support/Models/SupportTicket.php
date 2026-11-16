<?php

namespace App\Domain\Support\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use BelongsToAcademy, HasUuids, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_FOR_RESPONSE = 'waiting_for_response';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_FOR_RESPONSE,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_WAITING_FOR_RESPONSE, self::STATUS_RESOLVED, self::STATUS_CLOSED],
        self::STATUS_IN_PROGRESS => [self::STATUS_WAITING_FOR_RESPONSE, self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_OPEN],
        self::STATUS_WAITING_FOR_RESPONSE => [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_OPEN],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED, self::STATUS_OPEN, self::STATUS_IN_PROGRESS],
        self::STATUS_CLOSED => [self::STATUS_OPEN, self::STATUS_IN_PROGRESS],
        'pending' => [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_FOR_RESPONSE, self::STATUS_RESOLVED, self::STATUS_CLOSED],
        'Pending' => [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_FOR_RESPONSE, self::STATUS_RESOLVED, self::STATUS_CLOSED],
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        $current = strtolower($this->status);
        $target = strtolower($newStatus);

        if ($current === $target) {
            return true;
        }

        $allowed = self::TRANSITIONS[$this->status] ?? self::TRANSITIONS[$current] ?? [];

        return in_array($target, array_map('strtolower', $allowed), true);
    }

    protected $fillable = [
        'user_id',
        'academy_id',
        'subject',
        'description',
        'module_name',
        'status',
        'priority',
        'assigned_to',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'last_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function assignedToUserId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['assigned_to'] ?? null,
            set: fn ($value) => ['assigned_to' => $value],
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'support_ticket_id')->orderBy('created_at', 'asc');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, 'Pending']);
    }

    public function scopeOpenOnly(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, 'Pending']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_FOR_RESPONSE, 'Pending']);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }
}
