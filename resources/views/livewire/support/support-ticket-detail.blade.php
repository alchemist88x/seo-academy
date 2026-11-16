<div class="space-y-6">
    {{-- Header --}}
    <x-page-header
        :title="$ticket->subject"
        :subtitle="'Ticket #' . substr($ticket->id, 0, 8) . ' &bull; Submitted by ' . ($ticket->user?->name ?? 'Student') . ' &bull; ' . ($ticket->created_at ? $ticket->created_at->diffForHumans() : '')"
    >
        <x-slot:actions>
            <a href="{{ route('support.tickets.index') }}">
                <x-button variant="secondary" size="md">
                    &larr; Back to Tickets
                </x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Conversation Thread & Reply Box --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Initial Description --}}
            <x-card title="Initial Inquiry" :description="'Created ' . ($ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : '')">
                <div class="prose dark:prose-invert max-w-none text-slate-800 dark:text-slate-200 text-sm whitespace-pre-line">
                    {{ $ticket->description }}
                </div>
                @if ($ticket->module_name)
                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold">Module:</span>
                        <span>{{ $ticket->module_name }}</span>
                    </div>
                @endif
            </x-card>

            {{-- Thread Messages --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                    Activity &amp; Messages ({{ $ticket->messages->count() }})
                </h3>

                @if ($ticket->messages->isEmpty())
                    <x-card>
                        <p class="text-sm text-slate-500 dark:text-slate-400 italic text-center py-4">
                            No replies yet. Use the reply box below to respond to the student or leave an internal note.
                        </p>
                    </x-card>
                @else
                    @foreach ($ticket->messages as $msg)
                        @php
                            $isAuthorStaff = $msg->user && $msg->user->hasAnyRole(['admin', 'support_staff']);
                        @endphp

                        @if ($msg->is_internal)
                            {{-- Internal Note (Distinct Yellow/Amber Styling) --}}
                            <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 p-4 shadow-sm" wire:key="msg-{{ $msg->id }}">
                                <div class="flex items-center justify-between gap-4 mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200">
                                            Internal Note &bull; Staff Only
                                        </span>
                                        <span class="font-medium text-amber-950 dark:text-amber-100 text-sm">
                                            {{ $msg->user?->name ?? 'Staff' }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-amber-700 dark:text-amber-300">
                                        {{ $msg->created_at ? $msg->created_at->diffForHumans() : '' }}
                                    </span>
                                </div>
                                <div class="text-sm text-amber-900 dark:text-amber-200 whitespace-pre-line">
                                    {{ $msg->message }}
                                </div>
                            </div>
                        @else
                            {{-- Public Message / Staff Reply --}}
                            <div class="rounded-xl border {{ $isAuthorStaff ? 'border-primary-200 dark:border-primary-800 bg-primary-50/50 dark:bg-primary-950/20' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900' }} p-4 shadow-sm" wire:key="msg-{{ $msg->id }}">
                                <div class="flex items-center justify-between gap-4 mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-7 rounded-full {{ $isAuthorStaff ? 'bg-primary-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' }} flex items-center justify-center font-bold text-xs">
                                            {{ strtoupper(substr($msg->user?->name ?? 'U', 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-slate-900 dark:text-slate-100 text-sm">
                                            {{ $msg->user?->name ?? 'User' }}
                                        </span>
                                        @if ($isAuthorStaff)
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                                Support Staff
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-400">
                                        {{ $msg->created_at ? $msg->created_at->format('M d, Y H:i') : '' }}
                                    </span>
                                </div>
                                <div class="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line pl-9">
                                    {{ $msg->message }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            {{-- Reply Form --}}
            <x-card title="Add Reply or Internal Note">
                <form wire:submit="sendReply" class="space-y-4">
                    <div>
                        <textarea
                            wire:model="replyMessage"
                            rows="4"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Type your reply to the student, or leave an internal note for staff members..."
                            required
                        ></textarea>
                        @error('replyMessage')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="isInternalNote"
                                class="rounded border-slate-300 dark:border-slate-700 text-amber-600 shadow-sm focus:ring-amber-500 dark:bg-slate-900"
                            />
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                Internal Note (Only visible to staff)
                            </span>
                        </label>

                        <x-button
                            type="submit"
                            :variant="$isInternalNote ? 'secondary' : 'primary'"
                            size="md"
                        >
                            {{ $isInternalNote ? 'Add Internal Note' : 'Send Reply' }}
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- Right: Sidebar Controls & Metadata --}}
        <div class="space-y-6">
            {{-- Ticket Status & Assignment --}}
            <x-card title="Ticket Actions">
                <div class="space-y-4">
                    {{-- Status Dropdown --}}
                    <div>
                        <label for="ticket-status" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">
                            Status
                        </label>
                        <div class="flex gap-2">
                            <select
                                id="ticket-status"
                                wire:model="selectedStatus"
                                class="flex-1 rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                            >
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="waiting_for_response">Waiting for Response</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                            <x-button
                                type="button"
                                variant="secondary"
                                size="sm"
                                wire:click="updateStatus"
                            >
                                Update
                            </x-button>
                        </div>
                    </div>

                    {{-- Assignment Dropdown --}}
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-1">
                            <label for="ticket-assignee" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                Assigned Staff
                            </label>
                            <button
                                type="button"
                                wire:click="assignToMe"
                                class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                Assign to Me
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <select
                                id="ticket-assignee"
                                wire:model="selectedAssignee"
                                class="flex-1 rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-primary-500 focus:ring-primary-500"
                            >
                                <option value="">Unassigned</option>
                                @foreach ($staffUsers as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </select>
                            <x-button
                                type="button"
                                variant="secondary"
                                size="sm"
                                wire:click="updateAssignment"
                            >
                                Assign
                            </x-button>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- Metadata Card --}}
            <x-card title="Ticket Details">
                <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                    <div class="py-2.5 flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Priority</dt>
                        <dd>
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
                        </dd>
                    </div>

                    <div class="py-2.5 flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Status</dt>
                        <dd>
                            @php
                                $sColor = match (strtolower($ticket->status)) {
                                    'open', 'pending' => 'blue',
                                    'in_progress' => 'yellow',
                                    'waiting_for_response' => 'gray',
                                    'resolved' => 'green',
                                    'closed' => 'gray',
                                    default => 'gray',
                                };
                            @endphp
                            <x-badge :color="$sColor">
                                {{ ucfirst($ticket->status) }}
                            </x-badge>
                        </dd>
                    </div>

                    <div class="py-2.5 flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Student</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                            {{ $ticket->user?->name ?? '—' }}
                        </dd>
                    </div>

                    <div class="py-2.5 flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Student Email</dt>
                        <dd class="text-right text-xs font-mono text-slate-600 dark:text-slate-300">
                            {{ $ticket->user?->email ?? '—' }}
                        </dd>
                    </div>

                    <div class="py-2.5 flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Created</dt>
                        <dd class="text-right text-slate-600 dark:text-slate-300">
                            {{ $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : '—' }}
                        </dd>
                    </div>

                    @if ($ticket->resolved_at)
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">Resolved</dt>
                            <dd class="text-right text-slate-600 dark:text-slate-300">
                                {{ $ticket->resolved_at->format('M d, Y H:i') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </div>
    </div>
</div>
