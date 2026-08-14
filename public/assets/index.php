<?php
/**
 * Legacy entry — redirect to modern shell (absolute URL avoids /assets/ path traps).
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

$base = '';
if (class_exists('Plugin') && method_exists('Plugin', 'getWebDir')) {
    $base = rtrim((string) Plugin::getWebDir('paineldebordo', false), '/');
}
if ($base === '') {
    global $CFG_GLPI;
    $base = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/paineldebordo';
}
// GLPI 11 serves public/ without /public in the URL
header('Location: ' . $base . '/shell.php?page=assets');
exit;
