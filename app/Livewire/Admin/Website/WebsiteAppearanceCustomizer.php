<?php

namespace App\Livewire\Admin\Website;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsWebsiteTheme;
use App\Domain\Cms\Services\ThemeAppearanceService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Website Appearance & Design System')]
class WebsiteAppearanceCustomizer extends Component
{
    public ?string $academyId = null;

    /**
     * @var array<string, mixed>
     */
    public array $settings = [];

    public string $activeSection = 'colors'; // colors, header, footer, buttons, cards, typography, presets

    public string $previewDevice = 'desktop'; // desktop, tablet, mobile

    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public bool $hasUnpublishedChanges = false;

    public function mount(): void
    {
        $academy = app(TenantContext::class)->getAcademy()
            ?? Academy::current()
            ?? Academy::first();

        $this->academyId = $academy?->id;

        if ($this->academyId) {
            $service = app(ThemeAppearanceService::class);
            $draft = $service->getOrCreateDraft($this->academyId);
            $this->settings = $draft->settings ?? CmsWebsiteTheme::getDefaultSettings();
            $published = $service->getPublishedTheme($this->academyId);
            $this->hasUnpublishedChanges = $published ? ($published->updated_at < $draft->updated_at) : true;
        } else {
            $this->settings = CmsWebsiteTheme::getDefaultSettings();
        }
    }

    public function updatedSettings(): void
    {
        $this->hasUnpublishedChanges = true;
    }

    public function applyPreset(string $preset): void
    {
        $presets = [
            'indigo' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#1e293b',
                'accent_color' => '#0d9488',
                'bg_main' => '#ffffff',
                'bg_secondary' => '#f8fafc',
                'header_bg' => '#ffffff',
                'footer_bg' => '#020617',
                'btn_primary_bg' => '#2563eb',
                'btn_primary_text' => '#ffffff',
            ],
            'emerald' => [
                'primary_color' => '#059669',
                'secondary_color' => '#064e3b',
                'accent_color' => '#f59e0b',
                'bg_main' => '#ffffff',
                'bg_secondary' => '#f0fdf4',
                'header_bg' => '#ffffff',
                'footer_bg' => '#064e3b',
                'btn_primary_bg' => '#059669',
                'btn_primary_text' => '#ffffff',
            ],
            'slate' => [
                'primary_color' => '#0f172a',
                'secondary_color' => '#334155',
                'accent_color' => '#3b82f6',
                'bg_main' => '#ffffff',
                'bg_secondary' => '#f1f5f9',
                'header_bg' => '#ffffff',
                'footer_bg' => '#0f172a',
                'btn_primary_bg' => '#0f172a',
                'btn_primary_text' => '#ffffff',
            ],
            'amber' => [
                'primary_color' => '#d97706',
                'secondary_color' => '#451a03',
                'accent_color' => '#2563eb',
                'bg_main' => '#ffffff',
                'bg_secondary' => '#fffbeb',
                'header_bg' => '#ffffff',
                'footer_bg' => '#18181b',
                'btn_primary_bg' => '#d97706',
                'btn_primary_text' => '#ffffff',
            ],
        ];

        if (isset($presets[$preset])) {
            $this->settings = array_merge($this->settings, $presets[$preset]);
            $this->hasUnpublishedChanges = true;
            $this->saveDraft();
            $this->feedbackMessage = 'Applied preset: '.ucfirst($preset);
            $this->feedbackType = 'success';
        }
    }

    public function saveDraft(): void
    {
        if (! $this->academyId) {
            return;
        }

        $service = app(ThemeAppearanceService::class);
        $service->saveDraft($this->academyId, $this->settings);

        $this->hasUnpublishedChanges = true;
        $this->feedbackMessage = 'Theme draft saved successfully.';
        $this->feedbackType = 'success';
    }

    public function publishTheme(): void
    {
        if (! $this->academyId) {
            return;
        }

        $service = app(ThemeAppearanceService::class);
        $service->publishTheme($this->academyId, $this->settings);

        $this->hasUnpublishedChanges = false;
        $this->feedbackMessage = 'Theme successfully published to live website!';
        $this->feedbackType = 'success';
    }

    public function resetToDefaults(): void
    {
        if (! $this->academyId) {
            return;
        }

        $service = app(ThemeAppearanceService::class);
        $service->resetToDefaults($this->academyId);
        $this->settings = CmsWebsiteTheme::getDefaultSettings();

        $this->hasUnpublishedChanges = false;
        $this->feedbackMessage = 'Theme reset to default SEO Academy styling.';
        $this->feedbackType = 'success';
    }

    public function render(): View
    {
        $previewCss = CmsWebsiteTheme::compileCss($this->settings);

        return view('livewire.admin.website.website-appearance-customizer', [
            'previewCss' => $previewCss,
        ]);
    }
}
