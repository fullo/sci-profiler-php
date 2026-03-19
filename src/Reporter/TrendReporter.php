<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Generates a terminal-friendly trend report showing SCI changes over time.
 *
 * Groups entries by script filename and displays the SCI trajectory
 * (improving, worsening, stable) with ASCII sparklines, memory usage,
 * and the Config parameters used for the measurement.
 *
 * Requires the 'json' reporter to be enabled (reads from sci-profiler.jsonl).
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class TrendReporter implements ReporterInterface
{
    use EnsuresOutputDirectory;
    use ReadsJsonlHistory;

    /** Maximum entries to analyze for trends. */
    private const MAX_HISTORY = 500;

    /** Percentage change threshold to consider a trend stable. */
    private const STABLE_THRESHOLD = 5.0;

    public function report(ProfileResult $result, Config $config): void
    {
        $dir = $config->getOutputDir();
        $this->ensureDirectory($dir);

        $jsonlFile = $dir . '/sci-profiler.jsonl';
        $entries = $this->readJsonlEntries($jsonlFile, self::MAX_HISTORY);

        $report = count($entries) < 2
            ? $this->buildWaitingReport($entries, $config)
            : $this->buildReport($entries, $config);

        file_put_contents($dir . '/sci-trend.txt', $report, LOCK_EX);
    }

    public function getName(): string
    {
        return 'trend';
    }

    /**
     * Generate a placeholder report when there is not enough data for trends.
     *
     * @param array<int, array<string, mixed>> $entries
     */
    private function buildWaitingReport(array $entries, Config $config): string
    {
        $lines = [];
        $lines[] = '╔══════════════════════════════════════════════════════════════════╗';
        $lines[] = '║              SCI Trend Report — ' . gmdate('Y-m-d H:i:s') . ' UTC              ║';
        $lines[] = '╚══════════════════════════════════════════════════════════════════╝';
        $lines[] = '';
        $lines[] = sprintf(
            '  Config: E=%sW  I=%s gCO2eq/kWh  M=%s gCO2eq  Lifetime=%sh',
            $config->getDevicePowerWatts(),
            $config->getGridCarbonIntensity(),
            number_format($config->getEmbodiedCarbon(), 0, '', ','),
            number_format($config->getDeviceLifetimeHours(), 0, '', ','),
        );
        $lines[] = sprintf('  Machine: %s', $config->getMachineDescription());
        $lines[] = '';

        if (count($entries) === 0) {
            $lines[] = '  No profiling data collected yet.';
            $lines[] = '  Run your application with the SCI profiler enabled to start collecting data.';
        } else {
            $entry = $entries[0];
            $lines[] = sprintf(
                '  First measurement recorded: %s %s %s — %.4f mgCO2eq',
                $entry['request.method'] ?? 'CLI',
                $entry['request.script_filename'] ?? $entry['request.uri'] ?? 'unknown',
                $entry['timestamp'] ?? '',
                $entry['sci.sci_mgco2eq'] ?? 0,
            );
            $lines[] = '';
            $lines[] = '  Waiting for more data to compute trends.';
            $lines[] = '  Run the same script again to see the SCI trajectory.';
        }

        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function buildReport(array $entries, Config $config): string
    {
        $groups = [];
        foreach ($entries as $entry) {
            $script = $entry['request.script_filename']
                ?? $entry['request.uri']
                ?? 'unknown';
            $groups[$script][] = $entry;
        }

        $lines = [];
        $lines[] = '╔══════════════════════════════════════════════════════════════════╗';
        $lines[] = '║              SCI Trend Report — ' . gmdate('Y-m-d H:i:s') . ' UTC              ║';
        $lines[] = '╚══════════════════════════════════════════════════════════════════╝';
        $lines[] = '';

        // ── Config parameters ──
        $lines[] = sprintf(
            '  Config: E=%sW  I=%s gCO2eq/kWh  M=%s gCO2eq  Lifetime=%sh',
            $config->getDevicePowerWatts(),
            $config->getGridCarbonIntensity(),
            number_format($config->getEmbodiedCarbon(), 0, '', ','),
            number_format($config->getDeviceLifetimeHours(), 0, '', ','),
        );
        $lines[] = sprintf('  Machine: %s', $config->getMachineDescription());
        $lines[] = '';

        // ── Global summary ──
        $allSci = array_filter(
            array_column($entries, 'sci.sci_mgco2eq'),
            static fn ($v) => $v !== null,
        );

        if (count($allSci) > 0) {
            $lines[] = sprintf(
                '  Total entries: %d | Avg SCI: %.4f mgCO2eq | Min: %.4f | Max: %.4f',
                count($entries),
                array_sum($allSci) / count($allSci),
                min($allSci),
                max($allSci),
            );
            $lines[] = '';
        }

        // ── Per-script trends ──
        foreach ($groups as $script => $scriptEntries) {
            $sciValues = [];
            $memValues = [];
            foreach ($scriptEntries as $entry) {
                $sci = $entry['sci.sci_mgco2eq'] ?? null;
                $mem = $entry['memory.memory_peak_mb'] ?? null;
                if ($sci !== null) {
                    $sciValues[] = (float) $sci;
                }
                if ($mem !== null) {
                    $memValues[] = (float) $mem;
                }
            }

            if (count($sciValues) < 2) {
                continue;
            }

            $shortName = $this->shortenPath((string) $script);
            $count = count($sciValues);
            $first = $sciValues[0];
            $last = $sciValues[$count - 1];
            $avg = array_sum($sciValues) / $count;

            $changePercent = $first > 0 ? (($last - $first) / $first) * 100 : 0;
            $trend = $this->trendIndicator($changePercent);
            $sparkline = $this->sparkline($sciValues);

            $lines[] = sprintf('  %-40s [%d runs]', $shortName, $count);
            $lines[] = sprintf(
                '    SCI: %.4f → %.4f mgCO2eq  %s (%.1f%%)',
                $first,
                $last,
                $trend,
                $changePercent,
            );
            $lines[] = sprintf(
                '    Avg: %.4f | Min: %.4f | Max: %.4f',
                $avg,
                min($sciValues),
                max($sciValues),
            );

            if (count($memValues) > 0) {
                $lines[] = sprintf(
                    '    Memory: avg %.1f MB | peak %.1f MB',
                    array_sum($memValues) / count($memValues),
                    max($memValues),
                );
            }

            $lines[] = sprintf('    Trend: %s', $sparkline);
            $lines[] = '';
        }

        // ── Recent history ──
        $lines[] = '  ── Recent History (last 20) ──';
        $lines[] = '';
        $lines[] = sprintf(
            '  %-20s %-6s %6s %-25s %8s %10s %8s',
            'Timestamp',
            'Method',
            'Status',
            'Script',
            'Time(ms)',
            'SCI(mgCO2)',
            'Mem(MB)',
        );
        $lines[] = '  ' . str_repeat('─', 90);

        $recent = array_slice($entries, -20);
        $lastSciByScript = [];

        foreach ($recent as $entry) {
            $sci = (float) ($entry['sci.sci_mgco2eq'] ?? 0);
            $mem = $entry['memory.memory_peak_mb'] ?? '?';
            $method = $entry['request.method'] ?? 'CLI';
            $status = (int) ($entry['request.response_code'] ?? 0);
            $statusStr = ($method !== 'CLI' && $status > 0) ? (string) $status : '—';

            $script = $entry['request.script_filename']
                ?? $entry['request.uri']
                ?? 'unknown';

            // Delta: compare only to the previous entry of the SAME script
            $delta = '';
            if (isset($lastSciByScript[$script]) && $lastSciByScript[$script] > 0) {
                $change = (($sci - $lastSciByScript[$script]) / $lastSciByScript[$script]) * 100;
                if (abs($change) > self::STABLE_THRESHOLD) {
                    $delta = $change > 0 ? ' ▲' : ' ▼';
                } else {
                    $delta = ' ═';
                }
            }
            $lastSciByScript[$script] = $sci;

            $lines[] = sprintf(
                '  %-20s %-6s %6s %-25s %8.2f %8.4f%s %8s',
                substr($entry['timestamp'] ?? '', 0, 19),
                $method,
                $statusStr,
                $this->shortenPath($script, 25),
                $entry['time.wall_time_ms'] ?? 0,
                $sci,
                $delta,
                $mem,
            );

        }

        $lines[] = '';
        $lines[] = '  Legend: ▲ = SCI increased  ▼ = SCI decreased  ═ = stable (±' . self::STABLE_THRESHOLD . '%)';
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    private function sparkline(array $values): string
    {
        if (count($values) === 0) {
            return '';
        }

        $chars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        if ($range < 0.0001) {
            return str_repeat($chars[3], min(count($values), 40));
        }

        $sparkline = '';
        $step = max(1, (int) ceil(count($values) / 40));
        $numChars = count($chars);

        for ($i = 0; $i < count($values); $i += $step) {
            $normalized = ($values[$i] - $min) / $range;
            $idx = min($numChars - 1, max(0, (int) ($normalized * ($numChars - 1))));
            $sparkline .= $chars[$idx];
        }

        return $sparkline;
    }

    private function trendIndicator(float $changePercent): string
    {
        if (abs($changePercent) < self::STABLE_THRESHOLD) {
            return '═ stable';
        }

        if ($changePercent < -20) {
            return '▼▼ much improved';
        }
        if ($changePercent < 0) {
            return '▼ improved';
        }
        if ($changePercent > 20) {
            return '▲▲ much worse';
        }

        return '▲ worse';
    }

    private function shortenPath(string $path, int $maxLen = 40): string
    {
        $short = basename($path);
        $dir = basename(dirname($path));
        if ($dir !== '.' && $dir !== '') {
            $short = $dir . '/' . $short;
        }

        if (strlen($short) > $maxLen) {
            $short = '…' . substr($short, -(int) ($maxLen - 1));
        }

        return $short;
    }
}
