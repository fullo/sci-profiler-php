<?php

declare(strict_types=1);

namespace SciProfiler;

/**
 * Calculates Software Carbon Intensity (SCI) scores.
 *
 * Implements the Green Software Foundation formula:
 * SCI = ((E × I) + M) / R
 *
 * Where:
 *   E = Energy consumed (kWh)
 *   I = Grid carbon intensity (gCO2eq/kWh)
 *   M = Embodied carbon amortized per operation (gCO2eq)
 *   R = Functional unit (1 request)
 *
 * @see https://sci-guide.greensoftware.foundation/
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class SciCalculator
{
    /** Watt-seconds to kilowatt-hours conversion factor (1 kWh = 3,600,000 Ws). */
    private const WATT_SECONDS_PER_KWH = 3_600_000;

    /** Seconds per hour. */
    private const SECONDS_PER_HOUR = 3600;

    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * Calculate energy consumed in kilowatt-hours.
     *
     * E = DevicePower (W) × WallTime (s) / 3,600,000
     */
    public function calculateEnergy(float $wallTimeSeconds): float
    {
        return ($this->config->getDevicePowerWatts() * $wallTimeSeconds) / self::WATT_SECONDS_PER_KWH;
    }

    /**
     * Calculate operational carbon emissions in gCO2eq.
     *
     * Operational = E × I
     */
    public function calculateOperationalCarbon(float $energyKwh): float
    {
        return $energyKwh * $this->config->getGridCarbonIntensity();
    }

    /**
     * Calculate amortized embodied carbon per operation in gCO2eq.
     *
     * M = (TotalEmbodied / LifetimeHours) × (WallTime / 3600)
     */
    public function calculateEmbodiedCarbon(float $wallTimeSeconds): float
    {
        $lifetimeHours = $this->config->getDeviceLifetimeHours();

        if ($lifetimeHours <= 0.0) {
            return 0.0;
        }

        return ($this->config->getEmbodiedCarbon() / $lifetimeHours)
            * ($wallTimeSeconds / self::SECONDS_PER_HOUR);
    }

    /**
     * Calculate the full SCI score for a single request.
     *
     * @return array{
     *     energy_kwh: float,
     *     operational_carbon_gco2eq: float,
     *     embodied_carbon_gco2eq: float,
     *     sci_gco2eq: float,
     *     sci_mgco2eq: float,
     * }
     */
    public function calculate(float $wallTimeSeconds): array
    {
        $energy = $this->calculateEnergy($wallTimeSeconds);
        $operational = $this->calculateOperationalCarbon($energy);
        $embodied = $this->calculateEmbodiedCarbon($wallTimeSeconds);
        $sci = $operational + $embodied;

        return [
            'energy_kwh' => $energy,
            'operational_carbon_gco2eq' => round($operational, 9),
            'embodied_carbon_gco2eq' => round($embodied, 9),
            'sci_gco2eq' => round($sci, 9),
            'sci_mgco2eq' => round($sci * 1000, 6),
        ];
    }
}
