@component('layouts.guest', ['title' => 'Installation Wizard'])
    <div
        class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10"
        x-data="{
            driver: @js(old('driver', 'sqlite')),
            host: @js(old('host', '127.0.0.1')),
            port: @js(old('port', '3306')),
            database: @js(old('database', '')),
            username: @js(old('username', '')),
            password: '',
            testing: false,
            installing: false,
            testResult: null,

            selectDriver(nextDriver) {
                this.driver = nextDriver;
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
                        message: 'Unable to reach the installer endpoint. Please verify server connectivity and try again.',
                    };
                } finally {
                    this.testing = false;
                }
            }
        }"
    >
        {{-- Wizard Header --}}
        <div class="mb-8 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 dark:bg-primary-950/50 border border-primary-200 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-300 mb-3">
                SEO Academy v2.7 &bull; Commercial Self-Hosted LMS
            </div>
            <h1 class="text-3xl font-extrabold text-text-primary tracking-tight">Customer Installation Wizard</h1>
            <p class="mt-2 text-sm text-text-secondary max-w-xl mx-auto">
                Follow these 8 automated steps to configure your independent single-academy instance, verify requirements, set up your database, and establish your administrator credentials.
            </p>
        </div>

        @if ($errors->any())
            <x-alert type="error" title="Installation Error" class="mb-6" :dismissible="false">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- Step 1: Welcome & Overview --}}
        <x-card
            title="Step 1 — Welcome &amp; Architecture"
            description="Important architecture and privacy rules for this installation."
            class="mb-6"
        >
            <div class="space-y-3 text-xs text-text-secondary">
                <p>
                    <strong>One Installation = Exactly One Academy.</strong> SEO Academy operates independently on your server without central SaaS telemetry, platform switching, or external databases.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="p-3 rounded-lg bg-surface-elevated border border-border-default">
                        <span class="font-bold text-text-primary block">Zero Multi-Tenancy</span>
                        All courses, students, support tickets, and files remain strictly isolated to your organization.
                    </div>
                    <div class="p-3 rounded-lg bg-surface-elevated border border-border-default">
                        <span class="font-bold text-text-primary block">Shared Hosting Ready</span>
                        Runs on standard PHP 8.3 FastCGI without Docker, Redis, or root permissions required.
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Step 2: Server Requirements Pre-flight --}}
        <x-card
            title="Step 2 — Server Requirements Pre-Flight"
            description="Verifying PHP runtime, mandatory extensions, and writable storage paths."
            class="mb-6"
        >
            <div class="space-y-4 text-xs">
                {{-- PHP Version --}}
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-surface-elevated border border-border-default">
                    <div>
                        <span class="font-semibold text-text-primary">PHP Version</span>
                        <span class="text-text-muted ml-2">(Requires &gt;= {{ $requirements['php']['required'] }})</span>
                    </div>
                    <div class="flex items-center gap-1.5 font-bold {{ $requirements['php']['ok'] ? 'text-emerald-600' : 'text-rose-600' }}">
                        <span>{{ $requirements['php']['current'] }}</span>
                        <span>{{ $requirements['php']['ok'] ? '✓' : '✕' }}</span>
                    </div>
                </div>

                {{-- Mandatory Extensions --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-text-primary block">PHP Extensions:</span>
                        <span class="text-[11px] text-text-muted">SQLite requires <code class="font-mono">pdo_sqlite</code>; MySQL requires <code class="font-mono">pdo_mysql</code></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach ($requirements['extensions'] as $ext)
                            <div class="p-2 rounded border flex items-center justify-between {{ $ext['ok'] ? 'bg-surface-elevated border-border-default' : 'bg-rose-50 border-rose-300 dark:bg-rose-950/40 text-rose-700' }}">
                                <div>
                                    <span class="font-mono text-[11px] block">{{ $ext['name'] }}</span>
                                    @if (($ext['category'] ?? '') === 'driver')
                                        <span class="text-[9px] text-text-muted block uppercase tracking-wider font-semibold">{{ $ext['driver'] ?? 'Driver' }}</span>
                                    @endif
                                </div>
                                <span class="font-bold {{ $ext['ok'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $ext['ok'] ? '✓' : '✕' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Writable Directories --}}
                <div>
                    <span class="font-semibold text-text-primary block mb-2">Required Writable Storage Directories:</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($requirements['directories'] as $label => $dir)
                            <div class="p-2 rounded border flex items-center justify-between {{ $dir['ok'] ? 'bg-surface-elevated border-border-default' : 'bg-rose-50 border-rose-300 dark:bg-rose-950/40 text-rose-700' }}">
                                <span class="font-mono text-[11px] truncate max-w-[200px]" title="{{ $dir['path'] }}">{{ $label }}</span>
                                <span class="font-bold {{ $dir['ok'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $dir['ok'] ? 'Writable ✓' : 'Unwritable ✕' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (! $requirements['compatible'])
                    <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 font-semibold text-xs">
                        ✕ Server requirements are not met. You cannot proceed until the missing extensions or directory permissions are resolved.
                    </div>
                @endif
            </div>
        </x-card>

        <form
            method="POST"
            action="{{ route('install.store') }}"
            @submit="installing = true"
        >
            @csrf
            <input type="hidden" name="driver" :value="driver" />

            {{-- Step 3: Database Engine Configuration --}}
            <x-card
                title="Step 3 — Database Engine Configuration"
                description="Choose between self-contained SQLite or high-performance MySQL InnoDB."
                class="mb-6"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {{-- SQLite Selection --}}
                    <div
                        class="p-4 rounded-xl border-2 cursor-pointer transition-all"
                        :class="driver === 'sqlite' ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-950/30' : 'border-border-default hover:border-border-hover'"
                        @click="selectDriver('sqlite')"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-text-primary">SQLite 3</span>
                            <span class="badge text-[10px] uppercase font-semibold" :class="driver === 'sqlite' ? 'badge-primary' : 'badge-neutral'">Zero Configuration</span>
                        </div>
                        <p class="text-xs text-text-secondary leading-relaxed">
                            Single defragmented file in private storage outside the webroot. Ideal for shared hosting or smaller academies.
                        </p>
                    </div>

                    {{-- MySQL Selection --}}
                    <div
                        class="p-4 rounded-xl border-2 cursor-pointer transition-all"
                        :class="driver === 'mysql' ? 'border-primary-600 bg-primary-50/50 dark:bg-primary-950/30' : 'border-border-default hover:border-border-hover'"
                        @click="selectDriver('mysql')"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-text-primary">MySQL 8.0+</span>
                            <span class="badge text-[10px] uppercase font-semibold" :class="driver === 'mysql' ? 'badge-primary' : 'badge-neutral'">High Concurrency</span>
                        </div>
                        <p class="text-xs text-text-secondary leading-relaxed">
                            Recommended for growing academies with multiple concurrent trainers and large student bodies. Requires InnoDB.
                        </p>
                    </div>
                </div>

                {{-- MySQL Credentials Form --}}
                <div x-show="driver === 'mysql'" x-cloak class="space-y-4 pt-4 border-t border-border-default">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="label text-xs font-semibold" for="mysql_host">MySQL Host *</label>
                            <input
                                id="mysql_host"
                                type="text"
                                class="input text-xs"
                                x-model="host"
                                placeholder="127.0.0.1 or localhost"
                                :required="driver === 'mysql'"
                            />
                        </div>
                        <div>
                            <label class="label text-xs font-semibold" for="mysql_port">Port *</label>
                            <input
                                id="mysql_port"
                                type="number"
                                class="input text-xs"
                                x-model="port"
                                placeholder="3306"
                                :required="driver === 'mysql'"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold" for="mysql_database">Database Name *</label>
                        <input
                            id="mysql_database"
                            type="text"
                            class="input text-xs"
                            x-model="database"
                            placeholder="seo_academy"
                            :required="driver === 'mysql'"
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label text-xs font-semibold" for="mysql_username">Username *</label>
                            <input
                                id="mysql_username"
                                type="text"
                                class="input text-xs"
                                x-model="username"
                                placeholder="db_user"
                                :required="driver === 'mysql'"
                            />
                        </div>
                        <div>
                            <label class="label text-xs font-semibold" for="mysql_password">Password</label>
                            <input
                                id="mysql_password"
                                type="password"
                                class="input text-xs"
                                x-model="password"
                                placeholder="Leave blank if none"
                            />
                        </div>
                    </div>
                </div>

                <input type="hidden" name="host" :value="host" />
                <input type="hidden" name="port" :value="port" />
                <input type="hidden" name="database" :value="database" />
                <input type="hidden" name="username" :value="username" />
                <input type="hidden" name="password" :value="password" />

                {{-- Test Connection Button --}}
                <div class="mt-4 pt-3 flex flex-col sm:flex-row items-center gap-3">
                    <button
                        type="button"
                        class="btn btn-secondary text-xs"
                        :disabled="testing"
                        @click="testConnection()"
                    >
                        <span x-show="!testing">Test Database Connection</span>
                        <span x-show="testing">Testing Connection...</span>
                    </button>

                    <template x-if="testResult">
                        <div
                            class="text-xs px-3 py-1.5 rounded-lg border font-semibold flex-1"
                            :class="testResult.ok ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/40 dark:text-rose-200'"
                            x-text="testResult.message"
                        ></div>
                    </template>
                </div>
            </x-card>

            {{-- Step 4: Academy Profile --}}
            <x-card
                title="Step 4 — Academy Profile"
                description="Provide the identity and primary contact information for your academy."
                class="mb-6"
            >
                <div class="space-y-4">
                    <div>
                        <label class="label text-xs font-semibold" for="academy_name">Academy Name *</label>
                        <input
                            id="academy_name"
                            name="academy_name"
                            type="text"
                            required
                            class="input text-xs"
                            value="{{ old('academy_name', 'SEO Academy') }}"
                            placeholder="e.g. Acme Marketing Academy"
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label text-xs font-semibold" for="academy_slug">Academy Slug</label>
                            <input
                                id="academy_slug"
                                name="academy_slug"
                                type="text"
                                class="input text-xs"
                                value="{{ old('academy_slug', 'seo-academy') }}"
                                placeholder="seo-academy"
                            />
                        </div>
                        <div>
                            <label class="label text-xs font-semibold" for="support_email">Official Support Email *</label>
                            <input
                                id="support_email"
                                name="support_email"
                                type="email"
                                required
                                class="input text-xs"
                                value="{{ old('support_email', 'support@example.com') }}"
                                placeholder="support@youracademy.com"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label text-xs font-semibold" for="timezone">Default Timezone</label>
                            <select id="timezone" name="timezone" class="input text-xs">
                                <option value="UTC" {{ old('timezone', 'UTC') === 'UTC' ? 'selected' : '' }}>UTC (Coordinated Universal Time)</option>
                                <option value="America/New_York" {{ old('timezone') === 'America/New_York' ? 'selected' : '' }}>America/New_York (EST/EDT)</option>
                                <option value="America/Los_Angeles" {{ old('timezone') === 'America/Los_Angeles' ? 'selected' : '' }}>America/Los_Angeles (PST/PDT)</option>
                                <option value="Europe/London" {{ old('timezone') === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT/BST)</option>
                                <option value="Europe/Berlin" {{ old('timezone') === 'Europe/Berlin' ? 'selected' : '' }}>Europe/Berlin (CET/CEST)</option>
                                <option value="Asia/Kolkata" {{ old('timezone') === 'Asia/Kolkata' ? 'selected' : '' }}>Asia/Kolkata (IST)</option>
                                <option value="Asia/Singapore" {{ old('timezone') === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT)</option>
                            </select>
                        </div>
                        <div>
                            <label class="label text-xs font-semibold" for="locale">System Language</label>
                            <select id="locale" name="locale" class="input text-xs">
                                <option value="en" selected>English (en)</option>
                                <option value="es">Español (es)</option>
                                <option value="fr">Français (fr)</option>
                                <option value="de">Deutsch (de)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- Step 5: Initial Administrator Account --}}
            <x-card
                title="Step 5 — Initial Administrator Account"
                description="Define the credentials for your first local administrator. No default passwords are used."
                class="mb-6"
            >
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label text-xs font-semibold" for="admin_name">Administrator Full Name *</label>
                            <input
                                id="admin_name"
                                name="admin_name"
                                type="text"
                                required
                                class="input text-xs"
                                value="{{ old('admin_name', 'System Administrator') }}"
                                placeholder="Jane Doe"
                            />
                        </div>
                        <div>
                            <label class="label text-xs font-semibold" for="admin_email">Administrator Email *</label>
                            <input
                                id="admin_email"
                                name="admin_email"
                                type="email"
                                required
                                class="input text-xs"
                                value="{{ old('admin_email', 'admin@example.com') }}"
                                placeholder="admin@youracademy.com"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label text-xs font-semibold" for="admin_password">Admin Password * (Min 8 chars)</label>
                            <input
                                id="admin_password"
                                name="admin_password"
                                type="password"
                                required
                                class="input text-xs"
                                placeholder="••••••••••••"
                            />
                        </div>
                        <div>
                            <label class="label text-xs font-semibold" for="admin_password_confirmation">Confirm Password *</label>
                            <input
                                id="admin_password_confirmation"
                                name="admin_password_confirmation"
                                type="password"
                                required
                                class="input text-xs"
                                placeholder="••••••••••••"
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- Step 6: System Defaults Confirmation --}}
            <x-card
                title="Step 6 — System Defaults &amp; Shared Hosting Profiles"
                description="Automated initial configuration applied during installation."
                class="mb-6"
            >
                <div class="space-y-2 text-xs text-text-secondary">
                    <div class="flex items-center gap-2 text-emerald-600 font-semibold">
                        <span>✓</span>
                        <span>Spatie role structure initialization (<code class="font-mono">admin</code>, <code class="font-mono">trainer</code>, <code class="font-mono">support_staff</code>, <code class="font-mono">student</code>).</span>
                    </div>
                    <div class="flex items-center gap-2 text-emerald-600 font-semibold">
                        <span>✓</span>
                        <span>Database-backed queues &amp; sessions configured for shared hosting compatibility.</span>
                    </div>
                    <div class="flex items-center gap-2 text-emerald-600 font-semibold">
                        <span>✓</span>
                        <span>Zero demo courses or sample data created (clean production state).</span>
                    </div>
                </div>
            </x-card>

            {{-- Step 7 & 8: Installation Execution & Submit --}}
            <div class="pt-2">
                <button
                    type="submit"
                    class="btn btn-primary w-full py-3 text-sm font-bold flex items-center justify-center gap-2 shadow-lg"
                    :disabled="installing || {{ ! $requirements['compatible'] ? 'true' : 'false' }}"
                >
                    <span x-show="!installing">Execute Complete Installation (Steps 7 &amp; 8) &rarr;</span>
                    <span x-show="installing" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Installing Migrations, Academy Profile &amp; Admin...
                    </span>
                </button>
            </div>
        </form>
    </div>
@endcomponent
