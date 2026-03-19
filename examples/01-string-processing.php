<?php

declare(strict_types=1);

/**
 * Example 01 — String Processing: progressive optimization.
 *
 * Simulates building an HTML report from 5,000 records.
 * Run 3 times with increasing iteration number to see SCI drop:
 *
 *   php 01-string-processing.php 1    ← naive: .= in loop + str_replace on 2MB + substr_count
 *   php 01-string-processing.php 2    ← fix: array + implode (but still 2 loops)
 *   php 01-string-processing.php 3    ← refined: sprintf + single-pass stats in one loop
 *
 * @author fullo <https://github.com/fullo>
 * @license MIT
 * @version 1.0
 */

$iteration = (int) ($argv[1] ?? 1);
echo "=== String Processing — iteration {$iteration}/3 ===\n";

// ── Generate 20,000 user records (same seed for all iterations) ──
// Larger dataset makes string handling differences measurable.
mt_srand(42);
$users = [];
for ($i = 0; $i < 20000; $i++) {
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
    // ── Iteration 1: Maximally naive — concatenation + redundant processing ──
    // 7 separate .= per row (each copies the entire growing string).
    // After building the HTML, runs str_replace on the full string to
    // "fix" the CSS class names — a common anti-pattern in legacy code.
    // Then re-counts everything in separate loops.
    1 => (function () use ($users, $header, $footer): string {
        $html = $header;

        foreach ($users as $user) {
            $class = $user['active'] ? '' : ' class="inactive"';
            $status = $user['active'] ? 'Active' : 'Inactive';
            // 7 separate concatenations per row — each copies entire $html
            $html .= '<tr' . $class . '>';
            $html .= '<td>' . $user['id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($user['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($user['email']) . '</td>';
            $html .= '<td>' . number_format($user['score'], 2) . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '</tr>';
        }

        $html .= $footer;

        // Wasteful: "fix" class names via str_replace on the entire ~2MB string
        $html = str_replace('class="inactive"', 'class="user-inactive"', $html);
        $html = str_replace('class="user-inactive"', 'class="inactive"', $html);

        // Wasteful: count active users by parsing the HTML we just built
        $active = substr_count($html, '<td>Active</td>');
        $inactive = substr_count($html, '<td>Inactive</td>');

        // Wasteful: compute total score in a separate loop
        $total = 0.0;
        foreach ($users as $user) {
            $total += $user['score'];
        }

        $html .= '<p>Active: ' . $active . '/' . ($active + $inactive) . '</p>';
        $html .= '<p>Avg score: ' . number_format($total / count($users), 2) . '</p>';
        $html .= '</body></html>';

        echo 'Output: ' . strlen($html) . " bytes | Active: {$active}\n";
        return $html;
    })(),

    // ── Iteration 2: array + implode, but still two loops ──
    // Fixed: no more .= concatenation. Uses array + implode.
    // Remaining issue: summary stats computed in a separate second loop
    // over all 20,000 records. Also uses string concatenation for each row
    // instead of sprintf.
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

        // Still a second loop for summary — iterates 20,000 records again
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
