# Extending SCI Profiler PHP

The profiler is designed to be extended with custom collectors and reporters.

## Custom Collector

A collector gathers a specific type of metric during the request lifecycle. Implement `CollectorInterface`:

```php
<?php

declare(strict_types=1);

namespace App\SciProfiler;

use SciProfiler\Collector\CollectorInterface;

final class DatabaseCollector implements CollectorInterface
{
    private int $queryCount = 0;
    private float $queryTime = 0.0;

    public function start(): void
    {
        // Hook into your DB layer or framework's query log
        // Laravel: DB::enableQueryLog();
        // Doctrine: $connection->getConfiguration()->setSQLLogger($this);
    }

    public function stop(): void
    {
        // Collect final stats
        // Laravel: $this->queryCount = count(DB::getQueryLog());
    }

    public function getMetrics(): array
    {
        return [
            'query_count' => $this->queryCount,
            'query_time_ms' => round($this->queryTime, 3),
        ];
    }

    public function getName(): string
    {
        return 'database';
    }
}
```

### Interface contract

```php
interface CollectorInterface
{
    public function start(): void;       // Called at request start
    public function stop(): void;        // Called at shutdown
    public function getMetrics(): array; // Returns associative array
    public function getName(): string;   // Unique collector name
}
```

- `start()` is called once, before the application code executes
- `stop()` is called once, at shutdown
- `getMetrics()` must return an associative array; keys become metric names prefixed with the collector name (e.g., `database.query_count`)
- `getName()` must return a unique string; it is used as the prefix in the output

### Registering a custom collector

To use a custom collector, you need to modify the bootstrap or create your own entry point.

> **Note:** When using the phar, replace `require_once '/opt/sci-profiler-php/vendor/autoload.php'` with `require_once '/opt/sci-profiler.phar'` in the example below. The phar includes the autoloader and all classes.

```php
<?php
// my-bootstrap.php

// Using phar:
require_once '/opt/sci-profiler.phar';
// Or using source:
// require_once '/opt/sci-profiler-php/vendor/autoload.php';

require_once __DIR__ . '/DatabaseCollector.php';

$config = \SciProfiler\Config::fromFile('/path/to/config.php');
$profiler = new \SciProfiler\SciProfiler($config);

$profiler->addCollector(new \SciProfiler\Collector\TimeCollector());
$profiler->addCollector(new \SciProfiler\Collector\MemoryCollector());
$profiler->addCollector(new \SciProfiler\Collector\RequestCollector());
$profiler->addCollector(new \App\SciProfiler\DatabaseCollector());

$profiler->addReporter(new \SciProfiler\Reporter\JsonReporter());

$profiler->start();
register_shutdown_function(static fn () => $profiler->stop());
```

## Custom Reporter

A reporter persists or displays profiling results. Implement `ReporterInterface`:

```php
<?php

declare(strict_types=1);

namespace App\SciProfiler;

use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\ReporterInterface;

final class WebhookReporter implements ReporterInterface
{
    public function __construct(
        private readonly string $webhookUrl,
    ) {
    }

    public function report(ProfileResult $result, Config $config): void
    {
        $payload = json_encode($result->toArray(), JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 2,
            ],
        ]);

        @file_get_contents($this->webhookUrl, false, $context);
    }

    public function getName(): string
    {
        return 'webhook';
    }
}
```

### Interface contract

```php
interface ReporterInterface
{
    public function report(ProfileResult $result, Config $config): void;
    public function getName(): string;
}
```

- `report()` receives the immutable `ProfileResult` and the `Config`
- Reporter exceptions are caught silently by the profiler to never break the host application
- `getName()` returns a unique identifier for the reporter

### Useful ProfileResult methods

```php
$result->toArray();           // Flat associative array of all metrics
$result->getSciScore();       // SCI score in mgCO2eq (float)
$result->getProfileId();      // Unique profile ID (string)
$result->getTimestamp();      // ISO 8601 timestamp (string)
$result->getCollectorMetrics(); // Metrics grouped by collector name
$result->getSciMetrics();     // SCI calculation results
```

## PSR-3 Logger Integration

The built-in `LogReporter` accepts any PSR-3 compatible logger:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SciProfiler\Reporter\LogReporter;

$logger = new Logger('sci-profiler');
$logger->pushHandler(new StreamHandler('/var/log/sci.log', Logger::INFO));

$reporter = new LogReporter($logger);
```

When a PSR-3 logger is provided, the reporter calls `$logger->info()` with the log line as the message and the full result array as context. When no logger is provided, it writes to `<output_dir>/sci-profiler.log`.
