<x-layouts.marketing title="Academy Services & Corporate SEO Training" description="Explore professional search optimization training, technical site audits, team coaching, and corporate advisory services." :seoData="$seoData" :jsonLd="$jsonLd">
    {{-- Services Promotional Banner --}}
    <x-marketing.banner-carousel placement="services_promo" />

    <div class="bg-gradient-to-b from-slate-100 to-white py-16 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Enterprise & Academic Offerings</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mt-3">
                Academy Services & Advisory
            </h1>
            <p class="text-lg text-slate-600 mt-4 max-w-2xl mx-auto leading-relaxed">
                Beyond self-paced courses, we deliver custom corporate search engineering programs, in-depth site architecture audits, and private executive mentorship.
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        @if($services->isEmpty())
            <div class="p-12 text-center bg-white rounded-2xl border border-slate-200">
                <p class="text-slate-500">Services catalog is currently undergoing scheduled updates. Please check back shortly.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($services as $service)
                    <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                        <div>
                            <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                            </div>

                            <h2 class="text-xl font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
                                <a href="{{ route('services.show', $service->slug) }}">
                                    {{ $service->title }}
                                </a>
                            </h2>

                            <p class="text-sm text-slate-600 mt-3 leading-relaxed">
                                {{ $service->summary }}
                            </p>

                            @if(!empty($service->benefits))
                                <div class="mt-6 pt-6 border-t border-slate-100">
                                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider">Key Outcomes</span>
                                    <ul class="mt-3 space-y-2">
                                        @foreach(array_slice($service->benefits, 0, 4) as $benefit)
                                            <li class="flex items-center gap-2.5 text-xs text-slate-700">
                                                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span>{{ $benefit }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-between">
                            <a href="{{ route('services.show', $service->slug) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1.5">
                                <span>Service Details</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                            <a href="{{ url('/contact?service=' . urlencode($service->title)) }}" class="px-3.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition">
                                Request Proposal
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.marketing>
