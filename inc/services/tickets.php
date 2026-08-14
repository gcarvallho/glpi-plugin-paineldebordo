<?php

/**
 * Ticket queries for Painel de Bordo (modern views).
 */

include_once dirname(__DIR__) . '/access.inc.php';
include_once dirname(__DIR__) . '/filters.inc.php';
include_once dirname(__DIR__) . '/tv.inc.php';

/**
 * @return array{entity_sql:string,entity_ids:string,group_join:string,group_where:string}
 */
function plugin_paineldebordo_tickets_scope(?int $force_group = null, ?int $force_entity = null): array
{
    global $DB;

    $sel_ent = plugin_paineldebordo_getConfigValue('entity', '');
    $focus = (int) plugin_paineldebordo_getConfigValue('filter_entity_focus', '-1');
    if ($force_entity !== null && $force_entity >= 0) {
        $ent = (string) (int) $force_entity;
    } elseif ($focus >= 0) {
        $ent = (string) $focus;
    } elseif ($sel_ent === '' || $sel_ent === '-1') {
        $entities = $_SESSION['glpiactiveentities'] ?? [];
        if (!$entities && class_exists('Profile_User')) {
            $entities = Profile_User::getUserEntitiesForRight(
                $_SESSION['glpiID'],
                Ticket::$rightname,
                Ticket::READALL
            );
        }
        $ent = $entities ? implode(',', array_map('intval', (array) $entities)) : '0';
    } else {
        $ent = preg_replace('/[^0-9,]/', '', $sel_ent);
    }

    $entity_sql = "AND glpi_tickets.entities_id IN ($ent)";

    $group_join = '';
    $group_where = '';

    $gid = $force_group;
    if ($gid === null) {
        $gid = (int) plugin_paineldebordo_getConfigValue('filter_group', '0');
    }

    if ($gid > 0) {
        plugin_paineldebordo_assertGroupAccess($gid);
        // assert may have cleared a stale filter — re-read
        if ($force_group === null) {
            $gid = (int) plugin_paineldebordo_getConfigValue('filter_group', '0');
        } elseif (!plugin_paineldebordo_seesAllGroups()
            && !in_array($gid, plugin_paineldebordo_getUserGroupIds(), true)
        ) {
            $gid = 0;
        }
    }

    if ($gid > 0) {
        $group_join = ' INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2 ';
        $group_where = ' AND gt.groups_id = ' . (int) $gid . ' ';
    } elseif (!plugin_paineldebordo_seesAllGroups()) {
        $gids = plugin_paineldebordo_getUserGroupIds();
        if ($gids) {
            $gin = implode(',', $gids);
            $group_join = ' INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2 ';
            $group_where = " AND gt.groups_id IN ($gin) ";
        } else {
            $group_where = ' AND 0=1 ';
        }
    }

    return [
        'entity_sql'  => $entity_sql,
        'entity_ids'  => $ent,
        'group_join'  => $group_join,
        'group_where' => $group_where,
    ];
}

function plugin_paineldebordo_tickets_open_status_sql(): string
{
    return 'AND glpi_tickets.status NOT IN (5, 6)';
}

/**
 * Join + where for tickets assigned to a technician (glpi_tickets_users type=2).
 */
function plugin_paineldebordo_tickets_tech_sql(int $users_id): array
{
    $users_id = (int) $users_id;
    if ($users_id <= 0) {
        return ['join' => '', 'where' => ' AND 0=1 '];
    }
    return [
        'join'  => " INNER JOIN glpi_tickets_users tu_me ON tu_me.tickets_id = glpi_tickets.id AND tu_me.type = 2 AND tu_me.users_id = $users_id ",
        'where' => '',
    ];
}

/**
 * Join + where for tickets where user is requester (glpi_tickets_users type=1).
 */
