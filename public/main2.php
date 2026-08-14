<?php
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}
Session::checkLoginUser();
header('Location: shell.php?page=home');
exit;
