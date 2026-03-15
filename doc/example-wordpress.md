# SCI Profiler PHP — WordPress

Guide to measuring the carbon footprint of a WordPress site with SCI Profiler PHP.

## Use Cases and Functional Units

In WordPress every page load is a functional unit. The SCI score for `GET /hello-world/` includes everything WordPress does: loading `wp-load.php`, running plugin hooks, querying the database, rendering the theme template, and sending the response.

### Typical use cases

| Use Case | HTTP Request | What Happens Inside |
|----------|-------------|---------------------|
| **Blog post view** | `GET /2026/03/my-post/` | `wp-load.php` bootstrap, `WP_Query`, theme template hierarchy, sidebar widgets, `wp_footer` hooks, output buffering |
| **Homepage** | `GET /` | Front page query, featured posts, menu rendering, header/footer, all active plugin filters |
| **WooCommerce product page** | `GET /product/red-shirt/` | Product query, variable product data, related products, price calculation, add-to-cart form |
| **WooCommerce checkout** | `POST /checkout/` | Cart validation, shipping calculation, payment gateway API call, order creation, order confirmation email |
| **Admin dashboard** | `GET /wp-admin/` | Admin bootstrap, dashboard widgets, update checks, plugin notices |
| **REST API call** | `GET /wp-json/wp/v2/posts` | REST controller, permission check, `WP_Query`, serialization |
| **AJAX heartbeat** | `POST /wp-admin/admin-ajax.php` | Heartbeat API, autosave check, session refresh |
| **WP-Cron task** | `GET /wp-cron.php` | Scheduled hooks: post publication, plugin tasks, transient cleanup |

Each of these is an independent functional unit measured by the profiler.

## Setup

### Standard WordPress (Apache)

```apache
# /etc/apache2/sites-available/staging-wordpress.conf
<VirtualHost *:80>
    ServerName staging.myblog.local
    DocumentRoot /var/www/wordpress

    # Enable SCI profiling (staging only!)
    # Using phar:
    php_value auto_prepend_file "/opt/sci-profiler.phar"
    # Or using source:
    # php_value auto_prepend_file "/opt/sci-profiler-php/src/bootstrap.php"
</VirtualHost>
```

### WordPress with PHP-FPM (Nginx)

```nginx
# /etc/nginx/sites-available/staging-wordpress
server {
    server_name staging.myblog.local;
    root /var/www/wordpress;

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

### Laravel Valet / Herd

Create a `.user.ini` in the WordPress root:

```ini
; /var/www/wordpress/.user.ini
; Using phar:
auto_prepend_file = /opt/sci-profiler.phar
; Or using source:
; auto_prepend_file = /opt/sci-profiler-php/src/bootstrap.php
```

### wp-cli (CLI commands)

```bash
# Using phar:
php -d auto_prepend_file=/opt/sci-profiler.phar \
  /usr/local/bin/wp cron event run --all
# Or using source:
# php -d auto_prepend_file=/opt/sci-profiler-php/src/bootstrap.php \
#   /usr/local/bin/wp cron event run --all
```

## Configuration Example

```php
<?php
// /opt/sci-profiler-php/config/sci-profiler.local.php
return [
    'enabled'               => true,
    'device_power_watts'    => 18.0,
    'grid_carbon_intensity' => 385.0,      // Germany
    'output_dir'            => '/var/www/wordpress/wp-content/sci-profiler',
    'reporters'             => ['json', 'log', 'html'],
];
```

> Make sure the output directory is not publicly accessible, or add a deny rule in `.htaccess`:
>
> ```apache
> # wp-content/sci-profiler/.htaccess
> Deny from all
> ```

## Identifying High-Impact Use Cases

After collecting data from a browsing session, analyze by URI pattern:

```bash
# Top 10 highest-SCI endpoints
cat /var/www/wordpress/wp-content/sci-profiler/sci-profiler.jsonl \
  | jq -r '[.["request.uri"], .["sci.sci_mgco2eq"], .["time.wall_time_ms"]] | @tsv' \
  | sort -t$'\t' -k2 -rn \
  | head -10
```

```bash
# Average SCI per URI pattern
cat /var/www/wordpress/wp-content/sci-profiler/sci-profiler.jsonl \
  | jq -r '.["request.uri"]' \
  | sort | uniq -c | sort -rn | head -20
```

```bash
# Compare front-end page load vs admin
cat /var/www/wordpress/wp-content/sci-profiler/sci-profiler.jsonl \
  | jq -s '
    group_by(.["request.uri"] | test("wp-admin"))
    | map({
        area: (if .[0]["request.uri"] | test("wp-admin") then "admin" else "frontend" end),
        avg_sci: (map(.["sci.sci_mgco2eq"]) | add / length),
        count: length
      })
  '
```

## Common WordPress Optimization Insights

What the SCI profiler typically reveals in WordPress:

| Finding | Common Cause | Optimization |
|---------|-------------|--------------|
| High SCI on every page | Too many active plugins, each adding hooks and queries | Audit and disable unused plugins |
| `admin-ajax.php` dominates | Heartbeat API firing every 15-60 seconds | Increase heartbeat interval or disable on front-end |
| `wp-cron.php` spikes | Accumulated scheduled tasks running on page load | Switch to system cron (`define('DISABLE_WP_CRON', true);`) |
| Product pages 5x blog posts | WooCommerce related products query, variable product data | Enable object cache (Redis/Memcached), limit related products |
| High memory on homepage | Loading all posts with full content for excerpts | Use `WP_Query` with `'fields' => 'ids'` or proper pagination |
| Slow REST API responses | Missing persistent object cache, no query result caching | Install Redis object cache, add `Cache-Control` headers |

## Full Page Load Measurement

A single WordPress page load may trigger multiple requests. To measure the total carbon cost of a user viewing a page:

```bash
# Simulate a full page load and measure
# 1. The main HTML page
curl -s http://staging.myblog.local/2026/03/my-post/ > /dev/null

# 2. Check what PHP-generated resources the page loads
curl -s http://staging.myblog.local/2026/03/my-post/ \
  | grep -oP 'src="[^"]*\.php[^"]*"' \
  | sed 's/src="//;s/"//' \
  | while read url; do curl -s "$url" > /dev/null; done

# 3. Aggregate the SCI for all requests in the last 30 seconds
cat /var/www/wordpress/wp-content/sci-profiler/sci-profiler.jsonl \
  | jq -s 'map(.["sci.sci_mgco2eq"]) | {total_mgco2eq: add, requests: length}'
```

## WooCommerce Checkout Journey

To measure the complete carbon cost of a purchase flow:

```bash
# Filter requests from a checkout session
cat /var/www/wordpress/wp-content/sci-profiler/sci-profiler.jsonl \
  | jq 'select(.["request.uri"] | test("/cart|/checkout|wc-ajax|payment"))' \
  | jq -s '{
      journey: "checkout",
      steps: length,
      total_mgco2eq: map(.["sci.sci_mgco2eq"]) | add,
      total_time_ms: map(.["time.wall_time_ms"]) | add,
      heaviest: max_by(.["sci.sci_mgco2eq"]) | .["request.uri"]
    }'
```
