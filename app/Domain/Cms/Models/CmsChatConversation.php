<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsChatConversation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_chat_conversations';

    protected $fillable = [
        'academy_id',
        'session_id',
        'visitor_name',
        'visitor_email',
        'visitor_phone',
        'status', // open, waiting_for_support, assigned, waiting_for_visitor, resolved
        'handler', // ai, human, fallback
        'handoff_reason', // visitor_intent, visitor_button, ai_unavailable, ai_error, admin_takeover, staff_takeover, manual_reset
        'handoff_at',
        'assigned_to_user_id',
        'assigned_at',
        'operating_mode', // auto, ai_enabled, fallback_only, human_support
        'lead_inquiry_id',
        'last_activity_at',
        'resolved_at',
        'resolved_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
            'handoff_at' => 'datetime',
            'assigned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CmsChatMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function publicMessages(): HasMany
    {
        return $this->hasMany(CmsChatMessage::class, 'conversation_id')
            ->where('is_internal', false)
            ->orderBy('created_at', 'asc');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function leadInquiry(): BelongsTo
    {
        return $this->belongsTo(CmsContactInquiry::class, 'lead_inquiry_id');
    }

    public function scopeForAcademy(Builder $query, string $academyId): Builder
    {
        return $query->where('academy_id', $academyId);
    }

    public function scopeWaitingForSupport(Builder $query): Builder
    {
        return $query->where('status', 'waiting_for_support');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'waiting_for_support', 'assigned', 'waiting_for_visitor']);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    public function scopeHandledBy(Builder $query, string $handler): Builder
    {
        return $query->where('handler', $handler);
    }

    public function scopeAi(Builder $query): Builder
    {
        return $query->where('handler', 'ai');
    }

    public function scopeHuman(Builder $query): Builder
    {
        return $query->where('handler', 'human');
    }

    public function scopeFallback(Builder $query): Builder
    {
        return $query->where('handler', 'fallback');
    }

    public function isHandledByHuman(): bool
    {
        return $this->handler === 'human';
    }

    public function isHandledByAi(): bool
    {
        return $this->handler === 'ai';
    }

    public function isHandledByFallback(): bool
    {
        return $this->handler === 'fallback';
    }

    public function switchToHuman(string $reason, int|string|null $userId = null): self
    {
        $payload = [
            'handler' => 'human',
            'handoff_reason' => $reason,
            'handoff_at' => now(),
            'status' => $userId ? 'assigned' : 'waiting_for_support',
            'last_activity_at' => now(),
        ];

        if ($userId) {
            $payload['assigned_to_user_id'] = $userId;
            $payload['assigned_at'] = now();
        }

        $this->update($payload);

        return $this;
    }

    public function switchToAuto(): self
    {
        $this->update([
            'handler' => 'ai',
            'handoff_reason' => 'manual_reset',
            'operating_mode' => 'auto',
            'status' => 'open',
            'assigned_to_user_id' => null,
            'assigned_at' => null,
            'last_activity_at' => now(),
        ]);

        return $this;
    }

    public function markResolved(int|string|null $userId = null): self
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_user_id' => $userId,
            'last_activity_at' => now(),
        ]);

        return $this;
    }

    public function reopen(): self
    {
        $newStatus = $this->assigned_to_user_id ? 'assigned' : 'open';

        $this->update([
            'status' => $newStatus,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
            'last_activity_at' => now(),
        ]);

        return $this;
    }

    public function assignTo(int|string $userId): self
    {
        $this->update([
            'assigned_to_user_id' => $userId,
            'assigned_at' => now(),
            'handler' => 'human',
            'status' => 'assigned',
            'last_activity_at' => now(),
        ]);

        return $this;
    }
}
