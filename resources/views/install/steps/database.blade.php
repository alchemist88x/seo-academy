@component('install.layout', [
    'title' => 'Step 4 — Database Selection & Configuration',
    'stepNumber' => 4,
    'totalSteps' => 9,
    'state' => $state,
])
    <div
        class="space-y-6"
        x-data="{
            driver: @js(old('driver', $currentDriver ?? 'sqlite')),
            host: @js(old('host', '127.0.0.1')),
            port: @js(old('port', '3306')),
            database: @js(old('database', '')),
            username: @js(old('username', '')),
            password: '',
            confirmSwitch: false,
            testing: false,
            testResult: null,

            selectDriver(d) {
                this.driver = d;
                this.testResult = null;
            },

            async testConnection() {
                this.testing = true;
                this.testResult = null;

                const payload = this.driver === 'sqlite'
                    ? { driver: 'sqlite' }
                    : {
                        driver: 'mysql',
                        host: this.host,
                        port: this.port,
                        database: this.database,
                        username: this.username,
                        password: this.password,
                    };

                try {
                    const response = await fetch('{{ route('install.test') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();
                    this.testResult = {
                        ok: Boolean(data.ok),
                        message: data.message || (response.ok ? 'Connection successful.' : 'Connection check failed.'),
                    };
                } catch (err) {
                    this.testResult = {
                        ok: false,
                        message: 'Unable to reach the installer test endpoint. Please check network connectivity and try again.',
                    };
                } finally {
                    this.testing = false;
                }
            }
        }"
    >
        <div>
            <h2 class="text-lg font-bold text-text-primary">Choose Your Database Engine</h2>
            <p class="text-xs text-text-secondary mt-1">
                SEO Academy supports high-performance self-contained SQLite 3.35+ and enterprise-scale MySQL 8.4+ InnoDB. Select your target engine below.
            </p>
        </div>

        {{-- Driver Switch Tabs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
                type="button"
                @click="selectDriver('sqlite')"
                class="p-4 rounded-xl border text-left transition-all"
                :class="driver === 'sqlite' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover'"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-sm text-text-primary flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        SQLite (Recommended)
                    </span>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-surface-elevated text-text-muted">Self-Contained</span>
                </div>
                <p class="text-xs text-text-secondary">
                    Zero configuration required. Ideal for shared hosting, VPS, single academies, and easy full-system backups. Stored securely under <code>storage/app/private/</code>.
                </p>
            </button>

            <button
                type="button"
                @click="selectDriver('mysql')"
                class="p-4 rounded-xl border text-left transition-all"
                :class="driver === 'mysql' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover'"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-sm text-text-primary flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        MySQL 8.4+ InnoDB
                    </span>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-surface-elevated text-text-muted">Dedicated DB</span>
                </div>
                <p class="text-xs text-text-secondary">
                    Recommended for academies anticipating high concurrent student loads, large cohorts, and managed remote database servers.
                </p>
            </button>
        </div>

        {{-- Form Container --}}
        <form method="POST" action="{{ route('install.step.database.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="driver" :value="driver">

            {{-- SQLite Details --}}
            <div x-show="driver === 'sqlite'" class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-3">
                <span class="text-xs font-bold text-text-primary block">SQLite Database Configuration</span>
                <div class="text-xs text-text-secondary space-y-1">
                    <p>
                        Database File Path: <code class="font-mono text-primary-600 dark:text-primary-400">storage/app/private/database.sqlite</code>
                    </p>
                    <p class="text-[11px] text-text-muted">
                        The installer automatically creates the database file with safe permissions. The database is stored outside the public document root.
                    </p>
                </div>
            </div>

            {{-- MySQL Fields --}}
            <div x-show="driver === 'mysql'" class="space-y-4 p-4 rounded-lg bg-surface-elevated/40 border border-border-default" style="display: none;">
                <span class="text-xs font-bold text-text-primary block">MySQL Connection Credentials</span>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-text-secondary mb-1">Server Host</label>
                        <input
                            type="text"
                            name="host"
                            x-model="host"
                            placeholder="127.0.0.1 or localhost"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Port</label>
                        <input
                            type="number"
                            name="port"
                            x-model="port"
                            placeholder="3306"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Database Name</label>
                        <input
                            type="text"
                            name="database"
                            x-model="database"
                            placeholder="seo_academy"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Username</label>
                        <input
                            type="text"
                            name="username"
                            x-model="username"
                            placeholder="academy_user"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Password</label>
                        <input
                            type="password"
                            name="password"
                            x-model="password"
                            placeholder="••••••••••••"
                            class="input input-sm w-full text-xs"
                            autocomplete="new-password"
                        >
                    </div>
                </div>
            </div>

            {{-- Connection Test Result Alert --}}
            <div x-show="testResult !== null" style="display: none;">
                <div
                    class="p-3 rounded-lg text-xs flex items-center justify-between"
                    :class="testResult && testResult.ok ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400'"
                >
                    <span x-text="testResult ? testResult.message : ''"></span>
                    <span class="font-bold uppercase text-[10px]" x-text="testResult && testResult.ok ? 'Verified' : 'Failed'"></span>
                </div>
            </div>

            {{-- Database Switch Confirmation (if needed) --}}
            @if (session('warning_switch'))
                <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2">
                    <input type="checkbox" name="confirm_switch" value="1" id="confirm_switch" class="rounded border-border-default text-primary-600">
                    <label for="confirm_switch" class="font-medium">
                        I confirm that I want to switch databases and understand existing records in the old database will not be migrated.
                    </label>
                </div>
            @endif

            {{-- Connection Testing Button & Submit --}}
            <div class="pt-4 border-t border-border-default flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <a href="{{ route('install.step.environment') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                        &larr; Back to Environment
                    </a>
                    <button
                        type="button"
                        @click="testConnection()"
                        :disabled="testing"
                        class="btn btn-outline btn-sm text-xs font-semibold"
                    >
                        <span x-show="!testing">Test Connection</span>
                        <span x-show="testing" style="display: none;">Verifying...</span>
                    </button>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm"
                >
                    Save &amp; Proceed to Migrations &rarr;
                </button>
            </div>
        </form>
    </div>
@endcomponent