function plugin_paineldebordo_tickets_requester_sql(int $users_id): array
{
    $users_id = (int) $users_id;
    if ($users_id <= 0) {
        return ['join' => '', 'where' => ' AND 0=1 '];
    }
    return [
        'join'  => " INNER JOIN glpi_tickets_users tu_req ON tu_req.tickets_id = glpi_tickets.id AND tu_req.type = 1 AND tu_req.users_id = $users_id ",
        'where' => '',
    ];
}

/**
 * Where for tickets where user (or one of their groups) is observer
 * (glpi_tickets_users / glpi_groups_tickets type=3). Uses EXISTS instead of a
 * JOIN — observer can match via user OR group, a plain join would need two
 * joins and risk duplicate rows.
 *
 * @param int[] $group_ids
 */
function plugin_paineldebordo_tickets_observer_sql(int $users_id, array $group_ids = []): array
{
    $users_id = (int) $users_id;
    $group_ids = array_values(array_filter(array_map('intval', $group_ids), static fn ($id) => $id > 0));

    $conditions = [];
    if ($users_id > 0) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM glpi_tickets_users tu_obs
            WHERE tu_obs.tickets_id = glpi_tickets.id AND tu_obs.type = 3 AND tu_obs.users_id = $users_id
        )";
    }
    if ($group_ids !== []) {
        $ids = implode(',', $group_ids);
        $conditions[] = "EXISTS (
            SELECT 1 FROM glpi_groups_tickets gt_obs
            WHERE gt_obs.tickets_id = glpi_tickets.id AND gt_obs.type = 3 AND gt_obs.groups_id IN ($ids)
        )";
    }

    if ($conditions === []) {
        return ['join' => '', 'where' => ' AND 0=1 '];
    }

    return [
        'join'  => '',
        'where' => ' AND (' . implode(' OR ', $conditions) . ') ',
    ];
}

/**
 * Parse list sort/filter query params (allow-listed).
 *
 * @param array<string,mixed>|null $src
 * @return array{sort:?string,dir:string,q:string,status:?int,priority:?int,late:?string}
 */
function plugin_paineldebordo_tickets_list_query_opts(?array $src = null): array
{
    $src = $src ?? $_GET;
    $sort_ok = ['id', 'name', 'status', 'priority', 'date', 'late'];
    $sort = isset($src['sort']) ? (string) $src['sort'] : '';
    if ($sort !== '' && !in_array($sort, $sort_ok, true)) {
        $sort = '';
    }
    $dir = strtolower((string) ($src['dir'] ?? 'asc'));
    if ($dir !== 'desc') {
        $dir = 'asc';
    }
    $q = trim((string) ($src['q'] ?? ''));
    if (strlen($q) > 120) {
        $q = substr($q, 0, 120);
    }
    $status = null;
    if (isset($src['status']) && $src['status'] !== '' && $src['status'] !== null) {
        $status = (int) $src['status'];
    }
    $priority = null;
    if (isset($src['priority']) && $src['priority'] !== '' && $src['priority'] !== null) {
        $priority = (int) $src['priority'];
    }
    $late = null;
    if (isset($src['late']) && ($src['late'] === '0' || $src['late'] === '1')) {
        $late = (string) $src['late'];
    }
    $view = null;
    if (isset($src['view']) && in_array($src['view'], ['validation', 'solution'], true)) {
        $view = (string) $src['view'];
    }
    return [
        'sort'     => $sort !== '' ? $sort : null,
        'dir'      => $dir,
        'q'        => $q,
        'status'   => $status,
        'priority' => $priority,
        'late'     => $late,
        'view'     => $view,
    ];
}

/**
 * Ticket list for shell pages (Chamados / Para mim / Abertos por mim / …).
 *
 * KPI total/late = always open-only (status NOT IN 5,6).
 * List rows: Status "All" includes Solved(5) and Closed(6); a specific status
 * uses exact match (no open-only mask that would empty 5/6 filters).
 *
 * @return array{total:int,late:int,rows:array<int,array>,opts:array}
 */
