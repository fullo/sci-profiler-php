# SCI Profiler PHP

Non-invasive **Software Carbon Intensity** (SCI) profiler for PHP applications.

Measures the carbon footprint of every HTTP request using the [Green Software Foundation](https://sci-guide.greensoftware.foundation/) methodology — without modifying your application code.

## SCI Formula

```
SCI = ((E × I) + M) / R
```

| Symbol | Meaning | Unit |
|--------|---------|------|
| **E** | Energy consumed | kWh |
| **I** | Grid carbon intensity | gCO2eq/kWh |
| **M** | Embodied carbon (amortized) | gCO2eq |
| **R** | Functional unit | 1 request |

## Features

- **Zero application changes** — uses PHP's `auto_prepend_file` directive
- **Framework-agnostic** — works with Laravel, Symfony, WordPress, vanilla PHP
- **Minimal footprint** — measures start/end of request, no tick functions
- **Multiple reporters** — JSON lines, plain log, HTML dashboard
- **Configurable** — via PHP file, environment variables, or defaults
- **PSR compliant** — PSR-4 autoloading, PSR-12 coding style, PSR-3 logger support

## Quick Start

### 1. Clone the profiler

```bash
git clone https://github.com/fullo/sci-profiler-php.git /opt/sci-profiler-php
cd /opt/sci-profiler-php
composer install --no-dev
```

### 2. Configure (optional)

```bash
cp config/sci-profiler.php config/sci-profiler.local.php
# Edit config/sci-profiler.local.php with your values
export SCI_PROFILER_CONFIG_FILE=/opt/sci-profiler-php/config/sci-profiler.local.php
```

### 3. Enable in your staging/dev PHP

Add to your `php.ini` or PHP-FPM pool config:

```ini
auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

Or for a single virtualhost (Apache):

```apache
php_value auto_prepend_file "/opt/sci-profiler-php/src/bootstrap.php"
```

Or with PHP's built-in server:

```bash
php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php -S localhost:8000
```

### 4. View results

```bash
# JSON lines (one entry per request)
cat /tmp/sci-profiler/sci-profiler.jsonl | jq .

# Human-readable log
tail -f /tmp/sci-profiler/sci-profiler.log

# HTML dashboard (enable 'html' reporter in config)
open /tmp/sci-profiler/dashboard.html
```

## Configuration

### Via PHP file

```php
// config/sci-profiler.php
return [
    'enabled' => true,
    'device_power_watts' => 18.0,       // Your machine's power draw
    'grid_carbon_intensity' => 332.0,    // gCO2eq/kWh for your region
    'embodied_carbon' => 211000.0,       // Total device lifecycle CO2
    'device_lifetime_hours' => 11680.0,  // Expected device lifetime
    'output_dir' => '/tmp/sci-profiler',
    'reporters' => ['json', 'log'],      // json, log, html
];
```

### Via environment variables

```bash
export SCI_PROFILER_ENABLED=1
export SCI_PROFILER_DEVICE_POWER_WATTS=65
export SCI_PROFILER_GRID_CARBON_INTENSITY=56
export SCI_PROFILER_OUTPUT_DIR=/var/log/sci-profiler
export SCI_PROFILER_REPORTERS=json,log,html
```

Find your grid carbon intensity at [Electricity Maps](https://app.electricitymaps.com/).

## Collected Metrics

| Metric | Source | Description |
|--------|--------|-------------|
| Wall time | `hrtime(true)` | Nanosecond-precision elapsed time |
| CPU time | `getrusage()` | User + system CPU time (Linux/macOS) |
| Peak memory | `memory_get_peak_usage()` | Maximum memory allocated |
| Request info | `$_SERVER` | Method, URI, response code |
| I/O bytes | `php://input`, `ob_get_length()` | Request/response payload sizes |

## Architecture

```
src/
├── bootstrap.php              # auto_prepend_file entry point
├── Config.php                 # Configuration value object
├── SciCalculator.php          # SCI formula implementation
├── SciProfiler.php            # Orchestrator
├── ProfileResult.php          # Immutable result value object
├── Collector/
│   ├── CollectorInterface.php # Collector contract
│   ├── TimeCollector.php      # Wall time + CPU time
│   ├── MemoryCollector.php    # Memory usage tracking
│   └── RequestCollector.php   # HTTP request metadata
└── Reporter/
    ├── ReporterInterface.php  # Reporter contract
    ├── JsonReporter.php       # JSON lines output
    ├── LogReporter.php        # Plain text log (PSR-3 compatible)
    └── HtmlReporter.php       # Static HTML dashboard
```

## Development

```bash
composer install
composer test        # Run PHPUnit tests
composer analyse     # Run PHPStan static analysis
composer cs-fix      # Fix coding style (PSR-12)
```

## Important Notes

- **Do not use in production.** This profiler is designed for staging and development environments only.
- The profiler silently catches all reporter errors to never break the host application.
- SCI scores are approximations based on wall time and configured device parameters.

## Related

- [sci-profiler](https://github.com/fullo/sci-profiler) — Original TypeScript SCI profiler
- [Green Software Foundation SCI Specification](https://sci-guide.greensoftware.foundation/)
- [Electricity Maps](https://app.electricitymaps.com/) — Real-time grid carbon intensity data

## License

MIT
