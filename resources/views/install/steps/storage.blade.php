@component('install.layout', [
    'title' => 'Step 6 — Filesystem & Storage Setup',
    'stepNumber' => 6,
    'totalSteps' => 9,
    'state' => $state,
])
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-text-primary">Private Filesystem &amp; Cache Storage</h2>
            <p class="text-xs text-text-secondary mt-1">
                Verify that all application media, backups, cached views, and session directories are created with real write access.
            </p>
        </div>

        {{-- Overall Compatibility Badge --}}
        <div class="p-4 rounded-lg flex items-center justify-between {{ $storage['compatible'] ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400' }}">
            <div class="flex items-center gap-3">
                @if ($storage['compatible'])
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-emerald-300">Storage Validated</span>
                        <span class="text-xs text-emerald-400/80">All private storage and cache directories are created and writable.</span>
                    </div>
                @else
                    <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-rose-300">Permission Check Failed</span>
                        <span class="text-xs text-rose-400/80">One or more directories could not be written to by PHP.</span>
                    </div>
                @endif
            </div>
            <span class="text-xs font-mono font-bold px-2 py-1 rounded bg-surface-elevated border border-border-default text-text-primary">
                {{ count($storage['directories']) }} Directories
            </span>
        </div>

        {{-- Directories Grid --}}
        <div class="border border-border-default rounded-lg p-4 bg-surface-elevated/40">
            <h3 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-3">Directory Write Permissions</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                @foreach ($storage['directories'] as $label => $d)
                    <div class="p-2.5 rounded bg-surface-card border border-border-default flex items-center justify-between">
                        <span class="font-mono text-text-secondary">{{ $label }}</span>
                        @if ($d['ok'])
                            <span class="text-emerald-500 font-bold flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                Writable
                            </span>
                        @else
                            <span class="text-rose-500 font-bold">Unwritable</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Security Notice --}}
        <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default text-xs text-text-secondary space-y-1">
            <strong class="text-text-primary block">Private Storage Protection:</strong>
            <p>
                Student assignment submissions, course video files, and system backup archives are stored exclusively under <code>storage/app/private/</code>, completely blocked from direct public URL access.
            </p>
        </div>

        {{-- Form Actions --}}
        <form method="POST" action="{{ route('install.step.storage.store') }}" class="pt-4 border-t border-border-default flex items-center justify-between">
            @csrf
            <a href="{{ route('install.step.migrations') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                &larr; Back to Migrations
            </a>

            <button
                type="submit"
                class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm"
                {{ ! $storage['compatible'] ? 'disabled' : '' }}
            >
                Continue to Academy Setup &rarr;
            </button>
        </form>
    </div>
@endcomponent
