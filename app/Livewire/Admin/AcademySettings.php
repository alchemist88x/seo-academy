<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Services\AcademySettingsService;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Academy Settings')]
class AcademySettings extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $activeTab = 'profile';

    public string $academyId = '';

    // Profile & Contact
    public string $name = '';

    public string $slug = '';

    public ?string $description = '';

    public ?string $support_email = '';

    public ?string $contact_phone = '';

    public ?string $website_url = '';

    public ?string $address = '';

    public string $timezone = 'UTC';

    public string $locale = 'en';

    // Branding & Logo
    public string $tagline = 'Digital Marketing';

    public string $primary_color = '#2563eb';

    public string $secondary_color = '#0f172a';

    public $logo = null;

    public $favicon = null;

    public ?string $currentLogoUrl = null;

    public ?string $currentFaviconUrl = null;

    // Domain Configuration
    public ?string $subdomain = '';

    public ?string $custom_domain = '';

    // General Settings
    public bool $allow_self_registration = false;

    public int $default_enrollment_expiry_days = 365;

    public string $certificate_prefix = 'CERT';

    public string $email_sender_name = '';

    // Currency & Regional Pricing
    public string $default_currency = 'INR';

    public bool $enable_regional_pricing = true;

    public bool $enable_currency_switcher = true;

    public array $enabled_currencies = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'AUD', 'CAD', 'SGD', 'SAR'];

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $tenantContext->getAcademy();

        if ($academy === null) {
            abort(404, 'No active academy selected.');
        }

        $this->authorize('update', $academy);

        $this->academyId = (string) $academy->id;
        $this->fillFromAcademy($academy);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['profile', 'branding', 'domain', 'currency', 'settings'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function saveProfile(TenantContext $tenantContext, AcademySettingsService $settingsService): void
    {
        $academy = $this->resolveAuthorizedAcademy($tenantContext);

        $updated = $settingsService->updateProfile($academy, [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'support_email' => $this->support_email ?: null,
            'contact_phone' => $this->contact_phone ?: null,
            'website_url' => $this->website_url ?: null,
            'address' => $this->address ?: null,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
        ], Auth::user());

        $this->fillFromAcademy($updated);

        $this->dispatch('toast', type: 'success', message: 'Academy profile & contact updated.');
    }

    public function saveBranding(TenantContext $tenantContext, AcademySettingsService $settingsService): void
    {
        $academy = $this->resolveAuthorizedAcademy($tenantContext);

        $updated = $settingsService->updateBranding(
            $academy,
            [
                'name' => $this->name,
                'tagline' => $this->tagline,
                'primary_color' => $this->primary_color,
                'secondary_color' => $this->secondary_color,
            ],
            $this->logo,
            $this->favicon,
            Auth::user(),
        );

        $this->reset(['logo', 'favicon']);
        $this->fillFromAcademy($updated);

        $this->dispatch('toast', type: 'success', message: 'Academy branding & logo updated.');
    }

    public function saveDomain(TenantContext $tenantContext, AcademySettingsService $settingsService): void
    {
        $academy = $this->resolveAuthorizedAcademy($tenantContext);

        $updated = $settingsService->updateDomain($academy, [
            'subdomain' => $this->subdomain ?: null,
            'custom_domain' => $this->custom_domain ?: null,
        ], Auth::user());

        $this->fillFromAcademy($updated);

        $this->dispatch('toast', type: 'success', message: 'Domain configuration updated.');
    }

    public function saveSettings(TenantContext $tenantContext, AcademySettingsService $settingsService): void
    {
        $academy = $this->resolveAuthorizedAcademy($tenantContext);

        $validated = Validator::make([
            'allow_self_registration' => $this->allow_self_registration,
            'default_enrollment_expiry_days' => $this->default_enrollment_expiry_days,
            'certificate_prefix' => $this->certificate_prefix,
            'email_sender_name' => $this->email_sender_name,
        ], [
            'allow_self_registration' => ['boolean'],
            'default_enrollment_expiry_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'certificate_prefix' => ['required', 'string', 'max:20', 'alpha_dash'],
            'email_sender_name' => ['required', 'string', 'max:100'],
        ])->validate();

        $updated = $settingsService->updateSettings($academy, [
            'allow_self_registration' => [
                'group' => 'general',
                'key' => 'allow_self_registration',
                'value' => (bool) $validated['allow_self_registration'],
                'type' => 'boolean',
                'is_public' => true,
            ],
            'default_enrollment_expiry_days' => [
                'group' => 'general',
                'key' => 'default_enrollment_expiry_days',
                'value' => (int) $validated['default_enrollment_expiry_days'],
                'type' => 'integer',
                'is_public' => false,
            ],
            'certificate_prefix' => [
                'group' => 'general',
                'key' => 'certificate_prefix',
                'value' => (string) $validated['certificate_prefix'],
                'type' => 'string',
                'is_public' => false,
            ],
            'email_sender_name' => [
                'group' => 'general',
                'key' => 'email_sender_name',
                'value' => (string) $validated['email_sender_name'],
                'type' => 'string',
                'is_public' => false,
            ],
        ], Auth::user());

        $this->fillFromAcademy($updated);

        $this->dispatch('toast', type: 'success', message: 'General academy settings saved.');
    }

    public function saveCurrencySettings(TenantContext $tenantContext, AcademySettingsService $settingsService): void
    {
        $academy = $this->resolveAuthorizedAcademy($tenantContext);

        $supported = array_keys(CurrencyAndPricingService::SUPPORTED_CURRENCIES);

        $validated = Validator::make([
            'default_currency' => $this->default_currency,
            'enable_regional_pricing' => $this->enable_regional_pricing,
            'enable_currency_switcher' => $this->enable_currency_switcher,
            'enabled_currencies' => $this->enabled_currencies,
        ], [
            'default_currency' => ['required', 'string', Rule::in($supported)],
            'enable_regional_pricing' => ['boolean'],
            'enable_currency_switcher' => ['boolean'],
            'enabled_currencies' => ['required', 'array', 'min:1'],
            'enabled_currencies.*' => ['string', Rule::in($supported)],
        ])->validate();

        if (! in_array($validated['default_currency'], $validated['enabled_currencies'], true)) {
            $validated['enabled_currencies'][] = $validated['default_currency'];
        }

        $updated = $settingsService->updateSettings($academy, [
            'default_currency' => [
                'group' => 'currency',
                'key' => 'default_currency',
                'value' => (string) $validated['default_currency'],
                'type' => 'string',
                'is_public' => true,
            ],
            'enable_regional_pricing' => [
                'group' => 'currency',
                'key' => 'enable_regional_pricing',
                'value' => (bool) $validated['enable_regional_pricing'],
                'type' => 'boolean',
                'is_public' => true,
            ],
            'enable_currency_switcher' => [
                'group' => 'currency',
                'key' => 'enable_currency_switcher',
                'value' => (bool) $validated['enable_currency_switcher'],
                'type' => 'boolean',
                'is_public' => true,
            ],
            'enabled_currencies' => [
                'group' => 'currency',
                'key' => 'enabled_currencies',
                'value' => (array) $validated['enabled_currencies'],
                'type' => 'json',
                'is_public' => true,
            ],
        ], Auth::user());

        $this->fillFromAcademy($updated);

        $this->dispatch('toast', type: 'success', message: 'Currency and regional pricing settings saved successfully.');
    }

    public function render()
    {
        return view('livewire.admin.academy-settings');
    }

    protected function resolveAuthorizedAcademy(TenantContext $tenantContext): Academy
    {
        $academy = $tenantContext->getAcademy();

        if ($academy === null || (string) $academy->id !== $this->academyId) {
            abort(403, 'Invalid academy context.');
        }

        $this->authorize('update', $academy);

        return $academy;
    }

    protected function fillFromAcademy(Academy $academy): void
    {
        $this->name = (string) $academy->name;
        $this->tagline = (string) $academy->setting('tagline', 'Digital Marketing');
        $this->slug = (string) $academy->slug;
        $this->description = $academy->description ?? '';
        $this->support_email = $academy->support_email ?? '';
        $this->contact_phone = $academy->contact_phone ?? '';
        $this->website_url = $academy->website_url ?? '';
        $this->address = $academy->address ?? '';
        $this->timezone = (string) ($academy->timezone ?: 'UTC');
        $this->locale = (string) ($academy->locale ?: 'en');

        $this->primary_color = (string) ($academy->primary_color ?: '#2563eb');
        $this->secondary_color = (string) ($academy->secondary_color ?: '#0f172a');
        $this->currentLogoUrl = $academy->logoUrl();
        $this->currentFaviconUrl = $academy->faviconUrl();

        $this->subdomain = $academy->subdomain ?? '';
        $this->custom_domain = $academy->custom_domain ?? '';

        $this->allow_self_registration = (bool) $academy->setting('allow_self_registration', false);
        $this->default_enrollment_expiry_days = (int) $academy->setting('default_enrollment_expiry_days', 365);
        $this->certificate_prefix = (string) $academy->setting('certificate_prefix', 'CERT');
        $this->email_sender_name = (string) $academy->setting('email_sender_name', $academy->name);

        $this->default_currency = (string) $academy->setting('default_currency', 'INR');
        $this->enable_regional_pricing = (bool) $academy->setting('enable_regional_pricing', true);
        $this->enable_currency_switcher = (bool) $academy->setting('enable_currency_switcher', true);
        $rawCurrencies = $academy->setting('enabled_currencies', [
            'INR', 'USD', 'EUR', 'GBP', 'AED', 'AUD', 'CAD', 'SGD', 'SAR',
        ]);
        $this->enabled_currencies = is_array($rawCurrencies) ? $rawCurrencies : (json_decode((string) $rawCurrencies, true) ?? ['INR']);
    }
}
