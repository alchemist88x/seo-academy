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

    {{-- Tab 4: General Settings --}}
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
