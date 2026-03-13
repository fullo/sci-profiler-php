<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Writes profiling results as JSON lines (one JSON object per line).
 *
 * Output is suitable for CI/CD pipelines and automated analysis.
 */
final class JsonReporter implements ReporterInterface
{
    public function report(ProfileResult $result, Config $config): void
    {
        $dir = $config->getOutputDir();
        $this->ensureDirectory($dir);

        $file = $dir . '/sci-profiler.jsonl';
        $line = json_encode($result->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public function getName(): string
    {
        return 'json';
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
