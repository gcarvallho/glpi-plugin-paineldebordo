<?php

/**
 * JSON paginated asset list by KPI / tile / alert kind.
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
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/assets.php');

    plugin_paineldebordo_checkAccessJson(READ);
    plugin_paineldebordo_requireModuleJson('resources', READ);
    plugin_paineldebordo_syncFiltersFromRequest();

    $kind = (string) ($_REQUEST['kind'] ?? 'computers');
    $kind = preg_replace('/[^a-zA-Z0-9_]/', '', $kind) ?: 'computers';

    echo json_encode(plugin_paineldebordo_assets_list($kind, [
        'page'    => (int) ($_REQUEST['page'] ?? 1),
        'limit'   => (int) ($_REQUEST['limit'] ?? 25),
        'q'       => (string) ($_REQUEST['q'] ?? ''),
        'ram_mb'  => (int) ($_REQUEST['ram_mb'] ?? 8192),
        'itemtype'=> (string) ($_REQUEST['itemtype'] ?? ''),
    ]), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[assets_list] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'server_error',
        'detail'=> $detail,
        'rows'  => [],
        'total' => 0,
    ], JSON_UNESCAPED_UNICODE);
}
