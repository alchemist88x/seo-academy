<?php

namespace App\Livewire\Admin\Website;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsBanner;
use App\Domain\Cms\Services\BannerService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Website Banner & Hero Management')]
class WebsiteBannerManager extends Component
{
    use WithFileUploads;

    public ?string $academyId = null;

    public string $filterPlacement = 'all';

    public string $search = '';

    // Modal state
    public bool $showBannerModal = false;

    public ?string $editingBannerId = null;

    // Form inputs
    public string $name = '';

    public string $placement = 'homepage_hero';

    public string $heading = '';

    public ?string $subtitle = null;

    public ?string $description = null;

    public $desktopImage = null;

    public $mobileImage = null;

    public ?string $currentDesktopImagePath = null;

    public ?string $currentMobileImagePath = null;

    public ?string $primaryCtaLabel = 'Explore Courses';

    public ?string $primaryCtaUrl = '/courses';

    public ?string $secondaryCtaLabel = 'Learn More';

    public ?string $secondaryCtaUrl = '/contact';

    public string $textAlignment = 'left';

    public string $textColor = '#ffffff';

    public string $overlayColor = '#000000';

    public int $overlayOpacity = 40;

    public string $bannerHeight = 'medium';

    public int $displayOrder = 0;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public bool $isActive = true;

    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public function mount(): void
    {
        $academy = app(TenantContext::class)->getAcademy()
            ?? Academy::current()
            ?? Academy::first();

        $this->academyId = $academy?->id;
    }

    public function openCreateModal(): void
    {
        $this->resetBannerForm();
        $this->showBannerModal = true;
    }

    public function openEditModal(string $id): void
    {
        $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($id);

        $this->editingBannerId = $banner->id;
        $this->name = $banner->name;
        $this->placement = $banner->placement;
        $this->heading = $banner->heading;
        $this->subtitle = $banner->subtitle;
        $this->description = $banner->description;
        $this->currentDesktopImagePath = $banner->desktop_image_path;
        $this->currentMobileImagePath = $banner->mobile_image_path;
        $this->primaryCtaLabel = $banner->primary_cta_label;
        $this->primaryCtaUrl = $banner->primary_cta_url;
        $this->secondaryCtaLabel = $banner->secondary_cta_label;
        $this->secondaryCtaUrl = $banner->secondary_cta_url;
        $this->textAlignment = $banner->text_alignment;
        $this->textColor = $banner->text_color;
        $this->overlayColor = $banner->overlay_color;
        $this->overlayOpacity = $banner->overlay_opacity;
        $this->bannerHeight = $banner->banner_height;
        $this->displayOrder = $banner->display_order;
        $this->startsAt = $banner->starts_at?->format('Y-m-d\TH:i');
        $this->endsAt = $banner->ends_at?->format('Y-m-d\TH:i');
        $this->isActive = $banner->is_active;

        $this->showBannerModal = true;
    }

    public function resetBannerForm(): void
    {
        $this->editingBannerId = null;
        $this->name = '';
        $this->placement = 'homepage_hero';
        $this->heading = '';
        $this->subtitle = '';
        $this->description = '';
        $this->desktopImage = null;
        $this->mobileImage = null;
        $this->currentDesktopImagePath = null;
        $this->currentMobileImagePath = null;
        $this->primaryCtaLabel = 'Explore Courses';
        $this->primaryCtaUrl = '/courses';
        $this->secondaryCtaLabel = 'Contact Admissions';
        $this->secondaryCtaUrl = '/contact';
        $this->textAlignment = 'left';
        $this->textColor = '#ffffff';
        $this->overlayColor = '#000000';
        $this->overlayOpacity = 40;
        $this->bannerHeight = 'medium';
        $this->displayOrder = 0;
        $this->startsAt = null;
        $this->endsAt = null;
        $this->isActive = true;
    }