function plugin_paineldebordo_tickets_open_list(
    ?int $force_group = null,
    ?int $force_entity = null,
    int $limit = 200,
    ?int $force_tech = null,
    ?int $force_requester = null,
    array $opts = [],
    ?int $force_observer = null
): array {
    global $DB;

    if ($opts === []) {
        $opts = plugin_paineldebordo_tickets_list_query_opts();
    } else {
        $opts = plugin_paineldebordo_tickets_list_query_opts($opts);
    }

    $scope = plugin_paineldebordo_tickets_scope($force_group, $force_entity);
    $open_status_sql = plugin_paineldebordo_tickets_open_status_sql();
    // List status mask: none for "All" (include 5/6); exact status goes in $filter_sql.
    $list_status_sql = '';
    // Pseudo-status views (Validação / Aprovação columns on the TV board) —
    // reuse the exact same conditions the board already counts by, in place
    // of the normal open/status filters.
    if ($opts['view'] === 'validation') {
        $view_sql = ' AND ' . plugin_paineldebordo_tv_validation_waiting_sql('glpi_tickets');
        $open_status_sql = $view_sql;
        $list_status_sql = $view_sql;
    } elseif ($opts['view'] === 'solution') {
        $view_sql = ' AND ' . plugin_paineldebordo_tv_solution_approved_sql('glpi_tickets');
        $open_status_sql = $view_sql;
        $list_status_sql = $view_sql;
    }
    $person = ['join' => '', 'where' => ''];
    if ($force_tech !== null) {
        $person = plugin_paineldebordo_tickets_tech_sql((int) $force_tech);
    } elseif ($force_requester !== null) {
        $person = plugin_paineldebordo_tickets_requester_sql((int) $force_requester);
    } elseif ($force_observer !== null) {
        $observer_groups = function_exists('plugin_paineldebordo_getUserGroupIds')
            ? plugin_paineldebordo_getUserGroupIds()
            : [];
        $person = plugin_paineldebordo_tickets_observer_sql((int) $force_observer, $observer_groups);
    }

    $filter_sql = '';
    if ($opts['q'] !== '') {
        if (ctype_digit($opts['q'])) {
            $filter_sql .= ' AND glpi_tickets.id = ' . (int) $opts['q'];
        } else {
            $like = $DB->escape('%' . $opts['q'] . '%');
            $filter_sql .= " AND glpi_tickets.name LIKE '$like'";
        }
    }
    if ($opts['status'] !== null && $opts['view'] === null) {
        $filter_sql .= ' AND glpi_tickets.status = ' . (int) $opts['status'];
    }
    if ($opts['priority'] !== null) {
        $filter_sql .= ' AND glpi_tickets.priority = ' . (int) $opts['priority'];
    }
    if ($opts['late'] === '1') {
        $filter_sql .= ' AND glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW()';
    } elseif ($opts['late'] === '0') {
        $filter_sql .= ' AND (glpi_tickets.time_to_resolve IS NULL OR glpi_tickets.time_to_resolve >= NOW())';
    }

    $dir_sql = strtoupper($opts['dir']) === 'DESC' ? 'DESC' : 'ASC';
    $order_sql = 'ORDER BY glpi_tickets.priority DESC, glpi_tickets.date ASC';
    if ($opts['sort'] !== null) {
        $col_map = [
            'id'       => 'glpi_tickets.id',
            'name'     => 'glpi_tickets.name',
            'status'   => 'glpi_tickets.status',
            'priority' => 'glpi_tickets.priority',
            'date'     => 'glpi_tickets.date',
            'late'     => '(CASE WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW() THEN 0 ELSE 1 END)',
        ];
        $col = $col_map[$opts['sort']] ?? null;
        if ($col !== null) {
            $order_sql = "ORDER BY $col $dir_sql, glpi_tickets.id ASC";
        }
    }

    // KPI Abertos / Atrasados: always open-only, independent of Status filter.
    $sql_count = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
{$person['join']}
WHERE glpi_tickets.is_deleted = 0
{$open_status_sql}
{$scope['entity_sql']}
{$scope['group_where']}
{$person['where']}";

    $total = 0;
    $res = $DB->doQuery($sql_count);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $total = (int) $row['total'];
    }

    $sql_late = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
{$person['join']}
WHERE glpi_tickets.is_deleted = 0
{$open_status_sql}
{$scope['entity_sql']}
{$scope['group_where']}
{$person['where']}
AND glpi_tickets.time_to_resolve IS NOT NULL
AND glpi_tickets.time_to_resolve < NOW()";

    $late = 0;
    $res = $DB->doQuery($sql_late);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $late = (int) $row['total'];
    }

    $limit = max(1, min(500, $limit));
    $sql = "
