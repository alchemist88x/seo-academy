<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 5 — {{ $driver === 'sqlite' ? 'SQLite Database Configuration' : 'Database Hosting & Connection Credentials' }}</h2>
        <p class="text-xs text-text-secondary mt-1">
            {{ $driver === 'sqlite'
                ? 'Verify your local SQLite database storage path. Zero external database server configuration required.'
                : 'Select your database hosting environment (Hostinger CloudPanel, Ubuntu VPS, Amazon RDS, or Remote Server) and enter credentials.' }}
        </p>
    </div>

    {{-- Driver Validation Banner --}}
    @error('driver')
        <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $message }}</span>
        </div>
    @enderror

    {{-- Seamless Engine Switcher Bar --}}
    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-elevated/30 border border-border-default">
        <div class="flex items-center gap-2">
            <span class="text-xs text-text-muted">Target Engine:</span>
            <span class="text-xs font-bold text-text-primary uppercase font-mono">{{ $driver === 'sqlite' ? 'SQLite 3' : 'MySQL / MariaDB' }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                wire:click="selectDriver('sqlite')"
                class="px-3 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $driver === 'sqlite' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-elevated text-text-muted hover:text-text-primary border border-border-default' }}"
            >
                SQLite 3
            </button>
            <button
                type="button"
                wire:click="selectDriver('mysql')"
                class="px-3 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $driver === 'mysql' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-elevated text-text-muted hover:text-text-primary border border-border-default' }}"
            >
                MySQL / MariaDB
            </button>
        </div>
    </div>

    @if ($driver === 'sqlite')
        {{-- SQLite Configuration Form --}}
        <div class="space-y-4">
            <div class="p-5 rounded-2xl bg-surface-elevated/40 border border-border-default space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-default/60">
                    <span class="text-xs font-bold text-text-primary flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        SQLite Automated File Storage
                    </span>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        Zero Configuration Required
                    </span>
                </div>

                <div>
                    <label for="sqlitePath" class="block text-xs font-bold text-text-primary mb-1">
                        SQLite Database File Location *
                    </label>
                    <input
                        id="sqlitePath"
                        type="text"
                        wire:model.live="sqlitePath"
                        class="input input-sm w-full font-mono text-xs @error('sqlitePath') border-rose-500 @enderror"
                        placeholder="storage/app/private/database.sqlite"
                    />
                    @error('sqlitePath')
                        <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="text-[11px] text-text-secondary space-y-1.5 pt-1">
                    <p class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Default path: <code class="font-mono text-primary-600 dark:text-primary-400">storage/app/private/database.sqlite</code>
                    </p>
                    <p class="text-text-muted">
                        No host, port, username, or password is required. Stored securely in private application storage outside the web document root (<code class="font-mono">public/</code>) to prevent direct HTTP access.
                    </p>
                </div>
            </div>
        </div>
    @else
        {{-- MySQL Hosting & Credentials --}}
        <div class="space-y-6">
            {{-- Hosting Type Selection Cards --}}
            <div>
                <label class="block text-xs font-bold text-text-primary mb-2">
                    Database Hosting Type *
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Database Hosting Type">
                    {{-- Local MySQL Card --}}
                    <button
                        type="button"
                        wire:click="selectHostingType('local')"
                        class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between
                            {{ $mysqlHostingType === 'local'
                                ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 ring-2 ring-primary-500/20 shadow-xs'
                                : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-xs text-text-primary flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                    Local MySQL / MariaDB
                                </span>
                                <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded {{ $mysqlHostingType === 'local' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                                    CloudPanel / VPS
                                </span>
                            </div>
                            <p class="text-[11px] text-text-secondary leading-normal">
                                For Hostinger KVM VPS, CloudPanel, Ubuntu VPS, or MySQL running on the same server (<code class="font-mono text-primary-600 dark:text-primary-400">127.0.0.1:3306</code>).
                            </p>
                        </div>
                    </button>

                    {{-- Amazon RDS Card --}}
                    <button
                        type="button"
                        wire:click="selectHostingType('rds')"
                        class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between
                            {{ $mysqlHostingType === 'rds'
                                ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 ring-2 ring-primary-500/20 shadow-xs'
                                : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-xs text-text-primary flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                                    Amazon RDS
                                </span>
                                <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded {{ $mysqlHostingType === 'rds' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                                    AWS Cloud
                                </span>
                            </div>
                            <p class="text-[11px] text-text-secondary leading-normal">
                                For AWS RDS MySQL, Aurora MySQL, or managed cloud database clusters with custom endpoint URLs.
                            </p>
                        </div>
                    </button>

                    {{-- Remote MySQL Server Card --}}
                    <button
                        type="button"
                        wire:click="selectHostingType('remote')"
                        class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between
                            {{ $mysqlHostingType === 'remote'
                                ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 ring-2 ring-primary-500/20 shadow-xs'
                                : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-xs text-text-primary flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                    Remote MySQL Server
                                </span>
                                <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded {{ $mysqlHostingType === 'remote' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                                    External DB
                                </span>
                            </div>
                            <p class="text-[11px] text-text-secondary leading-normal">
                                For external database servers, dedicated database VPS, or managed MySQL hosting providers.
                            </p>
                        </div>
                    </button>

                    {{-- Custom Configuration Card --}}
                    <button
                        type="button"
                        wire:click="selectHostingType('custom')"
                        class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between
                            {{ $mysqlHostingType === 'custom'
                                ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 ring-2 ring-primary-500/20 shadow-xs'
                                : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-xs text-text-primary flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Custom Configuration
                                </span>
                                <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded {{ $mysqlHostingType === 'custom' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default' }}">
                                    Advanced
                                </span>
                            </div>
                            <p class="text-[11px] text-text-secondary leading-normal">
                                Manually configure all database connection parameters, custom port, or bespoke network topology.
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            {{-- CloudPanel Specific Assistance Guide (shown when Local is selected) --}}
            @if ($mysqlHostingType === 'local')
                <div class="p-4 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 text-xs text-text-secondary space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-sm text-blue-900 dark:text-blue-200 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            CloudPanel &amp; Local VPS Setup Guide
                        </span>
                        <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            Hostinger KVM &bull; CloudPanel
                        </span>
                    </div>

                    <ol class="list-decimal list-inside space-y-1.5 text-xs text-text-secondary pl-1">
                        <li>Log in to your <strong>CloudPanel</strong> dashboard.</li>
                        <li>Navigate to your site's <strong>Databases</strong> section.</li>
                        <li>Click <strong>Add Database</strong> (e.g. database name: <code class="font-mono text-primary-600 dark:text-primary-400">seo_academy</code>).</li>
                        <li>Create a <strong>Database User</strong> and generate a secure password.</li>
                        <li>Assign the database user appropriate full privileges to the created database.</li>
                        <li>Copy the database name, username, and password into the credentials form below.</li>
                    </ol>

                    <div class="pt-2 border-t border-blue-200/60 dark:border-blue-800/60 flex items-center justify-between text-[11px] text-text-muted">
                        <span>Recommended local connection: <code class="font-mono text-primary-600 dark:text-primary-400">DB_HOST=127.0.0.1</code> &bull; <code class="font-mono text-primary-600 dark:text-primary-400">DB_PORT=3306</code></span>
                        <span>Requires MySQL 8.4+ or MariaDB 10.11+</span>
                    </div>
                </div>
            @endif

            {{-- Database Credentials Form --}}
            <div class="p-5 rounded-2xl bg-surface-elevated/40 border border-border-default space-y-4">
                <div class="pb-2 border-b border-border-default/60">
                    <span class="text-xs font-bold text-text-primary uppercase tracking-wider">Database Connection Parameters</span>
                    <p class="text-[11px] text-text-muted mt-0.5">Enter your database server credentials. Passwords are encrypted and never exposed in diagnostic logs.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="mysqlHost" class="block text-xs font-bold text-text-primary mb-1">Database Server Host *</label>
                        <input
                            id="mysqlHost"
                            type="text"
                            wire:model.live="mysqlHost"
                            class="input input-sm w-full text-xs font-mono @error('mysqlHost') border-rose-500 @enderror"
                            placeholder="{{ $mysqlHostingType === 'rds' ? 'your-rds-endpoint.xxxxxx.us-east-1.rds.amazonaws.com' : ($mysqlHostingType === 'remote' ? 'db.yourdomain.com' : '127.0.0.1') }}"
                        />
                        @error('mysqlHost')
                            <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                        <span class="text-[10px] text-text-muted mt-1 block">
                            {{ $mysqlHostingType === 'local' ? 'Local host loopback (127.0.0.1) for CloudPanel and same-server setups.' : 'Host IP address or fully qualified domain name.' }}
                        </span>
                    </div>

                    <div>
                        <label for="mysqlPort" class="block text-xs font-bold text-text-primary mb-1">Database Port *</label>
                        <input
                            id="mysqlPort"
                            type="number"
                            wire:model.live="mysqlPort"
                            class="input input-sm w-full text-xs font-mono @error('mysqlPort') border-rose-500 @enderror"
                            placeholder="3306"
                        />
                        @error('mysqlPort')
                            <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                        <span class="text-[10px] text-text-muted mt-1 block">Default standard port is 3306.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="mysqlDatabase" class="block text-xs font-bold text-text-primary mb-1">Database Name *</label>
                        <input
                            id="mysqlDatabase"
                            type="text"
                            wire:model.live="mysqlDatabase"
                            class="input input-sm w-full text-xs font-mono @error('mysqlDatabase') border-rose-500 @enderror"
                            placeholder="seo_academy"
                        />
                        @error('mysqlDatabase')
                            <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                        <span class="text-[10px] text-text-muted mt-1 block">Target database created in your control panel.</span>
                    </div>

                    <div>
                        <label for="mysqlUsername" class="block text-xs font-bold text-text-primary mb-1">Database Username *</label>
                        <input
                            id="mysqlUsername"
                            type="text"
                            wire:model.live="mysqlUsername"
                            class="input input-sm w-full text-xs font-mono @error('mysqlUsername') border-rose-500 @enderror"
                            placeholder="seo_academy_user"
                            autocomplete="off"
                        />
                        @error('mysqlUsername')
                            <span class="text-rose-500 text-[11px] mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                        <span class="text-[10px] text-text-muted mt-1 block">User account with DDL &amp; DML privileges.</span>
                    </div>
                </div>

                <div>
                    <label for="mysqlPassword" class="block text-xs font-bold text-text-primary mb-1">Database Password</label>
                    <div class="relative">
                        <input
                            id="mysqlPassword"
                            type="{{ $showMysqlPassword ? 'text' : 'password' }}"
                            wire:model.live="mysqlPassword"
                            class="input input-sm w-full text-xs pr-10 font-mono @error('mysqlPassword') border-rose-500 @enderror"
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
                    <span class="text-[10px] text-text-muted mt-1 block">Leave empty only if MySQL user authentication has no password.</span>
                </div>

                <div class="p-3 rounded-lg bg-surface-elevated/40 border border-border-default text-[11px] text-text-muted flex items-center justify-between">
                    <span>Engine Requirement: <code class="font-mono text-text-secondary">MySQL 8.4+</code> or <code class="font-mono text-text-secondary">MariaDB 10.11+</code></span>
                    <span>Storage Engine: <code class="font-mono text-text-secondary font-bold">InnoDB (utf8mb4)</code></span>
                </div>
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
