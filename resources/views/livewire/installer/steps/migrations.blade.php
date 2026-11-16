<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 7 — Database Initialization &amp; Schema Migrations</h2>
        <p class="text-xs text-text-secondary mt-1">
            Initialize all database tables and seed default application security roles into your configured {{ strtoupper($driver) }} database.
        </p>
    </div>

    {{-- Zero Data Loss Safety Notice --}}
    <div class="p-4 rounded-xl bg-primary-50/50 dark:bg-primary-950/30 border border-primary-200 dark:border-primary-800 text-xs text-text-secondary space-y-2">
        <span class="font-bold text-text-primary block flex items-center gap-1.5 text-primary-700 dark:text-primary-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Zero Data Loss Guarantee
        </span>
        <p>
            SEO Academy executes database migrations incrementally. It will <strong>never</strong> execute <code class="font-mono text-primary-600 dark:text-primary-400">migrate:fresh</code>, wipe existing databases, or drop customer records.
        </p>
    </div>

    {{-- Migration Checklist --}}
    <div class="space-y-2.5">
        <h3 class="text-xs font-bold text-text-secondary uppercase tracking-wider">Initialization Pipeline</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">Storage Directory Setup</span>
                    <span class="text-text-muted text-[11px]">Private file paths verified</span>
                </div>
                <span class="font-bold text-emerald-500">Ready ✓</span>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">Target Database Connection</span>
                    <span class="text-text-muted text-[11px]">{{ strtoupper($driver) }} engine active</span>
                </div>
                <span class="font-bold text-emerald-500">Connected ✓</span>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">Database Schema Migrations</span>
                    <span class="text-text-muted text-[11px]">86 application migrations</span>
                </div>
                <span class="font-bold {{ $migrationsCompleted ? 'text-emerald-500' : 'text-primary-600 dark:text-primary-400' }}">
                    {{ $migrationsCompleted ? 'Applied (' . $migrationCount . ') ✓' : 'Pending' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">Spatie Security Roles</span>
                    <span class="text-text-muted text-[11px]">admin, trainer, support, student</span>
                </div>
                <span class="font-bold {{ $migrationsCompleted ? 'text-emerald-500' : 'text-primary-600 dark:text-primary-400' }}">
                    {{ $migrationsCompleted ? 'Seeded ✓' : 'Pending' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Execution Trigger / Progress Status --}}
    @if ($migrationsCompleted)
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $migrationMessage ?: 'All database migrations and role definitions executed successfully.' }}</span>
            </span>
            <span class="font-bold uppercase text-[10px] tracking-wider px-2 py-0.5 rounded bg-emerald-600 text-white shrink-0">Success</span>
        </div>
    @else
        <div class="p-5 rounded-xl border border-border-default bg-surface-elevated/20 text-center space-y-3">
            <p class="text-xs text-text-secondary">
                Click the button below to execute database migrations and initialize core security roles.
            </p>
            <button
                type="button"
                wire:click="runMigrations"
                wire:loading.attr="disabled"
                class="btn btn-primary px-8 py-3 text-xs font-bold rounded-lg shadow-md inline-flex items-center gap-2 cursor-pointer"
            >
                <span wire:loading.remove wire:target="runMigrations">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Execute Database Migrations &amp; Seed Roles
                </span>
                <span wire:loading wire:target="runMigrations" class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Running Schema Migrations (Please wait)...
                </span>
            </button>
        </div>
    @endif

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Connection Test
        </button>

        <button
            type="button"
            wire:click="proceedFromMigrations"
            wire:loading.attr="disabled"
            @disabled(! $migrationsCompleted)
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
        >
            Continue to Academy Setup &rarr;
        </button>
    </div>
</div>
