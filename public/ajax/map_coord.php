<?php

/**
 * Secure map coordinates save/clear (JSON).
 * Requires Recursos UPDATE.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');

header('Content-Type: application/json; charset=utf-8');

plugin_paineldebordo_requireModuleJson('resources', UPDATE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$entity_id = (int) ($_POST['entities_id'] ?? $_POST['id'] ?? 0);
$lat_raw = trim((string) ($_POST['lat'] ?? ''));
$lng_raw = trim((string) ($_POST['lng'] ?? ''));
$clear = isset($_POST['clear']) || ($lat_raw === '' && $lng_raw === '');

if ($entity_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'entity']);
    exit;
}

global $DB;

if (!$DB->TableExists('glpi_plugin_paineldebordo_map')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'no_table']);
    exit;
}

if ($clear) {
    $DB->doQuery('DELETE FROM glpi_plugin_paineldebordo_map WHERE entities_id = ' . $entity_id);
    echo json_encode(['ok' => true, 'cleared' => true, 'entities_id' => $entity_id]);
    exit;
}

if (!is_numeric($lat_raw) || !is_numeric($lng_raw)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'coords']);
    exit;
}

$lat = (float) $lat_raw;
$lng = (float) $lng_raw;
$name = '';
$res = $DB->doQuery('SELECT name FROM glpi_entities WHERE id = ' . $entity_id);
if ($res && ($row = $DB->fetchAssoc($res))) {
    $name = (string) $row['name'];
}
$loc = $DB->escape(mb_substr($name !== '' ? $name : ('E' . $entity_id), 0, 50));

$exists = $DB->doQuery('SELECT id FROM glpi_plugin_paineldebordo_map WHERE entities_id = ' . $entity_id . ' LIMIT 1');
if ($exists && $DB->numrows($exists) > 0) {
    $DB->doQuery(
        "UPDATE glpi_plugin_paineldebordo_map SET lat = $lat, lng = $lng, location = '$loc' WHERE entities_id = $entity_id"
    );
} else {
    $DB->doQuery(
        "INSERT INTO glpi_plugin_paineldebordo_map (entities_id, location, lat, lng)
         VALUES ($entity_id, '$loc', $lat, $lng)"
    );
}

echo json_encode([
    'ok'          => true,
    'entities_id' => $entity_id,
    'lat'         => $lat,
    'lng'         => $lng,
    'location'    => $name,
]);
