<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 9 — Initial Administrator Account</h2>
        <p class="text-xs text-text-secondary mt-1">
            Establish the master credentials for your academy super administrator. No default passwords are used.
        </p>
    </div>

    {{-- Form Fields --}}
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="adminName" class="block text-xs font-bold text-text-primary mb-1">
                    Administrator Full Name *
                </label>
                <input
                    id="adminName"
                    type="text"
                    wire:model="adminName"
                    class="input input-sm w-full text-xs @error('adminName') border-rose-500 @enderror"
                    placeholder="Jane Doe or Apex Administrator"
                />
                @error('adminName')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="adminEmail" class="block text-xs font-bold text-text-primary mb-1">
                    Administrator Email Address *
                </label>
                <input
                    id="adminEmail"
                    type="email"
                    wire:model="adminEmail"
                    class="input input-sm w-full text-xs @error('adminEmail') border-rose-500 @enderror"
                    placeholder="admin@youracademy.com"
                />
                @error('adminEmail')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="adminPassword" class="block text-xs font-bold text-text-primary mb-1">
                    Password * (Min 8 characters)
                </label>
                <div class="relative">
                    <input
                        id="adminPassword"
                        type="{{ $showAdminPassword ? 'text' : 'password' }}"
                        wire:model="adminPassword"
                        class="input input-sm w-full text-xs pr-10 @error('adminPassword') border-rose-500 @enderror"
                        placeholder="••••••••••••"
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        wire:click="togglePasswordVisibility('admin')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-muted hover:text-text-primary cursor-pointer text-xs"
                        title="{{ $showAdminPassword ? 'Hide password' : 'Show password' }}"
                    >
                        @if ($showAdminPassword)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        @endif
                    </button>
                </div>
                @error('adminPassword')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="adminPasswordConfirmation" class="block text-xs font-bold text-text-primary mb-1">
                    Confirm Password *
                </label>
                <input
                    id="adminPasswordConfirmation"
                    type="{{ $showAdminPassword ? 'text' : 'password' }}"
                    wire:model="adminPasswordConfirmation"
                    class="input input-sm w-full text-xs @error('adminPasswordConfirmation') border-rose-500 @enderror"
                    placeholder="••••••••••••"
                    autocomplete="new-password"
                />
                @error('adminPasswordConfirmation')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="p-3 rounded-lg bg-surface-elevated/40 border border-border-default text-[11px] text-text-muted flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>This account will be assigned the system <code class="font-mono text-text-primary">admin</code> role with full administrative privileges. Passwords are securely hashed with bcrypt.</span>
        </div>
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Academy Setup
        </button>

        <button
            type="button"
            wire:click="createAdministrator"
            wire:loading.attr="disabled"
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer"
        >
            <span wire:loading.remove wire:target="createAdministrator">
                Create Administrator &amp; Review &rarr;
            </span>
            <span wire:loading wire:target="createAdministrator" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Creating Admin...
            </span>
        </button>
    </div>
</div>
