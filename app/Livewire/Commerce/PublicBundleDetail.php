<?php

namespace App\Livewire\Commerce;

use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.marketing')]
class PublicBundleDetail extends Component
{
    public string $slug;

    public string $activeCurrency = 'INR';

    public function mount(string $slug, CurrencyAndPricingService $pricingService): void
    {
        $this->slug = $slug;
        $this->activeCurrency = $pricingService->resolveCurrency();
    }

    public function setCurrency(string $currency, CurrencyAndPricingService $pricingService): void
    {
        if ($pricingService->setCurrency($currency)) {
            $this->activeCurrency = $currency;
        }
    }

    public function render(CurrencyAndPricingService $pricingService): View
    {
        $bundle = CourseBundle::with(['courses.category', 'installmentPlan'])
            ->where('slug', $this->slug)
            ->where('status', 'published')
            ->firstOrFail();

        $pricing = $pricingService->getItemPrice($bundle, $this->activeCurrency);

        $enabledCodes = $pricingService->getEnabledCurrencies();
        $supportedCurrencies = array_intersect_key(
            CurrencyAndPricingService::SUPPORTED_CURRENCIES,
            array_flip($enabledCodes)
        );

        return view('livewire.commerce.public-bundle-detail', [
            'bundle' => $bundle,
            'pricing' => $pricing,
            'supportedCurrencies' => $supportedCurrencies,
            'isCurrencySwitcherEnabled' => $pricingService->isCurrencySwitcherEnabled(),
        ])->title($bundle->title.' — SEO Academy');
    }
}
