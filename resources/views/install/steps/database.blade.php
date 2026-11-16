@component('install.layout', [
    'title' => 'Step 4 — Database Selection & Configuration',
    'stepNumber' => 4,
    'totalSteps' => 9,
    'state' => $state,
])
    @php
        $selectedDriver = old('driver', $currentDriver ?? 'sqlite');
        $isMysql = ($selectedDriver === 'mysql');
        $dbHost = old('host', $state['db_config']['host'] ?? '127.0.0.1');
        $dbPort = old('port', $state['db_config']['port'] ?? '3306');
        $dbName = old('database', $state['db_config']['database'] ?? 'seo_academy');
        $dbUser = old('username', $state['db_config']['username'] ?? 'seo_academy_user');
    @endphp

    <div
        id="db-wizard-container"
        class="space-y-6"
        x-data="{
            driver: @js($selectedDriver),
            hostingType: 'local',
            host: @js($dbHost),
            port: @js($dbPort),
            database: @js($dbName),
            username: @js($dbUser),
            password: '',
            confirmSwitch: false,
            testing: false,
            testResult: null,

            selectDriver(d) {
                this.driver = d;
                this.testResult = null;
                if (typeof window.syncDatabaseDriver === 'function') {
                    window.syncDatabaseDriver(d);
                }
            },

            selectHostingType(t) {
                this.hostingType = t;
                this.testResult = null;
                if (typeof window.syncHostingType === 'function') {
                    window.syncHostingType(t);
                }
            },

            async testConnection() {
                this.testing = true;
                this.testResult = null;

                const currentDriver = document.querySelector('input[name=\'driver\']:checked')?.value || this.driver;
                const hostVal = document.getElementById('db_host')?.value || this.host;
                const portVal = document.getElementById('db_port')?.value || this.port;
                const dbVal = document.getElementById('db_database')?.value || this.database;
                const userVal = document.getElementById('db_username')?.value || this.username;
                const passVal = document.getElementById('db_password')?.value || this.password;

                const payload = currentDriver === 'sqlite'
                    ? { driver: 'sqlite' }
                    : {
                        driver: 'mysql',
                        host: hostVal,
                        port: portVal,
                        database: dbVal,
                        username: userVal,
                        password: passVal,
                    };

                try {
                    const response = await fetch('{{ route('install.test') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
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
                    const testResultEl = document.getElementById('client-test-result');
                    if (testResultEl && this.testResult) {
                        testResultEl.style.display = 'block';
                        const msgEl = document.getElementById('client-test-message');
                        const statusEl = document.getElementById('client-test-status');
                        const boxEl = document.getElementById('client-test-box');
                        if (msgEl) msgEl.textContent = this.testResult.message;
                        if (statusEl) statusEl.textContent = this.testResult.ok ? 'Verified' : 'Failed';
                        if (boxEl) {
                            boxEl.className = this.testResult.ok
                                ? 'p-3 rounded-lg text-xs flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 text-emerald-400'
                                : 'p-3 rounded-lg text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400';
                        }
                    }
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

        {{-- Form Container wrapping both engine selection and configuration --}}
        <form method="POST" action="{{ route('install.step.database.store') }}" class="space-y-6">
            @csrf

            {{-- Error Banner for Driver --}}
            @error('driver')
                <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            {{-- Driver Switch Cards (Semantic Radio Group) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" role="radiogroup" aria-label="Database Engine Selection">
                {{-- SQLite Card --}}
                <label
                    id="card-engine-sqlite"
                    for="staged-engine-sqlite"
                    onclick="window.syncDatabaseDriver('sqlite')"
                    @click="selectDriver('sqlite')"
                    class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between {{ ! $isMysql ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                    :class="driver === 'sqlite' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover'"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-text-primary flex items-center gap-2">
                                <input
                                    id="staged-engine-sqlite"
                                    type="radio"
                                    name="driver"
                                    value="sqlite"
                                    x-model="driver"
                                    onchange="window.syncDatabaseDriver('sqlite')"
                                    @change="selectDriver('sqlite')"
                                    {{ ! $isMysql ? 'checked' : '' }}
                                    class="w-4 h-4 text-primary-600 focus:ring-primary-500 border-border-default cursor-pointer"
                                />
                                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                SQLite (Recommended)
                            </span>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-surface-elevated text-text-muted">Self-Contained</span>
                        </div>
                        <p class="text-xs text-text-secondary pl-6">
                            Zero configuration required. Ideal for shared hosting, VPS, single academies, and easy full-system backups. Stored securely under <code>storage/app/private/</code>.
                        </p>
                    </div>

                    <div class="mt-3 pt-2 border-t border-border-default/60 flex items-center justify-between text-xs">
                        <span class="text-[11px] text-text-muted">Single-file database</span>
                        <span id="sqlite-status-selected" class="text-primary-600 dark:text-primary-400 font-bold text-xs" style="{{ $isMysql ? 'display: none;' : '' }}" x-show="driver === 'sqlite'">Selected</span>
                        <span id="sqlite-status-unselected" class="text-text-muted text-xs" style="{{ ! $isMysql ? 'display: none;' : '' }}" x-show="driver !== 'sqlite'">Click to select</span>
                    </div>
                </label>

                {{-- MySQL Card --}}
                <label
                    id="card-engine-mysql"
                    for="staged-engine-mysql"
                    onclick="window.syncDatabaseDriver('mysql')"
                    @click="selectDriver('mysql')"
                    class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between {{ $isMysql ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                    :class="driver === 'mysql' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover'"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-text-primary flex items-center gap-2">
                                <input
                                    id="staged-engine-mysql"
                                    type="radio"
                                    name="driver"
                                    value="mysql"
                                    x-model="driver"
                                    onchange="window.syncDatabaseDriver('mysql')"
                                    @change="selectDriver('mysql')"
                                    {{ $isMysql ? 'checked' : '' }}
                                    class="w-4 h-4 text-primary-600 focus:ring-primary-500 border-border-default cursor-pointer"
                                />
                                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                MySQL 8.4+ InnoDB
                            </span>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-surface-elevated text-text-muted">Dedicated DB</span>
                        </div>
                        <p class="text-xs text-text-secondary pl-6">
                            Recommended for academies anticipating high concurrent student loads, large cohorts, and managed remote database servers.
                        </p>
                    </div>

                    <div class="mt-3 pt-2 border-t border-border-default/60 flex items-center justify-between text-xs">
                        <span class="text-[11px] text-text-muted">High concurrency engine</span>
                        <span id="mysql-status-selected" class="text-primary-600 dark:text-primary-400 font-bold text-xs" style="{{ ! $isMysql ? 'display: none;' : '' }}" x-show="driver === 'mysql'">Selected</span>
                        <span id="mysql-status-unselected" class="text-text-muted text-xs" style="{{ $isMysql ? 'display: none;' : '' }}" x-show="driver !== 'mysql'">Click to select</span>
                    </div>
                </label>
            </div>

            {{-- SQLite Details --}}
            <div
                id="sqlite-config-panel"
                style="{{ $isMysql ? 'display: none;' : '' }}"
                x-show="driver === 'sqlite'"
                class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-3"
            >
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
            <div
                id="mysql-config-panel"
                style="{{ ! $isMysql ? 'display: none;' : '' }}"
                x-show="driver === 'mysql'"
                class="space-y-4 p-5 rounded-xl bg-surface-elevated/40 border border-border-default"
            >
                {{-- Hosting Type Selector --}}
                <div>
                    <label class="block text-xs font-bold text-text-primary mb-2">Database Hosting Type</label>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                        <button
                            type="button"
                            id="btn-hosting-local"
                            onclick="window.syncHostingType('local')"
                            @click="selectHostingType('local')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold"
                            :class="hostingType === 'local' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                        >
                            <span class="block text-text-primary font-bold">Local MySQL</span>
                            <span class="text-[10px] text-text-muted">CloudPanel / VPS</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-rds"
                            onclick="window.syncHostingType('rds')"
                            @click="selectHostingType('rds')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover"
                            :class="hostingType === 'rds' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                        >
                            <span class="block text-text-primary font-bold">Amazon RDS</span>
                            <span class="text-[10px] text-text-muted">AWS Database</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-remote"
                            onclick="window.syncHostingType('remote')"
                            @click="selectHostingType('remote')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover"
                            :class="hostingType === 'remote' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                        >
                            <span class="block text-text-primary font-bold">Remote Server</span>
                            <span class="text-[10px] text-text-muted">External Host</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-custom"
                            onclick="window.syncHostingType('custom')"
                            @click="selectHostingType('custom')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover"
                            :class="hostingType === 'custom' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                        >
                            <span class="block text-text-primary font-bold">Custom</span>
                            <span class="text-[10px] text-text-muted">Manual Setup</span>
                        </button>
                    </div>
                </div>

                {{-- CloudPanel Specific Assistance --}}
                <div
                    id="cloudpanel-guide"
                    class="p-3.5 rounded-lg bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 text-xs text-text-secondary space-y-2"
                    x-show="hostingType === 'local'"
                >
                    <span class="font-bold text-xs text-blue-900 dark:text-blue-200 block">CloudPanel Setup Guide</span>
                    <ol class="list-decimal list-inside space-y-1 text-[11px] text-text-secondary">
                        <li>Open CloudPanel and navigate to your site's <strong>Databases</strong> section.</li>
                        <li>Create a database (e.g. <code class="font-mono text-primary-600">seo_academy</code>) and database user.</li>
                        <li>Assign full schema privileges to the user.</li>
                        <li>Enter credentials below. Recommended local connection: <code class="font-mono">DB_HOST=127.0.0.1</code>, <code class="font-mono">DB_PORT=3306</code>.</li>
                    </ol>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-text-secondary mb-1">Database Server Host *</label>
                        <input
                            type="text"
                            id="db_host"
                            name="host"
                            x-model="host"
                            value="{{ $dbHost }}"
                            placeholder="127.0.0.1"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Port *</label>
                        <input
                            type="number"
                            id="db_port"
                            name="port"
                            x-model="port"
                            value="{{ $dbPort }}"
                            placeholder="3306"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Database Name *</label>
                        <input
                            type="text"
                            id="db_database"
                            name="database"
                            x-model="database"
                            value="{{ $dbName }}"
                            placeholder="seo_academy"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Username *</label>
                        <input
                            type="text"
                            id="db_username"
                            name="username"
                            x-model="username"
                            value="{{ $dbUser }}"
                            placeholder="seo_academy_user"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Password</label>
                        <input
                            type="password"
                            id="db_password"
                            name="password"
                            x-model="password"
                            value=""
                            placeholder="••••••••••••"
                            class="input input-sm w-full text-xs"
                            autocomplete="new-password"
                        >
                    </div>
                </div>
            </div>

            {{-- Connection Test Result Alert --}}
            <div id="client-test-result" style="display: none;" x-show="testResult !== null">
                <div
                    id="client-test-box"
                    class="p-3 rounded-lg text-xs flex items-center justify-between"
                    :class="testResult && testResult.ok ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400'"
                >
                    <span id="client-test-message" x-text="testResult ? testResult.message : ''"></span>
                    <span id="client-test-status" class="font-bold uppercase text-[10px]" x-text="testResult && testResult.ok ? 'Verified' : 'Failed'"></span>
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
                        id="btn-test-connection"
                        onclick="window.runConnectionTest()"
                        @click="testConnection()"
                        :disabled="testing"
                        class="btn btn-outline btn-sm text-xs font-semibold"
                    >
                        <span id="test-btn-idle" x-show="!testing">Test Connection</span>
                        <span id="test-btn-loading" style="display: none;" x-show="testing">Verifying...</span>
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

    {{-- Bulletproof Native JavaScript Fallback & Synchronization --}}
    <script>
        (function () {
            const activeClasses = ['border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/30'];
            const defaultClasses = ['border-border-default', 'bg-surface-elevated/40'];

            window.syncDatabaseDriver = function (driver) {
                const sqliteRadio = document.getElementById('staged-engine-sqlite');
                const mysqlRadio = document.getElementById('staged-engine-mysql');
                const sqliteCard = document.getElementById('card-engine-sqlite');
                const mysqlCard = document.getElementById('card-engine-mysql');
                const sqlitePanel = document.getElementById('sqlite-config-panel');
                const mysqlPanel = document.getElementById('mysql-config-panel');
                const sqliteSelected = document.getElementById('sqlite-status-selected');
                const sqliteUnselected = document.getElementById('sqlite-status-unselected');
                const mysqlSelected = document.getElementById('mysql-status-selected');
                const mysqlUnselected = document.getElementById('mysql-status-unselected');

                if (driver === 'mysql') {
                    if (mysqlRadio) mysqlRadio.checked = true;
                    if (sqliteRadio) sqliteRadio.checked = false;

                    if (mysqlCard) {
                        mysqlCard.classList.remove(...defaultClasses);
                        mysqlCard.classList.add(...activeClasses);
                    }
                    if (sqliteCard) {
                        sqliteCard.classList.remove(...activeClasses);
                        sqliteCard.classList.add(...defaultClasses);
                    }

                    if (mysqlPanel) mysqlPanel.style.display = 'block';
                    if (sqlitePanel) sqlitePanel.style.display = 'none';

                    if (mysqlSelected) mysqlSelected.style.display = 'inline';
                    if (mysqlUnselected) mysqlUnselected.style.display = 'none';
                    if (sqliteSelected) sqliteSelected.style.display = 'none';
                    if (sqliteUnselected) sqliteUnselected.style.display = 'inline';

                    // Ensure default values are populated if fields are blank
                    const hostInput = document.getElementById('db_host');
                    const portInput = document.getElementById('db_port');
                    const dbInput = document.getElementById('db_database');
                    const userInput = document.getElementById('db_username');
                    if (hostInput && !hostInput.value) hostInput.value = '127.0.0.1';
                    if (portInput && !portInput.value) portInput.value = '3306';
                    if (dbInput && !dbInput.value) dbInput.value = 'seo_academy';
                    if (userInput && !userInput.value) userInput.value = 'seo_academy_user';
                } else {
                    if (sqliteRadio) sqliteRadio.checked = true;
                    if (mysqlRadio) mysqlRadio.checked = false;

                    if (sqliteCard) {
                        sqliteCard.classList.remove(...defaultClasses);
                        sqliteCard.classList.add(...activeClasses);
                    }
                    if (mysqlCard) {
                        mysqlCard.classList.remove(...activeClasses);
                        mysqlCard.classList.add(...defaultClasses);
                    }

                    if (sqlitePanel) sqlitePanel.style.display = 'block';
                    if (mysqlPanel) mysqlPanel.style.display = 'none';

                    if (sqliteSelected) sqliteSelected.style.display = 'inline';
                    if (sqliteUnselected) sqliteUnselected.style.display = 'none';
                    if (mysqlSelected) mysqlSelected.style.display = 'none';
                    if (mysqlUnselected) mysqlUnselected.style.display = 'inline';
                }

                // Sync Alpine data if Alpine is active
                const container = document.getElementById('db-wizard-container');
                if (window.Alpine && container && window.Alpine.$data) {
                    try {
                        const data = window.Alpine.$data(container);
                        if (data && data.driver !== driver) {
                            data.driver = driver;
                        }
                    } catch (e) {
                        // ignore if Alpine not ready
                    }
                }
            };

            window.syncHostingType = function (type) {
                const types = ['local', 'rds', 'remote', 'custom'];
                const hostInput = document.getElementById('db_host');
                const portInput = document.getElementById('db_port');
                const guide = document.getElementById('cloudpanel-guide');

                types.forEach(t => {
                    const btn = document.getElementById('btn-hosting-' + t);
                    if (btn) {
                        if (t === type) {
                            btn.classList.add('border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/20', 'font-bold');
                            btn.classList.remove('border-border-default', 'bg-surface-card');
                        } else {
                            btn.classList.remove('border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/20', 'font-bold');
                            btn.classList.add('border-border-default', 'bg-surface-card');
                        }
                    }
                });

                if (type === 'local') {
                    if (hostInput) hostInput.value = '127.0.0.1';
                    if (portInput) portInput.value = '3306';
                    if (guide) guide.style.display = 'block';
                } else if (type === 'rds' || type === 'remote') {
                    if (hostInput && hostInput.value === '127.0.0.1') {
                        hostInput.value = '';
                    }
                    if (portInput) portInput.value = '3306';
                    if (guide) guide.style.display = 'none';
                } else {
                    if (guide) guide.style.display = 'none';
                }

                // Sync Alpine if available
                const container = document.getElementById('db-wizard-container');
                if (window.Alpine && container && window.Alpine.$data) {
                    try {
                        const data = window.Alpine.$data(container);
                        if (data) {
                            data.hostingType = type;
                            if (hostInput) data.host = hostInput.value;
                            if (portInput) data.port = portInput.value;
                        }
                    } catch (e) {}
                }
            };

            window.runConnectionTest = async function () {
                const idleEl = document.getElementById('test-btn-idle');
                const loadingEl = document.getElementById('test-btn-loading');
                const testResultEl = document.getElementById('client-test-result');
                const boxEl = document.getElementById('client-test-box');
                const msgEl = document.getElementById('client-test-message');
                const statusEl = document.getElementById('client-test-status');

                if (idleEl) idleEl.style.display = 'none';
                if (loadingEl) loadingEl.style.display = 'inline';

                const driver = document.querySelector('input[name="driver"]:checked')?.value || 'sqlite';
                const payload = driver === 'sqlite'
                    ? { driver: 'sqlite' }
                    : {
                        driver: 'mysql',
                        host: document.getElementById('db_host')?.value || '127.0.0.1',
                        port: document.getElementById('db_port')?.value || '3306',
                        database: document.getElementById('db_database')?.value || 'seo_academy',
                        username: document.getElementById('db_username')?.value || 'seo_academy_user',
                        password: document.getElementById('db_password')?.value || '',
                    };

                try {
                    const response = await fetch('{{ route('install.test') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();
                    const isOk = Boolean(data.ok);
                    const message = data.message || (response.ok ? 'Connection successful.' : 'Connection check failed.');

                    if (testResultEl) testResultEl.style.display = 'block';
                    if (msgEl) msgEl.textContent = message;
                    if (statusEl) statusEl.textContent = isOk ? 'Verified' : 'Failed';
                    if (boxEl) {
                        boxEl.className = isOk
                            ? 'p-3 rounded-lg text-xs flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 text-emerald-400'
                            : 'p-3 rounded-lg text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400';
                    }
                } catch (e) {
                    if (testResultEl) testResultEl.style.display = 'block';
                    if (msgEl) msgEl.textContent = 'Unable to reach the installer test endpoint. Please check network connectivity and try again.';
                    if (statusEl) statusEl.textContent = 'Failed';
                    if (boxEl) {
                        boxEl.className = 'p-3 rounded-lg text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400';
                    }
                } finally {
                    if (idleEl) idleEl.style.display = 'inline';
                    if (loadingEl) loadingEl.style.display = 'none';
                }
            };
        })();
    </script>
@endcomponent
