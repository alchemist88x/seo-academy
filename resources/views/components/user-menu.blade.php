{{--
    User Account Dropdown Menu
    - Displays authenticated user avatar/initials, name, and email
    - Links to Profile, Settings, and Logout POST form
--}}
@props([])

@php
    $user = auth()->user();
    $name = $user?->name ?? 'Guest User';
    $email = $user?->email ?? 'guest@seoacademy.local';
    $initials = collect(explode(' ', $name))
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-border-muted transition-colors"
        aria-label="User menu"
    >
        <div class="h-8 w-8 rounded-full bg-primary-600 text-white text-xs font-semibold flex items-center justify-center shrink-0">
            {{ $initials ?: 'U' }}
        </div>
        <div class="hidden sm:block text-left leading-tight">
            <p class="text-sm font-medium text-text-primary truncate max-w-[140px]">{{ $name }}</p>
            <p class="text-[11px] text-text-muted truncate max-w-[140px]">{{ $email }}</p>
        </div>
        <svg class="w-4 h-4 text-text-muted hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 mt-2 w-52 rounded-xl bg-surface-card border border-border-default shadow-lg py-1.5 z-50"
        style="display: none;"
    >
        <div class="px-4 py-2 border-b border-border-default sm:hidden">
            <p class="text-sm font-medium text-text-primary truncate">{{ $name }}</p>
            <p class="text-xs text-text-muted truncate">{{ $email }}</p>
        </div>

        <a
            href="{{ route('profile') }}"
            class="flex items-center gap-2.5 px-4 py-2 text-sm text-text-secondary hover:bg-border-muted hover:text-text-primary"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Profile
        </a>

        @php
            $settingsUrl = auth()->user()?->hasRole('admin')
                ? route('admin.system.settings')
                : route('notifications.preferences');
        @endphp
        <a
            href="{{ $settingsUrl }}"
            class="flex items-center gap-2.5 px-4 py-2 text-sm text-text-secondary hover:bg-border-muted hover:text-text-primary"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Settings
        </a>

        <div class="my-1 border-t border-border-default"></div>

        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sign Out
                </button>
            </form>
        @else
            <a
                href="{{ url('/login') }}"
                class="flex items-center gap-2.5 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
            >
                Sign Out
            </a>
        @endif
    </div>
</div>
