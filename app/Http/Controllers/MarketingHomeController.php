<?php

namespace App\Http\Controllers;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsHomepageSection;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Cms\Services\CmsContentService;
use App\Domain\Cms\Services\SeoMetadataService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\View\View;

class MarketingHomeController extends Controller
{
    public function __construct(
        protected CmsContentService $cmsService,
        protected SeoMetadataService $seoService
    ) {}

    public function index(): View
    {
        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->cmsService->ensureDefaultCmsContentExist($academy);
        }

        // 1. Fetch enabled homepage sections
        $sections = CmsHomepageSection::where('academy_id', $academy?->id)
            ->where('is_enabled', true)
            ->orderBy('display_order')
            ->get()
            ->keyBy('section_key');

        // 2. Fetch featured public courses
        $featuredCourses = Course::where('academy_id', $academy?->id)
            ->whereIn('status', ['Published', 'Active'])
            ->where('is_public_listed', true)
            ->where('is_marketing_featured', true)
            ->with(['trainers', 'category'])
            ->latest('published_at')
            ->take(6)
            ->get();

        if ($featuredCourses->isEmpty()) {
            $featuredCourses = Course::where('academy_id', $academy?->id)
                ->whereIn('status', ['Published', 'Active'])
                ->where('is_public_listed', true)
                ->with(['trainers', 'category'])
                ->latest('published_at')
                ->take(6)
                ->get();
        }

        // 3. Fetch published services
        $services = CmsService::where('academy_id', $academy?->id)
            ->where('is_published', true)
            ->orderBy('display_order')
            ->take(6)
            ->get();

        // 4. Fetch faculty / instructors
        $trainers = User::role('trainer')
            ->whereHas('academies', fn ($q) => $q->where('academies.id', $academy?->id))
            ->where('is_active', true)
            ->take(4)
            ->get();

        // 5. Calculate transparent, real statistics (no fake numbers)
        $liveStats = [
            'courses' => Course::where('academy_id', $academy?->id)->whereIn('status', ['Published', 'Active'])->count(),
            'modules' => Module::whereHas('course', fn ($q) => $q->where('academy_id', $academy?->id))->count(),
            'lessons' => Lesson::whereHas('module.course', fn ($q) => $q->where('academy_id', $academy?->id))->count(),
            'enrollments' => Enrollment::where('academy_id', $academy?->id)->count(),
        ];

        // 6. Generate SEO metadata and Schema.org JSON-LD
        $seoData = $this->seoService->getMetadata($academy, null);
        $jsonLd = $this->seoService->getJsonLd($academy, null);

        return view('marketing.home', [
            'academy' => $academy,
            'sections' => $sections,
            'featuredCourses' => $featuredCourses,
            'services' => $services,
            'trainers' => $trainers,
            'liveStats' => $liveStats,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }
}
