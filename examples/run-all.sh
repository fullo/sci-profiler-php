#!/usr/bin/env bash
#
# SCI Profiler PHP — Example Runner
#
# Runs all before/after examples with SCI profiling enabled.
# Generates all 4 report formats (json, log, html, trend) per example.
#
# Usage:
#   cd examples && bash run-all.sh
#
# Requirements:
#   - PHP >= 8.1
#   - No external dependencies (uses source bootstrap, not phar)
#
# @author fullo <https://github.com/fullo>
# @license MIT
# @version 1.0

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BOOTSTRAP="${PROJECT_ROOT}/src/bootstrap.php"
RUNS=3

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${BOLD}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║     SCI Profiler PHP — Example Runner                    ║${NC}"
echo -e "${BOLD}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verify bootstrap exists
if [ ! -f "$BOOTSTRAP" ]; then
    echo -e "${RED}Error: bootstrap not found at ${BOOTSTRAP}${NC}"
    echo "Run from the examples/ directory: cd examples && bash run-all.sh"
    exit 1
fi

# Process each example directory
for example_dir in "$SCRIPT_DIR"/0*; do
    [ -d "$example_dir" ] || continue

    example_name="$(basename "$example_dir")"
    results_dir="${example_dir}/results"

    echo -e "${CYAN}━━━ ${example_name} ━━━${NC}"
    echo ""

    # Clean previous results
    rm -rf "$results_dir"
    mkdir -p "$results_dir"

    # Create config that writes to this example's results directory
    config_file="${results_dir}/_sci-config.php"
    cat > "$config_file" << PHPEOF
<?php
return [
    'enabled'               => true,
    'device_power_watts'    => 18.0,
    'grid_carbon_intensity' => 332.0,
    'embodied_carbon'       => 211000.0,
    'device_lifetime_hours' => 11680.0,
    'machine_description'   => 'Example: ${example_name}',
    'output_dir'            => '${results_dir}',
    'reporters'             => ['json', 'log', 'html', 'trend'],
];
PHPEOF

    # Run BEFORE version (3 times)
    if [ -f "${example_dir}/before.php" ]; then
        echo -e "  ${RED}BEFORE${NC} (${RUNS} runs):"
        for i in $(seq 1 $RUNS); do
            output=$(SCI_PROFILER_CONFIG_FILE="$config_file" XDEBUG_MODE=off \
                php -d auto_prepend_file="$BOOTSTRAP" "${example_dir}/before.php" 2>&1 | head -1)
            echo "    [$i] $output"
        done
        echo ""
    fi

    # Run AFTER version (3 times)
    if [ -f "${example_dir}/after.php" ]; then
        echo -e "  ${GREEN}AFTER${NC} (${RUNS} runs):"
        for i in $(seq 1 $RUNS); do
            output=$(SCI_PROFILER_CONFIG_FILE="$config_file" XDEBUG_MODE=off \
                php -d auto_prepend_file="$BOOTSTRAP" "${example_dir}/after.php" 2>&1 | head -1)
            echo "    [$i] $output"
        done
        echo ""
    fi

    # Remove temp config
    rm -f "$config_file"

    # Show results summary
    jsonl="${results_dir}/sci-profiler.jsonl"
    if [ -f "$jsonl" ]; then
        echo -e "  ${YELLOW}Results:${NC}"

        # Before vs After comparison
        before_avg=$(cat "$jsonl" | grep "before.php" | jq -s 'map(.["sci.sci_mgco2eq"]) | add / length | . * 10000 | round / 10000' 2>/dev/null || echo "N/A")
        after_avg=$(cat "$jsonl" | grep "after.php" | jq -s 'map(.["sci.sci_mgco2eq"]) | add / length | . * 10000 | round / 10000' 2>/dev/null || echo "N/A")

        before_ms=$(cat "$jsonl" | grep "before.php" | jq -s 'map(.["time.wall_time_ms"]) | add / length | . * 10 | round / 10' 2>/dev/null || echo "N/A")
        after_ms=$(cat "$jsonl" | grep "after.php" | jq -s 'map(.["time.wall_time_ms"]) | add / length | . * 10 | round / 10' 2>/dev/null || echo "N/A")

        echo "    Before: ${before_avg} mgCO2eq (${before_ms} ms avg)"
        echo "    After:  ${after_avg} mgCO2eq (${after_ms} ms avg)"

        if command -v bc &> /dev/null && [ "$before_avg" != "N/A" ] && [ "$after_avg" != "N/A" ]; then
            reduction=$(echo "scale=1; (1 - $after_avg / $before_avg) * 100" | bc 2>/dev/null || echo "?")
            echo -e "    ${GREEN}Reduction: ${reduction}%${NC}"
        fi

        echo ""
        echo "    Files:"
        echo "      JSONL:     ${results_dir}/sci-profiler.jsonl"
        echo "      Log:       ${results_dir}/sci-profiler.log"
        echo "      Dashboard: ${results_dir}/dashboard.html"
        echo "      Trend:     ${results_dir}/sci-trend.txt"
    fi

    echo ""
done

echo -e "${BOLD}━━━ Trend Reports ━━━${NC}"
echo ""

for example_dir in "$SCRIPT_DIR"/0*; do
    [ -d "$example_dir" ] || continue
    trend_file="${example_dir}/results/sci-trend.txt"
    if [ -f "$trend_file" ]; then
        echo -e "${CYAN}$(basename "$example_dir"):${NC}"
        cat "$trend_file"
        echo ""
    fi
done

echo -e "${BOLD}Done.${NC} Open any dashboard.html to see the visual report."
