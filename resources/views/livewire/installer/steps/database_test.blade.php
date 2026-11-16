<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 6 — Database Connection Verification</h2>
        <p class="text-xs text-text-secondary mt-1">
            Test and verify your database connection before proceeding with schema migrations and role initialization.
        </p>
    </div>

    {{-- Database Connection Summary Card --}}
    <div class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-border-default/60">
            <span class="text-xs font-bold text-text-primary uppercase tracking-wider">Target Connection Summary</span>
            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20">
                {{ strtoupper($driver) }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            @if ($driver === 'sqlite')
                <div class="sm:col-span-2">
                    <span class="text-text-muted text-[11px] block">Database File Location</span>
                    <span class="font-mono font-bold text-text-primary text-[11px] truncate block">{{ $sqlitePath }}</span>
                </div>
                <div>
                    <span class="text-text-muted text-[11px] block">Storage Isolation</span>
                    <span class="font-bold text-text-primary">Private Application Storage</span>
                </div>
                <div>
                    <span class="text-text-muted text-[11px] block">Journal Mode</span>
                    <span class="font-mono font-bold text-text-primary">WAL (Write-Ahead Logging)</span>
                </div>
            @else
                <div>
                    <span class="text-text-muted text-[11px] block">Server Host &amp; Port</span>
                    <span class="font-mono font-bold text-text-primary">{{ $mysqlHost }}:{{ $mysqlPort }}</span>
                </div>
                <div>
                    <span class="text-text-muted text-[11px] block">Database Name</span>
                    <span class="font-mono font-bold text-text-primary">{{ $mysqlDatabase }}</span>
                </div>
                <div>
                    <span class="text-text-muted text-[11px] block">Username</span>
                    <span class="font-mono font-bold text-text-primary">{{ $mysqlUsername }}</span>
                </div>
                <div>
                    <span class="text-text-muted text-[11px] block">Password</span>
                    <span class="font-mono text-text-primary">{{ $mysqlPassword ? '••••••••••••' : '(None)' }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Dedicated Test Connection Trigger & Result Box --}}
    <div class="p-5 rounded-xl border border-border-default bg-surface-elevated/20 text-center space-y-4">
        <div>
            <button
                type="button"
                wire:click="testConnection"
                wire:loading.attr="disabled"
                class="btn btn-outline px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm inline-flex items-center gap-2 cursor-pointer"
            >
                <span wire:loading.remove wire:target="testConnection">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Test Database Connection
                </span>
                <span wire:loading wire:target="testConnection" class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Connecting to Database...
                </span>
            </button>
        </div>

        {{-- Test Result Alert --}}
        @if ($testResult)
            <div class="p-4 rounded-xl border text-xs text-left flex items-start justify-between gap-3 transition-all
                {{ ($testResult['ok'] ?? false)
                    ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200'
                    : 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200' }}"
            >
                <div class="flex items-start gap-2.5">
                    @if ($testResult['ok'] ?? false)
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <span class="font-bold block text-sm mb-0.5">Connection Verified</span>
                            <span>{{ $testResult['message'] ?? 'Successfully connected to database.' }}</span>
                            @if (! empty($testResult['server_version']))
                                <span class="block mt-1 font-mono text-[11px] text-emerald-700 dark:text-emerald-300">Server Version: {{ $testResult['server_version'] }}</span>
                            @endif
                        </div>
                    @else
                        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <span class="font-bold block text-sm mb-0.5">Connection Failed</span>
                            <span>{{ $testResult['message'] ?? 'Unable to establish database connection.' }}</span>
                        </div>
                    @endif
                </div>

                <span class="font-bold uppercase text-[10px] tracking-wider px-2 py-0.5 rounded shrink-0 {{ ($testResult['ok'] ?? false) ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">
                    {{ ($testResult['ok'] ?? false) ? 'Verified' : 'Failed' }}
                </span>
            </div>
        @else
            <p class="text-xs text-text-muted">
                Click the button above to test the database connection before continuing to migrations.
            </p>
        @endif
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Configuration
        </button>

        <button
            type="button"
            wire:click="proceedFromConnectionTest"
            wire:loading.attr="disabled"
            @disabled(! $connectionVerified)
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="proceedFromConnectionTest">
                Proceed to Migrations &rarr;
            </span>
            <span wire:loading wire:target="proceedFromConnectionTest" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Verifying...
            </span>
        </button>
    </div>
</div>
