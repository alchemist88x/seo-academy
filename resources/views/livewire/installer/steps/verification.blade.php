<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 10 — Final System Verification</h2>
        <p class="text-xs text-text-secondary mt-1">
            Review the final pre-flight verification audit. Finalizing will generate the atomic security lockfile (<code class="font-mono text-primary-600 dark:text-primary-400">installed.lock</code>) and seal the installer permanently.
        </p>
    </div>

    {{-- Audit Checklist Items --}}
    <div class="space-y-2.5">
        @foreach ($verificationItems as $item)
            <div class="p-4 rounded-xl border flex items-center justify-between transition-all {{ ($item['ok'] ?? true) ? 'bg-surface-elevated/40 border-border-default' : 'bg-rose-50/50 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800' }}">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5 {{ ($item['ok'] ?? true) ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500' }}">
                        @if ($item['ok'] ?? true)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        @endif
                    </div>
                    <div>
                        <span class="font-bold text-xs text-text-primary block">{{ $item['title'] }}</span>
                        <span class="text-text-secondary text-[11px] block mt-0.5">{{ $item['description'] }}</span>
                    </div>
                </div>

                <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded {{ ($item['ok'] ?? true) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border border-rose-500/20' }}">
                    {{ $item['status'] }}
                </span>
            </div>
        @endforeach
    </div>

    {{-- Lockdown Confirmation Notice --}}
    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-200 text-xs flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <div>
            <span class="font-bold block text-sm mb-0.5">Permanent Security Lock</span>
            <p>
                Clicking the button below writes the atomic lockfile (<code class="font-mono">storage/installed.lock</code>) and updates the academy profile. Once finalized, all installer routes are permanently disabled, and future requests will automatically redirect to the sign-in screen.
            </p>
        </div>
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Administrator
        </button>

        <button
            type="button"
            wire:click="finalizeInstallation"
            wire:loading.attr="disabled"
            class="btn btn-primary px-8 py-3 text-xs font-bold rounded-lg shadow-md flex items-center gap-2 cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white border-none"
        >
            <span wire:loading.remove wire:target="finalizeInstallation" class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Finalize Installation &amp; Lock System &rarr;
            </span>
            <span wire:loading wire:target="finalizeInstallation" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Writing Lockfile &amp; Finalizing...
            </span>
        </button>
    </div>
</div>
