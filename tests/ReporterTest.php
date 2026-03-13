<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\JsonReporter;
use SciProfiler\Reporter\LogReporter;
use SciProfiler\Reporter\HtmlReporter;

final class ReporterTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/sci-test-' . uniqid();
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

    private function createResult(): ProfileResult
    {
        return new ProfileResult(
            collectorMetrics: [
                'time' => ['wall_time_ms' => 50.123, 'wall_time_sec' => 0.050123],
                'memory' => ['memory_peak_mb' => 2.5],
                'request' => ['method' => 'GET', 'uri' => '/test', 'response_code' => 200],
            ],
            sciMetrics: [
                'energy_kwh' => 0.0000001,
                'sci_gco2eq' => 0.0001,
                'sci_mgco2eq' => 0.1,
            ],
            timestamp: '2026-01-01T00:00:00+00:00',
            profileId: 'test123',
        );
    }

    public function testJsonReporterCreatesJsonlFile(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new JsonReporter();
        $result = $this->createResult();

        $reporter->report($result, $config);

        $file = $this->outputDir . '/sci-profiler.jsonl';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertNotFalse($content);

        $decoded = json_decode(trim($content), true);
        $this->assertIsArray($decoded);
        $this->assertSame('test123', $decoded['profile_id']);
    }

    public function testLogReporterCreatesLogFile(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new LogReporter();
        $result = $this->createResult();

        $reporter->report($result, $config);

        $file = $this->outputDir . '/sci-profiler.log';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('GET', $content);
        $this->assertStringContainsString('/test', $content);
        $this->assertStringContainsString('mgCO2eq', $content);
    }

    public function testHtmlReporterCreatesDashboard(): void
    {
        $config = new Config(outputDir: $this->outputDir);

        // First write a jsonl entry so the HTML has data
        $jsonReporter = new JsonReporter();
        $result = $this->createResult();
        $jsonReporter->report($result, $config);

        // Now generate dashboard
        $htmlReporter = new HtmlReporter();
        $htmlReporter->report($result, $config);

        $file = $this->outputDir . '/dashboard.html';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('SCI Profiler Dashboard', $content);
        $this->assertStringContainsString('mgCO2eq', $content);
    }
}
