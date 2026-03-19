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

### 01 — String Processing (20,000 records)

| Iteration | Approach | SCI |
|-----------|----------|-----|
| 1 (naive) | `.=` in loop (7 per row) + `str_replace` on 2MB + `substr_count` | 0.182 mgCO2eq |
| 2 (optimized) | Array + `implode()` (but still 2 loops for summary) | 0.154 mgCO2eq |
| 3 (refined) | `sprintf` per row + single-pass stats in one loop | 0.104 mgCO2eq |

**Total reduction: ~43%**

### 02 — Database Simulation (N+1 → Batch)

| Iteration | Approach | SCI |
|-----------|----------|-----|
| 1 (naive) | N+1 queries: 1,001 total (50μs each) | 0.464 mgCO2eq |
| 2 (optimized) | 3 batch queries, but linear scan O(n²) for join | 0.211 mgCO2eq |
| 3 (refined) | 3 batch + hash-map O(1) join + inline aggregation | 0.007 mgCO2eq |

**Total reduction: ~98%**

### 03 — JSON API Processing (10,000 events)

| Iteration | Approach | SCI |
|-----------|----------|-----|
| 1 (naive) | Double decode, sort, 6 `array_filter` passes, per-record `json_encode` | 0.453 mgCO2eq |
| 2 (optimized) | Single-pass aggregation + one `json_encode` | 0.212 mgCO2eq |
| 3 (refined) | Regex extraction from raw JSON — no full decode at all | 0.144 mgCO2eq |

**Total reduction: ~68%**

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
