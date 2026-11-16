<div class="max-w-4xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-text-muted mb-1">
                <a href="{{ route('notifications.index') }}" class="hover:underline">Notifications</a>
                <span>/</span>
                <span>Preferences</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Notification Preferences</h1>
            <p class="text-sm text-text-muted mt-1">
                Configure which notifications you receive and choose delivery channels for each category.
            </p>
        </div>

        <a href="{{ route('notifications.index') }}" class="btn btn-secondary text-sm">
            &larr; Back to notifications
        </a>
    </div>

    {{-- Preferences Matrix --}}
    <div class="card overflow-hidden">
        <div class="p-6 border-b border-border-default">
            <h2 class="text-base font-semibold text-text-primary">Delivery Channels</h2>
            <p class="text-xs text-text-muted mt-0.5">
                Toggle in-app and email delivery options below. Changes are saved automatically.
            </p>
        </div>

        <div class="divide-y divide-border-default">
            {{-- Category: Enrollments --}}
            <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="max-w-md">
                    <h3 class="text-sm font-semibold text-text-primary">Enrollments &amp; Access</h3>
                    <p class="text-xs text-text-muted mt-1">
                        Updates regarding course enrollments, access activations, suspensions, and upcoming expiration notices.
                    </p>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-text-secondary">
                        <input
                            type="checkbox"
                            wire:click="toggle('enrollment', 'in_app')"
                            @checked($preferences['enrollment']['in_app'] ?? true)
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        >
                        <span>In-App</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-text-secondary">
                        <input
                            type="checkbox"
                            wire:click="toggle('enrollment', 'email')"
                            @checked($preferences['enrollment']['email'] ?? true)
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        >
                        <span>Email</span>
                    </label>
                </div>
            </div>

            {{-- Category: Support --}}
            <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="max-w-md">
                    <h3 class="text-sm font-semibold text-text-primary">Support &amp; Help Desk</h3>
                    <p class="text-xs text-text-muted mt-1">
                        Responses to support inquiries, ticket assignments, status changes, and priority updates.
                    </p>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-text-secondary">
                        <input
                            type="checkbox"
                            wire:click="toggle('support', 'in_app')"
                            @checked($preferences['support']['in_app'] ?? true)
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        >
                        <span>In-App</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-text-secondary">
                        <input
                            type="checkbox"
                            wire:click="toggle('support', 'email')"
                            @checked($preferences['support']['email'] ?? true)
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        >
                        <span>Email</span>
                    </label>
                </div>
            </div>

            {{-- Category: Courses --}}
            <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="max-w-md">
                    <h3 class="text-sm font-semibold text-text-primary">Courses &amp; Training</h3>
                    <p class="text-xs text-text-muted mt-1">
                        New lesson releases, trainer assignments, announcements, and curriculum updates.
                    </p>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-text-secondary">
                        <input
                            type="checkbox"
                            wire:click="toggle('course', 'in_app')"
                            @checked($preferences['course']['in_app'] ?? true)
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        >
                        <span>In-App</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-text-secondary">
                        <input
                            type="checkbox"
                            wire:click="toggle('course', 'email')"
                            @checked($preferences['course']['email'] ?? true)
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        >
                        <span>Email</span>
                    </label>
                </div>
            </div>

            {{-- Category: System & Security (Required) --}}
            <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface-elevated/40">
                <div class="max-w-md">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-text-primary">Account Security &amp; System Notices</h3>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                            Required
                        </span>
                    </div>
                    <p class="text-xs text-text-muted mt-1">
                        Critical account security alerts, password resets, and essential operational notices cannot be disabled.
                    </p>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-xs font-medium text-text-muted cursor-not-allowed">
                        <input type="checkbox" checked disabled class="rounded border-border-default text-primary-600 opacity-60">
                        <span>In-App (Always On)</span>
                    </label>

                    <label class="flex items-center gap-2 text-xs font-medium text-text-muted cursor-not-allowed">
                        <input type="checkbox" checked disabled class="rounded border-border-default text-primary-600 opacity-60">
                        <span>Email (Always On)</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

