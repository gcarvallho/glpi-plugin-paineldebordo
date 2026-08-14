<?php

/**
 * Authenticated audit beacon — lets client-side actions (chart export, which
 * happens entirely in the browser with no server round-trip) record a log
 * entry. Only a small whitelist of actions is accepted, so this can't be used
 * to spam arbitrary events. No CSRF is required: it merely appends one
 * append-only row for the already-authenticated session user.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this file;
// only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/audit.inc.php');

if (!plugin_paineldebordo_canRead()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$allowed = ['export_chart'];
if (!in_array($action, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'action'], JSON_UNESCAPED_UNICODE);
    exit;
}

$detail = (string) ($_POST['detail'] ?? '');
plugin_paineldebordo_audit_log($action, $detail, 'chart');

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
