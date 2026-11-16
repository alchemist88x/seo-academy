@props([
    'title' => 'Sign In',
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
<body class="min-h-screen bg-surface-bg text-text-primary flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 antialiased" x-data>
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        {{-- Brand Logo --}}
        <div class="flex justify-center">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <div class="h-10 w-10 rounded-xl bg-primary-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                    S
                </div>
                <span class="text-xl font-bold tracking-tight text-text-primary">
                    {{ config('app.name', 'SEO Academy') }}
                </span>
            </a>
        </div>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="card p-6 sm:p-8">
            {{ $slot }}
        </div>
    </div>

    {{-- Theme Switcher in Corner --}}
    <div class="fixed top-4 right-4">
        <button
            type="button"
            @click="$store.theme.toggle()"
            class="btn btn-ghost p-2 rounded-lg border border-border-default bg-surface-card"
            aria-label="Toggle theme"
        >
            <svg x-show="!$store.theme.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
            <svg x-show="$store.theme.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </button>
    </div>

    <x-toast-container />
    @livewireScripts
    @stack('scripts')
</body>
</html>
