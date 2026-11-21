<?php

namespace App\Livewire\Admin\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payment Gateway Configuration — Razorpay')]
class PaymentSettingsManager extends Component
{
    use RecordsAuditLog;

    public bool $is_enabled = false;

    public string $mode = 'test'; // 'test' | 'live'

    public string $key_id = '';

    public string $key_secret = '';

    public string $webhook_secret = '';

    public string $currency = 'INR';

    public bool $hasStoredKeySecret = false;

    public bool $hasStoredWebhookSecret = false;

    public string $maskedKeySecret = '';

    public string $maskedWebhookSecret = '';

    public bool $acknowledge_live_mode = false;

    public string $webhookUrl = '';

    public ?string $testResultStatus = null; // 'success' | 'error'

    public ?string $testResultMessage = null;

    public function mount(TenantContext $tenantContext, RazorpayPaymentService $razorpayService): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized access to Payment Settings.');
        }

        $academy = $tenantContext->getAcademy() ?? Academy::current() ?? Academy::first();
        if ($academy) {
            $razorpayService->loadSettings($academy);
        }

        $this->webhookUrl = url('/api/webhooks/razorpay');

        $this->is_enabled = $razorpayService->isEnabled();
        $this->mode = $razorpayService->getMode();
        $this->currency = $razorpayService->getCurrency();

        $keyId = $razorpayService->getKeyId();
        $this->key_id = ($keyId !== 'rzp_test_placeholder' && ! empty($keyId)) ? $keyId : '';

        $this->hasStoredKeySecret = $razorpayService->hasStoredKeySecret();
        $this->hasStoredWebhookSecret = $razorpayService->hasStoredWebhookSecret();
        $this->maskedKeySecret = $razorpayService->getMaskedKeySecret();
        $this->maskedWebhookSecret = $razorpayService->getMaskedWebhookSecret();

        $this->acknowledge_live_mode = $this->mode === 'live';
    }

    public function testConnection(RazorpayPaymentService $razorpayService): void
    {
        $this->testResultStatus = null;
        $this->testResultMessage = null;

        $keyIdToTest = trim($this->key_id);
        $keySecretToTest = ! empty($this->key_secret) ? trim($this->key_secret) : null;

        if (empty($keyIdToTest)) {
            $this->testResultStatus = 'error';
            $this->testResultMessage = 'Please enter a Razorpay Key ID before testing connection.';

            return;
        }

        if (empty($keySecretToTest) && ! $this->hasStoredKeySecret) {
            $this->testResultStatus = 'error';
            $this->testResultMessage = 'Please enter a Razorpay Key Secret before testing connection.';

            return;
        }

        $result = $razorpayService->testConnection($keyIdToTest, $keySecretToTest);

        $this->testResultStatus = $result['success'] ? 'success' : 'error';
        $this->testResultMessage = $result['message'];

        if ($result['success']) {
            session()->flash('test_success', $result['message']);
        } else {
            session()->flash('test_error', $result['message']);
        }
    }

    public function saveSettings(TenantContext $tenantContext, RazorpayPaymentService $razorpayService): void
    {
        $rules = [
            'currency' => 'required|string|in:INR,USD,EUR,GBP,AED,AUD,CAD,SGD,SAR',
            'mode' => 'required|in:test,live',
        ];

        if ($this->is_enabled) {
            $rules['key_id'] = 'required|string|max:255';

            if (! $this->hasStoredKeySecret && empty($this->key_secret)) {
                $rules['key_secret'] = 'required|string|max:255';
            }

            if (! $this->hasStoredWebhookSecret && empty($this->webhook_secret)) {
                $rules['webhook_secret'] = 'required|string|max:255';
            }

            if ($this->mode === 'live') {
                $rules['acknowledge_live_mode'] = 'accepted';
            }
        }

        $messages = [
            'acknowledge_live_mode.accepted' => 'You must check the acknowledgment checkbox before activating Live Production Mode.',
            'key_id.required' => 'A valid Razorpay Key ID is required when the gateway is enabled.',
            'key_secret.required' => 'A valid Razorpay Key Secret is required.',
            'webhook_secret.required' => 'A Webhook Secret is required to verify Razorpay webhooks.',
        ];

        $this->validate($rules, $messages);

        // Strict prefix validation
        if ($this->is_enabled) {
            $keyId = trim($this->key_id);
            if ($this->mode === 'live' && ! str_starts_with($keyId, 'rzp_live_')) {
                $this->addError('key_id', 'Live mode requires a production Key ID starting with "rzp_live_".');

                return;
            }

            if ($this->mode === 'test' && str_starts_with($keyId, 'rzp_live_')) {
                $this->addError('key_id', 'You have entered a Live Key ID (rzp_live_) while in Test Mode. Please switch to Live Mode or provide a test key starting with "rzp_test_".');

                return;
            }
        }

        $academy = $tenantContext->getAcademy() ?? Academy::current() ?? Academy::first();
        if (! $academy) {
            session()->flash('error', 'No active academy context found.');

            return;
        }

        $dataToSave = [
            'enabled' => $this->is_enabled,
            'mode' => $this->mode,
            'key_id' => trim($this->key_id),
            'currency' => strtoupper($this->currency),
        ];

        if (! empty(trim($this->key_secret))) {
            $dataToSave['key_secret'] = trim($this->key_secret);
        }

        if (! empty(trim($this->webhook_secret))) {
            $dataToSave['webhook_secret'] = trim($this->webhook_secret);
        }

        $razorpayService->saveSettings($academy, $dataToSave, Auth::user());

        // Audit log without sensitive secrets
        $this->recordAudit(
            $academy,
            Auth::user(),
            'settings.razorpay_updated',
            Setting::class,
            'razorpay_config',
            [
                'enabled' => $this->is_enabled,
                'mode' => $this->mode,
                'currency' => $this->currency,
                'key_id' => $this->key_id,
                'has_key_secret' => $razorpayService->hasStoredKeySecret(),
                'has_webhook_secret' => $razorpayService->hasStoredWebhookSecret(),
            ]
        );

        // Reset plain text input properties immediately
        $this->key_secret = '';
        $this->webhook_secret = '';

        // Refresh masking state
        $this->hasStoredKeySecret = $razorpayService->hasStoredKeySecret();
        $this->hasStoredWebhookSecret = $razorpayService->hasStoredWebhookSecret();
        $this->maskedKeySecret = $razorpayService->getMaskedKeySecret();
        $this->maskedWebhookSecret = $razorpayService->getMaskedWebhookSecret();

        session()->flash('success', 'Razorpay payment gateway settings saved and encrypted successfully.');
    }

    public function render(): View
    {
        return view('livewire.admin.commerce.payment-settings-manager');
    }
}
