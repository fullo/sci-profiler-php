# Reporters

SCI Profiler PHP includes three built-in reporters. Enable them via the `reporters` configuration option.

## JSON Lines (`json`)

Writes one JSON object per line to `<output_dir>/sci-profiler.jsonl`.

Best for: CI/CD pipelines, automated analysis, data processing, `jq` queries.

### Output format

Each line is a self-contained JSON object:

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

### Analysis examples

```bash
# View latest entry
tail -1 /tmp/sci-profiler/sci-profiler.jsonl | jq .

# Average SCI over last 100 requests
tail -100 /tmp/sci-profiler/sci-profiler.jsonl \
  | jq -s 'map(.["sci.sci_mgco2eq"]) | add / length'

# Filter by URI pattern
cat /tmp/sci-profiler/sci-profiler.jsonl \
  | jq 'select(.["request.uri"] | startswith("/api/"))'

# Top 10 heaviest requests
cat /tmp/sci-profiler/sci-profiler.jsonl \
  | jq -s 'sort_by(-.["sci.sci_mgco2eq"]) | .[0:10] | map({uri: .["request.uri"], sci: .["sci.sci_mgco2eq"], ms: .["time.wall_time_ms"]})'

# Group by HTTP method
cat /tmp/sci-profiler/sci-profiler.jsonl \
  | jq -s 'group_by(.["request.method"]) | map({method: .[0]["request.method"], count: length, avg_sci: (map(.["sci.sci_mgco2eq"]) | add / length)})'

# Carbon budget check: fail if any request exceeds 5 mgCO2eq
MAX_SCI=5.0
VIOLATIONS=$(jq "select(.[\"sci.sci_mgco2eq\"] > $MAX_SCI)" /tmp/sci-profiler/sci-profiler.jsonl | wc -l)
if [ "$VIOLATIONS" -gt 0 ]; then
    echo "FAIL: $VIOLATIONS requests exceeded SCI budget"
    exit 1
fi
```

## Log (`log`)

Appends human-readable lines to `<output_dir>/sci-profiler.log`.

Best for: quick monitoring with `tail -f`, human review, development.

### Output format

```
[2026-03-13T10:30:00+00:00] GET /dashboard | 45.23 ms | 0.3021 mgCO2eq | peak 8.00 MB
[2026-03-13T10:30:01+00:00] POST /api/users | 123.45 ms | 0.8234 mgCO2eq | peak 12.50 MB
[2026-03-13T10:30:02+00:00] CLI /var/www/myapp/artisan | 1523.00 ms | 8.1200 mgCO2eq | peak 64.00 MB
```

### PSR-3 logger integration

The `LogReporter` accepts an optional PSR-3 compatible logger. When provided, it uses the logger instead of writing to a file:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SciProfiler\Reporter\LogReporter;

$logger = new Logger('sci');
$logger->pushHandler(new StreamHandler('/var/log/sci-profiler.log'));

$reporter = new LogReporter($logger);
```

This allows routing SCI logs to any PSR-3 compatible backend (Monolog, Sentry, Logtail, etc.).

## HTML Dashboard (`html`)

Generates a self-contained static HTML page at `<output_dir>/dashboard.html`.

Best for: visual overview, team dashboards, periodic review.

> **Requires** the `json` reporter to be enabled — the dashboard reads from `sci-profiler.jsonl`.

### What it shows

- **Summary cards**: total requests, average SCI, total emissions, average response time
- **Detail table**: timestamp, method, URI, response time, SCI score, peak memory for the last 200 requests

### Setup

```bash
# Enable both json and html reporters
export SCI_PROFILER_REPORTERS=json,html

# After some requests, open the dashboard
open /tmp/sci-profiler/dashboard.html
```

The dashboard is regenerated on every request, so it always shows the latest data.

## Using Multiple Reporters

Enable multiple reporters as a comma-separated list:

```php
// config file
'reporters' => ['json', 'log', 'html'],
```

```bash
# environment variable
export SCI_PROFILER_REPORTERS=json,log,html
```

All reporters run independently. If one fails, the others still execute (errors are silently caught).

## Collected Metrics Reference

All reporters output the same metrics collected during the request:

| Metric Key | PHP Source | Description |
|------------|-----------|-------------|
| `time.wall_time_ns` | `hrtime(true)` | Wall time in nanoseconds |
| `time.wall_time_ms` | derived | Wall time in milliseconds |
| `time.wall_time_sec` | derived | Wall time in seconds |
| `time.cpu_user_time_sec` | `getrusage()` | CPU user-space time (Linux/macOS) |
| `time.cpu_system_time_sec` | `getrusage()` | CPU kernel-space time (Linux/macOS) |
| `time.cpu_total_time_sec` | derived | CPU user + system time |
| `memory.memory_start_bytes` | `memory_get_usage()` | Memory at request start |
| `memory.memory_end_bytes` | `memory_get_usage()` | Memory at request end |
| `memory.memory_peak_bytes` | `memory_get_peak_usage()` | Peak memory during request |
| `memory.memory_delta_bytes` | derived | Memory end - start |
| `memory.memory_peak_mb` | derived | Peak memory in megabytes |
| `request.method` | `$_SERVER['REQUEST_METHOD']` | HTTP method or `CLI` |
| `request.uri` | `$_SERVER['REQUEST_URI']` | Request path with query string |
| `request.response_code` | `http_response_code()` | HTTP response status code |
| `request.input_bytes` | `php://input` | Request body size in bytes |
| `request.output_bytes` | `ob_get_length()` | Response body size in bytes |
| `sci.energy_kwh` | calculated | Energy consumed (kWh) |
| `sci.operational_carbon_gco2eq` | calculated | Operational carbon (gCO2eq) |
| `sci.embodied_carbon_gco2eq` | calculated | Embodied carbon (gCO2eq) |
| `sci.sci_gco2eq` | calculated | SCI score (gCO2eq) |
| `sci.sci_mgco2eq` | calculated | SCI score (mgCO2eq) |
