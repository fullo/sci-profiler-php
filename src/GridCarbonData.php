<?php

declare(strict_types=1);

namespace SciProfiler;

/**
 * Static grid carbon intensity data by country and timezone.
 *
 * Values are in gCO2eq/kWh (lifecycle emissions) sourced from Ember Climate
 * and IPCC lifecycle emission factors. Update this table periodically from:
 *
 * - Ember Yearly Electricity Data: https://ember-energy.org/data/yearly-electricity-data/
 * - Ember Data Explorer: https://ember-energy.org/data/electricity-data-explorer/
 * - Low Carbon Power map: https://lowcarbonpower.org/map-gCO2eq-kWh
 *
 * Data vintage: 2024 (most countries), CC BY 4.0 license.
 *
 * @see https://ember-energy.org/
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class GridCarbonData
{
    /**
     * Carbon intensity by ISO 3166-1 alpha-2 country code (gCO2eq/kWh).
     *
     * @var array<string, int>
     */
    private const COUNTRY_INTENSITY = [
        // ── Europe — EU ──
        'AT' => 126,  // Austria
        'BE' => 185,  // Belgium
        'BG' => 255,  // Bulgaria
        'CZ' => 323,  // Czechia
        'DE' => 298,  // Germany
        'DK' => 120,  // Denmark
        'EE' => 353,  // Estonia
        'ES' => 126,  // Spain
        'FI' =>  87,  // Finland
        'FR' =>  33,  // France
        'GR' => 278,  // Greece
        'HR' => 243,  // Croatia
        'HU' => 230,  // Hungary
        'IE' => 301,  // Ireland
        'IT' => 324,  // Italy
        'LT' => 217,  // Lithuania
        'LU' => 326,  // Luxembourg
        'LV' => 215,  // Latvia
        'NL' => 281,  // Netherlands
        'PL' => 530,  // Poland
        'PT' => 160,  // Portugal
        'RO' => 242,  // Romania
        'SE' =>  32,  // Sweden
        'SI' => 165,  // Slovenia
        'SK' =>  91,  // Slovakia

        // ── Europe — non-EU ──
        'AL' =>  25,  // Albania
        'BA' => 507,  // Bosnia and Herzegovina
        'CH' =>  38,  // Switzerland
        'GB' => 229,  // United Kingdom
        'IS' =>  28,  // Iceland
        'ME' => 352,  // Montenegro
        'MK' => 502,  // North Macedonia
        'NO' =>  27,  // Norway
        'RS' => 556,  // Serbia
        'TR' => 392,  // Turkey
        'UA' => 230,  // Ukraine

        // ── Americas ──
        'AR' => 275,  // Argentina
        'BR' => 109,  // Brazil
        'CA' => 136,  // Canada
        'CL' => 228,  // Chile
        'CO' => 157,  // Colombia
        'MX' => 412,  // Mexico
        'US' => 348,  // United States

        // ── Asia ──
        'CN' => 478,  // China
        'HK' => 559,  // Hong Kong
        'ID' => 625,  // Indonesia
        'IN' => 595,  // India
        'JP' => 436,  // Japan
        'KR' => 396,  // South Korea
        'MY' => 538,  // Malaysia
        'PH' => 597,  // Philippines
        'SG' => 480,  // Singapore
        'TH' => 479,  // Thailand
        'TW' => 540,  // Taiwan
        'VN' => 447,  // Vietnam

        // ── Oceania ──
        'AU' => 466,  // Australia
        'NZ' => 109,  // New Zealand

        // ── Africa ──
        'EG' => 446,  // Egypt
        'KE' => 112,  // Kenya
        'NG' => 383,  // Nigeria
        'ZA' => 683,  // South Africa

        // ── Middle East ──
        'AE' => 359,  // United Arab Emirates
        'IL' => 502,  // Israel
        'SA' => 543,  // Saudi Arabia
    ];

    /**
     * Mapping from PHP timezone identifiers to ISO country codes.
     *
     * Covers the most common timezones used by developers.
     * For countries with sub-regions on different grids (US, India),
     * uses the most common/populous region as default.
     *
     * @var array<string, string>
     */
    private const TIMEZONE_TO_COUNTRY = [
        // ── Europe ──
        'Europe/Amsterdam'  => 'NL',
        'Europe/Athens'     => 'GR',
        'Europe/Belgrade'   => 'RS',
        'Europe/Berlin'     => 'DE',
        'Europe/Bratislava' => 'SK',
        'Europe/Brussels'   => 'BE',
        'Europe/Bucharest'  => 'RO',
        'Europe/Budapest'   => 'HU',
        'Europe/Copenhagen' => 'DK',
        'Europe/Dublin'     => 'IE',
        'Europe/Helsinki'   => 'FI',
        'Europe/Istanbul'   => 'TR',
        'Europe/Kiev'       => 'UA',
        'Europe/Kyiv'       => 'UA',
        'Europe/Lisbon'     => 'PT',
        'Europe/Ljubljana'  => 'SI',
        'Europe/London'     => 'GB',
        'Europe/Luxembourg' => 'LU',
        'Europe/Madrid'     => 'ES',
        'Europe/Oslo'       => 'NO',
        'Europe/Paris'      => 'FR',
        'Europe/Prague'     => 'CZ',
        'Europe/Riga'       => 'LV',
        'Europe/Rome'       => 'IT',
        'Europe/Sarajevo'   => 'BA',
        'Europe/Skopje'     => 'MK',
        'Europe/Sofia'      => 'BG',
        'Europe/Stockholm'  => 'SE',
        'Europe/Tallinn'    => 'EE',
        'Europe/Tirane'     => 'AL',
        'Europe/Vienna'     => 'AT',
        'Europe/Vilnius'    => 'LT',
        'Europe/Warsaw'     => 'PL',
        'Europe/Zagreb'     => 'HR',
        'Europe/Zurich'     => 'CH',
        'Atlantic/Reykjavik' => 'IS',

        // ── Americas ──
        'America/Argentina/Buenos_Aires' => 'AR',
        'America/Bogota'        => 'CO',
        'America/Chicago'       => 'US',
        'America/Denver'        => 'US',
        'America/Los_Angeles'   => 'US',
        'America/Mexico_City'   => 'MX',
        'America/New_York'      => 'US',
        'America/Phoenix'       => 'US',
        'America/Santiago'      => 'CL',
        'America/Sao_Paulo'     => 'BR',
        'America/Toronto'       => 'CA',
        'America/Vancouver'     => 'CA',
        'US/Eastern'            => 'US',
        'US/Central'            => 'US',
        'US/Mountain'           => 'US',
        'US/Pacific'            => 'US',

        // ── Asia ──
        'Asia/Calcutta'     => 'IN',
        'Asia/Hong_Kong'    => 'HK',
        'Asia/Jakarta'      => 'ID',
        'Asia/Kolkata'      => 'IN',
        'Asia/Kuala_Lumpur' => 'MY',
        'Asia/Manila'       => 'PH',
        'Asia/Seoul'        => 'KR',
        'Asia/Shanghai'     => 'CN',
        'Asia/Singapore'    => 'SG',
        'Asia/Taipei'       => 'TW',
        'Asia/Tel_Aviv'     => 'IL',
        'Asia/Tokyo'        => 'JP',
        'Asia/Bangkok'      => 'TH',
        'Asia/Dubai'        => 'AE',
        'Asia/Ho_Chi_Minh'  => 'VN',
        'Asia/Riyadh'       => 'SA',

        // ── Oceania ──
        'Australia/Melbourne' => 'AU',
        'Australia/Sydney'    => 'AU',
        'Pacific/Auckland'    => 'NZ',

        // ── Africa ──
        'Africa/Cairo'        => 'EG',
        'Africa/Johannesburg' => 'ZA',
        'Africa/Lagos'        => 'NG',
        'Africa/Nairobi'      => 'KE',
    ];

    /**
     * Get the carbon intensity for a country code.
     *
     * @return int|null gCO2eq/kWh, or null if unknown
     */
    public static function forCountry(string $countryCode): ?int
    {
        return self::COUNTRY_INTENSITY[strtoupper($countryCode)] ?? null;
    }

    /**
     * Get the carbon intensity for a PHP timezone identifier.
     *
     * Resolves the timezone to a country code, then looks up the intensity.
     *
     * @return int|null gCO2eq/kWh, or null if the timezone is unknown
     */
    public static function forTimezone(string $timezone): ?int
    {
        $country = self::TIMEZONE_TO_COUNTRY[$timezone] ?? null;
        if ($country === null) {
            return null;
        }

        // All TIMEZONE_TO_COUNTRY values exist in COUNTRY_INTENSITY,
        // but we guard defensively in case the maps drift during updates.
        return self::COUNTRY_INTENSITY[$country] ?? null;
    }

    /**
     * Get the country code for a PHP timezone identifier.
     */
    public static function countryForTimezone(string $timezone): ?string
    {
        return self::TIMEZONE_TO_COUNTRY[$timezone] ?? null;
    }

    /**
     * Detect the carbon intensity from the system's default timezone.
     *
     * Returns null if the timezone cannot be mapped to a known country.
     *
     * @return array{intensity: int, country: string, timezone: string}|null
     */
    public static function detectFromSystem(): ?array
    {
        $timezone = date_default_timezone_get();
        $country = self::TIMEZONE_TO_COUNTRY[$timezone] ?? null;

        if ($country === null) {
            return null;
        }

        $intensity = self::COUNTRY_INTENSITY[$country] ?? null;
        if ($intensity === null) {
            return null;
        }

        return [
            'intensity' => $intensity,
            'country' => $country,
            'timezone' => $timezone,
        ];
    }

    /**
     * Get all country data as an array.
     *
     * @return array<string, int>
     */
    public static function allCountries(): array
    {
        return self::COUNTRY_INTENSITY;
    }

    /**
     * Get the data source description for attribution.
     */
    public static function getSourceAttribution(): string
    {
        return 'Ember Climate — Yearly Electricity Data (2024), CC BY 4.0. '
            . 'https://ember-energy.org/data/yearly-electricity-data/';
    }
}
