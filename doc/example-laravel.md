# SCI Profiler PHP — Laravel

Guide to measuring the carbon footprint of a Laravel application with SCI Profiler PHP.

## Use Cases and Functional Units

In Laravel every HTTP request and every Artisan command execution is a functional unit. The SCI score for `GET /dashboard` includes the full Laravel lifecycle: service container boot, middleware pipeline, controller logic, Eloquent queries, Blade rendering, and response.

### Typical use cases

| Use Case | Functional Unit | What Happens Inside |
|----------|----------------|---------------------|
| **Dashboard page** | `GET /dashboard` | Auth middleware, controller, Eloquent queries, Blade view compilation, layout rendering |
| **API resource list** | `GET /api/v1/users` | Sanctum/Passport auth, FormRequest validation, Eloquent query + pagination, API Resource serialization |
| **Form submission** | `POST /orders` | CSRF verification, FormRequest validation, DB transaction, event dispatch, redirect |
| **File upload** | `POST /api/documents` | Upload handling, virus scan, S3 storage, thumbnail generation, DB record creation |
| **Artisan command** | `php artisan reports:generate` | Console bootstrap, database queries, PDF generation, email dispatch |
| **Queue worker job** | `Job: SendInvoiceEmail` | Worker bootstrap, job deserialization, mail rendering, SMTP delivery, job completion |
| **Scheduled task** | `php artisan schedule:run` | Scheduler evaluation, cron expression matching, task execution |

### What is NOT a functional unit in Laravel

| NOT a Functional Unit | Why |
|----------------------|-----|
| `UserRepository::findActive()` | Internal service method, not a user-facing operation |
| A single Eloquent query | Part of a controller action; measuring it alone misses the full cost |
| A middleware execution | One step in the pipeline, not the complete use case |
| `Cache::remember()` | Infrastructure call, not a user intent |

The profiler measures at the request/command boundary, capturing the complete cost of serving the user's intent.

## Setup

### PHP built-in server (local dev)

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  artisan serve --host=0.0.0.0 --port=8000
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   artisan serve --host=0.0.0.0 --port=8000
```

### Laravel Herd / Valet

Create a `.user.ini` in your Laravel `public/` directory:

```ini
; public/.user.ini
; Using phar:
auto_prepend_file = /opt/sci-profiler.phar
; Or using source:
; auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

### PHP-FPM (Nginx)

```nginx
server {
    server_name staging.myapp.local;
    root /var/www/myapp/public;

    location ~ \.php$ {
        # Using phar:
        fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler.phar";
        # Or using source:
        # fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php";
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Docker (Laravel Sail)

In your `docker-compose.yml` or Dockerfile:

```dockerfile
# Dockerfile — using phar:
COPY sci-profiler.phar /opt/sci-profiler.phar
RUN echo "auto_prepend_file=/opt/sci-profiler.phar" \
    >> /usr/local/etc/php/conf.d/sci-profiler.ini

# Or using source:
# COPY sci-profiler-php /opt/sci-profiler-php
# RUN echo "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php" \
#     >> /usr/local/etc/php/conf.d/sci-profiler.ini
```

Or mount via volume and set the env:

```yaml
# docker-compose.yml
services:
  laravel.test:
    volumes:
      - ./sci-profiler.phar:/opt/sci-profiler.phar
    environment:
      PHP_VALUE: "auto_prepend_file=/opt/sci-profiler.phar"
```

### Artisan commands

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  artisan emails:send-digest
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   artisan emails:send-digest
```

