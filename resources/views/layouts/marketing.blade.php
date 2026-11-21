@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'seoData' => [],
    'jsonLd' => null,
])

@php
    $academy = \App\Domain\Academies\Models\Academy::current() ?? \App\Domain\Academies\Models\Academy::first();
    $academyName = $academy?->name ?: config('app.name', 'SEO Academy');
    $tagline = $academy?->setting('tagline') ?: 'Professional Digital Education';
    
    $seo = array_merge([
        'title' => $title ? "{$title} — {$academyName}" : "{$academyName} — {$tagline}",
        'description' => $description ?: "Transform your digital career with hands-on technical SEO, realistic client audit simulations, and verifiable credentials at SEO Academy.",
        'canonical_url' => $canonical ?: url()->current(),
        'og_type' => 'website',
        'og_image' => $ogImage ?: asset('build/assets/og-default.png'),
        'og_title' => $title ? "{$title} — {$academyName}" : "{$academyName} — {$tagline}",
        'og_description' => $description ?: "Practical search engine optimization training, technical site audits, and verified certificates.",
        'og_site_name' => $academyName,
        'robots' => 'index, follow',
    ], $seoData);

    $headerNav = \App\Domain\Cms\Models\CmsNavigationItem::where('academy_id', $academy?->id)
        ->where('menu_location', 'header')
        ->where('is_active', true)
        ->orderBy('display_order')
        ->get();

    $footerNav = \App\Domain\Cms\Models\CmsNavigationItem::where('academy_id', $academy?->id)
        ->where('menu_location', 'footer')
        ->where('is_active', true)
        ->orderBy('display_order')
        ->get();

    $themeService = app(\App\Domain\Cms\Services\ThemeAppearanceService::class);
    $themeCss = $themeService->getCompiledCss($academy?->id);
    $chatbotSetting = $academy ? \App\Domain\Cms\Models\CmsChatbotSetting::getOrCreateForAcademy($academy->id) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Technical SEO Meta Tags --}}
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <link rel="canonical" href="{{ $seo['canonical_url'] }}">

    {{-- Open Graph / Facebook / LinkedIn --}}
    <meta property="og:type" content="{{ $seo['og_type'] }}">
    <meta property="og:url" content="{{ $seo['canonical_url'] }}">
    <meta property="og:title" content="{{ $seo['og_title'] }}">
    <meta property="og:description" content="{{ $seo['og_description'] }}">
    <meta property="og:image" content="{{ $seo['og_image'] }}">
    <meta property="og:site_name" content="{{ $seo['og_site_name'] }}">

    {{-- Twitter / X Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['og_title'] }}">
    <meta name="twitter:description" content="{{ $seo['og_description'] }}">
    <meta name="twitter:image" content="{{ $seo['og_image'] }}">

    {{-- Favicon --}}
    @if($academy?->favicon_path)
        <link rel="icon" type="image/x-icon" href="{{ asset($academy->favicon_path) }}">
    @else
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎓</text></svg>">
    @endif

    {{-- Fonts & Styles --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')

    {{-- Dynamic Website Theme CSS Variables --}}
    <style id="cms-theme-variables">
        {!! $themeCss !!}
    </style>

    {{-- Structured Data (JSON-LD) --}}
    @if(!empty($jsonLd))
        {!! $jsonLd !!}
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 font-sans antialiased flex flex-col selection:bg-blue-600 selection:text-white"
      x-data="{ mobileMenuOpen: false }">

    {{-- Public Marketing Header --}}
    <header class="sticky top-0 z-40 w-full bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-xs transition">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            {{-- Brand Logo & Name --}}
            <div class="flex items-center space-x-8">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    @if($academy?->logo_path)
                        <img src="{{ asset($academy->logo_path) }}" alt="{{ $academyName }}" class="h-10 w-auto object-contain">
                    @else
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-700 via-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-md shadow-blue-500/20 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            </svg>
                        </div>
                    @endif
                    <div>
                        <span class="text-xl font-extrabold tracking-tight text-slate-900 group-hover:text-blue-700 transition">
                            {{ $academyName }}
                        </span>
                        <span class="hidden sm:block text-[11px] font-semibold text-slate-500 uppercase tracking-widest -mt-1">
                            Digital Marketing LMS
                        </span>
                    </div>
                </a>

                {{-- Desktop Navigation Menu --}}
                <nav class="hidden md:flex items-center space-x-1 lg:space-x-2">
                    @if($headerNav->isNotEmpty())
                        @foreach($headerNav as $item)
                            <a href="{{ $item->getEffectiveUrl() }}"
                               target="{{ $item->target }}"
                               class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition {{ request()->is(ltrim($item->getEffectiveUrl(), '/').'*') ? 'text-blue-700 font-bold bg-blue-50' : '' }}">
                                {{ $item->label }}
                            </a>
                        @endforeach
                    @else
                        <a href="{{ route('courses.public.index') }}" class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition">Courses</a>
                        <a href="{{ route('bundles.public.index') }}" class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition">Bundles</a>
                        <a href="{{ url('/services') }}" class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition">Services</a>
                        <a href="{{ url('/about') }}" class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition">About</a>
                        <a href="{{ url('/blog') }}" class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition">Blog</a>
                        <a href="{{ url('/contact') }}" class="px-3.5 py-2 text-sm font-semibold rounded-lg text-slate-600 hover:text-blue-700 hover:bg-slate-100 transition">Contact</a>
                    @endif
                </nav>
            </div>

            {{-- Right CTA & Auth Actions --}}
            <div class="hidden md:flex items-center space-x-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="px-4 py-2 rounded-xl text-sm font-bold bg-slate-900 hover:bg-slate-800 text-white shadow-sm transition flex items-center space-x-2">
                        <span>Go to Dashboard</span>
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="px-4 py-2 rounded-xl text-sm font-bold text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}"
                       class="px-5 py-2.5 rounded-xl text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-600/20 hover:shadow-lg hover:shadow-blue-600/30 transition flex items-center space-x-1.5">
                        <span>Join Academy</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                @endauth
            </div>

            {{-- Mobile Hamburger Button --}}
            <div class="md:hidden flex items-center">
                <button type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="p-2.5 rounded-xl text-slate-700 hover:bg-slate-100 focus:outline-hidden transition"
                        aria-label="Toggle Navigation Menu">
                    <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile Drawer Menu --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3 shadow-xl"
             style="display: none;">
            <nav class="space-y-1">
                @if($headerNav->isNotEmpty())
                    @foreach($headerNav as $item)
                        <a href="{{ $item->getEffectiveUrl() }}"
                           target="{{ $item->target }}"
                           class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">
                            {{ $item->label }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ route('courses.public.index') }}" class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">Courses</a>
                    <a href="{{ route('bundles.public.index') }}" class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">Bundles</a>
                    <a href="{{ url('/services') }}" class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">Services</a>
                    <a href="{{ url('/about') }}" class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">About</a>
                    <a href="{{ url('/blog') }}" class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">Blog</a>
                    <a href="{{ url('/contact') }}" class="block px-3 py-2 rounded-lg text-base font-semibold text-slate-700 hover:text-blue-700 hover:bg-slate-50">Contact</a>
                @endif
            </nav>

            <div class="pt-4 border-t border-slate-100 space-y-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="w-full py-3 px-4 rounded-xl bg-slate-900 text-white font-bold text-center block shadow-sm">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="w-full py-2.5 px-4 rounded-xl border border-slate-300 text-slate-700 font-bold text-center block hover:bg-slate-50">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="w-full py-2.5 px-4 rounded-xl bg-blue-600 text-white font-bold text-center block shadow-md">
                        Join Academy
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Main Page Content --}}
    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- Public Marketing Footer --}}
    <footer class="bg-slate-950 text-slate-400 border-t border-slate-900 mt-20 pt-16 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-slate-900">
                
                {{-- Column 1: Brand & Mission --}}
                <div class="md:col-span-1 space-y-4">
                    <a href="{{ url('/') }}" class="flex items-center space-x-2 text-white font-extrabold text-xl">
                        <span class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white text-sm">SEO</span>
                        <span>{{ $academyName }}</span>
                    </a>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        {{ $academy?->description ?: 'Independent professional academy providing advanced digital marketing curriculum, technical search engineering, and verified credentials.' }}
                    </p>
                    <div class="flex items-center space-x-3 text-xs text-slate-500 pt-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Independent Self-Hosted LMS</span>
                    </div>
                </div>

                {{-- Column 2: Navigation Links --}}
                <div>
                    <h4 class="text-xs font-bold text-slate-200 uppercase tracking-widest mb-4">Academy</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ route('courses.public.index') }}" class="hover:text-white transition">Course Catalogue</a></li>
                        <li><a href="{{ route('bundles.public.index') }}" class="hover:text-white transition">Course Bundles</a></li>
                        <li><a href="{{ url('/services') }}" class="hover:text-white transition">Academy Services</a></li>
                        <li><a href="{{ url('/about') }}" class="hover:text-white transition">About Our Faculty</a></li>
                        <li><a href="{{ url('/blog') }}" class="hover:text-white transition">SEO Articles & Insights</a></li>
                        <li><a href="{{ url('/contact') }}" class="hover:text-white transition">Admissions & Contact</a></li>
                    </ul>
                </div>

                {{-- Column 3: Corporate & Services --}}
                <div>
                    <h4 class="text-xs font-bold text-slate-200 uppercase tracking-widest mb-4">Training Programs</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ url('/services/technical-seo-training') }}" class="hover:text-white transition">Technical SEO Training</a></li>
                        <li><a href="{{ url('/services/corporate-seo-training') }}" class="hover:text-white transition">Corporate Team Coaching</a></li>
                        <li><a href="{{ url('/services/practical-seo-projects') }}" class="hover:text-white transition">Practical Audit Projects</a></li>
                        <li><a href="{{ url('/services/digital-marketing-consulting') }}" class="hover:text-white transition">Advisory Consulting</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white transition">Student Portal Login</a></li>
                    </ul>
                </div>

                {{-- Column 4: Contact & Legal --}}
                <div class="space-y-4">
                    <h4 class="text-xs font-bold text-slate-200 uppercase tracking-widest mb-4">Connect</h4>
                    <p class="text-sm">
                        <strong class="text-white block font-medium">Academic Desk:</strong>
                        <a href="mailto:{{ $academy?->support_email ?: 'admissions@seo-academy.local' }}" class="text-blue-400 hover:underline">
                            {{ $academy?->support_email ?: 'admissions@seo-academy.local' }}
                        </a>
                    </p>
                    @if($academy?->contact_phone)
                        <p class="text-sm">
                            <strong class="text-white block font-medium">Telephone:</strong>
                            <span class="text-slate-300">{{ $academy->contact_phone }}</span>
                        </p>
                    @endif
                    <div class="pt-2 flex flex-wrap gap-x-4 gap-y-2 text-xs">
                        <a href="{{ url('/page/privacy-policy') }}" class="hover:text-white transition">Privacy Policy</a>
                        <a href="{{ url('/page/terms-of-service') }}" class="hover:text-white transition">Terms of Service</a>
                        <a href="{{ url('/sitemap.xml') }}" class="hover:text-white transition">Sitemap XML</a>
                    </div>
                </div>
            </div>

            {{-- Bottom Sub-Footer --}}
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-4">
                <p>&copy; {{ date('Y') }} {{ $academyName }}. All rights reserved.</p>
                <p class="flex items-center space-x-1.5">
                    <svg class="w-4 h-4 text-emerald-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Independent Single-Academy Educational Platform</span>
                </p>
            </div>
        </div>
    </footer>

    {{-- Public Chatbot Widget --}}
    @if($chatbotSetting && $chatbotSetting->is_enabled)
        <livewire:marketing.chatbot-widget :academy-id="$academy?->id" />
    @endif

    @livewireScripts
    @stack('scripts')
</body>
</html>
