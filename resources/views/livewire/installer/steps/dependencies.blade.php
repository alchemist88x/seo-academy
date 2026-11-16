<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 3 — Application &amp; Composer Dependencies</h2>
        <p class="text-xs text-text-secondary mt-1">
            SEO Academy validates that all core framework, reactive component, PDF generation, and authorization dependencies are present.
        </p>
    </div>

    {{-- Overall Compatibility Badge --}}
    @if ($dependencies['compatible'] ?? true)
        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2 font-medium">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                All mandatory production packages and autoloader files are present and verified.
            </span>
            <span class="font-bold uppercase text-[10px] tracking-wider px-2 py-0.5 rounded bg-emerald-600 text-white">Verified</span>
        </div>
    @else
        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2 font-medium">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                One or more core dependencies are missing or incompatible.
            </span>
            <span class="font-bold uppercase text-[10px] tracking-wider px-2 py-0.5 rounded bg-rose-600 text-white">Missing</span>
        </div>
    @endif

    {{-- Autoloader Infrastructure --}}
    <div class="space-y-2">
        <h3 class="text-xs font-bold text-text-secondary uppercase tracking-wider">1. Composer Infrastructure</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-mono text-text-primary block font-bold">composer.json</span>
                    <span class="text-text-muted text-[10px]">Package Definition</span>
                </div>
                <span class="font-bold text-xs {{ ($dependencies['has_composer_json'] ?? true) ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ ($dependencies['has_composer_json'] ?? true) ? 'Present ✓' : 'Missing ✕' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-mono text-text-primary block font-bold">vendor/autoload.php</span>
                    <span class="text-text-muted text-[10px]">Runtime Autoloader</span>
                </div>
                <span class="font-bold text-xs {{ ($dependencies['has_autoload'] ?? true) ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ ($dependencies['has_autoload'] ?? true) ? 'Present ✓' : 'Missing ✕' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-mono text-text-primary block font-bold">installed.json</span>
                    <span class="text-text-muted text-[10px]">Installed Manifest</span>
                </div>
                <span class="font-bold text-xs {{ ($dependencies['has_installed_json'] ?? true) ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ ($dependencies['has_installed_json'] ?? true) ? 'Present ✓' : 'Missing ✕' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Core Production Packages --}}
    <div class="space-y-2">
        <h3 class="text-xs font-bold text-text-secondary uppercase tracking-wider">2. Core Production Packages</h3>
        <div class="overflow-x-auto rounded-xl border border-border-default bg-surface-elevated/20">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-border-default bg-surface-elevated/60 text-text-muted text-[11px]">
                        <th class="px-3.5 py-2.5 font-semibold">Core Package</th>
                        <th class="px-3.5 py-2.5 font-semibold">Required Constraint</th>
                        <th class="px-3.5 py-2.5 font-semibold">Installed Version</th>
                        <th class="px-3.5 py-2.5 font-semibold text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default text-text-secondary">
                    @foreach ($dependencies['packages'] ?? [] as $pkgName => $pkg)
                        <tr class="hover:bg-surface-elevated/40 transition-colors">
                            <td class="px-3.5 py-2.5 font-mono text-text-primary font-bold">{{ $pkgName }}</td>
                            <td class="px-3.5 py-2.5 font-mono text-[11px]">{{ $pkg['required'] ?? '—' }}</td>
                            <td class="px-3.5 py-2.5 font-mono text-[11px] text-text-primary">{{ $pkg['installed'] ?? 'Not Installed' }}</td>
                            <td class="px-3.5 py-2.5 text-right">
                                <span class="inline-flex items-center gap-1 font-bold text-[11px] {{ ($pkg['ok'] ?? false) ? 'text-emerald-500' : 'text-rose-500' }}">
                                    {{ ($pkg['ok'] ?? false) ? 'Verified ✓' : 'Missing ✕' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Deployment Information --}}
    <div class="p-4 rounded-xl bg-surface-elevated/30 border border-border-default text-xs text-text-secondary space-y-1">
        <span class="font-bold text-text-primary block">Zero-Terminal Shared Hosting Guarantee:</span>
        <p>
            When deploying SEO Academy via official commercial ZIP release packages, all production dependencies and autoloader maps are pre-compiled and bundled inside the archive. No terminal access, Composer installation, or Node.js runtime is required on your web server.
        </p>
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Requirements
        </button>

        <button
            type="button"
            wire:click="proceedFromDependencies"
            wire:loading.attr="disabled"
            @disabled(! ($dependencies['compatible'] ?? true))
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="proceedFromDependencies">Continue to Database Selection &rarr;</span>
            <span wire:loading wire:target="proceedFromDependencies" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Validating...
            </span>
        </button>
    </div>
</div>
