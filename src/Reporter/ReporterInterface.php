<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Interface for result reporters.
 *
 * Reporters persist or display profiling results.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
interface ReporterInterface
{
    /**
     * Report a single profiling result.
     */
    public function report(ProfileResult $result, Config $config): void;

    /**
     * Return a unique name for this reporter.
     */
    public function getName(): string;
}
