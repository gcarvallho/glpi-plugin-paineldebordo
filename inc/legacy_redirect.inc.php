<?php

/**
 * Redirect helper for legacy entry points → modern shell.
 */
function plugin_paineldebordo_legacy_redirect(string $page = 'home', array $extra = []): void
{
    $q = array_merge(['page' => $page], $extra);
    header('Location: ' . plugin_paineldebordo_legacy_shell_url($q));
    exit;
}

/**
 * @param array<string,scalar> $query
 */
function plugin_paineldebordo_legacy_shell_url(array $query): string
{
    // Resolve relative path from current script to public/shell.php
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/plugins/paineldebordo/public/');
    if ($pos !== false) {
        $base = substr($script, 0, $pos) . '/plugins/paineldebordo/public/shell.php';
        return $base . '?' . http_build_query($query);
    }
    // Fallback relative
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_ends_with($dir, '/reports') || str_ends_with($dir, '/graphs')
        || str_ends_with($dir, '/metrics') || str_ends_with($dir, '/map')
        || str_ends_with($dir, '/assets') || str_ends_with($dir, '/sh')
        || str_ends_with($dir, '/tickets')) {
        return '../shell.php?' . http_build_query($query);
    }
    return 'shell.php?' . http_build_query($query);
}
