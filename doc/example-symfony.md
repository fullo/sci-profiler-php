# SCI Profiler PHP — Symfony

Guide to measuring the carbon footprint of a Symfony application with SCI Profiler PHP.

## Use Cases and Functional Units

In Symfony every HTTP request handled by the kernel and every console command execution is a functional unit. The SCI score for `GET /admin/products` includes the entire Symfony lifecycle: kernel boot, event listeners, security firewall, controller invocation, Doctrine queries, Twig rendering, and response.

### Typical use cases

| Use Case | Functional Unit | What Happens Inside |
|----------|----------------|---------------------|
| **Product listing page** | `GET /products` | Kernel boot, router matching, security voter, controller, Doctrine DQL, Twig template, HTTP cache headers |
| **API platform resource** | `GET /api/products` | API Platform data provider, serialization, content negotiation, JSON-LD output |
| **Form submission** | `POST /contact` | CSRF token validation, form handling, Doctrine flush, event dispatch, redirect response |
| **Admin panel (EasyAdmin)** | `GET /admin/products` | EasyAdmin controller, Doctrine QueryBuilder, CRUD template rendering |
| **Console command** | `bin/console app:import-catalog` | Console kernel boot, service wiring, batch DB operations, progress output |
| **Messenger worker** | `bin/console messenger:consume async` | Worker loop, message deserialization, handler invocation, ack/reject |
| **Webhook receiver** | `POST /webhook/stripe` | Payload validation, signature verification, event handling, DB updates |

### What is NOT a functional unit in Symfony

| NOT a Functional Unit | Why |
|----------------------|-----|
| `ProductRepository::findByCategory()` | Internal Doctrine repository method, part of a larger controller action |
| A single Doctrine DQL query | One step in serving a page, not the use case itself |
| `Twig::render('product/list.html.twig')` | Template rendering is one phase of the request, not the complete operation |
| An event listener execution | A hook in the kernel lifecycle, not a user intent |
| `MessageBusInterface::dispatch()` | Internal dispatch mechanism; the message handler processing is the real unit |

## Setup

