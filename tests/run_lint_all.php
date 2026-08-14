<?php

/**
 * php -l on every plugin PHP file (excludes _legacy_ref).
 *
 * Run: php tests/run_lint_all.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = 0;
$passed = 0;
$skipped = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $norm = str_replace('\\', '/', $path);
    if (str_contains($norm, '/_legacy_ref/')) {
        $skipped++;
        continue;
    }
    if (str_contains($norm, '/vendor/')) {
        $skipped++;
        continue;
    }
    // Vendored PHP5-era PDF stack (curly-brace offsets) — not part of modern shell
    if (str_contains($norm, '/public/inc/html2pdf/')) {
        $skipped++;
        continue;
    }
    $files[] = $path;
}
sort($files);

$phpBin = PHP_BINARY;
foreach ($files as $path) {
    $rel = substr($path, strlen($root) + 1);
    $out = [];
    $code = 0;
    exec(escapeshellarg($phpBin) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code === 0) {
        $passed++;
        echo "[PASS] lint $rel\n";
    } else {
        $failed++;
        echo "[FAIL] lint $rel → " . implode(' ', $out) . "\n";
    }
}

echo "\n---\nLint passed: $passed  Failed: $failed  Skipped: $skipped\n";
exit($failed > 0 ? 1 : 0);
