<?php

namespace App\Domain\Cms\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class ContactValidationService
{
    /**
     * Validate an email address according to RFC, valid public suffix / TLD, domain normalization, and max length.
     *
     * @return array{valid: bool, normalized: ?string, error: ?string}
     */
    public function validateEmail(?string $email): array
    {
        if ($email === null || trim($email) === '') {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Email address is required.',
            ];
        }

        $raw = trim($email);

        // Reject any interior whitespace
        if (preg_match('/\s/', $raw)) {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Email address cannot contain spaces.',
            ];
        }

        // Maximum RFC length 254
        if (strlen($raw) > 254) {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Email address exceeds maximum allowed length of 254 characters.',
            ];
        }

        // Reject multiple @ characters or missing @
        $atCount = substr_count($raw, '@');
        if ($atCount !== 1) {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Email address must contain exactly one "@" symbol.',
            ];
        }

        [$localPart, $domain] = explode('@', $raw, 2);

        if ($localPart === '' || $domain === '') {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Email address must have a valid username and domain.',
            ];
        }

        // Standard filter_var check
        if (! filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Please enter a valid email address (e.g. name@example.com).',
            ];
        }

        // Domain verification: check for missing TLD (e.g. user@localhost, user@domain)
        if (! str_contains($domain, '.')) {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'Email domain must include a valid top-level domain (e.g. .com, .org, .edu).',
            ];
        }

        $domainParts = explode('.', strtolower($domain));
        $tld = end($domainParts);

        // TLD must be at least 2 alpha characters
        if (! preg_match('/^[a-z]{2,63}$/i', $tld)) {
            return [
                'valid' => false,
                'normalized' => null,
                'error' => 'The domain extension (.'.htmlspecialchars($tld).') is invalid.',
            ];
        }

        // Reject leading or trailing dots/hyphens in domain labels
        foreach ($domainParts as $part) {
            if ($part === '' || str_starts_with($part, '-') || str_ends_with($part, '-')) {
                return [
                    'valid' => false,
                    'normalized' => null,
                    'error' => 'Email domain has an invalid format.',
                ];
            }
        }

        $normalized = $localPart.'@'.strtolower($domain);

        return [
            'valid' => true,
            'normalized' => $normalized,
            'error' => null,
        ];
    }

    /**
     * Validate an international phone number using Google's libphonenumber metadata.
     *
     * @return array{
     *     valid: bool,
     *     e164: ?string,
     *     national: ?string,
     *     international: ?string,
     *     country_code: ?string,
     *     error: ?string
     * }
     */
    public function validatePhone(?string $phone, ?string $countryCode = 'US'): array
    {
        if ($phone === null || trim($phone) === '') {
            return [
                'valid' => false,
                'e164' => null,
                'national' => null,
                'international' => null,
                'country_code' => null,
                'error' => 'Phone number is required.',
            ];
        }

        $cleanedPhone = trim($phone);
        $defaultRegion = strtoupper(trim($countryCode ?? 'US'));
        if (strlen($defaultRegion) !== 2) {
            $defaultRegion = 'US';
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse($cleanedPhone, $defaultRegion);

            if (! $phoneUtil->isValidNumber($numberProto)) {
                $region = $phoneUtil->getRegionCodeForNumber($numberProto) ?: $defaultRegion;

                return [
                    'valid' => false,
                    'e164' => null,
                    'national' => null,
                    'international' => null,
                    'country_code' => $region,
                    'error' => "The phone number is not valid for {$region}.",
                ];
            }

            $e164 = $phoneUtil->format($numberProto, PhoneNumberFormat::E164);
            $national = $phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL);
            $international = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
            $detectedRegion = $phoneUtil->getRegionCodeForNumber($numberProto) ?: $defaultRegion;

            return [
                'valid' => true,
                'e164' => $e164,
                'national' => $national,
                'international' => $international,
                'country_code' => $detectedRegion,
                'error' => null,
            ];
        } catch (NumberParseException $e) {
            return [
                'valid' => false,
                'e164' => null,
                'national' => null,
                'international' => null,
                'country_code' => $defaultRegion,
                'error' => 'Unable to parse phone number: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get a comprehensive worldwide list of supported countries with flags, ISO codes, and calling codes.
     *
     * @return array<int, array{code: string, name: string, dial_code: string, flag: string}>
     */
    public function getSupportedCountries(): array
    {
        return [
            ['code' => 'IN', 'name' => 'India', 'dial_code' => '+91', 'flag' => '🇮🇳'],
            ['code' => 'AE', 'name' => 'United Arab Emirates', 'dial_code' => '+971', 'flag' => '🇦🇪'],
            ['code' => 'US', 'name' => 'United States', 'dial_code' => '+1', 'flag' => '🇺🇸'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44', 'flag' => '🇬🇧'],
            ['code' => 'CA', 'name' => 'Canada', 'dial_code' => '+1', 'flag' => '🇨🇦'],
            ['code' => 'AU', 'name' => 'Australia', 'dial_code' => '+61', 'flag' => '🇦🇺'],
            ['code' => 'DE', 'name' => 'Germany', 'dial_code' => '+49', 'flag' => '🇩🇪'],
            ['code' => 'SG', 'name' => 'Singapore', 'dial_code' => '+65', 'flag' => '🇸🇬'],
            ['code' => 'SA', 'name' => 'Saudi Arabia', 'dial_code' => '+966', 'flag' => '🇸🇦'],
            ['code' => 'QA', 'name' => 'Qatar', 'dial_code' => '+974', 'flag' => '🇶🇦'],
            ['code' => 'KW', 'name' => 'Kuwait', 'dial_code' => '+965', 'flag' => '🇰🇼'],
            ['code' => 'OM', 'name' => 'Oman', 'dial_code' => '+968', 'flag' => '🇴🇲'],
            ['code' => 'BH', 'name' => 'Bahrain', 'dial_code' => '+973', 'flag' => '🇧🇭'],
            ['code' => 'BR', 'name' => 'Brazil', 'dial_code' => '+55', 'flag' => '🇧🇷'],
            ['code' => 'JP', 'name' => 'Japan', 'dial_code' => '+81', 'flag' => '🇯🇵'],
            ['code' => 'FR', 'name' => 'France', 'dial_code' => '+33', 'flag' => '🇫🇷'],
            ['code' => 'NL', 'name' => 'Netherlands', 'dial_code' => '+31', 'flag' => '🇳🇱'],
            ['code' => 'ES', 'name' => 'Spain', 'dial_code' => '+34', 'flag' => '🇪🇸'],
            ['code' => 'IT', 'name' => 'Italy', 'dial_code' => '+39', 'flag' => '🇮🇹'],
            ['code' => 'CH', 'name' => 'Switzerland', 'dial_code' => '+41', 'flag' => '🇨🇭'],
            ['code' => 'SE', 'name' => 'Sweden', 'dial_code' => '+46', 'flag' => '🇸🇪'],
            ['code' => 'NO', 'name' => 'Norway', 'dial_code' => '+47', 'flag' => '🇳🇴'],
            ['code' => 'DK', 'name' => 'Denmark', 'dial_code' => '+45', 'flag' => '🇩🇰'],
            ['code' => 'IE', 'name' => 'Ireland', 'dial_code' => '+353', 'flag' => '🇮🇪'],
            ['code' => 'NZ', 'name' => 'New Zealand', 'dial_code' => '+64', 'flag' => '🇳🇿'],
            ['code' => 'ZA', 'name' => 'South Africa', 'dial_code' => '+27', 'flag' => '🇿🇦'],
            ['code' => 'NG', 'name' => 'Nigeria', 'dial_code' => '+234', 'flag' => '🇳🇬'],
            ['code' => 'KE', 'name' => 'Kenya', 'dial_code' => '+254', 'flag' => '🇰🇪'],
            ['code' => 'EG', 'name' => 'Egypt', 'dial_code' => '+20', 'flag' => '🇪🇬'],
            ['code' => 'PK', 'name' => 'Pakistan', 'dial_code' => '+92', 'flag' => '🇵🇰'],
            ['code' => 'BD', 'name' => 'Bangladesh', 'dial_code' => '+880', 'flag' => '🇧🇩'],
            ['code' => 'LK', 'name' => 'Sri Lanka', 'dial_code' => '+94', 'flag' => '🇱🇰'],
            ['code' => 'MY', 'name' => 'Malaysia', 'dial_code' => '+60', 'flag' => '🇲🇾'],
            ['code' => 'ID', 'name' => 'Indonesia', 'dial_code' => '+62', 'flag' => '🇮🇩'],
            ['code' => 'PH', 'name' => 'Philippines', 'dial_code' => '+63', 'flag' => '🇵🇭'],
            ['code' => 'TH', 'name' => 'Thailand', 'dial_code' => '+66', 'flag' => '🇹🇭'],
            ['code' => 'VN', 'name' => 'Vietnam', 'dial_code' => '+84', 'flag' => '🇻🇳'],
            ['code' => 'CN', 'name' => 'China', 'dial_code' => '+86', 'flag' => '🇨🇳'],
            ['code' => 'HK', 'name' => 'Hong Kong', 'dial_code' => '+852', 'flag' => '🇭🇰'],
            ['code' => 'KR', 'name' => 'South Korea', 'dial_code' => '+82', 'flag' => '🇰🇷'],
            ['code' => 'MX', 'name' => 'Mexico', 'dial_code' => '+52', 'flag' => '🇲🇽'],
            ['code' => 'AR', 'name' => 'Argentina', 'dial_code' => '+54', 'flag' => '🇦🇷'],
            ['code' => 'CL', 'name' => 'Chile', 'dial_code' => '+56', 'flag' => '🇨🇱'],
            ['code' => 'CO', 'name' => 'Colombia', 'dial_code' => '+57', 'flag' => '🇨🇴'],
            ['code' => 'TR', 'name' => 'Turkey', 'dial_code' => '+90', 'flag' => '🇹🇷'],
            ['code' => 'PL', 'name' => 'Poland', 'dial_code' => '+48', 'flag' => '🇵🇱'],
        ];
    }
}
