<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">System Audit Logs</h1>
            <p class="text-sm text-text-muted mt-1">
                Immutable, chronological activity trail of administrative actions, enrollments, support events, and system changes.
            </p>
        </div>

        <div>
            <button
                type="button"
                wire:click="resetFilters"
                class="btn btn-secondary text-xs flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset Filters</span>
            </button>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card p-4 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            {{-- Search --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-text-muted mb-1">Search</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search action, entity ID, or IP..."
                    class="input text-xs"
                >
            </div>

            {{-- Action Filter --}}
            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">Action</label>
                <select wire:model.live="action" class="input text-xs">
                    <option value="all">All Actions</option>
                    @foreach ($availableActions as $act)
                        <option value="{{ $act }}">{{ $act }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Module / Entity Filter --}}
            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">Module / Entity</label>
                <select wire:model.live="module" class="input text-xs">
                    <option value="all">All Modules</option>
                    @foreach ($availableModules as $mod)
                        <option value="{{ $mod }}">{{ $mod }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">From Date</label>
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="input text-xs"
                >
            </div>

            {{-- Date To --}}
            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">To Date</label>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="input text-xs"
                >
            </div>
        </div>
    </div>

    {{-- Logs Table & Detail Split --}}
    <div class="grid grid-cols-1 {{ $selectedLog ? 'lg:grid-cols-3' : '' }} gap-6">
        <div class="card overflow-hidden {{ $selectedLog ? 'lg:col-span-2' : '' }}">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-text-secondary">
                    <thead class="bg-surface-elevated/70 text-text-muted font-semibold border-b border-border-default uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Timestamp</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Target Entity</th>
                            <th class="px-4 py-3 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse ($logs as $log)
                            @php
                                $actorName = $log->user?->name ?? ($log->properties['_actor']['name'] ?? 'System / Anonymous');
                                $actorEmail = $log->user?->email ?? ($log->properties['_actor']['email'] ?? null);
                                $isSelected = $selectedLog && $selectedLog->id === $log->id;
                            @endphp
                            <tr
                                wire:key="log-{{ $log->id }}"
                                class="hover:bg-surface-elevated/40 transition-colors {{ $isSelected ? 'bg-primary-50/50 dark:bg-primary-950/20' : '' }}"
                            >
                                <td class="px-4 py-3 font-mono text-[11px] text-text-muted whitespace-nowrap">
                                    {{ $log->created_at?->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-text-primary truncate max-w-[140px]" title="{{ $actorName }}">
                                        {{ $actorName }}
                                    </div>
                                    @if ($actorEmail)
                                        <div class="text-[10px] text-text-muted truncate max-w-[140px]">
                                            {{ $actorEmail }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-medium bg-surface-elevated text-text-primary">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-text-muted">
                                    <div>{{ class_basename($log->subject_type ?? '') }}</div>
                                    <div class="text-[10px] truncate max-w-[100px]" title="{{ $log->subject_id }}">{{ $log->subject_id }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="selectLog('{{ $log->id }}')"
                                        class="text-xs font-semibold {{ $isSelected ? 'text-primary-700 font-bold' : 'text-primary-600 hover:underline' }}"
                                    >
                                        {{ $isSelected ? 'Selected' : 'Inspect' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-text-muted">
                                    No audit entries found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="p-4 border-t border-border-default">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>

        {{-- Detail Inspector Pane --}}
        @if ($selectedLog)
            <div class="card p-5 space-y-4 self-start sticky top-6">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-sm font-bold text-text-primary">Event Details</h3>
                    <button
                        type="button"
                        wire:click="selectLog(null)"
                        class="text-text-muted hover:text-text-primary text-xs"
                    >
                        ✕ Close
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-text-muted block text-[11px] uppercase tracking-wider font-semibold">Event ID</span>
                        <span class="font-mono text-text-primary select-all break-all">{{ $selectedLog->id }}</span>
                    </div>

                    <div>
                        <span class="text-text-muted block text-[11px] uppercase tracking-wider font-semibold">Timestamp</span>
                        <span class="font-mono text-text-primary">{{ $selectedLog->created_at?->toIso8601String() }}</span>
                    </div>

                    <div>
                        <span class="text-text-muted block text-[11px] uppercase tracking-wider font-semibold">Actor</span>
                        <div class="text-text-primary font-medium">
                            {{ $selectedLog->user?->name ?? ($selectedLog->properties['_actor']['name'] ?? 'System / Anonymous') }}
                            @if ($selectedLog->user_id)
                                <span class="font-mono text-[10px] text-text-muted">({{ $selectedLog->user_id }})</span>
                            @else
                                <span class="text-[10px] text-amber-600 font-medium">(User account deleted)</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="text-text-muted block text-[11px] uppercase tracking-wider font-semibold">Action &amp; Target</span>
                        <div class="font-mono text-text-primary mt-0.5">
                            <span class="font-bold">{{ $selectedLog->action }}</span>
                            @if ($selectedLog->subject_type)
                                <span class="text-text-muted">on {{ class_basename($selectedLog->subject_type) }} #{{ $selectedLog->subject_id }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($selectedLog->ip_address)
                        <div>
                            <span class="text-text-muted block text-[11px] uppercase tracking-wider font-semibold">Client Metadata</span>
                            <span class="font-mono text-text-secondary block">IP: {{ $selectedLog->ip_address }}</span>
                            @if ($selectedLog->user_agent)
                                <span class="text-[10px] text-text-muted block truncate mt-0.5" title="{{ $selectedLog->user_agent }}">UA: {{ $selectedLog->user_agent }}</span>
                            @endif
                        </div>
                    @endif

                    <div>
                        <span class="text-text-muted block text-[11px] uppercase tracking-wider font-semibold mb-1">Properties (State Delta)</span>
                        <div class="p-3 rounded-lg bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto max-h-72">
                            <pre>{{ json_encode($selectedLog->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

