@component('layouts.guest', ['title' => 'Installation Complete'])
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mb-6">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="text-3xl font-extrabold text-text-primary">Installation Successful!</h1>
        <p class="mt-2 text-sm text-text-secondary">
            <strong>{{ $academyName }}</strong> has been initialized and is ready for operational use.
        </p>

        <div class="card mt-8 p-6 text-left space-y-4">
            <div>
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Academy Name</span>
                <p class="text-sm font-bold text-text-primary mt-0.5">{{ $academyName }}</p>
            </div>

            <div>
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Administrator Account</span>
                <p class="text-sm font-mono font-semibold text-text-primary mt-0.5">{{ $adminEmail }}</p>
                <p class="text-xs text-text-muted mt-0.5">Use the password you defined during setup to log in.</p>
            </div>

            <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 space-y-1">
                <strong class="block font-semibold">Post-Installation Security Checklist:</strong>
                <ul class="list-disc list-inside space-y-0.5 pl-1">
                    <li>The installer is now locked (<code class="font-mono">installed.lock</code>).</li>
                    <li>Ensure your web server points document root to <code class="font-mono">public/</code>.</li>
                    <li>Configure your server cron: <code class="font-mono">* * * * * php artisan schedule:run</code>.</li>
                </ul>
            </div>
        </div>

        <div class="mt-8">
            <a href="{{ route('login') }}" class="btn btn-primary w-full py-2.5 text-sm font-semibold justify-center">
                Go to Sign In &rarr;
            </a>
        </div>
    </div>
@endcomponent

