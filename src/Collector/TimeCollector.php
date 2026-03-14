<?php

declare(strict_types=1);

namespace SciProfiler\Collector;

/**
 * Collects wall time and CPU time metrics.
 *
 * Uses hrtime() for nanosecond-precision wall time
 * and getrusage() for user/system CPU time on supported platforms.
 */
final class TimeCollector implements CollectorInterface
{
    /** Whether getrusage() is available — checked once, not per call. */
    private static bool $hasRusage;

    private int $startHrtime = 0;
    private int $stopHrtime = 0;

    /** @var array{ru_utime.tv_sec: int, ru_utime.tv_usec: int, ru_stime.tv_sec: int, ru_stime.tv_usec: int}|null */
    private ?array $startRusage = null;

    /** @var array{ru_utime.tv_sec: int, ru_utime.tv_usec: int, ru_stime.tv_sec: int, ru_stime.tv_usec: int}|null */
    private ?array $stopRusage = null;

    public function __construct()
    {
        // Cache function availability once per process, not per start()/stop() call.
        self::$hasRusage ??= function_exists('getrusage');
    }

    public function start(): void
    {
        $this->startHrtime = hrtime(true);

        if (self::$hasRusage) {
            $this->startRusage = getrusage();
        }
    }

    public function stop(): void
    {
        $this->stopHrtime = hrtime(true);

        if (self::$hasRusage) {
            $this->stopRusage = getrusage();
        }
    }

    public function getMetrics(): array
    {
        $wallTimeNs = $this->stopHrtime - $this->startHrtime;
        $wallTimeMs = $wallTimeNs / 1_000_000;
        $wallTimeSec = $wallTimeNs / 1_000_000_000;

        $metrics = [
            'wall_time_ns' => $wallTimeNs,
            'wall_time_ms' => round($wallTimeMs, 3),
            'wall_time_sec' => round($wallTimeSec, 6),
        ];

        if ($this->startRusage !== null && $this->stopRusage !== null) {
            $userTimeSec = $this->computeRusageDelta('ru_utime.tv_sec', 'ru_utime.tv_usec');
            $systemTimeSec = $this->computeRusageDelta('ru_stime.tv_sec', 'ru_stime.tv_usec');

            $metrics['cpu_user_time_sec'] = round($userTimeSec, 6);
            $metrics['cpu_system_time_sec'] = round($systemTimeSec, 6);
            $metrics['cpu_total_time_sec'] = round($userTimeSec + $systemTimeSec, 6);
        }

        return $metrics;
    }

    public function getName(): string
    {
        return 'time';
    }

    /**
     * Compute the delta between start and stop rusage for a given field pair.
     */
    private function computeRusageDelta(string $secKey, string $usecKey): float
    {
        $startSec = $this->startRusage[$secKey] ?? 0;
        $startUsec = $this->startRusage[$usecKey] ?? 0;
        $stopSec = $this->stopRusage[$secKey] ?? 0;
        $stopUsec = $this->stopRusage[$usecKey] ?? 0;

        return ($stopSec - $startSec) + ($stopUsec - $startUsec) / 1_000_000;
    }
}
