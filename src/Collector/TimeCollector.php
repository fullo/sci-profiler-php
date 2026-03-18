<?php

declare(strict_types=1);

namespace SciProfiler\Collector;

use Psr\Clock\ClockInterface;

/**
 * Collects wall time and CPU time metrics.
 *
 * Uses hrtime() for nanosecond-precision wall time by default.
 * Accepts an optional PSR-20 ClockInterface for testable timing.
 * Uses getrusage() for user/system CPU time on supported platforms.
 *
 * @see https://www.php-fig.org/psr/psr-20/
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class TimeCollector implements CollectorInterface
{
    /** Whether getrusage() is available — checked once per process. */
    private static bool $hasRusage;

    private int $startHrtime = 0;
    private int $stopHrtime = 0;

    private ?\DateTimeImmutable $startTime = null;
    private ?\DateTimeImmutable $stopTime = null;

    /** @var array<string, int>|null */
    private ?array $startRusage = null;

    /** @var array<string, int>|null */
    private ?array $stopRusage = null;

    /**
     * @param ClockInterface|null $clock Optional PSR-20 clock for testability.
     *                                   When null, uses hrtime() for nanosecond precision.
     */
    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
        self::$hasRusage ??= function_exists('getrusage');
    }

    public function start(): void
    {
        $this->startHrtime = hrtime(true);

        if ($this->clock !== null) {
            $this->startTime = $this->clock->now();
        }

        if (self::$hasRusage) {
            $this->startRusage = getrusage();
        }
    }

    public function stop(): void
    {
        $this->stopHrtime = hrtime(true);

        if ($this->clock !== null) {
            $this->stopTime = $this->clock->now();
        }

        if (self::$hasRusage) {
            $this->stopRusage = getrusage();
        }
    }

    public function getMetrics(): array
    {
        // When a PSR-20 clock is provided and both timestamps exist,
        // use the clock for wall time (enables deterministic testing).
        if ($this->startTime !== null && $this->stopTime !== null) {
            $wallTimeSec = (float) $this->stopTime->format('U.u')
                - (float) $this->startTime->format('U.u');
            $wallTimeNs = (int) ($wallTimeSec * 1_000_000_000);
            $wallTimeMs = $wallTimeSec * 1_000;
        } else {
            $wallTimeNs = $this->stopHrtime - $this->startHrtime;
            $wallTimeMs = $wallTimeNs / 1_000_000;
            $wallTimeSec = $wallTimeNs / 1_000_000_000;
        }

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
