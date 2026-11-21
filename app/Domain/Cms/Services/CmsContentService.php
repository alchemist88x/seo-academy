<?php

namespace App\Domain\Cms\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBlogCategory;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsContactInquiry;
use App\Domain\Cms\Models\CmsHomepageSection;
use App\Domain\Cms\Models\CmsNavigationItem;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Models\CmsRedirect;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Courses\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsContentService
{
    /**
     * Ensure default website starter content exists for the academy.
     */
    public function ensureDefaultCmsContentExist(Academy $academy, ?User $author = null): void
    {
        DB::transaction(function () use ($academy, $author) {
            // 1. Default Homepage Sections
            $this->seedDefaultHomepageSections($academy);

            // 2. Default Navigation Items (Header & Footer)
            $this->seedDefaultNavigation($academy);

            // 3. Default Services
            $this->seedDefaultServices($academy);

            // 4. Default CMS Pages
            $this->seedDefaultPages($academy, $author);

            // 5. Default Blog Category & Starter Post
            $this->seedDefaultBlog($academy, $author);

            // 6. Ensure default courses have marketing highlights
            $this->seedCourseMarketingDefaults($academy);
        });
    }

    protected function seedDefaultHomepageSections(Academy $academy): void
    {
        if (CmsHomepageSection::where('academy_id', $academy->id)->exists()) {
            return;
        }

        $sections = [
            [
                'section_key' => 'hero',
                'title' => 'Master Technical SEO & Modern Search Engineering',
                'subtitle' => 'Professional, practical digital education taught by industry practitioners. Build real audits, solve realistic search problems, and earn verified credentials.',
                'content' => [
                    'badge' => 'Independent Digital Marketing Academy',
                    'cta_primary_label' => 'Explore Courses',
                    'cta_primary_url' => '/courses',
                    'cta_secondary_label' => 'Academy Services',
                    'cta_secondary_url' => '/services',
                    'highlights' => [
                        'Hands-on Practical SEO Projects',
                        'Real-time Gradebook & Assessments',
                        'Verifiable Digital Certificates',
                    ],
                ],
                'display_order' => 1,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'stats',
                'title' => 'Proven Academic Standards',
                'subtitle' => 'Transparent education backed by structured curricula.',
                'content' => [
                    'use_live_stats' => true,
                    'stat_1_label' => 'Specialized Courses',
                    'stat_2_label' => 'Structured Modules',
                    'stat_3_label' => 'Active Practitioners',
                    'stat_4_label' => 'Completion Rate',
                ],
                'display_order' => 2,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'featured_courses',
                'title' => 'Featured Academic Programs',
                'subtitle' => 'Explore our intensive, outcome-oriented search marketing programs.',
                'content' => [
                    'limit' => 6,
                    'cta_label' => 'View All Programs',
                    'cta_url' => '/courses',
                ],
                'display_order' => 3,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'introduction',
                'title' => 'About SEO Academy',
                'subtitle' => 'We train modern SEO specialists through practical technical audits, structured problem-solving, and continuous instructor feedback.',
                'content' => [
                    'mission' => 'Bridge the gap between theoretical marketing concepts and real-world search engine optimization execution.',
                    'pillars' => [
                        [
                            'title' => 'Deep Technical Foundations',
                            'description' => 'Crawl architectures, server logs, rendering performance, and structured data schemas.',
                        ],
                        [
                            'title' => 'Simulated Enterprise Scenarios',
                            'description' => 'Work directly on client website simulations, penalty recoveries, and international migrations.',
                        ],
                        [
                            'title' => 'Trainer-Evaluated Outcomes',
                            'description' => 'Receive rubric-based evaluations and detailed feedback from certified lead instructors.',
                        ],
                    ],
                ],
                'display_order' => 4,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'services',
                'title' => 'Professional Academy Services',
                'subtitle' => 'Beyond classroom learning, our faculty provides corporate training, executive coaching, and technical consulting.',
                'content' => [
                    'cta_label' => 'Explore All Services',
                    'cta_url' => '/services',
                ],
                'display_order' => 5,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'why_us',
                'title' => 'Why Choose SEO Academy',
                'subtitle' => 'Built from the ground up for serious digital professionals.',
                'content' => [
                    'features' => [
                        [
                            'title' => 'No Generic Fluff',
                            'description' => 'Every lecture and assignment tackles actual search engine algorithms, server responses, and JavaScript rendering.',
                        ],
                        [
                            'title' => 'Verified Academic Transcripts',
                            'description' => 'Tamper-evident verification portal and cryptographically verified PDF credentials.',
                        ],
                        [
                            'title' => 'Dedicated Learning Environment',
                            'description' => 'Focused student classroom, distraction-free video playback, and comprehensive notes.',
                        ],
                    ],
                ],
                'display_order' => 6,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'instructors',
                'title' => 'Meet Our Instructors',
                'subtitle' => 'Learn directly from veteran search directors and technical consultants.',
                'content' => [],
                'display_order' => 7,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'faq',
                'title' => 'Frequently Asked Questions',
                'subtitle' => 'Everything you need to know about enrollments, prerequisites, and certification.',
                'content' => [
                    'items' => [
                        [
                            'question' => 'Are courses self-paced or cohort-based?',
                            'answer' => 'SEO Academy programs combine self-paced structured video and text modules with scheduled practical milestone assignments reviewed by trainers.',
                        ],
                        [
                            'question' => 'How are certificates verified?',
                            'answer' => 'Every issued certificate includes an unguessable verification token and scannable QR code linking to our public verification portal.',
                        ],
                        [
                            'question' => 'Can corporate teams enroll together?',
                            'answer' => 'Yes. We provide bespoke corporate training and team cohorts with dedicated progress reporting for organization leaders.',
                        ],
                    ],
                ],
                'display_order' => 8,
                'is_enabled' => true,
            ],
            [
                'section_key' => 'cta_banner',
                'title' => 'Ready to Master Search Engine Optimization?',
                'subtitle' => 'Join hundreds of marketing practitioners advancing their careers with structured education.',
                'content' => [
                    'cta_label' => 'Get Started Today',
                    'cta_url' => '/register',
                ],
                'display_order' => 9,
                'is_enabled' => true,
            ],
        ];

        foreach ($sections as $s) {
            CmsHomepageSection::create([
                'academy_id' => $academy->id,
                'section_key' => $s['section_key'],
                'title' => $s['title'],
                'subtitle' => $s['subtitle'],
                'content' => $s['content'],
                'display_order' => $s['display_order'],
                'is_enabled' => $s['is_enabled'],
            ]);
        }
    }

    protected function seedDefaultNavigation(Academy $academy): void
    {
        if (CmsNavigationItem::where('academy_id', $academy->id)->exists()) {
            return;
        }

        $headerNav = [
            ['label' => 'Courses', 'url' => '/courses', 'display_order' => 1],
            ['label' => 'Services', 'url' => '/services', 'display_order' => 2],
            ['label' => 'About Us', 'url' => '/about', 'display_order' => 3],
            ['label' => 'Blog', 'url' => '/blog', 'display_order' => 4],
            ['label' => 'Contact', 'url' => '/contact', 'display_order' => 5],
        ];

        foreach ($headerNav as $item) {
            CmsNavigationItem::create([
                'academy_id' => $academy->id,
                'menu_location' => 'header',
                'label' => $item['label'],
                'url' => $item['url'],
                'display_order' => $item['display_order'],
                'is_active' => true,
            ]);
        }

        $footerNav = [
            ['label' => 'Courses', 'url' => '/courses', 'display_order' => 1],
            ['label' => 'Academy Services', 'url' => '/services', 'display_order' => 2],
            ['label' => 'About Us', 'url' => '/about', 'display_order' => 3],
            ['label' => 'Blog & Insights', 'url' => '/blog', 'display_order' => 4],
            ['label' => 'Contact Us', 'url' => '/contact', 'display_order' => 5],
            ['label' => 'Privacy Policy', 'url' => '/page/privacy-policy', 'display_order' => 6],
            ['label' => 'Terms of Service', 'url' => '/page/terms-of-service', 'display_order' => 7],
        ];

        foreach ($footerNav as $item) {
            CmsNavigationItem::create([
                'academy_id' => $academy->id,
                'menu_location' => 'footer',
                'label' => $item['label'],
                'url' => $item['url'],
                'display_order' => $item['display_order'],
                'is_active' => true,
            ]);
        }
    }

    protected function seedDefaultServices(Academy $academy): void
    {
        if (CmsService::where('academy_id', $academy->id)->exists()) {
            return;
        }

        $services = [
            [
                'title' => 'Technical SEO Training',
                'slug' => 'technical-seo-training',
                'icon' => 'server',
                'short_description' => 'Comprehensive technical training covering server logs, crawl budgets, Core Web Vitals, and rendering budgets.',
                'full_description' => 'Our technical SEO training gives engineers and search professionals practical mastery over modern search architectures. Learn how Googlebot parses single-page applications, optimize hydration cycles, and resolve complex indexing loops.',
                'benefits' => [
                    'Server-side rendering & hydration audit workflows',
                    'Log file analysis & bot pattern identification',
                    'Faceted navigation crawl budget control',
                    'Structured data schema implementation at scale',
                ],
                'cta_label' => 'Request Syllabus',
                'display_order' => 1,
                'seo_title' => 'Technical SEO Training & Architecture Program',
                'seo_description' => 'Master technical search engine optimization with hands-on labs on crawl budgets, Core Web Vitals, and log analysis.',
            ],
            [
                'title' => 'Corporate SEO Team Coaching',
                'slug' => 'corporate-seo-training',
                'icon' => 'users',
                'short_description' => 'Custom cohorts and tailored curriculum for in-house enterprise marketing and engineering teams.',
                'full_description' => 'Equip your digital product and engineering squads with the foundational principles of modern organic search. We deliver private workshops, hands-on codebase reviews, and ongoing team evaluations.',
                'benefits' => [
                    'Tailored to your organization tech stack',
                    'Executive progress reporting & gradebook matrices',
                    'Direct access to senior instructors',
                    'Team capstone audits on your production properties',
                ],
                'cta_label' => 'Schedule Consultation',
                'display_order' => 2,
                'seo_title' => 'Corporate SEO Team Training & Enterprise Workshops',
                'seo_description' => 'Custom in-house search optimization training designed for enterprise product, marketing, and engineering teams.',
            ],
            [
                'title' => 'Practical SEO Project Mentorship',
                'slug' => 'practical-seo-projects',
                'icon' => 'briefcase',
                'short_description' => '1-on-1 feedback on simulated real-world client audits, penalty reviews, and site migrations.',
                'full_description' => 'Theory without execution is ineffective. Our practical project mentorship pairs students with seasoned practitioners who evaluate audit deliverables against strict professional rubrics.',
                'benefits' => [
                    'Realistic client scenario briefs',
                    'Rubric-based criteria and feedback',
                    'Public showcase portfolio integration',
                    'Direct trainer code reviews',
                ],
                'cta_label' => 'Learn More',
                'display_order' => 3,
                'seo_title' => 'Practical SEO Project Mentorship & Portfolio Building',
                'seo_description' => 'Complete realistic search optimization client projects with trainer rubric grading and portfolio certification.',
            ],
            [
                'title' => 'Digital Marketing Consulting',
                'slug' => 'digital-marketing-consulting',
                'icon' => 'bar-chart',
                'short_description' => 'Specialized advisory for high-growth search programs, internationalization, and platform migrations.',
                'full_description' => 'SEO Academy faculty provides advisory consulting for complex site migrations, multi-regional setups, and search penalty remediations.',
                'benefits' => [
                    'Strategic organic search roadmap',
                    'Risk mitigation during platform re-platforming',
                    'Information architecture redesign',
                    'Cross-departmental stakeholder alignment',
                ],
                'cta_label' => 'Inquire for Advisory',
                'display_order' => 4,
                'seo_title' => 'Enterprise SEO & Digital Marketing Consulting Advisory',
                'seo_description' => 'High-level search advisory and technical migration guidance from veteran industry educators.',
            ],
        ];

        foreach ($services as $srv) {
            CmsService::create(array_merge($srv, ['academy_id' => $academy->id, 'is_published' => true]));
        }
    }

    protected function seedDefaultPages(Academy $academy, ?User $author): void
    {
        if (CmsPage::where('academy_id', $academy->id)->exists()) {
            return;
        }

        $pages = [
            [
                'title' => 'About SEO Academy',
                'slug' => 'about',
                'excerpt' => 'Learn about our academic philosophy, specialized faculty, and mission to elevate search marketing standards.',
                'content' => '<h2>Empowering Digital Marketers with Practical Rigor</h2><p>SEO Academy was founded to provide digital professionals with an uncompromising, technical curriculum in search engine optimization. Rather than superficial tips or outdated hacks, our programs focus on the fundamental engineering and content architectures that drive sustainable search visibility.</p><h3>Our Teaching Philosophy</h3><p>We believe learning is reinforced through direct practice. Our students complete realistic audits, write algorithmic diagnosis reports, and defend their recommendations before industry trainers.</p>',
                'seo_title' => 'About SEO Academy — Practical Search Engineering Education',
                'seo_description' => 'Discover the mission, faculty, and academic standards behind SEO Academy search education.',
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact',
                'excerpt' => 'Get in touch with our admissions faculty or inquire about corporate team training.',
                'content' => "<h2>We're Here to Help You Advance</h2><p>Have questions regarding our academic syllabus, corporate group rates, or student certifications? Use the form below or contact our academic desk directly.</p>",
                'seo_title' => 'Contact SEO Academy — Admissions & Corporate Inquiries',
                'seo_description' => 'Connect with SEO Academy admissions or inquire about private team training workshops.',
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'excerpt' => 'Our commitment to protecting student and visitor privacy.',
                'content' => '<h2>Privacy Policy</h2><p>SEO Academy respects your privacy. We collect only the information necessary to provide authentication, progress tracking, credential verification, and course delivery. We never sell student data to third parties.</p><h3>Data Retention</h3><p>Academic records and certificate verification tokens are preserved to enable verified credential verification.</p>',
                'seo_title' => 'Privacy Policy — SEO Academy',
                'seo_description' => 'Official privacy policy and student data handling standards of SEO Academy.',
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'excerpt' => 'Terms and conditions governing academy access and student credentials.',
                'content' => '<h2>Terms of Service</h2><p>By accessing SEO Academy, you agree to comply with our academic honor code. Course materials, assessments, and proprietary diagnostic templates are licensed solely for enrolled student study.</p><h3>Certification Integrity</h3><p>Certificates are issued strictly upon satisfying course completion criteria and published passing marks.</p>',
                'seo_title' => 'Terms of Service — SEO Academy',
                'seo_description' => 'Terms of service and academic integrity policies governing SEO Academy.',
            ],
        ];

        foreach ($pages as $p) {
            CmsPage::create(array_merge($p, [
                'academy_id' => $academy->id,
                'status' => 'published',
                'published_at' => now(),
                'is_indexable' => true,
                'created_by' => $author?->id,
            ]));
        }
    }

    protected function seedDefaultBlog(Academy $academy, ?User $author): void
    {
        if (CmsBlogPost::where('academy_id', $academy->id)->exists()) {
            return;
        }

        $category = CmsBlogCategory::create([
            'academy_id' => $academy->id,
            'name' => 'Technical SEO',
            'slug' => 'technical-seo',
            'description' => 'Articles and tutorials on crawlability, indexing, and website architecture.',
            'display_order' => 1,
        ]);

        CmsBlogCategory::create([
            'academy_id' => $academy->id,
            'name' => 'Algorithm Updates',
            'slug' => 'algorithm-updates',
            'description' => 'Analysis of major search engine core updates and ranking system changes.',
            'display_order' => 2,
        ]);

        CmsBlogPost::create([
            'academy_id' => $academy->id,
            'category_id' => $category->id,
            'author_id' => $author?->id,
            'title' => 'Understanding JavaScript Rendering and Hydration for Search Engines',
            'slug' => 'understanding-javascript-rendering-and-hydration',
            'excerpt' => 'A deep dive into how search engines crawl, render, and execute modern client-rendered JavaScript applications.',
            'content' => '<h2>The Two Waves of Indexing</h2><p>Modern search engines process web pages in distinct phases. The initial crawl fetches raw HTML and inline resources. If client-side JavaScript is detected, the document enters a rendering queue where headless Chromium renders the DOM.</p><h3>Common Hydration Bottlenecks</h3><p>When hydration mismatches occur or client-side fetches exceed rendering timeouts, essential metadata and contextual content can fail to be parsed. In this guide, we examine diagnostic methods to inspect rendered DOM snapshots.</p>',
            'status' => 'published',
            'published_at' => now(),
            'reading_time_minutes' => 6,
            'seo_title' => 'JavaScript SEO: Rendering, Hydration, and Indexing Guide',
            'seo_description' => 'Learn how modern search engines handle client-rendered JavaScript applications and how to diagnose rendering bottlenecks.',
            'is_indexable' => true,
        ]);
    }

    protected function seedCourseMarketingDefaults(Academy $academy): void
    {
        $courses = Course::where('academy_id', $academy->id)->get();
        foreach ($courses as $c) {
            $c->is_marketing_featured = true;
            $c->is_public_listed = true;
            if (empty($c->marketing_description)) {
                $c->marketing_description = Str::limit($c->description ?: 'Comprehensive professional course with hands-on practical assignments and instructor grading.', 180);
            }
            if (empty($c->marketing_highlights)) {
                $c->marketing_highlights = [
                    'Comprehensive curriculum with real-world scenarios',
                    'Interactive quizzes and practical evaluations',
                    'Verifiable completion certificate',
                ];
            }
            if (empty($c->seo_title)) {
                $c->seo_title = "{$c->title} — SEO Academy Program";
            }
            if (empty($c->seo_description)) {
                $c->seo_description = Str::limit($c->description ?: "Learn {$c->title} with industry practitioners at SEO Academy.", 155);
            }
            $c->save();
        }
    }

    /**
     * Submit and record a contact form inquiry.
     */
    public function submitContactInquiry(Academy $academy, array $data, ?string $ip = null, ?string $userAgent = null): CmsContactInquiry
    {
        return CmsContactInquiry::create([
            'academy_id' => $academy->id,
            'name' => trim($data['name'] ?? ''),
            'email' => strtolower(trim($data['email'] ?? '')),
            'phone' => trim($data['phone'] ?? null) ?: null,
            'subject' => trim($data['subject'] ?? 'General Inquiry'),
            'message' => trim($data['message'] ?? ''),
            'interested_service' => trim($data['interested_service'] ?? null) ?: null,
            'status' => 'new',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Update page with automatic 301 redirect if slug changes.
     */
    public function updatePage(CmsPage $page, array $data, ?User $actor = null): CmsPage
    {
        return DB::transaction(function () use ($page, $data, $actor) {
            $oldSlug = $page->slug;
            $newSlug = Str::slug($data['slug'] ?? $data['title'] ?? $page->slug);

            if ($oldSlug !== $newSlug && $page->isPublished()) {
                CmsRedirect::updateOrCreate(
                    [
                        'academy_id' => $page->academy_id,
                        'source_path' => "/page/{$oldSlug}",
                    ],
                    [
                        'target_path' => "/page/{$newSlug}",
                        'status_code' => 301,
                        'is_active' => true,
                    ]
                );
            }

            $page->update(array_merge($data, [
                'slug' => $newSlug,
                'updated_by' => $actor?->id,
            ]));

            return $page->fresh();
        });
    }

    /**
     * Validate navigation URL to prevent unsafe schemes (e.g. javascript:, data:, vbscript:).
     */
    public function validateNavigationUrl(string $url): bool
    {
        $trimmed = trim(strtolower($url));
        if ($trimmed === '' || $trimmed === '#') {
            return true;
        }

        if (str_starts_with($trimmed, '/')) {
            return true;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, 'mailto:') || str_starts_with($trimmed, 'tel:')) {
            return true;
        }

        return false;
    }

    /**
     * Alias for updatePage.
     */
    public function updatePageWithRedirect(CmsPage $page, array $data, ?User $actor = null): CmsPage
    {
        return $this->updatePage($page, $data, $actor);
    }

    /**
     * Alias for validateNavigationUrl.
     */
    public function isSafeNavigationUrl(string $url): bool
    {
        return $this->validateNavigationUrl($url);
    }
}
