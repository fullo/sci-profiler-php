<?php

declare(strict_types=1);

/**
 * Database Simulation — AFTER optimization.
 *
 * Fix: batch all queries upfront instead of N+1.
 * 1 query for orders + 1 query for ALL customers + 1 query for ALL items = 3.
 * Then join in PHP with indexed lookups (hash map, O(1) per key).
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

// ── Same simulated database tables ──

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

function dbQuery(string $description): void
{
    usleep(50);
}

// ── Optimization: 3 batch queries instead of 1,001 ──
// Real-world equivalent:
//   SELECT * FROM orders
//   SELECT * FROM customers WHERE id IN (1,2,3,...200)
//   SELECT * FROM order_items WHERE order_id IN (1,2,3,...500)

dbQuery('SELECT * FROM orders');
dbQuery('SELECT * FROM customers WHERE id IN (...)');  // 1 query, all customers
dbQuery('SELECT * FROM order_items WHERE order_id IN (...)');  // 1 query, all items

// ── Join in PHP with hash-map lookups (O(1) per key) ──
// The $customers and $orderItems arrays are already indexed by ID.

$results = [];
$totalRevenue = 0.0;

foreach ($orders as $order) {
    // O(1) lookup — no query
    $customer = $customers[$order['customer_id']];
    $items = $orderItems[$order['id']];

    $orderTotal = 0.0;
    foreach ($items as $item) {
        $orderTotal += $item['price'] * $item['quantity'];
    }

    $totalRevenue += $orderTotal;

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

$avgOrder = $totalRevenue / count($results);

echo 'Orders processed: ' . count($results) . "\n";
echo 'Total queries: 3' . "\n";
echo 'Revenue: $' . number_format($totalRevenue, 2) . "\n";
echo 'Average order: $' . number_format($avgOrder, 2) . "\n";
