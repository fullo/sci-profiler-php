<?php

declare(strict_types=1);

/**
 * Example 03 — JSON API Processing: progressive optimization.
 *
 * Simulates a backend that receives a 3,000-event JSON payload,
 * filters, aggregates, and produces a summary response.
 *
 *   php 03-json-api.php 1    ← naive: multiple filter passes, repeated encode/decode
 *   php 03-json-api.php 2    ← fix: single-pass aggregation, one encode
 *   php 03-json-api.php 3    ← refined: isset() lookups + pre-allocated counters
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

$iteration = (int) ($argv[1] ?? 1);
echo "=== JSON API Processing — iteration {$iteration}/3 ===\n";

// ── Generate a 3,000-event JSON payload (same seed) ──
mt_srand(42);

$records = [];
for ($i = 0; $i < 3000; $i++) {
    $records[] = [
        'id' => $i + 1,
        'timestamp' => date('c', strtotime("-{$i} minutes")),
        'type' => ['pageview', 'click', 'conversion', 'error'][$i % 4],
        'url' => '/page/' . mt_rand(1, 50),
        'user_agent' => 'Mozilla/5.0 (Bot/' . mt_rand(1, 20) . ')',
        'duration_ms' => mt_rand(10, 5000),
        'country' => ['US', 'DE', 'FR', 'IT', 'GB', 'JP', 'BR', 'IN'][$i % 8],
        'metadata' => [
            'session_id' => bin2hex(random_bytes(8)),
            'referrer' => 'https://search.example.com/q=' . bin2hex(random_bytes(4)),
        ],
    ];
}

$payload = json_encode(['events' => $records], JSON_THROW_ON_ERROR);

match ($iteration) {
    // ── Iteration 1: multiple filter passes + repeated encode/decode ──
    // array_filter creates a new array copy each time.
    // json_encode per record is O(n) × payload_size.
    1 => (function () use ($payload): void {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $events = $decoded['events'];

        // 4 separate filter passes (each scans all 3,000 records)
        $pageviews = array_filter($events, fn ($e) => $e['type'] === 'pageview');
        $clicks = array_filter($events, fn ($e) => $e['type'] === 'click');
        $conversions = array_filter($events, fn ($e) => $e['type'] === 'conversion');
        $errors = array_filter($events, fn ($e) => $e['type'] === 'error');

        // 2 more filter passes for country groups
        $usEvents = array_filter($events, fn ($e) => $e['country'] === 'US');
        $euEvents = array_filter($events, fn ($e) => in_array($e['country'], ['DE', 'FR', 'IT', 'GB']));

        // Re-encode each record to compute "weight"
        $totalWeight = 0;
        foreach ($events as $event) {
            $totalWeight += strlen(json_encode($event, JSON_THROW_ON_ERROR));
        }

        // Build response from separately encoded pieces
        $response = '{"summary":' . json_encode([
            'total' => count($events),
            'pageviews' => count($pageviews),
            'clicks' => count($clicks),
            'conversions' => count($conversions),
            'errors' => count($errors),
            'us_events' => count($usEvents),
            'eu_events' => count($euEvents),
        ]) . ',"top_pages":' . json_encode(
            array_count_values(array_column($events, 'url'))
        ) . ',"avg_duration":' . json_encode(
            array_sum(array_column($events, 'duration_ms')) / count($events)
        ) . '}';

        // Wasteful verification round-trip
        json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        echo "Events: " . count($events) . " | Response: " . strlen($response) . " bytes\n";
    })(),

    // ── Iteration 2: single-pass aggregation, one encode ──
    // All counts computed in one loop. One json_encode at the end.
    2 => (function () use ($payload): void {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $events = $decoded['events'];

        $typeCounts = ['pageview' => 0, 'click' => 0, 'conversion' => 0, 'error' => 0];
        $usCount = 0;
        $euCount = 0;
        $totalDuration = 0;
        $pageCounts = [];

        foreach ($events as $event) {
            $typeCounts[$event['type']]++;

            if ($event['country'] === 'US') {
                $usCount++;
            } elseif (in_array($event['country'], ['DE', 'FR', 'IT', 'GB'])) {
                $euCount++;
            }

            $totalDuration += $event['duration_ms'];
            $url = $event['url'];
            $pageCounts[$url] = ($pageCounts[$url] ?? 0) + 1;
        }

        $response = json_encode([
            'summary' => [
                'total' => count($events),
                'pageviews' => $typeCounts['pageview'],
                'clicks' => $typeCounts['click'],
                'conversions' => $typeCounts['conversion'],
                'errors' => $typeCounts['error'],
                'us_events' => $usCount,
                'eu_events' => $euCount,
            ],
            'top_pages' => $pageCounts,
            'avg_duration' => $totalDuration / count($events),
        ], JSON_THROW_ON_ERROR);

        echo "Events: " . count($events) . " | Response: " . strlen($response) . " bytes\n";
    })(),

    // ── Iteration 3: isset() lookups + pre-allocated counters ──
    // isset() is faster than in_array() for country check.
    // No closure overhead for array_filter. No array_column copies.
    3 => (function () use ($payload): void {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $events = $decoded['events'];
        $total = count($events);

        $euCountries = ['DE' => true, 'FR' => true, 'IT' => true, 'GB' => true];

        $typeCounts = ['pageview' => 0, 'click' => 0, 'conversion' => 0, 'error' => 0];
        $usCount = 0;
        $euCount = 0;
        $totalDuration = 0;
        $pageCounts = [];

        foreach ($events as $event) {
            $typeCounts[$event['type']]++;

            // isset() on hash map: O(1), faster than in_array()
            $country = $event['country'];
            if ($country === 'US') {
                $usCount++;
            } elseif (isset($euCountries[$country])) {
                $euCount++;
            }

            $totalDuration += $event['duration_ms'];
            $url = $event['url'];
            $pageCounts[$url] = ($pageCounts[$url] ?? 0) + 1;
        }

        $response = json_encode([
            'summary' => [
                'total' => $total,
                'pageviews' => $typeCounts['pageview'],
                'clicks' => $typeCounts['click'],
                'conversions' => $typeCounts['conversion'],
                'errors' => $typeCounts['error'],
                'us_events' => $usCount,
                'eu_events' => $euCount,
            ],
            'top_pages' => $pageCounts,
            'avg_duration' => $totalDuration / $total,
        ], JSON_THROW_ON_ERROR);

        echo "Events: {$total} | Response: " . strlen($response) . " bytes\n";
    })(),

    default => throw new InvalidArgumentException("Usage: php 03-json-api.php [1|2|3]"),
};
