<?php

/**
 * JSON BI board for active (or requested) page.
 * POST layout=… renders a draft without persisting (edit mode).
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
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/bi.php');

    plugin_paineldebordo_checkAccessJson(READ);
    plugin_paineldebordo_requireModuleJson('analysis', READ);
    plugin_paineldebordo_syncFiltersFromRequest();

    $page_id = isset($_REQUEST['page_id']) ? (string) $_REQUEST['page_id'] : null;
    $layout_override = null;
    $raw = (string) ($_POST['layout'] ?? $_GET['layout'] ?? '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $layout_override = $decoded;
        }
    }

    echo json_encode(plugin_paineldebordo_bi_board($page_id, $layout_override), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[bi_board] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'detail'  => $detail,
        'widgets' => [],
    ], JSON_UNESCAPED_UNICODE);
}
