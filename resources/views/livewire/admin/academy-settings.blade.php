<div class="space-y-6">
    <x-page-header
        title="Academy Settings"
        :subtitle="'Manage profile, branding, domain, and general settings for ' . $name . '.'"
    >
        <x-slot:actions>
            <a
                href="{{ route('admin.dashboard') }}"
                class="btn btn-secondary btn-md inline-flex items-center gap-2"
            >
                &larr; Back to Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Tab Navigation --}}
    <div class="border-b border-border-default">
        <nav class="-mb-px flex flex-wrap gap-2 sm:gap-6" aria-label="Settings Tabs">
            @php
                $tabs = [
                    'profile' => 'Profile & Contact',
                    'branding' => 'Branding & Logo',
                    'domain' => 'Domain Configuration',
                    'currency' => 'Currency & Regional Pricing',
                    'settings' => 'General Settings',
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

    {{-- Tab 1: Profile & Contact --}}
    @if ($activeTab === 'profile')
        <form wire:submit="saveProfile">
            <x-card title="Profile & Contact" description="Core academy identity, contact details, timezone, and locale.">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-input
                        name="name"
                        label="Academy Name"
                        wire:model="name"
                        required
                    />
                    <x-input
                        name="slug"
                        label="URL Slug"
                        wire:model="slug"
                        hint="Lowercase letters, numbers, dashes, or underscores."
                        required
                    />

                    <div class="md:col-span-2">
                        <label for="description" class="form-label">Description</label>
                        <textarea
                            id="description"
                            rows="3"
                            wire:model="description"
                            class="form-input @error('description') error @enderror"
                            placeholder="Brief overview of your academy..."
                        ></textarea>
                        @error('description')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-input
                        name="support_email"
                        label="Support Email"
                        type="email"
                        wire:model="support_email"
                        placeholder="support@academy.example.com"
                    />
                    <x-input
                        name="contact_phone"
                        label="Contact Phone"
                        wire:model="contact_phone"
                        placeholder="+1 (555) 123-4567"
                    />
                    <x-input
                        name="website_url"
                        label="Website URL"
                        type="url"
                        wire:model="website_url"
                        placeholder="https://academy.example.com"
                    />
                    <x-input
                        name="address"
                        label="Address"
                        wire:model="address"
                        placeholder="123 Main St, City, Country"
                    />
                    <x-input
                        name="timezone"
                        label="Timezone"
                        wire:model="timezone"
                        hint="IANA timezone identifier (e.g. UTC, America/New_York, Asia/Kolkata)."
                        required
                    />
                    <x-input
                        name="locale"
                        label="Locale"
                        wire:model="locale"
                        hint="Default language code (e.g. en, en_US)."
                        required
                    />
                </div>

                <x-slot:footer>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveProfile">Save Profile</span>
                        <span wire:loading wire:target="saveProfile">Saving...</span>
                    </x-button>
                </x-slot:footer>
            </x-card>
        </form>
    @endif

    {{-- Tab 2: Branding & Logo --}}
    @if ($activeTab === 'branding')
        <form wire:submit="saveBranding">
            <x-card title="Branding & Logo" description="Customize your academy colors, logo, and favicon.">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="primary_color" class="form-label">Primary Color</label>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                wire:model.live="primary_color"
                                class="h-10 w-12 rounded border border-border-default bg-transparent cursor-pointer"
                            />
                            <input
                                id="primary_color"
                                type="text"
                                wire:model="primary_color"
                                class="form-input @error('primary_color') error @enderror"
                                placeholder="#2563eb"
                            />
                        </div>
                        @error('primary_color')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="secondary_color" class="form-label">Secondary Color</label>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                wire:model.live="secondary_color"
                                class="h-10 w-12 rounded border border-border-default bg-transparent cursor-pointer"
                            />
                            <input
                                id="secondary_color"
                                type="text"
                                wire:model="secondary_color"
                                class="form-input @error('secondary_color') error @enderror"
                                placeholder="#0f172a"
                            />
                        </div>
                        @error('secondary_color')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="logo" class="form-label">Academy Logo</label>
                        @if ($currentLogoUrl)
                            <div class="mb-3 p-3 rounded-lg border border-border-default bg-surface-subtle inline-flex items-center gap-3">
                                <img src="{{ $currentLogoUrl }}" alt="Current Academy Logo" class="h-12 w-auto object-contain rounded" />
                                <span class="text-xs text-text-muted">Current logo</span>
                            </div>
                        @endif
                        <input
                            id="logo"
                            type="file"
                            wire:model="logo"
                            accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-sm text-text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                        />
                        <p class="mt-1 text-xs text-text-muted">PNG, JPG, or WEBP up to 2MB.</p>
                        @error('logo')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="favicon" class="form-label">Favicon</label>
                        @if ($currentFaviconUrl)
                            <div class="mb-3 p-3 rounded-lg border border-border-default bg-surface-subtle inline-flex items-center gap-3">
                                <img src="{{ $currentFaviconUrl }}" alt="Current Favicon" class="h-8 w-8 object-contain rounded" />
                                <span class="text-xs text-text-muted">Current favicon</span>
                            </div>
                        @endif
                        <input
                            id="favicon"
                            type="file"
                            wire:model="favicon"
                            accept="image/png,image/x-icon,image/webp,image/jpeg"
                            class="block w-full text-sm text-text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                        />
                        <p class="mt-1 text-xs text-text-muted">PNG, ICO, or WEBP up to 512KB.</p>
                        @error('favicon')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-slot:footer>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveBranding">Save Branding</span>
                        <span wire:loading wire:target="saveBranding">Saving...</span>
                    </x-button>
                </x-slot:footer>
            </x-card>
        </form>
    @endif

    {{-- Tab 3: Domain Configuration --}}
    @if ($activeTab === 'domain')
        <form wire:submit="saveDomain">
            <x-card title="Domain Configuration" description="Configure tenant subdomain and custom domain routing.">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-input
                        name="subdomain"
                        label="Subdomain"
                        wire:model="subdomain"
                        placeholder="acme"
                        hint="Lowercase alphanumeric and hyphens only (e.g. acme)."
                    />
                    <x-input
                        name="custom_domain"
                        label="Custom Domain"
                        wire:model="custom_domain"
                        placeholder="learn.acme.com"
                        hint="Point a CNAME record to the platform host before enabling."
                    />
                </div>

                <x-slot:footer>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveDomain">Save Domain</span>
                        <span wire:loading wire:target="saveDomain">Saving...</span>
                    </x-button>
                </x-slot:footer>
            </x-card>
        </form>
    @endif

    {{-- Tab 4: Currency & Regional Pricing --}}
    @if ($activeTab === 'currency')
        <form wire:submit="saveCurrencySettings">
            <x-card title="Currency & Regional Pricing" description="Configure the global default currency, exchange conversions, and regional pricing behavior.">
                <div class="space-y-6">
                    <div class="bg-primary-50 dark:bg-primary-950/30 border border-primary-200 dark:border-primary-800 rounded-lg p-4 text-sm text-text-primary">
                        <div class="flex items-start gap-3">
                            <x-icon name="information-circle" class="w-5 h-5 text-primary-600 mt-0.5 shrink-0" />
                            <div>
                                <span class="font-semibold text-text-primary">Global Pricing Rule:</span>
                                All courses, bundles, checkout summaries, and reports will respect this default currency unless regional pricing or visitor currency selection is active. Existing transaction records, invoices, and historical orders preserve their original transaction currency.
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="default_currency" class="form-label font-medium text-text-primary mb-1">
                                Default Academy Currency <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="default_currency"
                                wire:model="default_currency"
                                class="form-input w-full"
                            >
                                <option value="INR">INR — Indian Rupee (₹)</option>
                                <option value="USD">USD — US Dollar ($)</option>
                                <option value="AED">AED — UAE Dirham (AED)</option>
                                <option value="EUR">EUR — Euro (€)</option>
                                <option value="GBP">GBP — British Pound (£)</option>
                                <option value="AUD">AUD — Australian Dollar (A$)</option>
                                <option value="CAD">CAD — Canadian Dollar (C$)</option>
                                <option value="SGD">SGD — Singapore Dollar (S$)</option>
                                <option value="SAR">SAR — Saudi Riyal (SAR)</option>
                            </select>
                            <p class="text-xs text-text-muted mt-1">
                                Used as the primary base currency throughout the entire academy for new courses, bundles, and public pricing.
                            </p>
                            @error('default_currency')
                                <p class="form-error mt-1 text-sm text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4">
                            <label class="form-label font-medium text-text-primary">Regional & Visitor Options</label>

                            <div class="flex items-start gap-3 p-3 bg-surface-alt rounded-lg border border-border-default">
                                <input
                                    type="checkbox"
                                    id="enable_regional_pricing"
                                    wire:model="enable_regional_pricing"
                                    class="mt-1 h-4 w-4 rounded border-border-default text-primary-600 focus:ring-primary-500"
                                />
                                <div>
                                    <label for="enable_regional_pricing" class="font-medium text-sm text-text-primary cursor-pointer">
                                        Enable Regional Currency Detection
                                    </label>
                                    <p class="text-xs text-text-muted">
                                        Automatically presents prices in the visitor's local currency based on GeoIP / Cloudflare country headers if regional pricing is defined.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 p-3 bg-surface-alt rounded-lg border border-border-default">
                                <input
                                    type="checkbox"
                                    id="enable_currency_switcher"
                                    wire:model="enable_currency_switcher"
                                    class="mt-1 h-4 w-4 rounded border-border-default text-primary-600 focus:ring-primary-500"
                                />
                                <div>
                                    <label for="enable_currency_switcher" class="font-medium text-sm text-text-primary cursor-pointer">
                                        Enable Visitor Currency Switcher
                                    </label>
                                    <p class="text-xs text-text-muted">
                                        Permit visitors and students to manually select their preferred currency from public pages and checkout.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label font-medium text-text-primary mb-2">Enabled Currencies</label>
                        <p class="text-xs text-text-muted mb-3">Select which currencies can be used for pricing and checkout.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            @foreach (\App\Domain\Commerce\Services\CurrencyAndPricingService::SUPPORTED_CURRENCIES as $code => $curr)
                                <label class="flex items-center gap-2 p-2.5 rounded border border-border-default bg-surface hover:bg-surface-alt cursor-pointer text-sm">
                                    <input
                                        type="checkbox"
                                        value="{{ $code }}"
                                        wire:model="enabled_currencies"
                                        class="h-4 w-4 rounded border-border-default text-primary-600 focus:ring-primary-500"
                                    />
                                    <span class="font-medium text-text-primary">{{ $code }}</span>
                                    <span class="text-text-muted text-xs">({{ $curr['symbol'] }})</span>
                                </label>
                            @endforeach
                        </div>
                        @error('enabled_currencies')
                            <p class="form-error mt-1 text-sm text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-slot:footer>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveCurrencySettings">Save Currency Settings</span>
                        <span wire:loading wire:target="saveCurrencySettings">Saving...</span>
                    </x-button>
                </x-slot:footer>
            </x-card>
        </form>
    @endif

    {{-- Tab 5: General Settings --}}
    @if ($activeTab === 'settings')
        <form wire:submit="saveSettings">
            <x-card title="General Settings" description="Control registration, enrollment defaults, certificates, and email sender.">
                <div class="space-y-5">
                    <div class="flex items-start gap-3">
                        <input
                            id="allow_self_registration"
                            type="checkbox"
                            wire:model="allow_self_registration"
                            class="mt-1 h-4 w-4 rounded border-border-default text-primary-600 focus:ring-primary-500"
                        />
                        <div>
                            <label for="allow_self_registration" class="text-sm font-medium text-text-primary">
                                Allow Student Self-Registration
                            </label>
                            <p class="text-xs text-text-muted">
                                When enabled, prospective students can register directly on this academy's portal.
                            </p>
                            @error('allow_self_registration')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-input
                            name="default_enrollment_expiry_days"
                            label="Default Enrollment Expiry (Days)"
                            type="number"
                            min="1"
                            max="3650"
                            wire:model="default_enrollment_expiry_days"
                            required
                        />
                        <x-input
                            name="certificate_prefix"
                            label="Certificate Prefix"
                            wire:model="certificate_prefix"
                            hint="Prefix for generated certificate codes (e.g. SEO)."
                            required
                        />
                        <x-input
                            name="email_sender_name"
                            label="Email Sender Name"
                            wire:model="email_sender_name"
                            hint="Display name for outgoing academy emails."
                            required
                        />
                    </div>
                </div>

                <x-slot:footer>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveSettings">Save Settings</span>
                        <span wire:loading wire:target="saveSettings">Saving...</span>
                    </x-button>
                </x-slot:footer>
            </x-card>
        </form>
    @endif
</div>
