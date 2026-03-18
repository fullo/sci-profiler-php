<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

/**
 * Shared JSONL reading logic for reporters that need historical data.
 *
 * Uses a ring buffer to efficiently read only the last N entries
 * without loading the entire file into memory.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
trait ReadsJsonlHistory
{
    /**
     * Read the last $maxEntries from a JSONL file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readJsonlEntries(string $jsonlFile, int $maxEntries): array
    {
        $handle = @fopen($jsonlFile, 'r');
        if ($handle === false) {
            return [];
        }

        $ring = [];
        $pos = 0;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $ring[$pos % $maxEntries] = $line;
            $pos++;
        }
        fclose($handle);

        if ($pos === 0) {
            return [];
        }

        $entries = [];
        $total = min($pos, $maxEntries);
        $start = $pos <= $maxEntries ? 0 : $pos % $maxEntries;

        for ($i = 0; $i < $total; $i++) {
            $idx = ($start + $i) % $maxEntries;
            $decoded = json_decode($ring[$idx], true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }
}
