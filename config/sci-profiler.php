<?php

/**
 * SCI Profiler configuration.
 *
 * Copy this file and adjust values for your development/staging environment.
 * Point SCI_PROFILER_CONFIG_FILE env var to your copy, or place it at the
 * default location: <profiler-root>/config/sci-profiler.php
 *
 * @see https://sci-guide.greensoftware.foundation/
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Profiler Enabled
    |--------------------------------------------------------------------------
    | Set to false to disable profiling without removing auto_prepend_file.
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Device Power (Watts)
    |--------------------------------------------------------------------------
    | Average power consumption of the machine running the application.
    | Examples: MacBook Pro M1 = 18W, Desktop workstation = 65W,
    | Cloud VM (shared) = 5-15W
    */
    'device_power_watts' => 18.0,

    /*
    |--------------------------------------------------------------------------
    | Grid Carbon Intensity (gCO2eq/kWh)
    |--------------------------------------------------------------------------
    | Carbon intensity of the electricity grid where the machine is located.
    | Find your region: https://app.electricitymaps.com/
    | Examples: France = 56, Germany = 385, USA average = 390, Norway = 26
    | Default: 332 (GitHub Actions median)
    */
    'grid_carbon_intensity' => 332.0,

    /*
    |--------------------------------------------------------------------------
    | Embodied Carbon (gCO2eq)
    |--------------------------------------------------------------------------
    | Total lifecycle carbon footprint of the hardware.
    | Examples: MacBook Pro 14" = 211,000g, Dell Latitude = 320,000g
    */
    'embodied_carbon' => 211000.0,

    /*
    |--------------------------------------------------------------------------
    | Device Lifetime (hours)
    |--------------------------------------------------------------------------
    | Expected operational lifetime of the device.
    | 4 years × 8h/day × 365 days = 11,680 hours
    */
    'device_lifetime_hours' => 11680.0,

    /*
    |--------------------------------------------------------------------------
    | Machine Description
    |--------------------------------------------------------------------------
    | Human-readable label for reports and dashboards.
    */
    'machine_description' => 'Development machine',

    /*
    |--------------------------------------------------------------------------
    | LCA Source
    |--------------------------------------------------------------------------
    | Reference for the embodied carbon data source.
    */
    'lca_source' => 'Estimated',

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    | Where profiling results are stored. Must be writable by the PHP process.
    */
    'output_dir' => '/tmp/sci-profiler',

    /*
    |--------------------------------------------------------------------------
    | Reporters
    |--------------------------------------------------------------------------
    | Which reporters to activate. Available: json, log, html
    | Use multiple: ['json', 'html']
    */
    'reporters' => ['json', 'log'],
];
