<?php

declare(strict_types=1);

/**
 * Example 02 — Database Simulation: progressive optimization.
 *
 * Simulates processing 500 orders with customer and item lookups.
 * Uses usleep() to simulate real database query latency (50μs per query).
 *
 *   php 02-database-simulation.php 1    ← naive: N+1 queries (1,001 total)
 *   php 02-database-simulation.php 2    ← fix: 3 batch queries, but linear scan join O(n²)
 *   php 02-database-simulation.php 3    ← refined: 3 batch + hash-map O(1) + inline aggregation
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

    // ── Iteration 2: 3 batch queries, but linear scan for join ──
    // Good: only 3 queries instead of 1,001.
    // Bad: customer lookup uses array_filter (O(n) per order = O(n²) total)
    // instead of indexed array access. Also builds a flat customer list
    // first, losing the indexed structure.
    2 => (function () use ($orders, $customers, $orderItems, &$queryCount): void {
        dbQuery('SELECT * FROM orders');
        dbQuery('SELECT * FROM customers WHERE id IN (...)');
        dbQuery('SELECT * FROM order_items WHERE order_id IN (...)');
        $queryCount = 3;

        // Simulate receiving batch results as flat arrays (no index)
        $customerList = array_values($customers);
        $itemsByOrder = [];
        foreach ($orderItems as $orderId => $items) {
            foreach ($items as $item) {
                $itemsByOrder[] = $item;
            }
        }

        $results = [];
        foreach ($orders as $order) {
            // O(n) linear scan to find customer — array_filter on 200 customers × 500 orders
            $matches = array_filter($customerList, fn ($c) => $c['id'] === $order['customer_id']);
            $customer = reset($matches);

            // O(n) linear scan for order items
            $items = array_filter($itemsByOrder, fn ($i) => $i['order_id'] === $order['id']);

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

    // ── Iteration 3: batch queries + hash-map join + inline aggregation ──
    // 3 queries + O(1) hash-map lookups + revenue computed inline.
    // No intermediate $results array, no second summary loop.
    3 => (function () use ($orders, $customers, $orderItems, &$queryCount): void {
        dbQuery('SELECT * FROM orders');
        dbQuery('SELECT * FROM customers WHERE id IN (...)');
        dbQuery('SELECT * FROM order_items WHERE order_id IN (...)');
        $queryCount = 3;

        // $customers and $orderItems are already indexed by ID — O(1) lookup
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
