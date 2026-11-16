@props([
    'title' => config('app.name', 'SEO Academy'),
    'theme' => 'light',
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ config('app.name', 'SEO Academy') }}</title>

    {{-- Prevent FOUC on dark/light theme before Alpine initializes --}}
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
<body
    class="min-h-screen bg-surface-bg text-text-primary antialiased"
    :class="{ 'overflow-hidden': $store?.sidebar?.mobileOpen }"
    x-data
>
    {{-- Mobile Sidebar Backdrop --}}
    <div
        x-show="$store?.sidebar?.mobileOpen"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$store.sidebar.closeMobile()"
        class="fixed inset-0 z-30 bg-slate-900/60 backdrop-blur-xs md:hidden"
        style="display: none;"
        aria-hidden="true"
    ></div>

    {{-- Sidebar --}}
    <x-sidebar />

    {{-- Main Content Shell --}}
    <div
        class="main-content"
        :class="{ 'sidebar-collapsed': $store.sidebar.collapsed }"
    >
        {{-- Top Header --}}
        <x-header :title="$title">
            @isset($breadcrumbs)
                <x-slot:breadcrumbs>
                    {{ $breadcrumbs }}
                </x-slot:breadcrumbs>
            @endisset
        </x-header>

        {{-- Page Body --}}
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
            {{ $slot }}
        </main>
    </div>

    {{-- Global Toast Container & Livewire Toast Listener --}}
    <x-toast-container />
    <livewire:ui.toast />

    @livewireScripts
    @stack('scripts')
</body>
</html>
