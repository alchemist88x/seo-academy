<?php

namespace App\Domain\Cms\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\AI\Models\AiFaq;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Courses\Models\Course;
use Illuminate\Support\Str;

class ChatbotRetrievalService
{
    /**
     * Retrieve relevant public records based on query tokens.
     *
     * @param  array<int, string>  $tokens
     * @return array{courses: array<int, array<string, mixed>>, services: array<int, array<string, mixed>>, faqs: array<int, array<string, mixed>>, pages: array<int, array<string, mixed>>, blog: array<int, array<string, mixed>>, contact: array<string, mixed>}
     */
    public function retrieve(string $academyId, string $query, array $tokens = []): array
    {
        $academy = Academy::find($academyId) ?? Academy::first();

        $courses = $this->retrieveRelevantCourses($academyId, $query, $tokens);
        $services = $this->retrieveRelevantServices($academyId, $tokens);
        $faqs = $this->retrieveRelevantFaqs($academyId, $query, $tokens);
        $pages = $this->retrieveRelevantPages($academyId, $tokens);
        $blog = $this->retrieveRelevantBlogPosts($academyId, $tokens);

        $contact = [
            'name' => $academy?->name ?? 'SEO Academy',
            'support_email' => $academy?->support_email ?? 'admissions@seo-academy.local',
            'phone' => $academy?->contact_phone ?? '+1 (800) 555-0199',
            'contact_url' => url('/contact'),
            'courses_url' => url('/courses'),
            'register_url' => url('/register'),
            'login_url' => url('/login'),
        ];

        return [
            'courses' => $courses,
            'services' => $services,
            'faqs' => $faqs,
            'pages' => $pages,
            'blog' => $blog,
            'contact' => $contact,
        ];
    }

    /**
     * Match and retrieve public courses based on natural language preferences.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantCourses(string $academyId, string $query, array $tokens = []): array
    {
        $clean = strtolower($query);

        $courseQuery = Course::where('academy_id', $academyId)
            ->whereIn('status', ['Published', 'Active'])
            ->where('is_public_listed', true);

        // Detect difficulty preference
        if (str_contains($clean, 'beginner') || str_contains($clean, 'intro') || str_contains($clean, 'start')) {
            $courseQuery->where(function ($q) {
                $q->where('difficulty', 'Beginner')->orWhereNull('difficulty');
            });
        } elseif (str_contains($clean, 'advanced') || str_contains($clean, 'expert') || str_contains($clean, 'master')) {
            $courseQuery->where('difficulty', 'Advanced');
        } elseif (str_contains($clean, 'intermediate')) {
            $courseQuery->where('difficulty', 'Intermediate');
        }

        // Detect free filter
        if (str_contains($clean, 'free')) {
            $courseQuery->where(function ($q) {
                $q->where('is_free', true)->orWhere('price', 0);
            });
        }

        $allCourses = $courseQuery->get();

        if ($allCourses->isEmpty()) {
            return [];
        }

        $scored = [];
        foreach ($allCourses as $c) {
            $score = 0;
            $text = strtolower($c->title.' '.$c->description.' '.$c->difficulty.' '.($c->category?->name ?? ''));

            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($text, $token)) {
                    $score += 5;
                }
            }

            // Keyword boosts
            if (str_contains($clean, 'technical') && str_contains($text, 'technical')) {
                $score += 15;
            }
            if (str_contains($clean, 'audit') && str_contains($text, 'audit')) {
                $score += 15;
            }
            if (str_contains($clean, 'keyword') && str_contains($text, 'keyword')) {
                $score += 15;
            }
            if (str_contains($clean, 'link') && str_contains($text, 'link')) {
                $score += 15;
            }

            $scored[] = [
                'course' => $c,
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $selected = array_slice($scored, 0, 3);
        $cards = [];

        foreach ($selected as $item) {
            /** @var Course $course */
            $course = $item['course'];
            $priceText = ($course->price && $course->price > 0)
                ? app(CurrencyAndPricingService::class)->format($course->price, $course->currency)
                : ($course->is_free ? 'Free' : 'Admissions Track');
            $durationText = $course->estimated_hours ? "{$course->estimated_hours} Hours" : 'Self-Paced Intensive';

