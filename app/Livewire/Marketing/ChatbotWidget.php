<?php

namespace App\Livewire\Marketing;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsChatbotSetting;
use App\Domain\Cms\Models\CmsChatConversation;
use App\Domain\Cms\Models\CmsChatMessage;
use App\Domain\Cms\Services\ChatbotService;
use App\Domain\Cms\Services\ContactValidationService;
use App\Domain\Courses\Models\Course;
use Illuminate\Support\Str;
use Livewire\Component;

class ChatbotWidget extends Component
{
    public ?string $academyId = null;

    public ?string $page = null;

    public bool $isOpen = false;

    public string $message = '';

    /**
     * @var array<int, array{id?: string, sender: string, sender_name?: string|null, text: string, time: string, links?: array<int, array{label: string, url: string}>, course_cards?: array<int, array<string, mixed>>, is_handoff?: bool, show_lead_capture?: bool, is_name_prompt?: bool}>
     */
    public array $conversation = [];

    public string $sessionId = '';

    public array $suggestedQuestions = [];

    public ?CmsChatbotSetting $settings = null;

    // Visitor Identification State
    public ?string $visitorName = null;

    public bool $hasIdentified = false;

    public string $nameInput = '';

    // Error & Retry
    public ?string $errorMessage = null;

    public ?string $lastUserMessage = null;

    // Human Support State
    public string $conversationStatus = 'open';

    public string $operatingMode = 'auto';

    public string $handler = 'ai'; // ai, human, fallback

    public bool $isWaitingForSupport = false;

    // Inline Admissions Lead Capture Card
    public bool $showLeadForm = false;

    public string $leadName = '';

    public string $leadCountryCode = 'IN';

    public string $leadPhone = '';

    public string $leadEmail = '';

    public string $leadCourseInterest = 'General Admissions';

    public string $leadMessage = '';

    public bool $leadConsent = false;

    public string $leadConsentText = 'I agree that SEO Academy may contact me about courses and admissions using the contact details I have provided.';

    public bool $leadSubmitted = false;

    public ?string $leadFeedback = null;

    public ?string $leadPhoneError = null;

    public ?string $leadEmailError = null;

    public ?string $leadConsentError = null;

    public function mount(?string $academyId = null, ?string $page = null): void
    {
        $academy = $academyId
            ? Academy::find($academyId)
            : (Academy::current() ?? Academy::first());

        $this->academyId = $academy?->id;
        $this->page = $page ?? $this->detectCurrentPage();

        // Preserve session across reloads
        $storedSessionId = session()->get('seo_chatbot_session_id');
        if (! $storedSessionId) {
            $storedSessionId = (string) Str::uuid();
            session()->put('seo_chatbot_session_id', $storedSessionId);
        }
        $this->sessionId = $storedSessionId;

        // Load persisted visitor identification (if any)
        $this->visitorName = session()->get('seo_chatbot_visitor_name');
        $this->hasIdentified = session()->get('seo_chatbot_identified', false);

        if ($this->academyId) {
            $this->settings = CmsChatbotSetting::getOrCreateForAcademy($this->academyId);
            $this->suggestedQuestions = $this->getDefaultQuickActions();

            // Load existing conversation if present
            $this->loadExistingConversation();
        }
    }

    public function loadExistingConversation(): void
    {
        if (! $this->academyId || ! $this->sessionId) {
            $this->initGreeting();

            return;
        }

        $conv = CmsChatConversation::with(['messages' => function ($q) {
            $q->where('is_internal', false)->orderBy('created_at', 'asc');
        }])->where('academy_id', $this->academyId)->where('session_id', $this->sessionId)->first();

        if ($conv) {
            $this->conversationStatus = $conv->status;
            $this->operatingMode = $conv->operating_mode;
            $this->handler = $conv->handler ?? 'ai';
            $this->isWaitingForSupport = ($this->handler === 'human') || in_array($conv->status, ['waiting_for_support', 'assigned', 'waiting_for_visitor'], true);

            if ($conv->visitor_name && ! $this->visitorName) {
                $this->visitorName = $conv->visitor_name;
                $this->hasIdentified = true;
                session()->put('seo_chatbot_visitor_name', $conv->visitor_name);
                session()->put('seo_chatbot_identified', true);
            }

            if ($conv->messages->isNotEmpty()) {
                $this->conversation = [];
                foreach ($conv->messages as $m) {
                    $sender = $m->sender_type === 'visitor' ? 'user' : ($m->isFromStaff() ? $m->sender_type : 'bot');
                    $this->conversation[] = [
                        'id' => $m->id,
                        'sender' => $sender,
                        'sender_name' => $m->sender_name,
                        'text' => $m->message,
                        'time' => $m->created_at->format('H:i'),
                        'course_cards' => $m->structured_data['course_cards'] ?? [],
                        'links' => [],
                        'is_handoff' => false,
                        'show_lead_capture' => false,
                        'is_name_prompt' => false,
                    ];
                }
            } else {
                $this->initGreeting();
            }
        } else {
            $this->initGreeting();
        }
    }