    public function saveBanner(): void
    {
        $this->validate([
            'name' => 'required|string|max:191',
            'placement' => 'required|string|in:homepage_hero,homepage_promo,courses_promo,services_promo,blog_promo',
            'heading' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'desktopImage' => 'nullable|image|max:5120',
            'mobileImage' => 'nullable|image|max:5120',
            'primaryCtaLabel' => 'nullable|string|max:100',
            'primaryCtaUrl' => 'nullable|string|max:255',
            'secondaryCtaLabel' => 'nullable|string|max:100',
            'secondaryCtaUrl' => 'nullable|string|max:255',
            'textAlignment' => 'required|in:left,center,right',
            'overlayOpacity' => 'required|integer|min:0|max:100',
            'bannerHeight' => 'required|in:compact,medium,tall',
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
        ]);

        $service = app(BannerService::class);

        // Validate safe URLs against dangerous scripts
        if (! empty($this->primaryCtaUrl) && ! $service->isSafeUrl($this->primaryCtaUrl)) {
            $this->addError('primaryCtaUrl', 'Unsafe URL format. Must start with /, #, http://, or https://');

            return;
        }

        if (! empty($this->secondaryCtaUrl) && ! $service->isSafeUrl($this->secondaryCtaUrl)) {
            $this->addError('secondaryCtaUrl', 'Unsafe URL format. Must start with /, #, http://, or https://');

            return;
        }

        $desktopPath = $this->currentDesktopImagePath;
        if ($this->desktopImage) {
            $filename = 'banner_desk_'.Str::random(12).'.'.$this->desktopImage->getClientOriginalExtension();
            $stored = $this->desktopImage->storeAs('public/banners', $filename);
            $desktopPath = Storage::url($stored);
        }

        $mobilePath = $this->currentMobileImagePath;
        if ($this->mobileImage) {
            $filename = 'banner_mob_'.Str::random(12).'.'.$this->mobileImage->getClientOriginalExtension();
            $stored = $this->mobileImage->storeAs('public/banners', $filename);
            $mobilePath = Storage::url($stored);
        }

        $data = [
            'name' => $this->name,
            'placement' => $this->placement,
            'heading' => $this->heading,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'desktop_image_path' => $desktopPath,
            'mobile_image_path' => $mobilePath,
            'primary_cta_label' => $this->primaryCtaLabel,
            'primary_cta_url' => $this->primaryCtaUrl,
            'secondary_cta_label' => $this->secondaryCtaLabel,
            'secondary_cta_url' => $this->secondaryCtaUrl,
            'text_alignment' => $this->textAlignment,
            'text_color' => $this->textColor,
            'overlay_color' => $this->overlayColor,
            'overlay_opacity' => $this->overlayOpacity,
            'banner_height' => $this->bannerHeight,
            'display_order' => $this->displayOrder,
            'starts_at' => $this->startsAt ?: null,
            'ends_at' => $this->endsAt ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingBannerId) {
            $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($this->editingBannerId);
            $service->updateBanner($banner, $data);
            $this->feedbackMessage = 'Banner updated successfully.';
        } else {
            $service->createBanner($this->academyId, $data);
            $this->feedbackMessage = 'Banner created successfully.';
        }

        $this->feedbackType = 'success';
        $this->showBannerModal = false;
        $this->resetBannerForm();
    }

    public function toggleActive(string $id): void
    {
        $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);

        $this->feedbackMessage = 'Banner status updated.';
        $this->feedbackType = 'success';
    }

    public function duplicateBanner(string $id): void
    {
        $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($id);
        app(BannerService::class)->duplicateBanner($banner);

        $this->feedbackMessage = 'Banner duplicated as inactive draft copy.';
        $this->feedbackType = 'success';
    }

    public function deleteBanner(string $id): void
    {
        $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($id);
        $banner->delete();

        $this->feedbackMessage = 'Banner deleted.';
        $this->feedbackType = 'success';
    }

    public function moveUp(string $id): void
    {
        $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($id);
        $banner->decrement('display_order');
    }

    public function moveDown(string $id): void
    {
        $banner = CmsBanner::where('academy_id', $this->academyId)->findOrFail($id);
        $banner->increment('display_order');
    }

    public function render(): View
    {
        $query = CmsBanner::where('academy_id', $this->academyId)
            ->orderBy('placement')
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc');

        if ($this->filterPlacement !== 'all') {
            $query->where('placement', $this->filterPlacement);
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('heading', 'like', "%{$this->search}%")
                    ->orWhere('subtitle', 'like', "%{$this->search}%");
            });
        }

        $banners = $query->get();

        return view('livewire.admin.website.website-banner-manager', [
            'banners' => $banners,
        ]);
    }
}
