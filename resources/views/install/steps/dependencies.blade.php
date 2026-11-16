@component('install.layout', [
    'title' => 'Step 2 — Composer & Application Dependencies',
    'stepNumber' => 2,
    'totalSteps' => 9,
    'state' => $state,
])
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-text-primary">Production Packages &amp; Autoloader</h2>
            <p class="text-xs text-text-secondary mt-1">
                SEO Academy validates that all core framework, reactive component, PDF generation, and authorization dependencies are installed and compatible.
            </p>
        </div>

        {{-- Overall Status --}}
        <div class="p-4 rounded-lg flex items-center justify-between {{ $dependencies['compatible'] ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400' }}">
            <div class="flex items-center gap-3">
                @if ($dependencies['compatible'])
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-emerald-300">Dependencies Verified</span>
                        <span class="text-xs text-emerald-400/80">All mandatory PHP packages and autoloaders are present and compatible.</span>
                    </div>
                @else
                    <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-rose-300">Missing Packages Detected</span>
                        <span class="text-xs text-rose-400/80">One or more mandatory PHP packages are not installed on this server.</span>
                    </div>
                @endif
            </div>
            <span class="text-xs font-mono font-bold px-2.5 py-1 rounded bg-surface-elevated border border-border-default text-text-primary">
                {{ count($dependencies['packages']) }} Core Packages
            </span>
        </div>

        {{-- Autoloader Files --}}
        <div class="border border-border-default rounded-lg p-4 bg-surface-elevated/40">
            <h3 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-3">Composer Infrastructure</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span class="font-mono">composer.json</span>
                    @if ($dependencies['has_composer_json'])
                        <span class="text-emerald-500 font-bold">Present</span>
                    @else
                        <span class="text-rose-500 font-bold">Missing</span>
                    @endif
                </div>
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span class="font-mono">vendor/autoload.php</span>
                    @if ($dependencies['has_autoload'])
                        <span class="text-emerald-500 font-bold">Present</span>
                    @else
                        <span class="text-rose-500 font-bold">Missing</span>
                    @endif
                </div>
                <div class="p-3 rounded bg-surface-card border border-border-default flex items-center justify-between">
                    <span class="font-mono">installed.json</span>
                    @if ($dependencies['has_installed_json'])
                        <span class="text-emerald-500 font-bold">Present</span>
                    @else
                        <span class="text-rose-500 font-bold">Missing</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Core Packages Table --}}
        <div class="border border-border-default rounded-lg overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-surface-elevated/70 border-b border-border-default text-text-muted">
                    <tr>
                        <th class="py-2.5 px-4 font-semibold">Core Package</th>
                        <th class="py-2.5 px-4 font-semibold">Constraint</th>
                        <th class="py-2.5 px-4 font-semibold">Installed Version</th>
                        <th class="py-2.5 px-4 font-semibold text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach ($dependencies['packages'] as $name => $pkg)
                        <tr class="hover:bg-surface-elevated/20 transition-colors">
                            <td class="py-2.5 px-4 font-mono font-medium text-text-primary">{{ $name }}</td>
                            <td class="py-2.5 px-4 font-mono text-text-secondary">{{ $pkg['required'] }}</td>
                            <td class="py-2.5 px-4 font-mono text-text-secondary">{{ $pkg['installed'] ?? 'Not Installed' }}</td>
                            <td class="py-2.5 px-4 text-right">
                                @if ($pkg['ok'])
                                    <span class="inline-flex items-center gap-1 text-emerald-500 font-bold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        Verified
                                    </span>
                                @else
                                    <span class="text-rose-500 font-bold">Missing</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Guidance for Shared Hosting --}}
        <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default text-xs text-text-secondary space-y-1">
            <strong class="text-text-primary block">Deployment Note for Shared Hosting:</strong>
            <p>
                SEO Academy is designed to run in zero-terminal hosting environments without requiring Node.js or permanent command line workers. When deploying via ZIP, all production packages are pre-compiled and bundled inside the release archive.
            </p>
        </div>

        {{-- Form Actions --}}
        <form method="POST" action="{{ route('install.step.dependencies.store') }}" class="pt-4 border-t border-border-default flex items-center justify-between">
            @csrf
            <a href="{{ route('install.step.requirements') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                &larr; Back to Requirements
            </a>

            <button
                type="submit"
                class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm"
                {{ ! $dependencies['compatible'] ? 'disabled' : '' }}
            >
                Continue to Environment Setup &rarr;
            </button>
        </form>
    </div>
@endcomponent
