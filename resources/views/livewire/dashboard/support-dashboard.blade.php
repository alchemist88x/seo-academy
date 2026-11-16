<div class="space-y-6">
    <x-page-header
        title="Support Desk"
        subtitle="Welcome back, {{ $staffName }}. Triage student support tickets, respond to inquiries, and manage escalations."
    >
        <x-slot:actions>
            <a href="{{ route('support.tickets.index') }}">
                <x-button variant="primary" size="md">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    View Tickets Queue
                </x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach ($stats as $stat)
            <x-stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :trend="$stat['trend']"
                :trendDirection="$stat['direction']"
            />
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Urgent Tickets --}}
        <x-card title="Urgent Tickets" description="High priority escalations requiring immediate attention.">
            @if ($urgentTickets->isEmpty())
                <x-empty-state
                    title="No urgent tickets"
                    message="There are no urgent tickets pending review."
                />
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($urgentTickets as $ticket)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('support.tickets.show', $ticket) }}" class="text-sm font-semibold text-slate-900 dark:text-slate-100 hover:text-primary-600 dark:hover:text-primary-400 truncate block">
                                    {{ $ticket->subject }}
                                </a>
                                <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span>{{ $ticket->user?->name ?? 'Student' }}</span>
                                    <span>&bull;</span>
                                    <span>Assigned: {{ $ticket->assignedTo?->name ?? 'Unassigned' }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <x-badge color="red">
                                    Urgent
                                </x-badge>
                                <a
                                    href="{{ route('support.tickets.show', $ticket) }}"
                                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors"
                                    title="Open Ticket"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Recent Open Tickets --}}
        <x-card title="Recent Open Tickets" description="Latest student questions and support requests.">
            @if ($recentOpenTickets->isEmpty())
                <x-empty-state
                    title="Support queue is clear"
                    message="No open tickets awaiting response right now."
                />
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($recentOpenTickets as $ticket)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('support.tickets.show', $ticket) }}" class="text-sm font-medium text-slate-900 dark:text-slate-100 hover:text-primary-600 dark:hover:text-primary-400 truncate block">
                                    {{ $ticket->subject }}
                                </a>
                                <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span>{{ $ticket->user?->name ?? 'Student' }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $ticket->created_at ? $ticket->created_at->diffForHumans() : '' }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @php
                                    $pColor = match ($ticket->priority) {
                                        'urgent' => 'red',
                                        'high' => 'yellow',
                                        'low' => 'gray',
                                        default => 'blue',
                                    };
                                @endphp
                                <x-badge :color="$pColor">
                                    {{ ucfirst($ticket->priority ?? 'medium') }}
                                </x-badge>
                                <a
                                    href="{{ route('support.tickets.show', $ticket) }}"
                                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors"
                                    title="Open Ticket"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-right">
                    <a href="{{ route('support.tickets.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        View all tickets &rarr;
                    </a>
                </div>
            @endif
        </x-card>
    </div>
</div>
