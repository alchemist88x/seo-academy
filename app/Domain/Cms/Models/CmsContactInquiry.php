<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CmsContactInquiry extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_contact_inquiries';

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_CONVERTED,
        self::STATUS_CLOSED,
    ];

    public const SOURCE_CONTACT_FORM = 'contact_form';

    public const SOURCE_CHATBOT = 'chatbot';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'academy_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'interested_service',
        'status',
        'source',
        'conversation_id',
        'assigned_to_user_id',
        'follow_up_date',
        'phone_country_code',
        'phone_formatted',
        'course_interest',
        'consent_text',
        'consented_at',
        'consent_version',
        'activity_history',
        'ip_address',
        'user_agent',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'consented_at' => 'datetime',
            'activity_history' => 'array',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CmsChatConversation::class, 'conversation_id');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status && $status !== 'all' && in_array($status, self::STATUSES, true)) {
            return $query->where('status', $status);
        }

        return $query;
    }

    public function scopeSource(Builder $query, ?string $source): Builder
    {
        if ($source && $source !== 'all') {
            return $query->where('source', $source);
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! empty($search)) {
            $term = '%'.trim($search).'%';

            return $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('phone_formatted', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('course_interest', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * Log an action in the chronological activity history JSON.
     */
    public function logActivity(string $action, string $description, ?User $actor = null): self
    {
        $history = $this->activity_history ?? [];
        if (! is_array($history)) {
            $history = [];
        }

        $history[] = [
            'id' => (string) Str::uuid(),
            'action' => $action,
            'description' => $description,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'System',
            'actor_role' => $actor?->role,
            'created_at' => Carbon::now()->toIso8601String(),
        ];

        $this->activity_history = $history;

        return $this;
    }

    /**
     * Assign inquiry to staff member.
     */
    public function assignTo(?User $user, ?User $actor = null): bool
    {
        $oldAssignee = $this->assignedTo?->name ?? 'Unassigned';
        $newAssignee = $user?->name ?? 'Unassigned';

        $this->assigned_to_user_id = $user?->id;
        $this->logActivity('assigned', "Assigned from {$oldAssignee} to {$newAssignee}", $actor);

        return $this->save();
    }

    /**
     * Update inquiry status.
     */
    public function updateStatus(string $status, ?User $actor = null): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = $status;
        $this->logActivity('status_changed', "Status changed from {$oldStatus} to {$status}", $actor);

        return $this->save();
    }

    /**
     * Add an internal note.
     */
    public function addInternalNote(string $note, ?User $actor = null): bool
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i');
        $author = $actor?->name ?? 'Staff';
        $entry = "[{$timestamp} by {$author}]\n".trim($note)."\n\n";

        $this->admin_notes = $this->admin_notes ? ($this->admin_notes.$entry) : $entry;
        $this->logActivity('note_added', 'Added internal note: "'.Str::limit(trim($note), 40).'"', $actor);

        return $this->save();
    }

    /**
     * Set or update follow up date.
     */
    public function setFollowUpDate(?string $date, ?User $actor = null): bool
    {
        $this->follow_up_date = $date ? Carbon::parse($date)->toDateString() : null;
        $formattedDate = $this->follow_up_date ? $this->follow_up_date->format('Y-m-d') : 'None';
        $this->logActivity('follow_up_set', "Follow up date set to {$formattedDate}", $actor);

        return $this->save();
    }
}
