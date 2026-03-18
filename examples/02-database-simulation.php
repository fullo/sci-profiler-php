<?php

declare(strict_types=1);

/**
 * Example 02 — Database Simulation: progressive optimization.
 *
 * Simulates processing 500 orders with customer and item lookups.
 * Uses usleep() to simulate real database query latency (50μs per query).
 *
 *   php 02-database-simulation.php 1    ← naive: N+1 queries (1,001 total)
 *   php 02-database-simulation.php 2    ← fix: 3 batch queries + hash join
 *   php 02-database-simulation.php 3    ← refined: batch + inline aggregation
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

$iteration = (int) ($argv[1] ?? 1);
echo "=== Database Simulation — iteration {$iteration}/3 ===\n";

// ── Simulated database tables (same seed for reproducibility) ──
mt_srand(42);

$customers = [];
for ($i = 1; $i <= 200; $i++) {
    $customers[$i] = [
        'id' => $i,
        'name' => 'Customer ' . $i,
        'email' => 'customer' . $i . '@shop.example.com',
        'tier' => ['bronze', 'silver', 'gold', 'platinum'][$i % 4],
    ];
}

$orders = [];
$orderItems = [];
$itemId = 0;
for ($i = 1; $i <= 500; $i++) {
    $customerId = mt_rand(1, 200);
    $orders[$i] = [
        'id' => $i,
        'customer_id' => $customerId,
        'date' => date('Y-m-d', strtotime("-{$i} days")),
        'status' => ['pending', 'shipped', 'delivered', 'cancelled'][$i % 4],
    ];

    $numItems = mt_rand(1, 5);
    $orderItems[$i] = [];
    for ($j = 0; $j < $numItems; $j++) {
        $itemId++;
        $orderItems[$i][] = [
            'id' => $itemId,
            'order_id' => $i,
            'product' => 'Product ' . mt_rand(1, 100),
            'quantity' => mt_rand(1, 10),
            'price' => mt_rand(500, 15000) / 100,
        ];
    }
}

/** Simulate a database query with latency (50μs). */
function dbQuery(string $description): void
{
    usleep(50);
}

$queryCount = 0;

match ($iteration) {
    // ── Iteration 1: N+1 queries ──
    // 1 query for orders + 500 for customers + 500 for items = 1,001 queries
    1 => (function () use ($orders, $customers, $orderItems, &$queryCount): void {
        dbQuery('SELECT * FROM orders');
        $queryCount = 1;

        $results = [];
        foreach ($orders as $order) {
            dbQuery("SELECT * FROM customers WHERE id = {$order['customer_id']}");
            $queryCount++;
            $customer = $customers[$order['customer_id']];

            dbQuery("SELECT * FROM order_items WHERE order_id = {$order['id']}");
            $queryCount++;
            $items = $orderItems[$order['id']];

            $total = 0.0;
            foreach ($items as $item) {
                $total += $item['price'] * $item['quantity'];
            }

            $results[] = ['order_id' => $order['id'], 'customer' => $customer['name'], 'total' => $total];
        }

        // Summary: separate loop
        $revenue = 0.0;
        foreach ($results as $r) {
            $revenue += $r['total'];
        }

        echo "Orders: " . count($results) . " | Queries: {$queryCount} | Revenue: $" . number_format($revenue, 2) . "\n";
    })(),

    // ── Iteration 2: 3 batch queries ──
    // Fetch all data upfront, join in PHP with O(1) hash lookups.
    2 => (function () use ($orders, $customers, $orderItems, &$queryCount): void {
        dbQuery('SELECT * FROM orders');
        dbQuery('SELECT * FROM customers WHERE id IN (...)');
        dbQuery('SELECT * FROM order_items WHERE order_id IN (...)');
        $queryCount = 3;

        $results = [];
        foreach ($orders as $order) {
            $customer = $customers[$order['customer_id']];
            $items = $orderItems[$order['id']];

            $total = 0.0;
            foreach ($items as $item) {
                $total += $item['price'] * $item['quantity'];
            }

            $results[] = ['order_id' => $order['id'], 'customer' => $customer['name'], 'total' => $total];
        }

        // Summary: separate loop
        $revenue = 0.0;
        foreach ($results as $r) {
            $revenue += $r['total'];
        }

        echo "Orders: " . count($results) . " | Queries: {$queryCount} | Revenue: $" . number_format($revenue, 2) . "\n";
    })(),

    // ── Iteration 3: batch + inline aggregation ──
    // Same 3 queries, but revenue computed inline — no second loop,
    // no intermediate $results array (saves memory + CPU).
    3 => (function () use ($orders, $customers, $orderItems, &$queryCount): void {
        dbQuery('SELECT * FROM orders');
        dbQuery('SELECT * FROM customers WHERE id IN (...)');
        dbQuery('SELECT * FROM order_items WHERE order_id IN (...)');
        $queryCount = 3;

        $revenue = 0.0;
        $count = 0;

        foreach ($orders as $order) {
            $items = $orderItems[$order['id']];

            $orderTotal = 0.0;
            foreach ($items as $item) {
                $orderTotal += $item['price'] * $item['quantity'];
            }
            $revenue += $orderTotal;
            $count++;
        }

        echo "Orders: {$count} | Queries: {$queryCount} | Revenue: $" . number_format($revenue, 2) . "\n";
    })(),

    default => throw new InvalidArgumentException("Usage: php 02-database-simulation.php [1|2|3]"),
};
