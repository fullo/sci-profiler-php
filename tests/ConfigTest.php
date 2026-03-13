<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Config;

final class ConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new Config();

        $this->assertSame(18.0, $config->getDevicePowerWatts());
        $this->assertSame(332.0, $config->getGridCarbonIntensity());
        $this->assertSame(211000.0, $config->getEmbodiedCarbon());
        $this->assertSame(11680.0, $config->getDeviceLifetimeHours());
        $this->assertTrue($config->isEnabled());
        $this->assertSame('/tmp/sci-profiler', $config->getOutputDir());
        $this->assertSame(['json'], $config->getReporters());
    }

    public function testFromArray(): void
    {
        $config = Config::fromArray([
            'device_power_watts' => 65.0,
            'grid_carbon_intensity' => 56.0,
            'enabled' => false,
            'reporters' => ['json', 'html'],
        ]);

        $this->assertSame(65.0, $config->getDevicePowerWatts());
        $this->assertSame(56.0, $config->getGridCarbonIntensity());
        $this->assertFalse($config->isEnabled());
        $this->assertSame(['json', 'html'], $config->getReporters());
    }

    public function testFromArrayUsesDefaultsForMissingKeys(): void
    {
        $config = Config::fromArray([]);

        $this->assertSame(18.0, $config->getDevicePowerWatts());
        $this->assertSame(332.0, $config->getGridCarbonIntensity());
    }

    public function testFromFileThrowsOnMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Config::fromFile('/nonexistent/path/config.php');
    }

    public function testFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sci_test_');
        file_put_contents($tmpFile, '<?php return ["device_power_watts" => 42.0, "enabled" => false];');

        $config = Config::fromFile($tmpFile);

        $this->assertSame(42.0, $config->getDevicePowerWatts());
        $this->assertFalse($config->isEnabled());

        unlink($tmpFile);
    }
}
