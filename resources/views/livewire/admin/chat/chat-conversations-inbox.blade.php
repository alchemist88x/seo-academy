<div class="space-y-6" wire:poll.5s="refreshConversations">
    {{-- Page Header --}}
    <x-page-header
        title="Live Chat Inbox & Human Support Desk"
        subtitle="Manage visitor assistance, human support handoffs, live staff replies, and internal team notes."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('admin.website.chatbot') }}" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Chatbot Settings</span>
                </a>
                <a href="{{ route('admin.website.index') }}" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span>Contact Inquiries</span>
                </a>
                <a href="{{ route('support.tickets.index') }}" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>Support Tickets</span>
                </a>
                <a href="{{ url('/') }}" target="_blank" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <span>Test on Site</span>
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Feedback Message --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-xl {{ $feedbackType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }} flex items-center justify-between text-sm shadow-xs">
            <div class="flex items-center gap-2 font-medium">
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="text-slate-400 hover:text-slate-600">
                &times;
            </button>
        </div>
    @endif

    {{-- KPI Cards Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
        <button type="button" wire:click="setStatusFilter('all')" class="p-3.5 rounded-2xl bg-white border text-left transition {{ $statusFilter === 'all' ? 'border-blue-500 ring-2 ring-blue-100 shadow-xs' : 'border-slate-200 hover:border-slate-300' }}">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Total Chats</span>
            <div class="text-xl font-extrabold text-slate-900 mt-1">{{ number_format($stats['total']) }}</div>
        </button>

        <button type="button" wire:click="setStatusFilter('waiting_for_support')" class="p-3.5 rounded-2xl bg-white border text-left transition {{ $statusFilter === 'waiting_for_support' ? 'border-amber-500 ring-2 ring-amber-100 shadow-xs' : 'border-slate-200 hover:border-slate-300' }}">
            <span class="text-[11px] font-semibold text-amber-600 uppercase tracking-wider block flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Needs Reply</span>
            </span>
            <div class="text-xl font-extrabold text-amber-700 mt-1">{{ number_format($stats['waiting']) }}</div>
        </button>

        <button type="button" wire:click="setStatusFilter('ai')" class="p-3.5 rounded-2xl bg-white border text-left transition {{ $statusFilter === 'ai' ? 'border-blue-500 ring-2 ring-blue-100 shadow-xs' : 'border-slate-200 hover:border-slate-300' }}">
            <span class="text-[11px] font-semibold text-blue-600 uppercase tracking-wider block flex items-center gap-1.5">
                <span>🤖 AI Handled</span>
            </span>
            <div class="text-xl font-extrabold text-blue-700 mt-1">{{ number_format($stats['ai'] ?? 0) }}</div>
        </button>

        <button type="button" wire:click="setStatusFilter('human')" class="p-3.5 rounded-2xl bg-white border text-left transition {{ $statusFilter === 'human' ? 'border-purple-500 ring-2 ring-purple-100 shadow-xs' : 'border-slate-200 hover:border-slate-300' }}">
            <span class="text-[11px] font-semibold text-purple-600 uppercase tracking-wider block flex items-center gap-1.5">
                <span>👤 Human Handled</span>
            </span>
            <div class="text-xl font-extrabold text-purple-700 mt-1">{{ number_format($stats['human'] ?? 0) }}</div>
        </button>

        <button type="button" wire:click="setStatusFilter('fallback')" class="p-3.5 rounded-2xl bg-white border text-left transition {{ $statusFilter === 'fallback' ? 'border-slate-500 ring-2 ring-slate-100 shadow-xs' : 'border-slate-200 hover:border-slate-300' }}">
            <span class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider block">Deterministic</span>
            <div class="text-xl font-extrabold text-slate-700 mt-1">{{ number_format($stats['fallback'] ?? 0) }}</div>
        </button>

        <button type="button" wire:click="setStatusFilter('resolved')" class="p-3.5 rounded-2xl bg-white border text-left transition {{ $statusFilter === 'resolved' ? 'border-emerald-500 ring-2 ring-emerald-100 shadow-xs' : 'border-slate-200 hover:border-slate-300' }}">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider block">Resolved</span>
            <div class="text-xl font-extrabold text-emerald-700 mt-1">{{ number_format($stats['resolved']) }}</div>
        </button>
    </div>

    {{-- Main Split View: Left List + Right Detail --}}
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden flex flex-col md:flex-row h-[780px]">
        {{-- Left List Column --}}
        <div class="w-full md:w-80 lg:w-96 border-b md:border-b-0 md:border-r border-slate-200 flex flex-col shrink-0 bg-slate-50/50">
            {{-- Search Bar --}}
            <div class="p-3.5 border-b border-slate-200 bg-white space-y-2.5">
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search visitor, email, query..."
                           class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                {{-- Status & Handler Filter Pills --}}
                <div class="flex items-center gap-1 overflow-x-auto pb-1 text-xs select-none">
                    <button type="button"
                            wire:click="setStatusFilter('all')"
                            class="px-2.5 py-1 rounded-lg font-medium transition {{ $statusFilter === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All
                    </button>
                    <button type="button"
                            wire:click="setStatusFilter('waiting_for_support')"
                            class="px-2.5 py-1 rounded-lg font-medium transition {{ $statusFilter === 'waiting_for_support' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                        Needs Reply
                    </button>
                    <button type="button"
                            wire:click="setStatusFilter('ai')"
                            class="px-2.5 py-1 rounded-lg font-medium transition {{ $statusFilter === 'ai' ? 'bg-blue-600 text-white shadow-xs' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' }}">
                        AI
                    </button>
                    <button type="button"
                            wire:click="setStatusFilter('human')"
                            class="px-2.5 py-1 rounded-lg font-medium transition {{ $statusFilter === 'human' ? 'bg-purple-600 text-white shadow-xs' : 'bg-purple-50 text-purple-800 hover:bg-purple-100' }}">
                        Human
                    </button>
                    <button type="button"
                            wire:click="setStatusFilter('fallback')"
                            class="px-2.5 py-1 rounded-lg font-medium transition {{ $statusFilter === 'fallback' ? 'bg-slate-700 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        Fallback
                    </button>
                    <button type="button"
                            wire:click="setStatusFilter('resolved')"
                            class="px-2.5 py-1 rounded-lg font-medium transition {{ $statusFilter === 'resolved' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}">
                        Resolved
                    </button>
                </div>
            </div>

            {{-- Conversation Items Scrollable List --}}
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                @forelse($conversations as $conv)
                    @php
                        $isSelected = $selectedConversationId === $conv->id;
                        $latestMsg = $conv->messages->first();
                        $unreadCount = $conv->messages()->where('is_read', false)->where('is_internal', false)->count();
                    @endphp
                    <div wire:click="selectConversation('{{ $conv->id }}')"
                         class="p-3.5 cursor-pointer transition flex flex-col gap-1.5 select-none {{ $isSelected ? 'bg-blue-50/80 border-l-4 border-blue-600' : 'hover:bg-slate-100/70 border-l-4 border-transparent' }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-xs text-slate-900 truncate">
                                {{ $conv->visitor_name ?: ($conv->visitor_email ?: 'Visitor #'.substr($conv->id, 0, 8)) }}
                            </span>
                            <span class="text-[10px] text-slate-400 shrink-0">
                                {{ $conv->last_activity_at ? $conv->last_activity_at->diffForHumans(null, true) : $conv->created_at->diffForHumans(null, true) }}
                            </span>
                        </div>

                        {{-- Snippet of latest message --}}
                        <p class="text-[11px] text-slate-500 line-clamp-1 leading-snug">
                            {{ $latestMsg?->message ?: 'Conversation started' }}
                        </p>

                        {{-- Status & Handler Pills --}}
                        <div class="flex items-center justify-between gap-1.5 pt-1 text-[10px]">
                            <div class="flex items-center gap-1 flex-wrap">
                                {{-- Handler Pill --}}
                                @if(($conv->handler ?? 'ai') === 'human')
                                    <span class="px-1.5 py-0.5 rounded font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                        👤 Human
                                    </span>
                                @elseif(($conv->handler ?? 'ai') === 'fallback')
                                    <span class="px-1.5 py-0.5 rounded font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        📄 Fallback
                                    </span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                        🤖 AI
                                    </span>
                                @endif

                                {{-- Lifecycle Status --}}
                                @if($conv->status === 'waiting_for_support')
                                    <span class="px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>Waiting</span>
                                    </span>
                                @elseif($conv->status === 'assigned')
                                    <span class="px-2 py-0.5 rounded-full font-semibold bg-indigo-100 text-indigo-800">
                                        Assigned
                                    </span>
                                @elseif($conv->status === 'waiting_for_visitor')
                                    <span class="px-2 py-0.5 rounded-full font-semibold bg-sky-100 text-sky-800">
                                        Replied
                                    </span>
                                @elseif($conv->status === 'resolved')
                                    <span class="px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">
                                        Resolved
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full font-semibold bg-slate-200 text-slate-700">
                                        Open
                                    </span>
                                @endif

                                {{-- Handoff Reason Pill --}}
                                @if($conv->handoff_reason)
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-amber-50 text-amber-900 border border-amber-200" title="Handoff reason: {{ $conv->handoff_reason }}">
                                        {{ str_replace('_', ' ', $conv->handoff_reason) }}
                                    </span>
                                @endif
                            </div>

                            @if($unreadCount > 0 && ! $isSelected)
                                <span class="px-1.5 py-0.2 bg-blue-600 text-white font-bold rounded-full text-[9px]">
                                    {{ $unreadCount }}
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400 space-y-2">
                        <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-xs">No conversations match the current filter.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Center Detail Column --}}
        @if($selected)
            <div class="flex-1 flex flex-col overflow-hidden bg-white">
                {{-- Detail Header --}}
                <div class="p-4 border-b border-slate-200 bg-white flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-slate-700 text-sm">
                            {{ strtoupper(substr($selected->visitor_name ?: ($selected->visitor_email ?: 'V'), 0, 2)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-sm text-slate-900">
                                    {{ $selected->visitor_name ?: 'Visitor #'.substr($selected->id, 0, 8) }}
                                </h3>
                                @if(($selected->handler ?? 'ai') === 'human')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                        👤 Human Handler
                                    </span>
                                @elseif(($selected->handler ?? 'ai') === 'fallback')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        📄 Fallback Handler
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                        🤖 AI Handler
                                    </span>
                                @endif

                                @if($selected->handoff_reason)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-900 border border-amber-200" title="Handoff reason">
                                        Reason: {{ str_replace('_', ' ', $selected->handoff_reason) }}
                                    </span>
                                @endif

                                @if($selected->leadInquiry)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        Lead Captured
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 flex items-center gap-3">
                                @if($selected->visitor_email)
                                    <span>{{ $selected->visitor_email }}</span>
                                @endif
                                @if($selected->visitor_phone)
                                    <span>{{ $selected->visitor_phone }}</span>
                                @endif
                                <span class="text-slate-400 font-mono text-[10px]">Session: {{ substr($selected->session_id, 0, 12) }}...</span>
                            </div>
                        </div>
                    </div>

                    {{-- Actions: Status, Assign, Operating Mode, Takeover --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Take Over / Return to Auto Button --}}
                        @if(($selected->handler ?? 'ai') !== 'human')
                            <button type="button" wire:click="takeOverConversation" class="btn btn-xs bg-purple-600 hover:bg-purple-700 text-white font-medium shadow-xs">
                                Take Over Conversation
                            </button>
                        @elseif(auth()->user()->hasRole('admin'))
                            <button type="button" wire:click="returnToAutoMode" class="btn btn-secondary btn-xs text-blue-700 border-blue-200 hover:bg-blue-50 font-medium">
                                Return to Auto
                            </button>
                        @endif

                        {{-- Mode Selector --}}
                        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs">
                            <button type="button"
                                    wire:click="setOperatingMode('auto')"
                                    title="Automatic AI/Human Selection"
                                    class="px-2 py-1 rounded-lg font-medium transition {{ $selected->operating_mode === 'auto' ? 'bg-white shadow-xs text-blue-700 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                                Auto
                            </button>
                            <button type="button"
                                    wire:click="setOperatingMode('human_support')"
                                    title="Human Support Desk Mode"
                                    class="px-2 py-1 rounded-lg font-medium transition {{ $selected->operating_mode === 'human_support' ? 'bg-white shadow-xs text-purple-700 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                                Staff Mode
                            </button>
                            <button type="button"
                                    wire:click="setOperatingMode('fallback_only')"
                                    title="Deterministic Non-AI Fallback"
                                    class="px-2 py-1 rounded-lg font-medium transition {{ $selected->operating_mode === 'fallback_only' ? 'bg-white shadow-xs text-slate-900 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                                Fallback
                            </button>
                            <button type="button"
                                    wire:click="setOperatingMode('ai_enabled')"
                                    title="AI Generative Enabled"
                                    class="px-2 py-1 rounded-lg font-medium transition {{ $selected->operating_mode === 'ai_enabled' ? 'bg-white shadow-xs text-blue-700 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                                AI
                            </button>
                        </div>

                        {{-- Assign Selector --}}
                        @if(auth()->user()->hasRole('admin'))
                            <select wire:change="assignToUser($event.target.value)" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 font-medium">
                                <option value="">-- Unassigned --</option>
                                @foreach($staffUsers as $staff)
                                    <option value="{{ $staff->id }}" {{ $selected->assigned_to_user_id === $staff->id ? 'selected' : '' }}>
                                        Assigned: {{ $staff->name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif(! $selected->assigned_to_user_id)
                            <button type="button" wire:click="assignToMe" class="btn btn-secondary btn-xs">
                                Assign to Me
                            </button>
                        @else
                            <span class="text-xs text-slate-600 font-medium px-2 py-1 bg-slate-100 rounded-lg">
                                Assigned to {{ $selected->assignedTo?->name }}
                            </span>
                        @endif

                        {{-- Resolve / Reopen Button --}}
                        @if($selected->status === 'resolved')
                            <button type="button" wire:click="updateStatus('open')" class="btn btn-secondary btn-xs text-blue-700 border-blue-200 hover:bg-blue-50">
                                Reopen Chat
                            </button>
                        @else
                            <button type="button" wire:click="updateStatus('resolved')" class="btn btn-secondary btn-xs text-emerald-700 border-emerald-200 hover:bg-emerald-50">
                                Mark Resolved
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Message Thread Scrollable Feed --}}
                <div class="flex-1 p-5 overflow-y-auto space-y-4 bg-slate-50/60"
                     x-data="{ scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight } }"
                     x-init="scrollToBottom()"
                     x-effect="$wire.selectedConversationId; setTimeout(() => scrollToBottom(), 50)">
                    @foreach($selected->messages as $msg)
                        @if($msg->is_internal)
                            {{-- Internal Staff Note --}}
                            <div class="p-3.5 bg-amber-50/90 border border-amber-300 rounded-2xl space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-[11px] text-amber-900 font-semibold">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        <span>Internal Staff Note &mdash; {{ $msg->sender_name ?: $msg->sender?->name }}</span>
                                    </span>
                                    <span class="text-amber-700 font-normal">{{ $msg->created_at->format('M d, H:i') }}</span>
                                </div>
                                <p class="text-xs text-amber-950 leading-relaxed whitespace-pre-line pl-5">
                                    {{ $msg->message }}
                                </p>
                            </div>
                        @elseif($msg->sender_type === 'visitor')
                            {{-- Visitor Message --}}
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-xl bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0">
                                    👤
                                </div>
                                <div class="max-w-[80%] space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">{{ $msg->sender_name ?: 'Visitor' }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $msg->created_at->format('H:i') }}</span>
                                    </div>
                                    <div class="bg-white border border-slate-200 rounded-2xl rounded-tl-xs px-4 py-2.5 text-xs text-slate-800 shadow-2xs leading-relaxed whitespace-pre-line">
                                        {{ $msg->message }}
                                    </div>
                                </div>
                            </div>
                        @elseif($msg->isFromStaff())
                            {{-- Human Staff Reply --}}
                            <div class="flex items-start justify-end space-x-3">
                                <div class="max-w-[80%] space-y-1 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-[10px] text-slate-400">{{ $msg->created_at->format('H:i') }}</span>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800">Support Agent</span>
                                        <span class="text-xs font-bold text-emerald-900">{{ $msg->sender_name ?: $msg->sender?->name }}</span>
                                    </div>
                                    <div class="bg-emerald-600 text-white rounded-2xl rounded-tr-xs px-4 py-2.5 text-xs shadow-xs text-left leading-relaxed whitespace-pre-line">
                                        {{ $msg->message }}
                                    </div>
                                </div>
                                <div class="w-8 h-8 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                                    👨‍💼
                                </div>
                            </div>
                        @else
                            {{-- Bot / Automated Response --}}
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                                    🎓
                                </div>
                                <div class="max-w-[80%] space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-blue-900">{{ $msg->sender_name ?: 'SEO Academy Assistant' }}</span>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold uppercase tracking-wider bg-blue-100 text-blue-800">{{ $msg->source }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $msg->created_at->format('H:i') }}</span>
                                    </div>
                                    <div class="bg-white border border-blue-200/80 rounded-2xl rounded-tl-xs px-4 py-2.5 text-xs text-slate-800 shadow-2xs leading-relaxed whitespace-pre-line">
                                        {{ $msg->message }}

                                        @if(!empty($msg->structured_data['course_cards']))
                                            <div class="mt-2 space-y-1.5 pt-2 border-t border-slate-100">
                                                @foreach($msg->structured_data['course_cards'] as $course)
                                                    <div class="p-2 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between text-[11px]">
                                                        <span class="font-bold text-slate-800">{{ $course['title'] }}</span>
                                                        <span class="text-blue-600 font-extrabold">{{ $course['price'] ?? 'Free' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Composer Section: Reply vs Internal Note --}}
                <div class="p-4 border-t border-slate-200 bg-white space-y-3">
                    <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
                        <button type="button"
                                wire:click="$set('composerTab', 'reply')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $composerTab === 'reply' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'text-slate-600 hover:bg-slate-100' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                            <span>Reply to Visitor</span>
                        </button>
                        <button type="button"
                                wire:click="$set('composerTab', 'note')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $composerTab === 'note' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'text-slate-600 hover:bg-slate-100' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span>Internal Staff Note</span>
                        </button>
                    </div>

                    @if($composerTab === 'reply')
                        {{-- Visitor Reply Composer Form --}}
                        <form wire:submit.prevent="sendReply" class="space-y-2">
                            <textarea wire:model="replyMessage"
                                      rows="3"
                                      placeholder="Type your response to the visitor... (This message will be instantly sent to their live chat widget)"
                                      class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500"></textarea>
                            @error('replyMessage') <span class="text-red-500 text-[11px] block">{{ $message }}</span> @enderror

                            <div class="flex items-center justify-between">
                                <span class="text-[11px] text-slate-500">
                                    Sending this reply will change status to <strong class="text-sky-700">Replied (Waiting for Visitor)</strong>.
                                </span>
                                <button type="submit" class="btn btn-primary btn-sm bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs">
                                    Send Reply to Visitor &rarr;
                                </button>
                            </div>
                        </form>
                    @else
                        {{-- Internal Note Composer Form --}}
                        <form wire:submit.prevent="addNote" class="space-y-2">
                            <textarea wire:model="internalNote"
                                      rows="3"
                                      placeholder="Write an internal note for team members (visible only to admin and support staff)..."
                                      class="w-full px-3 py-2 text-xs bg-amber-50/50 border border-amber-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500"></textarea>
                            @error('internalNote') <span class="text-red-500 text-[11px] block">{{ $message }}</span> @enderror

                            <div class="flex items-center justify-between">
                                <span class="text-[11px] text-amber-800 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    <span>Private note — never visible to visitors or students.</span>
                                </span>
                                <button type="submit" class="btn btn-secondary btn-sm bg-amber-600 hover:bg-amber-700 text-white shadow-xs border-amber-600">
                                    Save Internal Note
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Right Info Sidebar Panel (Hidden on small screens) --}}
            <div class="w-72 border-l border-slate-200 p-4 bg-slate-50/40 hidden xl:flex flex-col justify-between overflow-y-auto space-y-4 shrink-0 text-xs">
                <div class="space-y-4">
                    <div>
                        <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px] mb-2">Visitor Profile</h4>
                        <div class="bg-white border border-slate-200 rounded-xl p-3 space-y-2">
                            <div>
                                <span class="text-slate-400 block text-[10px]">Name</span>
                                <span class="font-semibold text-slate-800">{{ $selected->visitor_name ?: 'Not provided' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px]">Email</span>
                                <span class="font-semibold text-slate-800">{{ $selected->visitor_email ?: 'Not provided' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px]">Phone</span>
                                <span class="font-semibold text-slate-800">{{ $selected->visitor_phone ?: 'Not provided' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px]">Session ID</span>
                                <span class="font-mono text-[10px] text-slate-600 break-all">{{ $selected->session_id }}</span>
                            </div>
                        </div>
                    </div>

                    @if($selected->leadInquiry)
                        <div>
                            <h4 class="font-bold text-indigo-900 uppercase tracking-wider text-[11px] mb-2">Admissions Lead</h4>
                            <div class="bg-indigo-50/70 border border-indigo-200 rounded-xl p-3 space-y-1.5">
                                <span class="font-bold text-indigo-950 block">{{ $selected->leadInquiry->subject }}</span>
                                <p class="text-[11px] text-indigo-800 leading-snug">{{ Str::limit($selected->leadInquiry->message, 120) }}</p>
                                <a href="{{ route('admin.website.index') }}" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-semibold inline-block pt-1">
                                    View Full Lead &rarr;
                                </a>
                            </div>
                        </div>
                    @endif

                    <div>
                        <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px] mb-2">Conversation Lifecycle</h4>
                        <div class="bg-white border border-slate-200 rounded-xl p-3 space-y-2">
                            <div>
                                <span class="text-slate-400 block text-[10px]">Current Status</span>
                                <span class="font-bold uppercase tracking-wider text-[10px] text-slate-800">{{ str_replace('_', ' ', $selected->status) }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px]">Started</span>
                                <span class="text-slate-700">{{ $selected->created_at->format('M d, Y H:i') }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px]">Last Activity</span>
                                <span class="text-slate-700">{{ $selected->last_activity_at ? $selected->last_activity_at->diffForHumans() : 'N/A' }}</span>
                            </div>
                            @if($selected->resolved_at)
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Resolved At</span>
                                    <span class="text-emerald-700 font-medium">{{ $selected->resolved_at->format('M d, Y H:i') }} (by {{ $selected->resolvedBy?->name ?: 'Staff' }})</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Quick Actions footer --}}
                <div class="pt-3 border-t border-slate-200 space-y-2">
                    <button type="button" wire:click="updateStatus('resolved')" class="w-full btn btn-secondary btn-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50">
                        Mark Resolved
                    </button>
                    @if($selected->status === 'resolved')
                        <button type="button" wire:click="updateStatus('open')" class="w-full btn btn-secondary btn-xs text-blue-700 border-blue-300 hover:bg-blue-50">
                            Reopen Conversation
                        </button>
                    @endif
                </div>
            </div>
        @else
            {{-- Empty State when no conversation selected --}}
            <div class="flex-1 flex flex-col items-center justify-center p-12 text-center bg-slate-50/30">
                <div class="w-16 h-16 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 mb-4 shadow-xs">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-base text-slate-800">Select a Conversation</h3>
                <p class="text-xs text-slate-500 max-w-sm mt-1">
                    Choose a visitor thread from the list on the left to review their query, join as a live human advisor, or record internal team notes.
                </p>
            </div>
        @endif
    </div>
</div>
