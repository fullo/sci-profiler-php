<?php

declare(strict_types=1);

/**
 * Builds sci-profiler.phar from the src/ directory.
 *
 * Usage:
 *   php -d phar.readonly=0 bin/build-phar.php
 *
 * The resulting phar can be used directly with auto_prepend_file:
 *   php -d auto_prepend_file=bin/sci-profiler.phar -S localhost:8000
 */

$projectRoot = dirname(__DIR__);
$pharFile = $projectRoot . '/bin/sci-profiler.phar';
$srcDir = $projectRoot . '/src';
$configDir = $projectRoot . '/config';

// Clean previous build
if (file_exists($pharFile)) {
    unlink($pharFile);
    echo "Removed previous build.\n";
}

echo "Building sci-profiler.phar...\n";

try {
    $phar = new Phar($pharFile, 0, 'sci-profiler.phar');
    $phar->startBuffering();

    // Add all src/ files
    $srcIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    );

    $count = 0;
    foreach ($srcIterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = 'src/' . ltrim(
            str_replace($srcDir, '', $file->getPathname()),
            DIRECTORY_SEPARATOR,
        );

        $phar->addFile($file->getPathname(), $relativePath);
        echo "  + {$relativePath}\n";
        $count++;
    }

    // Add default config
    $phar->addFile($configDir . '/sci-profiler.php', 'config/sci-profiler.php');
    echo "  + config/sci-profiler.php\n";
    $count++;

    // Create the stub: when used as auto_prepend_file, it includes the bootstrap
    $stub = <<<'STUB'
<?php
/**
 * SCI Profiler PHP — phar stub.
 *
 * When loaded via auto_prepend_file, this registers the phar's autoloader
 * and executes the bootstrap to start profiling.
 */
// Resolve the absolute path to this phar file for reliable phar:// URLs.
// When used as auto_prepend_file, Phar::running() is empty, so we use __FILE__.
// Use Phar::running() first (works when executed directly), fall back to __FILE__
// with a regex that matches the LAST .phar in the path (avoids truncating if a
// parent directory name contains ".phar").
$__sciPharRunning = Phar::running(false);
$__sciPharPath = $__sciPharRunning !== ''
    ? $__sciPharRunning
    : (str_contains(__FILE__, '.phar')
        ? preg_replace('/\\.phar(?!.*\\.phar).*$/', '.phar', __FILE__)
        : __FILE__);

// Register PSR-4 autoloader for classes inside the phar
spl_autoload_register(static function (string $class) use ($__sciPharPath): void {
    $prefix = 'SciProfiler\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = 'phar://' . $__sciPharPath . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration (same logic as bootstrap.php, adapted for phar paths)
$__sciConfig = (static function () use ($__sciPharPath): \SciProfiler\Config {
    // 1. Explicit config file via env
    $configFile = getenv('SCI_PROFILER_CONFIG_FILE');
    if ($configFile !== false && is_file($configFile)) {
        return \SciProfiler\Config::fromFile($configFile);
    }

    // 2. Config file next to the phar (bin/sci-profiler.local.php)
    $pharDir = dirname($__sciPharPath);
    $localConfig = $pharDir . '/sci-profiler.local.php';
    if ($pharDir !== '' && is_file($localConfig)) {
        return \SciProfiler\Config::fromFile($localConfig);
    }

    // 3. Environment variables
    if (getenv('SCI_PROFILER_ENABLED') !== false) {
        return \SciProfiler\Config::fromEnvironment();
    }

    // 4. Default config bundled in the phar
    $pharConfig = 'phar://' . $__sciPharPath . '/config/sci-profiler.php';
    if (file_exists($pharConfig)) {
        return \SciProfiler\Config::fromFile($pharConfig);
    }

    // 5. Built-in defaults
    return new \SciProfiler\Config();
})();

if (!$__sciConfig->isEnabled()) {
    return;
}

// Build and start profiler
$__sciProfiler = new \SciProfiler\SciProfiler($__sciConfig);
$__sciProfiler->addCollector(new \SciProfiler\Collector\TimeCollector());
$__sciProfiler->addCollector(new \SciProfiler\Collector\MemoryCollector());
$__sciProfiler->addCollector(new \SciProfiler\Collector\RequestCollector());

$__sciReporterMap = [
    'json'  => static fn () => new \SciProfiler\Reporter\JsonReporter(),
    'log'   => static fn () => new \SciProfiler\Reporter\LogReporter(),
    'html'  => static fn () => new \SciProfiler\Reporter\HtmlReporter(),
    'trend' => static fn () => new \SciProfiler\Reporter\TrendReporter(),
];
foreach ($__sciConfig->getReporters() as $__rName) {
    $__rName = trim($__rName);
    if (isset($__sciReporterMap[$__rName])) {
        $__sciProfiler->addReporter($__sciReporterMap[$__rName]());
    }
}

$__sciProfiler->start();

register_shutdown_function(static function () use ($__sciProfiler): void {
    if ($__sciProfiler->isStarted()) {
        $__sciProfiler->stop();
    }
});

// Cleanup variables from global scope
unset($__sciPharPath, $__sciConfig, $__sciReporterMap, $__rName);

__HALT_COMPILER();
STUB;

    $phar->setStub($stub);
    $phar->stopBuffering();

    // Make executable
    chmod($pharFile, 0755);

    $size = filesize($pharFile);
    $sizeKb = round($size / 1024, 1);

    echo "\nBuild complete:\n";
    echo "  File: {$pharFile}\n";
    echo "  Size: {$sizeKb} KB\n";
    echo "  Files: {$count}\n";
    echo "\nUsage:\n";
    echo "  php -d auto_prepend_file={$pharFile} -S localhost:8000 -t public/\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
