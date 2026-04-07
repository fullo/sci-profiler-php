# Configuration

SCI Profiler PHP can be configured via PHP file, environment variables, or built-in defaults.

## Priority Order

Configuration is loaded in this priority order (first match wins):

1. **`SCI_PROFILER_CONFIG_FILE`** environment variable pointing to a PHP config file
2. **Default config file** at `<profiler-root>/config/sci-profiler.php`
3. **Environment variables** prefixed with `SCI_PROFILER_`
4. **Built-in defaults**

## Via PHP File

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
    'device_power_watts'    => 18.0,
    'grid_carbon_intensity' => 332.0,
    'embodied_carbon'       => 211000.0,
    'device_lifetime_hours' => 11680.0,
    'machine_description'   => 'MacBook Pro M1 14"',
    'lca_source'            => 'Apple Environmental Report 2022',
    'output_dir'            => '/tmp/sci-profiler',
    'reporters'             => ['json', 'log', 'html'],
];
```

## Via Environment Variables

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

## Reference

| Parameter | Env Variable | Default | Description |
|-----------|-------------|---------|-------------|
| `enabled` | `SCI_PROFILER_ENABLED` | `true` | Enable/disable profiling without removing `auto_prepend_file` |
| `device_power_watts` | `SCI_PROFILER_DEVICE_POWER_WATTS` | `18.0` | Average device power consumption in watts (TDP or measured) |
| `grid_carbon_intensity` | `SCI_PROFILER_GRID_CARBON_INTENSITY` | `332.0` | Grid carbon intensity in gCO2eq/kWh |
| `embodied_carbon` | `SCI_PROFILER_EMBODIED_CARBON` | `211000.0` | Total embodied carbon of the device in gCO2eq |
| `device_lifetime_hours` | `SCI_PROFILER_DEVICE_LIFETIME_HOURS` | `11680.0` | Expected device operational lifetime in hours |
| `machine_description` | `SCI_PROFILER_MACHINE_DESCRIPTION` | `Default development machine` | Human-readable label for reports and dashboard |
| `lca_source` | `SCI_PROFILER_LCA_SOURCE` | `Estimated` | Reference for the embodied carbon data source |
| `output_dir` | `SCI_PROFILER_OUTPUT_DIR` | `/tmp/sci-profiler` | Directory where reporters write results (must be writable) |
| `reporters` | `SCI_PROFILER_REPORTERS` | `json` | Comma-separated list of reporters: `json`, `log`, `html`, `trend` |

## Finding Your Grid Carbon Intensity

The `grid_carbon_intensity` parameter has the biggest impact on SCI scores.

### Built-in helper

SCI Profiler PHP includes a `GridCarbonData` class with carbon intensity values for 60+ countries, sourced from [Ember Climate](https://ember-energy.org/) (2024, CC BY 4.0). You can look up values programmatically:

```php
use SciProfiler\GridCarbonData;

// Auto-detect from system timezone (e.g., Europe/Rome → Italy → 324)
$detected = GridCarbonData::detectFromSystem();

// Look up by country code
GridCarbonData::forCountry('DE');  // 298 (Germany)
GridCarbonData::forCountry('FR');  //  33 (France)

// Look up by PHP timezone
GridCarbonData::forTimezone('America/New_York');  // 348 (US average)
```

See the full [country reference table](grid-carbon-intensity.md) for all values and update instructions.

### Common values

| Region | gCO2eq/kWh | Notes |
|--------|-----------|-------|
| Norway | 27 | Hydro-heavy grid |
| France | 33 | Nuclear-heavy grid |
| Sweden | 32 | Hydro + nuclear |
| Spain | 126 | Growing renewables |
| United Kingdom | 229 | Offshore wind growth |
| Germany | 298 | Coal phase-out in progress |
| Italy | 324 | Gas-heavy grid |
| United States | 348 | National average; varies by region |
| India | 595 | Coal-dominated |
| GitHub Actions | 332 | Estimated median for cloud |

### External sources

- [Electricity Maps](https://app.electricitymaps.com/) — real-time carbon intensity
- [Ember Climate](https://ember-energy.org/data/yearly-electricity-data/) — annual averages, CC BY 4.0
- [Our World in Data](https://ourworldindata.org/grapher/carbon-intensity-electricity) — historical trends

## Finding Your Device Power

| Device | Watts |
|--------|-------|
| MacBook Air M1 | 10 |
| MacBook Pro M1 14" | 18 |
| Desktop workstation | 65–150 |
| Cloud VM (shared, small) | 5–15 |
| Cloud VM (dedicated, large) | 50–100 |

## Finding Your Embodied Carbon

| Device | gCO2eq | Source |
|--------|--------|--------|
| MacBook Pro 14" M1 | 211,000 | Apple Environmental Report |
| Dell Latitude 5530 | 320,000 | Dell Product Carbon Footprint |
| Cloud VM (shared) | 50,000–100,000 | Estimated |

## Enabling in Different Environments

### php.ini (global)

```ini
; Using phar (no dependencies):
auto_prepend_file = /opt/sci-profiler.phar
; Or using source (requires composer install):
auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

### Apache VirtualHost

```apache
<VirtualHost *:80>
    ServerName staging.example.com
    DocumentRoot /var/www/myapp/public
    # Using phar:
    php_value auto_prepend_file "/opt/sci-profiler.phar"
    # Or using source:
    # php_value auto_prepend_file "/opt/sci-profiler-php/src/bootstrap.php"
</VirtualHost>
```

### PHP-FPM pool

```ini
[staging]
; Using phar:
php_value[auto_prepend_file] = /opt/sci-profiler.phar
; Or using source:
; php_value[auto_prepend_file] = /opt/sci-profiler-php/src/bootstrap.php
```

### Nginx + PHP-FPM

```nginx
location ~ \.php$ {
    # Using phar:
    fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler.phar";
    # Or using source:
    # fastcgi_param PHP_VALUE "auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php";
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    include fastcgi_params;
}
```

### Laravel Herd / Valet

```ini
; public/.user.ini
; Using phar:
auto_prepend_file = /opt/sci-profiler.phar
; Or using source:
; auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
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

### PHP built-in server

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar -S localhost:8000 -t public/
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php -S localhost:8000 -t public/
```

### Disable without removing

```bash
export SCI_PROFILER_ENABLED=0
```