### Symfony local server

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  -S 127.0.0.1:8000 -t public/
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   -S 127.0.0.1:8000 -t public/
```

Or if using the Symfony CLI:

```bash
# Create a php.ini override for the Symfony server
echo "auto_prepend_file=/opt/sci-profiler.phar" > php.ini.local
# Or with source: echo "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php" > php.ini.local
symfony server:start --php-ini=php.ini.local
```

### PHP-FPM (Nginx)

```nginx
server {
    server_name staging.myapp.local;
    root /var/www/myapp/public;

    location ~ ^/index\.php(/|$) {
        # Using phar:
        fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler.phar";
        # Or using source:
        # fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php";
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        internal;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName staging.myapp.local
    DocumentRoot /var/www/myapp/public

    # Using phar:
    php_value auto_prepend_file "/opt/sci-profiler.phar"
    # Or using source:
    # php_value auto_prepend_file "/opt/sci-profiler-php/src/bootstrap.php"

    <Directory /var/www/myapp/public>
        AllowOverride All
    </Directory>
</VirtualHost>
```

### Docker

```dockerfile
# Using phar:
COPY sci-profiler.phar /opt/sci-profiler.phar
RUN echo "auto_prepend_file=/opt/sci-profiler.phar" \
    >> /usr/local/etc/php/conf.d/sci-profiler.ini

# Or using source:
# COPY sci-profiler-php /opt/sci-profiler-php
# RUN echo "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php" \
#     >> /usr/local/etc/php/conf.d/sci-profiler.ini
```

### Console commands

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  bin/console app:import-catalog --env=staging
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   bin/console app:import-catalog --env=staging
```

### Messenger workers

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  bin/console messenger:consume async --limit=100 --time-limit=300
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   bin/console messenger:consume async --limit=100 --time-limit=300
```

The profiler measures the entire worker process lifecycle. For per-message granularity, use `--limit=1` or analyze the worker's total SCI divided by messages processed.

## Configuration Example

```php
<?php
// /opt/sci-profiler-php/config/sci-profiler.local.php
return [
    'enabled'               => true,
    'device_power_watts'    => 18.0,
    'grid_carbon_intensity' => 56.0,       // France
    'output_dir'            => '/var/www/myapp/var/sci-profiler',
    'reporters'             => ['json', 'log', 'html'],
];
```

Or via environment (`.env.staging`):

```dotenv
SCI_PROFILER_ENABLED=1
SCI_PROFILER_DEVICE_POWER_WATTS=18
SCI_PROFILER_GRID_CARBON_INTENSITY=56
SCI_PROFILER_OUTPUT_DIR=%kernel.project_dir%/var/sci-profiler
SCI_PROFILER_REPORTERS=json,log,html
```

> **Note**: `%kernel.project_dir%` is a Symfony parameter and is not resolved in plain environment variables. Use an absolute path or set it in the PHP config file instead.

## Analyzing Routes

### Group by Symfony route

Symfony URIs contain dynamic parameters. Normalize them for aggregation:

```bash
# Normalize IDs and UUIDs in URIs, then compute average SCI per route
cat /var/www/myapp/var/sci-profiler/sci-profiler.jsonl \
  | jq -r '{
      uri: (.["request.uri"]
        | split("?")[0]
        | gsub("/[0-9]+"; "/{id}")
        | gsub("/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"; "/{uuid}")),
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

### API Platform endpoints vs Twig pages

```bash
cat /var/www/myapp/var/sci-profiler/sci-profiler.jsonl \
  | jq -s '
    group_by(.["request.uri"] | test("^/api/"))
    | map({
        type: (if .[0]["request.uri"] | test("^/api/") then "API Platform" else "Twig pages" end),
        requests: length,
        avg_sci: (map(.["sci.sci_mgco2eq"]) | add / length | . * 1000 | round / 1000),
        total_sci: (map(.["sci.sci_mgco2eq"]) | add | . * 1000 | round / 1000)
      })
  '
```

### Console commands analysis

```bash
cat /var/www/myapp/var/sci-profiler/sci-profiler.jsonl \
  | jq 'select(.["request.method"] == "CLI")' \
  | jq -s '
    sort_by(-.["sci.sci_mgco2eq"])
    | map({
        command: .["request.uri"],
        sci_mgco2eq: .["sci.sci_mgco2eq"],
        time_ms: .["time.wall_time_ms"],
        peak_mb: .["memory.memory_peak_mb"]
      })
  '
```

## Common Symfony Optimization Insights

| Finding | Common Cause | Optimization |
|---------|-------------|--------------|
| High SCI on first request after deploy | Symfony container not compiled, no warmed cache | Run `bin/console cache:warmup --env=prod` in deploy |
| Admin panel 5x frontend | EasyAdmin loading all CRUD metadata, unoptimized list queries | Add pagination limits, use custom DQL for heavy lists |
| API collection endpoints slow | API Platform fetching full entities with nested relations | Use DTO output classes, custom data providers with SELECT optimization |
| Messenger worker high total SCI | Worker bootstraps full container for each message batch | Tune `--limit` and `--time-limit`, use worker restart on deploy |
| Console imports dominate emissions | Processing all records in single pass | Use `doctrine:query` batching, `EntityManager::clear()` every N records |
| Twig rendering slow | Complex template inheritance with many includes | Use Twig cache (default in prod), simplify block hierarchy, use `render_esi` for heavy fragments |
| Event listeners add overhead | Doctrine lifecycle listeners firing on every flush | Use lazy listeners, limit `postFlush` logic, batch operations |

## Profiling with Symfony Profiler Side-by-Side

SCI Profiler PHP complements the Symfony Web Profiler. Use both together:

- **Symfony Profiler**: shows detailed internal breakdown (Doctrine queries, Twig renders, event listeners, HTTP client calls)
- **SCI Profiler**: shows the carbon cost of the same request

This combination lets you identify **which internal operations** drive the carbon cost of a route, and then use Symfony's tools to optimize them.

```bash
# Find the highest-SCI routes
cat /var/www/myapp/var/sci-profiler/sci-profiler.jsonl \
  | jq -s 'sort_by(-.["sci.sci_mgco2eq"]) | .[0:5] | map(.["request.uri"])' \

# Then inspect those routes in the Symfony Profiler at:
# http://staging.myapp.local/_profiler/
```

## Carbon Budget in CI

```yaml
# .github/workflows/sci-budget.yml
name: SCI Budget Check

on: [pull_request]

jobs:
  sci-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Setup SCI Profiler
        run: |
          git clone https://github.com/fullo/sci-profiler-php.git /tmp/sci-profiler-php
          cd /tmp/sci-profiler-php && composer install --no-dev

      - name: Run functional tests with profiling
        run: |
          php -d auto_prepend_file=/tmp/sci-profiler-php/src/bootstrap.php \
            bin/phpunit --testsuite=functional
        env:
          SCI_PROFILER_ENABLED: 1
          SCI_PROFILER_OUTPUT_DIR: /tmp/sci-results
          SCI_PROFILER_REPORTERS: json

      - name: Check SCI budget
        run: |
          MAX_SCI=10.0
          VIOLATIONS=$(jq "select(.[\"sci.sci_mgco2eq\"] > $MAX_SCI)" \
            /tmp/sci-results/sci-profiler.jsonl | wc -l)
          if [ "$VIOLATIONS" -gt 0 ]; then
            echo "::error::$VIOLATIONS requests exceeded SCI budget of $MAX_SCI mgCO2eq"
            jq "select(.[\"sci.sci_mgco2eq\"] > $MAX_SCI) | [.\"request.method\", .\"request.uri\", .\"sci.sci_mgco2eq\"]" \
              /tmp/sci-results/sci-profiler.jsonl
            exit 1
          fi
```
