<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Config;

final class ConfigEnvironmentTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SCI_PROFILER_ENABLED');
        putenv('SCI_PROFILER_DEVICE_POWER_WATTS');
        putenv('SCI_PROFILER_GRID_CARBON_INTENSITY');
        putenv('SCI_PROFILER_OUTPUT_DIR');
        putenv('SCI_PROFILER_REPORTERS');
        putenv('SCI_PROFILER_MACHINE_DESCRIPTION');
    }

    public function testFromEnvironment(): void
    {
        putenv('SCI_PROFILER_ENABLED=1');
        putenv('SCI_PROFILER_DEVICE_POWER_WATTS=65');
        putenv('SCI_PROFILER_GRID_CARBON_INTENSITY=56');
        putenv('SCI_PROFILER_OUTPUT_DIR=/var/log/sci');
        putenv('SCI_PROFILER_REPORTERS=json,html');
        putenv('SCI_PROFILER_MACHINE_DESCRIPTION=CI server');

        $config = Config::fromEnvironment();

        $this->assertSame(65.0, $config->getDevicePowerWatts());
        $this->assertSame(56.0, $config->getGridCarbonIntensity());
        $this->assertSame('/var/log/sci', $config->getOutputDir());
        $this->assertSame(['json', 'html'], $config->getReporters());
        $this->assertSame('CI server', $config->getMachineDescription());
    }

    public function testFromEnvironmentUsesDefaults(): void
    {
        putenv('SCI_PROFILER_ENABLED=1');

        $config = Config::fromEnvironment();

        $this->assertSame(18.0, $config->getDevicePowerWatts());
        $this->assertSame(332.0, $config->getGridCarbonIntensity());
    }
}