    public function detectCurrentPage(): string
    {
        $path = request()->path();
        if ($path === '/' || empty($path)) {
            return 'home';
        }
        if (str_starts_with($path, 'courses')) {
            return 'courses';
        }
        if (str_starts_with($path, 'services')) {
            return 'services';
        }
        if (str_starts_with($path, 'blog')) {
            return 'blog';
        }
        if (str_starts_with($path, 'contact')) {
            return 'contact';
        }
        if (str_starts_with($path, 'page') || str_starts_with($path, 'about')) {
            return 'about';
        }

        return 'other';
    }

    public function isVisible(): bool
    {
        if (! $this->settings || ! $this->settings->is_enabled) {
            return false;
        }

        return $this->settings->isVisibleOnPage($this->page);
    }

    public function getDefaultQuickActions(): array
    {
        return [
            'Explore Courses',
            'Course Fees & Pricing',
            'Course Duration & Schedule',
            'Talk to Admissions',
            'Talk to Support',
        ];
    }

    public function initGreeting(): void
    {
        if (! $this->hasIdentified && empty($this->visitorName)) {
            // First time visitor: ask for name warmly
            $this->conversation = [
                [
                    'sender' => 'bot',
                    'text' => 'Hi there! Welcome to SEO Academy. Before we begin, may I know your name?',
                    'time' => now()->format('H:i'),
                    'links' => [],
                    'course_cards' => [],
                    'is_handoff' => false,
                    'show_lead_capture' => false,
                    'is_name_prompt' => true,
                ],
            ];
            $this->suggestedQuestions = [];
        } elseif (! empty($this->visitorName)) {
            // Returning identified visitor
            $this->conversation = [
                [
                    'sender' => 'bot',
                    'text' => "Welcome back, {$this->visitorName}! How can I assist you today?",
                    'time' => now()->format('H:i'),
                    'links' => [],
                    'course_cards' => [],
                    'is_handoff' => false,
                    'show_lead_capture' => false,
                    'is_name_prompt' => false,
                ],
            ];
            $this->suggestedQuestions = $this->getDefaultQuickActions();
        } else {
            // Skipped visitor
            $welcome = $this->settings?->welcome_message ?: 'Hi there! I am the SEO Academy assistant. How can I help your learning journey today?';
            $this->conversation = [
                [
                    'sender' => 'bot',
                    'text' => $welcome,
                    'time' => now()->format('H:i'),
                    'links' => [],
                    'course_cards' => [],
                    'is_handoff' => false,
                    'show_lead_capture' => false,
                    'is_name_prompt' => false,
                ],
            ];
            $this->suggestedQuestions = $this->getDefaultQuickActions();
        }
    }

    /**
     * Natural name extraction from introductions (e.g. "My name is Panda", "I'm Sarah", "This is John", "Call me Alex").
     */
    public function extractNameFromInput(string $input): string
    {
        $trimmed = trim($input);
        if (empty($trimmed)) {
            return '';
        }

        $patterns = [
            '/^(?:hello|hi|hey|good\s+(?:morning|afternoon|evening))?[,\s]*(?:my\s+name\s+is|name\s+is|i\'?m|i\s+am|this\s+is|call\s+me|you\s+can\s+call\s+me)\s+(.+)$/iu',
            '/^(?:it\'?s|its)\s+(.+)$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $candidate = trim($matches[1], " \t\n\r\0\x0B.,!?:;\"'()[]{}");
                if (! empty($candidate)) {
                    return $candidate;
                }
            }
        }

