@props([
    'placement' => 'homepage_hero',
    'academyId' => null,
])

@php
    $academy = $academyId
        ? \App\Domain\Academies\Models\Academy::find($academyId)
        : (\App\Domain\Academies\Models\Academy::current() ?? \App\Domain\Academies\Models\Academy::first());

    $banners = $academy
        ? app(\App\Domain\Cms\Services\BannerService::class)->getActiveBanners($academy->id, $placement)
        : collect();
@endphp

@if($banners->isNotEmpty())
    <div class="relative w-full overflow-hidden select-none"
         x-data="{
             activeSlide: 0,
             total: {{ $banners->count() }},
             autoplayTimer: null,
             paused: false,
             startAutoplay() {
                 if (this.total <= 1) return;
                 const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                 if (prefersReduced) return;
                 this.autoplayTimer = setInterval(() => {
                     if (!this.paused) {
                         this.next();
                     }
                 }, 5000);
             },
             stopAutoplay() {
                 if (this.autoplayTimer) clearInterval(this.autoplayTimer);
             },
             next() {
                 this.activeSlide = (this.activeSlide + 1) % this.total;
             },
             prev() {
                 this.activeSlide = (this.activeSlide - 1 + this.total) % this.total;
             },
             goTo(index) {
                 this.activeSlide = index;
             }
         }"
         x-init="startAutoplay()"
         @mouseenter="paused = true"
         @mouseleave="paused = false"
         @keydown.right.window="next()"
         @keydown.left.window="prev()">

        {{-- Slides Container --}}
        <div class="relative w-full">
            @foreach($banners as $index => $banner)
                @php
                    $heightClasses = match($banner->banner_height) {
                        'compact' => 'py-8 sm:py-12 min-h-[220px]',
                        'tall' => 'py-20 sm:py-32 min-h-[480px] sm:min-h-[560px]',
                        default => 'py-12 sm:py-20 min-h-[340px] sm:min-h-[420px]',
                    };

                    $alignClasses = match($banner->text_alignment) {
                        'center' => 'text-center mx-auto items-center',
                        'right' => 'text-right ml-auto items-end',
                        default => 'text-left mr-auto items-start',
                    };

                    $justifyCta = match($banner->text_alignment) {
                        'center' => 'justify-center',
                        'right' => 'justify-end',
                        default => 'justify-start',
                    };
                @endphp

                <div x-show="activeSlide === {{ $index }}"
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 scale-98"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-98"
                     class="relative w-full flex items-center bg-slate-900 {{ $heightClasses }}"
                     style="{{ $index > 0 ? 'display: none;' : '' }}">

                    {{-- Background Imagery --}}
                    @if($banner->desktop_image_path || $banner->background_image_path)
                        <picture class="absolute inset-0 w-full h-full pointer-events-none">
                            @if($banner->mobile_image_path)
                                <source media="(max-width: 640px)" srcset="{{ asset($banner->mobile_image_path) }}">
                            @endif
                            <img src="{{ asset($banner->desktop_image_path ?: $banner->background_image_path) }}"
                                 alt="{{ $banner->heading }}"
                                 class="w-full h-full object-cover">
                        </picture>
                    @endif

                    {{-- Color & Opacity Overlay --}}
                    <div class="absolute inset-0 pointer-events-none"
                         style="background-color: {{ $banner->overlay_color ?: '#000000' }}; opacity: {{ ($banner->overlay_opacity ?? 40) / 100 }};">
                    </div>

                    {{-- Slide Content --}}
                    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex flex-col {{ $alignClasses }}">
                        @if($banner->subtitle)
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-white/20 backdrop-blur-md text-white border border-white/20">
                                {{ $banner->subtitle }}
                            </span>
                        @endif

                        <h2 class="text-2xl sm:text-4xl md:text-5xl font-extrabold tracking-tight max-w-3xl leading-tight"
                            style="color: {{ $banner->text_color ?: '#ffffff' }};">
                            {{ $banner->heading }}
                        </h2>

                        @if($banner->description)
                            <p class="mt-3 text-sm sm:text-base md:text-lg max-w-2xl opacity-90 leading-relaxed"
                               style="color: {{ $banner->text_color ?: '#ffffff' }};">
                                {{ $banner->description }}
                            </p>
                        @endif

                        @if($banner->primary_cta_label || $banner->secondary_cta_label)
                            <div class="mt-6 flex flex-wrap gap-3.5 {{ $justifyCta }}">
                                @if($banner->primary_cta_label && $banner->primary_cta_url)
                                    <a href="{{ $banner->primary_cta_url }}"
                                       class="inline-flex items-center space-x-2 px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm sm:text-base shadow-lg shadow-blue-600/30 transition hover:scale-105 active:scale-95">
                                        <span>{{ $banner->primary_cta_label }}</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </a>
                                @endif

                                @if($banner->secondary_cta_label && $banner->secondary_cta_url)
                                    <a href="{{ $banner->secondary_cta_url }}"
                                       class="inline-flex items-center space-x-2 px-6 py-3 rounded-xl bg-white/15 hover:bg-white/25 text-white backdrop-blur-md border border-white/30 font-semibold text-sm sm:text-base transition hover:scale-105 active:scale-95">
                                        <span>{{ $banner->secondary_cta_label }}</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Next / Prev Controls (if > 1 slide) --}}
        @if($banners->count() > 1)
            <button type="button"
                    @click="prev()"
                    class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-black/30 hover:bg-black/60 text-white backdrop-blur-xs flex items-center justify-center transition shadow-md focus:outline-hidden"
                    aria-label="Previous Slide">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <button type="button"
                    @click="next()"
                    class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-black/30 hover:bg-black/60 text-white backdrop-blur-xs flex items-center justify-center transition shadow-md focus:outline-hidden"
                    aria-label="Next Slide">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Indicators / Dots --}}
            <div class="absolute bottom-4 inset-x-0 z-20 flex justify-center space-x-2">
                @foreach($banners as $index => $banner)
                    <button type="button"
                            @click="goTo({{ $index }})"
                            :class="activeSlide === {{ $index }} ? 'w-8 bg-white' : 'w-2.5 bg-white/50 hover:bg-white/80'"
                            class="h-2.5 rounded-full transition-all duration-300 focus:outline-hidden"
                            aria-label="Go to slide {{ $index + 1 }}">
                    </button>
                @endforeach
            </div>
        @endif
    </div>
@endif
