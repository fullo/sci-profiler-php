<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Collector\MemoryCollector;
use SciProfiler\Collector\TimeCollector;

final class CollectorTest extends TestCase
{
    public function testTimeCollectorMeasuresWallTime(): void
    {
        $collector = new TimeCollector();
        $collector->start();

        // Burn some CPU time
        $x = 0;
        for ($i = 0; $i < 100000; $i++) {
            $x += $i;
        }

        $collector->stop();
        $metrics = $collector->getMetrics();

        $this->assertSame('time', $collector->getName());
        $this->assertArrayHasKey('wall_time_ns', $metrics);
        $this->assertArrayHasKey('wall_time_ms', $metrics);
        $this->assertArrayHasKey('wall_time_sec', $metrics);
        $this->assertGreaterThan(0, $metrics['wall_time_ns']);
    }

    public function testTimeCollectorIncludesCpuTimeOnSupportedPlatforms(): void
    {
        if (!function_exists('getrusage')) {
            $this->markTestSkipped('getrusage() not available on this platform.');
        }

        $collector = new TimeCollector();
        $collector->start();

        $x = 0;
        for ($i = 0; $i < 100000; $i++) {
            $x += $i;
        }

        $collector->stop();
        $metrics = $collector->getMetrics();

        $this->assertArrayHasKey('cpu_user_time_sec', $metrics);
        $this->assertArrayHasKey('cpu_system_time_sec', $metrics);
        $this->assertArrayHasKey('cpu_total_time_sec', $metrics);
    }

    public function testMemoryCollectorTracksUsage(): void
    {
        $collector = new MemoryCollector();
        $collector->start();

        // Allocate some memory
        $data = str_repeat('x', 1024 * 1024);

        $collector->stop();
        $metrics = $collector->getMetrics();

        $this->assertSame('memory', $collector->getName());
        $this->assertArrayHasKey('memory_start_bytes', $metrics);
        $this->assertArrayHasKey('memory_end_bytes', $metrics);
        $this->assertArrayHasKey('memory_peak_bytes', $metrics);
        $this->assertArrayHasKey('memory_peak_mb', $metrics);
        $this->assertGreaterThan(0, $metrics['memory_peak_bytes']);

        // Prevent optimizer from removing $data
        $this->assertNotEmpty($data);
    }
}
