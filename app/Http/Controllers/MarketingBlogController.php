<?php

namespace App\Http\Controllers;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBlogCategory;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsRedirect;
use App\Domain\Cms\Services\CmsContentService;
use App\Domain\Cms\Services\SeoMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingBlogController extends Controller
{
    public function __construct(
        protected CmsContentService $cmsService,
        protected SeoMetadataService $seoService
    ) {}

    /**
     * Display blog listing with search and category filters.
     */
    public function index(Request $request): View
    {
        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->cmsService->ensureDefaultCmsContentExist($academy);
        }

        $search = trim($request->query('search', ''));
        $categorySlug = trim($request->query('category', ''));

        $query = CmsBlogPost::where('academy_id', $academy?->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['category', 'author']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $activeCategory = null;
        if ($categorySlug !== '') {
            $activeCategory = CmsBlogCategory::where('academy_id', $academy?->id)
                ->where('slug', $categorySlug)
                ->first();

            if ($activeCategory) {
                $query->where('category_id', $activeCategory->id);
            }
        }

        $posts = $query->latest('published_at')->paginate(9)->withQueryString();

        $categories = CmsBlogCategory::where('academy_id', $academy?->id)
            ->withCount(['posts' => fn ($q) => $q->where('status', 'published')->where('published_at', '<=', now())])
            ->orderBy('display_order')
            ->get();

        $seoData = $this->seoService->getMetadata($academy, null, [
            'title' => 'Search Engine Insights & Technical Tutorials',
            'description' => 'In-depth articles, algorithmic case studies, and practical guides on search engine optimization and digital marketing architecture.',
        ]);
        $jsonLd = $this->seoService->getJsonLd($academy, null);

        return view('marketing.blog.index', [
            'academy' => $academy,
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'search' => $search,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Display a single blog article.
     */
    public function show(string $slug): View|RedirectResponse
    {
        $academy = Academy::current() ?? Academy::first();

        // Check redirects
        $redirect = CmsRedirect::where('academy_id', $academy?->id)
            ->where('source_path', "/blog/{$slug}")
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            return redirect($redirect->target_path, $redirect->status_code);
        }

        $post = CmsBlogPost::where('academy_id', $academy?->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['category', 'author'])
            ->firstOrFail();

        $relatedPosts = CmsBlogPost::where('academy_id', $academy?->id)
            ->where('id', '!=', $post->id)
            ->where('status', 'published')
            ->where('category_id', $post->category_id)
            ->latest('published_at')
            ->take(3)
            ->get();

        $seoData = $this->seoService->getMetadata($academy, $post);
        $jsonLd = $this->seoService->getJsonLd($academy, $post);

        return view('marketing.blog.show', [
            'academy' => $academy,
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }
}
