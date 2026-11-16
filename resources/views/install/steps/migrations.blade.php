@component('install.layout', [
    'title' => 'Step 5 — Database Initialization & Migrations',
    'stepNumber' => 5,
    'totalSteps' => 9,
    'state' => $state,
])
    <div
        class="space-y-6"
        x-data="{
            running: false,
            startMigration() {
                this.running = true;
            }
        }"
    >
        <div>
            <h2 class="text-lg font-bold text-text-primary">Schema Initialization &amp; Security Roles</h2>
            <p class="text-xs text-text-secondary mt-1">
                Execute the verified migration catalog to construct database tables, establish foreign key constraints, and install Spatie RBAC role definitions.
            </p>
        </div>

        {{-- Migration Summary Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">Target Engine</span>
                <span class="font-bold text-sm font-mono uppercase text-primary-500">
                    {{ $state['driver'] ?? config('database.default', 'sqlite') }}
                </span>
                <p class="text-text-secondary text-[11px]">Active connection driver.</p>
            </div>

            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">Migration Catalog</span>
                <span class="font-bold text-sm text-text-primary font-mono">
                    {{ count($knownMigrations) }} Migrations
                </span>
                <p class="text-text-secondary text-[11px]">Strictly additive. Zero destructive drops.</p>
            </div>

            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">Security Roles</span>
                <span class="font-bold text-sm text-emerald-500 font-mono">
                    4 Standard Roles
                </span>
                <p class="text-text-secondary text-[11px]">admin, trainer, support_staff, student.</p>
            </div>
        </div>

        {{-- Safe Migration Notice --}}
        <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default text-xs text-text-secondary space-y-2">
            <strong class="text-text-primary block">Strict Data Safety Architecture:</strong>
            <p>
                SEO Academy never executes <code>migrate:fresh</code> or truncates existing data. Incomplete or interrupted installations are identified by persistent installation markers, allowing schema execution to resume idempotently.
            </p>
        </div>

        {{-- Execution Progress Indicator --}}
        <div x-show="running" style="display: none;" class="p-4 rounded-lg bg-primary-500/10 border border-primary-500/30 text-primary-400 text-xs">
            <div class="flex items-center gap-3">
                <svg class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div>
                    <span class="font-bold text-sm block text-primary-300">Executing Migrations...</span>
                    <span class="text-[11px] text-primary-400/80">Applying table schemas and security seeds. This usually takes 5 to 15 seconds. Please do not close this window.</span>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <form method="POST" action="{{ route('install.step.migrations.store') }}" @submit="startMigration()" class="pt-4 border-t border-border-default flex items-center justify-between">
            @csrf
            <a href="{{ route('install.step.database') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                &larr; Back to Database Selection
            </a>

            <button
                type="submit"
                :disabled="running"
                class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm"
            >
                <span x-show="!running">Execute Migrations &amp; Seed Roles &rarr;</span>
                <span x-show="running" style="display: none;">Migrating Database...</span>
            </button>
        </form>
    </div>
@endcomponent
