<x-layouts.marketing :title="$post->seo_title ?: $post->title" :description="$post->seo_description ?: $post->excerpt" :ogImage="$post->featured_image_path ? asset($post->featured_image_path) : null" :seoData="$seoData" :jsonLd="$jsonLd">
    <div class="bg-gradient-to-b from-slate-100 to-white py-16 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-6 uppercase tracking-wider">
                <a href="{{ route('blog.index') }}" class="hover:text-blue-600 transition">Blog</a>
                @if($post->category)
                    <span>/</span>
                    <a href="{{ route('blog.index', ['category' => $post->category->slug]) }}" class="hover:text-blue-600 transition">
                        {{ $post->category->name }}
                    </a>
                @endif
            </nav>

            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                {{ $post->title }}
            </h1>

            <div class="mt-6 flex flex-wrap items-center gap-4 text-xs sm:text-sm text-slate-500 border-t border-slate-200/80 pt-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center uppercase">
                        {{ substr($post->author?->name ?? 'A', 0, 1) }}
                    </div>
                    <span class="font-semibold text-slate-800">{{ $post->author?->name ?? 'SEO Academy Editorial Staff' }}</span>
                </div>
                <span>•</span>
                <span>Published on {{ $post->published_at?->format('F d, Y') ?? 'Recently' }}</span>
                @if($post->reading_time_minutes)
                    <span>•</span>
                    <span>{{ $post->reading_time_minutes }} min read</span>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        @if($post->featured_image_path)
            <div class="mb-10 rounded-2xl overflow-hidden shadow-lg border border-slate-200">
                <img src="{{ asset($post->featured_image_path) }}" alt="{{ $post->title }}" class="w-full h-auto max-h-[480px] object-cover">
            </div>
        @endif

        <article class="prose prose-slate lg:prose-lg max-w-none prose-headings:font-bold prose-headings:text-slate-900 prose-a:text-blue-600 hover:prose-a:text-blue-800">
            {!! nl2br(e($post->content)) !!}
        </article>

        {{-- Author Bio Card --}}
        <div class="mt-16 p-8 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center sm:items-start gap-6">
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-700 text-white font-bold text-xl flex items-center justify-center shrink-0 uppercase">
                {{ substr($post->author?->name ?? 'A', 0, 1) }}
            </div>
            <div>
                <h4 class="text-base font-bold text-slate-900">{{ $post->author?->name ?? 'SEO Academy Faculty' }}</h4>
                <p class="text-xs text-blue-600 font-semibold uppercase tracking-wider mt-0.5">Contributor & Instructor</p>
                <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                    Instructor at SEO Academy researching search crawler behavior, algorithmic rankings, and large-scale technical site health.
                </p>
            </div>
        </div>

        {{-- Related Articles --}}
        @if($relatedPosts->isNotEmpty())
            <div class="mt-16 pt-12 border-t border-slate-200">
                <h3 class="text-xl font-bold text-slate-900 mb-6">Related Technical Articles</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($relatedPosts as $rel)
                        <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between">
                            <div>
                                <span class="text-[11px] font-semibold text-blue-600 uppercase">{{ $rel->category?->name ?? 'Article' }}</span>
                                <h4 class="text-sm font-bold text-slate-900 mt-1 line-clamp-2">
                                    <a href="{{ route('blog.show', $rel->slug) }}" class="hover:text-blue-600 transition">
                                        {{ $rel->title }}
                                    </a>
                                </h4>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-400">
                                {{ $rel->published_at?->format('M d, Y') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Navigation Footer --}}
        <div class="mt-12 pt-6 border-t border-slate-200 flex items-center justify-between">
            <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to All Articles</span>
            </a>
            <a href="{{ url('/courses') }}" class="text-sm font-semibold text-slate-700 hover:text-blue-600">
                Explore Academy Courses &rarr;
            </a>
        </div>
    </div>
</x-layouts.marketing>
