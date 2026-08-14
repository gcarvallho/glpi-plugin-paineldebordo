<?php

/**
 * Orchestrator: lint + unit + asset suites.
 *
 * Run: php tests/run_all.php
 */

declare(strict_types=1);

$php = PHP_BINARY;
$dir = __DIR__;
$suites = [
    'lint'  => $dir . '/run_lint_all.php',
    'unit'  => $dir . '/run_unit_tests.php',
    'acl'   => $dir . '/run_acl_tests.php',
    'asset' => $dir . '/run_asset_tests.php',
];

$failedSuites = 0;
foreach ($suites as $name => $script) {
    echo "\n========== SUITE: $name ==========\n";
    $code = 0;
    passthru(escapeshellarg($php) . ' ' . escapeshellarg($script), $code);
    if ($code !== 0) {
        $failedSuites++;
        echo ">>> SUITE $name FAILED (exit $code)\n";
    } else {
        echo ">>> SUITE $name OK\n";
    }
}

echo "\n========== SUMMARY ==========\n";
echo $failedSuites === 0 ? "ALL SUITES PASSED\n" : "FAILED SUITES: $failedSuites\n";
exit($failedSuites > 0 ? 1 : 0);
