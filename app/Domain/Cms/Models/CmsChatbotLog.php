<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsChatbotLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_chatbot_logs';

    protected $fillable = [
        'academy_id',
        'session_id',
        'question',
        'answer',
        'source',
        'is_answered',
        'is_handoff',
        'selected_suggested_question',
        'ip_hash',
        'tokens_used',
        'is_lead_converted',
        'lead_inquiry_id',
        'structured_data',
    ];

    protected function casts(): array
    {
        return [
            'is_answered' => 'boolean',
            'is_handoff' => 'boolean',
            'selected_suggested_question' => 'boolean',
            'is_lead_converted' => 'boolean',
            'tokens_used' => 'integer',
            'structured_data' => 'array',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function leadInquiry(): BelongsTo
    {
        return $this->belongsTo(CmsContactInquiry::class, 'lead_inquiry_id');
    }

    public function getEstimatedCostAttribute(): float
    {
        return round((($this->tokens_used ?? 0) / 1000) * 0.0005, 6);
    }
}
