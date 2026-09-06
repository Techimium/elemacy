<?php

/**
 * Micro test harness: check($name, $fn) records PASS/FAIL/ERROR, and
 * conclude() prints the summary and sets the process exit code. Zero
 * dependencies by design — the release lockfile stays untouched.
 */

$GLOBALS['__test_results'] = [];

function check(string $name, callable $fn): void
{
    try {
        $ok = (bool) $fn();
        $GLOBALS['__test_results'][] = [$ok ? 'PASS' : 'FAIL', $name];
    } catch (\Throwable $e) {
        $GLOBALS['__test_results'][] = ['ERROR', $name . ' — ' . $e->getMessage()];
    }
}

function conclude(): void
{
    $failed = 0;

    foreach ($GLOBALS['__test_results'] as [$status, $name]) {
        echo str_pad($status, 6) . $name . "\n";

        if ($status !== 'PASS') {
            $failed++;
        }
    }

    $total = count($GLOBALS['__test_results']);
    echo "\n" . ($total - $failed) . "/{$total} passed\n";

    if ($failed > 0) {
        exit(1);
    }
}
