<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Collector\MemoryCollector;
use SciProfiler\Collector\TimeCollector;
use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\ReporterInterface;
use SciProfiler\SciProfiler;

final class SciProfilerTest extends TestCase
{
    public function testStartAndStopProducesResult(): void
    {
        $config = new Config(outputDir: sys_get_temp_dir() . '/sci-test-' . uniqid());
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());
        $profiler->addCollector(new MemoryCollector());

        $profiler->start();
        $this->assertTrue($profiler->isStarted());

        $result = $profiler->stop();

        $this->assertInstanceOf(ProfileResult::class, $result);
        $this->assertNotEmpty($result->getProfileId());
        $this->assertNotEmpty($result->getTimestamp());
        $this->assertGreaterThan(0, $result->getSciScore());
    }

    public function testDisabledProfilerDoesNotStart(): void
    {
        $config = new Config(enabled: false);
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());

        $profiler->start();

        $this->assertFalse($profiler->isStarted());
    }

    public function testReporterIsCalledOnStop(): void
    {
        $config = new Config(outputDir: sys_get_temp_dir() . '/sci-test-' . uniqid());
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());

        $reported = false;
        $mockReporter = new class ($reported) implements ReporterInterface {
            public function __construct(private bool &$reported)
            {
            }

            public function report(ProfileResult $result, Config $config): void
            {
                $this->reported = true;
            }

            public function getName(): string
            {
                return 'mock';
            }
        };

        $profiler->addReporter($mockReporter);
        $profiler->start();
        $profiler->stop();

        $this->assertTrue($reported);
    }

    public function testReporterExceptionDoesNotBreakProfiler(): void
    {
        $config = new Config();
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());

        $failingReporter = new class () implements ReporterInterface {
            public function report(ProfileResult $result, Config $config): void
            {
                throw new \RuntimeException('Reporter failed');
            }

            public function getName(): string
            {
                return 'failing';
            }
        };

        $profiler->addReporter($failingReporter);
        $profiler->start();

        // Should not throw
        $result = $profiler->stop();
        $this->assertInstanceOf(ProfileResult::class, $result);
    }

    public function testProfileResultToArray(): void
    {
        $result = new ProfileResult(
            collectorMetrics: [
                'time' => ['wall_time_ms' => 123.456],
                'memory' => ['memory_peak_mb' => 4.5],
            ],
            sciMetrics: [
                'sci_mgco2eq' => 0.0012,
            ],
            timestamp: '2026-01-01T00:00:00+00:00',
            profileId: 'abc123',
        );

        $array = $result->toArray();

        $this->assertSame('abc123', $array['profile_id']);
        $this->assertSame(123.456, $array['time.wall_time_ms']);
        $this->assertSame(4.5, $array['memory.memory_peak_mb']);
        $this->assertSame(0.0012, $array['sci.sci_mgco2eq']);
    }
}
