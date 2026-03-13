<?php

declare(strict_types=1);

namespace SciProfiler\Collector;

/**
 * Collects memory usage metrics.
 *
 * Tracks initial memory, peak memory, and delta during the request.
 */
final class MemoryCollector implements CollectorInterface
{
    private int $startMemory = 0;
    private int $stopMemory = 0;
    private int $peakMemory = 0;

    public function start(): void
    {
        $this->startMemory = memory_get_usage(true);
    }

    public function stop(): void
    {
        $this->stopMemory = memory_get_usage(true);
        $this->peakMemory = memory_get_peak_usage(true);
    }

    public function getMetrics(): array
    {
        return [
            'memory_start_bytes' => $this->startMemory,
            'memory_end_bytes' => $this->stopMemory,
            'memory_peak_bytes' => $this->peakMemory,
            'memory_delta_bytes' => $this->stopMemory - $this->startMemory,
            'memory_peak_mb' => round($this->peakMemory / 1_048_576, 2),
        ];
    }

    public function getName(): string
    {
        return 'memory';
    }
}