SELECT DISTINCT
  glpi_tickets.id,
  glpi_tickets.name,
  glpi_tickets.status,
  glpi_tickets.priority,
  glpi_tickets.date,
  glpi_tickets.time_to_resolve,
  glpi_tickets.users_id_recipient
FROM glpi_tickets
{$scope['group_join']}
{$person['join']}
WHERE glpi_tickets.is_deleted = 0
{$list_status_sql}
{$scope['entity_sql']}
{$scope['group_where']}
{$person['where']}
{$filter_sql}
$order_sql
LIMIT $limit";

    $rows = [];
    $res = $DB->doQuery($sql);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $rows[] = $row;
        }
    }

    return ['total' => $total, 'late' => $late, 'rows' => $rows, 'opts' => $opts];
}

/**
 * @return array<int,string>
 */
function plugin_paineldebordo_entity_options(): array
{
    global $DB;
    $out = [];
    $entities = $_SESSION['glpiactiveentities'] ?? [];
    if (!$entities && class_exists('Profile_User')) {
        $entities = Profile_User::getUserEntitiesForRight(
            $_SESSION['glpiID'],
            Ticket::$rightname,
            Ticket::READALL
        );
    }
    if (!$entities) {
        return $out;
    }
    $in = implode(',', array_map('intval', (array) $entities));
    $res = $DB->doQuery("SELECT id, name FROM glpi_entities WHERE id IN ($in) ORDER BY name");
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $out[(int) $row['id']] = $row['name'];
        }
    }
    return $out;
}

function plugin_paineldebordo_status_label(int $status): string
{
    if (class_exists('Ticket') && method_exists('Ticket', 'getAllStatusArray')) {
        $all = Ticket::getAllStatusArray();
        return $all[$status] ?? (string) $status;
    }
    $map = [
        1 => 'Novo',
        2 => 'Em atendimento (atribuído)',
        3 => 'Em atendimento (planejado)',
        4 => 'Pendente',
        5 => 'Solucionado',
        6 => 'Fechado',
    ];
    return $map[$status] ?? (string) $status;
}

function plugin_paineldebordo_priority_label(int $priority): string
{
    $map = [
        1 => 'Muito baixa',
        2 => 'Baixa',
        3 => 'Média',
        4 => 'Alta',
        5 => 'Muito alta',
        6 => 'Major',
    ];
    return $map[$priority] ?? (string) $priority;
}

/**
 * Open statuses for Modo TV / queues (everything except solved=5 and closed=6).
 *
 * @return int[]
 */
function plugin_paineldebordo_tickets_open_status_ids(): array
{
    return [1, 2, 3, 4];
}

/**
 * Wallboard queues: open tickets by status + validation + solution-approval columns.
 * Same scope as Chamados abertos for open statuses.
 *
 * @param array{entity_sql:string,group_join:string,group_where:string}|null $scope
 * @param array{for_me?:bool,opened_by_me?:bool,by_group?:bool,by_entity?:bool,view_mode?:string} $extras
 * @return array{
 *   open:int,
 *   late:int,
 *   kpis:array{today:int,week:int,month:int,late:int,oldest:?array<string,mixed>,validation_waiting:int,solution_waiting:int},
 *   queues:array<int,array{key:string,status:int,label:string,count:int,tickets:array<int,array<string,mixed>>}>
 * }
 */
