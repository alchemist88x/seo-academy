<div class="space-y-6">
    <x-page-header
        :title="$academy ? $academy->name . ' — Admin Overview' : 'Admin Overview'"
        subtitle="Monitor student engagement, course catalog, support queue, and recent academy activity."
    >
        <x-slot:actions>
            @if ($academy)
                <a
                    href="{{ route('admin.academy.settings') }}"
                    class="btn btn-primary btn-md inline-flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Academy Settings
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- KPI Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($stats as $stat)
            <x-stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :trend="$stat['trend']"
                :trendDirection="$stat['direction']"
            />
        @endforeach
    </div>

    {{-- Academy Summary Card --}}
    @if ($academy)
        @php
            $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $resolvedDomain = $academy->custom_domain
                ?: ($academy->subdomain ? $academy->subdomain . '.' . $appHost : 'Not configured');
        @endphp
        <x-card title="Academy Summary" description="Current academy configuration and quick links.">
            <x-slot:header>
                <a
                    href="{{ route('admin.academy.settings') }}"
                    class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                >
                    Configure &rarr;
                </a>
            </x-slot:header>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Academy Name</p>
                    <p class="mt-1 font-semibold text-text-primary">{{ $academy->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Domain</p>
                    <p class="mt-1 font-medium text-text-primary">{{ $resolvedDomain }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Timezone</p>
                    <p class="mt-1 font-medium text-text-primary">{{ $academy->timezone ?? 'UTC' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Contact Email</p>
                    <p class="mt-1 font-medium text-text-primary">{{ $academy->support_email ?: 'Not set' }}</p>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Recent Audit Activity --}}
    <x-card title="Recent Audit Activity" description="Latest administrative and configuration changes." :padding="false">
        @if ($recentAuditLogs->isEmpty())
            <div class="p-6">
                <x-empty-state
                    title="No recent audit activity"
                    message="Configuration updates and administrative actions will appear here."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border-default bg-surface-subtle/50 text-xs font-semibold uppercase tracking-wider text-text-muted">
                            <th class="px-5 py-3">Action</th>
                            <th class="px-5 py-3">Actor</th>
                            <th class="px-5 py-3">Details</th>
                            <th class="px-5 py-3 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @foreach ($recentAuditLogs as $log)
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="px-5 py-3 font-medium text-text-primary">
                                    {{ $log->action }}
                                </td>
                                <td class="px-5 py-3 text-text-secondary">
                                    {{ $log->user?->name ?? 'System' }}
                                </td>
                                <td class="px-5 py-3 text-text-muted text-xs">
                                    @if (!empty($log->properties))
                                        {{ collect($log->properties)->except('academy_id')->map(fn ($v, $k) => $k . ': ' . (is_array($v) ? implode(', ', $v) : $v))->implode(' | ') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-text-muted whitespace-nowrap">
                                    {{ $log->created_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
