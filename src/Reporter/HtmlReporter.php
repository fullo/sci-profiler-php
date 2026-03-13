<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Generates a static HTML dashboard with profiling history.
 *
 * Reads existing JSON lines data and produces a self-contained HTML page.
 * The dashboard is regenerated on each request for simplicity.
 */
final class HtmlReporter implements ReporterInterface
{
    private const MAX_ENTRIES = 200;

    public function report(ProfileResult $result, Config $config): void
    {
        $dir = $config->getOutputDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonlFile = $dir . '/sci-profiler.jsonl';
        $entries = $this->readEntries($jsonlFile);

        $html = $this->render($entries, $config);
        file_put_contents($dir . '/dashboard.html', $html, LOCK_EX);
    }

    public function getName(): string
    {
        return 'html';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readEntries(string $jsonlFile): array
    {
        if (!is_file($jsonlFile)) {
            return [];
        }

        $lines = file($jsonlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        foreach (array_slice($lines, -self::MAX_ENTRIES) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function render(array $entries, Config $config): string
    {
        $count = count($entries);
        $totalSci = 0.0;
        $totalTime = 0.0;
        $rows = '';

        foreach (array_reverse($entries) as $entry) {
            $sci = (float) ($entry['sci.sci_mgco2eq'] ?? 0);
            $time = (float) ($entry['time.wall_time_ms'] ?? 0);
            $totalSci += $sci;
            $totalTime += $time;

            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%.2f ms</td><td>%.4f</td><td>%s MB</td></tr>',
                htmlspecialchars((string) ($entry['timestamp'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($entry['request.method'] ?? 'CLI'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($entry['request.uri'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                $time,
                $sci,
                htmlspecialchars((string) ($entry['memory.memory_peak_mb'] ?? '?'), ENT_QUOTES, 'UTF-8'),
            );
        }

        $avgSci = $count > 0 ? $totalSci / $count : 0;
        $avgTime = $count > 0 ? $totalTime / $count : 0;
        $generated = gmdate('c');
        $machine = htmlspecialchars($config->getMachineDescription(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>SCI Profiler Dashboard</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; padding: 2rem; }
                h1 { color: #2d6a4f; margin-bottom: 0.5rem; }
                .meta { color: #666; font-size: 0.9rem; margin-bottom: 1.5rem; }
                .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
                .card { background: #fff; border-radius: 8px; padding: 1.2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .card .label { font-size: 0.8rem; color: #666; text-transform: uppercase; }
                .card .value { font-size: 1.8rem; font-weight: 700; color: #2d6a4f; }
                .card .unit { font-size: 0.9rem; color: #888; }
                table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                th { background: #2d6a4f; color: #fff; padding: 0.8rem; text-align: left; font-size: 0.85rem; }
                td { padding: 0.6rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.85rem; }
                tr:hover td { background: #f0faf4; }
            </style>
        </head>
        <body>
            <h1>SCI Profiler Dashboard</h1>
            <p class="meta">Machine: {$machine} | Generated: {$generated}</p>
            <div class="cards">
                <div class="card">
                    <div class="label">Total Requests</div>
                    <div class="value">{$count}</div>
                </div>
                <div class="card">
                    <div class="label">Avg SCI Score</div>
                    <div class="value">{$avgSci}</div>
                    <div class="unit">mgCO2eq/request</div>
                </div>
                <div class="card">
                    <div class="label">Total Emissions</div>
                    <div class="value">{$totalSci}</div>
                    <div class="unit">mgCO2eq</div>
                </div>
                <div class="card">
                    <div class="label">Avg Response Time</div>
                    <div class="value">{$avgTime}</div>
                    <div class="unit">ms</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Method</th>
                        <th>URI</th>
                        <th>Time</th>
                        <th>SCI (mgCO2eq)</th>
                        <th>Peak Memory</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </body>
        </html>
        HTML;
    }
}
