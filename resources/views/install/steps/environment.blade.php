@component('install.layout', [
    'title' => 'Step 3 — Automatic Environment Setup',
    'stepNumber' => 3,
    'totalSteps' => 9,
    'state' => $state,
])
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-text-primary">Environment File &amp; Encryption Key</h2>
            <p class="text-xs text-text-secondary mt-1">
                The installer automatically generated and configured your private <code>.env</code> file with a cryptographically secure 256-bit AES encryption key.
            </p>
        </div>

        {{-- Status Box --}}
        <div class="p-4 rounded-lg flex items-center justify-between {{ $envSetup['compatible'] ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400' }}">
            <div class="flex items-center gap-3">
                @if ($envSetup['compatible'])
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-emerald-300">Environment Ready</span>
                        <span class="text-xs text-emerald-400/80">{{ $envSetup['message'] }}</span>
                    </div>
                @else
                    <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold text-sm block text-rose-300">Configuration Required</span>
                        <span class="text-xs text-rose-400/80">Unable to generate or verify .env and APP_KEY.</span>
                    </div>
                @endif
            </div>
            <span class="text-xs font-mono font-bold px-2.5 py-1 rounded bg-surface-elevated border border-border-default text-text-primary">
                AES-256-CBC
            </span>
        </div>

        {{-- Verification Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">1. Environment File</span>
                <span class="font-bold text-sm {{ $envSetup['has_env'] ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ $envSetup['has_env'] ? 'Created & Active' : 'Missing' }}
                </span>
                <p class="text-text-secondary text-[11px]">
                    Auto-cloned from <code>.env.example</code> with safe local defaults.
                </p>
            </div>

            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">2. Application Key</span>
                <span class="font-bold text-sm {{ $envSetup['has_key'] ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ $envSetup['has_key'] ? 'Generated (32-byte)' : 'Unset' }}
                </span>
                <p class="text-text-secondary text-[11px]">
                    Secures user passwords, session tokens, and encrypted certificate verification codes.
                </p>
            </div>

            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default space-y-1">
                <span class="text-text-muted font-semibold block">3. Web Protection</span>
                <span class="font-bold text-sm {{ $envSetup['is_protected'] ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ $envSetup['is_protected'] ? 'Secure (Non-Public)' : 'Exposed' }}
                </span>
                <p class="text-text-secondary text-[11px]">
                    Stored outside the public web root to prevent unauthorized credential downloads.
                </p>
            </div>
        </div>

        {{-- Security Guarantee --}}
        <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default text-xs text-text-secondary">
            <strong class="text-text-primary block mb-1">Security Guarantee:</strong>
            <p>
                In compliance with commercial security standards, raw application keys and secret credentials are never exposed through browser output or diagnostic APIs.
            </p>
        </div>

        {{-- Form Actions --}}
        <form method="POST" action="{{ route('install.step.environment.store') }}" class="pt-4 border-t border-border-default flex items-center justify-between">
            @csrf
            <a href="{{ route('install.step.dependencies') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                &larr; Back to Dependencies
            </a>

            <button
                type="submit"
                class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm"
                {{ ! $envSetup['compatible'] ? 'disabled' : '' }}
            >
                Continue to Database Setup &rarr;
            </button>
        </form>
    </div>
@endcomponent
