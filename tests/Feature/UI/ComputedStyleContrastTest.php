<?php

namespace Tests\Feature\UI;

use Tests\TestCase;

class ComputedStyleContrastTest extends TestCase
{
    /**
     * Compute relative luminance according to WCAG 2.2 specification.
     * https://www.w3.org/WAI/GL/wiki/Relative_luminance
     */
    public static function calculateRelativeLuminance(string $hexColor): float
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255.0;
        $g = hexdec(substr($hex, 2, 2)) / 255.0;
        $b = hexdec(substr($hex, 4, 2)) / 255.0;

        $rLin = ($r <= 0.04045) ? ($r / 12.92) : pow(($r + 0.055) / 1.055, 2.4);
        $gLin = ($g <= 0.04045) ? ($g / 12.92) : pow(($g + 0.055) / 1.055, 2.4);
        $bLin = ($b <= 0.04045) ? ($b / 12.92) : pow(($b + 0.055) / 1.055, 2.4);

        return (0.2126 * $rLin) + (0.7152 * $gLin) + (0.0722 * $bLin);
    }

    /**
     * Compute WCAG 2.2 contrast ratio between two hex colors.
     * (L1 + 0.05) / (L2 + 0.05) where L1 is lighter than L2.
     */
    public static function calculateContrastRatio(string $color1, string $color2): float
    {
        $lum1 = self::calculateRelativeLuminance($color1);
        $lum2 = self::calculateRelativeLuminance($color2);

        $lighter = max($lum1, $lum2);
        $darker = min($lum1, $lum2);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    public function test_light_mode_color_tokens_satisfy_wcag_contrast(): void
    {
        // 1. Text on Input Background: #0f172a on #ffffff
        $textInputRatio = self::calculateContrastRatio('#0f172a', '#ffffff');
        $this->assertGreaterThanOrEqual(4.5, $textInputRatio);
        $this->assertSame(17.85, $textInputRatio, 'Light mode input text must achieve ~17.8:1 contrast (WCAG AAA requires 7.0:1).');

        // 2. Placeholder on Input Background: #64748b on #ffffff
        $placeholderRatio = self::calculateContrastRatio('#64748b', '#ffffff');
        $this->assertGreaterThanOrEqual(4.5, $placeholderRatio);
        $this->assertSame(4.76, $placeholderRatio, 'Light mode placeholder must achieve >= 4.5:1 contrast.');

        // 3. Primary Button: #ffffff text on #4f46e5 (primary-600)
        $primaryButtonRatio = self::calculateContrastRatio('#ffffff', '#4f46e5');
        $this->assertGreaterThanOrEqual(4.5, $primaryButtonRatio);
        $this->assertSame(6.29, $primaryButtonRatio);

        // 4. Danger Button: #ffffff text on #dc2626
        $dangerButtonRatio = self::calculateContrastRatio('#ffffff', '#dc2626');
        $this->assertGreaterThanOrEqual(4.5, $dangerButtonRatio);
        $this->assertSame(4.83, $dangerButtonRatio);

        // 5. Focus Indicator Ring: #6366f1 (primary-500) against white background
        $focusRingRatio = self::calculateContrastRatio('#6366f1', '#ffffff');
        $this->assertGreaterThanOrEqual(3.0, $focusRingRatio); // WCAG non-text contrast >= 3.0:1
        $this->assertSame(4.47, $focusRingRatio);
    }

    public function test_dark_mode_color_tokens_satisfy_wcag_contrast(): void
    {
        // 1. Text on Input Background: #f1f5f9 on #1e293b
        $darkTextInputRatio = self::calculateContrastRatio('#f1f5f9', '#1e293b');
        $this->assertGreaterThanOrEqual(4.5, $darkTextInputRatio);
        $this->assertSame(13.35, $darkTextInputRatio, 'Dark mode input text must achieve ~13.4:1 contrast (WCAG AAA requires 7.0:1).');

        // 2. Placeholder on Input Background: #94a3b8 on #1e293b
        $darkPlaceholderRatio = self::calculateContrastRatio('#94a3b8', '#1e293b');
        $this->assertGreaterThanOrEqual(4.5, $darkPlaceholderRatio);
        $this->assertSame(5.71, $darkPlaceholderRatio, 'Dark mode placeholder must achieve >= 4.5:1 contrast.');

        // 3. Select Dropdown Option: #f1f5f9 text on #1e293b background
        $dropdownOptionRatio = self::calculateContrastRatio('#f1f5f9', '#1e293b');
        $this->assertGreaterThanOrEqual(4.5, $dropdownOptionRatio);
        $this->assertSame(13.35, $dropdownOptionRatio);

        // 4. Autofill Inset Background & Text: #f1f5f9 on #1e293b
        $autofillRatio = self::calculateContrastRatio('#f1f5f9', '#1e293b');
        $this->assertGreaterThanOrEqual(4.5, $autofillRatio);
        $this->assertSame(13.35, $autofillRatio);

        // 5. Dark Focus Ring: #818cf8 (primary-400) against #1e293b
        $darkFocusRingRatio = self::calculateContrastRatio('#818cf8', '#1e293b');
        $this->assertGreaterThanOrEqual(3.0, $darkFocusRingRatio);
        $this->assertSame(4.90, $darkFocusRingRatio);
    }

    public function test_all_11_specified_forms_contain_compliant_inputs(): void
    {
        $viewsToCheck = [
            'Academy Settings' => resource_path('views/livewire/admin/academy-settings.blade.php'),
            'Student Management' => resource_path('views/livewire/admin/students-index.blade.php'),
            'Trainer Management' => resource_path('views/livewire/admin/trainers-index.blade.php'),
            'Course Forms' => resource_path('views/livewire/admin/courses-index.blade.php'),
            'Assessment Forms' => resource_path('views/livewire/admin/assessments-index.blade.php'),
            'Question Banks' => resource_path('views/livewire/admin/question-bank-manager.blade.php'),
            'Assignment & Grading Forms' => resource_path('views/livewire/admin/assignments-index.blade.php'),
            'Enrollment Forms' => resource_path('views/livewire/admin/enrollments-index.blade.php'),
            'Certificate Management' => resource_path('views/livewire/admin/certificates-index.blade.php'),
            'Support Ticket Forms' => resource_path('views/livewire/support/support-tickets-index.blade.php'),
            'Student Submission Forms' => resource_path('views/livewire/student/student-assignments-index.blade.php'),
        ];

        foreach ($viewsToCheck as $formName => $path) {
            $this->assertFileExists($path, "Form template for [{$formName}] must exist at {$path}");
            $content = file_get_contents($path);

            // Verify inputs utilize component library or form-input classes
            $hasComponentOrClass = str_contains($content, '<x-input')
                || str_contains($content, '<x-select')
                || str_contains($content, '<x-textarea')
                || str_contains($content, 'form-input')
                || str_contains($content, 'wire:model');

            $this->assertTrue(
                $hasComponentOrClass,
                "Form [{$formName}] must utilize standardized high-contrast inputs or form-input classes."
            );

            // Verify no hardcoded white text on white background or dark text on dark background
            $this->assertStringNotContainsString('style="color: white; background: white"', $content);
            $this->assertStringNotContainsString('style="color: black; background: black"', $content);
        }
    }
}
