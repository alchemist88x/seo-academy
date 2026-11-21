<x-layouts.marketing :title="$page->seo_title ?: $page->title" :description="$page->seo_description" :seoData="$seoData" :jsonLd="$jsonLd">
    <div class="bg-gradient-to-b from-slate-100 to-white py-16 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Official Academy Policy</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mt-3">
                {{ $page->title }}
            </h1>
            <p class="text-sm text-slate-500 mt-3">
                Last updated {{ $page->updated_at?->format('F d, Y') ?? 'Recently' }}
            </p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <article class="prose prose-slate lg:prose-lg max-w-none prose-headings:font-bold prose-headings:text-slate-900 prose-a:text-blue-600 hover:prose-a:text-blue-800">
            {!! nl2br(e($page->content)) !!}
        </article>

        <div class="mt-16 pt-8 border-t border-slate-200 flex items-center justify-between">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Return to Homepage</span>
            </a>
            <a href="{{ url('/contact') }}" class="text-sm text-slate-500 hover:text-slate-800">
                Have questions about this policy?
            </a>
        </div>
    </div>
</x-layouts.marketing>
