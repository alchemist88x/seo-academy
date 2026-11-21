<?php

namespace App\Livewire\Admin\Website;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBlogCategory;
use App\Domain\Cms\Models\CmsBlogPost;
use App\Domain\Cms\Models\CmsContactInquiry;
use App\Domain\Cms\Models\CmsHomepageSection;
use App\Domain\Cms\Models\CmsMediaAsset;
use App\Domain\Cms\Models\CmsNavigationItem;
use App\Domain\Cms\Models\CmsPage;
use App\Domain\Cms\Models\CmsService;
use App\Domain\Cms\Services\CmsContentService;
use App\Domain\Cms\Services\RobotsTxtService;
use App\Domain\Cms\Services\SitemapService;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Website CMS & SEO Management')]
class WebsiteManager extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $activeTab = 'homepage'; // homepage, pages, services, courses, navigation, blog, media, inquiries, seo

    // Feedback messages
    public ?string $feedbackMessage = null;

    public ?string $feedbackType = 'success'; // success, error

    // --- Tab 1: Homepage Builder ---
    public ?string $editingSectionId = null;

    public string $sectionTitle = '';

    public string $sectionSubtitle = '';

    public bool $sectionEnabled = true;

    public array $sectionContentData = [];

    // --- Tab 2: Pages Management ---
    public bool $showPageModal = false;

    public ?string $editingPageId = null;

    public string $pageTitle = '';

    public string $pageSlug = '';

    public string $pageContent = '';

    public string $pageStatus = 'published';

    public ?string $pageSeoTitle = '';

    public ?string $pageSeoDescription = '';

    public ?string $pageCanonicalUrl = '';

    public ?string $pageRobots = 'index, follow';

    // --- Tab 3: Services Management ---
    public bool $showServiceModal = false;

    public ?string $editingServiceId = null;

    public string $serviceTitle = '';

    public string $serviceSlug = '';

    public string $serviceSummary = '';

    public string $serviceDescription = '';

    public string $serviceBenefitsText = ''; // Newline-separated in textarea

    public string $serviceIcon = 'briefcase';

    public int $serviceDisplayOrder = 0;

    public bool $serviceIsPublished = true;

    public ?string $serviceSeoTitle = '';

    public ?string $serviceSeoDescription = '';

    // --- Tab 4: Course Marketing Management ---
    public ?string $editingCourseId = null;

    public bool $courseIsPublicListed = true;

    public bool $courseIsMarketingFeatured = false;

    public string $courseMarketingDescription = '';

    public string $courseMarketingHighlightsText = ''; // Newline separated

    public ?string $courseSeoTitle = '';

    public ?string $courseSeoDescription = '';

    // --- Tab 5: Navigation Management ---
    public string $navMenuLocation = 'header'; // header or footer

    public bool $showNavModal = false;

    public ?string $editingNavId = null;

    public string $navTitle = '';

    public string $navUrl = '';

    public string $navTarget = '_self';

    public int $navDisplayOrder = 0;

    public bool $navIsActive = true;

    // --- Tab 6: Blog Management ---
    public string $blogSubTab = 'posts'; // posts or categories

    public bool $showPostModal = false;

    public ?string $editingPostId = null;

    public string $postTitle = '';

    public string $postSlug = '';

    public ?string $postCategoryId = null;

    public string $postExcerpt = '';

    public string $postContent = '';

    public string $postStatus = 'published';

    public ?string $postPublishedAt = null;

    public ?string $postSeoTitle = '';

    public ?string $postSeoDescription = '';

    public $postFeaturedImage = null;

    // Category modal
    public bool $showCategoryModal = false;

    public ?string $editingCategoryId = null;

    public string $categoryName = '';

    public string $categorySlug = '';

    public string $categoryDescription = '';

    // --- Tab 7: Media Library ---
    public $mediaUpload = null;

    public string $mediaAltText = '';

    public string $mediaTitle = '';

    // --- Tab 8: Inquiries / Leads ---
    #[Url]
    public string $inquiryStatusFilter = 'all';

    public ?string $viewingInquiryId = null;

    public string $inquiryAdminNotes = '';

    public string $inquiryUpdateStatus = 'new';

    // --- Tab 9: SEO & SERP Preview ---
    public string $serpPreviewDevice = 'desktop'; // desktop or mobile

    public function mount(CmsContentService $cmsService, TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized access to website CMS.');
        }

        $academy = $tenantContext->getAcademy() ?? Academy::first();
        if ($academy) {
            $cmsService->ensureDefaultCmsContentExist($academy);
        }
    }

    protected function getAcademy(TenantContext $tenantContext): ?Academy
    {
        return $tenantContext->getAcademy() ?? Academy::first();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->feedbackMessage = null;
    }

    // ==========================================
    // TAB 1: HOMEPAGE BUILDER
    // ==========================================
    public function toggleSection(string $sectionId): void
    {
        $section = CmsHomepageSection::findOrFail($sectionId);
        $section->update(['is_enabled' => ! $section->is_enabled]);
        $this->feedbackMessage = "Section '{$section->name}' visibility updated.";
    }

    public function reorderSection(string $sectionId, string $direction): void
    {
        $section = CmsHomepageSection::findOrFail($sectionId);
        $other = $direction === 'up'
            ? CmsHomepageSection::where('academy_id', $section->academy_id)
                ->where('display_order', '<', $section->display_order)
                ->orderByDesc('display_order')
                ->first()
            : CmsHomepageSection::where('academy_id', $section->academy_id)
                ->where('display_order', '>', $section->display_order)
                ->orderBy('display_order')
                ->first();

        if ($other) {
            $currentOrder = $section->display_order;
            $section->update(['display_order' => $other->display_order]);
            $other->update(['display_order' => $currentOrder]);
            $this->feedbackMessage = 'Section order updated.';
        }
    }

    public function editSection(string $sectionId): void
    {
        $section = CmsHomepageSection::findOrFail($sectionId);
        $this->editingSectionId = $section->id;
        $this->sectionTitle = $section->title ?? '';
        $this->sectionSubtitle = $section->subtitle ?? '';
        $this->sectionEnabled = (bool) $section->is_enabled;
        $this->sectionContentData = $section->content_data ?? [];
    }

    public function saveSection(): void
    {
        if (! $this->editingSectionId) {
            return;
        }

        $section = CmsHomepageSection::findOrFail($this->editingSectionId);
        $section->update([
            'title' => $this->sectionTitle,
            'subtitle' => $this->sectionSubtitle,
            'is_enabled' => $this->sectionEnabled,
            'content_data' => $this->sectionContentData,
        ]);

        $this->editingSectionId = null;
        $this->feedbackMessage = "Section '{$section->name}' saved successfully.";
    }

    public function cancelSectionEdit(): void
    {
        $this->editingSectionId = null;
    }

    // ==========================================
    // TAB 2: PAGES MANAGEMENT
    // ==========================================
    public function openPageModal(?string $pageId = null): void
    {
        $this->resetPageForm();
        if ($pageId) {
            $page = CmsPage::findOrFail($pageId);
            $this->editingPageId = $page->id;
            $this->pageTitle = $page->title;
            $this->pageSlug = $page->slug;
            $this->pageContent = $page->content ?? '';
            $this->pageStatus = $page->status;
            $this->pageSeoTitle = $page->seo_title;
            $this->pageSeoDescription = $page->seo_description;
            $this->pageCanonicalUrl = $page->canonical_url;
            $this->pageRobots = $page->robots;
        }
        $this->showPageModal = true;
    }

    public function updatedPageTitle($value): void
    {
        if (! $this->editingPageId && empty($this->pageSlug)) {
            $this->pageSlug = Str::slug($value);
        }
    }

    public function savePage(TenantContext $tenantContext, CmsContentService $cmsService): void
    {
        $academy = $this->getAcademy($tenantContext);

        $this->validate([
            'pageTitle' => 'required|string|max:150',
            'pageSlug' => 'required|string|max:100|regex:/^[a-z0-9-]+$/',
            'pageContent' => 'required|string',
            'pageStatus' => 'required|in:draft,published,archived',
            'pageSeoTitle' => 'nullable|string|max:150',
            'pageSeoDescription' => 'nullable|string|max:300',
        ]);

        $attributes = [
            'title' => $this->pageTitle,
            'slug' => $this->pageSlug,
            'content' => $this->pageContent,
            'status' => $this->pageStatus,
            'seo_title' => $this->pageSeoTitle,
            'seo_description' => $this->pageSeoDescription,
            'canonical_url' => $this->pageCanonicalUrl,
            'robots' => $this->pageRobots,
        ];

        if ($this->editingPageId) {
            $page = CmsPage::findOrFail($this->editingPageId);
            $cmsService->updatePageWithRedirect($page, $attributes);
            $this->feedbackMessage = "Page '{$page->title}' updated successfully.";
        } else {
            $attributes['academy_id'] = $academy?->id;
            $page = CmsPage::create($attributes);
            $this->feedbackMessage = "New page '{$page->title}' created successfully.";
        }

        $this->showPageModal = false;
        $this->resetPageForm();
    }

    public function deletePage(string $pageId): void
    {
        $page = CmsPage::findOrFail($pageId);
        $title = $page->title;
        $page->delete();
        $this->feedbackMessage = "Page '{$title}' removed.";
    }

    protected function resetPageForm(): void
    {
        $this->editingPageId = null;
        $this->pageTitle = '';
        $this->pageSlug = '';
        $this->pageContent = '';
        $this->pageStatus = 'published';
        $this->pageSeoTitle = '';
        $this->pageSeoDescription = '';
        $this->pageCanonicalUrl = '';
        $this->pageRobots = 'index, follow';
    }

    // ==========================================
    // TAB 3: SERVICES MANAGEMENT
    // ==========================================
    public function openServiceModal(?string $serviceId = null): void
    {
        $this->resetServiceForm();
        if ($serviceId) {
            $service = CmsService::findOrFail($serviceId);
            $this->editingServiceId = $service->id;
            $this->serviceTitle = $service->title;
            $this->serviceSlug = $service->slug;
            $this->serviceSummary = $service->summary ?? '';
            $this->serviceDescription = $service->description ?? '';
            $this->serviceBenefitsText = implode("\n", $service->benefits ?? []);
            $this->serviceIcon = $service->icon ?? 'briefcase';
            $this->serviceDisplayOrder = (int) $service->display_order;
            $this->serviceIsPublished = (bool) $service->is_published;
            $this->serviceSeoTitle = $service->seo_title;
            $this->serviceSeoDescription = $service->seo_description;
        }
        $this->showServiceModal = true;
    }

    public function updatedServiceTitle($value): void
    {
        if (! $this->editingServiceId && empty($this->serviceSlug)) {
            $this->serviceSlug = Str::slug($value);
        }
    }

    public function saveService(TenantContext $tenantContext): void
    {
        $academy = $this->getAcademy($tenantContext);

        $this->validate([
            'serviceTitle' => 'required|string|max:150',
            'serviceSlug' => 'required|string|max:100|regex:/^[a-z0-9-]+$/',
            'serviceSummary' => 'required|string|max:500',
            'serviceDescription' => 'required|string',
            'serviceDisplayOrder' => 'required|integer',
        ]);

        $benefits = array_values(array_filter(array_map('trim', explode("\n", $this->serviceBenefitsText))));

        $data = [
            'title' => $this->serviceTitle,
            'slug' => $this->serviceSlug,
            'short_description' => $this->serviceSummary,
            'full_description' => $this->serviceDescription,
            'summary' => $this->serviceSummary,
            'description' => $this->serviceDescription,
            'benefits' => $benefits,
            'icon' => $this->serviceIcon,
            'display_order' => $this->serviceDisplayOrder,
            'is_published' => $this->serviceIsPublished,
            'seo_title' => $this->serviceSeoTitle,
            'seo_description' => $this->serviceSeoDescription,
        ];

        if ($this->editingServiceId) {
            $service = CmsService::findOrFail($this->editingServiceId);
            $service->update($data);
            $this->feedbackMessage = "Service '{$service->title}' updated.";
        } else {
            $data['academy_id'] = $academy?->id;
            $service = CmsService::create($data);
            $this->feedbackMessage = "Service '{$service->title}' created.";
        }

        $this->showServiceModal = false;
        $this->resetServiceForm();
    }

    public function deleteService(string $serviceId): void
    {
        $service = CmsService::findOrFail($serviceId);
        $title = $service->title;
        $service->delete();
        $this->feedbackMessage = "Service '{$title}' deleted.";
    }

    protected function resetServiceForm(): void
    {
        $this->editingServiceId = null;
        $this->serviceTitle = '';
        $this->serviceSlug = '';
        $this->serviceSummary = '';
        $this->serviceDescription = '';
        $this->serviceBenefitsText = '';
        $this->serviceIcon = 'briefcase';
        $this->serviceDisplayOrder = 0;
        $this->serviceIsPublished = true;
        $this->serviceSeoTitle = '';
        $this->serviceSeoDescription = '';
    }

    // ==========================================
    // TAB 4: COURSE MARKETING
    // ==========================================
    public function editCourseMarketing(string $courseId): void
    {
        $course = Course::findOrFail($courseId);
        $this->editingCourseId = $course->id;
        $this->courseIsPublicListed = (bool) $course->is_public_listed;
        $this->courseIsMarketingFeatured = (bool) $course->is_marketing_featured;
        $this->courseMarketingDescription = $course->marketing_description ?? '';
        $this->courseMarketingHighlightsText = implode("\n", $course->marketing_highlights ?? []);
        $this->courseSeoTitle = $course->seo_title;
        $this->courseSeoDescription = $course->seo_description;
    }

    public function saveCourseMarketing(): void
    {
        if (! $this->editingCourseId) {
            return;
        }

        $course = Course::findOrFail($this->editingCourseId);
        $highlights = array_values(array_filter(array_map('trim', explode("\n", $this->courseMarketingHighlightsText))));

        $course->update([
            'is_public_listed' => $this->courseIsPublicListed,
            'is_marketing_featured' => $this->courseIsMarketingFeatured,
            'marketing_description' => $this->courseMarketingDescription,
            'marketing_highlights' => $highlights,
            'seo_title' => $this->courseSeoTitle,
            'seo_description' => $this->courseSeoDescription,
        ]);

        $this->editingCourseId = null;
        $this->feedbackMessage = "Marketing details for '{$course->title}' saved.";
    }

    public function cancelCourseMarketing(): void
    {
        $this->editingCourseId = null;
    }

    // ==========================================
    // TAB 5: NAVIGATION MANAGEMENT
    // ==========================================
    public function openNavModal(?string $navId = null): void
    {
        $this->resetNavForm();
        if ($navId) {
            $item = CmsNavigationItem::findOrFail($navId);
            $this->editingNavId = $item->id;
            $this->navMenuLocation = $item->menu_location;
            $this->navTitle = $item->title;
            $this->navUrl = $item->url;
            $this->navTarget = $item->target;
            $this->navDisplayOrder = (int) $item->display_order;
            $this->navIsActive = (bool) $item->is_active;
        }
        $this->showNavModal = true;
    }

    public function saveNavItem(TenantContext $tenantContext, CmsContentService $cmsService): void
    {
        $academy = $this->getAcademy($tenantContext);

        $this->validate([
            'navTitle' => 'required|string|max:80',
            'navUrl' => 'required|string|max:255',
            'navTarget' => 'required|in:_self,_blank',
            'navMenuLocation' => 'required|in:header,footer',
            'navDisplayOrder' => 'required|integer',
        ]);

        if (! $cmsService->isSafeNavigationUrl($this->navUrl)) {
            $this->addError('navUrl', 'Invalid or unsafe URL scheme provided.');

            return;
        }

        $data = [
            'menu_location' => $this->navMenuLocation,
            'title' => $this->navTitle,
            'label' => $this->navTitle,
            'url' => $this->navUrl,
            'target' => $this->navTarget,
            'display_order' => $this->navDisplayOrder,
            'is_active' => $this->navIsActive,
        ];

        if ($this->editingNavId) {
            $item = CmsNavigationItem::findOrFail($this->editingNavId);
            $item->update($data);
            $this->feedbackMessage = "Navigation item '{$item->title}' updated.";
        } else {
            $data['academy_id'] = $academy?->id;
            $item = CmsNavigationItem::create($data);
            $this->feedbackMessage = "Navigation item '{$item->title}' added.";
        }

        $this->showNavModal = false;
        $this->resetNavForm();
    }

    public function deleteNavItem(string $navId): void
    {
        $item = CmsNavigationItem::findOrFail($navId);
        $title = $item->title;
        $item->delete();
        $this->feedbackMessage = "Navigation item '{$title}' removed.";
    }

    protected function resetNavForm(): void
    {
        $this->editingNavId = null;
        $this->navTitle = '';
        $this->navUrl = '';
        $this->navTarget = '_self';
        $this->navDisplayOrder = 0;
        $this->navIsActive = true;
    }

    // ==========================================
    // TAB 6: BLOG MANAGEMENT
    // ==========================================
    public function openPostModal(?string $postId = null): void
    {
        $this->resetPostForm();
        if ($postId) {
            $post = CmsBlogPost::findOrFail($postId);
            $this->editingPostId = $post->id;
            $this->postTitle = $post->title;
            $this->postSlug = $post->slug;
            $this->postCategoryId = $post->category_id ? (string) $post->category_id : null;
            $this->postExcerpt = $post->excerpt ?? '';
            $this->postContent = $post->content ?? '';
            $this->postStatus = $post->status;
            $this->postPublishedAt = $post->published_at?->format('Y-m-d\TH:i') ?? null;
            $this->postSeoTitle = $post->seo_title;
            $this->postSeoDescription = $post->seo_description;
        }
        $this->showPostModal = true;
    }

    public function updatedPostTitle($value): void
    {
        if (! $this->editingPostId && empty($this->postSlug)) {
            $this->postSlug = Str::slug($value);
        }
    }

    public function savePost(TenantContext $tenantContext): void
    {
        $academy = $this->getAcademy($tenantContext);

        $this->validate([
            'postTitle' => 'required|string|max:180',
            'postSlug' => 'required|string|max:120|regex:/^[a-z0-9-]+$/',
            'postContent' => 'required|string',
            'postStatus' => 'required|in:draft,published,scheduled',
            'postFeaturedImage' => 'nullable|image|max:3072',
        ]);

        $publishedAt = null;
        if ($this->postStatus === 'published') {
            $publishedAt = $this->postPublishedAt ? date('Y-m-d H:i:s', strtotime($this->postPublishedAt)) : now();
        }

        $data = [
            'title' => $this->postTitle,
            'slug' => $this->postSlug,
            'category_id' => $this->postCategoryId ?: null,
            'excerpt' => $this->postExcerpt,
            'content' => $this->postContent,
            'status' => $this->postStatus,
            'published_at' => $publishedAt,
            'seo_title' => $this->postSeoTitle,
            'seo_description' => $this->postSeoDescription,
        ];

        if ($this->postFeaturedImage) {
            $path = $this->postFeaturedImage->store('cms_blog', 'public');
            $data['featured_image_path'] = 'storage/'.$path;
        }

        if ($this->editingPostId) {
            $post = CmsBlogPost::findOrFail($this->editingPostId);
            $post->update($data);
            $this->feedbackMessage = "Article '{$post->title}' updated.";
        } else {
            $data['academy_id'] = $academy?->id;
            $data['author_id'] = Auth::id();
            $post = CmsBlogPost::create($data);
            $this->feedbackMessage = "New article '{$post->title}' published.";
        }

        $this->showPostModal = false;
        $this->resetPostForm();
    }

    public function deletePost(string $postId): void
    {
        $post = CmsBlogPost::findOrFail($postId);
        $title = $post->title;
        $post->delete();
        $this->feedbackMessage = "Article '{$title}' deleted.";
    }

    protected function resetPostForm(): void
    {
        $this->editingPostId = null;
        $this->postTitle = '';
        $this->postSlug = '';
        $this->postCategoryId = null;
        $this->postExcerpt = '';
        $this->postContent = '';
        $this->postStatus = 'published';
        $this->postPublishedAt = null;
        $this->postSeoTitle = '';
        $this->postSeoDescription = '';
        $this->postFeaturedImage = null;
    }

    // Category methods
    public function openCategoryModal(?string $categoryId = null): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categorySlug = '';
        $this->categoryDescription = '';

        if ($categoryId) {
            $cat = CmsBlogCategory::findOrFail($categoryId);
            $this->editingCategoryId = $cat->id;
            $this->categoryName = $cat->name;
            $this->categorySlug = $cat->slug;
            $this->categoryDescription = $cat->description ?? '';
        }
        $this->showCategoryModal = true;
    }

    public function updatedCategoryName($value): void
    {
        if (! $this->editingCategoryId && empty($this->categorySlug)) {
            $this->categorySlug = Str::slug($value);
        }
    }

    public function saveCategory(TenantContext $tenantContext): void
    {
        $academy = $this->getAcademy($tenantContext);
        $this->validate([
            'categoryName' => 'required|string|max:100',
            'categorySlug' => 'required|string|max:100|regex:/^[a-z0-9-]+$/',
        ]);

        $data = [
            'name' => $this->categoryName,
            'slug' => $this->categorySlug,
            'description' => $this->categoryDescription,
        ];

        if ($this->editingCategoryId) {
            $cat = CmsBlogCategory::findOrFail($this->editingCategoryId);
            $cat->update($data);
            $this->feedbackMessage = "Category '{$cat->name}' updated.";
        } else {
            $data['academy_id'] = $academy?->id;
            $cat = CmsBlogCategory::create($data);
            $this->feedbackMessage = "Category '{$cat->name}' created.";
        }

        $this->showCategoryModal = false;
    }

    public function deleteCategory(string $categoryId): void
    {
        $cat = CmsBlogCategory::findOrFail($categoryId);
        $name = $cat->name;
        $cat->delete();
        $this->feedbackMessage = "Category '{$name}' deleted.";
    }

    // ==========================================
    // TAB 7: MEDIA LIBRARY
    // ==========================================
    public function uploadMediaAsset(TenantContext $tenantContext): void
    {
        $academy = $this->getAcademy($tenantContext);

        $this->validate([
            'mediaUpload' => 'required|image|max:5120', // max 5MB
            'mediaAltText' => 'nullable|string|max:200',
            'mediaTitle' => 'nullable|string|max:150',
        ]);

        $filename = $this->mediaUpload->getClientOriginalName();
        $mime = $this->mediaUpload->getMimeType();
        $size = $this->mediaUpload->getSize();

        // Store into public disk
        $path = $this->mediaUpload->store('cms_media', 'public');
        $publicUrl = '/storage/'.$path;

        CmsMediaAsset::create([
            'academy_id' => $academy?->id,
            'file_name' => $filename,
            'title' => $this->mediaTitle ?: pathinfo($filename, PATHINFO_FILENAME),
            'alt_text' => $this->mediaAltText,
            'file_path' => $publicUrl,
            'disk' => 'public',
            'mime_type' => $mime,
            'file_size_bytes' => $size,
        ]);

        $this->mediaUpload = null;
        $this->mediaAltText = '';
        $this->mediaTitle = '';
        $this->feedbackMessage = "Public media asset '{$filename}' uploaded.";
    }

    public function deleteMediaAsset(string $assetId): void
    {
        $asset = CmsMediaAsset::findOrFail($assetId);
        $title = $asset->title;

        // Delete from disk if possible
        $diskPath = Str::after($asset->file_path, '/storage/');
        if ($diskPath && Storage::disk('public')->exists($diskPath)) {
            Storage::disk('public')->delete($diskPath);
        }

        $asset->delete();
        $this->feedbackMessage = "Asset '{$title}' deleted.";
    }

    // ==========================================
    // TAB 8: CONTACT INQUIRIES
    // ==========================================
    public function viewInquiry(string $inquiryId): void
    {
        $inquiry = CmsContactInquiry::findOrFail($inquiryId);
        $this->viewingInquiryId = $inquiry->id;
        $this->inquiryAdminNotes = $inquiry->admin_notes ?? '';
        $this->inquiryUpdateStatus = $inquiry->status;

        // Auto mark as read if new
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'read']);
            $this->inquiryUpdateStatus = 'read';
        }
    }

    public function closeInquiryModal(): void
    {
        $this->viewingInquiryId = null;
    }

    public function saveInquiryDetails(): void
    {
        if (! $this->viewingInquiryId) {
            return;
        }

        $inquiry = CmsContactInquiry::findOrFail($this->viewingInquiryId);
        $inquiry->update([
            'status' => $this->inquiryUpdateStatus,
            'admin_notes' => $this->inquiryAdminNotes,
            'resolved_at' => $this->inquiryUpdateStatus === 'resolved' ? ($inquiry->resolved_at ?? now()) : null,
        ]);

        $this->feedbackMessage = 'Inquiry record updated.';
        $this->viewingInquiryId = null;
    }

    public function deleteInquiry(string $inquiryId): void
    {
        $inquiry = CmsContactInquiry::findOrFail($inquiryId);
        $inquiry->delete();
        $this->feedbackMessage = 'Inquiry record deleted.';
    }

    // ==========================================
    // RENDER
    // ==========================================
    public function render(TenantContext $tenantContext, SitemapService $sitemapService, RobotsTxtService $robotsService): View
    {
        $academy = $this->getAcademy($tenantContext);
        $academyId = $academy?->id;

        // Data for Tab 1: Homepage
        $sections = CmsHomepageSection::where('academy_id', $academyId)
            ->orderBy('display_order')
            ->get();

        // Data for Tab 2: Pages
        $pages = CmsPage::where('academy_id', $academyId)
            ->latest('updated_at')
            ->paginate(10, pageName: 'pagesPage');

        // Data for Tab 3: Services
        $services = CmsService::where('academy_id', $academyId)
            ->orderBy('display_order')
            ->get();

        // Data for Tab 4: Courses
        $courses = Course::where('academy_id', $academyId)
            ->whereIn('status', ['Published', 'Active'])
            ->latest()
            ->get();

        // Data for Tab 5: Navigation
        $navItems = CmsNavigationItem::where('academy_id', $academyId)
            ->where('menu_location', $this->navMenuLocation)
            ->orderBy('display_order')
            ->get();

        // Data for Tab 6: Blog
        $blogPosts = CmsBlogPost::where('academy_id', $academyId)
            ->with(['category', 'author'])
            ->latest()
            ->paginate(10, pageName: 'blogPage');

        $blogCategories = CmsBlogCategory::where('academy_id', $academyId)
            ->withCount('posts')
            ->orderBy('display_order')
            ->get();

        // Data for Tab 7: Media
        $mediaAssets = CmsMediaAsset::where('academy_id', $academyId)
            ->latest()
            ->paginate(12, pageName: 'mediaPage');

        // Data for Tab 8: Inquiries
        $inquiriesQuery = CmsContactInquiry::where('academy_id', $academyId);
        if ($this->inquiryStatusFilter !== 'all') {
            $inquiriesQuery->where('status', $this->inquiryStatusFilter);
        }
        $inquiries = $inquiriesQuery->latest('created_at')->paginate(10, pageName: 'inquiriesPage');
        $inquiryCounts = [
            'all' => CmsContactInquiry::where('academy_id', $academyId)->count(),
            'new' => CmsContactInquiry::where('academy_id', $academyId)->where('status', 'new')->count(),
            'read' => CmsContactInquiry::where('academy_id', $academyId)->where('status', 'read')->count(),
            'in_progress' => CmsContactInquiry::where('academy_id', $academyId)->where('status', 'in_progress')->count(),
            'resolved' => CmsContactInquiry::where('academy_id', $academyId)->where('status', 'resolved')->count(),
        ];

        // Data for Tab 9: SEO & Robots Preview
        $robotsContent = $robotsService->generateRobotsTxt();
        $sitemapXml = $sitemapService->generateXml($academy);
        $sitemapUrlCount = substr_count($sitemapXml, '<loc>');

        return view('livewire.admin.website.website-manager', [
            'academy' => $academy,
            'sections' => $sections,
            'pages' => $pages,
            'services' => $services,
            'courses' => $courses,
            'navItems' => $navItems,
            'blogPosts' => $blogPosts,
            'blogCategories' => $blogCategories,
            'mediaAssets' => $mediaAssets,
            'inquiries' => $inquiries,
            'inquiryCounts' => $inquiryCounts,
            'robotsContent' => $robotsContent,
            'sitemapUrlCount' => $sitemapUrlCount,
            'activeInquiry' => $this->viewingInquiryId ? CmsContactInquiry::find($this->viewingInquiryId) : null,
        ]);
    }
}
