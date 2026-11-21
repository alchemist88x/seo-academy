<?php

namespace App\Livewire\Admin\Website;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Models\AiFaq;
use App\Domain\Cms\Models\CmsChatbotLog;
use App\Domain\Cms\Models\CmsChatbotSetting;
use App\Domain\Cms\Services\ChatbotRetrievalService;
use App\Domain\Cms\Services\ChatbotService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Public Website Chatbot & Visitor Assistant')]
class WebsiteChatbotManager extends Component
{
    use WithPagination;

    public ?string $academyId = null;

    public string $activeTab = 'general'; // general, faqs, suggestions, ai, sources, logs

    // Chatbot Settings Form
    public bool $isEnabled = true;

    public string $name = 'SEO Academy Assistant';

    public string $welcomeMessage = '';

    public string $initialGreeting = '';

    public string $brandColor = '#2563eb';

    public string $headerColor = '#1e293b';

    public string $position = 'bottom_right';

    public string $launcherIcon = 'message-circle';

    public bool $desktopVisibility = true;

    public bool $mobileVisibility = true;

    public array $visibilityPages = ['all'];

    public string $contactSupportCta = 'Contact Admissions Desk';

    public bool $leadCaptureEnabled = true;

    // Operating Mode & Support Options
    public string $defaultOperatingMode = 'auto'; // auto, ai_enabled, fallback_only, human_support

    public bool $humanSupportEnabled = true;

    public string $supportOfflineMessage = '';

    // AI Integration Settings
    public bool $aiEnabled = false;

    public string $aiProvider = 'null';

    public ?string $aiEndpoint = null;

    public ?string $aiModel = 'gpt-4o-mini';

    public ?string $aiApiKey = null;

    public float $aiTemperature = 0.7;

    public int $aiMaxTokens = 400;

    public int $aiTimeout = 15;

    public int $dailyRequestLimit = 500;

    public int $circuitBreakerCooldown = 300;

    public int $retentionDays = 90;

    public int $rateLimitPerMinute = 20;

    // Provider Connectivity Test
    public ?string $testPingResult = null;

    public ?bool $testPingSuccess = null;

    // Suggested Questions
    public array $suggestedQuestions = [];

    public string $newSuggestedQuestion = '';

    // FAQ Management
    public bool $showFaqModal = false;

    public ?string $editingFaqId = null;

    public string $faqQuestion = '';

    public string $faqAnswer = '';

    public string $faqCategory = 'Admissions';

    public int $faqDisplayOrder = 0;

    public bool $faqIsActive = true;

    // Analytics Filters
    public string $logSearch = '';

    public string $logSource = 'all';

    public string $logOutcome = 'all'; // all, answered, handoff, lead_converted

    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public function mount(): void
    {
        $academy = app(TenantContext::class)->getAcademy()
            ?? Academy::current()
            ?? Academy::first();

        $this->academyId = $academy?->id;

        if ($this->academyId) {
            $setting = CmsChatbotSetting::getOrCreateForAcademy($this->academyId);
            $this->loadSettings($setting);
        }
    }

