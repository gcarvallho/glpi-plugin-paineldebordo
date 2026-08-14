<?php

/**
 * Legacy entity-tab map save — requires Recursos UPDATE.
 * Prefer Configuration hub → ajax/map_coord.php for new UI.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');

error_reporting(E_ERROR | E_PARSE);

plugin_paineldebordo_checkModule('resources', UPDATE);

$ent_id = (int) ($_POST['id'] ?? 0);
$root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
$back = $root . '/front/entity.form.php?id=' . $ent_id;

if ($ent_id <= 0) {
    Session::addMessageAfterRedirect(__('Invalid entity', 'paineldebordo'), false, ERROR);
    Html::redirect($root . '/front/entity.php');
}

$lng = trim((string) ($_POST['lng'] ?? ''));
$lat = trim((string) ($_POST['lat'] ?? ''));

global $DB;

if (!$DB->TableExists('glpi_plugin_paineldebordo_map')) {
    Session::addMessageAfterRedirect(__('Map table missing', 'paineldebordo'), false, ERROR);
    Html::redirect($back);
}

if ($lng === '' && $lat === '') {
    $DB->doQuery('DELETE FROM glpi_plugin_paineldebordo_map WHERE entities_id = ' . $ent_id);
    Session::addMessageAfterRedirect(__('Coordinates cleared', 'paineldebordo'), false, INFO);
    Html::redirect($back);
}

if (!is_numeric($lat) || !is_numeric($lng)) {
    Session::addMessageAfterRedirect(__('Invalid coordinates', 'paineldebordo'), false, ERROR);
    Html::redirect($back);
}

$lat_f = (float) $lat;
$lng_f = (float) $lng;
$location = '';
$query = $DB->doQuery('SELECT name FROM glpi_entities WHERE id = ' . $ent_id);
if ($query && ($row = $DB->fetchAssoc($query))) {
    $location = (string) $row['name'];
}
$loc = $DB->escape(mb_substr($location !== '' ? $location : ('E' . $ent_id), 0, 50));

$exists = $DB->doQuery('SELECT id FROM glpi_plugin_paineldebordo_map WHERE entities_id = ' . $ent_id . ' LIMIT 1');
if ($exists && $DB->numrows($exists) > 0) {
    $DB->doQuery(
        "UPDATE glpi_plugin_paineldebordo_map SET lat = $lat_f, lng = $lng_f, location = '$loc' WHERE entities_id = $ent_id"
    );
} else {
    $DB->doQuery(
        "INSERT INTO glpi_plugin_paineldebordo_map (entities_id, location, lat, lng)
         VALUES ($ent_id, '$loc', $lat_f, $lng_f)"
    );
}

Session::addMessageAfterRedirect(__('Saved', 'paineldebordo'), false, INFO);
Html::redirect($back);
