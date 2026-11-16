@props([
    'title' => config('app.name', 'SEO Academy'),
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ config('app.name', 'SEO Academy') }}</title>

    <script>
        (function () {
            const mode = localStorage.getItem('seo_academy_theme') || 'system';
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = mode === 'dark' || (mode === 'system' && prefersDark);
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.classList.toggle('light', !isDark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="min-h-screen bg-surface-bg text-text-primary flex flex-col antialiased" x-data>
    <header class="border-b border-border-default bg-surface-card">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <div class="h-9 w-9 rounded-lg bg-primary-600 flex items-center justify-center text-white font-bold">
                    S
                </div>
                <span class="text-lg font-bold text-text-primary">{{ config('app.name', 'SEO Academy') }}</span>
            </a>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="$store?.theme?.toggle ? $store.theme.toggle() : null"
                    class="btn btn-ghost p-2 rounded-lg"
                    aria-label="Toggle theme"
                >
                    <svg x-show="!$store?.theme?.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="$store?.theme?.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Sign In</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-border-default bg-surface-card py-6 text-center text-sm text-text-muted">
        &copy; {{ date('Y') }} {{ config('app.name', 'SEO Academy') }}. All rights reserved.
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
