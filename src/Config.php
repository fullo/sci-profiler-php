<?php

declare(strict_types=1);

namespace SciProfiler;

/**
 * Configuration for SCI (Software Carbon Intensity) calculations.
 *
 * Default values align with the Green Software Foundation methodology.
 * Override via config file or environment variables prefixed with SCI_PROFILER_.
 */
final class Config
{
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
        float $devicePowerWatts = 18.0,
        float $gridCarbonIntensity = 332.0,
        float $embodiedCarbon = 211000.0,
        float $deviceLifetimeHours = 11680.0,
        string $machineDescription = 'Default development machine',
        string $lcaSource = 'Estimated',
        bool $enabled = true,
        string $outputDir = '/tmp/sci-profiler',
        array $reporters = ['json'],
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
            devicePowerWatts: (float) ($config['device_power_watts'] ?? 18.0),
            gridCarbonIntensity: (float) ($config['grid_carbon_intensity'] ?? 332.0),
            embodiedCarbon: (float) ($config['embodied_carbon'] ?? 211000.0),
            deviceLifetimeHours: (float) ($config['device_lifetime_hours'] ?? 11680.0),
            machineDescription: (string) ($config['machine_description'] ?? 'Default development machine'),
            lcaSource: (string) ($config['lca_source'] ?? 'Estimated'),
            enabled: (bool) ($config['enabled'] ?? true),
            outputDir: (string) ($config['output_dir'] ?? '/tmp/sci-profiler'),
            reporters: (array) ($config['reporters'] ?? ['json']),
        );
    }

    /**
     * Create a Config instance from environment variables.
     *
     * All variables are prefixed with SCI_PROFILER_.
     */
    public static function fromEnvironment(): self
    {
        $env = static function (string $key, mixed $default): string|false {
            $value = getenv('SCI_PROFILER_' . $key);
            return $value !== false ? $value : $default;
        };

        return new self(
            devicePowerWatts: (float) $env('DEVICE_POWER_WATTS', 18.0),
            gridCarbonIntensity: (float) $env('GRID_CARBON_INTENSITY', 332.0),
            embodiedCarbon: (float) $env('EMBODIED_CARBON', 211000.0),
            deviceLifetimeHours: (float) $env('DEVICE_LIFETIME_HOURS', 11680.0),
            machineDescription: (string) $env('MACHINE_DESCRIPTION', 'Default development machine'),
            lcaSource: (string) $env('LCA_SOURCE', 'Estimated'),
            enabled: (bool) $env('ENABLED', true),
            outputDir: (string) $env('OUTPUT_DIR', '/tmp/sci-profiler'),
            reporters: explode(',', (string) $env('REPORTERS', 'json')),
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
