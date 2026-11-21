<?php

namespace App\Domain\Cms\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Courses\Models\Course;
use Illuminate\Support\Facades\Request;

class SeoMetadataService
{
    /**
     * Build meta tags array for a given page or model.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(
        ?Academy $academy = null,
        mixed $entity = null,
        array $overrides = []
    ): array {
        $academy ??= Academy::current() ?? Academy::first();
        $academyName = $academy?->name ?: config('app.name', 'SEO Academy');
        $tagline = $academy?->setting('tagline') ?: 'Modern Search Education';
        $currentUrl = Request::url();

        $title = $overrides['title'] ?? null;
        $description = $overrides['description'] ?? null;
        $canonical = $overrides['canonical_url'] ?? null;
        $ogImage = $overrides['og_image'] ?? null;
        $isIndexable = $overrides['is_indexable'] ?? true;
        $type = 'website';

        if ($entity instanceof CmsPage) {
            $title ??= $entity->seo_title ?: $entity->title;
            $description ??= $entity->seo_description ?: $entity->excerpt;
            if (in_array($entity->slug, ['about', 'contact'], true)) {
                $canonical ??= $entity->canonical_url ?: url("/{$entity->slug}");
            } else {
                $canonical ??= $entity->canonical_url ?: url("/page/{$entity->slug}");
            }
            $ogImage ??= $entity->og_image_path;
            $isIndexable = (bool) $entity->is_indexable && $entity->isPublished();
        } elseif ($entity instanceof Course) {
            $title ??= $entity->seo_title ?: "{$entity->title} — {$academyName}";
            $description ??= $entity->seo_description ?: $entity->marketing_description ?: $entity->description;
            $canonical ??= $entity->canonical_url ?: url("/courses/{$entity->slug}");
            $ogImage ??= $entity->thumbnail_path;
            $isIndexable = (bool) $entity->is_public_listed && $entity->isPublished();
        } elseif ($entity instanceof CmsService) {
            $title ??= $entity->seo_title ?: "{$entity->title} — {$academyName}";
            $description ??= $entity->seo_description ?: $entity->short_description;
            $canonical ??= url("/services/{$entity->slug}");
            $isIndexable = (bool) $entity->is_published;
        } elseif ($entity instanceof CmsBlogPost) {
            $title ??= $entity->seo_title ?: $entity->title;
            $description ??= $entity->seo_description ?: $entity->excerpt;
            $canonical ??= $entity->canonical_url ?: url("/blog/{$entity->slug}");
            $ogImage ??= $entity->og_image_path ?: $entity->featured_image_path;
            $isIndexable = (bool) $entity->is_indexable && $entity->isPublished();
            $type = 'article';
        }

        $title ??= "{$academyName} — {$tagline}";
        $description ??= 'Professional digital education platform specializing in technical search engine optimization, hands-on audits, and verified certificates.';
        $canonical ??= $currentUrl;

        // Ensure canonical is an absolute URL
        if (! str_starts_with($canonical, 'http')) {
            $canonical = url($canonical);
        }

        // Full OG Image URL
        if ($ogImage && ! str_starts_with($ogImage, 'http')) {
            $ogImage = asset($ogImage);
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonical,
            'og_type' => $type,
            'og_image' => $ogImage ?: asset('build/assets/og-default.png'),
            'og_title' => $title,
            'og_description' => $description,
            'og_site_name' => $academyName,
            'robots' => $isIndexable ? 'index, follow' : 'noindex, nofollow',
        ];
    }

    /**
     * Generate Schema.org JSON-LD scripts for the public website.
     */
    public function getJsonLd(?Academy $academy = null, mixed $entity = null): string
    {
        $academy ??= Academy::current() ?? Academy::first();
        $academyName = $academy?->name ?: config('app.name', 'SEO Academy');
        $baseUrl = url('/');

        $schemas = [];

        // 1. Organization Schema
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => $academyName,
            'url' => $baseUrl,
            'logo' => $academy?->logo_path ? asset($academy->logo_path) : "{$baseUrl}/logo.png",
            'sameAs' => array_filter([
                $academy?->setting('twitter_url'),
                $academy?->setting('linkedin_url'),
                $academy?->setting('github_url'),
            ]),
        ];

        // 2. Specific Entity Schema
        if ($entity instanceof Course) {
            $trainers = $entity->trainers->map(fn ($t) => [
                '@type' => 'Person',
                'name' => $t->name,
            ])->toArray();

            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $entity->title,
                'description' => $entity->marketing_description ?: $entity->description,
                'provider' => [
                    '@type' => 'Organization',
                    'name' => $academyName,
                    'sameAs' => $baseUrl,
                ],
                'instructor' => ! empty($trainers) ? $trainers : null,
                'hasCourseInstance' => [
                    '@type' => 'CourseInstance',
                    'courseMode' => 'online',
                    'courseWorkload' => $entity->duration_months ? "P{$entity->duration_months}M" : 'P3M',
                ],
            ];
        } elseif ($entity instanceof CmsBlogPost) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $entity->title,
                'description' => $entity->excerpt,
                'datePublished' => $entity->published_at ? $entity->published_at->toIso8601String() : null,
                'dateModified' => $entity->updated_at ? $entity->updated_at->toIso8601String() : null,
                'author' => [
                    '@type' => 'Person',
                    'name' => $entity->author?->name ?: $academyName,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $academyName,
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $academy?->logo_path ? asset($academy->logo_path) : "{$baseUrl}/logo.png",
                    ],
                ],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => url("/blog/{$entity->slug}"),
                ],
            ];
        } elseif ($entity instanceof CmsService) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => $entity->title,
                'description' => $entity->short_description,
                'provider' => [
                    '@type' => 'Organization',
                    'name' => $academyName,
                ],
            ];
        } else {
            // Homepage WebSite Schema
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $academyName,
                'url' => $baseUrl,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => "{$baseUrl}/courses?search={search_term_string}",
                    'query-input' => 'required name=search_term_string',
                ],
            ];
        }

        $json = json_encode($schemas, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return '<script type="application/ld+json">'.$json.'</script>';
    }
}
