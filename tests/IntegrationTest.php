<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Collector\MemoryCollector;
use SciProfiler\Collector\RequestCollector;
use SciProfiler\Collector\TimeCollector;
use SciProfiler\Config;
use SciProfiler\Reporter\JsonReporter;
use SciProfiler\Reporter\LogReporter;
use SciProfiler\SciProfiler;

/**
 * End-to-end integration test simulating the bootstrap lifecycle.
 */
final class IntegrationTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/sci-integration-' . uniqid();
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['SCRIPT_FILENAME'],
        );

        if (is_dir($this->outputDir)) {
            $files = glob($this->outputDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            rmdir($this->outputDir);
        }
    }

    public function testFullRequestLifecycle(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/integration-test';

        $config = new Config(
            devicePowerWatts: 18.0,
            gridCarbonIntensity: 332.0,
            embodiedCarbon: 211000.0,
            deviceLifetimeHours: 11680.0,
            enabled: true,
            outputDir: $this->outputDir,
            reporters: ['json', 'log'],
        );

        $profiler = new SciProfiler($config);
        $profiler->addCollector(new TimeCollector());
        $profiler->addCollector(new MemoryCollector());
        $profiler->addCollector(new RequestCollector());
        $profiler->addReporter(new JsonReporter());
        $profiler->addReporter(new LogReporter());

        // Simulate request lifecycle
        $profiler->start();

        // Simulate work
        $data = array_map(static fn (int $i) => $i * $i, range(1, 1000));

        $result = $profiler->stop();

        // Verify result structure
        $this->assertGreaterThan(0, $result->getSciScore());

        $collectors = $result->getCollectorMetrics();
        $this->assertArrayHasKey('time', $collectors);
        $this->assertArrayHasKey('memory', $collectors);
        $this->assertArrayHasKey('request', $collectors);
        $this->assertSame('GET', $collectors['request']['method']);
        $this->assertSame('/integration-test', $collectors['request']['uri']);

        // Verify files were written
        $this->assertFileExists($this->outputDir . '/sci-profiler.jsonl');
        $this->assertFileExists($this->outputDir . '/sci-profiler.log');

        // Verify JSONL content is valid
        $jsonl = file_get_contents($this->outputDir . '/sci-profiler.jsonl');
        $decoded = json_decode(trim($jsonl), true);
        $this->assertIsArray($decoded);
        $this->assertSame($result->getProfileId(), $decoded['profile_id']);
        $this->assertArrayHasKey('sci.sci_mgco2eq', $decoded);

        // Verify log contains expected data
        $log = file_get_contents($this->outputDir . '/sci-profiler.log');
        $this->assertStringContainsString('GET', $log);
        $this->assertStringContainsString('/integration-test', $log);
        $this->assertStringContainsString('mgCO2eq', $log);

        // Prevent optimizer from removing $data
        $this->assertNotEmpty($data);
    }

    public function testMultipleRequestsAppendToJsonl(): void
    {
        $config = new Config(
            enabled: true,
            outputDir: $this->outputDir,
        );

        for ($i = 0; $i < 3; $i++) {
            $profiler = new SciProfiler($config);
            $profiler->addCollector(new TimeCollector());
            $profiler->addReporter(new JsonReporter());
            $profiler->start();
            $profiler->stop();
        }

        $lines = file($this->outputDir . '/sci-profiler.jsonl', FILE_SKIP_EMPTY_LINES);
        $this->assertCount(3, $lines);

        // Each line must be valid JSON with a unique profile_id
        $ids = [];
        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            $this->assertIsArray($decoded);
            $ids[] = $decoded['profile_id'];
        }

        $this->assertCount(3, array_unique($ids));
    }

    public function testSciScoreIncreasesWithLongerWork(): void
    {
        $config = new Config(
            enabled: true,
            outputDir: $this->outputDir,
        );

        // Short request
        $profiler1 = new SciProfiler($config);
        $profiler1->addCollector(new TimeCollector());
        $profiler1->addReporter(new JsonReporter());
        $profiler1->start();
        $result1 = $profiler1->stop();

        // Longer request
        $profiler2 = new SciProfiler($config);
        $profiler2->addCollector(new TimeCollector());
        $profiler2->addReporter(new JsonReporter());
        $profiler2->start();

        // Do some actual work
        $x = 0;
        for ($i = 0; $i < 500000; $i++) {
            $x += $i;
        }

        $result2 = $profiler2->stop();

        // The longer request should have a higher SCI score
        $this->assertGreaterThanOrEqual(
            $result1->getSciScore(),
            $result2->getSciScore()
        );

        // Prevent optimizer from removing $x
        $this->assertIsInt($x);
    }
}
