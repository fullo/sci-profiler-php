<?php

declare(strict_types=1);

/**
 * String Processing — AFTER optimization.
 *
 * Fix: collect parts in an array, implode once at the end.
 * Each array append is O(1) amortized. The final implode() does a single
 * allocation for the complete string. Also computes summary stats in the
 * same loop instead of iterating twice.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

// Same 5,000 user records
$users = [];
for ($i = 0; $i < 5000; $i++) {
    $users[] = [
        'id' => $i + 1,
        'name' => 'User ' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
        'email' => 'user' . ($i + 1) . '@example.com',
        'score' => mt_rand(0, 10000) / 100,
        'active' => $i % 7 !== 0,
    ];
}

// ── Optimization 1: array of parts instead of concatenation ──
// Each $parts[] = '...' is O(1). implode() at the end does one allocation.

$parts = [];
$parts[] = '<!DOCTYPE html><html><head><title>User Report</title>';
$parts[] = '<style>table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:4px 8px}';
$parts[] = 'tr:nth-child(even){background:#f9f9f9}.inactive{color:#999}</style></head><body>';
$parts[] = '<h1>User Report</h1>';
$parts[] = '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
$parts[] = '<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Score</th><th>Status</th></tr></thead><tbody>';

// ── Optimization 2: compute summary in the same loop ──
// Avoids a second iteration over 5,000 records.
$activeCount = 0;
$totalScore = 0.0;

foreach ($users as $user) {
    $class = $user['active'] ? '' : ' class="inactive"';
    $status = $user['active'] ? 'Active' : 'Inactive';

    // ── Optimization 3: sprintf for row — single string operation ──
    $parts[] = sprintf(
        '<tr%s><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
        $class,
        $user['id'],
        htmlspecialchars($user['name']),
        htmlspecialchars($user['email']),
        number_format($user['score'], 2),
        $status,
    );

    // Summary computed inline — no second loop
    if ($user['active']) {
        $activeCount++;
    }
    $totalScore += $user['score'];
}

$parts[] = '</tbody></table>';
$parts[] = '<p>Active: ' . $activeCount . ' / ' . count($users) . '</p>';
$parts[] = '<p>Average score: ' . number_format($totalScore / count($users), 2) . '</p>';
$parts[] = '</body></html>';

// ── Single allocation for the complete string ──
$html = implode('', $parts);

echo 'Report generated: ' . strlen($html) . " bytes\n";
echo 'Users: ' . count($users) . "\n";
echo 'Active: ' . $activeCount . "\n";
