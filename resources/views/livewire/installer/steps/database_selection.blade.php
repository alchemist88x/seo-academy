<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 4 — Database Selection</h2>
        <p class="text-xs text-text-secondary mt-1">
            Choose the database architecture for your SEO Academy installation. You can configure credentials in the next step.
        </p>
    </div>

    {{-- Validation Error Banner --}}
    @error('driver')
        <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $message }}</span>
        </div>
    @enderror

    {{-- Engine Selection Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2" role="radiogroup" aria-label="Database Engine Selection">
        {{-- SQLite Card --}}
        <label
            for="engine-sqlite"
            class="p-5 rounded-2xl border-2 text-left transition-all cursor-pointer relative group flex flex-col justify-between
                {{ $driver === 'sqlite'
                    ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 ring-2 ring-primary-500/20 shadow-md'
                    : 'border-border-default bg-surface-elevated/40 hover:border-border-hover hover:bg-surface-elevated/70' }}"
        >
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-base text-text-primary flex items-center gap-2.5">
                        <input
                            id="engine-sqlite"
                            type="radio"
                            name="driver"
                            value="sqlite"
                            wire:model.live="driver"
                            class="w-4 h-4 text-primary-600 focus:ring-primary-500 border-border-default cursor-pointer"
                        />
                        <svg class="w-6 h-6 {{ $driver === 'sqlite' ? 'text-primary-600 dark:text-primary-400' : 'text-text-muted group-hover:text-primary-500' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        SQLite 3
                    </span>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full {{ $driver === 'sqlite' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                        Recommended
                    </span>
                </div>
                <p class="text-xs text-text-secondary leading-relaxed pl-6">
                    Zero configuration required. All data is saved inside an optimized, single-file database stored securely in private storage outside the web document root (<code class="font-mono text-[11px] text-primary-600 dark:text-primary-400">storage/app/private/</code>).
                </p>
            </div>

            <div class="mt-4 pt-3 border-t border-border-default/60 flex items-center justify-between text-xs">
                <span class="text-text-muted text-[11px]">Best for: Shared Hosting &bull; VPS &bull; Single Academy</span>
                @if ($driver === 'sqlite')
                    <span class="flex items-center gap-1 font-bold text-primary-600 dark:text-primary-400 text-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Selected
                    </span>
                @else
                    <button type="button" wire:click="selectDriver('sqlite')" class="text-text-muted text-xs group-hover:text-text-primary underline">Select SQLite</button>
                @endif
            </div>
        </label>

        {{-- MySQL Card --}}
        <label
            for="engine-mysql"
            class="p-5 rounded-2xl border-2 text-left transition-all cursor-pointer relative group flex flex-col justify-between
                {{ $driver === 'mysql'
                    ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 ring-2 ring-primary-500/20 shadow-md'
                    : 'border-border-default bg-surface-elevated/40 hover:border-border-hover hover:bg-surface-elevated/70' }}"
        >
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-base text-text-primary flex items-center gap-2.5">
                        <input
                            id="engine-mysql"
                            type="radio"
                            name="driver"
                            value="mysql"
                            wire:model.live="driver"
                            class="w-4 h-4 text-primary-600 focus:ring-primary-500 border-border-default cursor-pointer"
                        />
                        <svg class="w-6 h-6 {{ $driver === 'mysql' ? 'text-primary-600 dark:text-primary-400' : 'text-text-muted group-hover:text-primary-500' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        MySQL 8.4+ InnoDB
                    </span>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full {{ $driver === 'mysql' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                        Dedicated Server
                    </span>
                </div>
                <p class="text-xs text-text-secondary leading-relaxed pl-6">
                    Connects to an external or local MySQL 8.4+ / MariaDB 10.11+ server with InnoDB engine and utf8mb4 encoding. Recommended for growing academies with large student bodies and heavy concurrent loads.
                </p>
            </div>

            <div class="mt-4 pt-3 border-t border-border-default/60 flex items-center justify-between text-xs">
                <span class="text-text-muted text-[11px]">Best for: Dedicated DB Servers &bull; High Concurrency</span>
                @if ($driver === 'mysql')
                    <span class="flex items-center gap-1 font-bold text-primary-600 dark:text-primary-400 text-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Selected
                    </span>
                @else
                    <button type="button" wire:click="selectDriver('mysql')" class="text-text-muted text-xs group-hover:text-text-primary underline">Select MySQL</button>
                @endif
            </div>
        </label>
    </div>

    {{-- Selection Confirmation Notice --}}
    <div class="p-3.5 rounded-xl bg-surface-elevated/30 border border-border-default text-xs text-text-secondary flex items-center justify-between">
        <span>Active Choice: <strong class="text-text-primary font-mono uppercase">{{ $driver }}</strong></span>
        <span class="text-[11px] text-text-muted">You can change this engine anytime before finalization.</span>
    </div>

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Back to Dependencies
        </button>

        <button
            type="button"
            wire:click="proceedFromDatabaseSelection"
            wire:loading.attr="disabled"
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer"
        >
            <span wire:loading.remove wire:target="proceedFromDatabaseSelection">
                Configure {{ $driver === 'sqlite' ? 'SQLite' : 'MySQL' }} &rarr;
            </span>
            <span wire:loading wire:target="proceedFromDatabaseSelection" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Saving...
            </span>
        </button>
    </div>
</div>

        <button
            type="button"
            wire:click="proceedFromDatabaseSelection"
            wire:loading.attr="disabled"
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer"
        >
            <span wire:loading.remove wire:target="proceedFromDatabaseSelection">
                Configure {{ $driver === 'sqlite' ? 'SQLite' : 'MySQL' }} &rarr;
            </span>
            <span wire:loading wire:target="proceedFromDatabaseSelection" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Saving...
            </span>
        </button>
    </div>
</div>
