<?php

/**
 * Authenticated JSON endpoint to approve a TV pairing code.
 * CSRF is enforced by GLPI (csrf_compliant) — do not validate twice
 * (tokens are single-use and a second check returns "access denied").
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');

if (!plugin_paineldebordo_canRead()) {
    http_response_code(403);
    echo json_encode([
        'ok'      => false,
        'error'   => 'forbidden',
        'message' => __('You do not have permission for Painel de Bordo.', 'paineldebordo'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'      => false,
        'error'   => 'method',
        'message' => __('Invalid or expired code', 'paineldebordo'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = trim((string) ($_POST['code'] ?? $_POST['tv_pair_code'] ?? ''));
if ($code === '') {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'error'   => 'empty',
        'message' => __('Invalid or expired code', 'paineldebordo'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $ok = plugin_paineldebordo_tv_pair_approve($code);
    if (!$ok) {
        echo json_encode([
            'ok'      => false,
            'error'   => 'invalid',
            'message' => __('Invalid or expired code', 'paineldebordo'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/audit.inc.php');
    if (function_exists('plugin_paineldebordo_audit_log')) {
        plugin_paineldebordo_audit_log('tv_pair_approve', $code, 'tv_pair');
    }
    echo json_encode([
        'ok'      => true,
        'message' => __('TV linked successfully', 'paineldebordo'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server',
        'message' => __('TV link failed', 'paineldebordo') . ': ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
