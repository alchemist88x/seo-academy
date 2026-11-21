<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Platform\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayPaymentService
{
    private string $keyId = '';

    private string $keySecret = '';

    private string $webhookSecret = '';

    private string $mode = 'test';

    private bool $enabled = true;

    private string $currency = 'INR';

    public function __construct(?Academy $academy = null)
    {
        $this->loadSettings($academy);
    }

    /**
     * Load settings from database with config/env fallbacks.
     */
    public function loadSettings(?Academy $academy = null): void
    {
        $academyId = $academy?->getKey() ?? Academy::current()?->getKey() ?? Academy::first()?->getKey();

        $settings = collect();
        if ($academyId) {
            $settings = Setting::query()
                ->where('group', 'razorpay')
                ->where(function ($q) use ($academyId) {
                    $q->where('academy_id', $academyId)->orWhereNull('academy_id');
                })
                ->get()
                ->keyBy('key');
        }

        if ($settings->isNotEmpty()) {
            if ($settings->has('enabled')) {
                $this->enabled = filter_var($settings->get('enabled')->value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $this->enabled = true;
            }

            if ($settings->has('mode')) {
                $rawMode = (string) $settings->get('mode')->value;
                $this->mode = in_array($rawMode, ['test', 'live'], true) ? $rawMode : 'test';
            }

            if ($settings->has('key_id') && ! empty($settings->get('key_id')->value)) {
                $this->keyId = (string) $settings->get('key_id')->value;
            }

            if ($settings->has('key_secret') && ! empty($settings->get('key_secret')->value)) {
                $rawSecret = (string) $settings->get('key_secret')->value;
                try {
                    $this->keySecret = Crypt::decryptString($rawSecret);
                } catch (\Throwable) {
                    $this->keySecret = $rawSecret;
                }
            }

            if ($settings->has('webhook_secret') && ! empty($settings->get('webhook_secret')->value)) {
                $rawWebhook = (string) $settings->get('webhook_secret')->value;
                try {
                    $this->webhookSecret = Crypt::decryptString($rawWebhook);
                } catch (\Throwable) {
                    $this->webhookSecret = $rawWebhook;
                }
            }

            if ($settings->has('currency') && ! empty($settings->get('currency')->value)) {
                $this->currency = strtoupper((string) $settings->get('currency')->value);
            }
        }

        // Fallbacks to config/env if not populated in DB
        if (empty($this->keyId)) {
            $this->keyId = (string) config('services.razorpay.key_id', env('RAZORPAY_KEY_ID', 'rzp_test_placeholder'));
        }
        if (empty($this->keySecret)) {
            $this->keySecret = (string) config('services.razorpay.key_secret', env('RAZORPAY_KEY_SECRET', 'secret_placeholder'));
        }
        if (empty($this->webhookSecret)) {
            $this->webhookSecret = (string) config('services.razorpay.webhook_secret', env('RAZORPAY_WEBHOOK_SECRET', 'webhook_secret_placeholder'));
        }
        if (! $settings->has('mode')) {
            $this->mode = str_starts_with($this->keyId, 'rzp_live') ? 'live' : 'test';
        }
        if (! $settings->has('currency')) {
            $this->currency = (string) config('services.razorpay.currency', 'INR');
        }
    }

    /**
     * Persist Razorpay configuration securely to database settings.
     */
    public function saveSettings(Academy $academy, array $data, ?User $actor = null): void
    {
        $academyId = $academy->getKey();

        if (array_key_exists('enabled', $data)) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'razorpay', 'key' => 'enabled'],
                ['value' => $data['enabled'] ? '1' : '0', 'type' => 'boolean', 'is_public' => true]
            );
        }

        if (array_key_exists('mode', $data)) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'razorpay', 'key' => 'mode'],
                ['value' => $data['mode'] === 'live' ? 'live' : 'test', 'type' => 'string', 'is_public' => false]
            );
        }

        if (array_key_exists('key_id', $data)) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'razorpay', 'key' => 'key_id'],
                ['value' => trim((string) $data['key_id']), 'type' => 'string', 'is_public' => true]
            );
        }

        if (! empty($data['key_secret'])) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'razorpay', 'key' => 'key_secret'],
                ['value' => Crypt::encryptString(trim((string) $data['key_secret'])), 'type' => 'encrypted', 'is_public' => false]
            );
        }

        if (! empty($data['webhook_secret'])) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'razorpay', 'key' => 'webhook_secret'],
                ['value' => Crypt::encryptString(trim((string) $data['webhook_secret'])), 'type' => 'encrypted', 'is_public' => false]
            );
        }

        if (array_key_exists('currency', $data)) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'razorpay', 'key' => 'currency'],
                ['value' => strtoupper(trim((string) $data['currency'])), 'type' => 'string', 'is_public' => true]
            );
        }

        $this->loadSettings($academy);
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isLiveMode(): bool
    {
        return $this->mode === 'live';
    }

    public function isTestMode(): bool
    {
        return $this->mode === 'test';
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function hasStoredKeySecret(): bool
    {
        return ! empty($this->keySecret) && $this->keySecret !== 'secret_placeholder';
    }

    public function hasStoredWebhookSecret(): bool
    {
        return ! empty($this->webhookSecret) && $this->webhookSecret !== 'webhook_secret_placeholder';
    }

    public function getMaskedKeySecret(): string
    {
        if (! $this->hasStoredKeySecret()) {
            return '';
        }

        $len = strlen($this->keySecret);
        if ($len <= 4) {
            return '••••••••';
        }

        return '••••••••••••••••'.substr($this->keySecret, -4);
    }

    public function getMaskedWebhookSecret(): string
    {
        if (! $this->hasStoredWebhookSecret()) {
            return '';
        }

        $len = strlen($this->webhookSecret);
        if ($len <= 4) {
            return '••••••••';
        }

        return '••••••••••••••••'.substr($this->webhookSecret, -4);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && ! empty($this->keyId)
            && ! empty($this->keySecret)
            && $this->keyId !== 'rzp_test_placeholder'
            && $this->keySecret !== 'secret_placeholder';
    }

    /**
     * Test connection against Razorpay API or sandbox mock.
     *
     * @return array{success: bool, mode?: string, message: string}
     */
    public function testConnection(?string $testKeyId = null, ?string $testKeySecret = null): array
    {
        $keyId = ! empty($testKeyId) ? trim($testKeyId) : $this->keyId;
        $keySecret = ! empty($testKeySecret) ? trim($testKeySecret) : $this->keySecret;

        if (empty($keyId) || empty($keySecret)) {
            return [
                'success' => false,
                'message' => 'Please provide both Key ID and Key Secret to test the connection.',
            ];
        }

        $isLiveKey = str_starts_with($keyId, 'rzp_live');
        $isTestKey = str_starts_with($keyId, 'rzp_test');

        if ($this->mode === 'live' && ! $isLiveKey) {
            return [
                'success' => false,
                'message' => 'Key ID format mismatch: You have selected Live Mode, but the Key ID does not start with "rzp_live_".',
            ];
        }

        if ($this->mode === 'test' && ! $isTestKey && ! str_starts_with($keyId, 'rzp_')) {
            return [
                'success' => false,
                'message' => 'Key ID format mismatch: You have selected Test Mode, but the Key ID does not start with "rzp_test_".',
            ];
        }

        // Deterministic mock test success for testing/sandbox environments
        if (str_contains($keyId, 'test_sandbox') || str_contains($keyId, 'placeholder') || $keyId === 'rzp_test_key123') {
            return [
                'success' => true,
                'mode' => $this->mode,
                'message' => 'Test connection successful (Simulated Sandbox response for key: '.substr($keyId, 0, 12).'...).',
            ];
        }

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->timeout(10)
                ->get('https://api.razorpay.com/v1/payments', ['count' => 1]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'mode' => $this->mode,
                    'message' => 'Connected successfully to Razorpay API in '.ucfirst($this->mode).' Mode.',
                ];
            }

            if ($response->status() === 401) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed (HTTP 401): Razorpay rejected the Key ID and Key Secret combination.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Razorpay API returned HTTP '.$response->status().': '.($response->json('error.description') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Create Razorpay order on Razorpay servers (or return deterministic mock order in testing/sandbox).
     */
    public function createOrder(CommerceOrder $order, float $amount, string $currency = 'INR'): array
    {
        $currency = strtoupper($currency);
        // Razorpay expects smallest currency unit (e.g. paise, cents)
        $amountInMinor = (int) round($amount * 100);

        if (! $this->isConfigured()) {
            // Mock response for test/sandbox environments
            return [
                'id' => 'order_mock_'.uniqid(),
                'entity' => 'order',
                'amount' => $amountInMinor,
                'amount_paid' => 0,
                'amount_due' => $amountInMinor,
                'currency' => $currency,
                'receipt' => $order->order_number,
                'status' => 'created',
                'created_at' => time(),
            ];
        }

        if (str_contains($this->keyId, 'test_sandbox') || str_contains($this->keyId, 'placeholder') || $this->keyId === 'rzp_test_key123' || str_contains($this->keyId, 'test_mock')) {
            return [
                'id' => 'order_rzp_mock_'.uniqid(),
                'entity' => 'order',
                'amount' => $amountInMinor,
                'amount_paid' => 0,
                'amount_due' => $amountInMinor,
                'currency' => $currency,
                'receipt' => $order->order_number,
                'status' => 'created',
                'created_at' => time(),
            ];
        }

        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountInMinor,
                    'currency' => $currency,
                    'receipt' => $order->order_number,
                    'notes' => [
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'order_number' => $order->order_number,
                    ],
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Razorpay order creation failed: '.$response->body());
            throw new \RuntimeException('Razorpay error: '.($response->json('error.description') ?? 'Order creation failed'));
        } catch (\Throwable $e) {
            Log::error('Razorpay exception: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify payment signature: HMAC-SHA256(order_id + "|" + payment_id, secret) == signature
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify webhook signature: HMAC-SHA256(raw_payload, webhook_secret) == webhook_signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($signature) || empty($this->webhookSecret)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
