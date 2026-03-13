<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\ProfileResult;

final class ProfileResultTest extends TestCase
{
    private function createResult(float $sciScore = 0.1234): ProfileResult
    {
        return new ProfileResult(
            collectorMetrics: [
                'time' => [
                    'wall_time_ns' => 50000000,
                    'wall_time_ms' => 50.0,
                    'wall_time_sec' => 0.05,
                ],
                'memory' => [
                    'memory_peak_bytes' => 4194304,
                    'memory_peak_mb' => 4.0,
                ],
                'request' => [
                    'method' => 'GET',
                    'uri' => '/dashboard',
                    'response_code' => 200,
                    'input_bytes' => 0,
                    'output_bytes' => 8192,
                ],
            ],
            sciMetrics: [
                'energy_kwh' => 0.00000025,
                'operational_carbon_gco2eq' => 0.000083,
                'embodied_carbon_gco2eq' => 0.000251,
                'sci_gco2eq' => 0.000334,
                'sci_mgco2eq' => $sciScore,
            ],
            timestamp: '2026-03-13T10:00:00+00:00',
            profileId: 'deadbeef01234567',
        );
    }

    public function testGetSciScoreReturnsMgCo2eq(): void
    {
        $result = $this->createResult(0.334);
        $this->assertSame(0.334, $result->getSciScore());
    }

    public function testGetSciScoreDefaultsToZero(): void
    {
        $result = new ProfileResult(
            collectorMetrics: [],
            sciMetrics: [],
            timestamp: '2026-01-01T00:00:00+00:00',
            profileId: 'empty',
        );

        $this->assertSame(0.0, $result->getSciScore());
    }

    public function testToArrayFlattensAllMetrics(): void
    {
        $result = $this->createResult();
        $array = $result->toArray();

        $this->assertSame('deadbeef01234567', $array['profile_id']);
        $this->assertSame('2026-03-13T10:00:00+00:00', $array['timestamp']);

        // Collector metrics are prefixed
        $this->assertSame(50.0, $array['time.wall_time_ms']);
        $this->assertSame(4.0, $array['memory.memory_peak_mb']);
        $this->assertSame('GET', $array['request.method']);
        $this->assertSame('/dashboard', $array['request.uri']);
        $this->assertSame(200, $array['request.response_code']);
        $this->assertSame(8192, $array['request.output_bytes']);

        // SCI metrics are prefixed
        $this->assertArrayHasKey('sci.energy_kwh', $array);
        $this->assertArrayHasKey('sci.sci_mgco2eq', $array);
    }

    public function testGettersReturnCorrectValues(): void
    {
        $result = $this->createResult();

        $this->assertSame('deadbeef01234567', $result->getProfileId());
        $this->assertSame('2026-03-13T10:00:00+00:00', $result->getTimestamp());
        $this->assertCount(3, $result->getCollectorMetrics());
        $this->assertCount(5, $result->getSciMetrics());
    }
}
