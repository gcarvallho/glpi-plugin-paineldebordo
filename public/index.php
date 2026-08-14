<?php

// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include("../../../inc/includes.php");
}

Session::checkLoginUser();

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');

if (!plugin_paineldebordo_canRead()) {
    // Only GLPI Setup admins may bootstrap minimal access (no wide vision / modules).
    if (Session::haveRight('config', UPDATE)) {
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/profile.class.php');
        if (isset($_SESSION['glpiactiveprofile']['id'])) {
            PluginPaineldebordoProfile::createMinimalAccess((int) $_SESSION['glpiactiveprofile']['id']);
            PluginPaineldebordoProfile::initProfile();
        }
    }
}

Session::checkRight('plugin_paineldebordo', READ);

header('Location: shell.php');
exit;
