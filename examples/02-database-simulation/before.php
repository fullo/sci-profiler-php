<?php

declare(strict_types=1);

/**
 * Database Simulation — BEFORE optimization.
 *
 * Anti-pattern: N+1 queries.
 * Fetches a list of orders, then for EACH order makes a separate "query"
 * to look up the customer and the order items. This is the classic N+1
 * problem that plagues ORMs and lazy-loading patterns.
 *
 * Simulated with arrays + usleep() to represent real query latency.
 * Each "query" costs 50μs (simulating a fast local database).
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

// ── Simulated database tables ──

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

/**
 * Simulate a database query with latency.
 */
function dbQuery(string $description): void
{
    // 50μs per query — represents a fast local DB
    usleep(50);
}

// ── N+1 query pattern ──
// 1 query to fetch all orders
// + 500 queries to fetch each customer
// + 500 queries to fetch each order's items
// = 1,001 total queries

dbQuery('SELECT * FROM orders');

$results = [];
foreach ($orders as $order) {
    // N+1: one query per order to get customer
    dbQuery("SELECT * FROM customers WHERE id = {$order['customer_id']}");
    $customer = $customers[$order['customer_id']];

    // N+1: one query per order to get items
    dbQuery("SELECT * FROM order_items WHERE order_id = {$order['id']}");
    $items = $orderItems[$order['id']];

    $orderTotal = 0.0;
    foreach ($items as $item) {
        $orderTotal += $item['price'] * $item['quantity'];
    }

    $results[] = [
        'order_id' => $order['id'],
        'customer' => $customer['name'],
        'tier' => $customer['tier'],
        'date' => $order['date'],
        'status' => $order['status'],
        'items' => count($items),
        'total' => $orderTotal,
    ];
}

// Summary
$totalRevenue = array_sum(array_column($results, 'total'));
$avgOrder = $totalRevenue / count($results);

echo 'Orders processed: ' . count($results) . "\n";
echo 'Total queries: ' . (1 + count($orders) * 2) . "\n";
echo 'Revenue: $' . number_format($totalRevenue, 2) . "\n";
echo 'Average order: $' . number_format($avgOrder, 2) . "\n";
