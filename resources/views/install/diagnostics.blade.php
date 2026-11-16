@component('layouts.guest', ['title' => 'System Diagnostics'])
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4 border-b border-border-default pb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 dark:bg-primary-950/50 border border-primary-200 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-300 mb-2">
                    System Health &amp; Diagnostics
                </div>
                <h1 class="text-2xl font-extrabold text-text-primary tracking-tight">Installation System Diagnostics</h1>
                <p class="text-xs text-text-secondary mt-1">
                    Environment parameters, database connectivity, and runtime metrics for this instance.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('install.diagnostics.download') }}" class="btn btn-outline btn-sm text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Sanitized Report (JSON)
                </a>

                @if ($isInstalled)
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm text-xs font-semibold">
                        Sign In &rarr;
                    </a>
                @else
                    <a href="{{ route('install.index') }}" class="btn btn-primary btn-sm text-xs font-semibold">
                        &larr; Return to Wizard
                    </a>
                @endif
            </div>
        </div>

        {{-- Top Diagnostics Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div class="p-4 rounded-xl bg-surface-card border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">Application Status</span>
                <span class="font-bold text-sm {{ $diagnostics['application']['installed'] ? 'text-emerald-500' : 'text-amber-500' }}">
                    {{ $diagnostics['application']['installed'] ? 'Installed & Locked' : 'Installing / Ready' }}
                </span>
                <span class="text-[11px] text-text-secondary block">{{ $diagnostics['application']['version'] }}</span>
            </div>

            <div class="p-4 rounded-xl bg-surface-card border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">PHP Environment</span>
                <span class="font-bold text-sm text-text-primary font-mono">PHP {{ $diagnostics['php']['version'] }}</span>
                <span class="text-[11px] text-text-secondary block">SAPI: {{ $diagnostics['php']['sapi'] }} ({{ $diagnostics['php']['os'] }})</span>
            </div>

            <div class="p-4 rounded-xl bg-surface-card border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">Database Engine</span>
                <span class="font-bold text-sm font-mono uppercase {{ $diagnostics['database']['status'] === 'connected' ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ $diagnostics['database']['driver'] }} ({{ $diagnostics['database']['status'] }})
                </span>
                <span class="text-[11px] text-text-secondary block">Version: {{ $diagnostics['database']['version'] }}</span>
            </div>

            <div class="p-4 rounded-xl bg-surface-card border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">Migration Progress</span>
                <span class="font-bold text-sm font-mono text-text-primary">
                    {{ $diagnostics['database']['migrations_executed'] }} / {{ $diagnostics['database']['known_migrations'] }}
                </span>
                <span class="text-[11px] text-text-secondary block">{{ $diagnostics['database']['tables'] }} Tables Present</span>
            </div>
        </div>

        {{-- PHP Extensions Matrix --}}
        <div class="bg-surface-card border border-border-default rounded-xl p-5 space-y-3">
            <h2 class="text-xs font-bold text-text-muted uppercase tracking-wider">Loaded PHP Extensions</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2 text-xs">
                @foreach ($diagnostics['extensions'] as $name => $loaded)
                    <div class="p-2 rounded bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                        <span class="font-mono text-text-secondary">{{ $name }}</span>
                        @if ($loaded)
                            <span class="text-emerald-500 font-bold text-[11px]">Loaded</span>
                        @else
                            <span class="text-rose-500 font-bold text-[11px]">Disabled</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Filesystem & Storage --}}
        <div class="bg-surface-card border border-border-default rounded-xl p-5 space-y-3">
            <h2 class="text-xs font-bold text-text-muted uppercase tracking-wider">Storage &amp; Cache Directory Layout</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                @foreach ($diagnostics['storage']['directories'] as $label => $d)
                    <div class="p-2.5 rounded bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                        <span class="font-mono text-text-secondary">{{ $label }}</span>
                        @if ($d['ok'])
                            <span class="text-emerald-500 font-bold flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Writable
                            </span>
                        @else
                            <span class="text-rose-500 font-bold">Unwritable</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Dependencies Summary --}}
        <div class="bg-surface-card border border-border-default rounded-xl p-5 space-y-3">
            <h2 class="text-xs font-bold text-text-muted uppercase tracking-wider">Core Package Integrity</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                @foreach ($diagnostics['dependencies']['packages'] as $name => $pkg)
                    <div class="p-2.5 rounded bg-surface-elevated/40 border border-border-default flex items-center justify-between">
                        <span class="font-mono text-text-primary">{{ $name }}</span>
                        <span class="font-mono text-text-secondary font-semibold">{{ $pkg['installed'] ?? 'Missing' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Security Guarantee --}}
        <div class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default text-xs text-text-secondary">
            <strong class="text-text-primary block mb-1">Sanitization Notice:</strong>
            <p>
                This diagnostics interface and its downloadable report are completely stripped of private secrets. Database passwords, session keys, encryption keys, and private customer paths are masked to ensure reports can be safely shared with academy support or technical advisors.
            </p>
        </div>
    </div>
@endcomponent