function plugin_paineldebordo_tv_queues(?array $scope = null, int $per_queue = 40, array $extras = [], int $users_id = 0): array
{
    global $DB;

    if ($scope === null) {
        $scope = plugin_paineldebordo_tickets_scope();
    }

    if ($users_id <= 0) {
        $users_id = (int) ($_SESSION['glpiID'] ?? 0);
    }

    // Board-wide view mode: filter ALL status columns + open KPIs (extras stay additive).
    $view_mode = (string) ($extras['view_mode'] ?? 'all');
    if (!in_array($view_mode, ['all', 'for_me', 'opened_by_me', 'my_groups'], true)) {
        $view_mode = 'all';
    }
    $view_join = '';
    $view_where = '';
    if ($view_mode === 'for_me' && $users_id > 0) {
        $view_join = " INNER JOIN glpi_tickets_users tu_vm ON tu_vm.tickets_id = glpi_tickets.id AND tu_vm.type = 2 AND tu_vm.users_id = $users_id ";
    } elseif ($view_mode === 'opened_by_me' && $users_id > 0) {
        $view_join = " INNER JOIN glpi_tickets_users tu_vreq ON tu_vreq.tickets_id = glpi_tickets.id AND tu_vreq.type = 1 AND tu_vreq.users_id = $users_id ";
    } elseif ($view_mode === 'my_groups') {
        $gids = plugin_paineldebordo_getUserGroupIds();
        if ($gids === [] && $users_id > 0) {
            $it = $DB->request([
                'SELECT' => ['groups_id'],
                'FROM'   => 'glpi_groups_users',
                'WHERE'  => ['users_id' => $users_id],
            ]);
            foreach ($it as $row) {
                $gids[] = (int) $row['groups_id'];
            }
            $gids = array_values(array_unique(array_filter($gids)));
        }
        if ($gids === []) {
            $view_where = ' AND 0=1 ';
        } else {
            $gin = implode(',', array_map('intval', $gids));
            $view_join = ' INNER JOIN glpi_groups_tickets gt_vm ON gt_vm.tickets_id = glpi_tickets.id AND gt_vm.type = 2 ';
            $view_where = " AND gt_vm.groups_id IN ($gin) ";
        }
    }
    $scope['group_join'] = ($scope['group_join'] ?? '') . $view_join;
    $scope['group_where'] = ($scope['group_where'] ?? '') . $view_where;

    $status_open = plugin_paineldebordo_tickets_open_status_sql();
    $per_queue = max(5, min(80, $per_queue));
    $status_ids = plugin_paineldebordo_tickets_open_status_ids();
    $today = date('Y-m-d');
    $week_start = date('Y-m-d 00:00:00', strtotime('-6 days'));
    $month_start = date('Y-m-01 00:00:00');
    $val_sql = plugin_paineldebordo_tv_validation_waiting_sql('glpi_tickets');
    $sol_sql = plugin_paineldebordo_tv_solution_approved_sql('glpi_tickets');
    $group_sql = plugin_paineldebordo_tv_group_name_sql('glpi_tickets');
    $tech_sql = plugin_paineldebordo_tv_tech_name_sql('glpi_tickets');
    $obs_sql = plugin_paineldebordo_tv_observers_sql('glpi_tickets');
    $requester_sql = plugin_paineldebordo_tv_requester_name_sql('glpi_tickets');
    $category_sql = plugin_paineldebordo_tv_category_name_sql('glpi_tickets');

    $sql_open = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}";
    $open = 0;
    $res = $DB->doQuery($sql_open);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $open = (int) $row['total'];
    }

    $sql_late = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
