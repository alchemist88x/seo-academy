<div class="max-w-5xl space-y-8">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-border-default">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold tracking-wider uppercase text-text-muted">Commerce &amp; Payments</span>
                <span class="text-text-muted">•</span>
                <span class="text-xs font-medium text-primary-600 dark:text-primary-400">Gateway Configuration</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary flex items-center gap-3">
                <x-icon name="credit-card" class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                <span>Razorpay Gateway Settings</span>
            </h1>
            <p class="text-sm text-text-muted mt-1">
                Configure online checkout, API credentials, regional currencies, and automated webhook verification for SEO Academy.
            </p>
        </div>

        {{-- Gateway Status Pill --}}
        <div class="flex items-center gap-2 self-start sm:self-auto">
            @if ($is_enabled)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Gateway Enabled
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    Gateway Disabled
                </span>
            @endif

            @if ($mode === 'live')
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800">
                    <x-icon name="shield" class="w-3.5 h-3.5" />
                    Live Production Mode
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    <x-icon name="code" class="w-3.5 h-3.5" />
                    Test Sandbox Mode
                </span>
            @endif
        </div>
    </div>

    {{-- Session Feedback Alerts --}}
    @if (session()->has('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex items-start gap-3">
            <x-icon name="check-circle" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
            <div class="text-sm font-medium text-emerald-800 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 flex items-start gap-3">
            <x-icon name="alert-triangle" class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
            <div class="text-sm font-medium text-red-800 dark:text-red-200">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Live Mode Warning Banner --}}
    @if ($mode === 'live')
        <div class="p-5 rounded-2xl bg-gradient-to-r from-red-50 via-amber-50 to-orange-50 dark:from-red-950/50 dark:via-amber-950/40 dark:to-orange-950/40 border-2 border-red-300 dark:border-red-800/80 shadow-sm space-y-3">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-300 flex items-center justify-center shrink-0">
                    <x-icon name="alert-triangle" class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-red-900 dark:text-red-100">
                        CRITICAL: Live Production Mode Active
                    </h3>
                    <p class="text-xs text-red-800 dark:text-red-200 mt-1 leading-relaxed">
                        When Live Mode is enabled, student checkouts and installment settlements will trigger <strong>real financial transactions</strong>, deducting actual money from credit cards, UPI, Netbanking, and debit cards. Ensure your production webhook endpoint is configured in your Razorpay Dashboard before receiving live payments.
                    </p>
                </div>
            </div>

            <div class="pt-2 border-t border-red-200 dark:border-red-900/60 flex items-center gap-3">
                <input
                    type="checkbox"
                    id="ack_live_mode"
                    wire:model="acknowledge_live_mode"
                    class="rounded border-red-300 text-red-600 focus:ring-red-500 w-4 h-4 cursor-pointer"
                >
                <label for="ack_live_mode" class="text-xs font-semibold text-red-900 dark:text-red-200 cursor-pointer">
                    I acknowledge that Live Mode processes real customer payments and charges real monetary accounts.
                </label>
            </div>
            @error('acknowledge_live_mode')
                <p class="text-xs font-medium text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
            @enderror
        </div>
    @else
        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
            <x-icon name="info" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
                <strong>Sandbox / Test Mode Active:</strong> Checkouts in this mode use simulated cards and dummy payments. No real funds will be charged. Use test cards provided in Razorpay documentation.
            </div>
        </div>
    @endif

    {{-- Main Configuration Form --}}
    <form wire:submit="saveSettings" class="card p-6 sm:p-8 space-y-8 bg-card border border-border-default rounded-2xl shadow-sm">
        {{-- Section 1: Operating Mode & Toggle --}}
        <div>
            <div class="pb-4 border-b border-border-default mb-6">
                <h2 class="text-base font-semibold text-text-primary">Gateway Status &amp; Environment</h2>
                <p class="text-xs text-text-muted mt-1">Control online gateway availability and toggle between Sandbox testing and Live production processing.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                {{-- Enable / Disable Toggle --}}
                <div class="p-4 rounded-xl bg-card border border-border-default flex items-center justify-between">
                    <div>
                        <span class="block text-sm font-semibold text-text-primary">Razorpay Online Gateway</span>
                        <span class="block text-xs text-text-muted mt-0.5">Allow students to purchase courses and bundles via Razorpay.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="is_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                    </label>
                </div>

                {{-- Mode Selection --}}
                <div class="p-4 rounded-xl bg-card border border-border-default space-y-2">
                    <span class="block text-sm font-semibold text-text-primary">Operating Mode</span>
                    <div class="flex items-center gap-6 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                value="test"
                                wire:model.live="mode"
                                class="text-primary-600 focus:ring-primary-500"
                            >
                            <span class="text-xs font-semibold text-text-primary">Test Mode (Sandbox)</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                value="live"
                                wire:model.live="mode"
                                class="text-red-600 focus:ring-red-500"
                            >
                            <span class="text-xs font-semibold text-red-600 dark:text-red-400">Live Mode (Production)</span>
                        </label>
                    </div>
                    @error('mode')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Section 2: API Credentials --}}
        <div>
            <div class="pb-4 border-b border-border-default mb-6">
                <h2 class="text-base font-semibold text-text-primary">API Credentials &amp; Encryption</h2>
                <p class="text-xs text-text-muted mt-1">
                    API keys are securely encrypted at rest using AES-256-CBC and never exposed to client-side scripts.
                </p>
            </div>

            <div class="space-y-5">
                {{-- Key ID --}}
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">
                        Razorpay Key ID <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="key_id"
                        placeholder="{{ $mode === 'live' ? 'rzp_live_xxxxxxxxxxxxxxxx' : 'rzp_test_xxxxxxxxxxxxxxxx' }}"
                        class="input text-xs font-mono w-full @error('key_id') border-red-500 @enderror"
                    >
                    <div class="flex items-center justify-between mt-1.5 text-xs text-text-muted">
                        <span>Format: {{ $mode === 'live' ? 'Must start with "rzp_live_"' : 'Starts with "rzp_test_"' }}</span>
                        <span>Obtained from Razorpay Dashboard > Settings > API Keys</span>
                    </div>
                    @error('key_id')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Key Secret --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-text-primary">
                            Razorpay Key Secret <span class="text-red-500">*</span>
                        </label>
                        @if ($hasStoredKeySecret)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <x-icon name="lock" class="w-3 h-3" />
                                Encrypted: {{ $maskedKeySecret }}
                            </span>
                        @endif
                    </div>
                    <input
                        type="password"
                        wire:model="key_secret"
                        placeholder="{{ $hasStoredKeySecret ? '•••••••••••••••• (Leave blank to keep existing encrypted secret)' : 'Enter Razorpay Key Secret' }}"
                        class="input text-xs font-mono w-full @error('key_secret') border-red-500 @enderror"
                        autocomplete="new-password"
                    >
                    <p class="text-xs text-text-muted mt-1.5">
                        {{ $hasStoredKeySecret ? 'A Key Secret is currently stored and encrypted. Only enter a new value if you wish to overwrite it.' : 'Used to authenticate order creation and verify HMAC-SHA256 payment signatures.' }}
                    </p>
                    @error('key_secret')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Webhook Secret --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-text-primary">
                            Razorpay Webhook Secret <span class="text-red-500">*</span>
                        </label>
                        @if ($hasStoredWebhookSecret)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <x-icon name="lock" class="w-3 h-3" />
                                Encrypted: {{ $maskedWebhookSecret }}
                            </span>
                        @endif
                    </div>
                    <input
                        type="password"
                        wire:model="webhook_secret"
                        placeholder="{{ $hasStoredWebhookSecret ? '•••••••••••••••• (Leave blank to keep existing encrypted secret)' : 'Enter Webhook Secret configured in Razorpay Dashboard' }}"
                        class="input text-xs font-mono w-full @error('webhook_secret') border-red-500 @enderror"
                        autocomplete="new-password"
                    >
                    <p class="text-xs text-text-muted mt-1.5">
                        {{ $hasStoredWebhookSecret ? 'A Webhook Secret is stored and encrypted. Only enter a new value to update.' : 'Must match the Secret entered in Razorpay Dashboard > Settings > Webhooks.' }}
                    </p>
                    @error('webhook_secret')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Section 3: Currency & Settlement --}}
        <div>
            <div class="pb-4 border-b border-border-default mb-6">
                <h2 class="text-base font-semibold text-text-primary">Settlement Currency</h2>
                <p class="text-xs text-text-muted mt-1">Select the primary currency Razorpay uses for transaction authorization.</p>
            </div>

            <div class="max-w-md">
                <label class="block text-xs font-semibold text-text-primary mb-1">Default Gateway Currency</label>
                <select wire:model="currency" class="select text-xs w-full">
                    <option value="INR">INR — Indian Rupee (₹) [Recommended for UPI &amp; RuPay]</option>
                    <option value="USD">USD — US Dollar ($)</option>
                    <option value="EUR">EUR — Euro (€)</option>
                    <option value="GBP">GBP — British Pound (£)</option>
                    <option value="AED">AED — United Arab Emirates Dirham (د.إ)</option>
                    <option value="AUD">AUD — Australian Dollar (A$)</option>
                    <option value="CAD">CAD — Canadian Dollar (C$)</option>
                    <option value="SGD">SGD — Singapore Dollar (S$)</option>
                    <option value="SAR">SAR — Saudi Riyal (﷼)</option>
                </select>
                <p class="text-xs text-text-muted mt-1.5">
                    Domestic Indian payment methods (UPI, QR, Netbanking) strictly require INR. Multi-currency international cards are auto-converted by Razorpay.
                </p>
                @error('currency')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Test Result Alert --}}
        @if ($testResultStatus)
            <div class="p-4 rounded-xl {{ $testResultStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200' }} text-sm flex items-start gap-3">
                <x-icon name="{{ $testResultStatus === 'success' ? 'check-circle' : 'alert-circle' }}" class="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                    <strong>{{ $testResultStatus === 'success' ? 'Connection Verified:' : 'Connection Failed:' }}</strong>
                    <p class="text-xs mt-0.5">{{ $testResultMessage }}</p>
                </div>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="pt-6 border-t border-border-default flex flex-col sm:flex-row items-center justify-between gap-4">
            <button
                type="button"
                wire:click="testConnection"
                wire:loading.attr="disabled"
                class="btn btn-secondary text-xs w-full sm:w-auto flex items-center justify-center gap-2"
            >
                <span wire:loading.remove wire:target="testConnection" class="flex items-center gap-1.5">
                    <x-icon name="activity" class="w-4 h-4" />
                    Test Razorpay Connection
                </span>
                <span wire:loading wire:target="testConnection" class="flex items-center gap-1.5">
                    <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Testing Connection...
                </span>
            </button>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="btn btn-primary text-xs w-full sm:w-auto flex items-center justify-center gap-2"
            >
                <span wire:loading.remove wire:target="saveSettings" class="flex items-center gap-1.5">
                    <x-icon name="check" class="w-4 h-4" />
                    Save &amp; Encrypt Settings
                </span>
                <span wire:loading wire:target="saveSettings" class="flex items-center gap-1.5">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Encrypting &amp; Saving...
                </span>
            </button>
        </div>
    </form>

    {{-- Webhook Configuration & Documentation Panel --}}
    <div class="card p-6 sm:p-8 space-y-6 bg-card border border-border-default rounded-2xl shadow-sm">
        <div class="pb-4 border-b border-border-default flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-text-primary flex items-center gap-2">
                    <x-icon name="link" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    Automated Webhook Integration Guide
                </h2>
                <p class="text-xs text-text-muted mt-1">
                    Razorpay webhooks guarantee that student enrollments and installment status updates occur in real-time even if a visitor closes their browser tab during checkout.
                </p>
            </div>
            <span class="px-2.5 py-1 rounded text-xs font-mono bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-800">
                HMAC-SHA256
            </span>
        </div>

        {{-- Registered Webhook URL Box --}}
        <div>
            <label class="block text-xs font-semibold text-text-primary mb-1.5">Production Webhook Endpoint (POST)</label>
            <div class="flex items-center gap-2" x-data="{ copied: false }">
                <input
                    type="text"
                    readonly
                    value="{{ $webhookUrl }}"
                    class="input text-xs font-mono bg-slate-50 dark:bg-slate-900/60 w-full text-slate-800 dark:text-slate-200 select-all"
                >
                <button
                    type="button"
                    @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                    class="btn btn-secondary text-xs shrink-0 flex items-center gap-1.5"
                >
                    <span x-show="!copied" class="flex items-center gap-1">
                        <x-icon name="copy" class="w-3.5 h-3.5" />
                        Copy URL
                    </span>
                    <span x-show="copied" class="text-emerald-600 font-semibold flex items-center gap-1" style="display: none;">
                        <x-icon name="check" class="w-3.5 h-3.5" />
                        Copied!
                    </span>
                </button>
            </div>
            <p class="text-xs text-text-muted mt-1.5">
                Paste this exact URL into your Razorpay Dashboard under <strong>Settings > Webhooks > Add New Webhook</strong>.
            </p>
        </div>

        {{-- Required Events Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-border-default space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold font-mono text-primary-600 dark:text-primary-400">payment.captured</span>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 font-medium">Required</span>
                </div>
                <p class="text-xs text-text-muted leading-relaxed">
                    Fires when Razorpay captures authorized student funds. Triggers automatic order payment status transition, transaction recording, and course access granting.
                </p>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-border-default space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold font-mono text-primary-600 dark:text-primary-400">order.paid</span>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 font-medium">Required</span>
                </div>
                <p class="text-xs text-text-muted leading-relaxed">
                    Fires when an order is settled. Acts as a redundancy safeguard to confirm installment and full bundle settlements across all payment channels.
                </p>
            </div>
        </div>

        {{-- Infrastructure & Security Checklist --}}
        <div class="space-y-3 pt-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Production Architecture &amp; Delivery Guarantees</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                <div class="p-3 rounded-lg border border-border-default bg-card space-y-1">
                    <div class="font-semibold text-text-primary flex items-center gap-1.5">
                        <x-icon name="shield" class="w-4 h-4 text-emerald-600" />
                        Signature Verification
                    </div>
                    <p class="text-text-muted text-[11px] leading-relaxed">
                        Every webhook payload is verified using HMAC-SHA256 against <code class="font-mono">X-Razorpay-Signature</code>. Forged requests are rejected immediately with HTTP 400.
                    </p>
                </div>

                <div class="p-3 rounded-lg border border-border-default bg-card space-y-1">
                    <div class="font-semibold text-text-primary flex items-center gap-1.5">
                        <x-icon name="check-circle" class="w-4 h-4 text-primary-600" />
                        Idempotency Defense
                    </div>
                    <p class="text-text-muted text-[11px] leading-relaxed">
                        Replayed webhooks are checked against existing transaction IDs in the database. Duplicates receive HTTP 200 OK without double-charging or duplicate enrollments.
                    </p>
                </div>

                <div class="p-3 rounded-lg border border-border-default bg-card space-y-1">
                    <div class="font-semibold text-text-primary flex items-center gap-1.5">
                        <x-icon name="globe" class="w-4 h-4 text-blue-600" />
                        Cloudflare &amp; CloudPanel
                    </div>
                    <p class="text-text-muted text-[11px] leading-relaxed">
                        CSRF verification is explicitly exempted for <code class="font-mono">api/webhooks/*</code>. Ensure Cloudflare WAF does not block POST requests from Razorpay IPs.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
