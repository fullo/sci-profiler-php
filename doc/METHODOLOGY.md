# Methodology

This document describes the technical methodology used by SCI Profiler PHP to calculate the Software Carbon Intensity (SCI) of PHP requests.

## Overview

SCI Profiler PHP implements the [Green Software Foundation's SCI specification](https://sci-guide.greensoftware.foundation/) to quantify the carbon emissions of individual PHP requests. The SCI score expresses **how much carbon is emitted per functional unit** (one HTTP request or CLI execution).

## The SCI Formula

```
SCI = ((E × I) + M) / R
```

| Component | Description | Unit |
|-----------|-------------|------|
| **E** | Energy consumed by the request | kWh |
| **I** | Location-based grid carbon intensity | gCO2eq/kWh |
| **M** | Embodied carbon amortized to the request | gCO2eq |
| **R** | Functional unit | 1 request |

The result is expressed in **gCO2eq per request** (or mgCO2eq for readability).

## Component Calculations

### E — Energy

Energy is estimated from the device's power consumption and the request's wall time:

```
E (kWh) = DevicePower (W) × WallTime (s) / 3,600,000
```

**Wall time** is measured using PHP's `hrtime(true)`, which provides nanosecond-precision monotonic clock readings. The measurement spans from the earliest point in the request lifecycle (`auto_prepend_file` execution) to the shutdown function.

**Device power** is a configured parameter representing the average power draw of the machine in watts. This is the Thermal Design Power (TDP) or a measured average.

#### Supplementary: CPU time

When available, `getrusage()` provides actual CPU time (user + system), which is recorded alongside wall time for analysis purposes. However, **wall time is used for energy calculation** because:

- It accounts for all system activity during the request (I/O wait, network, etc.)
- It represents the time the machine was "occupied" by this request
- `getrusage()` is not available on all platforms

### I — Grid Carbon Intensity

The marginal carbon intensity of the electricity grid where the server is located, expressed in gCO2eq per kWh.

This is a **configured parameter**. Regional values can be found at:

- [Electricity Maps](https://app.electricitymaps.com/) — real-time data
- [Our World in Data](https://ourworldindata.org/grapher/carbon-intensity-electricity) — country averages
- [EMBER Climate](https://ember-climate.org/data/) — historical data

**Default value**: 332 gCO2eq/kWh (global median estimate for cloud workloads).

#### Example regional values

| Region | gCO2eq/kWh | Source |
|--------|-----------|--------|
| France | 56 | Electricity Maps (nuclear-heavy grid) |
| Norway | 26 | Electricity Maps (hydro-heavy grid) |
| Germany | 385 | Electricity Maps |
| USA average | 390 | EPA eGRID |
| India | 632 | CEA |
| GitHub Actions | 332 | Estimated median |

### M — Embodied Carbon

Embodied carbon represents the total greenhouse gas emissions from manufacturing, transporting, and disposing of the hardware. It is amortized across the device's operational lifetime and then proportioned to the request's duration:

```
M (gCO2eq) = (TotalEmbodiedCarbon / DeviceLifetimeHours) × (WallTime / 3600)
```

**Total embodied carbon** is sourced from manufacturer LCA (Life Cycle Assessment) reports or estimated. Examples:

| Device | Embodied Carbon (gCO2eq) | Source |
|--------|-------------------------|--------|
| MacBook Pro 14" M1 | 211,000 | Apple Environmental Report |
| Dell Latitude 5530 | 320,000 | Dell Product Carbon Footprint |
| Typical cloud VM (shared) | 50,000–100,000 | Estimated |

**Device lifetime** is the expected operational lifetime in hours. Default: 11,680 hours (4 years × 8 hours/day × 365 days).

### R — Functional Unit

The functional unit **R** is the denominator of the SCI formula and defines **what you are measuring the carbon cost of**. Choosing the right functional unit is critical — it determines whether the SCI score is meaningful and actionable.

In SCI Profiler PHP, the functional unit is **one use case execution**: the complete end-to-end handling of a user-visible operation. This is not the execution of a single PHP method or class, but the entire chain of operations that PHP performs to fulfill a user's intent.

#### What a functional unit IS

A functional unit maps to a **user-facing operation** — something a real user or system triggers and expects a result from:

| Use Case | Functional Unit | What Gets Measured |
|----------|----------------|-------------------|
| Visitor loads a WordPress blog post | `GET /2026/03/my-post/` | Theme loading, database queries, plugin execution, template rendering, response output |
| Customer completes a WooCommerce checkout | `POST /checkout/` | Cart validation, payment gateway call, order creation, email dispatch, redirect |
| Admin publishes a Drupal article | `POST /node/add/article` | Form validation, node save, taxonomy indexing, cache clear, hook execution |
| API client fetches a user list | `GET /api/v1/users` | Authentication, authorization, Eloquent queries, serialization, JSON response |
| Cron job sends daily digest emails | `php artisan emails:send-digest` | Database scan, template rendering, SMTP calls for each recipient |

#### What a functional unit is NOT

A functional unit is **not** a single method call, a database query, or an internal class operation:

| NOT a Functional Unit | Why |
|----------------------|-----|
| `UserRepository::findById(42)` | Internal implementation detail, not a user-visible operation |
| A single SQL `SELECT` query | Part of a larger use case; measuring it alone gives no actionable SCI score |
| `CacheManager::flush()` | Infrastructure operation, not a user intent |
| `Twig::render('base.html.twig')` | One step in serving a page, not the page itself |

The distinction matters because the SCI specification asks: **"How much carbon does it cost to perform this operation for one user?"** — not "how much carbon does a single function call consume."

#### Why this approach

SCI Profiler PHP measures at the HTTP request / CLI execution boundary because:

1. **It matches the GSF specification**: the SCI score should quantify the rate of carbon emissions as a function of a use case
2. **It captures the full cost**: database queries, file I/O, external API calls, template rendering, plugin hooks — everything PHP does to serve the request
3. **It is actionable**: teams can compare the carbon cost of `/checkout` vs `/product-list` and prioritize optimization
4. **It is non-invasive**: no code instrumentation needed — the HTTP request boundary is the natural unit

#### Aggregating use cases

A single page load often triggers multiple HTTP requests (AJAX calls, API fetches). To measure the SCI of a complete user journey, aggregate related requests:

```bash
# Total SCI for a WordPress post page load (page + AJAX + REST API calls)
cat /tmp/sci-profiler/sci-profiler.jsonl \
  | jq 'select(.["request.uri"] | test("/2026/03/my-post|/wp-admin/admin-ajax|/wp-json"))' \
  | jq -s '{total_mgco2eq: map(.["sci.sci_mgco2eq"]) | add, requests: length}'
```

See the framework-specific guides for practical examples:

- [WordPress](example-wordpress.md) — blog, WooCommerce, admin panel
- [Laravel](example-laravel.md) — web routes, API, Artisan commands, queues
- [Symfony](example-symfony.md) — controllers, console commands, Messenger workers

## Measurement Points

### Timeline

```
┌─ auto_prepend_file loaded ─────────────────────────────────────────┐
│                                                                     │
│  ┌─ Collectors start ──┐                                           │
│  │  • hrtime(true)      │  ← T₀                                   │
│  │  • memory_get_usage  │                                           │
│  │  • $_SERVER read     │                                           │
│  │  • php://input read  │                                           │
│  └──────────────────────┘                                           │
│                                                                     │
│  ┌─ Application code executes ─────────────────────────────────┐   │
│  │  (Laravel, Symfony, WordPress, custom PHP, etc.)             │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─ register_shutdown_function ─────────────────────────────────┐  │
│  │  ┌─ Collectors stop ──────┐                                   │  │
│  │  │  • hrtime(true)         │  ← T₁                           │  │
│  │  │  • memory_get_peak_usage│                                   │  │
│  │  │  • http_response_code   │                                   │  │
│  │  │  • ob_get_length        │                                   │  │
│  │  └────────────────────────┘                                   │  │
│  │                                                                │  │
│  │  WallTime = T₁ - T₀                                           │  │
│  │  E = DevicePower × WallTime / 3,600,000                       │  │
│  │  SCI = ((E × I) + M) / 1                                      │  │
│  │                                                                │  │
│  │  ┌─ Reporters write ─────┐                                    │  │
│  │  │  • JSONL file          │                                    │  │
│  │  │  • Log file            │                                    │  │
│  │  │  • HTML dashboard      │                                    │  │
│  │  └───────────────────────┘                                    │  │
│  └────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

### Profiler overhead

The profiler itself consumes resources. This overhead is included in the measurement (wall time spans from prepend load to shutdown). In practice:

- **Time overhead**: ~0.1–0.5 ms per request (autoloader, config load, collector start/stop)
- **Memory overhead**: ~200–500 KB (profiler objects, reporter buffers)
- **I/O overhead**: 1–3 file write operations per request (depending on reporters)

For typical web requests (50–500 ms), the profiler overhead is < 1% of total wall time.

## Assumptions

### Power consumption is constant

We assume the device draws a constant amount of power during the request. In reality, power consumption varies with CPU frequency scaling, thermal throttling, and workload type. For short requests (< 1 second), this approximation is reasonable because:

- Modern CPUs reach full frequency within microseconds
- Short bursts don't trigger significant thermal throttling
- TDP is designed as an average over typical workloads

### Wall time approximates energy usage

Wall time is used as a proxy for energy consumption because:

- Direct power measurement requires hardware sensors or RAPL (not available in PHP userspace)
- Wall time correlates with energy for CPU-bound and I/O-bound workloads
- It captures the full cost of the request, including system calls and I/O wait

### Single-device attribution

The SCI calculation attributes the full device power to the profiled PHP process. On shared servers, this overestimates per-process energy. For more accurate multi-tenant estimation, reduce `device_power_watts` proportionally to the fraction of resources allocated to PHP.

### Embodied carbon is linearly amortized

We distribute embodied carbon uniformly across the device's lifetime. This is a simplification — real environmental impact varies with manufacturing, recycling, and disposal practices.

## Limitations

1. **No real-time power measurement**: Energy is estimated from TDP and wall time, not measured directly. On platforms supporting RAPL (Running Average Power Limit), a future collector could provide more accurate readings.

2. **GPU excluded**: The profiler does not account for GPU power consumption. For GPU-intensive PHP workloads (rare), this would underestimate energy.

3. **Network transfer not modeled**: The energy cost of transmitting request/response data over the network is not included. I/O bytes are collected for future use.

4. **Run-to-run variance**: Individual measurements vary due to system load, caching, garbage collection, and OPcache state. Aggregate metrics over many requests for meaningful analysis.

5. **Profiler overhead**: The measurement includes the profiler's own execution time and memory usage. This is typically < 1% of total request time but may be significant for very fast requests (< 5 ms).

6. **Shutdown function ordering**: PHP's `register_shutdown_function` callbacks execute in registration order. If the application registers its own shutdown functions, their execution time is included in the measurement.

## Interpreting Results

### SCI score ranges (typical web requests)

| Score (mgCO2eq) | Interpretation |
|-----------------|----------------|
| < 0.1 | Very low — fast, efficient request |
| 0.1 – 1.0 | Low — typical API endpoint or simple page |
| 1.0 – 10.0 | Moderate — complex page with database queries |
| 10.0 – 100.0 | High — heavy computation or large data processing |
| > 100.0 | Very high — consider optimization |

These ranges assume default configuration (18W device, 332 gCO2eq/kWh). Actual values depend heavily on your device power and grid carbon intensity.

### Comparative analysis

SCI scores are most useful for:

- **Comparing endpoints**: Which routes have the highest carbon cost?
- **Tracking over time**: Is the application becoming more or less efficient?
- **Budget enforcement**: Set maximum SCI thresholds for staging gates
- **Optimization targeting**: Focus optimization efforts on highest-SCI endpoints

### Example: carbon budget in CI/CD

```bash
# Fail if any request exceeds 5 mgCO2eq
MAX_SCI=5.0
VIOLATIONS=$(jq "select(.[\"sci.sci_mgco2eq\"] > $MAX_SCI)" /tmp/sci-profiler/sci-profiler.jsonl | wc -l)
if [ "$VIOLATIONS" -gt 0 ]; then
    echo "FAIL: $VIOLATIONS requests exceeded SCI budget of $MAX_SCI mgCO2eq"
    exit 1
fi
```

## References

- [Green Software Foundation — SCI Specification](https://sci-guide.greensoftware.foundation/)
- [ISO 14064 — Greenhouse Gas Accounting](https://www.iso.org/standard/66453.html)
- [Electricity Maps — Real-time Carbon Intensity](https://app.electricitymaps.com/)
- [Apple Environmental Reports](https://www.apple.com/environment/)
- [Dell Product Carbon Footprints](https://www.dell.com/en-us/dt/corporate/social-impact/advancing-sustainability/sustainable-products-and-services/product-carbon-footprints.htm)
- [EPA eGRID — US Grid Emissions](https://www.epa.gov/egrid)
- [EMBER Climate Data](https://ember-climate.org/data/)