AND glpi_tickets.time_to_resolve IS NOT NULL
AND glpi_tickets.time_to_resolve < NOW()";
    $late = 0;
    $res = $DB->doQuery($sql_late);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $late = (int) $row['total'];
    }

    $sql_today = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$scope['entity_sql']}
{$scope['group_where']}
AND DATE(glpi_tickets.date) = '" . $DB->escape($today) . "'";
    $today_n = 0;
    $res = $DB->doQuery($sql_today);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $today_n = (int) $row['total'];
    }

    $sql_week = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$scope['entity_sql']}
{$scope['group_where']}
AND glpi_tickets.date >= '" . $DB->escape($week_start) . "'";
    $week_n = 0;
    $res = $DB->doQuery($sql_week);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $week_n = (int) $row['total'];
    }

    $sql_month = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$scope['entity_sql']}
{$scope['group_where']}
AND glpi_tickets.date >= '" . $DB->escape($month_start) . "'";
    $month_n = 0;
    $res = $DB->doQuery($sql_month);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $month_n = (int) $row['total'];
    }

    $validation_waiting = 0;
    $sql_val = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
AND $val_sql
{$scope['entity_sql']}
{$scope['group_where']}";
    $res = $DB->doQuery($sql_val);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $validation_waiting = (int) $row['total'];
    }

    $solution_waiting = 0;
    $sql_sol = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
AND $sol_sql
{$scope['entity_sql']}
{$scope['group_where']}";
    $res = $DB->doQuery($sql_sol);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $solution_waiting = (int) $row['total'];
    }

    $oldest = null;
    $sql_oldest = "
SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.date, glpi_tickets.time_to_resolve,
  $tech_sql AS tech_name
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
ORDER BY glpi_tickets.date ASC
LIMIT 1";
    $res = $DB->doQuery($sql_oldest);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $ttr = $row['time_to_resolve'] ?? null;
        $is_late = $ttr !== null && $ttr !== '' && strtotime((string) $ttr) < time();
        $date = (string) $row['date'];
        $oldest = [
            'id'         => (int) $row['id'],
            'title'      => (string) ($row['name'] ?? ''),
            'date'       => $date,
            'date_label' => plugin_paineldebordo_tv_date_label($date),
            'age'        => plugin_paineldebordo_tv_age_label($date),
            'late'       => $is_late,
            'tech'       => trim((string) ($row['tech_name'] ?? '')),
        ];
    }

    $counts = [];
    $sql_by = "
SELECT glpi_tickets.status AS st, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
GROUP BY glpi_tickets.status";
    $res = $DB->doQuery($sql_by);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $counts[(int) $row['st']] = (int) $row['c'];
        }
    }

    $ticket_select = "
  glpi_tickets.id,
  glpi_tickets.name,
  glpi_tickets.status,
  glpi_tickets.priority,
  glpi_tickets.date,
  glpi_tickets.date_mod,
  glpi_tickets.time_to_resolve,
  glpi_tickets.content,
  $group_sql AS group_name,
  $tech_sql AS tech_name,
  $obs_sql AS observers,
  $requester_sql AS requester_name,
  $category_sql AS category_name";

    // Newest open first (still surfaces late tickets within the LIMIT window)
    $order_sql = "
ORDER BY
  CASE WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW() THEN 0 ELSE 1 END,
  glpi_tickets.date DESC";

    $queues = [];
    foreach ($status_ids as $st) {
        $sql = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
AND glpi_tickets.status = $st
{$scope['entity_sql']}
{$scope['group_where']}
$order_sql
LIMIT $per_queue";
        $tickets = [];
        try {
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $tickets[] = plugin_paineldebordo_tv_ticket_card($row);
                }
            }
        } catch (Throwable $e) {
            $tickets = [];
        }
        $queues[] = [
            'key'     => (string) $st,
            'status'  => $st,
            'label'   => plugin_paineldebordo_tv_status_label($st),
            'count'   => $counts[$st] ?? 0,
            'tickets' => $tickets,
        ];
    }

    // Column: ticket validation waiting (global_validation)
    $sql_val_list = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