    public function loadSettings(CmsChatbotSetting $setting): void
    {
        $this->isEnabled = $setting->is_enabled;
        $this->name = $setting->name;
        $this->welcomeMessage = $setting->welcome_message ?? '';
        $this->initialGreeting = $setting->initial_greeting ?? '';
        $this->brandColor = $setting->brand_color ?? '#2563eb';
        $this->headerColor = $setting->header_color ?? '#1e293b';
        $this->position = $setting->position ?? 'bottom_right';
        $this->launcherIcon = $setting->launcher_icon ?? 'message-circle';
        $this->desktopVisibility = $setting->desktop_visibility;
        $this->mobileVisibility = $setting->mobile_visibility;
        $this->visibilityPages = $setting->visibility_pages ?: ['all'];
        $this->contactSupportCta = $setting->contact_support_cta ?? 'Contact Admissions Desk';
        $this->leadCaptureEnabled = $setting->lead_capture_enabled ?? true;
        $this->defaultOperatingMode = $setting->default_operating_mode ?? 'auto';
        $this->humanSupportEnabled = (bool) ($setting->human_support_enabled ?? true);
        $this->supportOfflineMessage = $setting->support_offline_message ?? '';
        $this->suggestedQuestions = $setting->suggested_questions ?: CmsChatbotSetting::getDefaultQuestions();

        $this->aiEnabled = $setting->ai_enabled;
        $this->aiProvider = $setting->ai_provider ?? 'null';
        $this->aiEndpoint = $setting->ai_endpoint;
        $this->aiModel = $setting->ai_model ?? 'gpt-4o-mini';
        $this->aiTemperature = $setting->ai_temperature ?? 0.7;
        $this->aiMaxTokens = $setting->ai_max_tokens ?? 400;
        $this->aiTimeout = $setting->ai_timeout ?? 15;
        $this->dailyRequestLimit = $setting->daily_request_limit ?? 500;
        $this->circuitBreakerCooldown = $setting->circuit_breaker_cooldown ?? 300;
        $this->retentionDays = $setting->retention_days ?? 90;
        $this->rateLimitPerMinute = $setting->rate_limit_per_minute ?? 20;

        // Mask API key on load
        $this->aiApiKey = $setting->ai_api_key ? '••••••••••••••••' : '';
    }

