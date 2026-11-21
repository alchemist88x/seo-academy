<?php

namespace App\Domain\Cms\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsChatConversation;
use App\Domain\Cms\Models\CmsContactInquiry;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContactInquiryService
{
    public function __construct(
        protected ContactValidationService $validator,
        protected NotificationDispatcherService $notifications,
    ) {}

    /**
     * Create a new contact inquiry with validated data, activity history, and staff notifications.
     *
     * @param  array<string, mixed>  $data
     */
    public function createInquiry(array $data, ?User $actor = null): CmsContactInquiry
    {
        $academyId = $data['academy_id'] ?? (Academy::current()?->id ?? Academy::first()?->id);

        $name = trim($data['name'] ?? '');
        $rawEmail = $data['email'] ?? null;
        $rawPhone = $data['phone'] ?? null;
        $phoneCountryCode = $data['phone_country_code'] ?? 'US';

        // Email validation & normalization
        $normalizedEmail = null;
        if (! empty($rawEmail)) {
            $emailResult = $this->validator->validateEmail($rawEmail);
            if ($emailResult['valid']) {
                $normalizedEmail = $emailResult['normalized'];
            }
        }

        // Phone validation & formatting
        $e164Phone = null;
        $formattedPhone = null;
        $detectedCountry = $phoneCountryCode;
        if (! empty($rawPhone)) {
            $phoneResult = $this->validator->validatePhone($rawPhone, $phoneCountryCode);
            if ($phoneResult['valid']) {
                $e164Phone = $phoneResult['e164'];
                $formattedPhone = $phoneResult['international'] ?? $phoneResult['national'];
                $detectedCountry = $phoneResult['country_code'] ?? $phoneCountryCode;
            } else {
                $formattedPhone = $rawPhone;
            }
        }

        $source = $data['source'] ?? CmsContactInquiry::SOURCE_CONTACT_FORM;
        $courseInterest = $data['course_interest'] ?? ($data['interested_service'] ?? null);
        $subject = $data['subject'] ?? ($courseInterest ? "Inquiry about {$courseInterest}" : 'General Website Inquiry');
        $message = $data['message'] ?? 'Admissions inquiry submitted.';

        $consentText = $data['consent_text'] ?? null;
        $consentedAt = ! empty($consentText) ? ($data['consented_at'] ?? Carbon::now()) : null;
        $consentVersion = $data['consent_version'] ?? 'v1.0';

        $inquiry = new CmsContactInquiry([
            'academy_id' => $academyId,
            'name' => $name,
            'email' => $normalizedEmail ?? $rawEmail ?? '',
            'phone' => $e164Phone ?? $rawPhone,
            'phone_country_code' => $detectedCountry,
            'phone_formatted' => $formattedPhone,
            'subject' => $subject,
            'message' => $message,
            'interested_service' => $courseInterest,
            'course_interest' => $courseInterest,
            'status' => CmsContactInquiry::STATUS_NEW,
            'source' => $source,
            'conversation_id' => $data['conversation_id'] ?? null,
            'consent_text' => $consentText,
            'consented_at' => $consentedAt,
            'consent_version' => $consentVersion,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
        ]);

        $sourceLabel = $source === CmsContactInquiry::SOURCE_CHATBOT ? 'Chatbot Admissions Flow' : 'Public Website Contact Form';
        $inquiry->logActivity('created', "Inquiry received via {$sourceLabel}", $actor);
        $inquiry->save();

        // Link with conversation if present
        if (! empty($data['conversation_id'])) {
            $conversation = CmsChatConversation::find($data['conversation_id']);
            if ($conversation) {
                $conversation->update([
                    'lead_inquiry_id' => $inquiry->id,
                    'visitor_name' => $conversation->visitor_name ?: $name,
                    'visitor_email' => $conversation->visitor_email ?: ($normalizedEmail ?? $rawEmail),
                    'visitor_phone' => $conversation->visitor_phone ?: ($e164Phone ?? $rawPhone),
                ]);
            }
        }

        // Notify Admins & Support Staff
        $this->notifyStaffOfNewInquiry($inquiry);

        return $inquiry;
    }

    /**
     * Assign inquiry to a staff member.
     */
    public function assignInquiry(CmsContactInquiry $inquiry, ?User $staff, ?User $actor = null): bool
    {
        $success = $inquiry->assignTo($staff, $actor);

        if ($success && $staff) {
            try {
                $this->notifications->send(
                    recipient: $staff,
                    category: 'system',
                    title: 'New Contact Inquiry Assigned',
                    message: "You have been assigned inquiry from {$inquiry->name} ({$inquiry->subject}).",
                    link: route('admin.contact-inquiries.index', ['view' => $inquiry->id]),
                    actor: $actor,
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to send inquiry assignment notification: '.$e->getMessage());
            }
        }

        return $success;
    }

    /**
     * Notify administrators and support staff about a newly arrived inquiry.
     */
    protected function notifyStaffOfNewInquiry(CmsContactInquiry $inquiry): void
    {
        $staffMembers = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'support_staff']);
            })
            ->get();

        foreach ($staffMembers as $staff) {
            try {
                $this->notifications->send(
                    recipient: $staff,
                    category: 'system',
                    title: "New Lead / Contact Inquiry: {$inquiry->name}",
                    message: "New inquiry submitted via {$inquiry->source} for {$inquiry->course_interest}.",
                    link: route('admin.contact-inquiries.index', ['view' => $inquiry->id]),
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch inquiry notification: '.$e->getMessage());
            }
        }
    }
}
