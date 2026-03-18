<?php

/**
 * SCI Profiler bootstrap file.
 *
 * This file is designed to be used with PHP's auto_prepend_file directive.
 * It starts profiling automatically and registers a shutdown function
 * to finalize and report results.
 *
 * Usage in php.ini (staging/dev only):
 *   auto_prepend_file = /path/to/sci-profiler-php/src/bootstrap.php
 *
 * Configuration is loaded from (in priority order):
 *   1. Environment variables (SCI_PROFILER_*)
 *   2. Config file at SCI_PROFILER_CONFIG_FILE env var
 *   3. Default config at __DIR__/../config/sci-profiler.php
 *   4. Built-in defaults
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 *
 * @see https://www.php.net/manual/en/ini.core.php#ini.auto-prepend-file
 */

declare(strict_types=1);

(static function (): void {
    // Autoload — standalone (no Composer in target app required)
    $autoloadPaths = [
        __DIR__ . '/../vendor/autoload.php',  // Profiler's own vendor
    ];

    $loaded = false;
    foreach ($autoloadPaths as $autoload) {
        if (file_exists($autoload)) {
            require_once $autoload;
            $loaded = true;
            break;
        }
    }

    if (!$loaded) {
        // Fallback: register a minimal PSR-4 autoloader
        spl_autoload_register(static function (string $class): void {
            $prefix = 'SciProfiler\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    // Load configuration
    $config = loadSciProfilerConfig();

    if (!$config->isEnabled()) {
        return;
    }

    // Build profiler
    $profiler = new \SciProfiler\SciProfiler($config);

    // Register collectors
    $profiler->addCollector(new \SciProfiler\Collector\TimeCollector());
    $profiler->addCollector(new \SciProfiler\Collector\MemoryCollector());
    $profiler->addCollector(new \SciProfiler\Collector\RequestCollector());

    // Register reporters
    $reporterMap = [
        'json' => static fn () => new \SciProfiler\Reporter\JsonReporter(),
        'log' => static fn () => new \SciProfiler\Reporter\LogReporter(),
        'html' => static fn () => new \SciProfiler\Reporter\HtmlReporter(),
        'trend' => static fn () => new \SciProfiler\Reporter\TrendReporter(),
    ];

    foreach ($config->getReporters() as $name) {
        $name = trim($name);
        if (isset($reporterMap[$name])) {
            $profiler->addReporter($reporterMap[$name]());
        }
    }

    // Start profiling
    $profiler->start();

    // Register shutdown to finalize
    register_shutdown_function(static function () use ($profiler): void {
        if ($profiler->isStarted()) {
            $profiler->stop();
        }
    });
})();

/**
 * Load configuration from available sources.
 */
function loadSciProfilerConfig(): \SciProfiler\Config
{
    // 1. Check for explicit config file via env
    $configFile = getenv('SCI_PROFILER_CONFIG_FILE');
    if ($configFile !== false && is_file($configFile)) {
        return \SciProfiler\Config::fromFile($configFile);
    }

    // 2. Check for default config file location
    $defaultConfig = dirname(__DIR__) . '/config/sci-profiler.php';
    if (is_file($defaultConfig)) {
        return \SciProfiler\Config::fromFile($defaultConfig);
    }

    // 3. Try environment variables
    if (getenv('SCI_PROFILER_ENABLED') !== false) {
        return \SciProfiler\Config::fromEnvironment();
    }

    // 4. Built-in defaults
    return new \SciProfiler\Config();
}
