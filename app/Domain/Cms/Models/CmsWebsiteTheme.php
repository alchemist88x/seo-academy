<?php

namespace App\Domain\Cms\Models;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsWebsiteTheme extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cms_website_themes';

    protected $fillable = [
        'academy_id',
        'name',
        'is_published',
        'is_draft',
        'settings',
        'css_cache',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_draft' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('is_draft', true);
    }

    /**
     * Get validated, safe default theme settings.
     *
     * @return array<string, mixed>
     */
    public static function getDefaultSettings(): array
    {
        return [
            // Theme Mode
            'theme_mode' => 'light', // light, dark, system
            'allow_dark_mode' => true,

            // Global Colours
            'primary_color' => '#2563eb',
            'secondary_color' => '#1e293b',
            'accent_color' => '#0d9488',
            'bg_main' => '#ffffff',
            'bg_secondary' => '#f8fafc',
            'bg_card' => '#ffffff',
            'text_heading' => '#0f172a',
            'text_body' => '#334155',
            'text_muted' => '#64748b',
            'border_color' => '#e2e8f0',
            'link_color' => '#2563eb',
            'link_hover_color' => '#1d4ed8',

            // Header Component
            'header_bg' => '#ffffff',
            'header_logo_height' => 40,
            'header_nav_color' => '#475569',
            'header_nav_hover_color' => '#2563eb',
            'header_nav_active_bg' => '#eff6ff',
            'header_height' => 80,
            'header_sticky' => true,

            // Footer Component
            'footer_bg' => '#020617',
            'footer_text_color' => '#94a3b8',
            'footer_link_color' => '#cbd5e1',
            'footer_columns_count' => 4,
            'footer_show_curriculum' => true,
            'footer_show_services' => true,
            'footer_show_faculty' => true,

            // Buttons
            'btn_primary_bg' => '#2563eb',
            'btn_primary_text' => '#ffffff',
            'btn_secondary_bg' => '#ffffff',
            'btn_secondary_text' => '#334155',
            'btn_secondary_border' => '#cbd5e1',
            'btn_border_radius' => '12px', // 0px, 4px, 8px, 12px, 16px, 9999px
            'btn_hover_brightness' => '0.9',

            // Cards
            'card_bg' => '#ffffff',
            'card_border' => '#e2e8f0',
            'card_border_radius' => '16px', // 8px, 12px, 16px, 24px
            'card_shadow' => 'sm', // none, sm, md, lg, xl
            'card_hover_effect' => 'lift', // none, lift, highlight

            // Typography
            'font_family_body' => 'Plus Jakarta Sans, sans-serif',
            'font_family_heading' => 'Plus Jakarta Sans, sans-serif',
            'font_size_base' => '16px',
            'heading_scale' => 'prominent', // compact, normal, prominent
            'font_weight_headings' => '800',
            'line_height_body' => '1.6',
        ];
    }

    /**
     * Generate sanitized, CSS injection-proof custom properties string.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function compileCss(array $settings): string
    {
        $defaults = self::getDefaultSettings();
        $merged = array_merge($defaults, $settings);

        // Sanitize colors: ensure strictly valid HEX
        $sanitizeHex = function (?string $val, string $fallback): string {
            if ($val && preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($val))) {
                return trim($val);
            }

            return $fallback;
        };

        // Sanitize dimensions: numeric with optional unit
        $sanitizePx = function ($val, string $fallback): string {
            $num = (int) preg_replace('/[^0-9]/', '', (string) $val);
            if ($num > 0 && $num < 500) {
                return "{$num}px";
            }

            return $fallback;
        };

        // Sanitize font family safely against strict whitelist
        $safeFonts = [
            'Plus Jakarta Sans, sans-serif',
            'Inter, sans-serif',
            'Roboto, sans-serif',
            'Open Sans, sans-serif',
            'Outfit, sans-serif',
            'Montserrat, sans-serif',
            'Merriweather, serif',
            'system-ui, -apple-system, sans-serif',
        ];

        $bodyFont = in_array($merged['font_family_body'], $safeFonts, true) ? $merged['font_family_body'] : $defaults['font_family_body'];
        $headingFont = in_array($merged['font_family_heading'], $safeFonts, true) ? $merged['font_family_heading'] : $defaults['font_family_heading'];

        $primaryColor = $sanitizeHex($merged['primary_color'], '#2563eb');
        $secondaryColor = $sanitizeHex($merged['secondary_color'], '#1e293b');
        $accentColor = $sanitizeHex($merged['accent_color'], '#0d9488');
        $bgMain = $sanitizeHex($merged['bg_main'], '#ffffff');
        $bgSecondary = $sanitizeHex($merged['bg_secondary'], '#f8fafc');
        $bgCard = $sanitizeHex($merged['bg_card'], '#ffffff');
        $textHeading = $sanitizeHex($merged['text_heading'], '#0f172a');
        $textBody = $sanitizeHex($merged['text_body'], '#334155');
        $textMuted = $sanitizeHex($merged['text_muted'], '#64748b');
        $borderColor = $sanitizeHex($merged['border_color'], '#e2e8f0');
        $linkColor = $sanitizeHex($merged['link_color'], '#2563eb');
        $linkHoverColor = $sanitizeHex($merged['link_hover_color'], '#1d4ed8');

        $headerBg = $sanitizeHex($merged['header_bg'], '#ffffff');
        $headerNavColor = $sanitizeHex($merged['header_nav_color'], '#475569');
        $headerNavHoverColor = $sanitizeHex($merged['header_nav_hover_color'], '#2563eb');
        $headerNavActiveBg = $sanitizeHex($merged['header_nav_active_bg'], '#eff6ff');
        $headerHeight = $sanitizePx($merged['header_height'], '80px');
        $headerLogoHeight = $sanitizePx($merged['header_logo_height'], '40px');

        $footerBg = $sanitizeHex($merged['footer_bg'], '#020617');
        $footerTextColor = $sanitizeHex($merged['footer_text_color'], '#94a3b8');
        $footerLinkColor = $sanitizeHex($merged['footer_link_color'], '#cbd5e1');

        $btnPrimaryBg = $sanitizeHex($merged['btn_primary_bg'], '#2563eb');
        $btnPrimaryText = $sanitizeHex($merged['btn_primary_text'], '#ffffff');
        $btnSecondaryBg = $sanitizeHex($merged['btn_secondary_bg'], '#ffffff');
        $btnSecondaryText = $sanitizeHex($merged['btn_secondary_text'], '#334155');
        $btnSecondaryBorder = $sanitizeHex($merged['btn_secondary_border'], '#cbd5e1');
        $btnRadius = in_array($merged['btn_border_radius'], ['0px', '4px', '8px', '12px', '16px', '9999px'], true) ? $merged['btn_border_radius'] : '12px';

        $cardBg = $sanitizeHex($merged['card_bg'], '#ffffff');
        $cardBorder = $sanitizeHex($merged['card_border'], '#e2e8f0');
        $cardRadius = in_array($merged['card_border_radius'], ['0px', '8px', '12px', '16px', '24px'], true) ? $merged['card_border_radius'] : '16px';

        $fontSizeBase = in_array($merged['font_size_base'], ['14px', '15px', '16px', '18px'], true) ? $merged['font_size_base'] : '16px';
        $lineHeight = in_array((string) $merged['line_height_body'], ['1.4', '1.5', '1.6', '1.75'], true) ? $merged['line_height_body'] : '1.6';

        return ":root {
    --web-color-primary: {$primaryColor};
    --web-color-secondary: {$secondaryColor};
    --web-color-accent: {$accentColor};
    --web-bg-main: {$bgMain};
    --web-bg-secondary: {$bgSecondary};
    --web-bg-card: {$bgCard};
    --web-text-heading: {$textHeading};
    --web-text-body: {$textBody};
    --web-text-muted: {$textMuted};
    --web-border-color: {$borderColor};
    --web-link-color: {$linkColor};
    --web-link-hover: {$linkHoverColor};

    --web-header-bg: {$headerBg};
    --web-header-height: {$headerHeight};
    --web-header-logo-height: {$headerLogoHeight};
    --web-header-nav-color: {$headerNavColor};
    --web-header-nav-hover: {$headerNavHoverColor};
    --web-header-nav-active-bg: {$headerNavActiveBg};

    --web-footer-bg: {$footerBg};
    --web-footer-text: {$footerTextColor};
    --web-footer-link: {$footerLinkColor};

    --web-btn-primary-bg: {$btnPrimaryBg};
    --web-btn-primary-text: {$btnPrimaryText};
    --web-btn-secondary-bg: {$btnSecondaryBg};
    --web-btn-secondary-text: {$btnSecondaryText};
    --web-btn-secondary-border: {$btnSecondaryBorder};
    --web-btn-radius: {$btnRadius};

    --web-card-bg: {$cardBg};
    --web-card-border: {$cardBorder};
    --web-card-radius: {$cardRadius};

    --web-font-body: {$bodyFont};
    --web-font-heading: {$headingFont};
    --web-font-size-base: {$fontSizeBase};
    --web-line-height-body: {$lineHeight};
}";
    }
}
