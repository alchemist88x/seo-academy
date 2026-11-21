<?php

namespace App\Domain\Cms\Services;

class RobotsTxtService
{
    /**
     * Generate standard robots.txt content with allowed public areas and blocked private LMS routes.
     */
    public function generateRobotsTxt(): string
    {
        $baseUrl = rtrim(config('app.url', url('/')), '/');

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Allow: /courses',
            'Allow: /services',
            'Allow: /about',
            'Allow: /contact',
            'Allow: /blog',
            'Allow: /page/',
            'Allow: /certificates/verify/',
            'Allow: /portfolio/',
            '',
            '# Block authenticated and internal LMS areas',
            'Disallow: /admin/',
            'Disallow: /student/',
            'Disallow: /trainer/',
            'Disallow: /support/',
            'Disallow: /classroom/',
            'Disallow: /api/',
            'Disallow: /media/',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /password/',
            '',
            '# Official XML Sitemap',
            "Sitemap: {$baseUrl}/sitemap.xml",
        ];

        return implode("\n", $lines)."\n";
    }
}
