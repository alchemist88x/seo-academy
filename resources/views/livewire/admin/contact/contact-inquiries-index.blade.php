<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    {{-- Header & Stats --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-700/60 pb-5">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-primary-600/20 text-primary-400 border border-primary-500/30">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Contact Inquiries & Admissions</h1>
                    <p class="text-sm text-slate-400">Manage prospective student inquiries, admissions leads, follow-ups, and staff assignments.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.chat-conversations') }}" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 border border-slate-700 transition">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                Live Chat Inbox
            </a>
        </div>
    </div>

    {{-- Status Tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
        @php
            $tabs = [
                'all' => ['label' => 'All Inquiries', 'count' => $statusCounts['all'], 'color' => 'bg-slate-700 text-slate-300'],
                'new' => ['label' => 'New', 'count' => $statusCounts['new'], 'color' => 'bg-amber-500/20 text-amber-300 border-amber-500/30'],
                'contacted' => ['label' => 'Contacted', 'count' => $statusCounts['contacted'], 'color' => 'bg-blue-500/20 text-blue-300 border-blue-500/30'],
                'in_progress' => ['label' => 'In Progress', 'count' => $statusCounts['in_progress'], 'color' => 'bg-purple-500/20 text-purple-300 border-purple-500/30'],
                'converted' => ['label' => 'Converted', 'count' => $statusCounts['converted'], 'color' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'],
                'closed' => ['label' => 'Closed', 'count' => $statusCounts['closed'], 'color' => 'bg-slate-600/30 text-slate-400 border-slate-600/40'],
            ];
        @endphp

        @foreach ($tabs as $key => $tab)
            <button
                type="button"
                wire:click="$set('statusFilter', '{{ $key }}')"
                class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition border whitespace-nowrap {{ $statusFilter === $key ? 'bg-primary-600 text-white border-primary-500 shadow-sm' : 'bg-slate-800 text-slate-300 border-slate-700/80 hover:bg-slate-700 hover:text-white' }}"
            >
                <span>{{ $tab['label'] }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $statusFilter === $key ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-300' }}">
                    {{ $tab['count'] }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="bg-slate-800/80 rounded-xl p-4 border border-slate-700/80 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            {{-- Search Input --}}
            <div class="md:col-span-4 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, email, phone, course..."
                    class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                />
            </div>

            {{-- Source Filter --}}
            <div class="md:col-span-2">
                <select
                    wire:model.live="sourceFilter"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    <option value="all">All Sources</option>
                    <option value="contact_form">Website Contact Form</option>
                    <option value="chatbot">Chatbot Admissions</option>
                    <option value="manual">Manual Entry</option>
                </select>
            </div>

            {{-- Assigned Staff Filter --}}
            <div class="md:col-span-2">
                <select
                    wire:model.live="assignedFilter"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    <option value="all">All Assignees</option>
                    <option value="unassigned">Unassigned</option>
                    @foreach ($availableStaff as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Sort By --}}
            <div class="md:col-span-2">
                <select
                    wire:model.live="sortBy"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    <option value="recent">Most Recent First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="follow_up">Next Follow-up First</option>
                </select>
            </div>

            {{-- Reset Filters --}}
            <div class="md:col-span-2 flex items-center justify-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="w-full px-3 py-2 bg-slate-700/60 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-semibold transition border border-slate-600/50 flex items-center justify-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reset Filters
                </button>
            </div>
        </div>

        {{-- Date range collapsible/row --}}
        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-700/50 text-xs text-slate-400">
            <span class="font-medium text-slate-300">Date Range:</span>
            <div class="flex items-center gap-1.5">
                <span>From</span>
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="px-2.5 py-1 bg-slate-900 border border-slate-700 rounded-md text-xs text-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
            </div>
            <div class="flex items-center gap-1.5">
                <span>To</span>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="px-2.5 py-1 bg-slate-900 border border-slate-700 rounded-md text-xs text-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
            </div>
            <div class="ml-auto flex items-center gap-2">
                <span>Per page:</span>
                <select
                    wire:model.live="perPage"
                    class="px-2 py-1 bg-slate-900 border border-slate-700 rounded-md text-xs text-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Inquiries Table --}}
    <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/80 text-xs uppercase text-slate-400 tracking-wider border-b border-slate-700">
                    <tr>
                        <th class="py-3 px-4">Visitor</th>
                        <th class="py-3 px-4">Source</th>
                        <th class="py-3 px-4">Course / Interest</th>
                        <th class="py-3 px-4">Assigned Staff</th>
                        <th class="py-3 px-4">Follow-up</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Submitted</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    @forelse ($inquiries as $inquiry)
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            {{-- Visitor Info --}}
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-white flex items-center gap-1.5">
                                    {{ $inquiry->name }}
                                </div>
                                <div class="text-xs text-slate-400 truncate max-w-xs">{{ $inquiry->email }}</div>
                                @if ($inquiry->phone_formatted || $inquiry->phone)
                                    <div class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                        @if ($inquiry->phone_country_code)
                                            <span class="px-1.5 py-0.2 rounded bg-slate-700 text-[10px] font-mono text-slate-300">
                                                {{ $inquiry->phone_country_code }}
                                            </span>
                                        @endif
                                        <span>{{ $inquiry->phone_formatted ?: $inquiry->phone }}</span>
                                    </div>
                                @endif
                            </td>

                            {{-- Source Badge --}}
                            <td class="py-3.5 px-4">
                                @if ($inquiry->source === 'chatbot')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        Chatbot
                                    </span>
                                @elseif ($inquiry->source === 'contact_form')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        Contact Form
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-700 text-slate-300">
                                        {{ ucfirst($inquiry->source) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Course / Subject --}}
                            <td class="py-3.5 px-4 max-w-xs">
                                @if ($inquiry->course_interest)
                                    <div class="font-medium text-white truncate text-xs">{{ $inquiry->course_interest }}</div>
                                @endif
                                <div class="text-xs text-slate-400 truncate">{{ $inquiry->subject }}</div>
                            </td>

                            {{-- Assigned Staff --}}
                            <td class="py-3.5 px-4">
                                @if ($inquiry->assignedTo)
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-6 h-6 rounded-full bg-primary-600/30 border border-primary-500/40 text-primary-300 text-xs font-bold flex items-center justify-center">
                                            {{ strtoupper(substr($inquiry->assignedTo->name, 0, 1)) }}
                                        </div>
                                        <span class="text-xs text-white">{{ $inquiry->assignedTo->name }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500 italic">Unassigned</span>
                                @endif
                            </td>

                            {{-- Follow-up Date --}}
                            <td class="py-3.5 px-4 text-xs">
                                @if ($inquiry->follow_up_date)
                                    @php
                                        $isToday = $inquiry->follow_up_date->isToday();
                                        $isPast = $inquiry->follow_up_date->isPast() && ! $isToday;
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium {{ $isPast ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : ($isToday ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold' : 'bg-slate-700/60 text-slate-300') }}">
                                        {{ $inquiry->follow_up_date->format('M d, Y') }}
                                        @if ($isToday)
                                            <span class="text-[9px] uppercase tracking-wider font-extrabold">(Today)</span>
                                        @elseif ($isPast)
                                            <span class="text-[9px] uppercase tracking-wider font-extrabold">(Overdue)</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-slate-500 italic">—</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3.5 px-4">
                                @php
                                    $statusStyles = [
                                        'new' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                        'contacted' => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                                        'in_progress' => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                                        'converted' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'closed' => 'bg-slate-700 text-slate-400 border-slate-600',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusStyles[$inquiry->status] ?? 'bg-slate-700 text-slate-300' }}">
                                    {{ ucfirst(str_replace('_', ' ', $inquiry->status)) }}
                                </span>
                            </td>

                            {{-- Submitted Timestamp --}}
                            <td class="py-3.5 px-4 text-xs text-slate-400 whitespace-nowrap">
                                <div>{{ $inquiry->created_at->diffForHumans() }}</div>
                                <div class="text-[10px] text-slate-500">{{ $inquiry->created_at->format('M d, Y H:i') }}</div>
                            </td>

                            {{-- Actions --}}
                            <td class="py-3.5 px-4 text-right">
                                <button
                                    type="button"
                                    wire:click="openInquiryModal('{{ $inquiry->id }}')"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-600/20 hover:bg-primary-600 text-primary-300 hover:text-white border border-primary-500/30 transition"
                                >
                                    <span>View & Manage</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="max-w-sm mx-auto text-center space-y-2">
                                    <svg class="w-12 h-12 text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <h3 class="text-sm font-semibold text-slate-300">No Contact Inquiries Found</h3>
                                    <p class="text-xs text-slate-500">There are no inquiries matching your active filters. Try resetting search or filter options.</p>
                                    <button
                                        type="button"
                                        wire:click="resetFilters"
                                        class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 text-xs font-medium text-slate-200 hover:bg-slate-600"
                                    >
                                        Clear All Filters
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($inquiries->hasPages())
            <div class="p-4 border-t border-slate-700/80 bg-slate-900/40">
                {{ $inquiries->links() }}
            </div>
        @endif
    </div>

    {{-- Detail & Management Modal --}}
    @if ($selectedInquiry)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true">
            <div class="relative w-full max-w-4xl bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {{-- Modal Header --}}
                <div class="px-6 py-4 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-600/20 text-primary-400 border border-primary-500/30 flex items-center justify-center font-bold text-lg">
                            {{ strtoupper(substr($selectedInquiry->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-white">{{ $selectedInquiry->name }}</h2>
                                @php
                                    $statusBadgeStyles = [
                                        'new' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                        'contacted' => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                                        'in_progress' => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                                        'converted' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'closed' => 'bg-slate-700 text-slate-400 border-slate-600',
                                    ];
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadgeStyles[$selectedInquiry->status] ?? 'bg-slate-700 text-slate-300' }}">
                                    {{ ucfirst(str_replace('_', ' ', $selectedInquiry->status)) }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400">
                                Submitted {{ $selectedInquiry->created_at->format('M d, Y \a\t H:i') }}
                                ({{ $selectedInquiry->created_at->diffForHumans() }})
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeInquiryModal"
                        class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition"
                        aria-label="Close modal"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Feedback Alert --}}
                @if ($feedbackMessage)
                    <div class="px-6 py-2.5 text-xs font-medium {{ $feedbackType === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-b border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border-b border-rose-500/30' }}">
                        {{ $feedbackMessage }}
                    </div>
                @endif

                {{-- Modal Body: 2 Columns on Desktop --}}
                <div class="overflow-y-auto p-6 space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        {{-- Left Column: Inquiry & Visitor Details --}}
                        <div class="lg:col-span-7 space-y-6">
                            {{-- Visitor Contact Info Card --}}
                            <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 space-y-3">
                                <h3 class="text-xs uppercase font-bold text-slate-400 tracking-wider">Visitor Contact Details</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <span class="text-slate-400 block">Full Name:</span>
                                        <span class="text-white font-medium text-sm">{{ $selectedInquiry->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Email Address:</span>
                                        <a href="mailto:{{ $selectedInquiry->email }}" class="text-primary-400 hover:underline font-mono">
                                            {{ $selectedInquiry->email }}
                                        </a>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Phone (E.164 / Formatted):</span>
                                        <div class="text-white font-mono flex items-center gap-1.5">
                                            @if ($selectedInquiry->phone_country_code)
                                                <span class="px-1.5 py-0.5 rounded bg-slate-700 text-[10px] text-slate-300 font-bold">
                                                    {{ $selectedInquiry->phone_country_code }}
                                                </span>
                                            @endif
                                            <span>{{ $selectedInquiry->phone_formatted ?: ($selectedInquiry->phone ?: 'Not provided') }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Source:</span>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            @if ($selectedInquiry->source === 'chatbot')
                                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                                    Chatbot Admissions Flow
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                                    Website Contact Form
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Linked Conversation Link --}}
                                @if ($selectedInquiry->conversation_id)
                                    <div class="pt-3 border-t border-slate-700/60 flex items-center justify-between">
                                        <span class="text-xs text-slate-400">Captured in Chatbot Conversation:</span>
                                        <a
                                            href="{{ route('admin.chat-conversations', ['conversationId' => $selectedInquiry->conversation_id]) }}"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600 hover:text-white border border-indigo-500/30 text-xs font-semibold transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                            View Chat Transcript
                                        </a>
                                    </div>
                                @endif
                            </div>

                            {{-- Interest & Message Card --}}
                            <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 space-y-3">
                                <h3 class="text-xs uppercase font-bold text-slate-400 tracking-wider">Inquiry Content</h3>
                                <div>
                                    <span class="text-xs text-slate-400 block">Course / Service of Interest:</span>
                                    <span class="text-sm font-semibold text-white">
                                        {{ $selectedInquiry->course_interest ?: ($selectedInquiry->interested_service ?: 'General Inquiry') }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-400 block">Subject:</span>
                                    <span class="text-sm text-slate-200">{{ $selectedInquiry->subject }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-400 block">Message:</span>
                                    <div class="mt-1 p-3 rounded-lg bg-slate-900 border border-slate-700/80 text-sm text-slate-200 whitespace-pre-wrap leading-relaxed">
                                        {{ $selectedInquiry->message }}
                                    </div>
                                </div>
                            </div>

                            {{-- Consent Record Card --}}
                            <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 space-y-2">
                                <h3 class="text-xs uppercase font-bold text-slate-400 tracking-wider">Admissions Consent Audit</h3>
                                @if ($selectedInquiry->consent_text)
                                    <div class="p-3 rounded-lg bg-emerald-950/30 border border-emerald-500/30 space-y-1">
                                        <div class="flex items-center gap-2 text-emerald-400 text-xs font-semibold">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Consent Granted ({{ $selectedInquiry->consent_version ?? 'v1.0' }})
                                        </div>
                                        <p class="text-xs text-slate-300 italic">"{{ $selectedInquiry->consent_text }}"</p>
                                        <div class="text-[10px] text-slate-400">
                                            Consented at: {{ $selectedInquiry->consented_at ? $selectedInquiry->consented_at->format('M d, Y H:i:s T') : 'Recorded with inquiry' }}
                                        </div>
                                    </div>
                                @else
                                    <div class="text-xs text-slate-400 italic">Standard inquiry submission. No explicit additional consent terms were recorded.</div>
                                @endif

                                @if ($selectedInquiry->ip_address)
                                    <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-700/50 flex flex-wrap gap-4">
                                        <span>IP: <code class="text-slate-300">{{ $selectedInquiry->ip_address }}</code></span>
                                        @if ($selectedInquiry->user_agent)
                                            <span class="truncate max-w-xs" title="{{ $selectedInquiry->user_agent }}">
                                                Browser: {{ Str::limit($selectedInquiry->user_agent, 45) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Right Column: Management Actions, Assignment, Notes & Activity --}}
                        <div class="lg:col-span-5 space-y-6">
                            {{-- Action Card: Status, Assignee, Follow-up --}}
                            <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 space-y-4">
                                <h3 class="text-xs uppercase font-bold text-slate-400 tracking-wider">Inquiry Management</h3>

                                {{-- Change Status --}}
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Lifecycle Status</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'in_progress' => 'In Progress', 'converted' => 'Converted', 'closed' => 'Closed'] as $statusKey => $statusName)
                                            <button
                                                type="button"
                                                wire:click="updateStatus('{{ $statusKey }}')"
                                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition border text-left flex items-center justify-between {{ $selectedInquiry->status === $statusKey ? 'bg-primary-600 text-white border-primary-500 shadow-sm' : 'bg-slate-900/80 text-slate-300 border-slate-700 hover:bg-slate-700' }}"
                                            >
                                                <span>{{ $statusName }}</span>
                                                @if ($selectedInquiry->status === $statusKey)
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Assign Staff --}}
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Assign to Staff Member</label>
                                    <div class="flex gap-2">
                                        <select
                                            wire:model="editAssignedStaffId"
                                            class="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
                                        >
                                            <option value="">Unassigned</option>
                                            @foreach ($availableStaff as $staff)
                                                <option value="{{ $staff->id }}">{{ $staff->name }} ({{ $staff->role }})</option>
                                            @endforeach
                                        </select>
                                        <button
                                            type="button"
                                            wire:click="assignStaff(editAssignedStaffId)"
                                            class="px-3 py-1.5 rounded-lg bg-primary-600 text-white text-xs font-semibold hover:bg-primary-500 transition shrink-0"
                                        >
                                            Assign
                                        </button>
                                    </div>
                                </div>

                                {{-- Follow Up Date --}}
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Schedule Follow-up Date</label>
                                    <div class="flex gap-2">
                                        <input
                                            type="date"
                                            wire:model="editFollowUpDate"
                                            class="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
                                        />
                                        <button
                                            type="button"
                                            wire:click="saveFollowUpDate"
                                            class="px-3 py-1.5 rounded-lg bg-slate-700 text-white text-xs font-semibold hover:bg-slate-600 transition shrink-0"
                                        >
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Internal Staff Notes --}}
                            <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 space-y-3">
                                <h3 class="text-xs uppercase font-bold text-slate-400 tracking-wider">Internal Staff Notes</h3>

                                <div class="space-y-2">
                                    <textarea
                                        wire:model="newInternalNote"
                                        rows="2"
                                        placeholder="Add an internal note or update about this lead..."
                                        class="w-full p-2.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    ></textarea>
                                    <div class="flex justify-end">
                                        <button
                                            type="button"
                                            wire:click="submitInternalNote"
                                            class="px-3 py-1.5 bg-primary-600 text-white text-xs font-semibold rounded-lg hover:bg-primary-500 transition"
                                        >
                                            Add Note
                                        </button>
                                    </div>
                                </div>

                                @if ($selectedInquiry->admin_notes)
                                    <div class="mt-3 p-3 rounded-lg bg-slate-900/80 border border-slate-700 text-xs text-slate-300 whitespace-pre-wrap max-h-40 overflow-y-auto font-mono">
                                        {{ $selectedInquiry->admin_notes }}
                                    </div>
                                @endif
                            </div>

                            {{-- Chronological Activity History Log --}}
                            <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 space-y-3">
                                <h3 class="text-xs uppercase font-bold text-slate-400 tracking-wider">Activity History</h3>
                                <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
                                    @php
                                        $activities = array_reverse($selectedInquiry->activity_history ?? []);
                                    @endphp
                                    @forelse ($activities as $activity)
                                        <div class="text-xs border-l-2 border-primary-500/50 pl-3 py-1 space-y-0.5">
                                            <div class="flex items-center justify-between text-slate-400 text-[10px]">
                                                <span class="font-semibold text-primary-300">{{ $activity['actor_name'] ?? 'System' }}</span>
                                                <span>{{ \Carbon\Carbon::parse($activity['created_at'])->format('M d, H:i') }}</span>
                                            </div>
                                            <p class="text-slate-200 text-xs font-medium">{{ $activity['description'] ?? $activity['action'] }}</p>
                                        </div>
                                    @empty
                                        <div class="text-xs text-slate-500 italic">No activity recorded yet.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-3 bg-slate-850 border-t border-slate-700 flex justify-end">
                    <button
                        type="button"
                        wire:click="closeInquiryModal"
                        class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-xs font-semibold rounded-lg transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
