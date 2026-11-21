<?php

namespace App\Http\Controllers;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Services\RobotsTxtService;
use App\Domain\Cms\Services\SitemapService;
use Illuminate\Http\Response;

class RobotsAndSitemapController extends Controller
{
    public function __construct(
        protected SitemapService $sitemapService,
        protected RobotsTxtService $robotsTxtService
    ) {}

    /**
     * Render dynamic XML sitemap.
     */
    public function sitemap(): Response
    {
        $academy = Academy::current() ?? Academy::first();
        $xml = $this->sitemapService->generateXml($academy);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Render dynamic robots.txt.
     */
    public function robots(): Response
    {
        $content = $this->robotsTxtService->generateRobotsTxt();

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
