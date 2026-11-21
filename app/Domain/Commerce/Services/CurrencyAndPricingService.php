<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\Setting;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CurrencyAndPricingService
{
    public const SUPPORTED_CURRENCIES = [
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'rate_to_usd' => 0.012, 'decimals' => 2],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'rate_to_usd' => 1.0, 'decimals' => 2],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED ', 'rate_to_usd' => 0.27, 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 1.08, 'decimals' => 2],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'rate_to_usd' => 1.28, 'decimals' => 2],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'rate_to_usd' => 0.65, 'decimals' => 2],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'rate_to_usd' => 0.73, 'decimals' => 2],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'rate_to_usd' => 0.76, 'decimals' => 2],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR ', 'rate_to_usd' => 0.27, 'decimals' => 2],
    ];

    public const COUNTRY_CURRENCY_MAP = [
        'IN' => 'INR',
        'GB' => 'GBP',
        'AE' => 'AED',
        'AU' => 'AUD',
        'CA' => 'CAD',
        'SG' => 'SGD',
        'SA' => 'SAR',
        'DE' => 'EUR',
        'FR' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
        'NL' => 'EUR',
        'IE' => 'EUR',
        'BE' => 'EUR',
        'AT' => 'EUR',
        'PT' => 'EUR',
        'FI' => 'EUR',
        'GR' => 'EUR',
        'US' => 'USD',
    ];

    /**
     * Get the configured default currency of the current academy.
     * Defaults to 'INR' for fresh installations.
     */
    public function getAcademyCurrency(?Academy $academy = null): string
    {
        $resolvedAcademy = $academy ?? $this->resolveAcademy();

        if ($resolvedAcademy) {
            $currency = $resolvedAcademy->setting('default_currency');
            if (! empty($currency) && isset(self::SUPPORTED_CURRENCIES[strtoupper($currency)])) {
                return strtoupper($currency);
            }
        }

        try {
            $setting = Setting::where('group', 'currency')->where('key', 'default_currency')->value('value');
            if (! empty($setting) && isset(self::SUPPORTED_CURRENCIES[strtoupper($setting)])) {
                return strtoupper($setting);
            }
        } catch (\Throwable) {
            // DB table may not exist yet in early boot
        }

        return 'INR';
    }

    /**
     * Check if regional GeoIP currency detection is enabled.
     */
    public function isRegionalDetectionEnabled(?Academy $academy = null): bool
    {
        $resolvedAcademy = $academy ?? $this->resolveAcademy();

        if ($resolvedAcademy) {
            return (bool) $resolvedAcademy->setting('enable_regional_pricing', true);
        }

        try {
            $val = Setting::where('group', 'currency')->where('key', 'enable_regional_pricing')->value('value');
            if ($val !== null) {
                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (\Throwable) {
        }

        return true;
    }

    /**
     * Check if visitor manual currency switcher is enabled.
     */
    public function isCurrencySwitcherEnabled(?Academy $academy = null): bool
    {
        $resolvedAcademy = $academy ?? $this->resolveAcademy();

        if ($resolvedAcademy) {
            return (bool) $resolvedAcademy->setting('enable_currency_switcher', true);
        }

        try {
            $val = Setting::where('group', 'currency')->where('key', 'enable_currency_switcher')->value('value');
            if ($val !== null) {
                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (\Throwable) {
        }

        return true;
    }

    /**
     * Get the list of currencies enabled for the current academy.
     */
    public function getEnabledCurrencies(?Academy $academy = null): array
    {
        $resolvedAcademy = $academy ?? $this->resolveAcademy();

        if ($resolvedAcademy) {
            $currencies = $resolvedAcademy->setting('enabled_currencies');
            if (is_array($currencies) && ! empty($currencies)) {
                $filtered = array_values(array_filter($currencies, fn ($c) => isset(self::SUPPORTED_CURRENCIES[strtoupper($c)])));
                if (! empty($filtered)) {
                    return $filtered;
                }
            }
        }

        try {
            $val = Setting::where('group', 'currency')->where('key', 'enabled_currencies')->value('value');
            if ($val !== null) {
                $decoded = is_string($val) ? json_decode($val, true) : $val;
                if (is_array($decoded) && ! empty($decoded)) {
                    $filtered = array_values(array_filter($decoded, fn ($c) => isset(self::SUPPORTED_CURRENCIES[strtoupper($c)])));
                    if (! empty($filtered)) {
                        return $filtered;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return array_keys(self::SUPPORTED_CURRENCIES);
    }

    /**
     * Resolve the active currency for current request / visitor.
     * Order of precedence:
     * 1. Explicit visitor selection (if currency switcher enabled)
     * 2. Geolocation/IP detected currency (if regional detection enabled)
     * 3. Configured Academy Default Currency
     * 4. Hard fallback: 'INR'
     */
    public function resolveCurrency(?Request $request = null): string
    {
        $req = $request ?? (request()->hasSession() || app()->bound('request') ? request() : null);
        $academyCurrency = $this->getAcademyCurrency();
        $enabledCurrencies = $this->getEnabledCurrencies();
        $switcherEnabled = $this->isCurrencySwitcherEnabled();
        $regionalEnabled = $this->isRegionalDetectionEnabled();

        // 1. Explicit visitor selection (Session or Query parameter)
        if ($switcherEnabled) {
            if ($req && $req->has('currency')) {
                $candidate = strtoupper((string) $req->input('currency'));
                if (in_array($candidate, $enabledCurrencies, true)) {
                    if ($req->hasSession()) {
                        Session::put('commerce_currency', $candidate);
                    }

                    return $candidate;
                }
            }

            try {
                if (Session::isStarted() && Session::has('commerce_currency')) {
                    $candidate = strtoupper((string) Session::get('commerce_currency'));
                    if (in_array($candidate, $enabledCurrencies, true)) {
                        return $candidate;
                    }
                }
            } catch (\Throwable) {
                // Session not ready or CLI
            }
        }

        // 2. Geolocation / IP detected currency
        if ($regionalEnabled && $req) {
            $country = $req->header('CF-IPCountry') ?: $req->header('X-Country-Code');
            if ($country && isset(self::COUNTRY_CURRENCY_MAP[strtoupper($country)])) {
                $geoCurrency = self::COUNTRY_CURRENCY_MAP[strtoupper($country)];
                if (in_array($geoCurrency, $enabledCurrencies, true)) {
                    return $geoCurrency;
                }
            }
        }

        // 3. Configured Academy Default Currency
        if (in_array($academyCurrency, $enabledCurrencies, true)) {
            return $academyCurrency;
        }

        // 4. Hard fallback: INR
        return 'INR';
    }

    /**
     * Set visitor selected currency in session.
     */
    public function setCurrency(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        $enabledCurrencies = $this->getEnabledCurrencies();

        if (in_array($currency, $enabledCurrencies, true)) {
            try {
                Session::put('commerce_currency', $currency);

                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Format a monetary amount with currency symbol, precision, and optional ISO code.
     */
    public function format(float|int|string|null $amount, ?string $currency = null, bool $includeCode = false): string
    {
        $num = (float) ($amount ?? 0.0);
        $curr = strtoupper(trim((string) ($currency ?: $this->getAcademyCurrency())));

        $meta = self::SUPPORTED_CURRENCIES[$curr] ?? [
            'name' => $curr,
            'symbol' => $curr.' ',
            'rate_to_usd' => 1.0,
            'decimals' => 2,
        ];

        $symbol = $meta['symbol'] ?? ($curr.' ');
        $decimals = $meta['decimals'] ?? 2;
        $formattedNum = number_format($num, $decimals);

        $result = $symbol.$formattedNum;

        if ($includeCode) {
            $result .= ' '.$curr;
        }

        return $result;
    }

    /**
     * Calculate effective price for a Course or CourseBundle in specified currency.
     */
    public function getItemPrice(Course|CourseBundle $item, ?string $currency = null): array
    {
        $currency = strtoupper(trim((string) ($currency ?: $this->resolveCurrency())));
        $academyCurrency = $this->getAcademyCurrency();
        $baseCurrency = strtoupper(trim((string) ($item->currency ?: $academyCurrency)));
        $basePrice = (float) $item->price;

        $regionalPrices = is_array($item->regional_prices) ? $item->regional_prices : [];
        $hasRegionalPrice = isset($regionalPrices[$currency]) && is_numeric($regionalPrices[$currency]);

        if ($hasRegionalPrice) {
            $effectiveOriginal = (float) $regionalPrices[$currency];
        } else {
            if ($currency === $baseCurrency) {
                $effectiveOriginal = $basePrice;
            } else {
                // Approximate conversion using exchange rates relative to USD
                $baseRate = self::SUPPORTED_CURRENCIES[$baseCurrency]['rate_to_usd'] ?? 1.0;
                $targetRate = self::SUPPORTED_CURRENCIES[$currency]['rate_to_usd'] ?? 1.0;
                $usdEquivalent = $basePrice * $baseRate;
                $effectiveOriginal = round($usdEquivalent / $targetRate, 2);
            }
        }

        // Apply discount if active
        $now = now();
        $discountAmount = 0.00;
        $isDiscountActive = false;

        if ($item->discount_percentage !== null && (float) $item->discount_percentage > 0) {
            if (($item->discount_starts_at === null || $item->discount_starts_at <= $now) &&
                ($item->discount_expires_at === null || $item->discount_expires_at >= $now)) {
                $discountAmount = round(($effectiveOriginal * (float) $item->discount_percentage) / 100, 2);
                $isDiscountActive = true;
            }
        } elseif ($item->discount_price !== null && (float) $item->discount_price > 0 && $currency === $baseCurrency) {
            if (($item->discount_starts_at === null || $item->discount_starts_at <= $now) &&
                ($item->discount_expires_at === null || $item->discount_expires_at >= $now)) {
                $discountAmount = max(0.00, round($effectiveOriginal - (float) $item->discount_price, 2));
                $isDiscountActive = true;
            }
        }

        $finalPrice = max(0.00, round($effectiveOriginal - $discountAmount, 2));

        return [
            'currency' => $currency,
            'base_currency' => $baseCurrency,
            'original_price' => $effectiveOriginal,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'is_discount_active' => $isDiscountActive,
            'is_free' => ($item instanceof Course && $item->is_free) || $finalPrice <= 0.00,
        ];
    }

    /**
     * Create immutable snapshot of pricing for an order.
     */
    public function createSnapshot(Course|CourseBundle $item, ?string $currency = null, array $additionalData = []): array
    {
        $currency = strtoupper(trim((string) ($currency ?: $this->resolveCurrency())));
        $pricing = $this->getItemPrice($item, $currency);

        return array_merge([
            'item_id' => $item->id,
            'item_type' => $item instanceof Course ? 'course' : 'bundle',
            'item_title' => $item->title,
            'currency' => $currency,
            'original_price' => $pricing['original_price'],
            'discount_amount' => $pricing['discount_amount'],
            'final_price' => $pricing['final_price'],
            'timestamp' => now()->toISOString(),
        ], $additionalData);
    }

    /**
     * Helper to resolve active academy from TenantContext or fallback.
     */
    protected function resolveAcademy(): ?Academy
    {
        try {
            if (app()->bound(TenantContext::class)) {
                $academy = app(TenantContext::class)->getAcademy();
                if ($academy) {
                    return $academy;
                }
            }

            return Academy::getDefaultAcademy();
        } catch (\Throwable) {
            return null;
        }
    }
}
