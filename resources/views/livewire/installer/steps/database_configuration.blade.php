<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 5 — {{ $driver === 'sqlite' ? 'SQLite Database Configuration' : 'MySQL Connection Credentials' }}</h2>
        <p class="text-xs text-text-secondary mt-1">
            {{ $driver === 'sqlite' ? 'Verify your local SQLite database storage path and directory permissions.' : 'Enter the connection parameters for your dedicated MySQL 8.4+ / MariaDB database.' }}
        </p>
    </div>

    @if ($driver === 'sqlite')
        {{-- SQLite Configuration Form --}}
        <div class="space-y-4">
            <div class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default space-y-3">
                <div>
                    <label for="sqlitePath" class="block text-xs font-bold text-text-primary mb-1">
                        SQLite Database File Location *
                    </label>
                    <input
                        id="sqlitePath"
                        type="text"
                        wire:model="sqlitePath"
                        class="input input-sm w-full font-mono text-xs @error('sqlitePath') border-rose-500 @enderror"
                        placeholder="storage/app/private/database.sqlite"
                    />
                    @error('sqlitePath')
                        <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="text-[11px] text-text-secondary space-y-1 pt-1">
                    <p class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Default path: <code class="font-mono text-primary-600 dark:text-primary-400">storage/app/private/database.sqlite</code>
                    </p>
                    <p class="text-text-muted">
                        Stored in private application storage outside the web document root (<code class="font-mono">public/</code>) to prevent direct download via HTTP.
                    </p>
                </div>
            </div>
        </div>
    @else
        {{-- MySQL Configuration Form --}}
        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label for="mysqlHost" class="block text-xs font-bold text-text-primary mb-1">Database Server Host *</label>
                    <input
                        id="mysqlHost"
                        type="text"
                        wire:model="mysqlHost"
                        class="input input-sm w-full text-xs @error('mysqlHost') border-rose-500 @enderror"
                        placeholder="127.0.0.1 or localhost"
                    />
                    @error('mysqlHost')
                        <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="mysqlPort" class="block text-xs font-bold text-text-primary mb-1">Port *</label>
                    <input
                        id="mysqlPort"
                        type="number"
                        wire:model="mysqlPort"
                        class="input input-sm w-full text-xs @error('mysqlPort') border-rose-500 @enderror"
                        placeholder="3306"
                    />
                    @error('mysqlPort')
                        <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div>
                <label for="mysqlDatabase" class="block text-xs font-bold text-text-primary mb-1">Database Name *</label>
                <input
                    id="mysqlDatabase"
                    type="text"
                    wire:model="mysqlDatabase"
                    class="input input-sm w-full text-xs font-mono @error('mysqlDatabase') border-rose-500 @enderror"
                    placeholder="seo_academy"
                />
                @error('mysqlDatabase')
                    <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="mysqlUsername" class="block text-xs font-bold text-text-primary mb-1">Database Username *</label>
                    <input
                        id="mysqlUsername"
                        type="text"
                        wire:model="mysqlUsername"
                        class="input input-sm w-full text-xs @error('mysqlUsername') border-rose-500 @enderror"
                        placeholder="db_user or root"
                        autocomplete="off"
                    />
                    @error('mysqlUsername')
                        <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="mysqlPassword" class="block text-xs font-bold text-text-primary mb-1">Database Password</label>
                    <div class="relative">
                        <input
                            id="mysqlPassword"
                            type="{{ $showMysqlPassword ? 'text' : 'password' }}"
                            wire:model="mysqlPassword"
                            class="input input-sm w-full text-xs pr-10 @error('mysqlPassword') border-rose-500 @enderror"
                            placeholder="••••••••••••"
                            autocomplete="new-password"
                        />
                        <button
                            type="button"
                            wire:click="togglePasswordVisibility('mysql')"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-muted hover:text-text-primary cursor-pointer text-xs"
                            title="{{ $showMysqlPassword ? 'Hide password' : 'Show password' }}"
                        >
                            @if ($showMysqlPassword)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            @endif
                        </button>
                    </div>
                    @error('mysqlPassword')
                        <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="p-3 rounded-lg bg-surface-elevated/40 border border-border-default text-[11px] text-text-muted">
                Requires MySQL 8.4+ or MariaDB 10.11+ with <code class="font-mono text-text-secondary">InnoDB</code> engine and <code class="font-mono text-text-secondary">utf8mb4</code> collation.
            </div>
        </div>
    @endif

    {{-- Navigation Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-between">
        <button
            type="button"
            wire:click="previousStep"
            class="text-xs text-text-muted hover:text-text-primary underline cursor-pointer"
        >
            &larr; Change Database Engine
        </button>

        <button
            type="button"
            wire:click="saveDatabaseConfiguration"
            wire:loading.attr="disabled"
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer"
        >
            <span wire:loading.remove wire:target="saveDatabaseConfiguration">
                Save &amp; Proceed to Connection Test &rarr;
            </span>
            <span wire:loading wire:target="saveDatabaseConfiguration" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Saving...
            </span>
        </button>
    </div>
</div>
