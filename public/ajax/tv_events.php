<?php

/**
 * JSON feed for Modo TV — same visibility as Chamados abertos (tickets_scope).
 * GLPI 11 solution status: ACCEPTED=3, REFUSED=4 (CommonITILValidation).
 */
// GLPI 11 boots core (DB, session, classes) before its LegacyFileLoadController
// require()s this file, so inc/includes.php is already loaded — skip it. Do NOT
// compute the path via dirname(__DIR__): on symlink/volume mounts (Docker)
// __DIR__ is the resolved real path, which can sit outside the GLPI tree and
// makes the computed root wrong (e.g. /var). Only fall back to a CWD-relative
// bootstrap when core really isn't loaded — matching public/tv_pair.php.
if (!defined('GLPI_ROOT')) {
    include('../../../../inc/includes.php');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/filters.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');

$auth = plugin_paineldebordo_tv_resolve_auth();
$wide_view = false;
$warning = null;

if (!$auth['ok']) {
    http_response_code(403);
    echo json_encode([
        'ok'      => false,
        'error'   => 'forbidden',
        'warning' => __('You do not have permission for Painel de Bordo.', 'paineldebordo'),
        'events'  => [],
        'open'    => 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$device = $auth['device'];
if ($device !== null) {
    $scope = plugin_paineldebordo_tv_pair_scope($device);
    $wide_view = !empty($device['sees_all']);
    if (!$wide_view && ($device['group_ids'] ?? '') === '') {
        $warning = __('No groups assigned to your user. TV mode only shows what you can see.', 'paineldebordo');
    }
} else {
    $scope = plugin_paineldebordo_tickets_scope();
    $wide_view = plugin_paineldebordo_seesAllGroups();
    if (!$wide_view && plugin_paineldebordo_getUserGroupIds() === [] && (int) plugin_paineldebordo_getConfigValue('filter_group', '0') <= 0) {
        $warning = __('No groups assigned to your user. TV mode only shows what you can see.', 'paineldebordo');
    }
}

global $DB;

$since = plugin_paineldebordo_tv_validate_since($_GET['since'] ?? null);
$now = date('Y-m-d H:i:s');
$since_esc = $DB->escape($since);

$entity_sql = $scope['entity_sql'];
$group_join = $scope['group_join'];
$group_where = $scope['group_where'];
$group_sql = plugin_paineldebordo_tv_group_name_sql('glpi_tickets');
$group_sql_t = plugin_paineldebordo_tv_group_name_sql('t');

$events = [];

$entity_sql_t = str_replace('glpi_tickets.', 't.', $entity_sql);
$group_join_t = str_replace('glpi_tickets.', 't.', $group_join);
$group_where_t = str_replace('glpi_tickets.', 't.', $group_where);

$push = static function (array &$events, string $type, array $row) {
    $events[] = [
        'type'      => $type,
        'ticket_id' => (int) ($row['tickets_id'] ?? $row['id']),
        'title'     => (string) ($row['name'] ?? ''),
        'ts'        => (string) ($row['ts'] ?? ''),
        'status'    => (int) ($row['status'] ?? 0),
        'priority'  => (int) ($row['priority'] ?? 0),
        'group'     => (string) ($row['group_name'] ?? ''),
    ];
};

// New tickets
$sql_new = "
SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.date AS ts, glpi_tickets.status, glpi_tickets.priority,
  $group_sql AS group_name
FROM glpi_tickets
$group_join
WHERE glpi_tickets.is_deleted = 0
  $entity_sql
  AND glpi_tickets.date > '$since_esc'
  $group_where
ORDER BY glpi_tickets.date DESC
LIMIT 50";
$res = $DB->doQuery($sql_new);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $push($events, 'novo', $row);
    }
}

// Followups (kept in feed; UI does not toast these)
$sql_fu = "
SELECT f.id, f.items_id AS tickets_id, f.date AS ts, t.name, t.status, t.priority,
  $group_sql_t AS group_name
FROM glpi_itilfollowups f
INNER JOIN glpi_tickets t ON t.id = f.items_id AND f.itemtype = 'Ticket'
$group_join_t
WHERE t.is_deleted = 0
  $entity_sql_t
  AND f.date > '$since_esc'
  $group_where_t
ORDER BY f.date DESC
LIMIT 50";
$res = $DB->doQuery($sql_fu);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $push($events, 'atualizacao', $row);
    }
}

