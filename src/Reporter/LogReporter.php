<?php

declare(strict_types=1);

namespace SciProfiler\Reporter;

use Psr\Log\LoggerInterface;
use SciProfiler\Config;
use SciProfiler\ProfileResult;

/**
 * Writes a human-readable log line per request.
 *
 * If a PSR-3 LoggerInterface is provided, uses it. Otherwise writes to a plain file.
 *
 * @see https://www.php-fig.org/psr/psr-3/
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */
final class LogReporter implements ReporterInterface
{
    use EnsuresOutputDirectory;

    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function report(ProfileResult $result, Config $config): void
    {
        $data = $result->toArray();

        $method = $data['request.method'] ?? 'CLI';
        $uri = $data['request.uri'] ?? '-';
        $script = $data['request.script_filename'] ?? null;
        $status = $data['request.response_code'] ?? 0;
        $time = $data['time.wall_time_ms'] ?? '?';
        $sci = $result->getSciScore();
        $peak = $data['memory.memory_peak_mb'] ?? '?';

        // Include script_filename if it differs from URI (provides useful context)
        $target = ($script !== null && $script !== $uri)
            ? sprintf('%s (%s)', $uri, basename($script))
            : $uri;

        // Include response code for HTTP requests (skip for CLI)
        $statusStr = ($method !== 'CLI' && $status > 0) ? sprintf(' [%d]', $status) : '';

        $line = sprintf(
            '[%s] %s %s%s | %s ms | %.4f mgCO2eq | peak %s MB',
            $data['timestamp'],
            $method,
            $target,
            $statusStr,
            $time,
            $sci,
            $peak,
        );

        if ($this->logger !== null) {
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
