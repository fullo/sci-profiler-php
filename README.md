# SCI Profiler PHP

[![CI](https://github.com/fullo/sci-profiler-php/actions/workflows/ci.yml/badge.svg)](https://github.com/fullo/sci-profiler-php/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Non-invasive **Software Carbon Intensity** (SCI) profiler for PHP applications.

Measures the carbon footprint of every HTTP request using the [Green Software Foundation](https://sci-guide.greensoftware.foundation/) methodology — without modifying your application code.

> **Warning:** This profiler is designed for **staging and development environments only**. Do not use in production.

## Table of Contents

- [How It Works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Reporters](#reporters)
- [Collected Metrics](#collected-metrics)
- [Architecture](#architecture)
- [Extending](#extending)
- [Testing](#testing)
- [FAQ](#faq)
- [Related](#related)

## How It Works

SCI Profiler PHP uses PHP's [`auto_prepend_file`](https://www.php.net/manual/en/ini.core.php#ini.auto-prepend-file) directive to inject profiling code before every PHP script execution. At shutdown, it calculates the SCI score and writes results via configured reporters.

```
SCI = ((E × I) + M) / R
```

| Symbol | Meaning | Unit |
|--------|---------|------|
| **E** | Energy consumed by the request | kWh |
| **I** | Grid carbon intensity | gCO2eq/kWh |
| **M** | Embodied carbon (amortized per request) | gCO2eq |
| **R** | Functional unit | 1 request |

See [METHODOLOGY.md](METHODOLOGY.md) for the full technical specification.

### Features

- **Zero application changes** — uses PHP's `auto_prepend_file` directive
- **Framework-agnostic** — works with Laravel, Symfony, WordPress, Drupal, vanilla PHP
- **Minimal footprint** — measures only start/end of request, no tick functions
- **Multiple reporters** — JSON lines, plain log, static HTML dashboard
- **Three config sources** — PHP file, environment variables, or built-in defaults
- **PSR compliant** — PSR-4 autoloading, PSR-12 coding style, PSR-3 logger support
- **Fail-safe** — reporter errors are silently caught, never breaks the host application

## Requirements

- PHP >= 8.1
- `getrusage()` for CPU time metrics (available on Linux and macOS)
- Write permissions to the output directory

## Installation

### Option A: Clone (recommended for standalone use)

```bash
git clone https://github.com/fullo/sci-profiler-php.git /opt/sci-profiler-php
cd /opt/sci-profiler-php
composer install --no-dev
```

### Option B: Without Composer

The profiler includes a built-in PSR-4 autoloader fallback. Clone and use directly:

```bash
git clone https://github.com/fullo/sci-profiler-php.git /opt/sci-profiler-php
# No composer install needed — the bootstrap registers its own autoloader
```

## Configuration

Configuration is loaded in this priority order:

1. **`SCI_PROFILER_CONFIG_FILE`** env var pointing to a PHP config file
2. **Default config file** at `<profiler-root>/config/sci-profiler.php`
3. **Environment variables** prefixed with `SCI_PROFILER_`
4. **Built-in defaults**

### Via PHP file

Copy the default config and customize:

```bash
cp config/sci-profiler.php config/sci-profiler.local.php
export SCI_PROFILER_CONFIG_FILE=/opt/sci-profiler-php/config/sci-profiler.local.php
```

```php
<?php
// config/sci-profiler.local.php
return [
    'enabled'               => true,
    'device_power_watts'    => 18.0,       // Your machine's TDP or measured draw
    'grid_carbon_intensity' => 332.0,      // gCO2eq/kWh — see Electricity Maps
    'embodied_carbon'       => 211000.0,   // Total device lifecycle CO2 in grams
    'device_lifetime_hours' => 11680.0,    // Expected lifetime: 4y × 8h × 365d
    'machine_description'   => 'MacBook Pro M1 14"',
    'lca_source'            => 'Apple Environmental Report 2022',
    'output_dir'            => '/tmp/sci-profiler',
    'reporters'             => ['json', 'log', 'html'],
];
```

### Via environment variables

All variables use the `SCI_PROFILER_` prefix:

```bash
export SCI_PROFILER_ENABLED=1
export SCI_PROFILER_DEVICE_POWER_WATTS=65
export SCI_PROFILER_GRID_CARBON_INTENSITY=56
export SCI_PROFILER_EMBODIED_CARBON=320000
export SCI_PROFILER_DEVICE_LIFETIME_HOURS=11680
export SCI_PROFILER_MACHINE_DESCRIPTION="CI server"
export SCI_PROFILER_LCA_SOURCE="Dell Product Carbon Footprint"
export SCI_PROFILER_OUTPUT_DIR=/var/log/sci-profiler
export SCI_PROFILER_REPORTERS=json,log,html
```

### Configuration Reference

| Parameter | Env Variable | Default | Description |
|-----------|-------------|---------|-------------|
| `enabled` | `SCI_PROFILER_ENABLED` | `true` | Enable/disable profiling |
| `device_power_watts` | `SCI_PROFILER_DEVICE_POWER_WATTS` | `18.0` | Device power consumption (W) |
| `grid_carbon_intensity` | `SCI_PROFILER_GRID_CARBON_INTENSITY` | `332.0` | Grid carbon intensity (gCO2eq/kWh) |
| `embodied_carbon` | `SCI_PROFILER_EMBODIED_CARBON` | `211000.0` | Total embodied carbon (gCO2eq) |
| `device_lifetime_hours` | `SCI_PROFILER_DEVICE_LIFETIME_HOURS` | `11680.0` | Device lifetime (hours) |
| `machine_description` | `SCI_PROFILER_MACHINE_DESCRIPTION` | `Default development machine` | Label for reports |
| `lca_source` | `SCI_PROFILER_LCA_SOURCE` | `Estimated` | LCA data source reference |
| `output_dir` | `SCI_PROFILER_OUTPUT_DIR` | `/tmp/sci-profiler` | Output directory path |
| `reporters` | `SCI_PROFILER_REPORTERS` | `json` | Comma-separated reporter list |

Find your grid carbon intensity at [Electricity Maps](https://app.electricitymaps.com/).

## Usage

### Enable via php.ini (global)

Add to your `php.ini` or PHP-FPM pool config:

```ini
; Only in staging/dev php.ini!
auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

### Enable per VirtualHost (Apache)

```apache
<VirtualHost *:80>
    ServerName staging.example.com
    DocumentRoot /var/www/myapp/public
    php_value auto_prepend_file "/opt/sci-profiler-php/src/bootstrap.php"
</VirtualHost>
```

### Enable per pool (PHP-FPM)

```ini
; /etc/php/8.3/fpm/pool.d/staging.conf
[staging]
php_value[auto_prepend_file] = /opt/sci-profiler-php/src/bootstrap.php
```

### Enable per site (Nginx + PHP-FPM)

```nginx
location ~ \.php$ {
    fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php";
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    include fastcgi_params;
}
```

### PHP built-in server

```bash
php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php -S localhost:8000 -t public/
```

### Laravel Herd / Valet

Create a `.user.ini` in your project's public directory:

```ini
; public/.user.ini
auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

### Docker

```dockerfile
ENV SCI_PROFILER_ENABLED=1
ENV SCI_PROFILER_OUTPUT_DIR=/var/log/sci-profiler
COPY sci-profiler-php /opt/sci-profiler-php
RUN echo "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php" \
    >> /usr/local/etc/php/conf.d/sci-profiler.ini
```

### Disable without removing

Set `enabled` to `false` in config or environment:

```bash
export SCI_PROFILER_ENABLED=0
```

Or comment out the `auto_prepend_file` directive.

## Reporters

### JSON Lines (`json`)

Writes one JSON object per line to `<output_dir>/sci-profiler.jsonl`. Ideal for CI/CD pipelines, `jq` analysis, and data processing.

```bash
# View latest entry
tail -1 /tmp/sci-profiler/sci-profiler.jsonl | jq .

# Average SCI score over last 100 requests
tail -100 /tmp/sci-profiler/sci-profiler.jsonl | jq -s 'map(.["sci.sci_mgco2eq"]) | add / length'

# Filter by URI
cat /tmp/sci-profiler/sci-profiler.jsonl | jq 'select(.["request.uri"] | startswith("/api/"))'
```

Sample output:

```json
{
  "profile_id": "a1b2c3d4e5f6g7h8",
  "timestamp": "2026-03-13T10:30:00+00:00",
  "time.wall_time_ns": 45230000,
  "time.wall_time_ms": 45.23,
  "time.wall_time_sec": 0.04523,
  "time.cpu_user_time_sec": 0.032,
  "time.cpu_system_time_sec": 0.004,
  "time.cpu_total_time_sec": 0.036,
  "memory.memory_start_bytes": 2097152,
  "memory.memory_end_bytes": 4194304,
  "memory.memory_peak_bytes": 8388608,
  "memory.memory_delta_bytes": 2097152,
  "memory.memory_peak_mb": 8.0,
  "request.method": "GET",
  "request.uri": "/dashboard",
  "request.response_code": 200,
  "request.input_bytes": 0,
  "request.output_bytes": 15234,
  "sci.energy_kwh": 2.261e-7,
  "sci.operational_carbon_gco2eq": 0.0000751,
  "sci.embodied_carbon_gco2eq": 0.0002270,
  "sci.sci_gco2eq": 0.0003021,
  "sci.sci_mgco2eq": 0.3021
}
```

### Log (`log`)

Appends human-readable lines to `<output_dir>/sci-profiler.log`. Supports optional PSR-3 logger injection.

```
[2026-03-13T10:30:00+00:00] GET /dashboard | 45.23 ms | 0.3021 mgCO2eq | peak 8.00 MB
[2026-03-13T10:30:01+00:00] POST /api/users | 123.45 ms | 0.8234 mgCO2eq | peak 12.50 MB
```

### HTML Dashboard (`html`)

Generates a self-contained static HTML page at `<output_dir>/dashboard.html`. Shows summary cards (total requests, avg SCI, total emissions, avg response time) and a detailed table. Requires the `json` reporter to be enabled as it reads from the JSONL file.

```bash
# Enable both json and html reporters
export SCI_PROFILER_REPORTERS=json,html

# Open dashboard after some requests
open /tmp/sci-profiler/dashboard.html
```

## Collected Metrics

| Metric | PHP Source | Description |
|--------|-----------|-------------|
| Wall time (ns, ms, sec) | `hrtime(true)` | Nanosecond-precision elapsed time |
| CPU user time | `getrusage()` | User-space CPU time (Linux/macOS) |
| CPU system time | `getrusage()` | Kernel-space CPU time (Linux/macOS) |
| Memory start/end/peak | `memory_get_usage()`, `memory_get_peak_usage()` | Memory allocation tracking |
| Request method | `$_SERVER['REQUEST_METHOD']` | HTTP method or `CLI` |
| Request URI | `$_SERVER['REQUEST_URI']` | Request path with query string |
| Response code | `http_response_code()` | HTTP status code |
| Input bytes | `php://input` | Request body size |
| Output bytes | `ob_get_length()` | Response body size |

## Architecture

```
src/
├── bootstrap.php              # auto_prepend_file entry point
├── Config.php                 # Immutable configuration value object
├── SciCalculator.php          # SCI formula: ((E × I) + M) / R
├── SciProfiler.php            # Orchestrator: collectors → calculator → reporters
├── ProfileResult.php          # Immutable result value object
├── Collector/
│   ├── CollectorInterface.php # Collector contract (start/stop/getMetrics)
│   ├── TimeCollector.php      # Wall time (hrtime) + CPU time (getrusage)
│   ├── MemoryCollector.php    # Memory usage tracking
│   └── RequestCollector.php   # HTTP request metadata
└── Reporter/
    ├── ReporterInterface.php  # Reporter contract (report)
    ├── JsonReporter.php       # JSON lines output for automation
    ├── LogReporter.php        # Plain text log (PSR-3 compatible)
    └── HtmlReporter.php       # Static HTML dashboard generator
```

### Request Lifecycle

```
auto_prepend_file (bootstrap.php)
  │
  ├─ Load config (file → env → defaults)
  ├─ Create SciProfiler
  ├─ Register collectors (Time, Memory, Request)
  ├─ Register reporters (Json, Log, Html)
  ├─ collector.start() — capture initial state
  └─ register_shutdown_function()
       │
       └─ On shutdown:
            ├─ collector.stop() — capture final state
            ├─ SciCalculator.calculate(wallTime)
            ├─ Build ProfileResult
            └─ reporter.report() — persist results
```

## Extending

### Custom Collector

Implement `CollectorInterface` to add your own metrics:

```php
<?php

use SciProfiler\Collector\CollectorInterface;

final class DatabaseCollector implements CollectorInterface
{
    private int $queryCount = 0;

    public function start(): void
    {
        // Hook into your DB layer
    }

    public function stop(): void
    {
        // Finalize
    }

    public function getMetrics(): array
    {
        return ['query_count' => $this->queryCount];
    }

    public function getName(): string
    {
        return 'database';
    }
}
```

### Custom Reporter

Implement `ReporterInterface` to send results anywhere:

```php
<?php

use SciProfiler\Config;
use SciProfiler\ProfileResult;
use SciProfiler\Reporter\ReporterInterface;

final class WebhookReporter implements ReporterInterface
{
    public function __construct(private readonly string $webhookUrl)
    {
    }

    public function report(ProfileResult $result, Config $config): void
    {
        $payload = json_encode($result->toArray());
        // POST to your monitoring system
    }

    public function getName(): string
    {
        return 'webhook';
    }
}
```

### PSR-3 Logger Integration

The `LogReporter` accepts an optional PSR-3 logger:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SciProfiler\Reporter\LogReporter;

$logger = new Logger('sci');
$logger->pushHandler(new StreamHandler('/var/log/sci-profiler.log'));

$reporter = new LogReporter($logger);
```

## Testing

```bash
# Install dev dependencies
composer install

# Run tests
composer test

# Run tests with verbose output
vendor/bin/phpunit --testdox

# Run static analysis
composer analyse

# Fix coding style (PSR-12)
composer cs-fix
```

### Test Suite Structure

| Test File | Coverage |
|-----------|----------|
| `ConfigTest.php` | Default values, fromArray, fromFile, error handling |
| `ConfigEnvironmentTest.php` | Environment variable loading, defaults |
| `CollectorTest.php` | TimeCollector wall/CPU time, MemoryCollector tracking |
| `RequestCollectorTest.php` | CLI context, HTTP context, output buffering |
| `SciCalculatorTest.php` | Energy, operational carbon, embodied carbon, full SCI |
| `SciProfilerTest.php` | Start/stop lifecycle, disabled state, reporter dispatch |
| `ProfileResultTest.php` | SCI score access, toArray flattening, getters |
| `ReporterTest.php` | JsonReporter JSONL, LogReporter log, HtmlReporter dashboard |
| `IntegrationTest.php` | Full lifecycle, JSONL append, SCI scaling with work |

## FAQ

**Can I use this in production?**
No. The profiler adds overhead (file I/O for reporting, memory for collectors) and is designed for staging/development only.

**How accurate is the SCI score?**
It is an approximation. Wall time is used as a proxy for energy consumption. See [METHODOLOGY.md](METHODOLOGY.md) for assumptions and limitations.

**Does it work with CLI scripts?**
Yes. The `RequestCollector` detects CLI context automatically and reports `CLI` as the method.

**Does it modify my application's output?**
No. The profiler only writes to its own output directory. It never modifies HTTP headers, response body, or application state.

**What if a reporter fails?**
All reporter errors are caught silently. The profiler will never throw exceptions or break the host application.

**Can I use it with Docker?**
Yes. Mount the profiler directory and set the `auto_prepend_file` directive. See the [Docker](#docker) section.

## Related

- [sci-profiler](https://github.com/fullo/sci-profiler) — Original TypeScript SCI profiler
- [Green Software Foundation SCI Specification](https://sci-guide.greensoftware.foundation/)
- [Electricity Maps](https://app.electricitymaps.com/) — Real-time grid carbon intensity data
- [Green Software Patterns](https://patterns.greensoftware.foundation/) — Catalog of green software patterns

## License

[MIT](LICENSE)
