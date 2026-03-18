<?php

declare(strict_types=1);

/**
 * Example 01 — String Processing: progressive optimization.
 *
 * Simulates building an HTML report from 5,000 records.
 * Run 3 times with increasing iteration number to see SCI drop:
 *
 *   php 01-string-processing.php 1    ← naive: .= in loop
 *   php 01-string-processing.php 2    ← fix: array + implode
 *   php 01-string-processing.php 3    ← refined: sprintf + single-pass stats
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

$iteration = (int) ($argv[1] ?? 1);
echo "=== String Processing — iteration {$iteration}/3 ===\n";

// ── Generate 5,000 user records (same seed for all iterations) ──
mt_srand(42);
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

$header = '<!DOCTYPE html><html><head><title>User Report</title>'
    . '<style>table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:4px 8px}'
    . 'tr:nth-child(even){background:#f9f9f9}.inactive{color:#999}</style></head><body>'
    . '<h1>User Report</h1><p>Generated: ' . date('Y-m-d H:i:s') . '</p>'
    . '<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Score</th><th>Status</th></tr></thead><tbody>';

$footer = '</tbody></table>';

match ($iteration) {
    // ── Iteration 1: Naive — string concatenation in a loop ──
    // Each .= copies the entire $html string (O(n²) memory operations).
    1 => (function () use ($users, $header, $footer): string {
        $html = $header;

        foreach ($users as $user) {
            $class = $user['active'] ? '' : ' class="inactive"';
            $status = $user['active'] ? 'Active' : 'Inactive';
            $html .= '<tr' . $class . '>';
            $html .= '<td>' . $user['id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($user['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($user['email']) . '</td>';
            $html .= '<td>' . number_format($user['score'], 2) . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '</tr>';
        }

        $html .= $footer;

        // Summary: second loop over all users
        $active = 0;
        $total = 0.0;
        foreach ($users as $user) {
            if ($user['active']) {
                $active++;
            }
            $total += $user['score'];
        }
        $html .= '<p>Active: ' . $active . '/' . count($users) . '</p>';
        $html .= '<p>Avg score: ' . number_format($total / count($users), 2) . '</p>';
        $html .= '</body></html>';

        echo 'Output: ' . strlen($html) . " bytes | Active: {$active}\n";
        return $html;
    })(),

    // ── Iteration 2: Fix — array + implode, single allocation ──
    // Each $parts[] = '...' is O(1). implode() does one allocation at the end.
    2 => (function () use ($users, $header, $footer): string {
        $parts = [$header];

        foreach ($users as $user) {
            $class = $user['active'] ? '' : ' class="inactive"';
            $status = $user['active'] ? 'Active' : 'Inactive';
            $parts[] = '<tr' . $class . '>'
                . '<td>' . $user['id'] . '</td>'
                . '<td>' . htmlspecialchars($user['name']) . '</td>'
                . '<td>' . htmlspecialchars($user['email']) . '</td>'
                . '<td>' . number_format($user['score'], 2) . '</td>'
                . '<td>' . $status . '</td>'
                . '</tr>';
        }

        $parts[] = $footer;

        // Summary: still a second loop
        $active = 0;
        $total = 0.0;
        foreach ($users as $user) {
            if ($user['active']) {
                $active++;
            }
            $total += $user['score'];
        }
        $parts[] = '<p>Active: ' . $active . '/' . count($users) . '</p>';
        $parts[] = '<p>Avg score: ' . number_format($total / count($users), 2) . '</p>';
        $parts[] = '</body></html>';

        $html = implode('', $parts);
        echo 'Output: ' . strlen($html) . " bytes | Active: {$active}\n";
        return $html;
    })(),

    // ── Iteration 3: Refined — sprintf rows + single-pass stats ──
    // All stats computed inline during the same loop. No second iteration.
    // sprintf for each row avoids intermediate concatenation.
    3 => (function () use ($users, $header, $footer): string {
        $parts = [$header];
        $active = 0;
        $total = 0.0;

        foreach ($users as $user) {
            $parts[] = sprintf(
                '<tr%s><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $user['active'] ? '' : ' class="inactive"',
                $user['id'],
                htmlspecialchars($user['name']),
                htmlspecialchars($user['email']),
                number_format($user['score'], 2),
                $user['active'] ? 'Active' : 'Inactive',
            );

            if ($user['active']) {
                $active++;
            }
            $total += $user['score'];
        }

        $parts[] = $footer;
        $parts[] = '<p>Active: ' . $active . '/' . count($users) . '</p>';
        $parts[] = '<p>Avg score: ' . number_format($total / count($users), 2) . '</p>';
        $parts[] = '</body></html>';

        $html = implode('', $parts);
        echo 'Output: ' . strlen($html) . " bytes | Active: {$active}\n";
        return $html;
    })(),

    default => throw new InvalidArgumentException("Usage: php 01-string-processing.php [1|2|3]"),
};