### Queue workers

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  artisan queue:work --max-jobs=100
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   artisan queue:work --max-jobs=100
```

Each job processed by the worker is a separate PHP execution context. The profiler measures the full worker lifecycle per invocation. For granular per-job measurement, use `queue:work --max-jobs=1` or `queue:listen`.

## Configuration Example

```php
<?php
// /opt/sci-profiler-php/config/sci-profiler.local.php
return [
    'enabled'               => true,
    'device_power_watts'    => 18.0,
    'grid_carbon_intensity' => 390.0,      // USA average
    'output_dir'            => storage_path('sci-profiler'),
    'reporters'             => ['json', 'log', 'html'],
];
```

Or via `.env` in a staging environment:

```dotenv
SCI_PROFILER_ENABLED=1
SCI_PROFILER_DEVICE_POWER_WATTS=18
SCI_PROFILER_GRID_CARBON_INTENSITY=390
SCI_PROFILER_OUTPUT_DIR=/var/www/myapp/storage/sci-profiler
SCI_PROFILER_REPORTERS=json,log,html
```

## Analyzing Routes

### Group by route pattern

Laravel URIs include parameters. Group by pattern for meaningful comparison:

```bash
# Normalize URIs and compute average SCI per route
cat /var/www/myapp/storage/sci-profiler/sci-profiler.jsonl \
  | jq -r '{
      uri: (.["request.uri"] | split("?")[0] | gsub("/[0-9]+"; "/{id}")),
      method: .["request.method"],
      sci: .["sci.sci_mgco2eq"],
      time: .["time.wall_time_ms"]
    }' \
  | jq -s '
    group_by(.method + " " + .uri)
    | map({
        route: (.[0].method + " " + .[0].uri),
        count: length,
        avg_sci_mgco2eq: (map(.sci) | add / length | . * 1000 | round / 1000),
        avg_time_ms: (map(.time) | add / length | round)
      })
    | sort_by(-.avg_sci_mgco2eq)
  '
```

Sample output:

```json
[
  { "route": "POST /api/reports/{id}/export", "count": 12, "avg_sci_mgco2eq": 8.234, "avg_time_ms": 1523 },
  { "route": "GET /dashboard",                "count": 45, "avg_sci_mgco2eq": 1.102, "avg_time_ms": 204 },
  { "route": "GET /api/v1/users",             "count": 89, "avg_sci_mgco2eq": 0.451, "avg_time_ms": 83 },
  { "route": "POST /login",                   "count": 15, "avg_sci_mgco2eq": 0.312, "avg_time_ms": 58 }
]
```

### Compare web vs API

```bash
cat /var/www/myapp/storage/sci-profiler/sci-profiler.jsonl \
  | jq -s '
    group_by(.["request.uri"] | test("^/api/"))
    | map({
        type: (if .[0]["request.uri"] | test("^/api/") then "API" else "Web" end),
        requests: length,
        avg_sci: (map(.["sci.sci_mgco2eq"]) | add / length),
        total_sci: (map(.["sci.sci_mgco2eq"]) | add)
      })
  '
```

### Artisan commands vs HTTP requests

```bash
cat /var/www/myapp/storage/sci-profiler/sci-profiler.jsonl \
  | jq -s '
    group_by(.["request.method"] == "CLI")
    | map({
        type: (if .[0]["request.method"] == "CLI" then "Artisan/CLI" else "HTTP" end),
        count: length,
        avg_sci: (map(.["sci.sci_mgco2eq"]) | add / length),
        max_sci: (map(.["sci.sci_mgco2eq"]) | max)
      })
  '
```

## Common Laravel Optimization Insights

| Finding | Common Cause | Optimization |
|---------|-------------|--------------|
| High SCI on first request after deploy | Config/route/view cache not warmed | Run `artisan optimize` in deploy pipeline |
| API responses slow despite simple logic | N+1 queries on Eloquent relationships | Use `with()` eager loading, install Laravel Debugbar in staging |
| Artisan commands dominate total emissions | Long-running jobs processing all records | Chunk processing, use `LazyCollection`, optimize batch queries |
| Queue worker high per-job SCI | Worker re-bootstraps on each job | Use `queue:work` (persistent) instead of `queue:listen` |
| File upload routes 10x other endpoints | Synchronous image processing | Move to queued jobs, use image optimization libraries |
| Dashboard SCI varies widely | Uncached aggregate queries | Add `Cache::remember()` for dashboard widgets |

## Carbon Budget in Laravel Tests

Add a SCI budget check to your CI pipeline:

```bash
#!/bin/bash
# scripts/sci-budget-check.sh

# Run test suite with profiler enabled
php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
  artisan test --testsuite=Feature 2>&1

# Check budget: no single request should exceed 10 mgCO2eq
MAX_SCI=10.0
VIOLATIONS=$(cat /tmp/sci-profiler/sci-profiler.jsonl \
  | jq "select(.[\"sci.sci_mgco2eq\"] > $MAX_SCI)" \
  | jq -r '.["request.uri"]' \
  | sort -u)

if [ -n "$VIOLATIONS" ]; then
    echo "SCI budget exceeded on:"
    echo "$VIOLATIONS"
    exit 1
fi

echo "All requests within SCI budget of $MAX_SCI mgCO2eq"
```
