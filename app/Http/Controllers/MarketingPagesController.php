<?php

namespace App\Http\Controllers;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Models\CmsRedirect;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Cms\Services\CmsContentService;
use App\Domain\Cms\Services\ContactInquiryService;
use App\Domain\Cms\Services\ContactValidationService;
use App\Domain\Cms\Services\SeoMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingPagesController extends Controller
{
    public function __construct(
        protected CmsContentService $cmsService,
        protected SeoMetadataService $seoService,
        protected ContactValidationService $validator,
        protected ContactInquiryService $inquiryService
    ) {}

    /**
     * Display About Us page.
     */
    public function about(): View
    {
        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->cmsService->ensureDefaultCmsContentExist($academy);
        }

        $page = CmsPage::where('academy_id', $academy?->id)
            ->where('slug', 'about')
            ->where('status', 'published')
            ->first();

        $seoData = $this->seoService->getMetadata($academy, $page, [
            'title' => 'About Our Academy & Specialized Faculty',
        ]);
        $jsonLd = $this->seoService->getJsonLd($academy, $page);

        return view('marketing.pages.about', [
            'academy' => $academy,
            'page' => $page,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Display Contact Us page.
     */
    public function contact(): View
    {
        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->cmsService->ensureDefaultCmsContentExist($academy);
        }

        $page = CmsPage::where('academy_id', $academy?->id)
            ->where('slug', 'contact')
            ->where('status', 'published')
            ->first();

        $services = CmsService::where('academy_id', $academy?->id)
            ->where('is_published', true)
            ->get();

        $seoData = $this->seoService->getMetadata($academy, $page, [
            'title' => 'Contact Admissions & Corporate Training Faculty',
        ]);
        $jsonLd = $this->seoService->getJsonLd($academy, $page);

        $countries = $this->validator->getSupportedCountries();

        return view('marketing.pages.contact', [
            'academy' => $academy,
            'page' => $page,
            'services' => $services,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
            'countries' => $countries,
        ]);
    }

    /**
     * Handle public contact form inquiry submission.
     */
    public function submitContact(Request $request): RedirectResponse
    {
        $academy = Academy::current() ?? Academy::first();

        // Spam honeypot: 'website_hp' field must be empty
        if (! empty($request->input('website_hp'))) {
            return back()->with('success', 'Thank you! Your message has been received.');
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|max:254',
            'phone' => 'nullable|string|max:50',
            'phone_country_code' => 'nullable|string|max:10',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:3000',
            'interested_service' => 'nullable|string|max:100',
            'consent' => 'accepted',
        ], [
            'consent.accepted' => 'You must agree to the admissions contact terms before submitting.',
        ]);

        // Worldwide Email Validation
        $emailCheck = $this->validator->validateEmail($request->input('email'));
        if (! $emailCheck['valid']) {
            return back()->withErrors(['email' => $emailCheck['error']])->withInput();
        }

        // Worldwide Phone Validation (if entered)
        $countryCode = $request->input('phone_country_code') ?: 'IN';
        $phoneInput = $request->input('phone');
        if (! empty($phoneInput)) {
            $phoneCheck = $this->validator->validatePhone($phoneInput, $countryCode);
            if (! $phoneCheck['valid']) {
                return back()->withErrors(['phone' => $phoneCheck['error']])->withInput();
            }
        }

        $consentText = 'I agree that SEO Academy may contact me about courses and admissions using the contact details I have provided.';

        $this->inquiryService->createInquiry([
            'academy_id' => $academy?->id,
            'name' => $request->input('name'),
            'email' => $emailCheck['normalized'],
            'phone' => $phoneInput,
            'phone_country_code' => $countryCode,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'course_interest' => $request->input('interested_service'),
            'interested_service' => $request->input('interested_service'),
            'source' => 'contact_form',
            'consent_text' => $consentText,
            'consented_at' => now(),
            'consent_version' => 'v1.0',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Thank you for reaching out! Our academic desk will review your inquiry and get back to you promptly.');
    }

    /**
     * Display all public academy services.
     */
    public function services(): View
    {
        $academy = Academy::current() ?? Academy::first();
        if ($academy) {
            $this->cmsService->ensureDefaultCmsContentExist($academy);
        }

        $services = CmsService::where('academy_id', $academy?->id)
            ->where('is_published', true)
            ->orderBy('display_order')
            ->get();

        $seoData = $this->seoService->getMetadata($academy, null, [
            'title' => 'Academy Training Programs & Consulting Services',
            'description' => 'Explore professional search optimization training, technical site audits, team coaching, and corporate advisory services.',
        ]);
        $jsonLd = $this->seoService->getJsonLd($academy, null);

        return view('marketing.services.index', [
            'academy' => $academy,
            'services' => $services,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Display a specific service detail page.
     */
    public function serviceDetail(string $slug): View|RedirectResponse
    {
        $academy = Academy::current() ?? Academy::first();

        // Check redirects
        $redirect = CmsRedirect::where('academy_id', $academy?->id)
            ->where('source_path', "/services/{$slug}")
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            return redirect($redirect->target_path, $redirect->status_code);
        }

        $service = CmsService::where('academy_id', $academy?->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $otherServices = CmsService::where('academy_id', $academy?->id)
            ->where('id', '!=', $service->id)
            ->where('is_published', true)
            ->take(3)
            ->get();

        $seoData = $this->seoService->getMetadata($academy, $service);
        $jsonLd = $this->seoService->getJsonLd($academy, $service);

        return view('marketing.services.show', [
            'academy' => $academy,
            'service' => $service,
            'otherServices' => $otherServices,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Display a dynamic CMS page (e.g. /page/{slug}).
     */
    public function showPage(string $slug): View|RedirectResponse
    {
        $academy = Academy::current() ?? Academy::first();

        // Check redirects
        $redirect = CmsRedirect::where('academy_id', $academy?->id)
            ->where('source_path', "/page/{$slug}")
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            return redirect($redirect->target_path, $redirect->status_code);
        }

        $page = CmsPage::where('academy_id', $academy?->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $seoData = $this->seoService->getMetadata($academy, $page);
        $jsonLd = $this->seoService->getJsonLd($academy, $page);

        return view('marketing.pages.show', [
            'academy' => $academy,
            'page' => $page,
            'seoData' => $seoData,
            'jsonLd' => $jsonLd,
        ]);
    }
}
