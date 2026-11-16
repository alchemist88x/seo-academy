<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 8 — Academy Organization Profile</h2>
        <p class="text-xs text-text-secondary mt-1">
            Provide the identity, primary support contact, and localization preferences for your academy.
        </p>
    </div>

    {{-- Form Fields --}}
    <div class="space-y-4">
        <div>
            <label for="academyName" class="block text-xs font-bold text-text-primary mb-1">
                Academy Name *
            </label>
            <input
                id="academyName"
                type="text"
                wire:model="academyName"
                class="input input-sm w-full text-xs @error('academyName') border-rose-500 @enderror"
                placeholder="e.g. Apex Marketing Academy"
            />
            @error('academyName')
                <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="academySlug" class="block text-xs font-bold text-text-primary mb-1">
                    Academy URL Slug
                </label>
                <input
                    id="academySlug"
                    type="text"
                    wire:model="academySlug"
                    class="input input-sm w-full text-xs font-mono @error('academySlug') border-rose-500 @enderror"
                    placeholder="apex-marketing"
                />
                @error('academySlug')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="supportEmail" class="block text-xs font-bold text-text-primary mb-1">
                    Official Support Email *
                </label>
                <input
                    id="supportEmail"
                    type="email"
                    wire:model="supportEmail"
                    class="input input-sm w-full text-xs @error('supportEmail') border-rose-500 @enderror"
                    placeholder="support@youracademy.com"
                />
                @error('supportEmail')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="timezone" class="block text-xs font-bold text-text-primary mb-1">
                    Default Timezone *
                </label>
                <select
                    id="timezone"
                    wire:model="timezone"
                    class="select select-sm w-full text-xs @error('timezone') border-rose-500 @enderror"
                >
                    <option value="UTC">UTC (Coordinated Universal Time)</option>
                    <option value="America/New_York">America/New_York (EST/EDT)</option>
                    <option value="America/Chicago">America/Chicago (CST/CDT)</option>
                    <option value="America/Denver">America/Denver (MST/MDT)</option>
                    <option value="America/Los_Angeles">America/Los_Angeles (PST/PDT)</option>
                    <option value="Europe/London">Europe/London (GMT/BST)</option>
                    <option value="Europe/Paris">Europe/Paris (CET/CEST)</option>
                    <option value="Europe/Berlin">Europe/Berlin (CET/CEST)</option>
                    <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                    <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                    <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                    <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                    <option value="Australia/Sydney">Australia/Sydney (AEST/AEDT)</option>
                </select>
                @error('timezone')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="locale" class="block text-xs font-bold text-text-primary mb-1">
                    System Language *
                </label>
                <select
                    id="locale"
                    wire:model="locale"
                    class="select select-sm w-full text-xs @error('locale') border-rose-500 @enderror"
                >
                    <option value="en">English (en)</option>
                    <option value="es">Español (es)</option>
                    <option value="fr">Français (fr)</option>
                    <option value="de">Deutsch (de)</option>
                </select>
                @error('locale')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="currency" class="block text-xs font-bold text-text-primary mb-1">
                    Default Currency *
                </label>
                <select
                    id="currency"
                    wire:model="currency"
                    class="select select-sm w-full text-xs @error('currency') border-rose-500 @enderror"
                >
                    <option value="USD">USD ($) — US Dollar</option>
                    <option value="EUR">EUR (€) — Euro</option>
                    <option value="GBP">GBP (£) — British Pound</option>
                    <option value="CAD">CAD ($) — Canadian Dollar</option>
                    <option value="AUD">AUD ($) — Australian Dollar</option>
                    <option value="INR">INR (₹) — Indian Rupee</option>
                    <option value="JPY">JPY (¥) — Japanese Yen</option>
                </select>
                @error('currency')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="dateFormat" class="block text-xs font-bold text-text-primary mb-1">
                    Date Format *
                </label>
                <select
                    id="dateFormat"
                    wire:model="dateFormat"
                    class="select select-sm w-full text-xs @error('dateFormat') border-rose-500 @enderror"
                >
                    <option value="YYYY-MM-DD">YYYY-MM-DD (ISO 8601, Standard)</option>
                    <option value="DD/MM/YYYY">DD/MM/YYYY (UK / Europe / India)</option>
                    <option value="MM/DD/YYYY">MM/DD/YYYY (United States)</option>
                    <option value="DD.MM.YYYY">DD.MM.YYYY (German)</option>
                </select>
                @error('dateFormat')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Migrations
        </button>

        <button
            type="button"
            wire:click="saveAcademy"
            wire:loading.attr="disabled"
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer"
        >
            <span wire:loading.remove wire:target="saveAcademy">
                Save Academy &amp; Continue &rarr;
            </span>
            <span wire:loading wire:target="saveAcademy" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Saving Academy...
            </span>
        </button>
    </div>
</div>
