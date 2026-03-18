<?php

declare(strict_types=1);

namespace SciProfiler;

/**
 * Configuration for SCI (Software Carbon Intensity) calculations.
 *
 * Default values align with the Green Software Foundation methodology.
 * Override via config file or environment variables prefixed with SCI_PROFILER_.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class Config
{
    // -------------------------------------------------------------------------
    // Default values — single source of truth
    // -------------------------------------------------------------------------

    /** Default device power consumption in watts */
    public const DEFAULT_DEVICE_POWER_WATTS = 18.0;

    /** Default grid carbon intensity in gCO2eq/kWh (global median) */
    public const DEFAULT_GRID_CARBON_INTENSITY = 332.0;

    /** Default total embodied carbon in gCO2eq (MacBook Pro 14" M1) */
    public const DEFAULT_EMBODIED_CARBON = 211000.0;

    /** Default device lifetime in hours (4 years × 8h × 365d) */
    public const DEFAULT_DEVICE_LIFETIME_HOURS = 11680.0;

    /** Default machine description label */
    public const DEFAULT_MACHINE_DESCRIPTION = 'Default development machine';

    /** Default LCA data source reference */
    public const DEFAULT_LCA_SOURCE = 'Estimated';

    /** Default enabled state */
    public const DEFAULT_ENABLED = true;

    /** Default output directory */
    public const DEFAULT_OUTPUT_DIR = '/tmp/sci-profiler';

    /** Default reporters list */
    public const DEFAULT_REPORTERS = ['json'];

    // -------------------------------------------------------------------------
    // Instance properties
    // -------------------------------------------------------------------------

    /** Device power consumption in watts */
    private float $devicePowerWatts;

    /** Grid carbon intensity in gCO2eq/kWh */
    private float $gridCarbonIntensity;

    /** Total embodied carbon of the device in gCO2eq */
    private float $embodiedCarbon;

    /** Expected device lifetime in hours */
    private float $deviceLifetimeHours;

    /** Human-readable machine description */
    private string $machineDescription;

    /** LCA data source reference */
    private string $lcaSource;

    /** Whether the profiler is enabled */
    private bool $enabled;

    /** Directory for storing results */
    private string $outputDir;

    /** Reporter type(s) to use */
    private array $reporters;

    public function __construct(
        float $devicePowerWatts = self::DEFAULT_DEVICE_POWER_WATTS,
        float $gridCarbonIntensity = self::DEFAULT_GRID_CARBON_INTENSITY,
        float $embodiedCarbon = self::DEFAULT_EMBODIED_CARBON,
        float $deviceLifetimeHours = self::DEFAULT_DEVICE_LIFETIME_HOURS,
        string $machineDescription = self::DEFAULT_MACHINE_DESCRIPTION,
        string $lcaSource = self::DEFAULT_LCA_SOURCE,
        bool $enabled = self::DEFAULT_ENABLED,
        string $outputDir = self::DEFAULT_OUTPUT_DIR,
        array $reporters = self::DEFAULT_REPORTERS,
    ) {
        $this->devicePowerWatts = $devicePowerWatts;
        $this->gridCarbonIntensity = $gridCarbonIntensity;
        $this->embodiedCarbon = $embodiedCarbon;
        $this->deviceLifetimeHours = $deviceLifetimeHours;
        $this->machineDescription = $machineDescription;
        $this->lcaSource = $lcaSource;
        $this->enabled = $enabled;
        $this->outputDir = $outputDir;
        $this->reporters = $reporters;
    }

    /**
     * Create a Config instance from a PHP config file.
     *
     * The file must return an associative array.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(
                sprintf('Config file not found or not readable: %s', $path)
            );
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \InvalidArgumentException(
                sprintf('Config file must return an array: %s', $path)
            );
        }

        return self::fromArray($config);
    }

    /**
     * Create a Config instance from an associative array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            devicePowerWatts: (float) ($config['device_power_watts'] ?? self::DEFAULT_DEVICE_POWER_WATTS),
            gridCarbonIntensity: (float) ($config['grid_carbon_intensity'] ?? self::DEFAULT_GRID_CARBON_INTENSITY),
            embodiedCarbon: (float) ($config['embodied_carbon'] ?? self::DEFAULT_EMBODIED_CARBON),
            deviceLifetimeHours: (float) ($config['device_lifetime_hours'] ?? self::DEFAULT_DEVICE_LIFETIME_HOURS),
            machineDescription: (string) ($config['machine_description'] ?? self::DEFAULT_MACHINE_DESCRIPTION),
            lcaSource: (string) ($config['lca_source'] ?? self::DEFAULT_LCA_SOURCE),
            enabled: (bool) ($config['enabled'] ?? self::DEFAULT_ENABLED),
            outputDir: (string) ($config['output_dir'] ?? self::DEFAULT_OUTPUT_DIR),
            reporters: (array) ($config['reporters'] ?? self::DEFAULT_REPORTERS),
        );
    }

    /**
     * Create a Config instance from environment variables.
     *
     * All variables are prefixed with SCI_PROFILER_.
     */
    public static function fromEnvironment(): self
    {
        $env = static function (string $key, mixed $default): mixed {
            $value = getenv('SCI_PROFILER_' . $key);
            return $value !== false ? $value : $default;
        };

        return new self(
            devicePowerWatts: (float) $env('DEVICE_POWER_WATTS', self::DEFAULT_DEVICE_POWER_WATTS),
            gridCarbonIntensity: (float) $env('GRID_CARBON_INTENSITY', self::DEFAULT_GRID_CARBON_INTENSITY),
            embodiedCarbon: (float) $env('EMBODIED_CARBON', self::DEFAULT_EMBODIED_CARBON),
            deviceLifetimeHours: (float) $env('DEVICE_LIFETIME_HOURS', self::DEFAULT_DEVICE_LIFETIME_HOURS),
            machineDescription: (string) $env('MACHINE_DESCRIPTION', self::DEFAULT_MACHINE_DESCRIPTION),
            lcaSource: (string) $env('LCA_SOURCE', self::DEFAULT_LCA_SOURCE),
            enabled: (bool) $env('ENABLED', self::DEFAULT_ENABLED),
            outputDir: (string) $env('OUTPUT_DIR', self::DEFAULT_OUTPUT_DIR),
            reporters: explode(',', (string) $env('REPORTERS', implode(',', self::DEFAULT_REPORTERS))),
        );
    }

    public function getDevicePowerWatts(): float
    {
        return $this->devicePowerWatts;
    }

    public function getGridCarbonIntensity(): float
    {
        return $this->gridCarbonIntensity;
    }

    public function getEmbodiedCarbon(): float
    {
        return $this->embodiedCarbon;
    }

    public function getDeviceLifetimeHours(): float
    {
        return $this->deviceLifetimeHours;
    }

    public function getMachineDescription(): string
    {
        return $this->machineDescription;
    }

    public function getLcaSource(): string
    {
        return $this->lcaSource;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function getReporters(): array
    {
        return $this->reporters;
    }
}
