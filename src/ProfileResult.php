<?php

declare(strict_types=1);

namespace SciProfiler;

/**
 * Immutable value object containing all profiling results for a single request.
 */
final class ProfileResult
{
    /**
     * @param array<string, array<string, mixed>> $collectorMetrics Metrics keyed by collector name
     * @param array<string, float>                $sciMetrics       SCI calculation results
     * @param string                              $timestamp        ISO 8601 timestamp
     * @param string                              $profileId        Unique profile identifier
     */
    public function __construct(
        private readonly array $collectorMetrics,
        private readonly array $sciMetrics,
        private readonly string $timestamp,
        private readonly string $profileId,
    ) {
    }

    public function getCollectorMetrics(): array
    {
        return $this->collectorMetrics;
    }

    public function getSciMetrics(): array
    {
        return $this->sciMetrics;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    /**
     * Return the SCI score in milligrams CO2eq.
     */
    public function getSciScore(): float
    {
        return $this->sciMetrics['sci_mgco2eq'] ?? 0.0;
    }

    /**
     * Return all data as a flat associative array suitable for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'profile_id' => $this->profileId,
            'timestamp' => $this->timestamp,
        ];

        foreach ($this->collectorMetrics as $name => $metrics) {
            foreach ($metrics as $key => $value) {
                $data[$name . '.' . $key] = $value;
            }
        }

        foreach ($this->sciMetrics as $key => $value) {
            $data['sci.' . $key] = $value;
        }

        return $data;
    }
}