    public function saveGeneralSettings(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'welcomeMessage' => 'required|string|max:500',
            'initialGreeting' => 'nullable|string|max:500',
            'brandColor' => ['required', 'regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i'],
            'position' => 'required|in:bottom_right,bottom_left',
            'launcherIcon' => 'required|in:message-circle,bot,sparkle,help-circle',
            'contactSupportCta' => 'required|string|max:100',
            'visibilityPages' => 'array',
        ]);

        if (! $this->academyId) {
            return;
        }

        $setting = CmsChatbotSetting::getOrCreateForAcademy($this->academyId);
        $setting->update([
            'is_enabled' => $this->isEnabled,
            'name' => $this->name,
            'welcome_message' => $this->welcomeMessage,
            'initial_greeting' => $this->initialGreeting,
            'brand_color' => $this->brandColor,
            'position' => $this->position,
            'launcher_icon' => $this->launcherIcon,
            'desktop_visibility' => $this->desktopVisibility,
            'mobile_visibility' => $this->mobileVisibility,
            'visibility_pages' => empty($this->visibilityPages) ? ['all'] : $this->visibilityPages,
            'contact_support_cta' => $this->contactSupportCta,
            'lead_capture_enabled' => $this->leadCaptureEnabled,
            'default_operating_mode' => $this->defaultOperatingMode,
            'human_support_enabled' => $this->humanSupportEnabled,
            'support_offline_message' => $this->supportOfflineMessage,
        ]);

        $this->feedbackMessage = 'Chatbot appearance, operating mode and page visibility updated.';
        $this->feedbackType = 'success';
    }

    public function addSuggestedQuestion(): void
    {
        $clean = trim($this->newSuggestedQuestion);
        if (empty($clean) || in_array($clean, $this->suggestedQuestions, true)) {
            return;
        }

        $this->suggestedQuestions[] = $clean;
        $this->newSuggestedQuestion = '';
        $this->persistSuggestedQuestions();
    }

    public function removeSuggestedQuestion(int $index): void
    {
        if (isset($this->suggestedQuestions[$index])) {
            unset($this->suggestedQuestions[$index]);
            $this->suggestedQuestions = array_values($this->suggestedQuestions);
            $this->persistSuggestedQuestions();
        }
    }

    public function resetSuggestedQuestions(): void
    {
        $this->suggestedQuestions = CmsChatbotSetting::getDefaultQuestions();
        $this->persistSuggestedQuestions();
    }

    protected function persistSuggestedQuestions(): void
    {
        if (! $this->academyId) {
            return;
        }

        $setting = CmsChatbotSetting::getOrCreateForAcademy($this->academyId);
        $setting->update(['suggested_questions' => $this->suggestedQuestions]);

        $this->feedbackMessage = 'Suggested questions updated.';
        $this->feedbackType = 'success';
    }

    public function saveAiSettings(): void
    {
        $this->validate([
            'aiProvider' => 'required|string',
            'aiEndpoint' => 'nullable|url',
            'aiModel' => 'nullable|string|max:100',
            'aiTemperature' => 'required|numeric|min:0|max:1',
            'aiMaxTokens' => 'required|integer|min:50|max:2000',
            'aiTimeout' => 'required|integer|min:5|max:60',
            'dailyRequestLimit' => 'required|integer|min:10|max:5000',
            'circuitBreakerCooldown' => 'required|integer|min:30|max:3600',
            'retentionDays' => 'required|integer|min:1|max:365',
            'rateLimitPerMinute' => 'required|integer|min:5|max:60',
        ]);

        if (! $this->academyId) {
            return;
        }

        $setting = CmsChatbotSetting::getOrCreateForAcademy($this->academyId);

        $update = [
            'ai_enabled' => $this->aiEnabled,
            'ai_provider' => $this->aiProvider,
            'ai_endpoint' => $this->aiEndpoint,
            'ai_model' => $this->aiModel,
            'ai_temperature' => $this->aiTemperature,
            'ai_max_tokens' => $this->aiMaxTokens,
            'ai_timeout' => $this->aiTimeout,
            'daily_request_limit' => $this->dailyRequestLimit,
            'circuit_breaker_cooldown' => $this->circuitBreakerCooldown,
            'retention_days' => $this->retentionDays,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
        ];

        // Only update API key if newly entered and not masked placeholder
        if (! empty($this->aiApiKey) && ! str_starts_with($this->aiApiKey, '••••')) {
            $update['ai_api_key'] = $this->aiApiKey;
        }

        $setting->update($update);

        $this->feedbackMessage = 'AI assistant configuration and limits saved.';
        $this->feedbackType = 'success';
    }

    public function resetCircuitBreaker(): void
    {
        if (! $this->academyId) {
            return;
        }

        app(ChatbotService::class)->resetCircuitBreaker($this->academyId);
        $this->feedbackMessage = 'AI Circuit Breaker has been reset. The chatbot will resume calling the AI provider.';
        $this->feedbackType = 'success';
    }

    public function getIsCircuitBreakerOpenProperty(): bool
    {
        if (! $this->academyId) {
            return false;
        }

        return app(ChatbotService::class)->isCircuitBreakerOpen($this->academyId);
    }

    public function testProviderConnection(): void
    {
        if (! $this->academyId) {
            return;
        }

        $service = app(ChatbotService::class);
        $result = $service->testConnection($this->academyId, [
            'ai_provider' => $this->aiProvider,
            'ai_endpoint' => $this->aiEndpoint,
            'ai_model' => $this->aiModel,
            'ai_api_key' => (! empty($this->aiApiKey) && ! str_starts_with($this->aiApiKey, '••••')) ? $this->aiApiKey : null,
            'ai_timeout' => $this->aiTimeout,
        ]);

        $this->testPingSuccess = $result['success'];
        $this->testPingResult = $result['message'];
    }

    // --- FAQ Management ---

    public function openCreateFaqModal(): void
    {
        $this->editingFaqId = null;
        $this->faqQuestion = '';
        $this->faqAnswer = '';
        $this->faqCategory = 'Admissions';
        $this->faqDisplayOrder = 0;
        $this->faqIsActive = true;
        $this->showFaqModal = true;
    }

    public function openEditFaqModal(string $id): void
    {
        $faq = AiFaq::where('academy_id', $this->academyId)->findOrFail($id);

        $this->editingFaqId = $faq->id;
        $this->faqQuestion = $faq->question;
        $this->faqAnswer = $faq->answer;
        $this->faqCategory = $faq->category ?? 'Admissions';
        $this->faqDisplayOrder = $faq->display_order ?? 0;
        $this->faqIsActive = $faq->is_active;

        $this->showFaqModal = true;
    }

    public function saveFaq(): void
    {
        $this->validate([
            'faqQuestion' => 'required|string|max:500',
            'faqAnswer' => 'required|string|max:2000',
            'faqCategory' => 'nullable|string|max:100',
            'faqDisplayOrder' => 'required|integer|min:0',
        ]);

        if (! $this->academyId) {
            return;
        }

        $data = [
            'academy_id' => $this->academyId,
            'question' => $this->faqQuestion,
            'answer' => $this->faqAnswer,
            'category' => $this->faqCategory ?: 'General',
            'display_order' => $this->faqDisplayOrder,
            'is_active' => $this->faqIsActive,
        ];

        if ($this->editingFaqId) {
            $faq = AiFaq::where('academy_id', $this->academyId)->findOrFail($this->editingFaqId);
            $faq->update($data);
            $this->feedbackMessage = 'FAQ updated successfully.';
        } else {
            AiFaq::create($data);
            $this->feedbackMessage = 'FAQ created and added to chatbot knowledge.';
        }

        $this->feedbackType = 'success';
        $this->showFaqModal = false;
    }

    public function toggleFaqActive(string $id): void
    {
        $faq = AiFaq::where('academy_id', $this->academyId)->findOrFail($id);
        $faq->update(['is_active' => ! $faq->is_active]);

        $this->feedbackMessage = 'FAQ status updated.';
        $this->feedbackType = 'success';
    }

    public function deleteFaq(string $id): void
    {
        $faq = AiFaq::where('academy_id', $this->academyId)->findOrFail($id);
        $faq->delete();

        $this->feedbackMessage = 'FAQ deleted.';
        $this->feedbackType = 'success';
    }

    // --- Conversation & Retention Management ---

    public function pruneOldLogs(): void
    {
        if (! $this->academyId) {
            return;
        }

        $deleted = app(ChatbotService::class)->pruneLogs($this->academyId, $this->retentionDays);

        $this->feedbackMessage = "Pruned {$deleted} conversation logs older than {$this->retentionDays} days.";
        $this->feedbackType = 'success';
    }

    public function deleteLog(string $id): void
    {
        $log = CmsChatbotLog::where('academy_id', $this->academyId)->findOrFail($id);
        $log->delete();

        $this->feedbackMessage = 'Conversation record deleted.';
        $this->feedbackType = 'success';
    }

    public function render(): View
    {
        $faqs = AiFaq::where('academy_id', $this->academyId)
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $logQuery = CmsChatbotLog::where('academy_id', $this->academyId)
            ->orderBy('created_at', 'desc');

        if ($this->logSource !== 'all') {
            $logQuery->where('source', $this->logSource);
        }

        if ($this->logOutcome === 'answered') {
            $logQuery->where('is_answered', true);
        } elseif ($this->logOutcome === 'handoff') {
            $logQuery->where('is_handoff', true);
        } elseif ($this->logOutcome === 'lead_converted') {
            $logQuery->where('is_lead_converted', true);
        }

        if (! empty($this->logSearch)) {
            $logQuery->where(function ($q) {
                $q->where('question', 'like', "%{$this->logSearch}%")
                    ->orWhere('answer', 'like', "%{$this->logSearch}%");
            });
        }

        $logs = $logQuery->paginate(15);

        $knowledgeStats = app(ChatbotRetrievalService::class)->getKnowledgeStats($this->academyId);

        $totalTokens = (int) CmsChatbotLog::where('academy_id', $this->academyId)->sum('tokens_used');
        $estimatedCost = round(($totalTokens / 1000) * 0.0005, 4);

        $stats = [
            'total_interactions' => CmsChatbotLog::where('academy_id', $this->academyId)->count(),
            'answered_count' => CmsChatbotLog::where('academy_id', $this->academyId)->where('is_answered', true)->count(),
            'handoff_count' => CmsChatbotLog::where('academy_id', $this->academyId)->where('is_handoff', true)->count(),
            'leads_count' => CmsChatbotLog::where('academy_id', $this->academyId)->where('is_lead_converted', true)->count(),
            'faq_count' => $faqs->count(),
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
        ];

        return view('livewire.admin.website.website-chatbot-manager', [
            'faqs' => $faqs,
            'logs' => $logs,
            'stats' => $stats,
            'knowledgeStats' => $knowledgeStats,
        ]);
    }
}