        return trim($trimmed, " \t\n\r\0\x0B.,!?:;\"'()[]{}");
    }

    /**
     * Visitor submits their name.
     */
    public function submitVisitorName(?string $rawInput = null): void
    {
        $input = $rawInput ?? $this->nameInput;
        $name = $this->extractNameFromInput($input);

        if (empty($name)) {
            $name = 'Friend';
        }

        $this->visitorName = $name;
        $this->hasIdentified = true;
        session()->put('seo_chatbot_visitor_name', $name);
        session()->put('seo_chatbot_identified', true);

        // Pre-fill lead capture name
        $this->leadName = $name;

        // Sync with conversation record if exists
        if ($this->academyId && $this->sessionId) {
            CmsChatConversation::where('academy_id', $this->academyId)
                ->where('session_id', $this->sessionId)
                ->update(['visitor_name' => $name]);
        }

        // Add user response to conversation
        $this->conversation[] = [
            'sender' => 'user',
            'text' => trim($input ?: $name),
            'time' => now()->format('H:i'),
        ];

        // Acknowledge warmly
        $this->conversation[] = [
            'sender' => 'bot',
            'text' => "Hi {$name}! Nice to meet you. How can I help you today?",
            'time' => now()->format('H:i'),
            'links' => [],
            'course_cards' => [],
            'is_handoff' => false,
            'show_lead_capture' => false,
            'is_name_prompt' => false,
        ];

        $this->suggestedQuestions = $this->getDefaultQuickActions();
        $this->nameInput = '';
    }

    /**
     * Visitor chooses to skip entering their name.
     */
    public function skipVisitorName(): void
    {
        $this->hasIdentified = true;
        session()->put('seo_chatbot_identified', true);

        $this->conversation[] = [
            'sender' => 'bot',
            'text' => 'No problem! How can I help you today?',
            'time' => now()->format('H:i'),
            'links' => [],
            'course_cards' => [],
            'is_handoff' => false,
            'show_lead_capture' => false,
            'is_name_prompt' => false,
        ];

        $this->suggestedQuestions = $this->getDefaultQuickActions();
        $this->nameInput = '';
    }

    public function toggleChat(): void
    {
        $this->isOpen = ! $this->isOpen;
        $this->errorMessage = null;
        if ($this->isOpen) {
            $this->pollNewMessages();
        }
    }

    public function closeChat(): void
    {
        $this->isOpen = false;
        $this->showLeadForm = false;
    }

    public function clearConversation(): void
    {
        $this->sessionId = (string) Str::uuid();
        session()->put('seo_chatbot_session_id', $this->sessionId);
        $this->errorMessage = null;
        $this->showLeadForm = false;
        $this->conversationStatus = 'open';
        $this->isWaitingForSupport = false;
        $this->initGreeting();
    }

    public function selectSuggested(string $question): void
    {
        $this->message = $question;
        $this->sendMessage(true);
    }

    /**
     * Detect if visitor query indicates admissions, fee, pricing, schedule, or advisor intent.
     */
    public function detectAdmissionsIntent(string $text): bool
    {
        $lower = strtolower($text);

        return Str::contains($lower, [
            'join', 'enroll', 'enrol', 'enrolling', 'registration', 'register',
            'how to enroll', 'how to join', 'fee', 'fees', 'cost', 'pricing', 'price',
            'discount', 'duration', 'schedule', 'when does it start', 'timing', 'timings',
            'advisor', 'counselor', 'call me', 'talk to admissions', 'talk to someone',
            'admission', 'admissions', 'apply', 'syllabus',
        ]);
    }

    public function sendMessage(bool $isSuggested = false): void
    {
        $clean = trim($this->message);
        if (empty($clean) || ! $this->academyId) {
            return;
        }

        // If the visitor has not identified yet and sends a message, check if it's their name/introduction
        if (! $this->hasIdentified && count($this->conversation) <= 2) {
            $this->submitVisitorName($clean);
            $this->message = '';

            return;
        }

        $this->errorMessage = null;
        $this->lastUserMessage = $clean;

        // Add user question to local conversation view immediately
        $this->conversation[] = [
            'sender' => 'user',
            'text' => $clean,
            'time' => now()->format('H:i'),
        ];

        $this->message = '';

        // Check for admissions intent
        $hasAdmissionsIntent = $this->detectAdmissionsIntent($clean);

        try {
            $service = app(ChatbotService::class);
            $clientIp = request()->ip();

            $response = $service->respond(
                $this->academyId,
                $clean,
                $this->sessionId,
                $clientIp,
                $isSuggested,
                $this->page
            );

            $showLead = $response['show_lead_capture'] ?? false;
            if ($hasAdmissionsIntent && ($this->settings?->lead_capture_enabled ?? true)) {
                $showLead = true;
                // Pre-fill course of interest if user asked about a specific course or topic
                if (Str::contains(strtolower($clean), ['technical', 'audit'])) {
                    $this->leadCourseInterest = 'Technical SEO Mastery';
                } elseif (Str::contains(strtolower($clean), ['link', 'outreach'])) {
                    $this->leadCourseInterest = 'Link Building & Digital PR';
                } elseif (Str::contains(strtolower($clean), ['content', 'copywriting'])) {
                    $this->leadCourseInterest = 'Content Strategy & Copywriting';
                }
            }

            // Add assistant response to conversation
            $this->conversation[] = [
                'sender' => 'bot',
                'text' => $response['answer'],
                'time' => now()->format('H:i'),
                'links' => $response['links'] ?? [],
                'course_cards' => $response['course_cards'] ?? [],
                'is_handoff' => $response['is_handoff'] ?? false,
                'show_lead_capture' => $showLead,
                'is_name_prompt' => false,
            ];

            if ($showLead) {
                $this->showLeadForm = true;
            }

            if (! empty($response['suggestions'])) {
                $this->suggestedQuestions = $response['suggestions'];
            }

            // Sync conversation status
            $conv = CmsChatConversation::where('academy_id', $this->academyId)->where('session_id', $this->sessionId)->first();
            if ($conv) {
                $this->conversationStatus = $conv->status;
                $this->operatingMode = $conv->operating_mode;
                $this->handler = $conv->handler ?? 'ai';
                $this->isWaitingForSupport = ($this->handler === 'human') || in_array($conv->status, ['waiting_for_support', 'assigned', 'waiting_for_visitor'], true);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to get an answer. Please check your connection and retry.';
        }
    }

    public function requestHumanSupport(): void
    {
        if (! $this->academyId || ! $this->sessionId) {
            return;
        }

        try {
            $service = app(ChatbotService::class);
            $res = $service->requestHumanSupport(
                $this->academyId,
                $this->sessionId,
                'Visitor requested live support via chat widget'
            );

            $this->conversationStatus = 'waiting_for_support';
            $this->operatingMode = 'human_support';
            $this->handler = 'human';
            $this->isWaitingForSupport = true;

            $this->conversation[] = [
                'sender' => 'bot',
                'text' => "🙋 **Live Support Request Connected**\n\n".$res['message'],
                'time' => now()->format('H:i'),
                'links' => [],
                'course_cards' => [],
                'is_handoff' => true,
                'show_lead_capture' => false,
                'is_name_prompt' => false,
            ];
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not request live support. Please try again.';
        }
    }

    public function pollNewMessages(): void
    {
        if (! $this->academyId || ! $this->sessionId || ! $this->isOpen) {
            return;
        }

        $conv = CmsChatConversation::where('academy_id', $this->academyId)
            ->where('session_id', $this->sessionId)
            ->first();

        if (! $conv) {
            return;
        }

        $this->conversationStatus = $conv->status;
        $this->operatingMode = $conv->operating_mode;
        $this->handler = $conv->handler ?? 'ai';
        $this->isWaitingForSupport = ($this->handler === 'human') || in_array($conv->status, ['waiting_for_support', 'assigned', 'waiting_for_visitor'], true);

        // Fetch all public messages for this conversation
        $messages = CmsChatMessage::where('conversation_id', $conv->id)
            ->where('is_internal', false)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($messages->isNotEmpty()) {
            $freshList = [];
            foreach ($messages as $m) {
                $sender = $m->sender_type === 'visitor' ? 'user' : ($m->isFromStaff() ? $m->sender_type : 'bot');
                $freshList[] = [
                    'id' => $m->id,
                    'sender' => $sender,
                    'sender_name' => $m->sender_name,
                    'text' => $m->message,
                    'time' => $m->created_at->format('H:i'),
                    'course_cards' => $m->structured_data['course_cards'] ?? [],
                    'links' => [],
                    'is_handoff' => false,
                    'show_lead_capture' => false,
                    'is_name_prompt' => false,
                ];
            }
            $this->conversation = $freshList;
        }
    }

    public function retryLastMessage(): void
    {
        $this->errorMessage = null;
        if ($this->lastUserMessage) {
            $this->message = $this->lastUserMessage;
            $this->sendMessage();
        }
    }

    // Lead Capture Actions
    public function openLeadForm(?string $subject = null): void
    {
        if ($subject) {
            $this->leadCourseInterest = $subject;
        }
        if ($this->visitorName) {
            $this->leadName = $this->visitorName;
        }
        $this->leadSubmitted = false;
        $this->leadFeedback = null;
        $this->leadPhoneError = null;
        $this->leadEmailError = null;
        $this->leadConsentError = null;
        $this->showLeadForm = true;
    }

    public function closeLeadForm(): void
    {
        $this->showLeadForm = false;
    }

    public function submitLead(): void
    {
        $this->leadPhoneError = null;
        $this->leadEmailError = null;
        $this->leadConsentError = null;

        $name = trim($this->leadName ?: ($this->visitorName ?: ''));
        if (empty($name)) {
            $this->leadPhoneError = 'Please provide your name.';

            return;
        }

        $phone = trim($this->leadPhone);
        $email = trim($this->leadEmail);

        if (empty($phone) && empty($email)) {
            $this->leadPhoneError = 'Please provide either a phone number or email address.';

            return;
        }

        $validator = app(ContactValidationService::class);

        // Validate Phone if provided
        if (! empty($phone)) {
            $phoneResult = $validator->validatePhone($phone, $this->leadCountryCode);
            if (! $phoneResult['valid']) {
                $this->leadPhoneError = $phoneResult['error'];

                return;
            }
        }

        // Validate Email if provided
        if (! empty($email)) {
            $emailResult = $validator->validateEmail($email);
            if (! $emailResult['valid']) {
                $this->leadEmailError = $emailResult['error'];

                return;
            }
        }

        // Consent Checkbox MUST be checked
        if (! $this->leadConsent) {
            $this->leadConsentError = 'You must agree to the admissions contact terms before submitting.';

            return;
        }

        if (! $this->academyId) {
            return;
        }

        try {
            $conv = CmsChatConversation::where('academy_id', $this->academyId)
                ->where('session_id', $this->sessionId)
                ->first();

            $service = app(ChatbotService::class);
            $clientIp = request()->ip();

            $result = $service->captureLead(
                $this->academyId,
                [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'phone_country_code' => $this->leadCountryCode,
                    'course_interest' => $this->leadCourseInterest,
                    'interested_subject' => $this->leadCourseInterest,
                    'message' => $this->leadMessage ?: 'Inquiry submitted via Chatbot Admissions Flow.',
                    'consent' => $this->leadConsent,
                    'consent_text' => $this->leadConsentText,
                    'conversation_id' => $conv?->id,
                ],
                $this->sessionId,
                $clientIp
            );

            $this->leadSubmitted = true;
            $this->leadFeedback = $result['message'];

            // Add confirmation message to chat feed
            $visitorGreetingName = $this->visitorName ?: $name;
            $this->conversation[] = [
                'sender' => 'bot',
                'text' => "🎉 **Admissions Enquiry Received**\n\nThank you, {$visitorGreetingName}! Your enquiry has been received. Our admissions team will reach out to you shortly.",
                'time' => now()->format('H:i'),
                'links' => [['label' => 'Explore Course Catalog', 'url' => url('/courses')]],
                'course_cards' => [],
                'is_handoff' => false,
                'show_lead_capture' => false,
                'is_name_prompt' => false,
            ];

            // Reset form
            $this->leadPhone = '';
            $this->leadEmail = '';
            $this->leadMessage = '';
            $this->leadConsent = false;
            $this->showLeadForm = false;
        } catch (\Throwable $e) {
            $this->leadPhoneError = $e->getMessage();
        }
    }

    public function getSupportedCountriesProperty(): array
    {
        return app(ContactValidationService::class)->getSupportedCountries();
    }

    public function getAvailableCoursesProperty(): array
    {
        if (! $this->academyId) {
            return [];
        }

        return Course::where('academy_id', $this->academyId)
            ->where('status', 'Published')
            ->pluck('title')
            ->toArray();
    }

    public function render()
    {
        if (! $this->isVisible()) {
            return <<<'HTML'
            <div id="seo-chatbot-root" style="display: none;"></div>
            HTML;
        }

        return view('livewire.marketing.chatbot-widget', [
            'supportedCountries' => $this->supportedCountries,
            'availableCourses' => $this->availableCourses,
        ]);
    }
}
