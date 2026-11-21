<?php

namespace App\Livewire\Commerce;

use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.marketing')]
#[Title('Course Bundles — SEO Academy')]
class PublicBundlesIndex extends Component
{
    public string $activeCurrency = 'INR';

    public function mount(CurrencyAndPricingService $pricingService): void
    {
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
        $bundles = CourseBundle::with(['courses', 'installmentPlan'])
            ->where('status', 'published')
            ->latest()
            ->get();

        $bundlePricings = [];
        foreach ($bundles as $b) {
            $bundlePricings[$b->id] = $pricingService->getItemPrice($b, $this->activeCurrency);
        }

        $enabledCodes = $pricingService->getEnabledCurrencies();
        $supportedCurrencies = array_intersect_key(
            CurrencyAndPricingService::SUPPORTED_CURRENCIES,
            array_flip($enabledCodes)
        );

        return view('livewire.commerce.public-bundles-index', [
            'bundles' => $bundles,
            'bundlePricings' => $bundlePricings,
            'supportedCurrencies' => $supportedCurrencies,
            'isCurrencySwitcherEnabled' => $pricingService->isCurrencySwitcherEnabled(),
        ]);
    }
}
