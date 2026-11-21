<?php

namespace App\Domain\Cms\Services;

use App\Domain\Cms\Models\CmsWebsiteTheme;
use Illuminate\Support\Facades\Cache;

class ThemeAppearanceService
{
    /**
     * Get the active published theme for an academy.
     */
    public function getPublishedTheme(?string $academyId): ?CmsWebsiteTheme
    {
        if (! $academyId) {
            return null;
        }

        return CmsWebsiteTheme::where('academy_id', $academyId)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Get the draft theme for an academy.
     */
    public function getDraftTheme(?string $academyId): ?CmsWebsiteTheme
    {
        if (! $academyId) {
            return null;
        }

        return CmsWebsiteTheme::where('academy_id', $academyId)
            ->where('is_draft', true)
            ->first();
    }

    /**
     * Get or create a draft theme for customizer workspace.
     */
    public function getOrCreateDraft(string $academyId): CmsWebsiteTheme
    {
        $draft = $this->getDraftTheme($academyId);
        if ($draft) {
            return $draft;
        }

        $published = $this->getPublishedTheme($academyId);
        $settings = $published ? $published->settings : CmsWebsiteTheme::getDefaultSettings();
        $css = CmsWebsiteTheme::compileCss($settings);

        return CmsWebsiteTheme::create([
            'academy_id' => $academyId,
            'name' => 'Custom Theme (Draft)',
            'is_published' => false,
            'is_draft' => true,
            'settings' => $settings,
            'css_cache' => $css,
        ]);
    }

    /**
     * Save draft theme settings.
     */
    public function saveDraft(string $academyId, array $settings, ?string $name = null): CmsWebsiteTheme
    {
        $draft = $this->getOrCreateDraft($academyId);

        $merged = array_merge($draft->settings ?? CmsWebsiteTheme::getDefaultSettings(), $settings);
        $css = CmsWebsiteTheme::compileCss($merged);

        $draft->update([
            'name' => $name ?? $draft->name,
            'settings' => $merged,
            'css_cache' => $css,
        ]);

        return $draft;
    }

    /**
     * Publish current draft or specified settings to live website.
     */
    public function publishTheme(string $academyId, ?array $settings = null): CmsWebsiteTheme
    {
        $draft = $this->getDraftTheme($academyId);

        if ($settings !== null) {
            $effectiveSettings = array_merge(CmsWebsiteTheme::getDefaultSettings(), $settings);
        } elseif ($draft) {
            $effectiveSettings = $draft->settings;
        } else {
            $effectiveSettings = CmsWebsiteTheme::getDefaultSettings();
        }

        $css = CmsWebsiteTheme::compileCss($effectiveSettings);

        $published = CmsWebsiteTheme::updateOrCreate(
            [
                'academy_id' => $academyId,
                'is_published' => true,
            ],
            [
                'name' => 'Published Brand Theme',
                'is_draft' => false,
                'settings' => $effectiveSettings,
                'css_cache' => $css,
            ]
        );

        // Also keep draft in sync
        if ($draft) {
            $draft->update([
                'settings' => $effectiveSettings,
                'css_cache' => $css,
            ]);
        }

        Cache::forget("cms_theme_css_{$academyId}");

        return $published;
    }

    /**
     * Reset theme to default baseline tokens.
     */
    public function resetToDefaults(string $academyId): CmsWebsiteTheme
    {
        $defaultSettings = CmsWebsiteTheme::getDefaultSettings();
        $defaultCss = CmsWebsiteTheme::compileCss($defaultSettings);

        // Update draft
        CmsWebsiteTheme::updateOrCreate(
            [
                'academy_id' => $academyId,
                'is_draft' => true,
            ],
            [
                'name' => 'Default Theme (Draft)',
                'is_published' => false,
                'settings' => $defaultSettings,
                'css_cache' => $defaultCss,
            ]
        );

        // Update published
        $published = CmsWebsiteTheme::updateOrCreate(
            [
                'academy_id' => $academyId,
                'is_published' => true,
            ],
            [
                'name' => 'Default Brand Theme',
                'is_draft' => false,
                'settings' => $defaultSettings,
                'css_cache' => $defaultCss,
            ]
        );

        Cache::forget("cms_theme_css_{$academyId}");

        return $published;
    }

    /**
     * Get compiled CSS for injection into marketing layout.
     */
    public function getCompiledCss(?string $academyId): string
    {
        if (! $academyId) {
            return CmsWebsiteTheme::compileCss(CmsWebsiteTheme::getDefaultSettings());
        }

        return Cache::remember("cms_theme_css_{$academyId}", 3600, function () use ($academyId) {
            $published = $this->getPublishedTheme($academyId);
            if ($published && ! empty($published->css_cache)) {
                return $published->css_cache;
            }

            return CmsWebsiteTheme::compileCss($published ? $published->settings : CmsWebsiteTheme::getDefaultSettings());
        });
    }
}
