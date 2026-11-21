<?php

namespace App\Domain\Cms\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Courses\Models\Course;

class SitemapService
{
    /**
     * Generate standard XML sitemap for the public website.
     */
    public function generateXml(?Academy $academy = null): string
    {
        $academy ??= Academy::current() ?? Academy::first();
        $academyId = $academy?->id;
        $baseUrl = rtrim(config('app.url', url('/')), '/');

        if ($academy) {
            app(CmsContentService::class)->ensureDefaultCmsContentExist($academy);
        }

        $urls = [];

        // 1. Homepage
        $urls[] = [
            'loc' => $baseUrl.'/',
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // 2. Courses Index
        $urls[] = [
            'loc' => $baseUrl.'/courses',
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];

        // 3. Published Public Courses
        $coursesQuery = Course::query()
            ->whereIn('status', ['Published', 'Active'])
            ->where('is_public_listed', true)
            ->whereIn('visibility', ['public', 'academy_only']);

        if ($academyId) {
            $coursesQuery->where('academy_id', $academyId);
        }

        foreach ($coursesQuery->latest('updated_at')->get() as $course) {
            $urls[] = [
                'loc' => $baseUrl."/courses/{$course->slug}",
                'lastmod' => ($course->updated_at ?? now())->toIso8601String(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        // 4. Services Index & Detail Pages
        $urls[] = [
            'loc' => $baseUrl.'/services',
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        $servicesQuery = CmsService::query()->where('is_published', true);
        if ($academyId) {
            $servicesQuery->where('academy_id', $academyId);
        }

        foreach ($servicesQuery->orderBy('display_order')->get() as $service) {
            $urls[] = [
                'loc' => $baseUrl."/services/{$service->slug}",
                'lastmod' => ($service->updated_at ?? now())->toIso8601String(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        // 5. Blog Index & Published Articles
        $urls[] = [
            'loc' => $baseUrl.'/blog',
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ];

        $postsQuery = CmsBlogPost::query()
            ->where('status', 'published')
            ->where('is_indexable', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($academyId) {
            $postsQuery->where('academy_id', $academyId);
        }

        foreach ($postsQuery->latest('published_at')->get() as $post) {
            $urls[] = [
                'loc' => $baseUrl."/blog/{$post->slug}",
                'lastmod' => ($post->updated_at ?? $post->published_at)->toIso8601String(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        // 6. Published CMS Pages (Indexable only)
        $pagesQuery = CmsPage::query()
            ->where('status', 'published')
            ->where('is_indexable', true);

        if ($academyId) {
            $pagesQuery->where('academy_id', $academyId);
        }

        foreach ($pagesQuery->get() as $page) {
            $path = match ($page->slug) {
                'about' => '/about',
                'contact' => '/contact',
                default => "/page/{$page->slug}",
            };

            $urls[] = [
                'loc' => $baseUrl.$path,
                'lastmod' => ($page->updated_at ?? now())->toIso8601String(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>'."\n";
            $xml .= '    <lastmod>'.$u['lastmod'].'</lastmod>'."\n";
            $xml .= '    <changefreq>'.$u['changefreq'].'</changefreq>'."\n";
            $xml .= '    <priority>'.$u['priority'].'</priority>'."\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
