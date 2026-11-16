@component('install.layout', [
    'title' => 'Step 4 — Database Selection & Configuration',
    'stepNumber' => 4,
    'totalSteps' => 9,
    'state' => $state,
])
    @php
        $selectedDriver = old('driver', request()->query('driver', $currentDriver ?? 'sqlite'));
        $errBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
        // Fail-safe: if any MySQL-specific validation errors occurred, old('driver') is mysql, or URL query specifies mysql, force MySQL
        if ($errBag->has('host') || $errBag->has('database') || $errBag->has('username') || $errBag->has('port') || $errBag->has('password') || old('driver') === 'mysql' || request()->query('driver') === 'mysql') {
            $selectedDriver = 'mysql';
        }
        $isMysql = ($selectedDriver === 'mysql');
        $dbHost = old('host', $state['db_config']['host'] ?? '127.0.0.1');
        $dbPort = old('port', $state['db_config']['port'] ?? '3306');
        $dbName = old('database', $state['db_config']['database'] ?? 'seo_academy');
        $dbUser = old('username', $state['db_config']['username'] ?? 'root');
    @endphp

    <script>
        // Global engine switcher: updates DOM directly, works 100% reliably in every browser regardless of Alpine or CSS
        window.switchDatabaseEngine = function (driver) {
            const isMysql = (driver === 'mysql');
            const sqliteRadio = document.getElementById('staged-engine-sqlite');
            const mysqlRadio = document.getElementById('staged-engine-mysql');
            const sqlitePanel = document.getElementById('sqlite-config-panel');
            const mysqlPanel = document.getElementById('mysql-config-panel');
            const sqliteCard = document.getElementById('card-engine-sqlite');
            const mysqlCard = document.getElementById('card-engine-mysql');
            const sqliteStatusSelected = document.getElementById('sqlite-status-selected');
            const sqliteStatusUnselected = document.getElementById('sqlite-status-unselected');
            const mysqlStatusSelected = document.getElementById('mysql-status-selected');
            const mysqlStatusUnselected = document.getElementById('mysql-status-unselected');
            const btnQuickConfigureMysql = document.getElementById('btn-quick-configure-mysql');
            const btnQuickSwitchSqlite = document.getElementById('btn-quick-switch-sqlite');
            const activeDriverBadge = document.getElementById('active-driver-badge');

            if (sqliteRadio) sqliteRadio.checked = !isMysql;
            if (mysqlRadio) mysqlRadio.checked = isMysql;

            // Direct DOM display manipulation
            if (mysqlPanel) {
                mysqlPanel.style.setProperty('display', isMysql ? 'block' : 'none', 'important');
            }
            if (sqlitePanel) {
                sqlitePanel.style.setProperty('display', !isMysql ? 'block' : 'none', 'important');
            }

            // Status badges
            if (mysqlStatusSelected) mysqlStatusSelected.style.setProperty('display', isMysql ? 'inline' : 'none', 'important');
            if (mysqlStatusUnselected) mysqlStatusUnselected.style.setProperty('display', !isMysql ? 'inline' : 'none', 'important');
            if (sqliteStatusSelected) sqliteStatusSelected.style.setProperty('display', !isMysql ? 'inline' : 'none', 'important');
            if (sqliteStatusUnselected) sqliteStatusUnselected.style.setProperty('display', isMysql ? 'inline' : 'none', 'important');

            // Quick action buttons
            if (btnQuickConfigureMysql) btnQuickConfigureMysql.style.setProperty('display', isMysql ? 'none' : 'inline-flex', 'important');
            if (btnQuickSwitchSqlite) btnQuickSwitchSqlite.style.setProperty('display', !isMysql ? 'none' : 'inline-flex', 'important');

            if (activeDriverBadge) {
                activeDriverBadge.textContent = isMysql ? 'MYSQL' : 'SQLITE';
            }

            // Card highlight classes
            if (mysqlCard) {
                if (isMysql) {
                    mysqlCard.classList.add('border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/30');
                    mysqlCard.classList.remove('border-border-default', 'bg-surface-elevated/40');
                } else {
                    mysqlCard.classList.remove('border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/30');
                    mysqlCard.classList.add('border-border-default', 'bg-surface-elevated/40');
                }
            }
            if (sqliteCard) {
                if (!isMysql) {
                    sqliteCard.classList.add('border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/30');
                    sqliteCard.classList.remove('border-border-default', 'bg-surface-elevated/40');
                } else {
                    sqliteCard.classList.remove('border-primary-500', 'bg-primary-500/10', 'ring-2', 'ring-primary-500/30');
                    sqliteCard.classList.add('border-border-default', 'bg-surface-elevated/40');
                }
            }

            // Populate MySQL defaults if fields are empty
            if (isMysql) {
                const hostEl = document.getElementById('db_host');
                const portEl = document.getElementById('db_port');
                const nameEl = document.getElementById('db_database');
                const userEl = document.getElementById('db_username');
                if (hostEl && !hostEl.value) hostEl.value = '127.0.0.1';
                if (portEl && !portEl.value) portEl.value = '3306';
                if (nameEl && !nameEl.value) nameEl.value = 'seo_academy';
                if (userEl && !userEl.value) userEl.value = 'root';
            }

            // Sync Alpine state if initialized
            const container = document.getElementById('db-wizard-container');
            if (window.Alpine && container && typeof window.Alpine.$data === 'function') {
                try {
                    const data = window.Alpine.$data(container);
                    if (data) data.driver = driver;
                } catch (e) {}
            }
        };
    </script>

    <div
        id="db-wizard-container"
        class="space-y-6"
        x-data="{
            driver: @js($selectedDriver),
            hostingType: 'local',
            switchEngine(target) {
                this.driver = target;
                if (typeof window.switchDatabaseEngine === 'function') {
                    window.switchDatabaseEngine(target);
                }
            },
            setHosting(type) {
                this.hostingType = type;
                if (typeof window.switchHostingType === 'function') {
                    window.switchHostingType(type);
                }
            }
        }"
    >
        <div>
            <h2 class="text-lg font-bold text-text-primary">Choose Your Database Engine</h2>
            <p class="text-xs text-text-secondary mt-1">
                SEO Academy supports high-performance self-contained SQLite 3.35+ and enterprise-scale MySQL 8.4+ InnoDB. Select your target engine below to configure database parameters.
            </p>
        </div>

        {{-- Form Container wrapping both engine selection and configuration --}}
        <form id="db-config-form" method="POST" action="{{ route('install.step.database.store') }}" class="space-y-6">
            @csrf

            {{-- Error Banner for Driver --}}
            @error('driver')
                <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            {{-- Driver Switch Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" role="radiogroup" aria-label="Database Engine Selection">
                {{-- SQLite Card --}}
                <label
                    for="staged-engine-sqlite"
                    id="card-engine-sqlite"
                    onclick="window.switchDatabaseEngine('sqlite')"
                    @click="switchEngine('sqlite')"
                    :class="driver === 'sqlite' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover'"
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
                                    x-model="driver"
                                    onclick="window.switchDatabaseEngine('sqlite')"
                                    @change="switchEngine('sqlite')"
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
                        <span id="sqlite-status-selected" class="text-primary-600 dark:text-primary-400 font-bold text-xs" style="{{ $isMysql ? 'display: none !important;' : '' }}">Selected</span>
                        <span id="sqlite-status-unselected" class="text-text-muted text-xs" style="{{ ! $isMysql ? 'display: none !important;' : '' }}">Click to select</span>
                    </div>
                </label>

                {{-- MySQL Card --}}
                <label
                    for="staged-engine-mysql"
                    id="card-engine-mysql"
                    onclick="window.switchDatabaseEngine('mysql')"
                    @click="switchEngine('mysql')"
                    :class="driver === 'mysql' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-border-default bg-surface-elevated/40 hover:border-border-hover'"
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
                                    x-model="driver"
                                    onclick="window.switchDatabaseEngine('mysql')"
                                    @change="switchEngine('mysql')"
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
                        <span id="mysql-status-selected" class="text-primary-600 dark:text-primary-400 font-bold text-xs" style="{{ ! $isMysql ? 'display: none !important;' : '' }}">Selected</span>
                        <span id="mysql-status-unselected" class="text-text-muted text-xs" style="{{ $isMysql ? 'display: none !important;' : '' }}">Click to select</span>
                    </div>
                </label>
            </div>

            {{-- Explicit Quick Switch Bar --}}
            <div class="p-3.5 rounded-xl bg-surface-elevated/40 border border-border-default flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-text-muted">Active Database Driver:</span>
                    <span id="active-driver-badge" class="font-bold font-mono uppercase text-primary-600 dark:text-primary-400 text-sm" x-text="driver">{{ $selectedDriver }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="btn-quick-configure-mysql"
                        @click="switchEngine('mysql')"
                        onclick="window.switchDatabaseEngine('mysql')"
                        class="px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs transition-colors cursor-pointer"
                        style="{{ $isMysql ? 'display: none !important;' : '' }}"
                    >
                        Configure MySQL Connection &rarr;
                    </button>
                    <button
                        type="button"
                        id="btn-quick-switch-sqlite"
                        @click="switchEngine('sqlite')"
                        onclick="window.switchDatabaseEngine('sqlite')"
                        class="px-3 py-1.5 rounded-lg bg-surface-card hover:bg-surface-elevated border border-border-default text-text-primary font-semibold text-xs transition-colors cursor-pointer"
                        style="{{ ! $isMysql ? 'display: none !important;' : '' }}"
                    >
                        &larr; Switch Back to SQLite
                    </button>
                </div>
            </div>

            {{-- SQLite Details Panel --}}
            <div
                id="sqlite-config-panel"
                x-show="driver === 'sqlite'"
                style="{{ $isMysql ? 'display: none !important;' : '' }}"
                class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default space-y-3"
            >
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-text-primary">SQLite Database Configuration</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-semibold border border-emerald-500/20">Zero Server Setup</span>
                </div>
                <div class="text-xs text-text-secondary space-y-1">
                    <p>
                        Database File Path: <code class="font-mono text-primary-600 dark:text-primary-400 font-bold">storage/app/private/database.sqlite</code>
                    </p>
                    <p class="text-[11px] text-text-muted">
                        The installer automatically creates the database file with safe permissions. The database is stored outside the public document root and requires no network ports or credentials.
                    </p>
                </div>
            </div>

            {{-- MySQL Fields Panel --}}
            <div
                id="mysql-config-panel"
                x-show="driver === 'mysql'"
                style="{{ ! $isMysql ? 'display: none !important;' : '' }}"
                class="space-y-4 p-5 rounded-xl bg-surface-elevated/40 border border-border-default"
            >
                <div class="flex items-center justify-between pb-2 border-b border-border-default/60">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-text-primary">MySQL 8.4+ Database Configuration</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 font-semibold border border-blue-500/20">Dedicated DB</span>
                    </div>
                    <span class="text-[11px] text-text-muted">All marked fields (*) are required for MySQL</span>
                </div>

                {{-- Hosting Type Quick Presets --}}
                <div>
                    <label class="block text-xs font-bold text-text-primary mb-2">Hosting Configuration Presets</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button
                            type="button"
                            id="btn-hosting-local"
                            @click="setHosting('local')"
                            onclick="window.switchHostingType('local')"
                            :class="hostingType === 'local' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold cursor-pointer"
                        >
                            <span class="block text-text-primary font-bold">Local MySQL</span>
                            <span class="text-[10px] text-text-muted">CloudPanel / VPS</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-rds"
                            @click="setHosting('rds')"
                            onclick="window.switchHostingType('rds')"
                            :class="hostingType === 'rds' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover cursor-pointer"
                        >
                            <span class="block text-text-primary font-bold">Amazon RDS</span>
                            <span class="text-[10px] text-text-muted">AWS Database</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-remote"
                            @click="setHosting('remote')"
                            onclick="window.switchHostingType('remote')"
                            :class="hostingType === 'remote' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover cursor-pointer"
                        >
                            <span class="block text-text-primary font-bold">Remote Server</span>
                            <span class="text-[10px] text-text-muted">External Host</span>
                        </button>

                        <button
                            type="button"
                            id="btn-hosting-custom"
                            @click="setHosting('custom')"
                            onclick="window.switchHostingType('custom')"
                            :class="hostingType === 'custom' ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold' : 'border-border-default bg-surface-card hover:border-border-hover'"
                            class="p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover cursor-pointer"
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
                    <span class="font-bold text-xs text-blue-900 dark:text-blue-200 block">CloudPanel Setup Quick Guide</span>
                    <ol class="list-decimal list-inside space-y-1 text-[11px] text-text-secondary">
                        <li>Open your VPS CloudPanel dashboard and navigate to <strong>Databases</strong>.</li>
                        <li>Create a database (default: <code class="font-mono text-primary-600 dark:text-primary-400 font-bold">seo_academy</code>) and database user.</li>
                        <li>Assign full schema read/write privileges to that user.</li>
                        <li>Enter credentials below. Standard local connection: <code class="font-mono">DB_HOST=127.0.0.1</code>, <code class="font-mono">DB_PORT=3306</code>.</li>
                    </ol>
                </div>

                {{-- Host and Port Fields --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="db_host" class="block text-xs font-medium text-text-secondary mb-1">
                            Database Server Host *
                        </label>
                        <input
                            type="text"
                            id="db_host"
                            name="host"
                            value="{{ $dbHost }}"
                            placeholder="127.0.0.1"
                            class="input input-sm w-full text-xs font-mono {{ $errors->has('host') ? 'border-rose-500 focus:ring-rose-500' : '' }}"
                        />
                        @error('host')
                            <p class="text-[11px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="db_port" class="block text-xs font-medium text-text-secondary mb-1">
                            Port *
                        </label>
                        <input
                            type="number"
                            id="db_port"
                            name="port"
                            value="{{ $dbPort }}"
                            placeholder="3306"
                            class="input input-sm w-full text-xs font-mono {{ $errors->has('port') ? 'border-rose-500 focus:ring-rose-500' : '' }}"
                        />
                        @error('port')
                            <p class="text-[11px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Database Name, Username, and Password Fields --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="db_database" class="block text-xs font-medium text-text-secondary mb-1">
                            Database Name *
                        </label>
                        <input
                            type="text"
                            id="db_database"
                            name="database"
                            value="{{ $dbName }}"
                            placeholder="seo_academy"
                            class="input input-sm w-full text-xs font-mono {{ $errors->has('database') ? 'border-rose-500 focus:ring-rose-500' : '' }}"
                        />
                        @error('database')
                            <p class="text-[11px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="db_username" class="block text-xs font-medium text-text-secondary mb-1">
                            Username *
                        </label>
                        <input
                            type="text"
                            id="db_username"
                            name="username"
                            value="{{ $dbUser }}"
                            placeholder="root"
                            class="input input-sm w-full text-xs font-mono {{ $errors->has('username') ? 'border-rose-500 focus:ring-rose-500' : '' }}"
                        />
                        @error('username')
                            <p class="text-[11px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="db_password" class="block text-xs font-medium text-text-secondary mb-1">
                            Password
                        </label>
                        <input
                            type="password"
                            id="db_password"
                            name="password"
                            value=""
                            placeholder="••••••••••••"
                            class="input input-sm w-full text-xs"
                            autocomplete="new-password"
                        />
                        @error('password')
                            <p class="text-[11px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Connection Test Result Alert --}}
            <div id="client-test-result" style="display: none;">
                <div id="client-test-box" class="p-3.5 rounded-xl text-xs flex items-center justify-between">
                    <span id="client-test-message"></span>
                    <span id="client-test-status" class="font-bold uppercase text-[10px]"></span>
                </div>
            </div>

            {{-- Database Switch Confirmation (if needed) --}}
            @if (session('warning_switch'))
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2">
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
                        class="btn btn-outline btn-sm text-xs font-semibold cursor-pointer"
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

    {{-- Comprehensive Vanilla JS + Event Handlers --}}
    <script>
        // Preset switcher function
        window.switchHostingType = function (type) {
            const types = ['local', 'rds', 'remote', 'custom'];
            const hostInput = document.getElementById('db_host');
            const portInput = document.getElementById('db_port');
            const guide = document.getElementById('cloudpanel-guide');

            types.forEach(t => {
                const btn = document.getElementById('btn-hosting-' + t);
                if (btn) {
                    if (t === type) {
                        btn.className = 'p-3 rounded-lg border text-left text-xs transition-all border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/20 font-bold cursor-pointer';
                    } else {
                        btn.className = 'p-3 rounded-lg border text-left text-xs transition-all border-border-default bg-surface-card hover:border-border-hover cursor-pointer';
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

            // Sync Alpine if active
            const container = document.getElementById('db-wizard-container');
            if (window.Alpine && container && typeof window.Alpine.$data === 'function') {
                try {
                    const data = window.Alpine.$data(container);
                    if (data) data.hostingType = type;
                } catch (e) {}
            }
        };

        // AJAX connection test
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
                        ? 'p-3.5 rounded-xl text-xs flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-semibold'
                        : 'p-3.5 rounded-xl text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400 font-semibold';
                }
            } catch (err) {
                if (testResultEl) testResultEl.style.display = 'block';
                if (msgEl) msgEl.textContent = 'Unable to reach the installer test endpoint. Please check network connectivity and try again.';
                if (statusEl) statusEl.textContent = 'Failed';
                if (boxEl) {
                    boxEl.className = 'p-3.5 rounded-xl text-xs flex items-center justify-between bg-rose-500/10 border border-rose-500/30 text-rose-400 font-semibold';
                }
            } finally {
                if (idleEl) idleEl.style.display = 'inline';
                if (loadingEl) loadingEl.style.display = 'none';
            }
        };

        // Attach native DOM event listeners on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function () {
            const initialDriver = @js($selectedDriver);
            window.switchDatabaseEngine(initialDriver);

            // Directly observe radio button changes
            const radios = document.querySelectorAll('input[name="driver"]');
            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    window.switchDatabaseEngine(this.value);
                });
            });
        });
    </script>
@endcomponent
