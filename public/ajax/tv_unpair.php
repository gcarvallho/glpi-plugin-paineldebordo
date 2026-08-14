<?php

/**
 * Device self-unpair — Bearer token required (no GLPI session).
 * Used by TV Exit to revoke access for this display.
 */
// GLPI 11 boots core (DB, session, classes) before its LegacyFileLoadController
// require()s this file, so inc/includes.php is already loaded — skip it. Do NOT
// compute the path via dirname(__DIR__): on symlink/volume mounts (Docker)
// __DIR__ is the resolved real path, which can sit outside the GLPI tree and
// makes the computed root wrong (e.g. /var). Only fall back to a CWD-relative
// bootstrap when core really isn't loaded — matching public/tv_pair.php.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = plugin_paineldebordo_tv_extract_bearer();
if ($token === null || $token === '') {
    // Also accept body for clients that cannot set Authorization on POST
    $token = trim((string) ($_POST['tv_token'] ?? ''));
}

if ($token === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = plugin_paineldebordo_tv_pair_revoke_by_token($token);
if (!$ok) {
    // Already revoked / invalid — still OK for client cleanup
    echo json_encode(['ok' => true, 'revoked' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'revoked' => true], JSON_UNESCAPED_UNICODE);
