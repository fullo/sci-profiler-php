<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\GridCarbonData;

final class GridCarbonDataTest extends TestCase
{
    public function testForCountryReturnsKnownValue(): void
    {
        $this->assertSame(298, GridCarbonData::forCountry('DE'));
        $this->assertSame(33, GridCarbonData::forCountry('FR'));
        $this->assertSame(348, GridCarbonData::forCountry('US'));
        $this->assertSame(683, GridCarbonData::forCountry('ZA'));
    }

    public function testForCountryIsCaseInsensitive(): void
    {
        $this->assertSame(298, GridCarbonData::forCountry('de'));
        $this->assertSame(298, GridCarbonData::forCountry('De'));
    }

    public function testForCountryReturnsNullForUnknown(): void
    {
        $this->assertNull(GridCarbonData::forCountry('XX'));
        $this->assertNull(GridCarbonData::forCountry(''));
    }

    public function testForTimezoneReturnsCorrectValue(): void
    {
        $this->assertSame(324, GridCarbonData::forTimezone('Europe/Rome'));
        $this->assertSame(298, GridCarbonData::forTimezone('Europe/Berlin'));
        $this->assertSame(348, GridCarbonData::forTimezone('America/New_York'));
        $this->assertSame(436, GridCarbonData::forTimezone('Asia/Tokyo'));
    }

    public function testForTimezoneReturnsNullForUnknown(): void
    {
        $this->assertNull(GridCarbonData::forTimezone('Nowhere/Fantasy'));
    }

    public function testCountryForTimezone(): void
    {
        $this->assertSame('IT', GridCarbonData::countryForTimezone('Europe/Rome'));
        $this->assertSame('US', GridCarbonData::countryForTimezone('America/New_York'));
        $this->assertNull(GridCarbonData::countryForTimezone('Nowhere/Fantasy'));
    }

    public function testDetectFromSystemReturnsArray(): void
    {
        $result = GridCarbonData::detectFromSystem();

        // Result depends on system timezone — just verify structure if not null
        if ($result !== null) {
            $this->assertArrayHasKey('intensity', $result);
            $this->assertArrayHasKey('country', $result);
            $this->assertArrayHasKey('timezone', $result);
            $this->assertIsInt($result['intensity']);
            $this->assertGreaterThan(0, $result['intensity']);
            $this->assertSame(2, strlen($result['country']));
        } else {
            // System timezone is not in the mapping — that's fine
            $this->addToAssertionCount(1);
        }
    }

    public function testAllCountriesReturnsNonEmptyArray(): void
    {
        $all = GridCarbonData::allCountries();
        $this->assertGreaterThan(50, count($all));

        // All keys are 2-letter country codes
        foreach ($all as $code => $intensity) {
            $this->assertSame(2, strlen($code), "Code '{$code}' is not 2 chars");
            $this->assertSame(strtoupper($code), $code, "Code '{$code}' is not uppercase");
            $this->assertIsInt($intensity);
            $this->assertGreaterThan(0, $intensity);
        }
    }

    public function testGetSourceAttribution(): void
    {
        $source = GridCarbonData::getSourceAttribution();
        $this->assertStringContainsString('Ember', $source);
        $this->assertStringContainsString('CC BY 4.0', $source);
        $this->assertStringContainsString('ember-energy.org', $source);
    }
}
