<x-layouts.marketing :seoData="$seoData" :jsonLd="$jsonLd">
    @php
        $hero = $sections->get('hero');
        $aboutSec = $sections->get('about');
        $benefitsSec = $sections->get('benefits');
        $faqSec = $sections->get('faq');
        $ctaSec = $sections->get('cta');
    @endphp

    {{-- TOP BANNER HERO CAROUSEL (IF CONFIGURED) --}}
    <x-marketing.banner-carousel placement="homepage_hero" />

    {{-- HERO SECTION --}}
    @if($hero && $hero->is_enabled)
    <section class="relative overflow-hidden bg-gradient-to-b from-blue-50/60 via-white to-slate-50 pt-16 pb-20 lg:pt-24 lg:pb-32 border-b border-slate-200/80">
        {{-- Background decorative grid pattern --}}
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#1e40af 1px, transparent 1px); background-size: 24px 24px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                
                {{-- Left Column: Hero Text --}}
                <div class="lg:col-span-7 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-100/80 text-blue-800 text-xs sm:text-sm font-semibold tracking-wide mb-6 border border-blue-200">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                        {{ $hero->content_data['badge'] ?? 'Specialized Single-Academy Digital Campus' }}
                    </div>
                    
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 tracking-tight leading-[1.15] mb-6">
                        {{ $hero->title ?: 'Master Technical SEO with Hands-On Real-World Audits' }}
                    </h1>
                    
                    <p class="text-lg sm:text-xl text-slate-600 mb-8 max-w-2xl mx-auto lg:mx-0 font-normal leading-relaxed">
                        {{ $hero->subtitle ?: 'Accelerate your digital marketing career with rigorous curriculum, simulation sandbox audits, personalized trainer reviews, and verified cryptographic certificates.' }}
                    </p>
                    
                    <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                        <a href="{{ route('courses.public.index') }}" 
                           class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition duration-150 text-center flex items-center justify-center gap-2 group">
                            <span>{{ $hero->content_data['primary_button_text'] ?? 'Explore Courses' }}</span>
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                        <a href="{{ url('/contact') }}" 
                           class="w-full sm:w-auto px-8 py-3.5 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 font-semibold rounded-xl border border-slate-300 shadow-xs transition duration-150 text-center">
                            {{ $hero->content_data['secondary_button_text'] ?? 'Inquire for Enterprise' }}
                        </a>
                    </div>

                    {{-- Highlights under buttons --}}
                    <div class="mt-10 pt-8 border-t border-slate-200/60 grid grid-cols-3 gap-4 text-center lg:text-left">
                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400 tracking-wider">Methodology</div>
                            <div class="text-sm font-semibold text-slate-800 mt-1">Audit-First Lab</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400 tracking-wider">Instruction</div>
                            <div class="text-sm font-semibold text-slate-800 mt-1">Direct Trainer Feedback</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400 tracking-wider">Credential</div>
                            <div class="text-sm font-semibold text-slate-800 mt-1">Verifiable QR Badges</div>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Interactive Visual Card --}}
                <div class="lg:col-span-5 relative">
                    <div class="relative mx-auto max-w-md lg:max-w-none">
                        <div class="bg-gradient-to-tr from-blue-600 to-indigo-700 rounded-2xl p-1 shadow-2xl shadow-blue-600/20 transform rotate-1 hover:rotate-0 transition duration-300">
                            <div class="bg-slate-900 rounded-[14px] p-6 text-white">
                                <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                        <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                    </div>
                                    <span class="text-xs font-mono text-slate-400">seo_audit_engine.cli</span>
                                </div>
                                <div class="space-y-3 font-mono text-xs sm:text-sm">
                                    <p class="text-emerald-400">$ seo-academy analyze --target=production-site</p>
                                    <p class="text-slate-400">> Crawling 2,480 URLs with JavaScript hydration...</p>
                                    <div class="p-3 bg-slate-800/80 rounded border border-slate-700 space-y-1.5">
                                        <div class="flex justify-between text-xs">
                                            <span class="text-slate-300">Core Web Vitals LCP:</span>
                                            <span class="text-emerald-400 font-bold">1.4s (Good)</span>
                                        </div>
                                        <div class="flex justify-between text-xs">
                                            <span class="text-slate-300">Schema.org JSON-LD:</span>
                                            <span class="text-emerald-400 font-bold">Valid Course & Org</span>
                                        </div>
                                        <div class="flex justify-between text-xs">
                                            <span class="text-slate-300">Canonical Consistency:</span>
                                            <span class="text-emerald-400 font-bold">100% Match</span>
                                        </div>
                                    </div>
                                    <p class="text-blue-300 font-semibold">> Student Project Lab: Audit Complete.</p>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 p-2 bg-blue-950/60 border border-blue-800/60 rounded-lg">
                                            <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center font-bold text-white text-xs">A+</div>
                                            <div class="text-xs">
                                                <div class="text-white font-medium">Trainer Evaluation Status</div>
                                                <div class="text-blue-300 text-[11px]">Approved for Verified Portfolio Export</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
    @endif

    {{-- LIVE STATS BAR (Transparent real DB statistics, zero fake numbers) --}}
    <section class="bg-white border-b border-slate-200 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8">
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $liveStats['courses'] }}</div>
                        <div class="text-xs sm:text-sm text-slate-500 font-medium">Published Courses</div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $liveStats['modules'] }}</div>
                        <div class="text-xs sm:text-sm text-slate-500 font-medium">Curriculum Modules</div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $liveStats['lessons'] }}</div>
                        <div class="text-xs sm:text-sm text-slate-500 font-medium">Practical Lessons</div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $liveStats['enrollments'] }}</div>
                        <div class="text-xs sm:text-sm text-slate-500 font-medium">Active Enrollments</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURED COURSES SHOWCASE --}}
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-12">
                <div>
                    <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Curated Catalog</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2">Explore Industry-Ready Courses</h2>
                    <p class="text-slate-600 mt-2 max-w-xl">From core crawlability diagnostics to advanced content optimization strategies.</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <a href="{{ route('courses.public.index') }}" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-700 font-semibold text-sm group">
                        <span>View All Courses</span>
                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>

            @if($featuredCourses->isEmpty())
                <div class="p-12 text-center bg-white rounded-2xl border border-slate-200">
                    <p class="text-slate-500">No public courses are currently listed. Please check back shortly or contact our academic desk.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($featuredCourses as $course)
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group">
                            {{-- Course Thumbnail --}}
                            <div class="aspect-video w-full bg-slate-100 relative overflow-hidden">
                                @if($course->thumbnail_path)
                                    <img src="{{ asset($course->thumbnail_path) }}" alt="{{ $course->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-blue-700 to-indigo-900 flex items-center justify-center p-6 text-white text-center">
                                        <div>
                                            <svg class="w-12 h-12 mx-auto text-blue-300 opacity-60 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                            </svg>
                                            <span class="text-xs uppercase tracking-wider font-semibold text-blue-200">SEO Academy Curriculum</span>
                                        </div>
                                    </div>
                                @endif
                                <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                    @if($course->category)
                                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-white/95 text-slate-800 shadow-xs backdrop-blur-xs">
                                            {{ $course->category->name }}
                                        </span>
                                    @endif
                                    <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-600 text-white shadow-xs">
                                        {{ ucfirst($course->difficulty_level ?? 'All Levels') }}
                                    </span>
                                </div>
                            </div>

                            {{-- Card Body --}}
                            <div class="p-6 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-2">
                                        <a href="{{ route('courses.public.show', $course->slug) }}">
                                            {{ $course->title }}
                                        </a>
                                    </h3>
                                    
                                    <p class="text-sm text-slate-600 mt-2 line-clamp-2">
                                        {{ $course->marketing_description ?: $course->description }}
                                    </p>

                                    {{-- Module / Lesson stats --}}
                                    <div class="mt-4 flex items-center gap-4 text-xs text-slate-500 font-medium">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                            {{ $course->modules()->count() }} Modules
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                            {{ $course->lessons()->count() }} Lessons
                                        </span>
                                    </div>
                                </div>

                                {{-- Card Footer: Trainer & CTA --}}
                                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        @if($trainer = $course->trainers->first())
                                            <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs uppercase">
                                                {{ substr($trainer->name, 0, 1) }}
                                            </div>
                                            <span class="text-xs text-slate-600 font-medium">{{ $trainer->name }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">Academy Faculty</span>
                                        @endif
                                    </div>

                                    <a href="{{ route('courses.public.show', $course->slug) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                        <span>View Syllabus</span>
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ACADEMY INTRODUCTION / METHODOLOGY SECTION --}}
    @if($aboutSec && $aboutSec->is_enabled)
    <section class="py-20 bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div>
                    <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Our Academic Philosophy</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2 leading-tight">
                        {{ $aboutSec->title ?: 'Learn by Performing Real Technical Audits, Not Just Watching Videos' }}
                    </h2>
                    <p class="text-slate-600 mt-4 leading-relaxed">
                        {{ $aboutSec->subtitle ?: 'Most SEO programs rely purely on passive video lectures. At SEO Academy, every student performs real-world technical crawl diagnostics, resolves indexing blockers, writes structured data markup, and defends optimization strategies under trainer review.' }}
                    </p>

                    <div class="mt-8 space-y-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Direct Trainer Code Reviews</h4>
                                <p class="text-sm text-slate-500 mt-0.5">Get feedback on your robots.txt configurations, canonical implementations, and XML sitemaps from senior practitioners.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3.5">
                            <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Verifiable Student Portfolios</h4>
                                <p class="text-sm text-slate-500 mt-0.5">Graduates receive a public, cryptographically-signed portfolio URL showcasing their evaluated project audits to employers.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3.5">
                            <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Tamper-Proof Certificates</h4>
                                <p class="text-sm text-slate-500 mt-0.5">Instant public QR-code certificate verification protects the integrity of your academic achievement.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <a href="{{ url('/about') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800">
                            <span>Read more about our academy standards</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Visual highlight cards --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h4 class="text-base font-bold text-slate-900">Private LMS Sandbox</h4>
                        <p class="text-xs text-slate-500 mt-1">Single-academy isolated environment with zero external noise or distractions.</p>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h4 class="text-base font-bold text-slate-900">Live Heartbeat</h4>
                        <p class="text-xs text-slate-500 mt-1">Intelligent video position tracking ensures continuous progress across devices.</p>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h4 class="text-base font-bold text-slate-900">Practical Projects</h4>
                        <p class="text-xs text-slate-500 mt-1">Multi-stage SEO audit assignments mirroring client deliverables.</p>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                            </svg>
                        </div>
                        <h4 class="text-base font-bold text-slate-900">Career Readiness</h4>
                        <p class="text-xs text-slate-500 mt-1">Graduate equipped to conduct enterprise audits for high-traffic platforms.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- ACADEMY SERVICES & CONSULTING --}}
    @if($services->isNotEmpty())
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Professional Offerings</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2">Specialized Programs & Enterprise Services</h2>
                <p class="text-slate-600 mt-3 text-base">Whether you are an individual career-switcher or an enterprise marketing department, we deliver actionable search engineering solutions.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($services as $service)
                    <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                        <div>
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
                                <a href="{{ route('services.show', $service->slug) }}">
                                    {{ $service->title }}
                                </a>
                            </h3>
                            <p class="text-sm text-slate-600 mt-3 leading-relaxed">
                                {{ $service->summary }}
                            </p>

                            @if(!empty($service->benefits))
                                <ul class="mt-6 space-y-2 border-t border-slate-100 pt-4">
                                    @foreach(array_slice($service->benefits, 0, 3) as $benefit)
                                        <li class="flex items-center gap-2 text-xs text-slate-700">
                                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span>{{ $benefit }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between">
                            <a href="{{ route('services.show', $service->slug) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                <span>Learn More</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                            <a href="{{ url('/contact?service=' . urlencode($service->title)) }}" class="text-xs font-medium text-slate-500 hover:text-slate-800">
                                Inquire Now
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- FACULTY / INSTRUCTORS --}}
    @if($trainers->isNotEmpty())
    <section class="py-20 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Instructional Excellence</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2">Learn from Practicing Industry Engineers</h2>
                <p class="text-slate-600 mt-3 text-base">Our faculty members actively audit enterprise websites and lead technical search teams.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($trainers as $trainer)
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 text-center flex flex-col items-center">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-700 text-white font-bold text-2xl flex items-center justify-center shadow-md mb-4 uppercase">
                            {{ substr($trainer->name, 0, 1) }}
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $trainer->name }}</h3>
                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider mt-0.5">Faculty Trainer</p>
                        <p class="text-xs text-slate-500 mt-3 line-clamp-3">
                            Dedicated mentor specializing in organic search architecture, technical audit grading, and project supervision.
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- FAQ ACCORDION --}}
    @if($faqSec && $faqSec->is_enabled)
    <section class="py-20 bg-slate-50 border-t border-slate-200" x-data="{ activeFaq: null }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Got Questions?</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2">Frequently Asked Questions</h2>
                <p class="text-slate-600 mt-3 text-base">Everything you need to know about enrollment, certification, and our training methodology.</p>
            </div>

            @php
                $faqs = $faqSec->content_data['items'] ?? [
                    [
                        'q' => 'Is this a multi-tenant public marketplace or a dedicated academy?',
                        'a' => 'SEO Academy is an independent, specialized private LMS campus. We maintain strict single-academy standards, curated curricula, direct trainer oversight, and rigorous project evaluations.'
                    ],
                    [
                        'q' => 'How are student certificates verified?',
                        'a' => 'Every certificate issued includes a unique tamper-proof verification code and QR code. Employers and clients can visit our public verification endpoint anytime to confirm authenticity.'
                    ],
                    [
                        'q' => 'Do I need prior coding knowledge to enroll in Technical SEO courses?',
                        'a' => 'No prior programming experience is required for beginner tracks. Intermediate and advanced modules guide you step-by-step through HTML tags, HTTP status codes, structured data JSON-LD, and robots directives.'
                    ],
                    [
                        'q' => 'Can enterprise teams request customized training programs?',
                        'a' => 'Yes! We provide tailored corporate workshops and website audit coaching designed around your company’s specific CMS and technology stack. Contact our admissions desk to discuss your team’s requirements.'
                    ]
                ];
            @endphp

            <div class="space-y-4">
                @foreach($faqs as $idx => $faq)
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
                        <button type="button" 
                                @click="activeFaq = (activeFaq === {{ $idx }} ? null : {{ $idx }})"
                                class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-slate-900 hover:text-blue-600 transition-colors">
                            <span>{{ $faq['q'] }}</span>
                            <svg class="w-5 h-5 text-slate-400 shrink-0 transition-transform duration-200"
                                 :class="{ 'rotate-180': activeFaq === {{ $idx }} }"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="activeFaq === {{ $idx }}" x-collapse class="px-6 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3">
                            {{ $faq['a'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- PROMOTIONAL BANNER CAROUSEL (IF CONFIGURED) --}}
    <x-marketing.banner-carousel placement="homepage_promo" />

    {{-- CALL TO ACTION BANNER --}}
    @if($ctaSec && $ctaSec->is_enabled)
    <section class="py-20 bg-gradient-to-r from-blue-700 via-blue-800 to-indigo-900 text-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">
                {{ $ctaSec->title ?: 'Ready to Elevate Your Search Marketing Expertise?' }}
            </h2>
            <p class="text-blue-100 text-lg max-w-2xl mx-auto mb-8 font-normal">
                {{ $ctaSec->subtitle ?: 'Join our specialized academy, master technical optimization, and earn verifiable industry credentials.' }}
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('courses.public.index') }}" class="w-full sm:w-auto px-8 py-3.5 bg-white text-blue-900 hover:bg-blue-50 font-bold rounded-xl shadow-lg transition duration-150">
                    {{ $ctaSec->content_data['primary_button_text'] ?? 'Browse All Courses' }}
                </a>
                <a href="{{ url('/contact') }}" class="w-full sm:w-auto px-8 py-3.5 bg-blue-900/60 hover:bg-blue-900/80 text-white font-semibold rounded-xl border border-blue-400/40 transition duration-150">
                    {{ $ctaSec->content_data['secondary_button_text'] ?? 'Talk with an Academic Advisor' }}
                </a>
            </div>
        </div>
    </section>
    @endif
</x-layouts.marketing>
