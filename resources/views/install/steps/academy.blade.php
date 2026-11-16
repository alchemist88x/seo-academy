@component('install.layout', [
    'title' => 'Step 7 — Academy Profile Configuration',
    'stepNumber' => 7,
    'totalSteps' => 9,
    'state' => $state,
])
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-text-primary">Academy Identity &amp; Regional Settings</h2>
            <p class="text-xs text-text-secondary mt-1">
                Configure the primary branding, contact information, and regional formatting for your independent academy.
            </p>
        </div>

        <form method="POST" action="{{ route('install.step.academy.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Academy Name <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="academy_name"
                        value="{{ old('academy_name', $state['academy_name'] ?? 'SEO Academy') }}"
                        required
                        class="input input-sm w-full text-xs"
                        placeholder="e.g. Acme SEO Academy"
                    >
                    <p class="text-[11px] text-text-muted mt-1">The public brand name displayed across student portals and certificates.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Academy Slug / Identifier</label>
                    <input
                        type="text"
                        name="academy_slug"
                        value="{{ old('academy_slug', $state['academy_slug'] ?? 'seo-academy') }}"
                        class="input input-sm w-full text-xs font-mono"
                        placeholder="e.g. acme-seo"
                    >
                    <p class="text-[11px] text-text-muted mt-1">Internal URL and identifier slug (lowercase letters and hyphens).</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Support / Contact Email <span class="text-rose-500">*</span></label>
                    <input
                        type="email"
                        name="support_email"
                        value="{{ old('support_email', $state['support_email'] ?? 'support@example.com') }}"
                        required
                        class="input input-sm w-full text-xs"
                        placeholder="support@youracademy.com"
                    >
                    <p class="text-[11px] text-text-muted mt-1">Used for student communications and notification senders.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Default Timezone</label>
                    <select name="timezone" class="input input-sm w-full text-xs">
                        @php
                            $selectedTz = old('timezone', $state['timezone'] ?? 'UTC');
                            $tzList = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Singapore', 'Asia/Kolkata', 'Australia/Sydney'];
                        @endphp
                        @foreach ($tzList as $tz)
                            <option value="{{ $tz }}" {{ $selectedTz === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-text-muted mt-1">Determines course live class schedules and assignment due dates.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Default Locale</label>
                    <select name="locale" class="input input-sm w-full text-xs">
                        @php $loc = old('locale', $state['locale'] ?? 'en'); @endphp
                        <option value="en" {{ $loc === 'en' ? 'selected' : '' }}>English (en)</option>
                        <option value="es" {{ $loc === 'es' ? 'selected' : '' }}>Español (es)</option>
                        <option value="fr" {{ $loc === 'fr' ? 'selected' : '' }}>Français (fr)</option>
                        <option value="de" {{ $loc === 'de' ? 'selected' : '' }}>Deutsch (de)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Currency Code</label>
                    <input
                        type="text"
                        name="currency"
                        value="{{ old('currency', $state['currency'] ?? 'USD') }}"
                        class="input input-sm w-full text-xs font-mono uppercase"
                        placeholder="USD"
                        maxlength="10"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Date Format</label>
                    <select name="date_format" class="input input-sm w-full text-xs font-mono">
                        @php $df = old('date_format', $state['date_format'] ?? 'Y-m-d'); @endphp
                        <option value="Y-m-d" {{ $df === 'Y-m-d' ? 'selected' : '' }}>YYYY-MM-DD (2026-11-16)</option>
                        <option value="m/d/Y" {{ $df === 'm/d/Y' ? 'selected' : '' }}>MM/DD/YYYY (11/16/2026)</option>
                        <option value="d/m/Y" {{ $df === 'd/m/Y' ? 'selected' : '' }}>DD/MM/YYYY (16/11/2026)</option>
                        <option value="M j, Y" {{ $df === 'M j, Y' ? 'selected' : '' }}>Nov 16, 2026</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t border-border-default flex items-center justify-between">
                <a href="{{ route('install.step.storage') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                    &larr; Back to Storage
                </a>

                <button type="submit" class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm">
                    Save Academy &amp; Continue &rarr;
                </button>
            </div>
        </form>
    </div>
@endcomponent
