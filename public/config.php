<?php

/**
 * GLPI Plugins → Configure (wrench) entry point → Configuration hub.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}

Session::checkLoginUser();

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/filters.inc.php');

plugin_paineldebordo_checkAccess(READ);

$theme = plugin_paineldebordo_getFilters()['theme'] ?? 'light';
header('Location: shell.php?' . http_build_query(['page' => 'config', 'theme' => $theme]));
exit;
