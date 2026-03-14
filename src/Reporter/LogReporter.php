<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Writes a human-readable log line per request.
 *
 * If a PSR-3 logger is provided, uses it. Otherwise writes to a plain file.
 */
final class LogReporter implements ReporterInterface
{
    use EnsuresOutputDirectory;

    /** @var \Psr\Log\LoggerInterface|null */
    private ?object $logger;

    /**
     * @param object|null $logger Optional PSR-3 logger instance
     */
    public function __construct(?object $logger = null)
    {
        $this->logger = $logger;
    }

    public function report(ProfileResult $result, Config $config): void
    {
        $data = $result->toArray();
        $line = sprintf(
            '[%s] %s %s | %s ms | %.4f mgCO2eq | peak %s MB',
            $data['timestamp'],
            $data['request.method'] ?? 'CLI',
            $data['request.uri'] ?? '-',
            $data['time.wall_time_ms'] ?? '?',
            $result->getSciScore(),
            $data['memory.memory_peak_mb'] ?? '?',
        );

        if ($this->logger !== null && method_exists($this->logger, 'info')) {
            $this->logger->info($line, $result->toArray());
            return;
        }

        $dir = $config->getOutputDir();
        $this->ensureDirectory($dir);

        file_put_contents(
            $dir . '/sci-profiler.log',
            $line . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public function getName(): string
    {
        return 'log';
    }
}
