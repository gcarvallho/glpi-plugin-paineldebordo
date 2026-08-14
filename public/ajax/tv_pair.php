<?php

/**
 * Public AJAX for TV device pairing (create code / poll status).
 */
// GLPI 11 boots core (DB, session, classes) before its LegacyFileLoadController
// require()s this file, so inc/includes.php is already loaded — skip it. Do NOT
// compute the path via dirname(__DIR__): on symlink/volume mounts (Docker)
// __DIR__ is the resolved real path, which can sit outside the GLPI tree and
// makes the computed root wrong (e.g. /var). Only fall back to a CWD-relative
// bootstrap when core really isn't loaded — matching the sibling ajax files.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');

$action = $_GET['action'] ?? $_POST['action'] ?? 'create';

try {
    if ($action === 'create') {
        $tz = (string) ($_POST['timezone'] ?? $_GET['timezone'] ?? '');
        $created = plugin_paineldebordo_tv_pair_create([
            'timezone'   => $tz,
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
        echo json_encode(['ok' => true] + $created, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'poll') {
        $code = (string) ($_GET['code'] ?? $_POST['code'] ?? '');
        $status = plugin_paineldebordo_tv_pair_poll($code);
        if ($status === null) {
            echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => true] + $status, JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_action'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server',
        'message' => __('Could not create pairing code.', 'paineldebordo'),
    ], JSON_UNESCAPED_UNICODE);
}
