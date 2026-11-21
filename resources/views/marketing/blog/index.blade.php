<x-layouts.marketing title="SEO Insights, Case Studies & Technical Tutorials" description="In-depth articles, algorithmic case studies, and practical guides on search engine optimization and digital marketing architecture." :seoData="$seoData" :jsonLd="$jsonLd">
    {{-- Blog Promotional Banner --}}
    <x-marketing.banner-carousel placement="blog_promo" />

    <div class="bg-gradient-to-b from-slate-100 to-white py-16 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Editorial & Engineering Insights</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mt-3">
                SEO Academy Blog
            </h1>
            <p class="text-lg text-slate-600 mt-4 max-w-2xl mx-auto leading-relaxed">
                Technical deep dives, algorithmic analysis, indexing experiments, and practical optimization tutorials.
            </p>

            {{-- Search Bar --}}
            <div class="mt-8 max-w-xl mx-auto">
                <form method="GET" action="{{ route('blog.index') }}" class="flex items-center gap-2">
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <div class="relative flex-1">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search articles by keyword..."
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-xs transition">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                    <button type="submit" class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition">
                        Search
                    </button>
                    @if($search !== '' || request('category'))
                        <a href="{{ route('blog.index') }}" class="px-3 py-3 text-slate-500 hover:text-slate-800 text-sm">
                            Clear
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        {{-- Category Filter Pills --}}
        @if($categories->isNotEmpty())
            <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-10 scrollbar-none">
                <a href="{{ route('blog.index', array_filter(['search' => $search])) }}"
                   class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition {{ empty(request('category')) ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    All Topics
                </a>
                @foreach($categories as $category)
                    <a href="{{ route('blog.index', array_filter(['category' => $category->slug, 'search' => $search])) }}"
                       class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition {{ request('category') === $category->slug ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        {{ $category->name }} ({{ $category->posts_count }})
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Post Grid --}}
        @if($posts->isEmpty())
            <div class="p-12 text-center bg-white rounded-2xl border border-slate-200 max-w-xl mx-auto">
                <p class="text-slate-500">No published articles match your criteria.</p>
                @if($search !== '' || request('category'))
                    <a href="{{ route('blog.index') }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">
                        View all articles
                    </a>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($posts as $post)
                    <article class="bg-white rounded-2xl border border-slate-200 shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group">
                        @if($post->featured_image_path)
                            <div class="aspect-video w-full bg-slate-100 overflow-hidden">
                                <img src="{{ asset($post->featured_image_path) }}" alt="{{ $post->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            </div>
                        @else
                            <div class="aspect-video w-full bg-gradient-to-br from-slate-800 to-blue-950 flex items-center justify-center p-6 text-white text-center">
                                <span class="text-xs uppercase tracking-wider font-semibold text-blue-300">Technical Publication</span>
                            </div>
                        @endif

                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-3">
                                    @if($post->category)
                                        <span class="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700">
                                            {{ $post->category->name }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-slate-400">
                                        {{ $post->published_at?->format('M d, Y') ?? 'Recent' }}
                                    </span>
                                </div>

                                <h2 class="text-lg font-bold text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-2">
                                    <a href="{{ route('blog.show', $post->slug) }}">
                                        {{ $post->title }}
                                    </a>
                                </h2>

                                <p class="text-sm text-slate-600 mt-2 line-clamp-3">
                                    {{ $post->excerpt ?: Str::limit(strip_tags($post->content), 140) }}
                                </p>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center">
                                        {{ substr($post->author?->name ?? 'A', 0, 1) }}
                                    </div>
                                    <span class="text-xs text-slate-600 font-medium">{{ $post->author?->name ?? 'Faculty Staff' }}</span>
                                </div>

                                <a href="{{ route('blog.show', $post->slug) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                    <span>Read Article</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-12">
                {{ $posts->links() }}
            </div>
        @endif

    </div>
</x-layouts.marketing>
