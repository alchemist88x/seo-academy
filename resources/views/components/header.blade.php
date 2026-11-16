{{--
    Top Application Header
    - Mobile sidebar toggle button
    - Page title or breadcrumbs slot
    - Theme switcher (light / dark / system)
    - Notifications bell
    - User menu dropdown
--}}
@props([
    'title' => 'Dashboard',
])

<header class="app-header justify-between">
    <div class="flex items-center gap-3 min-w-0">
        {{-- Mobile Sidebar Drawer Trigger --}}
        <button
            type="button"
            @click="$store.sidebar.toggleMobile()"
            class="md:hidden p-2 -ml-2 rounded-lg text-text-secondary hover:bg-border-muted hover:text-text-primary transition-colors"
            aria-label="Open navigation menu"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- Title & Breadcrumbs --}}
        <div class="min-w-0">
            @isset($breadcrumbs)
                {{ $breadcrumbs }}
            @else
                <h1 class="text-base sm:text-lg font-semibold text-text-primary truncate">
                    {{ $title }}
                </h1>
            @endisset
        </div>
    </div>

    {{-- Right Controls --}}
    <div class="flex items-center gap-2 shrink-0">
        {{-- Theme Mode Switcher --}}
        <div class="relative" x-data="{ open: false }">
            <button
                type="button"
                @click="open = !open"
                class="p-2 rounded-lg text-text-secondary hover:bg-border-muted hover:text-text-primary transition-colors"
                title="Switch theme"
                aria-label="Theme switcher"
            >
                <svg x-show="!$store.theme.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <svg x-show="$store.theme.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-transition
                class="absolute right-0 mt-2 w-36 rounded-lg bg-surface-card border border-border-default shadow-lg py-1 z-50"
                style="display: none;"
            >
                <button
                    type="button"
                    @click="$store.theme.set('light'); open = false"
                    class="w-full px-3 py-1.5 text-left text-sm flex items-center justify-between hover:bg-border-muted text-text-primary"
                >
                    <span>Light</span>
                    <span x-show="$store.theme.mode === 'light'" class="text-primary-600 font-bold">&check;</span>
                </button>
                <button
                    type="button"
                    @click="$store.theme.set('dark'); open = false"
                    class="w-full px-3 py-1.5 text-left text-sm flex items-center justify-between hover:bg-border-muted text-text-primary"
                >
                    <span>Dark</span>
                    <span x-show="$store.theme.mode === 'dark'" class="text-primary-600 font-bold">&check;</span>
                </button>
                <button
                    type="button"
                    @click="$store.theme.set('system'); open = false"
                    class="w-full px-3 py-1.5 text-left text-sm flex items-center justify-between hover:bg-border-muted text-text-primary"
                >
                    <span>System</span>
                    <span x-show="$store.theme.mode === 'system'" class="text-primary-600 font-bold">&check;</span>
                </button>
            </div>
        </div>

        {{-- Notifications Bell --}}
        @php
            $headerUser = auth()->user();
            $headerUnreadCount = $headerUser
                ? \App\Domain\Notifications\Models\AcademyNotification::forUser($headerUser)->unreadForUser($headerUser)->count()
                : 0;
            $headerRecentNotifications = $headerUser && $headerUnreadCount > 0
                ? \App\Domain\Notifications\Models\AcademyNotification::forUser($headerUser)->unreadForUser($headerUser)->orderBy('created_at', 'desc')->take(4)->get()
                : collect();
        @endphp
        <div class="relative" x-data="{ open: false }">
            <button
                type="button"
                @click="open = !open"
                class="relative p-2 rounded-lg text-text-secondary hover:bg-border-muted hover:text-text-primary transition-colors"
                aria-label="Notifications"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                @if ($headerUnreadCount > 0)
                    <span class="absolute top-1 right-1 min-w-[1.125rem] h-4.5 px-1 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">
                        {{ $headerUnreadCount > 99 ? '99+' : $headerUnreadCount }}
                    </span>
                @endif
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-transition
                class="absolute right-0 mt-2 w-80 rounded-xl bg-surface-card border border-border-default shadow-lg py-2 z-50"
                style="display: none;"
            >
                <div class="px-4 py-2 border-b border-border-default flex items-center justify-between">
                    <span class="text-sm font-semibold text-text-primary">Notifications</span>
                    @if ($headerUnreadCount > 0)
                        <span class="text-xs text-primary-600 font-medium">{{ $headerUnreadCount }} unread</span>
                    @else
                        <span class="text-xs text-text-muted font-medium">All caught up</span>
                    @endif
                </div>

                @if ($headerRecentNotifications->isNotEmpty())
                    <div class="divide-y divide-border-default max-h-64 overflow-y-auto">
                        @foreach ($headerRecentNotifications as $item)
                            <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 hover:bg-surface-elevated transition-colors text-left">
                                <div class="flex items-center justify-between text-xs font-semibold text-text-primary">
                                    <span class="truncate">{{ $item->title }}</span>
                                    <span class="text-[10px] text-text-muted shrink-0">{{ $item->created_at?->diffForHumans(null, true) }}</span>
                                </div>
                                <p class="text-[11px] text-text-secondary mt-0.5 line-clamp-2">
                                    {{ $item->message }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-6 text-center text-xs text-text-muted">
                        No new notifications at the moment.
                    </div>
                @endif

                <div class="px-4 py-2 border-t border-border-default text-center">
                    <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-primary-600 hover:underline">
                        View all notifications &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- User Menu --}}
        <x-user-menu />
    </div>
</header>
