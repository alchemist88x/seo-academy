@component('install.layout', [
    'title' => 'Step 9 — Pre-Flight Finalization & Lock',
    'stepNumber' => 9,
    'totalSteps' => 9,
    'state' => $state,
])
    <div
        class="space-y-6"
        x-data="{
            finalizing: false,
            startFinalization() {
                this.finalizing = true;
            }
        }"
    >
        <div>
            <h2 class="text-lg font-bold text-text-primary">System Verification &amp; Security Lock</h2>
            <p class="text-xs text-text-secondary mt-1">
                Review your installation checklist. Finalizing will establish the installation lock and enable the live academy login portal.
            </p>
        </div>

        {{-- Verification Checklist --}}
        <div class="border border-border-default rounded-lg divide-y divide-border-default text-xs bg-surface-elevated/30">
            <div class="p-3.5 flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">1. Environment &amp; Encryption Key</span>
                    <span class="text-text-muted text-[11px]">.env initialized with 256-bit AES cryptographic key</span>
                </div>
                <span class="text-emerald-500 font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Verified
                </span>
            </div>

            <div class="p-3.5 flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">2. Production Dependencies</span>
                    <span class="text-text-muted text-[11px]">All required framework, Livewire, and Dompdf packages loaded</span>
                </div>
                <span class="text-emerald-500 font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Verified
                </span>
            </div>

            <div class="p-3.5 flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">3. Database Schema &amp; Security Roles</span>
                    <span class="text-text-muted text-[11px]">{{ $diagnostics['database']['driver'] }} connection verified &bull; {{ $diagnostics['database']['migrations_executed'] }} migrations executed</span>
                </div>
                <span class="text-emerald-500 font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Active
                </span>
            </div>

            <div class="p-3.5 flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">4. Private Storage &amp; Cache</span>
                    <span class="text-text-muted text-[11px]">Writable media, session, backup, and view cache layout</span>
                </div>
                <span class="text-emerald-500 font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Ready
                </span>
            </div>

            <div class="p-3.5 flex items-center justify-between">
                <div>
                    <span class="font-bold text-text-primary block">5. Organization &amp; Super Administrator</span>
                    <span class="text-text-muted text-[11px]">Academy: {{ $state['academy_name'] ?? 'SEO Academy' }} &bull; Admin: {{ $state['admin_email'] ?? 'Administrator' }}</span>
                </div>
                <span class="text-emerald-500 font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Ready
                </span>
            </div>
        </div>

        {{-- Permanent Lock Notice --}}
        <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs space-y-1">
            <strong class="font-bold block text-sm">Security Lock Notice:</strong>
            <p>
                Completing this step will write an immutable <code>installed.lock</code> file to private storage. Once locked, installer endpoints are permanently blocked and all future administrative access requires signing in as an administrator.
            </p>
        </div>

        {{-- Finalization Action --}}
        <form method="POST" action="{{ route('install.step.finalization.store') }}" @submit="startFinalization()" class="pt-4 border-t border-border-default flex items-center justify-between">
            @csrf
            <a href="{{ route('install.step.admin') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                &larr; Back to Administrator Account
            </a>

            <button
                type="submit"
                :disabled="finalizing"
                class="btn btn-primary px-8 py-3 text-xs font-bold rounded-lg shadow-md shadow-primary-500/20"
            >
                <span x-show="!finalizing">Finalize Installation &amp; Lock System &rarr;</span>
                <span x-show="finalizing" style="display: none;">Securing &amp; Finalizing...</span>
            </button>
        </form>
    </div>
@endcomponent
