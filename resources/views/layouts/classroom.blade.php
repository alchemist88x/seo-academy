@props([
    'title' => config('app.name', 'SEO Academy'),
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" data-theme-locked="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ config('app.name', 'SEO Academy') }}</title>

    {{-- Force Light Theme in Video Classroom regardless of stored dashboard preference --}}
    <script>
        (function () {
            function forceLight() {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            }
            forceLight();
            document.addEventListener('alpine:init', forceLight);
            document.addEventListener('DOMContentLoaded', forceLight);
            window.addEventListener('load', forceLight);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body
    class="min-h-screen bg-slate-100/70 text-slate-900 antialiased font-sans selection:bg-indigo-500 selection:text-white"
    x-data
>
    {{-- Main Classroom Shell --}}
    <div class="min-h-screen flex flex-col">
        {{ $slot }}
    </div>

    {{-- Global Toast Container & Livewire Toast Listener --}}
    <x-toast-container />
    <livewire:ui.toast />

    @livewireScripts
    @stack('scripts')
</body>
</html>