            $cards[] = [
                'id' => $course->id,
                'title' => $this->sanitizeText($course->title),
                'slug' => $course->slug,
                'description' => $this->sanitizeText(Str::limit($course->description ?? 'Specialized digital marketing curriculum with practical projects and verified certificate.', 130)),
                'difficulty' => $course->difficulty ?? 'All Levels',
                'duration' => $durationText,
                'price' => $priceText,
                'image_url' => $course->thumbnail_path ? asset($course->thumbnail_path) : null,
                'url' => url("/courses/{$course->slug}"),
            ];
        }

        return $cards;
    }

    /**
     * Retrieve published services.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantServices(string $academyId, array $tokens = []): array
    {
        $services = CmsService::where('academy_id', $academyId)
            ->where('is_published', true)
            ->get();

        $matched = [];
        foreach ($services as $s) {
            $text = strtolower($s->title.' '.$s->summary);
            $hasToken = false;
            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($text, $token)) {
                    $hasToken = true;
                    break;
                }
            }

            if ($hasToken || empty($tokens)) {
                $matched[] = [
                    'title' => $this->sanitizeText($s->title),
                    'slug' => $s->slug,
                    'summary' => $this->sanitizeText($s->summary),
                    'url' => url("/services/{$s->slug}"),
                ];
            }
        }

        return array_slice($matched, 0, 2);
    }

    /**
     * Retrieve approved FAQs.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantFaqs(string $academyId, string $query, array $tokens = []): array
    {
        $faqs = AiFaq::where('academy_id', $academyId)
            ->active()
            ->orderBy('display_order', 'asc')
            ->get();

        $qLower = strtolower($query);
        $matched = [];

        foreach ($faqs as $f) {
            $fText = strtolower($f->question.' '.$f->answer);
            $score = 0;

            if (str_contains($fText, $qLower) || str_contains($qLower, strtolower($f->question))) {
                $score += 50;
            }

            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($fText, $token)) {
                    $score += 10;
                }
            }

            if ($score > 0) {
                $matched[] = [
                    'faq' => $f,
                    'score' => $score,
                ];
            }
        }

        usort($matched, fn ($a, $b) => $b['score'] <=> $a['score']);

        $results = [];
        foreach (array_slice($matched, 0, 3) as $m) {
            $results[] = [
                'question' => $this->sanitizeText($m['faq']->question),
                'answer' => $this->sanitizeText($m['faq']->answer),
                'category' => $m['faq']->category ?? 'General',
            ];
        }

        return $results;
    }

    /**
     * Retrieve published CMS pages.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantPages(string $academyId, array $tokens = []): array
    {
        $pages = CmsPage::where('academy_id', $academyId)
            ->where('status', 'published')
            ->get();

        $matched = [];
        foreach ($pages as $p) {
            $text = strtolower($p->title.' '.$p->content);
            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($text, $token)) {
                    $matched[] = [
                        'title' => $this->sanitizeText($p->title),
                        'slug' => $p->slug,
                        'url' => url("/page/{$p->slug}"),
                    ];
                    break;
                }
            }
        }

        return array_slice($matched, 0, 2);
    }

    /**
     * Retrieve published blog posts.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantBlogPosts(string $academyId, array $tokens = []): array
    {
        $posts = CmsBlogPost::where('academy_id', $academyId)
            ->where('status', 'published')
            ->get();

        $matched = [];
        foreach ($posts as $b) {
            $text = strtolower($b->title.' '.$b->excerpt);
            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($text, $token)) {
                    $matched[] = [
                        'title' => $this->sanitizeText($b->title),
                        'slug' => $b->slug,
                        'url' => url("/blog/{$b->slug}"),
                    ];
                    break;
                }
            }
        }

        return array_slice($matched, 0, 2);
    }

    /**
     * Build grounded knowledge context text strictly from public records.
     *
     * @param  array<string, mixed>  $retrievalData
     */
    public function buildGroundedPromptContext(array $retrievalData): string
    {
        $out = "=== VERIFIED SEO ACADEMY PUBLIC KNOWLEDGE ===\n";
        $contact = $retrievalData['contact'];
        $out .= "ACADEMY NAME: {$contact['name']}\n";
        $out .= "CONTACT EMAIL: {$contact['support_email']}\n";
        $out .= "CONTACT PHONE: {$contact['phone']}\n";
        $out .= "CONTACT PAGE: {$contact['contact_url']}\n";
        $out .= "COURSE CATALOGUE: {$contact['courses_url']}\n";
        $out .= "REGISTRATION: {$contact['register_url']}\n\n";

        if (! empty($retrievalData['courses'])) {
            $out .= "PUBLISHED COURSES:\n";
            foreach ($retrievalData['courses'] as $c) {
                $out .= "- Title: {$c['title']} | Difficulty: {$c['difficulty']} | Duration: {$c['duration']} | Price: {$c['price']} | URL: {$c['url']}\n";
                $out .= "  Summary: {$c['description']}\n";
            }
            $out .= "\n";
        }

        if (! empty($retrievalData['services'])) {
            $out .= "PUBLISHED CONSULTING SERVICES:\n";
            foreach ($retrievalData['services'] as $s) {
                $out .= "- Service: {$s['title']} | URL: {$s['url']}\n";
                $out .= "  Summary: {$s['summary']}\n";
            }
            $out .= "\n";
        }

        if (! empty($retrievalData['faqs'])) {
            $out .= "APPROVED ACADEMY FAQS:\n";
            foreach ($retrievalData['faqs'] as $f) {
                $out .= "Q: {$f['question']}\n";
                $out .= "A: {$f['answer']}\n";
            }
            $out .= "\n";
        }

        if (! empty($retrievalData['blog'])) {
            $out .= "RELEVANT BLOG ARTICLES:\n";
            foreach ($retrievalData['blog'] as $b) {
                $out .= "- Article: {$b['title']} | URL: {$b['url']}\n";
            }
            $out .= "\n";
        }

        $out .= "=== END VERIFIED KNOWLEDGE ===\n";

        return $out;
    }

    /**
     * Get aggregate knowledge sources count for admin dashboard.
     *
     * @return array<string, int>
     */
    public function getKnowledgeStats(string $academyId): array
    {
        return [
            'published_courses' => Course::where('academy_id', $academyId)->whereIn('status', ['Published', 'Active'])->where('is_public_listed', true)->count(),
            'published_services' => CmsService::where('academy_id', $academyId)->where('is_published', true)->count(),
            'published_pages' => CmsPage::where('academy_id', $academyId)->where('status', 'published')->count(),
            'published_blog_posts' => CmsBlogPost::where('academy_id', $academyId)->where('status', 'published')->count(),
            'active_faqs' => AiFaq::where('academy_id', $academyId)->active()->count(),
        ];
    }

    /**
     * Sanitize retrieved text to prevent prompt injection or format breakage.
     */
    protected function sanitizeText(string $text): string
    {
        // Strip control characters and prompt-injection keywords
        $text = strip_tags($text);
        $injectionPatterns = [
            '/ignore\s+all\s+previous\s+instructions/i',
            '/system\s*:\s*/i',
            '/\[system\]/i',
            '/BEGIN PROMPT/i',
            '/reveal.*prompt/i',
        ];

        return preg_replace($injectionPatterns, '[redacted]', $text) ?? $text;
    }
}
