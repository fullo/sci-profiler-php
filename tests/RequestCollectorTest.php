<?php

declare(strict_types=1);

namespace SciProfiler\Tests;

use PHPUnit\Framework\TestCase;
use SciProfiler\Collector\RequestCollector;

final class RequestCollectorTest extends TestCase
{
    public function testCollectsCliContextWhenNoServerVars(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        $collector = new RequestCollector();
        $collector->start();
        $collector->stop();

        $metrics = $collector->getMetrics();

        $this->assertSame('request', $collector->getName());
        $this->assertSame('CLI', $metrics['method']);
        $this->assertArrayHasKey('uri', $metrics);
        $this->assertArrayHasKey('input_bytes', $metrics);
        $this->assertArrayHasKey('output_bytes', $metrics);
        $this->assertArrayHasKey('response_code', $metrics);
    }

    public function testCollectsHttpContext(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test?foo=bar';

        $collector = new RequestCollector();
        $collector->start();
        $collector->stop();

        $metrics = $collector->getMetrics();

        $this->assertSame('POST', $metrics['method']);
        $this->assertSame('/api/test?foo=bar', $metrics['uri']);

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    public function testOutputBytesWithOutputBuffering(): void
    {
        $collector = new RequestCollector();
        $collector->start();

        ob_start();
        echo 'hello world';
        $collector->stop();
        ob_end_clean();

        $metrics = $collector->getMetrics();
        $this->assertSame(11, $metrics['output_bytes']);
    }
}
