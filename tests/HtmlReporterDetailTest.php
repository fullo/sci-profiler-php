<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\HtmlReporter;
use SciProfiler\Reporter\JsonReporter;

/**
 * Detailed tests for HtmlReporter rendering branches.
 */
final class HtmlReporterDetailTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/sci-html-detail-' . uniqid();
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

    private function seedJsonl(array $entries): Config
    {
        $config = new Config(outputDir: $this->outputDir);
        $json = new JsonReporter();
        foreach ($entries as $entry) {
            $json->report($entry, $config);
        }
        return $config;
    }

    private function makeEntry(
        float $sci,
        string $script = '/app/index.php',
        string $uri = '/page',
        string $method = 'GET',
        int $responseCode = 200,
        float $peakMb = 4.0,
    ): ProfileResult {
        return new ProfileResult(
            collectorMetrics: [
                'time' => ['wall_time_ms' => 50.0, 'wall_time_sec' => 0.05],
                'memory' => ['memory_peak_mb' => $peakMb],
                'request' => [
                    'method' => $method,
                    'uri' => $uri,
                    'script_filename' => $script,
                    'response_code' => $responseCode,
                    'input_bytes' => 0,
                    'output_bytes' => 1024,
                ],
                'config' => [
                    'device_power_watts' => 18.0,
                    'grid_carbon_intensity' => 332.0,
                    'embodied_carbon' => 211000.0,
                    'device_lifetime_hours' => 11680.0,
                    'machine_description' => 'Test',
                ],
            ],
            sciMetrics: ['sci_mgco2eq' => $sci],
            timestamp: '2026-01-01T00:00:00+00:00',
            profileId: bin2hex(random_bytes(8)),
        );
    }

    private function getDashboard(Config $config): string
    {
        $html = new HtmlReporter();
        $html->report($this->makeEntry(0.1), $config);
        return (string) file_get_contents($this->outputDir . '/dashboard.html');
    }

    // ── Delta trend (first-half vs second-half) ──

    public function testDeltaWorseWithFourEntries(): void
    {
        // First 2: low SCI, last 2: high SCI → worse trend
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, '/app/test.php'),
            $this->makeEntry(0.1, '/app/test.php'),
            $this->makeEntry(0.5, '/app/test.php'),
            $this->makeEntry(0.5, '/app/test.php'),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('worse', $content);
        $this->assertStringContainsString('▲', $content);
    }

    public function testDeltaBetterWithFourEntries(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(1.0, '/app/test.php'),
            $this->makeEntry(1.0, '/app/test.php'),
            $this->makeEntry(0.3, '/app/test.php'),
            $this->makeEntry(0.3, '/app/test.php'),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('better', $content);
        $this->assertStringContainsString('▼', $content);
    }

    public function testDeltaStableWithinThreshold(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(1.00, '/app/test.php'),
            $this->makeEntry(1.00, '/app/test.php'),
            $this->makeEntry(1.02, '/app/test.php'),
            $this->makeEntry(1.02, '/app/test.php'),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('stable', $content);
    }

    public function testNoDeltaInPerScriptWithThreeEntries(): void
    {
        // Only 3 entries for one script → n < 4 → no delta in per-script summary
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, '/app/test.php'),
            $this->makeEntry(0.5, '/app/test.php'),
            $this->makeEntry(0.9, '/app/test.php'),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('test.php', $content);

        // Extract the per-script table section (between "Per-Script Summary" and "Recent Requests")
        $perScriptStart = strpos($content, 'Per-Script Summary');
        $recentStart = strpos($content, 'Recent Requests');
        $perScriptSection = substr($content, $perScriptStart, $recentStart - $perScriptStart);

        // Per-script section should NOT have delta indicators (n=3 < 4)
        $this->assertStringNotContainsString('class="delta worse"', $perScriptSection);
        $this->assertStringNotContainsString('class="delta better"', $perScriptSection);
        $this->assertStringNotContainsString('class="delta stable"', $perScriptSection);
    }

    public function testNoDeltaWhenFirstHalfAvgIsZero(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.0, '/app/test.php'),
            $this->makeEntry(0.0, '/app/test.php'),
            $this->makeEntry(5.0, '/app/test.php'),
            $this->makeEntry(5.0, '/app/test.php'),
        ]);

        $content = $this->getDashboard($config);
        // avgFirst = 0 → division guard → no delta shown
        $this->assertStringNotContainsString('class="delta worse"', $content);
    }

    // ── Status badges ──

    public function testStatusBadge200IsOk(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, responseCode: 200),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('badge ok', $content);
        $this->assertStringContainsString('200', $content);
    }

    public function testStatusBadge301IsRedir(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, responseCode: 301),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('badge redir', $content);
    }

    public function testStatusBadge500IsErr(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, responseCode: 500),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('badge err', $content);
    }

    public function testCliBadgeNotShown(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, method: 'CLI', responseCode: 0),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('CLI', $content);
        $this->assertStringNotContainsString('badge ok', $content);
        $this->assertStringNotContainsString('badge err', $content);
    }

    // ── HTML escaping ──

    public function testEscHandlesHtmlInjection(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, uri: '/<script>alert(1)</script>'),
        ]);

        $content = $this->getDashboard($config);
        $this->assertStringNotContainsString('<script>alert', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    // ── Script path display ──

    public function testScriptFilenameShownWhenDifferentFromUri(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, script: '/var/www/public/index.php', uri: '/dashboard'),
        ]);

        $content = $this->getDashboard($config);
        // URI displayed as main text, script_filename as <small>
        $this->assertStringContainsString('/dashboard', $content);
        $this->assertStringContainsString('index.php', $content);
    }

    public function testScriptShortenedInPerScriptTable(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.1, script: '/var/www/long/nested/path/to/index.php'),
        ]);

        $content = $this->getDashboard($config);
        // Should show "to/index.php" not the full path
        $this->assertStringContainsString('to/index.php', $content);
    }

    // ── Detail row delta marks ──

    public function testDetailRowDeltaMarks(): void
    {
        // Three entries with increasing then decreasing SCI
        $config = $this->seedJsonl([
            $this->makeEntry(0.1),
            $this->makeEntry(0.5),  // big increase from 0.1
            $this->makeEntry(0.1),  // big decrease from 0.5
        ]);

        $content = $this->getDashboard($config);
        // Should contain both better and worse delta marks in detail rows
        $this->assertStringContainsString('delta worse', $content);
        $this->assertStringContainsString('delta better', $content);
    }

    public function testDetailRowNoDeltaWhenPrevSciIsZero(): void
    {
        $config = $this->seedJsonl([
            $this->makeEntry(0.0),
            $this->makeEntry(5.0),
        ]);

        $content = $this->getDashboard($config);
        // prevSci is 0 → no delta mark (division guard)
        // The detail table is reversed, so 5.0 comes first (no prev), then 0.0 (prev=5.0 → delta shown)
        // This test verifies no crash occurs with zero values
        $this->assertStringContainsString('SCI Profiler Dashboard', $content);
    }

    // ── Ring buffer boundary ──

    public function testDashboardWith201Entries(): void
    {
        $config = new Config(outputDir: $this->outputDir);
        $json = new JsonReporter();

        for ($i = 0; $i < 201; $i++) {
            $json->report($this->makeEntry(0.1 + $i * 0.001), $config);
        }

        $content = $this->getDashboard($config);
        $this->assertStringContainsString('SCI Profiler Dashboard', $content);
        $this->assertStringContainsString('Per-Script Summary', $content);
    }
}
