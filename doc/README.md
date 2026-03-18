# SCI Profiler PHP — Documentation

## Concepts

| Document | Description |
|----------|-------------|
| [**Methodology**](METHODOLOGY.md) | The SCI formula explained step by step: energy (E), grid carbon intensity (I), embodied carbon (M), and functional unit (R). Includes assumptions, limitations, measurement timeline, and how to interpret SCI scores. |

## Setup and Configuration

| Document | Description |
|----------|-------------|
| [**Configuration**](configuration.md) | All configuration options with defaults. How to configure via PHP file, environment variables, or built-in defaults. Configuration priority order. |
| [**Grid Carbon Intensity**](grid-carbon-intensity.md) | Reference table of carbon intensity values per country (Ember Climate data, 2024). How to find the right value for your location, auto-detection by timezone, and how to update the data. |
| [**Reporters**](reporters.md) | The three built-in reporters (JSON lines, log, HTML dashboard). Output formats, analysis examples with `jq`, and PSR-3 logger integration. |
| [**Extending**](extending.md) | How to write custom collectors and reporters. Interface contracts, code examples, and PSR-3 logger integration. |

## Framework Examples

Each guide explains which **use cases** (functional units) are relevant for the framework, how to set up the profiler, how to analyze results, and common optimization insights.

| Document | Framework | Key Topics |
|----------|-----------|------------|
| [**WordPress**](example-wordpress.md) | WordPress, WooCommerce | Blog post view, checkout flow, admin panel, WP-Cron, REST API, full page load aggregation |
| [**Laravel**](example-laravel.md) | Laravel, Lumen | Web routes, API endpoints, Artisan commands, queue workers, Laravel Sail/Docker, CI carbon budget |
| [**Symfony**](example-symfony.md) | Symfony, API Platform | Controllers, console commands, Messenger workers, EasyAdmin, Symfony Profiler side-by-side, CI integration |

## Understanding Functional Units

A core concept of the SCI specification is the **functional unit** — what exactly you are measuring the carbon cost of.

In SCI Profiler PHP, a functional unit is a **complete user-facing operation**, not an internal method call:

| Functional Unit (YES) | NOT a Functional Unit |
|----------------------|----------------------|
| `GET /products` — full page load | `ProductRepository::findAll()` — internal method |
| `POST /checkout` — complete checkout flow | A single SQL query |
| `php artisan reports:generate` — full command | `Cache::remember()` — infrastructure call |

This is explained in detail in the [Methodology — Functional Unit](METHODOLOGY.md#r--functional-unit) section, with practical examples in each framework guide.
