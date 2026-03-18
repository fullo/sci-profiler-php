# SCI Profiler PHP — Examples

Practical before/after examples showing how code optimization reduces the Software Carbon Intensity (SCI) score.

Each example includes an inefficient version (`before.php`) and an optimized version (`after.php`). The SCI profiler measures both, generating reports in all 4 formats (JSONL, log, HTML dashboard, trend).

## Quick Start

```bash
cd examples
bash run-all.sh
```

This runs each script 3 times with the SCI profiler enabled and generates reports in `results/` directories.

## Examples

### 01 — String Processing

**Anti-pattern:** string concatenation with `.=` inside a loop.

Each `.=` on a growing string forces PHP to reallocate and copy the entire buffer, resulting in O(n²) memory operations. With 5,000 records this means millions of characters copied cumulatively.

**Fix:** collect parts in an array, `implode()` once at the end. Also compute summary statistics in the same loop instead of iterating twice.

| Metric | Before | After | Reduction |
|--------|--------|-------|-----------|
| Time | 4.7 ms | 4.3 ms | 10% |
| SCI | 0.031 mgCO2eq | 0.029 mgCO2eq | 10% |

### 02 — Database Simulation (N+1 Queries)

**Anti-pattern:** N+1 query pattern — fetch a list, then query each item's relations individually.

500 orders × 2 queries each (customer + items) = 1,001 total queries. Even with 50μs per query, this adds up to ~50ms of pure wait time.

**Fix:** 3 batch queries (all orders, all customers, all items), then join in PHP with hash-map lookups. O(1) per lookup, no additional "queries."

| Metric | Before | After | Reduction |
|--------|--------|-------|-----------|
| Time | 69.7 ms | 1.3 ms | 98% |
| SCI | 0.466 mgCO2eq | 0.008 mgCO2eq | 98% |
| Queries | 1,001 | 3 | 99.7% |

### 03 — JSON API Processing

**Anti-pattern:** repeated decode/encode cycles, multiple filter passes creating array copies, and re-encoding each record individually to measure its size.

**Fix:** single-pass aggregation — one decode, all counts/sums/groupings computed in a single loop, one encode for the response. No intermediate array copies.

| Metric | Before | After | Reduction |
|--------|--------|-------|-----------|
| Time | 13.4 ms | 11.4 ms | 15% |
| SCI | 0.090 mgCO2eq | 0.076 mgCO2eq | 15% |

## Generated Reports

After running `run-all.sh`, each example has a `results/` directory containing:

| File | Format | Description |
|------|--------|-------------|
| `sci-profiler.jsonl` | JSON Lines | One JSON object per run — machine-readable, ideal for `jq` analysis |
| `sci-profiler.log` | Plain text | One line per run — human-readable, ideal for `tail -f` |
| `dashboard.html` | HTML | Visual dashboard with SVG timeline chart, sparklines, and per-script comparison |
| `sci-trend.txt` | Plain text | Terminal-friendly trend report with ASCII sparklines and delta indicators |

Open `results/dashboard.html` in a browser to see the SVG timeline, per-script sparklines, and before/after comparison.

## How It Works

The `run-all.sh` script:

1. Creates a temporary config per example that enables all 4 reporters
2. Runs `before.php` 3 times with `auto_prepend_file=src/bootstrap.php`
3. Runs `after.php` 3 times with the same profiler
4. The profiler captures start→stop timing, memory, and SCI for each execution
5. The trend reporter shows how the SCI score changed across all 6 runs

Since `before.php` runs first, the trend report naturally shows the transition from higher SCI (before) to lower SCI (after).

## Notes

- Results vary between machines due to CPU speed, OPcache state, and system load
- The database simulation uses `usleep(50)` to simulate query latency — actual results depend on timer precision
- All examples are self-contained PHP scripts with no external dependencies
- The profiler overhead is typically < 1ms per run
