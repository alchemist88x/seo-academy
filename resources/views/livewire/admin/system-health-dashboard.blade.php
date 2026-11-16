<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">System Health &amp; Diagnostics</h1>
            <p class="text-sm text-text-muted mt-1">
                Real-time operational status of application runtime, database, storage volumes, queue workers, and cron scheduler.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Diagnostics Active</span>
            </span>
        </div>
    </div>

    {{-- Grid Layout --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Application Status --}}
        <div class="card p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border-default">
                <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span>Application Core</span>
                </h2>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-surface-elevated text-text-muted">
                    v{{ $appHealth['app_version'] }}
                </span>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-text-muted">Application Name:</span>
                    <span class="font-semibold text-text-primary">{{ $appHealth['app_name'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Environment:</span>
                    <span class="font-mono px-1.5 py-0.2 rounded bg-surface-elevated text-text-primary">{{ $appHealth['environment'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Laravel Framework:</span>
                    <span class="font-mono text-text-primary">v{{ $appHealth['laravel_version'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">PHP Engine:</span>
                    <span class="font-mono text-text-primary">v{{ $appHealth['php_version'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Debug Mode:</span>
                    <span class="font-semibold {{ $appHealth['debug_mode'] ? 'text-amber-600' : 'text-emerald-600' }}">
                        {{ $appHealth['debug_mode'] ? 'Enabled (Warning in Prod)' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Maintenance Mode:</span>
                    <span class="font-semibold {{ $appHealth['maintenance_mode'] ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $appHealth['maintenance_mode'] ? 'Down for Maintenance' : 'Active (Live)' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Database Status --}}
        <div class="card p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border-default">
                <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                    <span>Database Engine</span>
                </h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $dbHealth['connected'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' }}">
                    {{ $dbHealth['connected'] ? 'Connected' : 'Offline' }}
                </span>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-text-muted">Configured Driver:</span>
                    <span class="font-mono font-semibold text-text-primary uppercase">{{ $dbHealth['driver'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Database Identifier:</span>
                    <span class="font-mono text-text-primary truncate max-w-[150px]">{{ $dbHealth['database_name'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Query Latency:</span>
                    <span class="font-mono text-emerald-600">{{ $dbHealth['latency_ms'] !== null ? $dbHealth['latency_ms'] . ' ms' : 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Migrations Executed:</span>
                    <span class="font-semibold text-text-primary">{{ $dbHealth['migrations_ran'] }} migrations</span>
                </div>
                @if ($dbHealth['error'])
                    <div class="p-2 rounded bg-rose-50 text-rose-700 text-[11px] font-mono break-all">
                        {{ $dbHealth['error'] }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Storage & Volumes --}}
        <div class="card p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border-default">
                <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <span>Storage Volumes</span>
                </h2>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-surface-elevated text-text-muted">
                    Disk: {{ $storageHealth['default_disk'] }}
                </span>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="flex justify-between items-center">
                    <span class="text-text-muted">Private Storage Writable:</span>
                    <span class="font-semibold {{ $storageHealth['storage_writable'] ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $storageHealth['storage_writable'] ? '✓ Writable' : '✕ Read-Only' }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-text-muted">Media Disk Writable:</span>
                    <span class="font-semibold {{ $storageHealth['media_writable'] ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $storageHealth['media_writable'] ? '✓ Writable' : '✕ Read-Only' }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-text-muted">Backups Disk Writable:</span>
                    <span class="font-semibold {{ $storageHealth['backups_writable'] ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $storageHealth['backups_writable'] ? '✓ Writable' : '✕ Read-Only' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Managed Uploads:</span>
                    <span class="font-semibold text-text-primary">{{ $fileService->formatBytes($storageHealth['total_managed_bytes']) }} ({{ $storageHealth['total_managed_files'] }} files)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Filesystem Free Space:</span>
                    <span class="font-mono text-emerald-600">{{ $storageHealth['disk_free_bytes'] ? $fileService->formatBytes($storageHealth['disk_free_bytes']) : 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Queue Operations --}}
        <div class="card p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border-default">
                <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                    </svg>
                    <span>Queue Subsystem</span>
                </h2>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-surface-elevated text-text-muted">
                    Driver: {{ $queueHealth['driver'] }}
                </span>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="text-text-secondary leading-relaxed">
                    {{ $queueHealth['status_label'] }}
                </div>

                @if ($queueHealth['pending_jobs'] !== null)
                    <div class="flex justify-between pt-2 border-t border-border-default">
                        <span class="text-text-muted">Pending Database Jobs:</span>
                        <span class="font-mono font-bold text-text-primary">{{ $queueHealth['pending_jobs'] }}</span>
                    </div>
                @endif

                @if ($queueHealth['failed_jobs'] !== null)
                    <div class="flex justify-between">
                        <span class="text-text-muted">Failed Jobs:</span>
                        <span class="font-mono font-bold {{ $queueHealth['failed_jobs'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $queueHealth['failed_jobs'] }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Scheduler / Cron Subsystem --}}
        <div class="card p-5 space-y-4 md:col-span-2">
            <div class="flex items-center justify-between pb-3 border-b border-border-default">
                <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Scheduler &amp; Crontab</span>
                </h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $schedulerHealth['is_running_recently'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' }}">
                    {{ $schedulerHealth['is_running_recently'] ? 'Active Cron' : 'Cron Required' }}
                </span>
            </div>

            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-text-muted">Scheduler Status:</span>
                    <span class="font-semibold text-text-primary">{{ $schedulerHealth['status'] }}</span>
                </div>

                @if ($schedulerHealth['last_run_at'])
                    <div class="flex items-center justify-between">
                        <span class="text-text-muted">Last Recorded Execution:</span>
                        <span class="font-mono text-text-primary">{{ $schedulerHealth['last_run_at']->format('Y-m-d H:i:s') }} ({{ $schedulerHealth['last_run_at']->diffForHumans() }})</span>
                    </div>
                @endif

                <div class="pt-2 border-t border-border-default">
                    <span class="text-text-muted block mb-1">Recommended Shared Hosting Crontab Entry:</span>
                    <div class="p-2.5 rounded-lg bg-slate-900 text-emerald-400 font-mono text-[11px] overflow-x-auto select-all">
                        {{ $schedulerHealth['cron_command'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PHP Requirements & Extensions Checklist --}}
    <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-border-default">
            <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>PHP Extensions &amp; Runtime Configuration</span>
            </h2>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $phpHealth['all_extensions_passed'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' }}">
                {{ $phpHealth['all_extensions_passed'] ? 'All Required Passed' : 'Missing Extensions' }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach ($phpHealth['extensions'] as $extKey => $ext)
                <div class="p-3 rounded-lg border border-border-default flex items-center justify-between text-xs">
                    <div>
                        <div class="font-semibold text-text-primary">{{ $extKey }}</div>
                        <div class="text-[10px] text-text-muted truncate max-w-[100px]">{{ $ext['name'] }}</div>
                    </div>
                    @if ($ext['loaded'])
                        <span class="text-emerald-500 font-bold text-base">✓</span>
                    @else
                        <span class="text-rose-500 font-bold text-base">✕</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-border-default text-xs">
            <div>
                <span class="text-text-muted block">Memory Limit:</span>
                <span class="font-mono font-semibold text-text-primary">{{ $phpHealth['memory_limit'] }}</span>
            </div>
            <div>
                <span class="text-text-muted block">Max Execution Time:</span>
                <span class="font-mono font-semibold text-text-primary">{{ $phpHealth['max_execution_time'] }}</span>
            </div>
            <div>
                <span class="text-text-muted block">Upload Max Filesize:</span>
                <span class="font-mono font-semibold text-text-primary">{{ $phpHealth['upload_max_filesize'] }}</span>
            </div>
            <div>
                <span class="text-text-muted block">Post Max Size:</span>
                <span class="font-mono font-semibold text-text-primary">{{ $phpHealth['post_max_size'] }}</span>
            </div>
        </div>
    </div>
</div>

