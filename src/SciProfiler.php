<?php

declare(strict_types=1);

namespace SciProfiler;

use SciProfiler\Collector\CollectorInterface;
use SciProfiler\Reporter\ReporterInterface;

/**
 * Main profiler orchestrator.
 *
 * Coordinates collectors and reporters to measure the SCI score
 * of a PHP request without modifying application code.
 */
final class SciProfiler
{
    /** @var CollectorInterface[] */
    private array $collectors = [];

    /** @var ReporterInterface[] */
    private array $reporters = [];

    private SciCalculator $calculator;
    private bool $started = false;

    public function __construct(
        private readonly Config $config,
    ) {
        $this->calculator = new SciCalculator($config);
    }

    public function addCollector(CollectorInterface $collector): self
    {
        $this->collectors[$collector->getName()] = $collector;
        return $this;
    }

    public function addReporter(ReporterInterface $reporter): self
    {
        $this->reporters[] = $reporter;
        return $this;
    }

    /**
     * Start all collectors.
     *
     * Should be called as early as possible in the request lifecycle.
     */
    public function start(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        foreach ($this->collectors as $collector) {
            $collector->start();
        }

        $this->started = true;
    }

    /**
     * Stop all collectors, compute SCI, and dispatch to reporters.
     *
     * Should be called at request shutdown.
     */
    public function stop(): ProfileResult
    {
        $collectorMetrics = [];
        foreach ($this->collectors as $collector) {
            $collector->stop();
            $collectorMetrics[$collector->getName()] = $collector->getMetrics();
        }

        $wallTimeSec = $collectorMetrics['time']['wall_time_sec'] ?? 0.0;
        $sciMetrics = $this->calculator->calculate((float) $wallTimeSec);

        $result = new ProfileResult(
            collectorMetrics: $collectorMetrics,
            sciMetrics: $sciMetrics,
            timestamp: gmdate('c'),
            profileId: $this->generateProfileId(),
        );

        foreach ($this->reporters as $reporter) {
            try {
                $reporter->report($result, $this->config);
            } catch (\Throwable) {
                // Silently ignore reporter errors to never break the host application.
            }
        }

        return $result;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    private function generateProfileId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
