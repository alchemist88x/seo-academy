@component('install.layout', [
    'title' => 'Step 1 — Server & PHP Requirements',
    'stepNumber' => 1,
    'totalSteps' => 9,
    'state' => $state,
])
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-text-primary">System Environment Verification</h2>
            <p class="text-xs text-text-secondary mt-1">
                SEO Academy inspects your server runtime to ensure all mandatory PHP extensions, core storage directories, and configuration settings meet self-hosted LMS requirements.
            </p>
        </div>

        {{-- Overall Compatibility Badge --}}
        <div class="p-4 rounded-lg flex items-center justify-between {{ $requirements['compatible'] ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400' }}">
            <div class="flex items-center gap-3">
                @if ($requirements['compatible'])
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-emerald-300">Environment Ready</span>
                        <span class="text-xs text-emerald-400/80">Your server satisfies all mandatory execution prerequisites.</span>
                    </div>
                @else
                    <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-rose-300">Action Required</span>
                        <span class="text-xs text-rose-400/80">One or more mandatory PHP extensions or storage directory permissions require attention.</span>
                    </div>
                @endif
            </div>
            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-surface-elevated border border-border-default text-text-primary">
                PHP {{ $requirements['php']['current'] }}
            </span>
        </div>

        {{-- 1. PHP Runtime & SAPI --}}
        <div class="border border-border-default rounded-lg p-4 bg-surface-elevated/40">
            <h3 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-3">1. PHP Runtime &amp; Server Limits</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span>PHP Version (>= 8.3)</span>
                    <span class="font-bold {{ $requirements['php']['ok'] ? 'text-emerald-500' : 'text-rose-500' }}">
                        {{ $requirements['php']['current'] }}
                    </span>
                </div>
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span>Memory Limit</span>
                    <span class="font-bold text-text-primary">{{ $requirements['server_limits']['memory_limit'] }}</span>
                </div>
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span>Max Execution Time</span>
                    <span class="font-bold text-text-primary">{{ $requirements['server_limits']['max_execution_time'] }}s</span>
                </div>
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span>Upload Max Filesize</span>
                    <span class="font-bold text-text-primary">{{ $requirements['server_limits']['upload_max_filesize'] }}</span>
                </div>
            </div>
        </div>

        {{-- 2. Mandatory PHP Extensions --}}
        <div class="border border-border-default rounded-lg p-4 bg-surface-elevated/40">
            <h3 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-3">2. Core &amp; Database PHP Extensions</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 text-xs">
                @foreach ($requirements['extensions'] as $name => $ext)
                    <div class="p-2.5 rounded bg-surface-card border border-border-default flex items-center justify-between">
                        <span class="font-mono text-text-secondary">{{ $name }}</span>
                        @if ($ext['loaded'])
                            <span class="text-emerald-500 font-bold flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                OK
                            </span>
                        @else
                            <span class="{{ $ext['required'] ? 'text-rose-500' : 'text-text-muted' }} font-bold">
                                {{ $ext['required'] ? 'Missing' : 'Optional' }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 3. Writable Storage Directories --}}
        <div class="border border-border-default rounded-lg p-4 bg-surface-elevated/40">
            <h3 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-3">3. Filesystem Write Access</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                @foreach ($requirements['directories'] as $label => $dir)
                    <div class="p-2.5 rounded bg-surface-card border border-border-default flex items-center justify-between">
                        <span class="font-mono text-text-secondary">{{ $label }}</span>
                        @if ($dir['ok'])
                            <span class="text-emerald-500 font-bold flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                Writable
                            </span>
                        @else
                            <span class="text-rose-500 font-bold">Not Writable</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Corrective Actions / Warnings if any --}}
        @if (! empty($requirements['corrective_actions']))
            <div class="p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs space-y-1.5">
                <span class="font-bold block text-sm">Corrective Actions Needed:</span>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($requirements['corrective_actions'] as $action)
                        <li>{{ $action }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form Actions --}}
        <form method="POST" action="{{ route('install.step.requirements.store') }}" class="pt-4 border-t border-border-default flex items-center justify-between">
            @csrf
            <input type="hidden" name="driver" value="{{ $driver }}">

            <a href="{{ route('install.step.requirements', ['driver' => $driver]) }}" class="text-xs text-text-muted hover:text-text-primary underline">
                Refresh Checks
            </a>

            <button
                type="submit"
                class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm"
                {{ ! $requirements['compatible'] ? 'disabled' : '' }}
            >
                Continue to Dependencies &rarr;
            </button>
        </form>
    </div>
@endcomponent
