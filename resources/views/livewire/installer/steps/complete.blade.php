<div class="space-y-6 text-center py-4">
    {{-- Success Hero Icon --}}
    <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-500 mx-auto flex items-center justify-center border-2 border-emerald-500/20 shadow-lg shadow-emerald-500/10">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
    </div>

    <div>
        <h2 class="text-2xl font-extrabold text-text-primary tracking-tight">Installation Successful!</h2>
        <p class="text-xs text-text-secondary mt-1.5 max-w-md mx-auto">
            SEO Academy has been completely installed, configured, and secured on your server.
        </p>
    </div>

    {{-- Installation Summary Card --}}
    <div class="max-w-md mx-auto p-5 rounded-2xl bg-surface-elevated/40 border border-border-default text-left space-y-3.5 text-xs">
        <div class="flex items-center justify-between pb-2 border-b border-border-default/60">
            <span class="text-text-muted">Academy Name</span>
            <span class="font-bold text-text-primary">{{ $academyName }}</span>
        </div>

        <div class="flex items-center justify-between pb-2 border-b border-border-default/60">
            <span class="text-text-muted">Database Engine</span>
            <span class="font-mono font-bold text-primary-600 dark:text-primary-400">{{ strtoupper($driver) }}</span>
        </div>

        <div class="flex items-center justify-between pb-2 border-b border-border-default/60">
            <span class="text-text-muted">Primary Administrator</span>
            <span class="font-mono font-bold text-text-primary">{{ $adminEmail }}</span>
        </div>

        <div class="flex items-center justify-between">
            <span class="text-text-muted">Security Lockdown</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Sealed (installed.lock)
            </span>
        </div>
    </div>

    {{-- Security Alert --}}
    <div class="max-w-md mx-auto p-3.5 rounded-xl bg-surface-elevated/30 border border-border-default text-[11px] text-text-muted text-left">
        The web installer is now permanently locked. For security reasons, you cannot re-run the wizard unless the server lockfile is manually removed by an authorized server administrator.
    </div>

    {{-- Sign In CTA --}}
    <div class="pt-2">
        <a
            href="{{ route('login') }}"
            class="btn btn-primary px-8 py-3 text-xs font-bold rounded-lg shadow-md inline-flex items-center gap-2 cursor-pointer"
        >
            <span>Go to Sign In &rarr;</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>
</div>
