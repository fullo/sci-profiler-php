<?php

declare(strict_types=1);

/**
 * JSON API — AFTER optimization.
 *
 * Fix: single-pass processing with one decode and one encode.
 * All aggregations (counts, sums, groupings) computed in a single loop.
 * No intermediate array copies, no redundant serialization.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

// ── Same payload generation ──

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

// ── Optimization 1: single decode, no re-encode of individual records ──

$decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
$events = $decoded['events'];

// ── Optimization 2: single-pass aggregation ──
// All counts, sums, and groupings computed in one loop.
// No array_filter copies, no array_column intermediate arrays.

$euCountries = ['DE' => true, 'FR' => true, 'IT' => true, 'GB' => true];

$typeCounts = ['pageview' => 0, 'click' => 0, 'conversion' => 0, 'error' => 0];
$usCount = 0;
$euCount = 0;
$totalDuration = 0;
$pageCounts = [];

foreach ($events as $event) {
    // Type count
    $typeCounts[$event['type']] = ($typeCounts[$event['type']] ?? 0) + 1;

    // Country counts — isset() is faster than in_array()
    if ($event['country'] === 'US') {
        $usCount++;
    } elseif (isset($euCountries[$event['country']])) {
        $euCount++;
    }

    // Duration sum
    $totalDuration += $event['duration_ms'];

    // Page frequency
    $url = $event['url'];
    $pageCounts[$url] = ($pageCounts[$url] ?? 0) + 1;
}

$total = count($events);

// ── Optimization 3: single json_encode for the complete response ──

$response = json_encode([
    'summary' => [
        'total' => $total,
        'pageviews' => $typeCounts['pageview'],
        'clicks' => $typeCounts['click'],
        'conversions' => $typeCounts['conversion'],
        'errors' => $typeCounts['error'],
        'us_events' => $usCount,
        'eu_events' => $euCount,
        'total_weight_bytes' => strlen($payload), // payload size already known
    ],
    'top_pages' => $pageCounts,
    'avg_duration' => $totalDuration / $total,
], JSON_THROW_ON_ERROR);

// No verification decode needed — we built the array directly

echo 'Events: ' . $total . "\n";
echo 'Payload: ' . strlen($payload) . " bytes\n";
echo 'Response: ' . strlen($response) . " bytes\n";
echo 'Total weight: ' . strlen($payload) . " bytes\n";
