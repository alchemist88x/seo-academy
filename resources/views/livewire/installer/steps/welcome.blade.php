<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-text-primary">Step 1 — Welcome &amp; Architecture</h2>
        <p class="text-xs text-text-secondary mt-1">
            Thank you for deploying SEO Academy. Review the architecture principles and begin the browser setup.
        </p>
    </div>

    {{-- Product Description & Features --}}
    <div class="p-5 rounded-xl bg-surface-elevated/40 border border-border-default space-y-3">
        <p class="text-sm text-text-primary font-medium leading-relaxed">
            SEO Academy is an enterprise-grade commercial Learning Management System engineered for independent SEO, digital marketing, and web analytics academies.
        </p>
        <p class="text-xs text-text-secondary leading-relaxed">
            This installation wizard will guide you through verifying your PHP runtime, selecting your database engine (SQLite or MySQL 8.4+), testing your connection, initializing your database schema without terminal commands, and establishing your first administrator credentials.
        </p>
    </div>

    {{-- Architecture Guarantees --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
        <div class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default text-xs space-y-1">
            <span class="font-bold text-text-primary block flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                100% Data Sovereignty
            </span>
            <p class="text-text-secondary">
                Your database, student files, quizzes, and certificates remain exclusively on your server. Zero external SaaS telemetry.
            </p>
        </div>

        <div class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default text-xs space-y-1">
            <span class="font-bold text-text-primary block flex items-center gap-1.5">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Zero Multi-Tenancy
            </span>
            <p class="text-text-secondary">
                One installation equals exactly one independent academy. No platform admins or cross-organization leaks.
            </p>
        </div>

        <div class="p-4 rounded-xl bg-surface-elevated/40 border border-border-default text-xs space-y-1">
            <span class="font-bold text-text-primary block flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Shared Hosting Ready
            </span>
            <p class="text-text-secondary">
                Runs cleanly on Apache FastCGI, cPanel, and VPS without Docker, Node.js, or permanent command-line workers.
            </p>
        </div>
    </div>

    {{-- System Prerequisites Summary --}}
    <div class="p-4 rounded-xl bg-surface-elevated/20 border border-border-default text-xs space-y-2">
        <span class="font-bold text-text-primary block">Installation Prerequisites:</span>
        <ul class="list-disc list-inside space-y-1 text-text-secondary">
            <li>PHP 8.3 or higher with standard extensions (BCMath, DOM, FileInfo, JSON, Mbstring, OpenSSL, PDO, Zip).</li>
            <li>Document root mapped to the application's <code class="font-mono text-primary-600 dark:text-primary-400">public/</code> directory.</li>
            <li>Writable <code class="font-mono">storage/</code> and <code class="font-mono">bootstrap/cache/</code> directories.</li>
            <li>Database choice: SQLite (zero configuration, built-in) or MySQL 8.4+ InnoDB credentials.</li>
        </ul>
    </div>

    {{-- Actions --}}
    <div class="pt-4 border-t border-border-default flex items-center justify-end">
        <button
            type="button"
            wire:click="startInstallation"
            wire:loading.attr="disabled"
            class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm flex items-center gap-2 cursor-pointer"
        >
            <span wire:loading.remove wire:target="startInstallation">Start Installation &rarr;</span>
            <span wire:loading wire:target="startInstallation" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Initializing...
            </span>
        </button>
    </div>
</div>
