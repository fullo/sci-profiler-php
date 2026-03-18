<?php

declare(strict_types=1);

namespace SciProfiler\Collector;

/**
 * Interface for data collectors.
 *
 * Each collector gathers a specific type of metric
 * during the request lifecycle.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
interface CollectorInterface
{
    /**
     * Start collecting data.
     *
     * Called at the very beginning of the request.
     */
    public function start(): void;

    /**
     * Stop collecting and finalize data.
     *
     * Called at request shutdown.
     */
    public function stop(): void;

    /**
     * Return collected metrics as an associative array.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array;

    /**
     * Return a unique name for this collector.
     */
    public function getName(): string;
}
