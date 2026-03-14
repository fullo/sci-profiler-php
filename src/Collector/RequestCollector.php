<?php

declare(strict_types=1);

namespace SciProfiler\Collector;

/**
 * Collects HTTP request metadata.
 *
 * Captures method, URI, response code, and I/O sizes
 * without modifying the application.
 */
final class RequestCollector implements CollectorInterface
{
    private string $method = '';
    private string $uri = '';
    private int $inputBytes = 0;
    private int $outputBytes = 0;
    private int $responseCode = 0;

    public function start(): void
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $this->uri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');

        // Use CONTENT_LENGTH header when available to avoid reading the entire
        // request body into memory (file uploads, large JSON payloads, etc.).
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $this->inputBytes = (int) $_SERVER['CONTENT_LENGTH'];
        } else {
            $input = file_get_contents('php://input');
            $this->inputBytes = $input !== false ? strlen($input) : 0;
        }
    }

    public function stop(): void
    {
        $this->responseCode = http_response_code() ?: 0;

        if (function_exists('ob_get_length')) {
            $length = ob_get_length();
            $this->outputBytes = $length !== false ? $length : 0;
        }
    }

    public function getMetrics(): array
    {
        return [
            'method' => $this->method,
            'uri' => $this->uri,
            'response_code' => $this->responseCode,
            'input_bytes' => $this->inputBytes,
            'output_bytes' => $this->outputBytes,
        ];
    }

    public function getName(): string
    {
        return 'request';
    }
}