// Solution accepted (GLPI 11 CommonITILValidation::ACCEPTED = 3)
// Person validation sets date_approval (not date_mod) — filter on that.
$sql_acc = "
SELECT s.id, s.items_id AS tickets_id,
  COALESCE(s.date_approval, s.date_mod) AS ts,
  t.name, t.status, t.priority,
  $group_sql_t AS group_name
FROM glpi_itilsolutions s
INNER JOIN glpi_tickets t ON t.id = s.items_id AND s.itemtype = 'Ticket'
$group_join_t
WHERE t.is_deleted = 0
  $entity_sql_t
  AND s.status = 3
  AND COALESCE(s.date_approval, s.date_mod) > '$since_esc'
  $group_where_t
ORDER BY COALESCE(s.date_approval, s.date_mod) DESC
LIMIT 30";
$res = $DB->doQuery($sql_acc);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $push($events, 'solucao_aceita', $row);
    }
}

// Solution refused (GLPI 11 CommonITILValidation::REFUSED = 4)
$sql_sol = "
SELECT s.id, s.items_id AS tickets_id,
  COALESCE(s.date_approval, s.date_mod) AS ts,
  t.name, t.status, t.priority,
  $group_sql_t AS group_name
FROM glpi_itilsolutions s
INNER JOIN glpi_tickets t ON t.id = s.items_id AND s.itemtype = 'Ticket'
$group_join_t
WHERE t.is_deleted = 0
  $entity_sql_t
  AND s.status = 4
  AND COALESCE(s.date_approval, s.date_mod) > '$since_esc'
  $group_where_t
ORDER BY COALESCE(s.date_approval, s.date_mod) DESC
LIMIT 30";
$res = $DB->doQuery($sql_sol);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $push($events, 'solucao_negada', $row);
    }
}

// Reopen via status change logs
$sql_re = "
SELECT l.id, l.items_id AS tickets_id, l.date_mod AS ts, l.old_value, l.new_value, t.name, t.status, t.priority,
  $group_sql_t AS group_name
FROM glpi_logs l
INNER JOIN glpi_tickets t ON t.id = l.items_id AND l.itemtype = 'Ticket'
$group_join_t
WHERE t.is_deleted = 0
  $entity_sql_t
  AND l.date_mod > '$since_esc'
  AND l.id_search_option = 12
  $group_where_t
ORDER BY l.date_mod DESC
LIMIT 50";
$res = $DB->doQuery($sql_re);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $old = (int) $row['old_value'];
        $new = (int) $row['new_value'];
        if (plugin_paineldebordo_tv_classify_status_change($old, $new) === 'reabertura') {
            $push($events, 'reabertura', $row);
        }
    }
}

// SLA overdue crossing window
$sql_sla = "
SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.time_to_resolve AS ts, glpi_tickets.status, glpi_tickets.priority,
  $group_sql AS group_name
FROM glpi_tickets
$group_join
WHERE glpi_tickets.is_deleted = 0
  $entity_sql
  AND glpi_tickets.status NOT IN (5, 6)
  AND glpi_tickets.time_to_resolve IS NOT NULL
  AND glpi_tickets.time_to_resolve <= NOW()
  AND glpi_tickets.time_to_resolve > '$since_esc'
  $group_where
ORDER BY glpi_tickets.time_to_resolve DESC
LIMIT 30";
$res = $DB->doQuery($sql_sla);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $push($events, 'sla_atrasado', $row);
    }
}

$events = plugin_paineldebordo_tv_sort_events($events);

$sql_open = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
$group_join
WHERE glpi_tickets.is_deleted = 0
  $entity_sql
  AND glpi_tickets.status NOT IN (5, 6)
  $group_where";
$open = 0;
$res = $DB->doQuery($sql_open);
if ($res && ($row = $DB->fetchAssoc($res))) {
    $open = (int) $row['total'];
}

echo json_encode([
    'ok'          => true,
    'server_ts'   => $now,
    'since'       => $since,
    'open'        => $open,
    'wide_view'   => $wide_view,
    'warning'     => $warning,
    'catalog'     => plugin_paineldebordo_tv_event_catalog(),
    'toast_types' => plugin_paineldebordo_tv_toast_types(),
    'events'      => $events,
], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[tv_events] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'warning' => __('TV mode connection failed', 'paineldebordo'),
        'detail'  => $detail,
        'events'  => [],
    ], JSON_UNESCAPED_UNICODE);
}
