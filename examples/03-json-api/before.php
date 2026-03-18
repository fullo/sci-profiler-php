<?php

declare(strict_types=1);

/**
 * JSON API — BEFORE optimization.
 *
 * Anti-pattern: repeated decode/encode cycles and redundant processing.
 * Simulates a backend that receives a large JSON payload, processes each
 * record individually (decoding/encoding multiple times), applies filters
 * in separate passes, and builds the response with repeated serialization.
 *
 * Common in microservice middleware, webhook processors, and ETL scripts.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

// ── Generate a large JSON payload (simulating an API request body) ──

$records = [];
for ($i = 0; $i < 3000; $i++) {
    $records[] = [
        'id' => $i + 1,
        'timestamp' => date('c', strtotime("-{$i} minutes")),
        'type' => ['pageview', 'click', 'conversion', 'error'][$i % 4],
        'url' => '/page/' . mt_rand(1, 50),
        'user_agent' => 'Mozilla/5.0 (compatible; Bot/' . mt_rand(1, 20) . ')',
        'duration_ms' => mt_rand(10, 5000),
        'country' => ['US', 'DE', 'FR', 'IT', 'GB', 'JP', 'BR', 'IN'][$i % 8],
        'metadata' => [
            'session_id' => bin2hex(random_bytes(8)),
            'referrer' => 'https://search.example.com/q=' . bin2hex(random_bytes(4)),
            'viewport' => ['width' => mt_rand(320, 1920), 'height' => mt_rand(568, 1080)],
        ],
    ];
}

$payload = json_encode(['events' => $records], JSON_THROW_ON_ERROR);

// ── Anti-pattern 1: decode the entire payload, then re-encode individual records ──

$decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
$events = $decoded['events'];

// ── Anti-pattern 2: multiple separate filter passes ──
// Each array_filter creates a new array copy.

// Pass 1: filter by type
$pageviews = array_filter($events, fn ($e) => $e['type'] === 'pageview');
$clicks = array_filter($events, fn ($e) => $e['type'] === 'click');
$conversions = array_filter($events, fn ($e) => $e['type'] === 'conversion');
$errors = array_filter($events, fn ($e) => $e['type'] === 'error');

// Pass 2: filter by country (on ALL events again)
$usEvents = array_filter($events, fn ($e) => $e['country'] === 'US');
$euEvents = array_filter($events, fn ($e) => in_array($e['country'], ['DE', 'FR', 'IT', 'GB']));

// ── Anti-pattern 3: re-encode each record to compute its "weight" ──

$totalWeight = 0;
foreach ($events as $event) {
    // Encoding each record individually to measure its JSON size
    $encoded = json_encode($event, JSON_THROW_ON_ERROR);
    $totalWeight += strlen($encoded);
}

// ── Anti-pattern 4: build response by encoding sub-arrays separately ──

$response = '{"summary":' . json_encode([
    'total' => count($events),
    'pageviews' => count($pageviews),
    'clicks' => count($clicks),
    'conversions' => count($conversions),
    'errors' => count($errors),
    'us_events' => count($usEvents),
    'eu_events' => count($euEvents),
    'total_weight_bytes' => $totalWeight,
]) . ',"top_pages":' . json_encode(
    // Another full iteration to count page frequencies
    array_count_values(array_column($events, 'url'))
) . ',"avg_duration":' . json_encode(
    array_sum(array_column($events, 'duration_ms')) / count($events)
) . '}';

// Decode the response we just built to verify it (wasteful round-trip)
$verify = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

echo 'Events: ' . $verify['summary']['total'] . "\n";
echo 'Payload: ' . strlen($payload) . " bytes\n";
echo 'Response: ' . strlen($response) . " bytes\n";
echo 'Total weight: ' . $totalWeight . " bytes\n";
