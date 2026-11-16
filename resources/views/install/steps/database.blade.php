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
        $dbUser = old('username', $state['db_config']['username'] ?? 'root');
    @endphp

    <script>
        // Define global functions immediately before DOM rendering so inline handlers never fail
        window.switchDatabaseEngine = function (driver) {
            const isMysql = (driver === 'mysql');
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

            const activeClasses = ['border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/30'];
            const inactiveClasses = ['border-border-default', 'bg-surface-elevated/40'];

            if (isMysql) {
                if (mysqlRadio) mysqlRadio.checked = true;
                if (sqliteRadio) sqliteRadio.checked = false;

                if (mysqlCard) {
                    inactiveClasses.forEach(c => mysqlCard.classList.remove(c));
                    activeClasses.forEach(c => mysqlCard.classList.add(c));
                }
                if (sqliteCard) {
                    activeClasses.forEach(c => sqliteCard.classList.remove(c));
                    inactiveClasses.forEach(c => sqliteCard.classList.add(c));
                }

                if (mysqlPanel) {
                    mysqlPanel.style.removeProperty('display');
                    mysqlPanel.style.display = 'block';
                }
                if (sqlitePanel) {
                    sqlitePanel.style.display = 'none';
                }

                if (mysqlSelected) mysqlSelected.style.display = 'inline';
                if (mysqlUnselected) mysqlUnselected.style.display = 'none';
                if (sqliteSelected) sqliteSelected.style.display = 'none';
                if (sqliteUnselected) sqliteUnselected.style.display = 'inline';

                // Populate defaults if fields are empty
                const hostEl = document.getElementById('db_host');
                const portEl = document.getElementById('db_port');
                const nameEl = document.getElementById('db_database');
                const userEl = document.getElementById('db_username');
                if (hostEl) {
                    if (!hostEl.value) hostEl.value = '127.0.0.1';
                    hostEl.setAttribute('required', 'required');
                }
                if (portEl && !portEl.value) portEl.value = '3306';
                if (nameEl) {
                    if (!nameEl.value) nameEl.value = 'seo_academy';
                    nameEl.setAttribute('required', 'required');
                }
                if (userEl) {
                    if (!userEl.value) userEl.value = 'root';
                    userEl.setAttribute('required', 'required');
                }
            } else {
                if (sqliteRadio) sqliteRadio.checked = true;
                if (mysqlRadio) mysqlRadio.checked = false;

                if (sqliteCard) {
                    inactiveClasses.forEach(c => sqliteCard.classList.remove(c));
                    activeClasses.forEach(c => sqliteCard.classList.add(c));
                }
                if (mysqlCard) {
                    activeClasses.forEach(c => mysqlCard.classList.remove(c));
                    inactiveClasses.forEach(c => mysqlCard.classList.add(c));
                }

                if (sqlitePanel) {
                    sqlitePanel.style.removeProperty('display');
                    sqlitePanel.style.display = 'block';
                }
                if (mysqlPanel) {
                    mysqlPanel.style.display = 'none';
                }

                if (sqliteSelected) sqliteSelected.style.display = 'inline';
                if (sqliteUnselected) sqliteUnselected.style.display = 'none';
                if (mysqlSelected) mysqlSelected.style.display = 'none';
                if (mysqlUnselected) mysqlUnselected.style.display = 'inline';

                const hostEl = document.getElementById('db_host');
                const nameEl = document.getElementById('db_database');
                const userEl = document.getElementById('db_username');
                if (hostEl) hostEl.removeAttribute('required');
                if (nameEl) nameEl.removeAttribute('required');
                if (userEl) userEl.removeAttribute('required');
            }
        };

        window.switchHostingType = function (type) {
            const types = ['local', 'rds', 'remote', 'custom'];
            const hostInput = document.getElementById('db_host');
            const portInput = document.getElementById('db_port');
            const guide = document.getElementById('cloudpanel-guide');

            types.forEach(t => {
                const btn = document.getElementById('btn-hosting-' + t);
                if (btn) {
                    if (t === type) {
                        btn.className = 'p-3 rounded-lg border text-left text-xs transition-all border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold';
                    } else {
                        btn.className = 'p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover';
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
        };

        window.testDatabaseConnection = async function () {
            const idleEl = document.getElementById('test-btn-idle');
            const loadingEl = document.getElementById('test-btn-loading');
            const testResultEl = document.getElementById('client-test-result');
            const boxEl = document.getElementById('client-test-box');
            const msgEl = document.getElementById('client-test-message');
            const statusEl = document.getElementById('client-test-status');

            if (idleEl) idleEl.style.display = 'none';
            if (loadingEl) loadingEl.style.display = 'inline';

            const isMysqlChecked = document.getElementById('staged-engine-mysql')?.checked;
            const driver = isMysqlChecked ? 'mysql' : 'sqlite';

            const payload = (driver === 'sqlite')
                ? { driver: 'sqlite' }
                : {
                    driver: 'mysql',
                    host: document.getElementById('db_host')?.value || '127.0.0.1',
                    port: document.getElementById('db_port')?.value || '3306',
                    database: document.getElementById('db_database')?.value || 'seo_academy',
                    username: document.getElementById('db_username')?.value || 'root',
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
                        ? 'p-3 rounded-lg text-xs flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-semibold'
                        : 'p-3 rounded-lg text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400 font-semibold';
                }
            } catch (err) {
                if (testResultEl) testResultEl.style.display = 'block';
                if (msgEl) msgEl.textContent = 'Unable to reach the installer test endpoint. Please check network connectivity and try again.';
                if (statusEl) statusEl.textContent = 'Failed';
                if (boxEl) {
                    boxEl.className = 'p-3 rounded-lg text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400 font-semibold';
                }
            } finally {
                if (idleEl) idleEl.style.display = 'inline';
                if (loadingEl) loadingEl.style.display = 'none';
            }
        };
    </script>

    <div id="db-wizard-container" class="space-y-6">
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

            {{-- Driver Switch Cards (Interactive Radio Group) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" role="radiogroup" aria-label="Database Engine Selection">
                {{-- SQLite Card --}}
                <div
                    id="card-engine-sqlite"
                    onclick="window.switchDatabaseEngine('sqlite')"
                    class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between {{ ! $isMysql ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-text-primary flex items-center gap-2">
                                <input
                                    id="staged-engine-sqlite"
                                    type="radio"
                                    name="driver"
                                    value="sqlite"
                                    onchange="window.switchDatabaseEngine('sqlite')"
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
                        <span id="sqlite-status-selected" class="text-primary-600 dark:text-primary-400 font-bold text-xs" style="{{ $isMysql ? 'display: none;' : '' }}">Selected</span>
                        <span id="sqlite-status-unselected" class="text-text-muted text-xs" style="{{ ! $isMysql ? 'display: none;' : '' }}">Click to select</span>
                    </div>
                </div>

                {{-- MySQL Card --}}
                <div
                    id="card-engine-mysql"
                    onclick="window.switchDatabaseEngine('mysql')"
                    class="p-4 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between {{ $isMysql ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover' }}"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-text-primary flex items-center gap-2">
                                <input
                                    id="staged-engine-mysql"
                                    type="radio"
                                    name="driver"
                                    value="mysql"
                                    onchange="window.switchDatabaseEngine('mysql')"
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
                        <span id="mysql-status-selected" class="text-primary-600 dark:text-primary-400 font-bold text-xs" style="{{ ! $isMysql ? 'display: none;' : '' }}">Selected</span>
                        <span id="mysql-status-unselected" class="text-text-muted text-xs" style="{{ $isMysql ? 'display: none;' : '' }}">Click to select</span>
                    </div>
                </div>
            </div>

            {{-- SQLite Details --}}
            <div
                id="sqlite-config-panel"
                style="{{ $isMysql ? 'display: none;' : '' }}"
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
                class="space-y-4 p-5 rounded-xl bg-surface-elevated/40 border border-border-default"
            >
                {{-- Hosting Type Selector --}}
                <div>
                    <label class="block text-xs font-bold text-text-primary mb-2">Database Hosting Type</label>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                        <button
                            type="button"
                            id="btn-hosting-local"
                            onclick="window.switchHostingType('local')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold"
                        >
                            <span class="block text-text-primary font-bold">Local MySQL</span>
                            <span class="text-[10px] text-text-muted">CloudPanel / VPS</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-rds"
                            onclick="window.switchHostingType('rds')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover"
                        >
                            <span class="block text-text-primary font-bold">Amazon RDS</span>
                            <span class="text-[10px] text-text-muted">AWS Database</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-remote"
                            onclick="window.switchHostingType('remote')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover"
                        >
                            <span class="block text-text-primary font-bold">Remote Server</span>
                            <span class="text-[10px] text-text-muted">External Host</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-custom"
                            onclick="window.switchHostingType('custom')"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover"
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
                        <label for="db_host" class="block text-xs font-medium text-text-secondary mb-1">Database Server Host *</label>
                        <input
                            type="text"
                            id="db_host"
                            name="host"
                            value="{{ $dbHost }}"
                            placeholder="127.0.0.1"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label for="db_port" class="block text-xs font-medium text-text-secondary mb-1">Port *</label>
                        <input
                            type="number"
                            id="db_port"
                            name="port"
                            value="{{ $dbPort }}"
                            placeholder="3306"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="db_database" class="block text-xs font-medium text-text-secondary mb-1">Database Name *</label>
                        <input
                            type="text"
                            id="db_database"
                            name="database"
                            value="{{ $dbName }}"
                            placeholder="seo_academy"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label for="db_username" class="block text-xs font-medium text-text-secondary mb-1">Username *</label>
                        <input
                            type="text"
                            id="db_username"
                            name="username"
                            value="{{ $dbUser }}"
                            placeholder="root"
                            class="input input-sm w-full text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label for="db_password" class="block text-xs font-medium text-text-secondary mb-1">Password</label>
                        <input
                            type="password"
                            id="db_password"
                            name="password"
                            value=""
                            placeholder="••••••••••••"
                            class="input input-sm w-full text-xs"
                            autocomplete="new-password"
                        >
                    </div>
                </div>
            </div>

            {{-- Connection Test Result Alert --}}
            <div id="client-test-result" style="display: none;">
                <div id="client-test-box" class="p-3 rounded-lg text-xs flex items-center justify-between">
                    <span id="client-test-message"></span>
                    <span id="client-test-status" class="font-bold uppercase text-[10px]"></span>
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
                        onclick="window.testDatabaseConnection()"
                        class="btn btn-outline btn-sm text-xs font-semibold"
                    >
                        <span id="test-btn-idle">Test Connection</span>
                        <span id="test-btn-loading" style="display: none;">Verifying...</span>
                    </button>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm cursor-pointer"
                >
                    Save &amp; Proceed to Migrations &rarr;
                </button>
            </div>
        </form>
    </div>

    {{-- Initialize correct engine on load --}}
    <script>
        (function () {
            const initialDriver = @js($selectedDriver);
            window.switchDatabaseEngine(initialDriver);

            // Also attach direct event listeners as backup
            const sqliteCard = document.getElementById('card-engine-sqlite');
            const mysqlCard = document.getElementById('card-engine-mysql');
            const sqliteRadio = document.getElementById('staged-engine-sqlite');
            const mysqlRadio = document.getElementById('staged-engine-mysql');

            if (sqliteCard) sqliteCard.addEventListener('click', () => window.switchDatabaseEngine('sqlite'));
            if (mysqlCard) mysqlCard.addEventListener('click', () => window.switchDatabaseEngine('mysql'));
            if (sqliteRadio) sqliteRadio.addEventListener('change', () => window.switchDatabaseEngine('sqlite'));
            if (mysqlRadio) mysqlRadio.addEventListener('change', () => window.switchDatabaseEngine('mysql'));
        })();
    </script>
@endcomponent
