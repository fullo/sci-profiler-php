<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use SciProfiler\Collector\MemoryCollector;
use SciProfiler\Collector\RequestCollector;
use SciProfiler\Collector\TimeCollector;
use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\JsonReporter;
use SciProfiler\Reporter\TrendReporter;
use SciProfiler\SciCalculator;
use SciProfiler\SciProfiler;

/**
 * Edge case tests for boundary conditions, error handling, and robustness.
 */
final class EdgeCaseTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/sci-edge-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            $files = glob($this->outputDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            rmdir($this->outputDir);
        }
    }

    // =========================================================================
    // TrendReporter edge cases
    // =========================================================================

    public function testTrendReporterFirstSciZeroShowsStable(): void
    {
        // Bug: when first SCI is 0.0 and last is 5.0, changePercent = 0 (division skipped)
        // This is actually correct behavior: 0→5 is undefined % change, so "stable" is safer
        // than an arbitrary "infinite % increase"
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        $entries = [
            $this->makeResult(0.0, '/test.php'),
            $this->makeResult(5.0, '/test.php'),
        ];

        foreach ($entries as $result) {
            $jsonR->report($result, $config);
        }
        $trendR->report($entries[1], $config);

        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        // Should not crash, should produce a report
        $this->assertStringContainsString('test.php', $content);
        $this->assertStringContainsString('0.0%', $content); // 0% change from base 0
    }

    public function testTrendReporterSparklineIdenticalValues(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        // All identical SCI values
        for ($i = 0; $i < 5; $i++) {
            $jsonR->report($this->makeResult(1.234, '/stable.php'), $config);
        }
        $trendR->report($this->makeResult(1.234, '/stable.php'), $config);

        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('stable', $content);
        $this->assertStringContainsString('0.0%', $content);
        // Sparkline should be a flat line (repeated mid-char)
        $this->assertStringContainsString('▄▄▄', $content);
    }

    public function testTrendReporterSingleRunScriptSkippedInTrends(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        // One script with 1 run, another with 2 runs
        $jsonR->report($this->makeResult(1.0, '/single.php'), $config);
        $jsonR->report($this->makeResult(2.0, '/multi.php'), $config);
        $jsonR->report($this->makeResult(2.5, '/multi.php'), $config);
        $trendR->report($this->makeResult(0.1, '/dummy'), $config);

        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        // multi.php should appear in per-script trends section with run count
        $this->assertStringContainsString('multi.php', $content);
        $this->assertMatchesRegularExpression('/multi\.php\s+\[2 runs\]/', $content);
        // single.php should NOT appear in per-script trends (only 1 run)
        // but it MAY appear in "Recent History" — that's correct behavior
        $this->assertDoesNotMatchRegularExpression('/single\.php\s+\[\d+ runs\]/', $content);
    }

    // =========================================================================
    // TimeCollector + PSR-20 Clock edge cases
    // =========================================================================

    public function testTimeCollectorWithMockClock(): void
    {
        $clock = $this->createMockClock([
            new \DateTimeImmutable('2026-01-01 00:00:00.000000'),
            new \DateTimeImmutable('2026-01-01 00:00:00.050000'), // 50ms later
        ]);

        $collector = new TimeCollector(clock: $clock);
        $collector->start();
        $collector->stop();

        $metrics = $collector->getMetrics();
        $this->assertEqualsWithDelta(50.0, $metrics['wall_time_ms'], 1.0);
        $this->assertEqualsWithDelta(0.05, $metrics['wall_time_sec'], 0.001);
    }

    public function testTimeCollectorWithBackwardClock(): void
    {
        // Clock returns earlier time on stop() — should produce negative wall time
        $clock = $this->createMockClock([
            new \DateTimeImmutable('2026-01-01 00:00:01.000000'), // start: later
            new \DateTimeImmutable('2026-01-01 00:00:00.000000'), // stop: earlier
        ]);

        $collector = new TimeCollector(clock: $clock);
        $collector->start();
        $collector->stop();

        $metrics = $collector->getMetrics();
        // Negative wall time — profiler should handle gracefully, not crash
        $this->assertLessThan(0, $metrics['wall_time_sec']);
    }

    public function testTimeCollectorWithSameStartStopClock(): void
    {
        $same = new \DateTimeImmutable('2026-01-01 00:00:00.000000');
        $clock = $this->createMockClock([$same, $same]);

        $collector = new TimeCollector(clock: $clock);
        $collector->start();
        $collector->stop();

        $metrics = $collector->getMetrics();
        $this->assertSame(0.0, $metrics['wall_time_sec']);
        $this->assertSame(0.0, $metrics['wall_time_ms']);
    }

    public function testTimeCollectorWithoutClockUsesHrtime(): void
    {
        $collector = new TimeCollector(); // No clock
        $collector->start();
        usleep(1000); // 1ms
        $collector->stop();

        $metrics = $collector->getMetrics();
        $this->assertGreaterThan(0, $metrics['wall_time_ns']);
        $this->assertArrayHasKey('wall_time_ms', $metrics);
        $this->assertArrayHasKey('wall_time_sec', $metrics);
    }

    // =========================================================================
    // ReadsJsonlHistory edge cases
    // =========================================================================

    public function testCorruptedJsonlLinesAreSkipped(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        @mkdir($this->outputDir, 0755, true);

        $jsonlFile = $this->outputDir . '/sci-profiler.jsonl';
        file_put_contents($jsonlFile, implode("\n", [
            '{"profile_id":"1","sci.sci_mgco2eq":0.1}',
            '{"bad json missing closing brace',
            '',
            '{"profile_id":"3","sci.sci_mgco2eq":0.3}',
            'not json at all',
            '{"profile_id":"5","sci.sci_mgco2eq":0.5}',
        ]) . "\n");

        // TrendReporter reads via ReadsJsonlHistory — should skip bad lines
        $trendR = new TrendReporter();
        $trendR->report($this->makeResult(0.1, '/test.php'), $config);

        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        // Should not crash, should produce a report with 3 valid entries
        $this->assertStringContainsString('SCI Trend Report', $content);
    }

    public function testRingBufferAtExactBoundary(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        @mkdir($this->outputDir, 0755, true);

        $jsonlFile = $this->outputDir . '/sci-profiler.jsonl';

        // Write exactly 200 entries (HtmlReporter MAX_ENTRIES) + 1 to test wrap
        $lines = [];
        for ($i = 0; $i < 201; $i++) {
            $lines[] = json_encode([
                'profile_id' => 'id-' . $i,
                'sci.sci_mgco2eq' => 0.1 + $i * 0.001,
                'request.script_filename' => '/test.php',
                'request.method' => 'CLI',
                'timestamp' => '2026-01-01T00:00:0' . ($i % 10) . '+00:00',
                'time.wall_time_ms' => 50.0,
                'memory.memory_peak_mb' => 4.0,
            ]);
        }
        file_put_contents($jsonlFile, implode("\n", $lines) . "\n");

        $trendR = new TrendReporter();
        $trendR->report($this->makeResult(0.1, '/test.php'), $config);

        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('SCI Trend Report', $content);
        $this->assertStringContainsString('test.php', $content);
    }

    public function testEmptyJsonlFile(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        @mkdir($this->outputDir, 0755, true);

        file_put_contents($this->outputDir . '/sci-profiler.jsonl', '');

        $trendR = new TrendReporter();
        $trendR->report($this->makeResult(0.1, '/test.php'), $config);

        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('No profiling data collected yet', $content);
    }

    // =========================================================================
    // SciProfiler orchestrator edge cases
    // =========================================================================

    public function testStopWithoutStartProducesResult(): void
    {
        $config = new Config(outputDir: $this->outputDir, reporters: []);
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());
        $profiler->addCollector(new MemoryCollector());

        // stop() without start() — should not throw
        $result = $profiler->stop();

        $this->assertInstanceOf(ProfileResult::class, $result);
        // SCI score may be negative or zero but should not crash
        $this->assertIsFloat($result->getSciScore());
    }

    public function testProfilerWithNoReporters(): void
    {
        $config = new Config(outputDir: $this->outputDir, reporters: []);
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());

        $profiler->start();
        $result = $profiler->stop();

        // Result should be valid even without reporters
        $this->assertInstanceOf(ProfileResult::class, $result);
        $this->assertGreaterThan(0, $result->getSciScore());
        // No files should be created
        $this->assertDirectoryDoesNotExist($this->outputDir);
    }

    public function testProfilerWithNoCollectors(): void
    {
        $config = new Config(outputDir: $this->outputDir, reporters: []);
        $profiler = new SciProfiler($config);
        // No user collectors added

        $profiler->start();
        $result = $profiler->stop();

        // SCI = 0 because wall_time_sec defaults to 0.0 (no TimeCollector)
        $this->assertSame(0.0, $result->getSciScore());
        // Profiler may inject a config collector internally, but no time/memory/request
        $this->assertArrayNotHasKey('time', $result->getCollectorMetrics());
        $this->assertArrayNotHasKey('memory', $result->getCollectorMetrics());
        $this->assertArrayNotHasKey('request', $result->getCollectorMetrics());
    }

    public function testProfilerDisabledSkipsEverything(): void
    {
        $config = new Config(enabled: false, outputDir: $this->outputDir);
        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());

        $profiler->start();
        $this->assertFalse($profiler->isStarted());

        // stop() still works and returns a result
        $result = $profiler->stop();
        $this->assertInstanceOf(ProfileResult::class, $result);
    }

    // =========================================================================
    // SciCalculator edge cases
    // =========================================================================

    public function testCalculateWithZeroWallTime(): void
    {
        $config = new Config();
        $calc = new SciCalculator($config);

        $result = $calc->calculate(0.0);

        $this->assertSame(0.0, $result['energy_kwh']);
        $this->assertSame(0.0, $result['sci_mgco2eq']);
    }

    public function testCalculateWithNegativeWallTime(): void
    {
        $config = new Config();
        $calc = new SciCalculator($config);

        // Negative wall time (e.g., clock going backward)
        $result = $calc->calculate(-1.0);

        // Should produce negative values, not crash
        $this->assertLessThan(0, $result['energy_kwh']);
        $this->assertLessThan(0, $result['sci_mgco2eq']);
    }

    public function testCalculateWithZeroDevicePower(): void
    {
        $config = new Config(devicePowerWatts: 0.0);
        $calc = new SciCalculator($config);

        $result = $calc->calculate(1.0);

        // Energy = 0, but embodied carbon still contributes
        $this->assertSame(0.0, $result['energy_kwh']);
        $this->assertGreaterThan(0, $result['embodied_carbon_gco2eq']);
        $this->assertGreaterThan(0, $result['sci_mgco2eq']);
    }

    public function testCalculateWithZeroGridIntensity(): void
    {
        $config = new Config(gridCarbonIntensity: 0.0);
        $calc = new SciCalculator($config);

        $result = $calc->calculate(1.0);

        // Operational carbon = 0 (100% renewable), but embodied remains
        $this->assertSame(0.0, $result['operational_carbon_gco2eq']);
        $this->assertGreaterThan(0, $result['embodied_carbon_gco2eq']);
    }

    public function testCalculateWithZeroLifetimeHours(): void
    {
        $config = new Config(deviceLifetimeHours: 0.0);
        $calc = new SciCalculator($config);

        $result = $calc->calculate(1.0);

        // Embodied carbon = 0 (guarded by <= 0 check)
        $this->assertSame(0.0, $result['embodied_carbon_gco2eq']);
        $this->assertGreaterThan(0, $result['operational_carbon_gco2eq']);
    }

    // =========================================================================
    // Config edge cases
    // =========================================================================

    public function testConfigFromArrayWithStringNumbersCastsCorrectly(): void
    {
        $config = Config::fromArray([
            'device_power_watts' => '65',
            'grid_carbon_intensity' => '56',
            'enabled' => '1',
        ]);

        $this->assertSame(65.0, $config->getDevicePowerWatts());
        $this->assertSame(56.0, $config->getGridCarbonIntensity());
        $this->assertTrue($config->isEnabled());
    }

    public function testConfigFromArrayWithNonNumericStringCastsToZero(): void
    {
        $config = Config::fromArray([
            'device_power_watts' => 'not a number',
        ]);

        $this->assertSame(0.0, $config->getDevicePowerWatts());
    }

    public function testConfigFromArrayWithEmptyReporters(): void
    {
        $config = Config::fromArray([
            'reporters' => [],
        ]);

        $this->assertSame([], $config->getReporters());
    }

    public function testConfigEnabledFalseFromEnvironment(): void
    {
        putenv('SCI_PROFILER_ENABLED=0');

        $config = Config::fromEnvironment();

        // PHP: (bool) '0' === false — this should work correctly
        $this->assertFalse($config->isEnabled());

        putenv('SCI_PROFILER_ENABLED');
    }

    public function testConfigEnabledTrueFromEnvironment(): void
    {
        putenv('SCI_PROFILER_ENABLED=1');

        $config = Config::fromEnvironment();
        $this->assertTrue($config->isEnabled());

        putenv('SCI_PROFILER_ENABLED');
    }

    // =========================================================================
    // TrendReporter — all 5 trendIndicator paths
    // =========================================================================

    public function testTrendIndicatorMuchImproved(): void
    {
        // Change < -20% → "▼▼ much improved"
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        $jsonR->report($this->makeResult(10.0, '/test.php'), $config);
        $jsonR->report($this->makeResult(5.0, '/test.php'), $config); // -50%

        $trendR->report($this->makeResult(0.1, '/dummy'), $config);
        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('much improved', $content);
    }

    public function testTrendIndicatorImproved(): void
    {
        // Change between -20% and -5% → "▼ improved"
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        $jsonR->report($this->makeResult(1.0, '/test.php'), $config);
        $jsonR->report($this->makeResult(0.88, '/test.php'), $config); // -12%

        $trendR->report($this->makeResult(0.1, '/dummy'), $config);
        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('▼ improved', $content);
        $this->assertStringNotContainsString('much improved', $content);
    }

    public function testTrendIndicatorWorse(): void
    {
        // Change between +5% and +20% → "▲ worse"
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        $jsonR->report($this->makeResult(1.0, '/test.php'), $config);
        $jsonR->report($this->makeResult(1.15, '/test.php'), $config); // +15%

        $trendR->report($this->makeResult(0.1, '/dummy'), $config);
        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('▲ worse', $content);
        $this->assertStringNotContainsString('much worse', $content);
    }

    public function testTrendIndicatorMuchWorse(): void
    {
        // Change > +20% → "▲▲ much worse"
        $config = new Config(outputDir: $this->outputDir);
        $jsonR = new JsonReporter();
        $trendR = new TrendReporter();

        $jsonR->report($this->makeResult(1.0, '/test.php'), $config);
        $jsonR->report($this->makeResult(2.0, '/test.php'), $config); // +100%

        $trendR->report($this->makeResult(0.1, '/dummy'), $config);
        $content = (string) file_get_contents($this->outputDir . '/sci-trend.txt');
        $this->assertStringContainsString('much worse', $content);
    }

    // =========================================================================
    // Config — getLcaSource
    // =========================================================================

    public function testConfigLcaSourceCustomValue(): void
    {
        $config = Config::fromArray(['lca_source' => 'Apple Environmental Report 2024']);
        $this->assertSame('Apple Environmental Report 2024', $config->getLcaSource());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeResult(float $sci, string $script): ProfileResult
    {
        return new ProfileResult(
            collectorMetrics: [
                'time' => ['wall_time_ms' => 50.0, 'wall_time_sec' => 0.05],
                'memory' => ['memory_peak_mb' => 4.0],
                'request' => [
                    'method' => 'CLI',
                    'uri' => $script,
                    'script_filename' => $script,
                    'response_code' => 0,
                    'input_bytes' => 0,
                    'output_bytes' => 0,
                ],
            ],
            sciMetrics: ['sci_mgco2eq' => $sci],
            timestamp: gmdate('c'),
            profileId: bin2hex(random_bytes(8)),
        );
    }

    /**
     * Create a mock PSR-20 ClockInterface that returns timestamps in sequence.
     *
     * @param \DateTimeImmutable[] $timestamps
     */
    private function createMockClock(array $timestamps): ClockInterface
    {
        return new class ($timestamps) implements ClockInterface {
            private int $index = 0;

            /** @param \DateTimeImmutable[] $timestamps */
            public function __construct(private readonly array $timestamps)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->timestamps[$this->index++] ?? $this->timestamps[array_key_last($this->timestamps)];
            }
        };
    }
}
