<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use SciProfiler\Collector\MemoryCollector;
use SciProfiler\Collector\RequestCollector;
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

    public function testTimeCollectorWithPsr20Clock(): void
    {
        $callCount = 0;
        $clock = new class ($callCount) implements ClockInterface {
            public function __construct(private int &$callCount)
            {
            }

            public function now(): \DateTimeImmutable
            {
                $this->callCount++;
                // First call = start (T=0), second call = stop (T=0.123s)
                return $this->callCount <= 1
                    ? new \DateTimeImmutable('2026-01-01 00:00:00.000000')
                    : new \DateTimeImmutable('2026-01-01 00:00:00.123000');
            }
        };

        $collector = new TimeCollector(clock: $clock);
        $collector->start();
        $collector->stop();
        $metrics = $collector->getMetrics();

        // PSR-20 clock should drive the wall time calculation
        $this->assertEqualsWithDelta(123.0, $metrics['wall_time_ms'], 0.5);
        $this->assertEqualsWithDelta(0.123, $metrics['wall_time_sec'], 0.001);
    }

    public function testTimeCollectorWithoutClockUsesHrtime(): void
    {
        // No clock provided — should use hrtime() and produce real wall time
        $collector = new TimeCollector();
        $collector->start();
        usleep(5000); // 5ms
        $collector->stop();
        $metrics = $collector->getMetrics();

        $this->assertGreaterThan(4.0, $metrics['wall_time_ms']);
        $this->assertLessThan(100.0, $metrics['wall_time_ms']);
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

    public function testRequestCollectorGetName(): void
    {
        $collector = new RequestCollector();
        $this->assertSame('request', $collector->getName());
    }

    public function testRequestCollectorIncludesScriptFilename(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/app/index.php';

        $collector = new RequestCollector();
        $collector->start();
        $collector->stop();
        $metrics = $collector->getMetrics();

        $this->assertSame('/var/www/app/index.php', $metrics['script_filename']);

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_FILENAME']);
    }
}