AND $val_sql
{$scope['entity_sql']}
{$scope['group_where']}
$order_sql
LIMIT $per_queue";
    $val_tickets = [];
    $res = $DB->doQuery($sql_val_list);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $val_tickets[] = plugin_paineldebordo_tv_ticket_card($row);
        }
    }
    $queues[] = [
        'key'     => 'validation',
        'status'  => 0,
        'label'   => plugin_paineldebordo_tv_validation_label(),
        'count'   => $validation_waiting,
        'tickets' => $val_tickets,
    ];

    // Column: solution approved by requester (ITILSolution ACCEPTED)
    $order_sol_sql = "
ORDER BY
  (SELECT COALESCE(MAX(s_ord.date_approval), MAX(s_ord.date_mod))
   FROM glpi_itilsolutions s_ord
   WHERE s_ord.itemtype = 'Ticket' AND s_ord.items_id = glpi_tickets.id AND s_ord.status = 3) DESC,
  glpi_tickets.date_mod DESC";
    $sql_sol_list = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
AND $sol_sql
{$scope['entity_sql']}
{$scope['group_where']}
$order_sol_sql
LIMIT $per_queue";
    $sol_tickets = [];
    $res = $DB->doQuery($sql_sol_list);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $sol_tickets[] = plugin_paineldebordo_tv_ticket_card($row);
        }
    }
    $queues[] = [
        'key'     => 'solution',
        'status'  => 5,
        'label'   => plugin_paineldebordo_tv_solution_label(),
        'count'   => $solution_waiting,
        'tickets' => $sol_tickets,
    ];

    // Optional extra columns (prefs): for me / by group / by entity — always ACL-scoped
    if ($users_id <= 0) {
        $users_id = (int) ($_SESSION['glpiID'] ?? 0);
    }
    if (!empty($extras['for_me']) && $users_id > 0) {
        $tech = plugin_paineldebordo_tickets_tech_sql($users_id);
        $sql_me_count = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
{$tech['join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}";
        $me_count = 0;
        $res = $DB->doQuery($sql_me_count);
        if ($res && ($row = $DB->fetchAssoc($res))) {
            $me_count = (int) $row['total'];
        }
        $sql_me = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$scope['group_join']}
{$tech['join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
$order_sql
LIMIT $per_queue";
        $me_tickets = [];
        $res = $DB->doQuery($sql_me);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $me_tickets[] = plugin_paineldebordo_tv_ticket_card($row);
            }
        }
        $queues[] = [
            'key'     => 'for_me',
            'status'  => 0,
            'label'   => function_exists('__') ? __('For me', 'paineldebordo') : 'For me',
            'count'   => $me_count,
            'tickets' => $me_tickets,
        ];
    }

    if (!empty($extras['opened_by_me']) && $users_id > 0) {
        $req = plugin_paineldebordo_tickets_requester_sql($users_id);
        $sql_obm_count = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
{$req['join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}";
        $obm_count = 0;
        $res = $DB->doQuery($sql_obm_count);
        if ($res && ($row = $DB->fetchAssoc($res))) {
            $obm_count = (int) $row['total'];
        }
        $sql_obm = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$scope['group_join']}
{$req['join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
$order_sql
LIMIT $per_queue";
        $obm_tickets = [];
        $res = $DB->doQuery($sql_obm);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $obm_tickets[] = plugin_paineldebordo_tv_ticket_card($row);
            }
        }
        $queues[] = [
            'key'     => 'opened_by_me',
            'status'  => 0,
            'label'   => function_exists('__') ? __('Opened by me', 'paineldebordo') : 'Opened by me',
            'count'   => $obm_count,
            'tickets' => $obm_tickets,
        ];
    }

    if (!empty($extras['observer']) && $users_id > 0) {
        $obs_group_ids = function_exists('plugin_paineldebordo_getUserGroupIds')
            ? plugin_paineldebordo_getUserGroupIds()
            : [];
        $obsv = plugin_paineldebordo_tickets_observer_sql($users_id, $obs_group_ids);
        $sql_obsv_count = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$scope['group_join']}
{$obsv['join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
{$obsv['where']}";
        $obsv_count = 0;
        $res = $DB->doQuery($sql_obsv_count);
        if ($res && ($row = $DB->fetchAssoc($res))) {
            $obsv_count = (int) $row['total'];
        }
        $sql_obsv = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$scope['group_join']}
{$obsv['join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$scope['entity_sql']}
{$scope['group_where']}
{$obsv['where']}
$order_sql
LIMIT $per_queue";
        $obsv_tickets = [];
        $res = $DB->doQuery($sql_obsv);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $obsv_tickets[] = plugin_paineldebordo_tv_ticket_card($row);
            }
        }
        $queues[] = [
            'key'     => 'observer',
            'status'  => 0,
            'label'   => function_exists('__') ? __('Observer', 'paineldebordo') : 'Observer',
            'count'   => $obsv_count,
            'tickets' => $obsv_tickets,
        ];
    }

    if (!empty($extras['by_group'])) {
        $groups = plugin_paineldebordo_getGroupOptions();
        $n = 0;
        foreach ($groups as $gid => $gname) {
            $gid = (int) $gid;
            if ($gid <= 0) {
                continue;
            }
            if (++$n > 12) {
                break;
            }
            $gscope = plugin_paineldebordo_tickets_scope($gid, null);
            $sql_gc = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$gscope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$gscope['entity_sql']}
{$gscope['group_where']}";
            $gc = 0;
            $res = $DB->doQuery($sql_gc);
            if ($res && ($row = $DB->fetchAssoc($res))) {
                $gc = (int) $row['total'];
            }
            $sql_g = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$gscope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$gscope['entity_sql']}
{$gscope['group_where']}
$order_sql
LIMIT $per_queue";
            $gtickets = [];
            $res = $DB->doQuery($sql_g);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $gtickets[] = plugin_paineldebordo_tv_ticket_card($row);
                }
            }
            $queues[] = [
                'key'     => 'group_' . $gid,
                'status'  => 0,
                'label'   => (string) $gname,
                'count'   => $gc,
                'tickets' => $gtickets,
            ];
        }
    }

    if (!empty($extras['by_entity'])) {
        $entities = plugin_paineldebordo_entity_options();
        $n = 0;
        foreach ($entities as $eid => $ename) {
            $eid = (int) $eid;
            if (++$n > 12) {
                break;
            }
            $escope = plugin_paineldebordo_tickets_scope(null, $eid);
            $sql_ec = "
SELECT COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
{$escope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$escope['entity_sql']}
{$escope['group_where']}";
            $ec = 0;
            $res = $DB->doQuery($sql_ec);
            if ($res && ($row = $DB->fetchAssoc($res))) {
                $ec = (int) $row['total'];
            }
            $sql_e = "
SELECT DISTINCT
$ticket_select
FROM glpi_tickets
{$escope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$status_open}
{$escope['entity_sql']}
{$escope['group_where']}
$order_sql
LIMIT $per_queue";
            $etickets = [];
            $res = $DB->doQuery($sql_e);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $etickets[] = plugin_paineldebordo_tv_ticket_card($row);
                }
            }
            $queues[] = [
                'key'     => 'entity_' . $eid,
                'status'  => 0,
                'label'   => (string) $ename,
                'count'   => $ec,
                'tickets' => $etickets,
            ];
        }
    }

    return [
        'open'   => $open,
        'late'   => $late,
        'kpis'   => [
            'today'               => $today_n,
            'week'                => $week_n,
            'month'               => $month_n,
            'late'                => $late,
            'oldest'              => $oldest,
            'validation_waiting'  => $validation_waiting,
            'solution_waiting'    => $solution_waiting,
        ],
        'queues' => $queues,
    ];
}
