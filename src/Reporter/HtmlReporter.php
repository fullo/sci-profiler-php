<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Generates a static HTML dashboard with profiling history.
 *
 * Features:
 * - Summary cards with totals and averages
 * - SVG timeline chart of SCI over the last 50 entries
 * - Per-script grouping with inline SVG sparklines and last-vs-previous comparison
 * - Config parameters used (I, E, M) for reproducibility
 * - Chronological detail table with delta indicators
 *
 * Requires the 'json' reporter to be enabled (reads from sci-profiler.jsonl).
 * Zero external dependencies — all CSS and SVG are inline.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class HtmlReporter implements ReporterInterface
{
    use EnsuresOutputDirectory;
    use ReadsJsonlHistory;

    private const MAX_ENTRIES = 200;

    public function report(ProfileResult $result, Config $config): void
    {
        $dir = $config->getOutputDir();
        $this->ensureDirectory($dir);

        $jsonlFile = $dir . '/sci-profiler.jsonl';
        $entries = $this->readJsonlEntries($jsonlFile, self::MAX_ENTRIES);

        $html = $this->render($entries, $config);
        file_put_contents($dir . '/dashboard.html', $html, LOCK_EX);
    }

    public function getName(): string
    {
        return 'html';
    }

    private function esc(mixed $value, string $default = '-'): string
    {
        $str = (string) ($value ?? $default);
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function render(array $entries, Config $config): string
    {
        $count = count($entries);
        if ($count === 0) {
            return $this->renderEmpty($config);
        }

        // ── Summary ──
        $totalSci = 0.0;
        $totalTime = 0.0;
        foreach ($entries as $entry) {
            $totalSci += (float) ($entry['sci.sci_mgco2eq'] ?? 0);
            $totalTime += (float) ($entry['time.wall_time_ms'] ?? 0);
        }
        $avgSci = $totalSci / $count;
        $avgTime = $totalTime / $count;

        // ── Timeline chart (last 50 entries) ──
        $timelineHtml = $this->renderTimelineChart($entries);

        // ── Per-script grouping ──
        $groups = [];
        foreach ($entries as $entry) {
            $key = $entry['request.script_filename']
                ?? $entry['request.uri']
                ?? 'unknown';
            $groups[$key][] = $entry;
        }

        $scriptRows = '';
        foreach ($groups as $script => $scriptEntries) {
            $sciValues = array_map(
                static fn ($e) => (float) ($e['sci.sci_mgco2eq'] ?? 0),
                $scriptEntries,
            );
            $timeValues = array_map(
                static fn ($e) => (float) ($e['time.wall_time_ms'] ?? 0),
                $scriptEntries,
            );
            $memValues = array_map(
                static fn ($e) => (float) ($e['memory.memory_peak_mb'] ?? 0),
                $scriptEntries,
            );

            $n = count($sciValues);
            $avgScriptSci = array_sum($sciValues) / $n;
            $avgScriptTime = array_sum($timeValues) / $n;
            $maxMem = max($memValues);

            // Delta: compare last half vs first half
            $delta = '';
            $deltaClass = '';
            if ($n >= 4) {
                $mid = (int) ($n / 2);
                $firstHalf = array_slice($sciValues, 0, $mid);
                $secondHalf = array_slice($sciValues, $mid);
                $avgFirst = array_sum($firstHalf) / count($firstHalf);
                $avgSecond = array_sum($secondHalf) / count($secondHalf);
                if ($avgFirst > 0) {
                    $changePct = (($avgSecond - $avgFirst) / $avgFirst) * 100;
                    if (abs($changePct) >= 5) {
                        $arrow = $changePct > 0 ? '▲' : '▼';
                        $delta = sprintf('%s %.1f%%', $arrow, $changePct);
                        $deltaClass = $changePct > 0 ? 'worse' : 'better';
                    } else {
                        $delta = '═ stable';
                        $deltaClass = 'stable';
                    }
                }
            }

            $shortName = $this->shortenScript((string) $script);
            $deltaHtml = $delta !== ''
                ? sprintf('<span class="delta %s">%s</span>', $this->esc($deltaClass), $this->esc($delta))
                : '';

            // Sparkline SVG
            $sparklineSvg = $this->renderSparklineSvg($sciValues);

            // Last vs Previous comparison
            $lastVsPrev = $this->renderLastVsPrev($sciValues);

            $scriptRows .= sprintf(
                '<tr><td>%s</td><td class="num">%d</td><td class="num">%.4f</td>'
                . '<td class="num">%.2f</td><td class="num">%.1f</td>'
                . '<td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->esc($shortName),
                $n,
                $avgScriptSci,
                $avgScriptTime,
                $maxMem,
                $sparklineSvg,
                $lastVsPrev,
                $deltaHtml,
            );
        }

        // ── Detail table (newest first, last 100) ──
        // Pre-compute deltas in chronological order BEFORE reversing,
        // so each entry's delta compares to its chronological predecessor.
        $detailRows = '';
        $recentEntries = array_slice($entries, -100);

        $deltas = [];
        $prevSci = null;
        foreach ($recentEntries as $i => $entry) {
            $sci = (float) ($entry['sci.sci_mgco2eq'] ?? 0);
            if ($prevSci !== null && $prevSci > 0) {
                $change = (($sci - $prevSci) / $prevSci) * 100;
                if ($change > 5) {
                    $deltas[$i] = ' <span class="delta worse">▲</span>';
                } elseif ($change < -5) {
                    $deltas[$i] = ' <span class="delta better">▼</span>';
                }
            }
            $prevSci = $sci;
        }

        foreach (array_reverse($recentEntries, true) as $i => $entry) {
            $sci = (float) ($entry['sci.sci_mgco2eq'] ?? 0);
            $time = (float) ($entry['time.wall_time_ms'] ?? 0);
            $method = $entry['request.method'] ?? 'CLI';
            $uri = $entry['request.uri'] ?? '-';
            $script = $entry['request.script_filename'] ?? null;
            $status = (int) ($entry['request.response_code'] ?? 0);
            $peak = $entry['memory.memory_peak_mb'] ?? '?';

            $target = ($script !== null && $script !== $uri)
                ? sprintf('%s<br><small>%s</small>', $this->esc($uri), $this->esc(basename($script)))
                : $this->esc($uri);

            $statusBadge = '';
            if ($method !== 'CLI' && $status > 0) {
                $statusClass = $status >= 400 ? 'err' : ($status >= 300 ? 'redir' : 'ok');
                $statusBadge = sprintf(' <span class="badge %s">%d</span>', $statusClass, $status);
            }

            $deltaMark = $deltas[$i] ?? '';

            $detailRows .= sprintf(
                '<tr><td>%s</td><td>%s%s</td><td>%s</td><td class="num">%.2f</td>'
                . '<td class="num">%.4f%s</td><td class="num">%s</td></tr>',
                $this->esc(substr($entry['timestamp'] ?? '', 0, 19)),
                $this->esc($method),
                $statusBadge,
                $target,
                $time,
                $sci,
                $deltaMark,
                $this->esc($peak),
            );
        }

        // ── Config section ──
        $lastEntry = end($entries);
        $configHtml = $this->renderConfigSection($lastEntry, $config);

        $generated = gmdate('c');
        $machine = $this->esc($config->getMachineDescription());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCI Profiler Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; padding: 2rem; max-width: 1200px; margin: 0 auto; }
        h1 { color: #2d6a4f; margin-bottom: 0.3rem; }
        h2 { color: #2d6a4f; margin: 1.5rem 0 0.8rem; font-size: 1.1rem; }
        .meta { color: #666; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .card { background: #fff; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .card .label { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 0.03em; }
        .card .value { font-size: 1.6rem; font-weight: 700; color: #2d6a4f; }
        .card .unit { font-size: 0.8rem; color: #888; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 1.5rem; }
        th { background: #2d6a4f; color: #fff; padding: 0.6rem 0.8rem; text-align: left; font-size: 0.8rem; }
        td { padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.82rem; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr:hover td { background: #f0faf4; }
        td small { color: #999; font-size: 0.75rem; }
        .badge { font-size: 0.7rem; padding: 1px 5px; border-radius: 3px; font-weight: 600; }
        .badge.ok { background: #d4edda; color: #155724; }
        .badge.redir { background: #fff3cd; color: #856404; }
        .badge.err { background: #f8d7da; color: #721c24; }
        .delta { font-size: 0.8rem; font-weight: 600; }
        .delta.better { color: #28a745; }
        .delta.worse { color: #dc3545; }
        .delta.stable { color: #6c757d; }
        .config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; background: #fff; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 1.5rem; }
        .config-item .ck { font-size: 0.75rem; color: #888; text-transform: uppercase; }
        .config-item .cv { font-size: 0.95rem; color: #333; font-weight: 500; }
        .timeline-box { background: #fff; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 1.5rem; }
        .timeline-box svg { width: 100%; height: auto; }
        .sparkline { vertical-align: middle; }
        .lastprev { font-size: 0.78rem; white-space: nowrap; }
        .lastprev .arrow { font-weight: 700; }
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
            <div class="unit">mgCO2eq / request</div>
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

    {$timelineHtml}

    {$configHtml}

    <h2>Per-Script Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Script</th>
                <th>Runs</th>
                <th>Avg SCI (mgCO2eq)</th>
                <th>Avg Time (ms)</th>
                <th>Peak Mem (MB)</th>
                <th>Sparkline</th>
                <th>Last vs Prev</th>
                <th>Trend</th>
            </tr>
        </thead>
        <tbody>{$scriptRows}</tbody>
    </table>

    <h2>Recent Requests</h2>
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Method</th>
                <th>URI</th>
                <th>Time (ms)</th>
                <th>SCI (mgCO2eq)</th>
                <th>Peak (MB)</th>
            </tr>
        </thead>
        <tbody>{$detailRows}</tbody>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Render an SVG timeline chart of SCI values over the last 50 entries.
     *
     * @param array<int, array<string, mixed>> $entries
     */
    private function renderTimelineChart(array $entries): string
    {
        $recent = array_slice($entries, -50);
        $values = array_map(
            static fn ($e) => (float) ($e['sci.sci_mgco2eq'] ?? 0),
            $recent,
        );

        $n = count($values);
        if ($n < 2) {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        if ($range < 0.0001) {
            $range = 1.0;
        }

        $w = 800;
        $h = 150;
        $padX = 60;
        $padY = 20;
        $plotW = $w - $padX - 10;
        $plotH = $h - $padY * 2;

        // Build polyline points
        $points = [];
        $polyPoints = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $padX + ($i / max(1, $n - 1)) * $plotW;
            $y = $padY + (1 - ($values[$i] - $min) / $range) * $plotH;
            $points[] = [$x, $y];
            $polyPoints[] = sprintf('%.1f,%.1f', $x, $y);
        }

        $polyline = implode(' ', $polyPoints);

        // Area fill polygon (polyline + bottom corners)
        $areaPoints = $polyline
            . sprintf(' %.1f,%.1f %.1f,%.1f', $padX + $plotW, $padY + $plotH, $padX, $padY + $plotH);

        // Grid lines at 25%, 50%, 75%
        $gridLines = '';
        foreach ([0.25, 0.5, 0.75] as $pct) {
            $gy = $padY + (1 - $pct) * $plotH;
            $gridLines .= sprintf(
                '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#eee" stroke-width="1"/>',
                $padX,
                $gy,
                $padX + $plotW,
                $gy,
            );
        }

        // Y-axis labels
        $minLabel = sprintf('%.4f', $min);
        $maxLabel = sprintf('%.4f', $max);

        // X-axis labels (first and last timestamp)
        $firstTs = substr($recent[0]['timestamp'] ?? '', 0, 16);
        $lastTs = substr($recent[$n - 1]['timestamp'] ?? '', 0, 16);

        return sprintf(
            '<h2>SCI Timeline (Last %d)</h2>'
            . '<div class="timeline-box">'
            . '<svg viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg">'
            . '%s'
            . '<polygon points="%s" fill="rgba(45,106,79,0.1)" />'
            . '<polyline points="%s" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linejoin="round" />'
            . '<text x="%d" y="%.1f" font-size="10" fill="#888" text-anchor="end">%s</text>'
            . '<text x="%d" y="%.1f" font-size="10" fill="#888" text-anchor="end">%s</text>'
            . '<text x="%d" y="%d" font-size="9" fill="#aaa">%s</text>'
            . '<text x="%d" y="%d" font-size="9" fill="#aaa" text-anchor="end">%s</text>'
            . '</svg></div>',
            $n,
            $w,
            $h,
            $gridLines,
            $areaPoints,
            $polyline,
            $padX - 5,
            $padY + 4,
            $this->esc($maxLabel),
            $padX - 5,
            $padY + $plotH + 4,
            $this->esc($minLabel),
            $padX,
            $h - 2,
            $this->esc($firstTs),
            $padX + $plotW,
            $h - 2,
            $this->esc($lastTs),
        );
    }

    /**
     * Render an inline SVG sparkline for a series of SCI values.
     *
     * @param float[] $values
     */
    private function renderSparklineSvg(array $values): string
    {
        $n = count($values);
        if ($n < 2) {
            return '<span class="sparkline" style="color:#999">—</span>';
        }

        $w = 120;
        $h = 24;
        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $x = ($i / max(1, $n - 1)) * $w;
            $y = $range > 0.0001
                ? $h - 2 - (($values[$i] - $min) / $range) * ($h - 4)
                : $h / 2;
            $points[] = sprintf('%.1f,%.1f', $x, $y);
        }

        return sprintf(
            '<svg class="sparkline" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '<polyline points="%s" fill="none" stroke="#2d6a4f" stroke-width="1.5" stroke-linejoin="round" />'
            . '</svg>',
            $w,
            $h,
            $w,
            $h,
            implode(' ', $points),
        );
    }

    /**
     * Render a "last vs previous" comparison for a script.
     *
     * @param float[] $sciValues
     */
    private function renderLastVsPrev(array $sciValues): string
    {
        $n = count($sciValues);
        if ($n < 2) {
            return '<span class="lastprev" style="color:#999">—</span>';
        }

        $prev = $sciValues[$n - 2];
        $last = $sciValues[$n - 1];
        $changePct = $prev > 0 ? (($last - $prev) / $prev) * 100 : 0.0;

        if (abs($changePct) < 5) {
            $color = '#6c757d';
            $arrow = '═';
        } elseif ($changePct < 0) {
            $color = '#28a745';
            $arrow = '▼';
        } else {
            $color = '#dc3545';
            $arrow = '▲';
        }

        return sprintf(
            '<span class="lastprev">%.4f → %.4f <span class="arrow" style="color:%s">%s %.1f%%</span></span>',
            $prev,
            $last,
            $color,
            $arrow,
            $changePct,
        );
    }

    /**
     * Render the Config parameters section.
     *
     * @param array<string, mixed>|false $lastEntry
     */
    private function renderConfigSection(array|false $lastEntry, Config $config): string
    {
        $power = $lastEntry['config.device_power_watts']
            ?? $config->getDevicePowerWatts();
        $intensity = $lastEntry['config.grid_carbon_intensity']
            ?? $config->getGridCarbonIntensity();
        $embodied = $lastEntry['config.embodied_carbon']
            ?? $config->getEmbodiedCarbon();
        $lifetime = $lastEntry['config.device_lifetime_hours']
            ?? $config->getDeviceLifetimeHours();

        return sprintf(
            '<h2>Measurement Parameters</h2>'
            . '<div class="config-grid">'
            . '<div class="config-item"><div class="ck">Device Power (E)</div><div class="cv">%s W</div></div>'
            . '<div class="config-item"><div class="ck">Grid Carbon Intensity (I)</div><div class="cv">%s gCO2eq/kWh</div></div>'
            . '<div class="config-item"><div class="ck">Embodied Carbon (M)</div><div class="cv">%s gCO2eq</div></div>'
            . '<div class="config-item"><div class="ck">Device Lifetime</div><div class="cv">%s hours</div></div>'
            . '</div>',
            $this->esc($power),
            $this->esc($intensity),
            $this->esc(number_format((float) $embodied, 0, '', ',')),
            $this->esc(number_format((float) $lifetime, 0, '', ',')),
        );
    }

    /**
     * Shorten a script path for display.
     */
    private function shortenScript(string $path): string
    {
        $base = basename($path);
        $dir = basename(dirname($path));
        if ($dir !== '.' && $dir !== '') {
            return $dir . '/' . $base;
        }
        return $base;
    }

    /**
     * Render an empty dashboard when no data is available.
     */
    private function renderEmpty(Config $config): string
    {
        $machine = $this->esc($config->getMachineDescription());
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SCI Profiler Dashboard</title>
    <style>body{font-family:sans-serif;padding:2rem;color:#666;text-align:center}h1{color:#2d6a4f}</style>
</head>
<body>
    <h1>SCI Profiler Dashboard</h1>
    <p>No profiling data yet. Machine: {$machine}</p>
    <p>Run some requests with the profiler enabled to see results here.</p>
</body>
</html>
HTML;
    }
}
