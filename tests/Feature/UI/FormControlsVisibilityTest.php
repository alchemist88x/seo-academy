<?php

namespace Tests\Feature\UI;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FormControlsVisibilityTest extends TestCase
{
    public function test_input_component_renders_with_form_input_and_error_classes(): void
    {
        $rendered = Blade::render('<x-input name="email" label="Email Address" placeholder="you@example.com" hint="We will not share your email" />');

        $this->assertStringContainsString('form-input', $rendered);
        $this->assertStringContainsString('form-label', $rendered);
        $this->assertStringContainsString('Email Address', $rendered);
        $this->assertStringContainsString('you@example.com', $rendered);
        $this->assertStringContainsString('We will not share your email', $rendered);

        // Render with error
        $renderedError = Blade::render('<x-input name="email" error="Invalid email address" />');
        $this->assertStringContainsString('form-input error', $renderedError);
        $this->assertStringContainsString('form-error', $renderedError);
        $this->assertStringContainsString('Invalid email address', $renderedError);
    }

    public function test_select_component_renders_with_form_input_and_select_classes(): void
    {
        $rendered = Blade::render('<x-select name="role" label="Role" :options="[\'student\' => \'Student\', \'trainer\' => \'Trainer\']" placeholder="Select a role" />');

        $this->assertStringContainsString('form-input form-select', $rendered);
        $this->assertStringContainsString('Select a role', $rendered);
        $this->assertStringContainsString('Student', $rendered);
        $this->assertStringContainsString('Trainer', $rendered);
    }

    public function test_textarea_component_renders_with_form_textarea_classes(): void
    {
        $rendered = Blade::render('<x-textarea name="bio" label="Biography" rows="5">Hello world</x-textarea>');

        $this->assertStringContainsString('form-input form-textarea', $rendered);
        $this->assertStringContainsString('rows="5"', $rendered);
        $this->assertStringContainsString('Hello world', $rendered);
    }

    public function test_checkbox_component_renders_with_proper_contrast_classes(): void
    {
        $rendered = Blade::render('<x-checkbox name="terms" label="Accept terms and conditions" description="Required for registration" />');

        $this->assertStringContainsString('type="checkbox"', $rendered);
        $this->assertStringContainsString('text-text-primary', $rendered);
        $this->assertStringContainsString('Accept terms and conditions', $rendered);
        $this->assertStringContainsString('Required for registration', $rendered);
    }

    public function test_css_tokens_and_contrast_specifications(): void
    {
        $cssPath = resource_path('css/app.css');
        $this->assertFileExists($cssPath);
        $css = file_get_contents($cssPath);

        // Token declarations
        $this->assertStringContainsString('--color-input-bg:          #ffffff;', $css);
        $this->assertStringContainsString('--color-input-text:        #0f172a;', $css);
        $this->assertStringContainsString('--color-input-bg:          #1e293b;', $css);
        $this->assertStringContainsString('--color-input-text:        #f1f5f9;', $css);

        // Global form controls rules
        $this->assertStringContainsString('.form-input,', $css);
        $this->assertStringContainsString('background-color: var(--color-input-bg) !important;', $css);
        $this->assertStringContainsString('color: var(--color-input-text) !important;', $css);
        $this->assertStringContainsString('.dark input:not([type=\'checkbox\'])', $css);
        $this->assertStringContainsString('background-color: #1e293b !important;', $css);
        $this->assertStringContainsString('color: #f1f5f9 !important;', $css);

        // Autofill and browser overrides
        $this->assertStringContainsString('input:-webkit-autofill', $css);
        $this->assertStringContainsString('select option', $css);
        $this->assertStringContainsString('::file-selector-button', $css);
        $this->assertStringContainsString('::-webkit-calendar-picker-indicator', $css);
    }

    public function test_compiled_manifest_and_build_assets(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('resources/css/app.css', $manifest);

        $builtCssFile = public_path('build/'.$manifest['resources/css/app.css']['file']);
        $this->assertFileExists($builtCssFile);
    }
}
