<?php

declare(strict_types=1);

/**
 * String Processing — BEFORE optimization.
 *
 * Anti-pattern: string concatenation inside a loop.
 * Each .= on a large string forces PHP to reallocate and copy the entire
 * buffer, resulting in O(n²) memory copies.
 *
 * This simulates building an HTML report from 5,000 records — a common
 * task in admin panels, CSV exports, and email digest generators.
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

// Simulate 5,000 user records
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

// ── Anti-pattern: concatenation in loop ──
// Each .= copies the entire $html string, which grows with every iteration.
// For 5,000 rows, this means ~12.5 million characters copied cumulatively.

$html = '<!DOCTYPE html><html><head><title>User Report</title>';
$html .= '<style>table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:4px 8px}';
$html .= 'tr:nth-child(even){background:#f9f9f9}.inactive{color:#999}</style></head><body>';
$html .= '<h1>User Report</h1>';
$html .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
$html .= '<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Score</th><th>Status</th></tr></thead><tbody>';

foreach ($users as $user) {
    $class = $user['active'] ? '' : ' class="inactive"';
    $status = $user['active'] ? 'Active' : 'Inactive';
    $scoreFormatted = number_format($user['score'], 2);

    // Each concatenation copies the entire $html string
    $html .= '<tr' . $class . '>';
    $html .= '<td>' . $user['id'] . '</td>';
    $html .= '<td>' . htmlspecialchars($user['name']) . '</td>';
    $html .= '<td>' . htmlspecialchars($user['email']) . '</td>';
    $html .= '<td>' . $scoreFormatted . '</td>';
    $html .= '<td>' . $status . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

// Summary: count active/inactive
$activeCount = 0;
$totalScore = 0.0;
foreach ($users as $user) {
    if ($user['active']) {
        $activeCount++;
    }
    $totalScore += $user['score'];
}

$html .= '<p>Active: ' . $activeCount . ' / ' . count($users) . '</p>';
$html .= '<p>Average score: ' . number_format($totalScore / count($users), 2) . '</p>';
$html .= '</body></html>';

echo 'Report generated: ' . strlen($html) . " bytes\n";
echo 'Users: ' . count($users) . "\n";
echo 'Active: ' . $activeCount . "\n";
