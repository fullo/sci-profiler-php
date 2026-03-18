<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

/**
 * Shared directory creation logic for reporters.
 *
 * Uses @mkdir() to avoid TOCTOU race conditions (checking is_dir()
 * before mkdir() is unsafe under concurrent requests).
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
trait EnsuresOutputDirectory
{
    /**
     * Create the output directory if it does not exist.
     *
     * Suppresses warnings: mkdir() returns false if the directory already
     * exists, which is the expected case on most requests.
     */
    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
