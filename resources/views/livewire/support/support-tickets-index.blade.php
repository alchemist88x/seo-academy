<div class="space-y-6">
    <x-page-header
        title="Support Tickets"
        subtitle="Manage and respond to student inquiries and support tickets for {{ $academy->name }}."
    >
        <x-slot:actions>
            <a href="{{ route('support.dashboard') }}">
                <x-button variant="secondary" size="md">
                    &larr; Support Desk
                </x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <x-input
                    name="search"
                    label="Search Ticket or Student"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search subject, description, student..."
                />
            </div>

            <div>
                <label for="status-filter" class="form-label block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Status
                </label>
                <select
                    id="status-filter"
                    wire:model.live="status"
                    class="form-select w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="waiting_for_response">Waiting for Response</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <div>
                <label for="priority-filter" class="form-label block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Priority
                </label>
                <select
                    id="priority-filter"
                    wire:model.live="priority"
                    class="form-select w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>

            <div>
                <label for="assigned-filter" class="form-label block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Assignment
                </label>
                <select
                    id="assigned-filter"
                    wire:model.live="assigned"
                    class="form-select w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="all">All Tickets</option>
                    <option value="mine">Assigned to Me</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </div>
        </div>

        @if ($search !== '' || $status !== '' || $priority !== '' || $assigned !== 'all')
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                >
                    Clear filters
                </button>
            </div>
        @endif
    </x-card>

    {{-- Tickets Table --}}
    <x-card :padding="false">
        @if ($tickets->isEmpty())
            <x-empty-state
                title="No support tickets found"
                message="No tickets match your filter criteria."
            />
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th>Subject</th>
                    <th>Student</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th class="text-right">Action</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($tickets as $ticket)
                        <tr wire:key="ticket-{{ $ticket->id }}">
                            <td>
                                <a
                                    href="{{ route('support.tickets.show', $ticket) }}"
                                    class="font-semibold text-slate-900 dark:text-slate-100 hover:text-primary-600 dark:hover:text-primary-400 block"
                                >
                                    {{ $ticket->subject }}
                                </a>
                                @if ($ticket->module_name)
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        Module: {{ $ticket->module_name }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ $ticket->user?->name ?? 'Student' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $ticket->user?->email ?? '—' }}
                                </div>
                            </td>
                            <td>
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
                            </td>
                            <td>
                                @php
                                    $sColor = match (strtolower($ticket->status)) {
                                        'open', 'pending' => 'blue',
                                        'in_progress' => 'yellow',
                                        'waiting_for_response' => 'gray',
                                        'resolved' => 'green',
                                        'closed' => 'gray',
                                        default => 'gray',
                                    };
                                    $statusLabel = match (strtolower($ticket->status)) {
                                        'in_progress' => 'In Progress',
                                        'waiting_for_response' => 'Waiting for Response',
                                        default => ucfirst($ticket->status),
                                    };
                                @endphp
                                <x-badge :color="$sColor">
                                    {{ $statusLabel }}
                                </x-badge>
                            </td>
                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                @if ($ticket->assignedTo)
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ $ticket->assignedTo->name }}
                                    </div>
                                @else
                                    <span class="italic text-slate-400 dark:text-slate-500">Unassigned</span>
                                @endif
                            </td>
                            <td class="text-sm text-slate-500 dark:text-slate-400">
                                {{ $ticket->created_at ? $ticket->created_at->format('M d, Y') : '—' }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('support.tickets.show', $ticket) }}">
                                    <x-button variant="secondary" size="sm">
                                        View
                                    </x-button>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $tickets->links() }}
            </div>
        @endif
    </x-card>
</div>
