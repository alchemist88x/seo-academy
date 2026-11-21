<?php

namespace App\Domain\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsChatMessage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_chat_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type', // visitor, bot, admin, support_staff, system
        'sender_id',
        'sender_name',
        'message',
        'is_internal',
        'source', // ai, faq, fallback, human, system, lead_capture
        'structured_data',
        'is_read',
        'client_ip',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_read' => 'boolean',
            'structured_data' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CmsChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function scopePublicOnly(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternalOnly(Builder $query): Builder
    {
        return $query->where('is_internal', true);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function isFromStaff(): bool
    {
        return in_array($this->sender_type, ['admin', 'support_staff'], true);
    }
}
