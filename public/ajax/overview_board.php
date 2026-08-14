<?php

/**
 * JSON Overview NOC board — period/group/entity aware.
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
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/overview.php');

    plugin_paineldebordo_checkAccessJson(READ);
    plugin_paineldebordo_syncFiltersFromRequest();

    $board = plugin_paineldebordo_overview_board();
    echo json_encode($board, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[overview_board] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'warning' => __('Overview failed to load', 'paineldebordo'),
        'detail'  => $detail,
        'kpis'    => [],
        'charts'  => [],
    ], JSON_UNESCAPED_UNICODE);
}
