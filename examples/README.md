# SCI Profiler PHP — Examples

Practical examples showing how code optimization reduces the Software Carbon Intensity (SCI) score through a realistic profile → optimize → re-profile workflow.

Each script accepts an `iteration` argument (1, 2, 3) representing progressively optimized versions of the same code. The SCI profiler measures all iterations, and the reports show the improvement trajectory.

## Quick Start

```bash
cd examples
bash run-all.sh
open results/dashboard.html
```

This runs each script through 3 optimization iterations and generates unified reports in `results/`.

## How It Works

```
Iteration 1 (naive)     →  profile  →  SCI: 0.47 mgCO2eq
    ↓ optimize
Iteration 2 (improved)  →  profile  →  SCI: 0.008 mgCO2eq
    ↓ refine
Iteration 3 (refined)   →  profile  →  SCI: 0.007 mgCO2eq
```

The `run-all.sh` script:
1. Runs `php 01-string-processing.php 1`, then `2`, then `3`
2. Same for each example script
3. All runs write to the same `results/` directory
4. The trend report and dashboard show the SCI trajectory per script

## Examples

### 01 — String Processing

| Iteration | Approach | SCI |
|-----------|----------|-----|
| 1 (naive) | `.=` concatenation in loop — O(n²) memory copies | 0.035 mgCO2eq |
| 2 (optimized) | Array of parts + `implode()` — O(n) allocation | 0.030 mgCO2eq |
| 3 (refined) | `sprintf` per row + single-pass stats — no second loop | 0.026 mgCO2eq |

**Total reduction: ~30%**

### 02 — Database Simulation (N+1 Queries)

| Iteration | Approach | SCI |
|-----------|----------|-----|
| 1 (naive) | N+1 queries: 1,001 total (50μs each) | 0.468 mgCO2eq |
| 2 (optimized) | 3 batch queries + hash-map join | 0.008 mgCO2eq |
| 3 (refined) | Batch + inline aggregation, no intermediate array | 0.007 mgCO2eq |

**Total reduction: ~98%**

### 03 — JSON API Processing

| Iteration | Approach | SCI |
|-----------|----------|-----|
| 1 (naive) | 6 `array_filter` passes + per-record `json_encode` | 0.078 mgCO2eq |
| 2 (optimized) | Single-pass aggregation + one `json_encode` | 0.061 mgCO2eq |
| 3 (refined) | `isset()` lookups instead of `in_array()` | 0.066 mgCO2eq |

**Total reduction: ~16%**

## Generated Reports

After running `run-all.sh`, the `results/` directory contains:

| File | Format | Description |
|------|--------|-------------|
| `sci-profiler.jsonl` | JSON Lines | One JSON object per run — all 9 measurements |
| `sci-profiler.log` | Plain text | One line per run — human-readable |
| `dashboard.html` | HTML | SVG timeline chart, per-script sparklines, last-vs-previous comparison |
| `sci-trend.txt` | Plain text | Terminal-friendly trend with ASCII sparklines and delta indicators |

Open `results/dashboard.html` in a browser to see the SVG charts showing the SCI improvement visually across iterations.

## Running Individual Scripts

You can also run examples individually:

```bash
# Run with profiler (source mode)
php -d auto_prepend_file=../src/bootstrap.php 01-string-processing.php 1
php -d auto_prepend_file=../src/bootstrap.php 01-string-processing.php 2
php -d auto_prepend_file=../src/bootstrap.php 01-string-processing.php 3

# Run with profiler (phar mode)
php -d auto_prepend_file=../bin/sci-profiler.phar 02-database-simulation.php 1
```

## Notes

- Results vary between machines due to CPU speed, OPcache state, and system load
- The database simulation uses `usleep(50)` to simulate query latency
- All examples use `mt_srand(42)` for reproducible data across iterations
- The profiler overhead is typically < 1ms per run
