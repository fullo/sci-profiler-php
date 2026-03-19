<?php

declare(strict_types=1);

/**
 * Example 03 — JSON API Processing: progressive optimization.
 *
 * Simulates a backend that receives a 3,000-event JSON payload,
 * filters, aggregates, and produces a summary response.
 *
 *   php 03-json-api.php 1    ← naive: double decode, sort, 6 filter passes, per-record encode
 *   php 03-json-api.php 2    ← fix: single-pass aggregation, one encode
 *   php 03-json-api.php 3    ← refined: regex extraction from raw JSON, no full decode
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

$iteration = (int) ($argv[1] ?? 1);
echo "=== JSON API Processing — iteration {$iteration}/3 ===\n";

// ── Generate a 10,000-event JSON payload (same seed) ──
// Larger payload makes efficiency differences measurable.
mt_srand(42);

$records = [];
for ($i = 0; $i < 10000; $i++) {
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
    // ── Iteration 1: maximally wasteful — decode/encode/sort/copy everywhere ──
    // Decodes the payload twice, sorts a full copy for no reason,
    // applies 6 array_filter passes (each copying the array),
    // re-encodes every record individually to compute "weight",
    // and verifies the response with yet another decode round-trip.
    1 => (function () use ($payload): void {
        // Wasteful: decode twice (simulates "validate then process" anti-pattern)
        $validated = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $validatedJson = json_encode($validated, JSON_THROW_ON_ERROR);
        $decoded = json_decode($validatedJson, true, 512, JSON_THROW_ON_ERROR);
        $events = $decoded['events'];

        // Wasteful: sort the entire array by timestamp before filtering
        // (common anti-pattern: sort everything, then filter to a subset)
        $sorted = $events;
        usort($sorted, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        // 4 separate filter passes on the sorted copy (each creates a new array)
        $pageviews = array_filter($sorted, fn ($e) => $e['type'] === 'pageview');
        $clicks = array_filter($sorted, fn ($e) => $e['type'] === 'click');
        $conversions = array_filter($sorted, fn ($e) => $e['type'] === 'conversion');
        $errors = array_filter($sorted, fn ($e) => $e['type'] === 'error');

        // 2 more filter passes for country groups
        $usEvents = array_filter($sorted, fn ($e) => $e['country'] === 'US');
        $euEvents = array_filter($sorted, fn ($e) => in_array($e['country'], ['DE', 'FR', 'IT', 'GB']));

        // Wasteful: re-encode each record individually to compute "weight"
        $totalWeight = 0;
        foreach ($events as $event) {
            $totalWeight += strlen(json_encode($event, JSON_THROW_ON_ERROR));
        }

        // Wasteful: build response by encoding sub-arrays separately
        $response = '{"summary":' . json_encode([
            'total' => count($events),
            'pageviews' => count($pageviews),
            'clicks' => count($clicks),
            'conversions' => count($conversions),
            'errors' => count($errors),
            'us_events' => count($usEvents),
            'eu_events' => count($euEvents),
        ]) . ',"top_pages":' . json_encode(
            array_count_values(array_column($sorted, 'url'))
        ) . ',"avg_duration":' . json_encode(
            array_sum(array_column($sorted, 'duration_ms')) / count($sorted)
        ) . '}';

        // Wasteful: decode the response to "verify" it
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

    // ── Iteration 3: stream-aggregate from raw JSON without full decode ──
    // Instead of json_decode() into a massive PHP array (10,000 objects with
    // metadata, referrers, etc.), use preg_match_all to extract only the 4
    // fields we need directly from the raw JSON string. No PHP array of
    // 10,000 elements ever created — just counters updated from regex matches.
    // This avoids the ~30MB peak memory allocation of full decode.
    3 => (function () use ($payload): void {
        // Extract fields directly from raw JSON with regex
        // Each record has: "type":"pageview","url":"/page/42","duration_ms":123,"country":"US"
        preg_match_all('/"type":"(\w+)"/', $payload, $typeMatches);
        preg_match_all('/"country":"(\w+)"/', $payload, $countryMatches);
        preg_match_all('/"duration_ms":(\d+)/', $payload, $durationMatches);
        preg_match_all('/"url":"([^"]+)"/', $payload, $urlMatches);

        $types = $typeMatches[1];
        $countries = $countryMatches[1];
        $durations = $durationMatches[1];
        $urls = $urlMatches[1];
        $total = count($types);

        $euCountries = ['DE' => true, 'FR' => true, 'IT' => true, 'GB' => true];

        $typeCounts = ['pageview' => 0, 'click' => 0, 'conversion' => 0, 'error' => 0];
        $usCount = 0;
        $euCount = 0;
        $totalDuration = 0;
        $pageCounts = [];

        for ($i = 0; $i < $total; $i++) {
            $typeCounts[$types[$i]]++;

            $country = $countries[$i];
            if ($country === 'US') {
                $usCount++;
            } elseif (isset($euCountries[$country])) {
                $euCount++;
            }

            $totalDuration += (int) $durations[$i];
            $url = $urls[$i];
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
