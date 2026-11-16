<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Notification Center</h1>
            <p class="text-sm text-text-muted mt-1">
                Stay updated with academy announcements, course assignments, enrollments, and support activities.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    wire:loading.attr="disabled"
                    class="btn btn-secondary text-sm flex items-center gap-2"
                >
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Mark all as read</span>
                </button>
            @endif

            <a href="{{ route('notifications.preferences') }}" class="btn btn-secondary text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Preferences</span>
            </a>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card p-4 space-y-4 sm:space-y-0 sm:flex sm:items-center sm:justify-between gap-4">
        {{-- Category Pills --}}
        <div class="flex flex-wrap items-center gap-1.5">
            <button
                type="button"
                wire:click="$set('filter', 'all')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $filter === 'all' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
            >
                All
            </button>
            <button
                type="button"
                wire:click="$set('filter', 'unread')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 {{ $filter === 'unread' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
            >
                <span>Unread</span>
                @if ($unreadCount > 0)
                    <span class="inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $filter === 'unread' ? 'bg-white text-primary-700' : 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-300' }}">
                        {{ $unreadCount }}
                    </span>
                @endif
            </button>
            <button
                type="button"
                wire:click="$set('filter', 'enrollment')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $filter === 'enrollment' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
            >
                Enrollments
            </button>
            <button
                type="button"
                wire:click="$set('filter', 'support')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $filter === 'support' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
            >
                Support
            </button>
            <button
                type="button"
                wire:click="$set('filter', 'course')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $filter === 'course' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
            >
                Courses
            </button>
            <button
                type="button"
                wire:click="$set('filter', 'system')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $filter === 'system' ? 'bg-primary-600 text-white' : 'bg-surface-elevated text-text-secondary hover:text-text-primary hover:bg-border-muted' }}"
            >
                System
            </button>
        </div>

        {{-- Search Input --}}
        <div class="relative sm:w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search notifications..."
                class="input pl-9 text-xs"
            >
            <svg class="w-4 h-4 text-text-muted absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
    </div>

    {{-- Notification List --}}
    <div class="space-y-3">
        @forelse ($notifications as $notification)
            @php
                $isRead = isset($readNotificationIds[$notification->id]);
            @endphp
            <div
                wire:key="notification-{{ $notification->id }}"
                class="card p-4 transition-all hover:border-border-muted flex items-start gap-4 {{ $isRead ? 'opacity-75 bg-surface-card/60' : 'bg-surface-card border-l-4 border-l-primary-600' }}"
            >
                {{-- Category Icon --}}
                <div class="shrink-0 p-2.5 rounded-xl {{ $isRead ? 'bg-surface-elevated text-text-muted' : 'bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400' }}">
                    @if ($notification->category === 'enrollment')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    @elseif ($notification->category === 'support')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    @elseif ($notification->category === 'course')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    @else
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @endif
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold text-text-primary truncate">
                            {{ $notification->title }}
                        </h2>
                        <span class="text-xs text-text-muted shrink-0" title="{{ $notification->created_at }}">
                            {{ $notification->created_at?->diffForHumans() }}
                        </span>
                    </div>

                    <p class="text-xs text-text-secondary mt-1 whitespace-pre-line leading-relaxed">
                        {{ $notification->message }}
                    </p>

                    <div class="mt-2.5 flex items-center justify-between">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider bg-surface-elevated text-text-muted">
                            {{ $notification->category ?? 'General' }}
                        </span>

                        @if (! $isRead)
                            <button
                                type="button"
                                wire:click="markAsRead('{{ $notification->id }}')"
                                class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline flex items-center gap-1"
                            >
                                <span>Mark as read</span>
                            </button>
                        @else
                            <span class="text-[11px] text-text-muted flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Read</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="card p-12 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-elevated text-text-muted mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-text-primary">No notifications found</h3>
                <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">
                    {{ $filter === 'unread' ? 'You have no unread notifications right now.' : 'There are no notifications matching your current filter.' }}
                </p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($notifications->hasPages())
        <div class="pt-2">
            {{ $notifications->links() }}
        </div>
    @endif
</div>

