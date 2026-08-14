<?php

/**
 * GET/POST BI canvas layout (per-user bi_layout).
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/filters.inc.php');
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/bi.php');

    plugin_paineldebordo_checkAccessJson(READ);
    plugin_paineldebordo_requireModuleJson('analysis', READ);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        echo json_encode([
            'ok'       => true,
            'layout'   => plugin_paineldebordo_bi_layout_get(),
            'palette'  => plugin_paineldebordo_bi_palette(),
            'defaults' => plugin_paineldebordo_bi_layout_defaults(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method']);
        exit;
    }

    plugin_paineldebordo_requireModuleJson('analysis', UPDATE);

    // CSRF is enforced by GLPI (csrf_compliant) — do not validate twice
    // (tokens are single-use; a second check makes Save appear to "do nothing").

    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'reset') {
        plugin_paineldebordo_setConfigValue('bi_layout', '');
        echo json_encode([
            'ok'     => true,
            'layout' => plugin_paineldebordo_bi_layout_defaults(),
            'reset'  => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = (string) ($_POST['layout'] ?? '');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'layout']);
        exit;
    }

    $layout = plugin_paineldebordo_bi_layout_save($decoded);
    echo json_encode(['ok' => true, 'layout' => $layout], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[bi_layout] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $detail], JSON_UNESCAPED_UNICODE);
}
