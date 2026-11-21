<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsChatbotSetting extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_chatbot_settings';

    protected $fillable = [
        'academy_id',
        'is_enabled',
        'name',
        'welcome_message',
        'initial_greeting',
        'avatar_path',
        'brand_color',
        'header_color',
        'text_color',
        'position',
        'launcher_icon',
        'desktop_visibility',
        'mobile_visibility',
        'visibility_pages',
        'suggested_questions',
        'offline_message',
        'contact_support_cta',
        'lead_capture_enabled',
        'ai_enabled',
        'ai_provider',
        'ai_endpoint',
        'ai_model',
        'ai_api_key',
        'ai_temperature',
        'ai_max_tokens',
        'ai_timeout',
        'daily_request_limit',
        'retention_days',
        'rate_limit_per_minute',
        'default_operating_mode',
        'human_support_enabled',
        'circuit_breaker_cooldown',
        'support_offline_message',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'desktop_visibility' => 'boolean',
            'mobile_visibility' => 'boolean',
            'lead_capture_enabled' => 'boolean',
            'human_support_enabled' => 'boolean',
            'visibility_pages' => 'array',
            'ai_enabled' => 'boolean',
            'suggested_questions' => 'array',
            'ai_temperature' => 'float',
            'ai_max_tokens' => 'integer',
            'ai_timeout' => 'integer',
            'circuit_breaker_cooldown' => 'integer',
            'daily_request_limit' => 'integer',
            'retention_days' => 'integer',
            'rate_limit_per_minute' => 'integer',
            'ai_api_key' => 'encrypted',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public static function getDefaultQuestions(): array
    {
        return [
            'What SEO courses do you offer?',
            'Are certifications recognized industry-wide?',
            'What are the course schedules and pricing?',
            'How can I get personal mentoring or admissions advice?',
        ];
    }

    public static function getOrCreateForAcademy(string $academyId): self
    {
        return self::firstOrCreate(
            ['academy_id' => $academyId],
            [
                'is_enabled' => true,
                'name' => 'SEO Academy Assistant',
                'welcome_message' => 'Hi there! I am the SEO Academy assistant. How can I help your learning journey today?',
                'initial_greeting' => 'Ask anything about our SEO courses, certifications, syllabus, or practical projects.',
                'brand_color' => '#2563eb',
                'header_color' => '#1e293b',
                'text_color' => '#ffffff',
                'position' => 'bottom_right',
                'launcher_icon' => 'message-circle',
                'desktop_visibility' => true,
                'mobile_visibility' => true,
                'suggested_questions' => self::getDefaultQuestions(),
                'offline_message' => 'Our admissions desk is currently offline. Please leave an inquiry and we will get back to you shortly.',
                'contact_support_cta' => 'Contact Admissions Desk',
                'lead_capture_enabled' => true,
                'visibility_pages' => ['all'],
                'ai_enabled' => false,
                'ai_provider' => 'null',
                'ai_temperature' => 0.7,
                'ai_max_tokens' => 500,
                'ai_timeout' => 15,
                'daily_request_limit' => 500,
                'retention_days' => 90,
                'rate_limit_per_minute' => 20,
                'default_operating_mode' => 'auto',
                'human_support_enabled' => true,
                'circuit_breaker_cooldown' => 300,
                'support_offline_message' => 'Our human support desk is currently offline. Please leave your contact details and message, and our team will get back to you shortly.',
            ]
        );
    }

    public function isVisibleOnPage(?string $page): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if (empty($this->visibility_pages) || in_array('all', $this->visibility_pages, true)) {
            return true;
        }

        if (! $page) {
            return true;
        }

        return in_array($page, $this->visibility_pages, true);
    }
}
