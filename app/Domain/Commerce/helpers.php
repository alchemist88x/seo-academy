<?php

use App\Domain\Commerce\Services\CurrencyAndPricingService;

if (! function_exists('format_currency')) {
    /**
     * Format a monetary amount according to ISO 4217 rules and academy configuration.
     */
    function format_currency(mixed $amount, ?string $currency = null, bool $includeCode = false): string
    {
        return app(CurrencyAndPricingService::class)->format($amount, $currency, $includeCode);
    }
}
