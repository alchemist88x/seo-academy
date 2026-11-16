<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 2 — Server &amp; PHP Requirements</h2>
        <p class="text-xs text-text-secondary mt-1">
            SEO Academy inspects your server runtime to ensure all mandatory PHP extensions, memory limits, and storage paths satisfy requirements.
        </p>
    </div>

    {{-- Overall Compatibility Badge --}}
    @if ($requirements['compatible'] ?? false)
        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2 font-medium">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Your server satisfies all mandatory execution prerequisites.
            </span>
            <span class="font-bold uppercase text-[10px] tracking-wider px-2 py-0.5 rounded bg-emerald-600 text-white">Environment Ready</span>
        </div>
    @else
        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2 font-medium">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                One or more mandatory prerequisites failed. Please resolve them before continuing.
            </span>
            <span class="font-bold uppercase text-[10px] tracking-wider px-2 py-0.5 rounded bg-rose-600 text-white">Action Required</span>
        </div>
    @endif

    {{-- 1. PHP Runtime & Limits --}}
    <div class="space-y-2">
        <h3 class="text-xs font-bold text-text-secondary uppercase tracking-wider">1. PHP Runtime &amp; Server Limits</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex flex-col justify-between">
                <span class="text-text-muted text-[11px]">PHP Version (&ge; 8.3)</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="font-mono font-bold text-text-primary">{{ $requirements['php']['current'] ?? PHP_VERSION }}</span>
                    <span class="font-bold text-[11px] {{ ($requirements['php']['ok'] ?? true) ? 'text-emerald-500' : 'text-rose-500' }}">
                        {{ ($requirements['php']['ok'] ?? true) ? 'Passed ✓' : 'Failed ✕' }}
                    </span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex flex-col justify-between">
                <span class="text-text-muted text-[11px]">Memory Limit (&ge; 128M)</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="font-mono font-bold text-text-primary">{{ $requirements['memory_limit']['current'] ?? ini_get('memory_limit') }}</span>
                    <span class="font-bold text-[11px] {{ ($requirements['memory_limit']['ok'] ?? true) ? 'text-emerald-500' : 'text-amber-500' }}">
                        {{ ($requirements['memory_limit']['ok'] ?? true) ? 'Passed ✓' : 'Warning !' }}
                    </span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex flex-col justify-between">
                <span class="text-text-muted text-[11px]">Max Execution Time</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="font-mono font-bold text-text-primary">{{ ($requirements['max_execution_time']['current'] ?? ini_get('max_execution_time')) }}s</span>
                    <span class="font-bold text-[11px] {{ ($requirements['max_execution_time']['ok'] ?? true) ? 'text-emerald-500' : 'text-amber-500' }}">
                        {{ ($requirements['max_execution_time']['ok'] ?? true) ? 'Passed ✓' : 'Warning !' }}
                    </span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex flex-col justify-between">
                <span class="text-text-muted text-[11px]">Upload Max Filesize</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="font-mono font-bold text-text-primary">{{ ini_get('upload_max_filesize') }}</span>
                    <span class="font-bold text-[11px] text-text-muted">Info</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Core PHP Extensions --}}
    <div class="space-y-2">
        <h3 class="text-xs font-bold text-text-secondary uppercase tracking-wider">2. Core &amp; Database PHP Extensions</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
            @foreach ($requirements['extensions'] ?? [] as $ext)
                <div class="p-2.5 rounded-lg border flex items-center justify-between {{ ($ext['ok'] ?? false) ? 'bg-surface-elevated/40 border-border-default' : 'bg-rose-50/50 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800' }}">
                    <span class="font-mono text-[11px] text-text-primary">{{ $ext['name'] }}</span>
                    <span class="text-[11px] font-bold {{ ($ext['ok'] ?? false) ? 'text-emerald-500' : 'text-rose-500' }}">
                        {{ ($ext['ok'] ?? false) ? 'Passed ✓' : 'Failed ✕' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 3. Filesystem Permissions --}}
    <div class="space-y-2">
        <h3 class="text-xs font-bold text-text-secondary uppercase tracking-wider">3. Writable Storage &amp; Cache Paths</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            @foreach ($requirements['directories'] ?? [] as $label => $dir)
                <div class="p-2.5 rounded-lg border flex items-center justify-between {{ ($dir['ok'] ?? false) ? 'bg-surface-elevated/40 border-border-default' : 'bg-rose-50/50 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800' }}">
                    <span class="font-mono text-[11px] text-text-primary truncate max-w-[220px]" title="{{ $dir['path'] ?? '' }}">{{ $label }}</span>
                    <span class="text-[11px] font-bold {{ ($dir['ok'] ?? false) ? 'text-emerald-500' : 'text-rose-500' }}">
                        {{ ($dir['ok'] ?? false) ? 'Writable ✓' : 'Unwritable ✕' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Welcome
        </button>

        <button
            type="button"
            wire:click="proceedFromRequirements"
            wire:loading.attr="disabled"
            @disabled(! ($requirements['compatible'] ?? false))
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="proceedFromRequirements">Continue to Dependencies &rarr;</span>
            <span wire:loading wire:target="proceedFromRequirements" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Verifying...
            </span>
        </button>
    </div>
</div>
