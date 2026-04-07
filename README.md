# SCI Profiler PHP

[![CI](https://github.com/fullo/sci-profiler-php/actions/workflows/ci.yml/badge.svg)](https://github.com/fullo/sci-profiler-php/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Non-invasive **Software Carbon Intensity** (SCI) profiler for PHP applications.

Measures the carbon footprint of every HTTP request using the [Green Software Foundation](https://sci-guide.greensoftware.foundation/) methodology — without modifying your application code.

> **Warning:** This profiler is designed for **staging and development environments only**. Do not use in production.

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
| **R** | Functional unit (one use case) | 1 request |

The **functional unit** is a complete user-facing operation (e.g., loading a WordPress page, processing a Laravel API request), not a single method call. See [Methodology](doc/METHODOLOGY.md) for details.

### Features

- **Zero application changes** — uses PHP's `auto_prepend_file` directive
- **Framework-agnostic** — works with Laravel, Symfony, WordPress, Drupal, vanilla PHP
- **Minimal footprint** — measures only start/end of request, no tick functions
- **Multiple reporters** — JSON lines, plain log, static HTML dashboard, SCI trend tracking
- **PSR compliant** — PSR-4 autoloading, PSR-12 coding style, PSR-3 logger support
- **Fail-safe** — reporter errors are silently caught, never breaks the host application

## Quick Start

### 1. Install

```bash
# Option A: Phar (zero dependencies, ~82KB)
wget -O /opt/sci-profiler.phar https://github.com/fullo/sci-profiler-php/releases/latest/download/sci-profiler.phar

# Option B: Clone + Composer
git clone https://github.com/fullo/sci-profiler-php.git /opt/sci-profiler-php
cd /opt/sci-profiler-php && composer install --no-dev
```

### 2. Enable (staging/dev only)

Add to your `php.ini`, PHP-FPM pool, or virtualhost config:

```ini
; Using phar (no dependencies):
auto_prepend_file = /opt/sci-profiler.phar
; Or using source (requires composer install):
auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

Or with PHP's built-in server:

```bash
php -d auto_prepend_file=/opt/sci-profiler.phar -S localhost:8000 -t public/
# Or with source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php -S localhost:8000 -t public/
```

### 3. Configure (optional)

```bash
cp config/sci-profiler.php config/sci-profiler.local.php
# Edit with your device power, grid carbon intensity, etc.
export SCI_PROFILER_CONFIG_FILE=/opt/sci-profiler-php/config/sci-profiler.local.php
```

### 4. View results

```bash
# JSON (one entry per request)
tail -1 /tmp/sci-profiler/sci-profiler.jsonl | jq .

# Human-readable log
tail -f /tmp/sci-profiler/sci-profiler.log

# HTML dashboard
open /tmp/sci-profiler/dashboard.html
```

## Documentation

Full documentation is in the [`doc/`](doc/) directory:

| Document | Description |
|----------|-------------|
| [**Documentation Index**](doc/README.md) | Overview of all documentation |
| [**Methodology**](doc/METHODOLOGY.md) | SCI formula, functional units, assumptions, limitations |
| [**Configuration**](doc/configuration.md) | All config options, environment variables, priority order |
| [**Reporters**](doc/reporters.md) | JSON lines, log, HTML dashboard — setup and analysis examples |
| [**Extending**](doc/extending.md) | Custom collectors, custom reporters, PSR-3 integration |
| [**WordPress Example**](doc/example-wordpress.md) | Setup, use cases, WooCommerce, optimization insights |
| [**Laravel Example**](doc/example-laravel.md) | Setup, routes, Artisan, queues, CI carbon budget |
| [**Symfony Example**](doc/example-symfony.md) | Setup, API Platform, Messenger, CI integration |

## Development

```bash
composer install
composer test          # PHPUnit (131 tests, 589 assertions)
composer analyse       # PHPStan static analysis
composer cs-fix        # PSR-12 coding style
```

### Building the phar

```bash
php bin/build-phar.php
# Creates bin/sci-profiler.phar (~82KB, zero dependencies)
```

## Related

- [sci-profiler](https://github.com/fullo/sci-profiler) — Original TypeScript SCI profiler
- [Green Software Foundation SCI Specification](https://sci-guide.greensoftware.foundation/)
- [Electricity Maps](https://app.electricitymaps.com/) — Real-time grid carbon intensity data

## License

[MIT](LICENSE)
