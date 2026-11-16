<div class="max-w-4xl space-y-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-text-primary">System &amp; Application Settings</h1>
        <p class="text-sm text-text-muted mt-1">
            Configure outgoing SMTP delivery, general localization defaults, notification preferences, and inspect storage health.
        </p>
    </div>

    @if (session()->has('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="border-b border-border-default">
        <nav class="-mb-px flex flex-wrap gap-2 sm:gap-6" aria-label="System Settings Tabs">
            @php
                $tabs = [
                    'mail' => 'Email & SMTP',
                    'general' => 'General & Localization',
                    'notifications' => 'Notification Preferences',
                    'storage' => 'Storage & Environment',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="py-3 px-1 border-b-2 text-sm font-medium transition-colors whitespace-nowrap {{ $activeTab === $key
                        ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-text-secondary hover:text-text-primary hover:border-border-default' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab 1: Email & SMTP --}}
    @if ($activeTab === 'mail')
        {{-- SMTP Configuration Card --}}
        <form wire:submit="saveSettings" class="card p-6 space-y-6">
            <div class="pb-4 border-b border-border-default">
                <h2 class="text-base font-semibold text-text-primary">Outbound SMTP Mailer</h2>
                <p class="text-xs text-text-muted mt-1">
                    Configure your SMTP server for sending course assignments, enrollment expiration notices, and support updates.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">SMTP Host</label>
                    <input
                        type="text"
                        wire:model="mail_host"
                        placeholder="smtp.example.com or mail.hosting.com"
                        class="input text-xs"
                    >
                    @error('mail_host') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">SMTP Port</label>
                    <input
                        type="number"
                        wire:model="mail_port"
                        placeholder="587 or 465"
                        class="input text-xs"
                    >
                    @error('mail_port') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Encryption Protocol</label>
                    <select wire:model="mail_encryption" class="input text-xs">
                        <option value="tls">TLS (STARTTLS - Port 587)</option>
                        <option value="ssl">SSL / SMTPS (Port 465)</option>
                        <option value="none">None (Plaintext - Port 25/2525)</option>
                    </select>
                    @error('mail_encryption') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">SMTP Username</label>
                    <input
                        type="text"
                        wire:model="mail_username"
                        placeholder="smtp-user or email address"
                        class="input text-xs"
                    >
                    @error('mail_username') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-text-primary mb-1">
                        SMTP Password
                        @if ($hasStoredPassword)
                            <span class="text-emerald-600 font-normal ml-2">(Stored Encrypted at Rest ✓)</span>
                        @endif
                    </label>
                    <input
                        type="password"
                        wire:model="mail_password"
                        placeholder="{{ $hasStoredPassword ? '•••••••••••• (Leave blank to keep existing password)' : 'Enter SMTP password' }}"
                        class="input text-xs"
                    >
                    <span class="text-[10px] text-text-muted mt-1 block">
                        Passwords are encrypted at rest using AES-256 and never returned to browser JavaScript.
                    </span>
                    @error('mail_password') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">From Sender Email</label>
                    <input
                        type="email"
                        wire:model="mail_from_address"
                        placeholder="notifications@academy.example.com"
                        class="input text-xs"
                    >
                    @error('mail_from_address') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">From Sender Name</label>
                    <input
                        type="text"
                        wire:model="mail_from_name"
                        placeholder="SEO Academy"
                        class="input text-xs"
                    >
                    @error('mail_from_name') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="pt-4 border-t border-border-default flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="btn btn-primary text-xs flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="saveSettings">Save SMTP Configuration</span>
                    <span wire:loading wire:target="saveSettings">Saving...</span>
                </button>
            </div>
        </form>

        {{-- SMTP Testing Card --}}
        <div class="card p-6 space-y-4">
            <div class="pb-3 border-b border-border-default">
                <h2 class="text-base font-semibold text-text-primary">Verify Mailer Connection</h2>
                <p class="text-xs text-text-muted mt-0.5">
                    Send a test email to verify that your mail server credentials, encryption, and port are functioning properly.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-semibold text-text-primary mb-1">Test Recipient Email</label>
                    <input
                        type="email"
                        wire:model="test_recipient"
                        placeholder="admin@example.com"
                        class="input text-xs"
                    >
                    @error('test_recipient') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <button
                    type="button"
                    wire:click="testConnection"
                    wire:loading.attr="disabled"
                    wire:target="testConnection"
                    class="btn btn-secondary text-xs shrink-0 flex items-center gap-1.5"
                >
                    <span wire:loading.remove wire:target="testConnection">Send Test Email</span>
                    <span wire:loading wire:target="testConnection">Connecting...</span>
                </button>
            </div>

            @if ($testStatus === 'success')
                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200">
                    ✓ {{ $testMessage }}
                </div>
            @elseif ($testStatus === 'error')
                <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-200 font-mono break-all">
                    ✕ {{ $testMessage }}
                </div>
            @endif
        </div>
    @endif

    {{-- Tab 2: General & Localization --}}
    @if ($activeTab === 'general')
        <form wire:submit="saveGeneralSettings" class="card p-6 space-y-6">
            <div class="pb-4 border-b border-border-default">
                <h2 class="text-base font-semibold text-text-primary">Localization &amp; Display Defaults</h2>
                <p class="text-xs text-text-muted mt-1">
                    Set default date formatting, time representation, and session timeout options.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Date Format</label>
                    <select wire:model="date_format" class="input text-xs">
                        <option value="Y-m-d">YYYY-MM-DD ({{ now()->format('Y-m-d') }})</option>
                        <option value="m/d/Y">MM/DD/YYYY ({{ now()->format('m/d/Y') }})</option>
                        <option value="d/m/Y">DD/MM/YYYY ({{ now()->format('d/m/Y') }})</option>
                        <option value="d.m.Y">DD.MM.YYYY ({{ now()->format('d.m.Y') }})</option>
                    </select>
                    @error('date_format') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Time Format</label>
                    <select wire:model="time_format" class="input text-xs">
                        <option value="H:i">24-hour ({{ now()->format('H:i') }})</option>
                        <option value="g:i A">12-hour ({{ now()->format('g:i A') }})</option>
                    </select>
                    @error('time_format') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Session Lifetime (Minutes)</label>
                    <input
                        type="number"
                        wire:model="session_lifetime"
                        min="15"
                        max="10080"
                        class="input text-xs"
                    >
                    <span class="text-[10px] text-text-muted mt-1 block">Inactivity period before requiring re-login.</span>
                    @error('session_lifetime') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="pt-4 border-t border-border-default flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="btn btn-primary text-xs flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="saveGeneralSettings">Save General Settings</span>
                    <span wire:loading wire:target="saveGeneralSettings">Saving...</span>
                </button>
            </div>
        </form>
    @endif

    {{-- Tab 3: Notification Preferences --}}
    @if ($activeTab === 'notifications')
        <form wire:submit="saveNotificationSettings" class="card p-6 space-y-6">
            <div class="pb-4 border-b border-border-default">
                <h2 class="text-base font-semibold text-text-primary">System Notification Channels</h2>
                <p class="text-xs text-text-muted mt-1">
                    Control global delivery channels for academy alerts, assignment updates, and course notices.
                </p>
            </div>

            <div class="space-y-4">
                <label class="flex items-start gap-3 p-3 rounded-lg border border-border-default bg-surface-bg cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="email_notifications_enabled"
                        class="mt-1 rounded border-border-default text-primary-600 focus:ring-primary-500"
                    >
                    <div>
                        <p class="text-sm font-semibold text-text-primary">Email Notifications</p>
                        <p class="text-xs text-text-muted">Send automated transaction emails when assignments are submitted, grades published, or certificates earned.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3 rounded-lg border border-border-default bg-surface-bg cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="in_app_notifications_enabled"
                        class="mt-1 rounded border-border-default text-primary-600 focus:ring-primary-500"
                    >
                    <div>
                        <p class="text-sm font-semibold text-text-primary">In-App Notification Center</p>
                        <p class="text-xs text-text-muted">Display bell icon notifications and badges inside the authenticated student and trainer dashboard.</p>
                    </div>
                </label>
            </div>

            <div class="pt-4 border-t border-border-default flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="btn btn-primary text-xs flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="saveNotificationSettings">Save Notification Settings</span>
                    <span wire:loading wire:target="saveNotificationSettings">Saving...</span>
                </button>
            </div>
        </form>
    @endif

    {{-- Tab 4: Storage & Architecture --}}
    @if ($activeTab === 'storage')
        <div class="card p-6 space-y-6">
            <div class="pb-4 border-b border-border-default">
                <h2 class="text-base font-semibold text-text-primary">Storage Configuration &amp; Boundaries</h2>
                <p class="text-xs text-text-muted mt-1">
                    Inspect local filesystem drivers and private storage isolation for shared-hosting compliance.
                </p>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="p-3 rounded-lg border border-border-default bg-surface-bg">
                    <dt class="text-text-muted font-medium">Default Filesystem Disk</dt>
                    <dd class="text-text-primary font-mono font-semibold mt-1">local (Shared-Hosting Compliant)</dd>
                </div>

                <div class="p-3 rounded-lg border border-border-default bg-surface-bg">
                    <dt class="text-text-muted font-medium">Storage Security Boundary</dt>
                    <dd class="text-emerald-600 dark:text-emerald-400 font-semibold mt-1">Isolated Private Disk (Unserved Publicly)</dd>
                </div>

                <div class="p-3 rounded-lg border border-border-default bg-surface-bg">
                    <dt class="text-text-muted font-medium">Private App Storage Path</dt>
                    <dd class="text-text-primary font-mono text-[11px] mt-1 break-all">{{ storage_path('app/private') }}</dd>
                </div>

                <div class="p-3 rounded-lg border border-border-default bg-surface-bg">
                    <dt class="text-text-muted font-medium">Private Media Root</dt>
                    <dd class="text-text-primary font-mono text-[11px] mt-1 break-all">{{ storage_path('app/private_media') }}</dd>
                </div>
            </dl>

            <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-xs text-blue-800 dark:text-blue-200">
                <strong>Shared Hosting Architecture Note:</strong> SEO Academy operates entirely on self-contained local storage without requiring external paid cloud buckets (S3/Azure), Docker containers, or background Node runtimes. All uploaded files, certificates, and media are safely authorized through private controller streaming.
            </div>
        </div>
    @endif
</div>
