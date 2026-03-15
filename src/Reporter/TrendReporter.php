<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Generates a terminal-friendly trend report showing SCI changes over time.
 *
 * Reads the JSONL history and groups entries by script filename, displaying
 * the SCI trajectory (improving, worsening, stable) with ASCII sparklines.
 *
 * Requires the 'json' reporter to be enabled (reads from sci-profiler.jsonl).
 */
final class TrendReporter implements ReporterInterface
{
    use EnsuresOutputDirectory;

    /** Maximum entries to analyze for trends. */
    private const MAX_HISTORY = 500;

    /** Percentage change threshold to consider a trend stable. */
    private const STABLE_THRESHOLD = 5.0;

    public function report(ProfileResult $result, Config $config): void
    {
        $dir = $config->getOutputDir();
        $this->ensureDirectory($dir);

        $jsonlFile = $dir . '/sci-profiler.jsonl';
        $entries = $this->readEntries($jsonlFile);

        if (count($entries) < 2) {
            return; // Need at least 2 entries for a trend
        }

        $report = $this->buildReport($entries);
        file_put_contents($dir . '/sci-trend.txt', $report, LOCK_EX);
    }

    public function getName(): string
    {
        return 'trend';
    }

    /**
     * Read entries from the JSONL file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readEntries(string $jsonlFile): array
    {
        $handle = @fopen($jsonlFile, 'r');
        if ($handle === false) {
            return [];
        }

        $ring = [];
        $pos = 0;
        $size = self::MAX_HISTORY;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $ring[$pos % $size] = $line;
            $pos++;
        }
        fclose($handle);

        if ($pos === 0) {
            return [];
        }

        $entries = [];
        $total = min($pos, $size);
        $start = $pos <= $size ? 0 : $pos % $size;

        for ($i = 0; $i < $total; $i++) {
            $idx = ($start + $i) % $size;
            $decoded = json_decode($ring[$idx], true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * Build the trend report text.
     */
    private function buildReport(array $entries): string
    {
        // Group entries by script filename
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

        // Global summary
        $allSci = array_column($entries, 'sci.sci_mgco2eq');
        $allSci = array_filter($allSci, static fn ($v) => $v !== null);

        if (count($allSci) > 0) {
            $avg = array_sum($allSci) / count($allSci);
            $min = min($allSci);
            $max = max($allSci);
            $lines[] = sprintf(
                '  Total entries: %d | Avg SCI: %.4f mgCO2eq | Min: %.4f | Max: %.4f',
                count($entries),
                $avg,
                $min,
                $max,
            );
            $lines[] = '';
        }

        // Per-script trends
        foreach ($groups as $script => $scriptEntries) {
            $sciValues = [];
            foreach ($scriptEntries as $entry) {
                $sci = $entry['sci.sci_mgco2eq'] ?? null;
                if ($sci !== null) {
                    $sciValues[] = (float) $sci;
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
            $min = min($sciValues);
            $max = max($sciValues);

            // Calculate trend
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
                $min,
                $max,
            );
            $lines[] = sprintf('    Trend: %s', $sparkline);
            $lines[] = '';
        }

        // Timeline: last 20 entries chronologically
        $lines[] = '  ── Recent History (last 20) ──';
        $lines[] = '';
        $lines[] = sprintf(
            '  %-20s %-6s %-30s %10s %12s',
            'Timestamp',
            'Method',
            'Script',
            'Time (ms)',
            'SCI (mgCO2)',
        );
        $lines[] = '  ' . str_repeat('─', 82);

        $recent = array_slice($entries, -20);
        $prevSci = null;

        foreach ($recent as $entry) {
            $sci = (float) ($entry['sci.sci_mgco2eq'] ?? 0);
            $delta = '';

            if ($prevSci !== null && $prevSci > 0) {
                $change = (($sci - $prevSci) / $prevSci) * 100;
                if (abs($change) > self::STABLE_THRESHOLD) {
                    $delta = $change > 0 ? ' ▲' : ' ▼';
                } else {
                    $delta = ' ═';
                }
            }

            $script = $entry['request.script_filename']
                ?? $entry['request.uri']
                ?? 'unknown';

            $lines[] = sprintf(
                '  %-20s %-6s %-30s %10.2f %10.4f%s',
                substr($entry['timestamp'] ?? '', 0, 19),
                $entry['request.method'] ?? 'CLI',
                $this->shortenPath($script, 30),
                $entry['time.wall_time_ms'] ?? 0,
                $sci,
                $delta,
            );

            $prevSci = $sci;
        }

        $lines[] = '';
        $lines[] = '  Legend: ▲ = SCI increased  ▼ = SCI decreased  ═ = stable (±' . self::STABLE_THRESHOLD . '%)';
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generate an ASCII sparkline from SCI values.
     */
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
        // Sample up to 40 points evenly
        $step = max(1, (int) ceil(count($values) / 40));

        for ($i = 0; $i < count($values); $i += $step) {
            $normalized = ($values[$i] - $min) / $range;
            $idx = (int) ($normalized * (count($chars) - 1));
            $sparkline .= $chars[max(0, min(count($chars) - 1, $idx))];
        }

        return $sparkline;
    }

    /**
     * Return a trend indicator based on percentage change.
     */
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

    /**
     * Shorten a file path to a readable form.
     */
    private function shortenPath(string $path, int $maxLen = 40): string
    {
        // Strip common prefixes
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
