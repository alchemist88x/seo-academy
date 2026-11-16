{{--
    Collapsible Unified Fixed Sidebar Navigation
    - Permanently fixed on the left side of the viewport
    - Independently scrollable navigation list
    - Fully accessible with keyboard navigation, focus indicators, and ARIA labels
    - Role-aware navigation generated via NavigationService
    - Lucide-style SVG icon system via <x-icon />
--}}
@props([
    'collapsed' => false,
])

@php
    $user = auth()->user();
    $activeAcademy = app(\App\Domain\Tenants\Services\TenantContext::class)->getAcademy();
    $brandName = $activeAcademy?->name ?: config('app.name', 'SEO Academy');
    $navigationGroups = app(\App\Domain\Platform\Services\NavigationService::class)->getNavigationForUser($user);
@endphp

<aside
    id="main-sidebar"
    class="sidebar border-r border-slate-800"
    :class="{
        'collapsed': $store?.sidebar?.collapsed,
        'mobile-open': $store?.sidebar?.mobileOpen
    }"
    aria-label="Sidebar Navigation"
    @keydown.escape.window="if ($store?.sidebar?.mobileOpen) $store.sidebar.closeMobile()"
>
    {{-- Brand Header --}}
    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800 shrink-0">
        <a href="{{ url('/') }}" class="flex items-center gap-3 overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded-md">
            <div class="h-9 w-9 rounded-lg bg-primary-600 flex items-center justify-center text-white font-bold shrink-0 shadow-sm">
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($brandName, 0, 1)) }}
            </div>
            <span
                x-show="!$store?.sidebar?.collapsed || $store?.sidebar?.mobileOpen"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="text-base font-bold text-white tracking-tight whitespace-nowrap truncate"
            >
                {{ $brandName }}
            </span>
        </a>

        {{-- Desktop Collapse Toggle --}}
        <button
            type="button"
            @click="$store?.sidebar?.toggleCollapse ? $store.sidebar.toggleCollapse() : null"
            class="hidden md:flex items-center justify-center w-7 h-7 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :title="$store?.sidebar?.collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            :aria-expanded="!$store?.sidebar?.collapsed"
            aria-controls="main-sidebar"
            aria-label="Toggle sidebar collapse"
        >
            <svg
                class="w-4 h-4 transition-transform duration-200"
                :class="{ 'rotate-180': $store?.sidebar?.collapsed }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
        </button>

        {{-- Mobile Close Button --}}
        <button
            type="button"
            @click="$store?.sidebar?.closeMobile ? $store.sidebar.closeMobile() : null"
            class="md:hidden p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            aria-label="Close sidebar navigation"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Navigation Groups --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6" role="navigation">
        @foreach ($navigationGroups as $group)
            <div class="space-y-1">
                @if (!empty($group['title']))
                    <p
                        x-show="!$store?.sidebar?.collapsed || $store?.sidebar?.mobileOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 select-none"
                    >
                        {{ $group['title'] }}
                    </p>
                @endif

                @foreach ($group['items'] as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="sidebar-nav-link {{ $item['active'] ? 'active' : '' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400"
                        :title="$store.sidebar.collapsed && !$store.sidebar.mobileOpen ? '{{ addslashes($item['label']) }}' : ''"
                        @if ($item['active']) aria-current="page" @endif
                        @click="if (window.innerWidth < 768) $store.sidebar.closeMobile()"
                    >
                        <x-icon :name="$item['icon']" class="nav-icon" />
                        <span
                            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            class="truncate"
                        >
                            {{ $item['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Sidebar Footer --}}
    <div class="p-3 border-t border-slate-800 shrink-0">
        <div class="flex items-center gap-3 px-2 py-1.5 text-xs text-slate-400">
            <span class="h-2 w-2 rounded-full bg-emerald-400 shrink-0" aria-hidden="true"></span>
            <span x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen" class="truncate font-medium">
                SEO Academy v1.0
            </span>
        </div>
    </div>
</aside>
