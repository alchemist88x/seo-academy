<x-layouts.marketing :title="$service->seo_title ?: $service->title" :description="$service->seo_description ?: $service->summary" :seoData="$seoData" :jsonLd="$jsonLd">
    <div class="bg-gradient-to-b from-slate-100 to-white py-16 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <nav class="flex items-center justify-center gap-2 text-xs font-semibold text-slate-500 mb-4 uppercase tracking-wider">
                <a href="{{ route('services.index') }}" class="hover:text-blue-600 transition">Services</a>
                <span>/</span>
                <span class="text-slate-800">{{ $service->title }}</span>
            </nav>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight">
                {{ $service->title }}
            </h1>
            <p class="text-lg text-slate-600 mt-4 max-w-2xl mx-auto leading-relaxed">
                {{ $service->summary }}
            </p>
            <div class="mt-8 flex justify-center gap-4">
                <a href="{{ url('/contact?service=' . urlencode($service->title)) }}" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-md transition">
                    Inquire About This Service
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            
            {{-- Main Content Column --}}
            <div class="lg:col-span-8">
                <article class="prose prose-slate lg:prose-lg max-w-none prose-headings:font-bold prose-headings:text-slate-900">
                    {!! nl2br(e($service->description)) !!}
                </article>

                @if(!empty($service->benefits))
                    <div class="mt-12 p-8 bg-slate-50 rounded-2xl border border-slate-200">
                        <h3 class="text-xl font-bold text-slate-900 mb-4">Core Deliverables & Key Benefits</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($service->benefits as $benefit)
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm text-slate-700 font-medium">{{ $benefit }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar Column --}}
            <div class="lg:col-span-4 space-y-8">
                <div class="p-6 bg-white rounded-2xl border border-slate-200 shadow-xs">
                    <h4 class="text-base font-bold text-slate-900 mb-3">Service Inquiries</h4>
                    <p class="text-xs text-slate-600 leading-relaxed mb-6">
                        Custom scope consultations are held directly with faculty members to align with your organization’s codebase and marketing objectives.
                    </p>
                    <a href="{{ url('/contact?service=' . urlencode($service->title)) }}" class="w-full block py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-center rounded-lg text-sm transition">
                        Schedule Consultation
                    </a>
                </div>

                @if($otherServices->isNotEmpty())
                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-4">Other Services</h4>
                        <div class="space-y-4">
                            @foreach($otherServices as $other)
                                <div>
                                    <a href="{{ route('services.show', $other->slug) }}" class="text-sm font-semibold text-slate-900 hover:text-blue-600 transition block">
                                        {{ $other->title }}
                                    </a>
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-1">{{ $other->summary }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-layouts.marketing>
