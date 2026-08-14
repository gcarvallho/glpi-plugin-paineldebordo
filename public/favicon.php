<?php

/**
 * Dynamic favicon (SVG) — color from ?c= (cache-bust) or branding config.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this file,
// so inc/includes.php is already loaded — skip it. Don't compute the path via
// dirname(__DIR__): on symlink/volume mounts (Docker) __DIR__ is the resolved
// real path, which can sit outside the GLPI tree. Only fall back to a
// CWD-relative bootstrap when core isn't loaded — matching public/tv_pair.php.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/branding.inc.php');

$color = null;
if (isset($_GET['c'])) {
    $raw = '#' . ltrim((string) $_GET['c'], '#');
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $raw)) {
        $color = strtolower($raw);
    }
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo plugin_paineldebordo_favicon_svg($color);
