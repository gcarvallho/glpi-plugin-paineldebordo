<?php

/**
 * JSON Assets NOC board — entity-aware fleet inventory.
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

    $ram = isset($_REQUEST['ram_mb']) ? (int) $_REQUEST['ram_mb'] : 8192;
    echo json_encode(plugin_paineldebordo_assets_board($ram), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[assets_board] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'server_error',
        'detail' => $detail,
        'kpis'   => [],
        'tiles'  => [],
        'charts' => [],
        'lists'  => [],
    ], JSON_UNESCAPED_UNICODE);
}
