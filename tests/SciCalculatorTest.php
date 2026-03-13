<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Config;
use SciProfiler\SciCalculator;

final class SciCalculatorTest extends TestCase
{
    private SciCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SciCalculator(new Config(
            devicePowerWatts: 18.0,
            gridCarbonIntensity: 332.0,
            embodiedCarbon: 211000.0,
            deviceLifetimeHours: 11680.0,
        ));
    }

    public function testCalculateEnergyForOneSecond(): void
    {
        // E = 18W × 1s / 3,600,000 = 0.000005 kWh
        $energy = $this->calculator->calculateEnergy(1.0);

        $this->assertEqualsWithDelta(0.000005, $energy, 0.0000001);
    }

    public function testCalculateEnergyForZeroTime(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateEnergy(0.0));
    }

    public function testCalculateOperationalCarbon(): void
    {
        $energy = 0.000005; // kWh
        // Operational = 0.000005 × 332 = 0.00166 gCO2eq
        $operational = $this->calculator->calculateOperationalCarbon($energy);

        $this->assertEqualsWithDelta(0.00166, $operational, 0.00001);
    }

    public function testCalculateEmbodiedCarbon(): void
    {
        // M = (211000 / 11680) × (1 / 3600) = 0.005017 gCO2eq
        $embodied = $this->calculator->calculateEmbodiedCarbon(1.0);

        $this->assertEqualsWithDelta(0.005017, $embodied, 0.0001);
    }

    public function testCalculateEmbodiedCarbonWithZeroLifetime(): void
    {
        $calculator = new SciCalculator(new Config(
            deviceLifetimeHours: 0.0,
        ));

        $this->assertSame(0.0, $calculator->calculateEmbodiedCarbon(1.0));
    }

    public function testFullCalculation(): void
    {
        $result = $this->calculator->calculate(1.0);

        $this->assertArrayHasKey('energy_kwh', $result);
        $this->assertArrayHasKey('operational_carbon_gco2eq', $result);
        $this->assertArrayHasKey('embodied_carbon_gco2eq', $result);
        $this->assertArrayHasKey('sci_gco2eq', $result);
        $this->assertArrayHasKey('sci_mgco2eq', $result);

        // SCI = operational + embodied, should be positive for positive wall time
        $this->assertGreaterThan(0, $result['sci_gco2eq']);
        $this->assertGreaterThan(0, $result['sci_mgco2eq']);

        // mgCO2eq should be 1000× gCO2eq
        $this->assertEqualsWithDelta(
            $result['sci_gco2eq'] * 1000,
            $result['sci_mgco2eq'],
            0.001
        );
    }
}
