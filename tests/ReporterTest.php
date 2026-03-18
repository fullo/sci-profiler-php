<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\HtmlReporter;
use SciProfiler\Reporter\JsonReporter;
use SciProfiler\Reporter\LogReporter;
use SciProfiler\Reporter\TrendReporter;

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

    private function createResult(
        string $uri = '/test',
        string $scriptFilename = '/var/www/app/public/index.php',
        float $sciScore = 0.1,
        int $responseCode = 200,
    ): ProfileResult {
        return new ProfileResult(
            collectorMetrics: [
                'time' => ['wall_time_ms' => 50.123, 'wall_time_sec' => 0.050123],
                'memory' => ['memory_peak_mb' => 2.5, 'memory_peak_bytes' => 2621440],
                'request' => [
                    'method' => 'GET',
                    'uri' => $uri,
                    'script_filename' => $scriptFilename,
                    'response_code' => $responseCode,
                    'input_bytes' => 0,
                    'output_bytes' => 8192,
                ],
                'config' => [
                    'device_power_watts' => 18.0,
                    'grid_carbon_intensity' => 332.0,
                    'embodied_carbon' => 211000.0,
                    'device_lifetime_hours' => 11680.0,
                    'machine_description' => 'Test machine',
                ],
            ],
            sciMetrics: [
                'energy_kwh' => 0.0000001,
                'operational_carbon_gco2eq' => 0.0000332,
                'embodied_carbon_gco2eq' => 0.0000902,
                'sci_gco2eq' => 0.0001,
                'sci_mgco2eq' => $sciScore,
            ],
            timestamp: '2026-01-01T00:00:00+00:00',
            profileId: 'test123',
        );
    }

    // ── JSON Reporter ──

    public function testJsonReporterCreatesJsonlFile(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new JsonReporter();
        $result = $this->createResult();

        $reporter->report($result, $config);

        $file = $this->outputDir . '/sci-profiler.jsonl';
        $this->assertFileExists($file);

        $decoded = json_decode(trim(file_get_contents($file)), true);
        $this->assertIsArray($decoded);
        $this->assertSame('test123', $decoded['profile_id']);
    }

    public function testJsonReporterIncludesAllFields(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new JsonReporter();
        $result = $this->createResult();

        $reporter->report($result, $config);

        $decoded = json_decode(trim(file_get_contents($this->outputDir . '/sci-profiler.jsonl')), true);

        // Core fields all reporters should expose
        $this->assertArrayHasKey('request.script_filename', $decoded);
        $this->assertArrayHasKey('request.response_code', $decoded);
        $this->assertArrayHasKey('request.input_bytes', $decoded);
        $this->assertArrayHasKey('request.output_bytes', $decoded);
        $this->assertArrayHasKey('memory.memory_peak_mb', $decoded);

        // Config parameters for reproducibility
        $this->assertArrayHasKey('config.device_power_watts', $decoded);
        $this->assertArrayHasKey('config.grid_carbon_intensity', $decoded);
        $this->assertArrayHasKey('config.embodied_carbon', $decoded);
        $this->assertArrayHasKey('config.device_lifetime_hours', $decoded);
        $this->assertArrayHasKey('config.machine_description', $decoded);

        $this->assertEquals(18.0, $decoded['config.device_power_watts']);
        $this->assertEquals(332.0, $decoded['config.grid_carbon_intensity']);
    }

    // ── Log Reporter ──

    public function testLogReporterCreatesLogFile(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new LogReporter();
        $result = $this->createResult();

        $reporter->report($result, $config);

        $file = $this->outputDir . '/sci-profiler.log';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('GET', $content);
        $this->assertStringContainsString('/test', $content);
        $this->assertStringContainsString('mgCO2eq', $content);
    }

    public function testLogReporterIncludesScriptFilenameAndStatus(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new LogReporter();
        $result = $this->createResult(
            uri: '/dashboard',
            scriptFilename: '/var/www/app/public/index.php',
            responseCode: 404,
        );

        $reporter->report($result, $config);

        $content = file_get_contents($this->outputDir . '/sci-profiler.log');
        $this->assertStringContainsString('index.php', $content);
        $this->assertStringContainsString('[404]', $content);
    }

    public function testLogReporterSkipsStatusForCli(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $reporter = new LogReporter();

        $result = new ProfileResult(
            collectorMetrics: [
                'time' => ['wall_time_ms' => 50.0, 'wall_time_sec' => 0.05],
                'memory' => ['memory_peak_mb' => 2.0],
                'request' => ['method' => 'CLI', 'uri' => '/usr/bin/artisan', 'response_code' => 0],
            ],
            sciMetrics: ['sci_mgco2eq' => 0.5],
            timestamp: '2026-01-01T00:00:00+00:00',
            profileId: 'cli-test',
        );

        $reporter->report($result, $config);

        $content = file_get_contents($this->outputDir . '/sci-profiler.log');
        $this->assertStringContainsString('CLI', $content);
        $this->assertStringNotContainsString('[0]', $content);
    }

    // ── HTML Reporter ──

    public function testHtmlReporterCreatesDashboard(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $jsonReporter = new JsonReporter();
        $htmlReporter = new HtmlReporter();
        $result = $this->createResult();

        $jsonReporter->report($result, $config);
        $htmlReporter->report($result, $config);

        $file = $this->outputDir . '/dashboard.html';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('SCI Profiler Dashboard', $content);
        $this->assertStringContainsString('mgCO2eq', $content);
    }

    public function testHtmlReporterShowsConfigParameters(): void
    {
        $config = new Config(
            outputDir: $this->outputDir,
            gridCarbonIntensity: 56.0,
        );
        $jsonReporter = new JsonReporter();
        $htmlReporter = new HtmlReporter();
        $result = $this->createResult();

        $jsonReporter->report($result, $config);
        $htmlReporter->report($result, $config);

        $content = file_get_contents($this->outputDir . '/dashboard.html');
        $this->assertStringContainsString('Measurement Parameters', $content);
        $this->assertStringContainsString('gCO2eq/kWh', $content);
        $this->assertStringContainsString('Device Power', $content);
    }

    public function testHtmlReporterShowsPerScriptGrouping(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $jsonReporter = new JsonReporter();
        $htmlReporter = new HtmlReporter();

        // Write entries for 2 different scripts
        for ($i = 0; $i < 3; $i++) {
            $jsonReporter->report(
                $this->createResult(uri: '/page-a', scriptFilename: '/app/a.php', sciScore: 0.3 + $i * 0.1),
                $config,
            );
            $jsonReporter->report(
                $this->createResult(uri: '/page-b', scriptFilename: '/app/b.php', sciScore: 1.0 + $i * 0.2),
                $config,
            );
        }

        $htmlReporter->report($this->createResult(), $config);

        $content = file_get_contents($this->outputDir . '/dashboard.html');
        $this->assertStringContainsString('Per-Script Summary', $content);
        $this->assertStringContainsString('a.php', $content);
        $this->assertStringContainsString('b.php', $content);
    }

    public function testHtmlReporterHandlesEmptyData(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $htmlReporter = new HtmlReporter();
        $result = $this->createResult();

        // No JSONL file exists
        $htmlReporter->report($result, $config);

        $content = file_get_contents($this->outputDir . '/dashboard.html');
        $this->assertStringContainsString('No profiling data yet', $content);
    }

    // ── Trend Reporter ──

    public function testTrendReporterCreatesTrendFile(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $jsonReporter = new JsonReporter();
        $trendReporter = new TrendReporter();

        // Need at least 2 entries
        $jsonReporter->report($this->createResult(sciScore: 0.5), $config);
        $jsonReporter->report($this->createResult(sciScore: 0.3), $config);

        $trendReporter->report($this->createResult(), $config);

        $file = $this->outputDir . '/sci-trend.txt';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('SCI Trend Report', $content);
    }

    public function testTrendReporterShowsConfigAndMemory(): void
    {
        $config = new Config(
            outputDir: $this->outputDir,
            machineDescription: 'My Dev Machine',
        );
        $jsonReporter = new JsonReporter();
        $trendReporter = new TrendReporter();

        for ($i = 0; $i < 5; $i++) {
            $jsonReporter->report($this->createResult(sciScore: 0.5 + $i * 0.1), $config);
        }

        $trendReporter->report($this->createResult(), $config);

        $content = file_get_contents($this->outputDir . '/sci-trend.txt');

        // Config params visible
        $this->assertStringContainsString('Config:', $content);
        $this->assertStringContainsString('gCO2eq/kWh', $content);
        $this->assertStringContainsString('My Dev Machine', $content);

        // Memory stats visible
        $this->assertStringContainsString('Memory:', $content);
        $this->assertStringContainsString('MB', $content);
    }

    public function testTrendReporterSkipsWithLessThanTwoEntries(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $jsonReporter = new JsonReporter();
        $trendReporter = new TrendReporter();

        $jsonReporter->report($this->createResult(), $config);
        $trendReporter->report($this->createResult(), $config);

        // Only 1 entry — trend file should NOT be created
        $this->assertFileDoesNotExist($this->outputDir . '/sci-trend.txt');
    }
}
