# SCI Profiler PHP — Development Guidelines

## Project Overview

Non-invasive Software Carbon Intensity (SCI) profiler for PHP applications. Uses `auto_prepend_file` to measure carbon footprint per HTTP request or CLI execution.

## Key Commands

```bash
composer test          # Run PHPUnit test suite
composer analyse       # PHPStan static analysis
composer cs-fix        # Fix PSR-12 coding style
```

## Pre-Push Checklist

Before every push to the repository:

1. **Run the test suite**: `composer test` — all tests must pass
2. **Run static analysis**: `composer analyse` — no errors allowed
3. **Build the phar**: `php -d phar.readonly=0 bin/build-phar.php` — must succeed and produce `bin/sci-profiler.phar`
4. **Quick phar smoke test**: verify the phar works with a simple script:
   ```bash
   php -d auto_prepend_file=bin/sci-profiler.phar -r "echo 'OK';"
   tail -1 /tmp/sci-profiler/sci-profiler.jsonl | jq .sci.sci_mgco2eq
   ```
5. **Update the documentation**: parse and update all the markdown files when needed

 
## Architecture

- `src/bootstrap.php` — entry point for `auto_prepend_file` (source mode)
- `bin/sci-profiler.phar` — self-contained phar with built-in autoloader (no composer needed on host)
- `bin/build-phar.php` — phar build script
- `src/Config.php` — immutable config value object; defaults are class constants (`DEFAULT_*`)
- `src/SciCalculator.php` — SCI formula; constants for magic numbers
- `src/SciProfiler.php` — orchestrator: collectors → calculator → reporters (fail-safe)
- `src/ProfileResult.php` — immutable result value object
- `src/GridCarbonData.php` — static Ember Climate data: 60+ countries, timezone auto-detect
- `src/Collector/` — `CollectorInterface` + TimeCollector (PSR-20), MemoryCollector, RequestCollector
- `src/Reporter/` — `ReporterInterface` + JsonReporter, LogReporter, HtmlReporter, TrendReporter + `EnsuresOutputDirectory` and `ReadsJsonlHistory` traits
- `config/sci-profiler.php` — default config template (bundled in phar)
- `examples/` — optimization examples with SCI reports (string, database, JSON)
- `analisi/` — local-only test scripts (in .gitignore)

## Coding Standards

- PSR-12 coding style (enforced by php-cs-fixer)
- PSR-4 autoloading under `SciProfiler\` namespace
- All classes are `final` with `declare(strict_types=1)`
- No dependencies in production code (only dev: phpunit, phpstan, php-cs-fixer)
- Reporter errors are always caught silently — never break the host application
- PSR-1: Basic Coding Standard
- PSR-3: Logger Interface (accetteremo un PSR-3 logger opzionale)
- PSR-20: Clock interface (per testabilità del timing)

## The phar

The phar bundles all source files + default config into a single ~82KB file. It includes its own PSR-4 autoloader in the stub, so it requires zero dependencies on the host machine.

Config resolution in phar mode:
1. `SCI_PROFILER_CONFIG_FILE` env var
2. `sci-profiler.local.php` next to the phar
3. `SCI_PROFILER_*` env vars
4. Bundled default config
5. Built-in class defaults
