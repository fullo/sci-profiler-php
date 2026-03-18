#!/usr/bin/env bash
#
# SCI Profiler PHP — Example Runner
#
# Runs each example script through 3 optimization iterations,
# simulating a real profiling → optimize → re-profile workflow.
# Generates all 4 report formats in examples/results/.
#
# Usage:
#   cd examples && bash run-all.sh
#
# @author fullo <https://github.com/fullo>
# @license MIT
# @version 1.0

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BOOTSTRAP="${PROJECT_ROOT}/src/bootstrap.php"
RESULTS_DIR="${SCRIPT_DIR}/results"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

echo -e "${BOLD}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   SCI Profiler PHP — Optimization Examples                   ║${NC}"
echo -e "${BOLD}║                                                               ║${NC}"
echo -e "${BOLD}║   Each script runs 3 iterations:                              ║${NC}"
echo -e "${BOLD}║     1 → naive code (anti-pattern)                             ║${NC}"
echo -e "${BOLD}║     2 → first optimization                                    ║${NC}"
echo -e "${BOLD}║     3 → refined optimization                                  ║${NC}"
echo -e "${BOLD}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verify bootstrap exists
if [ ! -f "$BOOTSTRAP" ]; then
    echo -e "${RED}Error: bootstrap not found at ${BOOTSTRAP}${NC}"
    echo "Run from the examples/ directory: cd examples && bash run-all.sh"
    exit 1
fi

# Clean previous results
rm -rf "$RESULTS_DIR"
mkdir -p "$RESULTS_DIR"

# Config: all 4 reporters, unified results directory
CONFIG_FILE="${RESULTS_DIR}/_config.php"
cat > "$CONFIG_FILE" << PHPEOF
<?php
return [
    'enabled'               => true,
    'device_power_watts'    => 18.0,
    'grid_carbon_intensity' => 332.0,
    'embodied_carbon'       => 211000.0,
    'device_lifetime_hours' => 11680.0,
    'machine_description'   => 'SCI Profiler examples',
    'output_dir'            => '${RESULTS_DIR}',
    'reporters'             => ['json', 'log', 'html', 'trend'],
];
PHPEOF

# ── Run each example through 3 iterations ──

for script in "$SCRIPT_DIR"/0*.php; do
    [ -f "$script" ] || continue
    name="$(basename "$script" .php)"

    echo -e "${CYAN}━━━ ${name} ━━━${NC}"
    echo ""

    for iter in 1 2 3; do
        case $iter in
            1) label="${RED}iteration 1${NC} ${DIM}(naive)${NC}" ;;
            2) label="${YELLOW}iteration 2${NC} ${DIM}(optimized)${NC}" ;;
            3) label="${GREEN}iteration 3${NC} ${DIM}(refined)${NC}" ;;
        esac

        output=$(SCI_PROFILER_CONFIG_FILE="$CONFIG_FILE" XDEBUG_MODE=off \
            php -d auto_prepend_file="$BOOTSTRAP" "$script" "$iter" 2>&1 | tail -1)

        echo -e "  ${label}: ${output}"
    done

    echo ""
done

# Clean temp config
rm -f "$CONFIG_FILE"

# ── Summary from JSONL ──

JSONL="${RESULTS_DIR}/sci-profiler.jsonl"
if [ -f "$JSONL" ] && command -v jq &> /dev/null; then
    echo -e "${BOLD}━━━ SCI Results Summary ━━━${NC}"
    echo ""

    for script in "$SCRIPT_DIR"/0*.php; do
        [ -f "$script" ] || continue
        name="$(basename "$script")"

        echo -e "  ${CYAN}${name}${NC}"

        # Extract 3 iterations (in order they were recorded)
        values=$(grep "$name" "$JSONL" | jq -r '.["sci.sci_mgco2eq"]' 2>/dev/null)
        times=$(grep "$name" "$JSONL" | jq -r '.["time.wall_time_ms"]' 2>/dev/null)

        iter=0
        paste <(echo "$values") <(echo "$times") | while IFS=$'\t' read -r sci ms; do
            iter=$((iter + 1))
            case $iter in
                1) tag="naive   " ;;
                2) tag="optimize" ;;
                3) tag="refined " ;;
                *) tag="run $iter  " ;;
            esac
            printf "    %s: %s mgCO2eq  (%s ms)\n" "$tag" "$sci" "$ms"
        done

        # Reduction: first vs last
        first=$(grep "$name" "$JSONL" | head -1 | jq -r '.["sci.sci_mgco2eq"]' 2>/dev/null)
        last=$(grep "$name" "$JSONL" | tail -1 | jq -r '.["sci.sci_mgco2eq"]' 2>/dev/null)
        if [ -n "$first" ] && [ -n "$last" ] && command -v bc &> /dev/null; then
            reduction=$(echo "scale=1; (1 - $last / $first) * 100" | bc 2>/dev/null || echo "?")
            echo -e "    ${GREEN}Total reduction: ${reduction}%${NC}"
        fi
        echo ""
    done
fi

# ── Show trend report ──

TREND="${RESULTS_DIR}/sci-trend.txt"
if [ -f "$TREND" ]; then
    echo -e "${BOLD}━━━ Trend Report ━━━${NC}"
    echo ""
    cat "$TREND"
    echo ""
fi

# ── Final output ──

echo -e "${BOLD}━━━ Generated Files ━━━${NC}"
echo ""
echo "  JSONL:     ${RESULTS_DIR}/sci-profiler.jsonl"
echo "  Log:       ${RESULTS_DIR}/sci-profiler.log"
echo "  Dashboard: ${RESULTS_DIR}/dashboard.html"
echo "  Trend:     ${RESULTS_DIR}/sci-trend.txt"
echo ""
echo -e "${BOLD}Open dashboard.html in a browser to see SVG charts and sparklines.${NC}"
