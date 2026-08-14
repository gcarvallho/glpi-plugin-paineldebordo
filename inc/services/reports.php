<?php

/**
 * Modern reports catalog + runners (legacy parity + new indicators).
 * Scope = Chamados abertos; period = layout filter (today/7d/month/ytd/all).
 */

include_once __DIR__ . '/tickets.php';
include_once dirname(__DIR__) . '/filters.inc.php';

/**
 * @return array{start:string,end:string,sql:string,label:string,period:string}
 */
function plugin_paineldebordo_report_period(?string $column = 'glpi_tickets.date'): array
{
    $period = plugin_paineldebordo_normalize_period(plugin_paineldebordo_getFilters()['period'] ?? 'month');
    $end = date('Y-m-d 23:59:59');
    $col = $column ?: 'glpi_tickets.date';

    if ($period === 'today') {
        $start = date('Y-m-d 00:00:00');
        $label = plugin_paineldebordo_period_label('today');
        $sql = " AND DATE($col) = CURDATE() ";
        return ['start' => $start, 'end' => $end, 'sql' => $sql, 'label' => $label, 'period' => $period];
    }
    if ($period === 'all') {
        $start = '1970-01-01 00:00:00';
        $label = plugin_paineldebordo_period_label('all');
        return ['start' => $start, 'end' => $end, 'sql' => '', 'label' => $label, 'period' => $period];
    }
    if ($period === '7d') {
        $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $label = plugin_paineldebordo_period_label('7d');
    } elseif ($period === 'ytd') {
        $start = date('Y-01-01 00:00:00');
        $label = plugin_paineldebordo_period_label('ytd');
    } else {
        // Calendar month (replaces legacy 30d rolling window)
        $start = date('Y-m-01 00:00:00');
        $label = plugin_paineldebordo_period_label('month');
        $period = 'month';
    }
    $sql = " AND $col >= '" . addslashes($start) . "' AND $col <= '" . addslashes($end) . "' ";
    return ['start' => $start, 'end' => $end, 'sql' => $sql, 'label' => $label, 'period' => $period];
}

/**
 * Map legacy basename (without .php) → modern report id.
 *
 * @return array<string,string>
 */
function plugin_paineldebordo_reports_legacy_map(): array
{
    return [
        'rel_usuario' => 'by_requester',
        'rel_tecnico' => 'by_tech_detail',
        'rel_tecnicos' => 'by_tech_detail',
        'rel_grupo' => 'by_group_detail',
        'rel_grupos' => 'by_group_detail',
        'rel_grupo_tec' => 'group_tech',
        'rel_grupo_req' => 'group_requester',
        'rel_entidade' => 'by_entity_detail',
        'rel_entidades' => 'by_entity_detail',
        'rel_categoria' => 'by_category_tree',
        'rel_categorias' => 'by_category_tree',
        'rel_categoria_sons' => 'by_category_tree',
        'rel_localidade' => 'by_location',
        'rel_localidades' => 'by_location',
        'rel_data' => 'by_date',
        'rel_tickets' => 'open_list',
        'rel_tickets1' => 'tickets_period',
        'rel_sla' => 'sla_by_policy',
        'rel_slas' => 'sla_by_policy',
        'rel_sltsa' => 'sla_tto',
        'rel_sltsas' => 'sla_tto',
        'rel_sltsr' => 'sla_ttr',
        'rel_sltsrs' => 'sla_ttr',
        'rel_oltsa' => 'ola_tto',
        'rel_oltsas' => 'ola_tto',
        'rel_oltsr' => 'ola_ttr',
        'rel_oltsrs' => 'ola_ttr',
        'rel_satisfacao' => 'csat_detail',
        'rel_custo_tec' => 'cost_by_tech',
        'rel_custo_req' => 'cost_by_requester',
        'rel_custo_loc' => 'cost_by_location',
        'rel_custo_ent' => 'cost_by_entity',
        'rel_assets' => 'assets_summary',
        'rel_tarefa' => 'tasks_list',
        'rel_tarefa_cham' => 'tasks_by_ticket',
        'rel_tarefa_cham_group' => 'tasks_by_group',
        'rel_task_req' => 'tasks_by_req',
        'rel_task_ent' => 'tasks_by_ent',
        'rel_projects' => 'projects',
        'rel_projecttasks' => 'project_tasks',
        'rel_sint' => 'synth_overview',
        'rel_sint_all' => 'synth_overview',
        'rel_sint_all_pdf' => 'synth_overview',
        'rel_sint_ent' => 'synth_by_entity',
        'rel_sint_ent_pdf' => 'synth_by_entity',
        'rel_sint_tec' => 'synth_by_tech',
        'rel_sint_tec_pdf' => 'synth_by_tech',
        'rel_sint_req' => 'synth_by_requester',
        'rel_sint_req_pdf' => 'synth_by_requester',
        'rel_sint_grp' => 'synth_by_group',
    ];
}

/**
 * @return array<string, array{title:string,items:array<string,string>}>
 */
function plugin_paineldebordo_reports_catalog(): array
{
    return [
        'tickets' => [
            'title' => __('Tickets'),
            'items' => [
                'open_list'      => __('Open tickets list', 'paineldebordo'),
                'tickets_period' => __('Tickets in period', 'paineldebordo'),
                'by_status'      => __('Count by status', 'paineldebordo'),
                'by_priority'    => __('Count by priority', 'paineldebordo'),
            ],
        ],
        'people' => [
            'title' => __('People / Groups', 'paineldebordo'),
            'items' => [
                'by_requester'    => __('By requester', 'paineldebordo'),
                'by_tech_detail'  => __('By technician (detail)', 'paineldebordo'),
                'by_group_detail' => __('By group (detail)', 'paineldebordo'),
                'group_tech'      => __('Group × technician', 'paineldebordo'),
                'group_requester' => __('Group × requester', 'paineldebordo'),
                'by_tech'         => __('Open by technician', 'paineldebordo'),
                'by_group'        => __('Open by group', 'paineldebordo'),
            ],
        ],
        'structure' => [
            'title' => __('Structure', 'paineldebordo'),
            'items' => [
                'by_entity_detail'  => __('By entity (detail)', 'paineldebordo'),
                'by_category_tree'  => __('By category', 'paineldebordo'),
                'by_location'       => __('By location', 'paineldebordo'),
                'by_date'           => __('By day (opened / solved)', 'paineldebordo'),
                'by_entity'         => __('Open by entity', 'paineldebordo'),
                'by_category'       => __('Open by category', 'paineldebordo'),
            ],
        ],
        'sla' => [
            'title' => __('SLA / OLA', 'paineldebordo'),
            'items' => [
                'sla_late'      => __('Late open tickets', 'paineldebordo'),
                'sla_due'       => __('Due in 24h', 'paineldebordo'),
                'sla_by_policy' => __('By SLA policy', 'paineldebordo'),
                'sla_tto'       => __('SLA time to own', 'paineldebordo'),
                'sla_ttr'       => __('SLA time to resolve', 'paineldebordo'),
                'ola_tto'       => __('OLA time to own', 'paineldebordo'),
                'ola_ttr'       => __('OLA time to resolve', 'paineldebordo'),
                'breach_radar'  => __('Breach radar', 'paineldebordo'),
            ],
        ],
        'quality' => [
            'title' => __('Satisfaction', 'paineldebordo'),
            'items' => [
                'csat_detail' => __('Satisfaction detail', 'paineldebordo'),
                'csat_vs_sla' => __('Satisfaction vs SLA', 'paineldebordo'),
            ],
        ],
        'costs' => [
            'title' => __('Costs', 'paineldebordo'),
            'items' => [
                'cost_by_tech'      => __('Cost by technician', 'paineldebordo'),
                'cost_by_requester' => __('Cost by requester', 'paineldebordo'),
                'cost_by_location'  => __('Cost by location', 'paineldebordo'),
                'cost_by_entity'    => __('Cost by entity', 'paineldebordo'),
            ],
        ],
        'tasks' => [
            'title' => __('Tasks', 'paineldebordo'),
            'items' => [
                'tasks_list'      => __('Tasks list', 'paineldebordo'),
                'tasks_by_ticket' => __('Tasks by ticket', 'paineldebordo'),
                'tasks_by_group'  => __('Tasks by group', 'paineldebordo'),
                'tasks_by_req'    => __('Tasks by requester', 'paineldebordo'),
                'tasks_by_ent'    => __('Tasks by entity', 'paineldebordo'),
            ],
        ],
        'assets' => [
            'title' => __('Assets'),
            'items' => [
                'assets_summary' => __('Assets summary', 'paineldebordo'),
            ],
        ],
        'projects' => [
            'title' => __('Projects', 'paineldebordo'),
            'items' => [
                'projects'      => __('Projects', 'paineldebordo'),
                'project_tasks' => __('Project tasks', 'paineldebordo'),
            ],
        ],
        'synth' => [
            'title' => __('Synthetic', 'paineldebordo'),
            'items' => [
                'synth_overview'      => __('Synthetic overview', 'paineldebordo'),
                'synth_by_entity'     => __('Synthetic by entity', 'paineldebordo'),
                'synth_by_tech'       => __('Synthetic by technician', 'paineldebordo'),
                'synth_by_requester'  => __('Synthetic by requester', 'paineldebordo'),
                'synth_by_group'      => __('Synthetic by group', 'paineldebordo'),
            ],
        ],
        'insights' => [
            'title' => __('Insights', 'paineldebordo'),
            'items' => [
                'aging_buckets'       => __('Aging buckets', 'paineldebordo'),
                'flow_open_vs_solved' => __('Opened vs solved', 'paineldebordo'),
                'first_response_risk' => __('First response risk', 'paineldebordo'),
                'reopen_rate'         => __('Reopen rate', 'paineldebordo'),
                'workload_fairness'   => __('Workload fairness', 'paineldebordo'),
                'hot_categories'      => __('Hot categories', 'paineldebordo'),
            ],
        ],
    ];
}

/**
 * Flatten catalog to id => title.
 *
 * @return array<string,string>
 */
function plugin_paineldebordo_reports_flat(): array
{
    $flat = [];
    foreach (plugin_paineldebordo_reports_catalog() as $section) {
        foreach ($section['items'] as $id => $label) {
            $flat[$id] = $label;
        }
    }
    return $flat;
}

/**
 * @return array{headers:string[],rows:array<int,array<int,string|int|float>>,meta?:array}
 */
function plugin_paineldebordo_report_run(string $report): array
{
    global $DB;

    $scope = plugin_paineldebordo_tickets_scope();
    $status_open = plugin_paineldebordo_tickets_open_status_sql();
    $period = plugin_paineldebordo_report_period();
    $limit = 500;
    $headers = [];
    $rows = [];
    $meta = ['period' => $period['label'], 'truncated' => 0];

    $fetch = static function (string $sql) use ($DB): array {
        $out = [];
        $res = $DB->doQuery($sql);
        if ($res) {
            while ($r = $DB->fetchAssoc($res)) {
                $out[] = $r;
            }
        }
        return $out;
    };

    $agg_dim = static function (string $label_expr, string $joins, string $group_by, bool $open_only = true) use ($DB, $scope, $status_open, $period, $limit): array {
        $st = $open_only ? $status_open : '';
        $psql = $open_only ? '' : $period['sql'];
        $sql = "SELECT $label_expr AS name,
  COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW()
            AND glpi_tickets.status NOT IN (5,6) THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
$joins
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0
{$scope['entity_sql']}
{$scope['group_where']}
$st
$psql
GROUP BY $group_by
ORDER BY total DESC
LIMIT $limit";
        $out = [];
        $res = $DB->doQuery($sql);
        if ($res) {
            while ($r = $DB->fetchAssoc($res)) {
                $total = (int) $r['total'];
                $late = (int) $r['late'];
                $pct = $total > 0 ? round(($late / $total) * 100) : 0;
                $out[] = [
                    (string) ($r['name'] ?: '—'),
                    $total,
                    $late,
                    $pct . '%',
                ];
            }
        }
        return $out;
    };

    switch ($report) {
        case 'open_list':
            $list = plugin_paineldebordo_tickets_open_list(null, null, $limit);
            $headers = ['#', __('Title'), __('Status'), __('Priority'), __('Opened', 'paineldebordo')];
            $status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
            foreach ($list['rows'] as $r) {
                $st = (int) $r['status'];
                $rows[] = [
                    (int) $r['id'],
                    $r['name'],
                    $status_map[$st] ?? plugin_paineldebordo_status_label($st),
                    plugin_paineldebordo_priority_label((int) $r['priority']),
                    substr((string) $r['date'], 0, 16),
                ];
            }
            break;

        case 'tickets_period':
            $headers = ['#', __('Title'), __('Status'), __('Priority'), __('Opened', 'paineldebordo')];
            $status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
            $sql = "SELECT DISTINCT glpi_tickets.id, glpi_tickets.name, glpi_tickets.status, glpi_tickets.priority, glpi_tickets.date
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} {$period['sql']}
ORDER BY glpi_tickets.date DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $st = (int) $r['status'];
                $rows[] = [
                    (int) $r['id'],
                    $r['name'],
                    $status_map[$st] ?? plugin_paineldebordo_status_label($st),
                    plugin_paineldebordo_priority_label((int) $r['priority']),
                    substr((string) $r['date'], 0, 16),
                ];
            }
            break;

        case 'by_status':
        case 'by_priority':
        case 'by_group':
        case 'by_tech':
        case 'by_entity':
        case 'by_category':
            include_once __DIR__ . '/charts.php';
            $map = [
                'by_status' => 'status', 'by_priority' => 'priority', 'by_group' => 'groups',
                'by_tech' => 'tech', 'by_entity' => 'entity', 'by_category' => 'category',
            ];
            $ds = plugin_paineldebordo_chart_dataset($map[$report]);
            $headers = [__('Name'), __('Total')];
            foreach ($ds['categories'] as $i => $name) {
                $rows[] = [$name, $ds['data'][$i] ?? 0];
            }
            break;

        case 'by_requester':
            $headers = [__('Requester'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $rows = $agg_dim(
                "TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,'')))",
                'INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 1
INNER JOIN glpi_users u ON u.id = tu.users_id',
                'u.id'
            );
            break;

        case 'by_tech_detail':
            $headers = [__('Technician'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $rows = $agg_dim(
                "TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,'')))",
                'INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2
INNER JOIN glpi_users u ON u.id = tu.users_id',
                'u.id'
            );
            break;

        case 'by_group_detail':
            $headers = [__('Group'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $extra = plugin_paineldebordo_sqlGroupScope('g.id');
            $sql = "SELECT g.name AS name, COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW() THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2
INNER JOIN glpi_groups g ON g.id = gt.groups_id
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} $extra
GROUP BY g.id ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $total = (int) $r['total'];
                $late = (int) $r['late'];
                $rows[] = [$r['name'], $total, $late, ($total ? round($late / $total * 100) : 0) . '%'];
            }
            break;

        case 'group_tech':
            $headers = [__('Group'), __('Technician'), __('Open', 'paineldebordo')];
            $extra = plugin_paineldebordo_sqlGroupScope('g.id');
            $sql = "SELECT g.name AS gname, TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,''))) AS tname,
  COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2
INNER JOIN glpi_groups g ON g.id = gt.groups_id
INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2
INNER JOIN glpi_users u ON u.id = tu.users_id
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} $extra
GROUP BY g.id, u.id ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [$r['gname'], trim($r['tname']) ?: '—', (int) $r['total']];
            }
            break;

        case 'group_requester':
            $headers = [__('Group'), __('Requester'), __('Open', 'paineldebordo')];
            $extra = plugin_paineldebordo_sqlGroupScope('g.id');
            $sql = "SELECT g.name AS gname, TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,''))) AS rname,
  COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2
INNER JOIN glpi_groups g ON g.id = gt.groups_id
INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 1
INNER JOIN glpi_users u ON u.id = tu.users_id
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} $extra
GROUP BY g.id, u.id ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [$r['gname'], trim($r['rname']) ?: '—', (int) $r['total']];
            }
            break;

        case 'by_entity_detail':
            $headers = [__('Entity'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $rows = $agg_dim('e.name', 'INNER JOIN glpi_entities e ON e.id = glpi_tickets.entities_id', 'e.id');
            break;

        case 'by_category_tree':
            $headers = [__('Category'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $rows = $agg_dim(
                "COALESCE(c.completename, c.name, '—')",
                'LEFT JOIN glpi_itilcategories c ON c.id = glpi_tickets.itilcategories_id',
                'glpi_tickets.itilcategories_id'
            );
            break;

        case 'by_location':
            $headers = [__('Location'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $rows = $agg_dim(
                "COALESCE(l.completename, l.name, '—')",
                'LEFT JOIN glpi_locations l ON l.id = glpi_tickets.locations_id',
                'glpi_tickets.locations_id'
            );
            break;

        case 'by_date':
            $headers = [__('Date'), __('Opened', 'paineldebordo'), __('Solved', 'paineldebordo')];
            $sql = "SELECT DATE(glpi_tickets.date) AS d,
  COUNT(DISTINCT glpi_tickets.id) AS opened,
  SUM(CASE WHEN glpi_tickets.solvedate IS NOT NULL AND DATE(glpi_tickets.solvedate) = DATE(glpi_tickets.date) THEN 1 ELSE 0 END) AS solved_same
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} {$period['sql']}
GROUP BY DATE(glpi_tickets.date) ORDER BY d DESC LIMIT $limit";
            // Better: separate opened by date and solved by solvedate
            $opened = [];
            foreach ($fetch($sql) as $r) {
                $opened[$r['d']] = [(int) $r['opened'], 0];
            }
            $sql2 = "SELECT DATE(glpi_tickets.solvedate) AS d, COUNT(DISTINCT glpi_tickets.id) AS solved
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.solvedate IS NOT NULL
AND glpi_tickets.solvedate >= '{$period['start']}' AND glpi_tickets.solvedate <= '{$period['end']}'
GROUP BY DATE(glpi_tickets.solvedate)";
            foreach ($fetch($sql2) as $r) {
                if (!isset($opened[$r['d']])) {
                    $opened[$r['d']] = [0, 0];
                }
                $opened[$r['d']][1] = (int) $r['solved'];
            }
            krsort($opened);
            foreach ($opened as $d => $vals) {
                $rows[] = [$d, $vals[0], $vals[1]];
            }
            break;

        case 'sla_late':
            $headers = ['#', __('Title'), __('SLA'), __('Due')];
            $sql = "SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.time_to_resolve,
  COALESCE(s.name, '—') AS sla_name
FROM glpi_tickets
LEFT JOIN glpi_slas s ON s.id = glpi_tickets.slas_id_ttr
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW()
ORDER BY glpi_tickets.time_to_resolve ASC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [(int) $r['id'], $r['name'], $r['sla_name'], substr((string) $r['time_to_resolve'], 0, 16)];
            }
            break;

        case 'sla_due':
            $headers = ['#', __('Title'), __('SLA'), __('Due')];
            $sql = "SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.time_to_resolve,
  COALESCE(s.name, '—') AS sla_name
FROM glpi_tickets
LEFT JOIN glpi_slas s ON s.id = glpi_tickets.slas_id_ttr
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.time_to_resolve IS NOT NULL
AND glpi_tickets.time_to_resolve BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 DAY)
ORDER BY glpi_tickets.time_to_resolve ASC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [(int) $r['id'], $r['name'], $r['sla_name'], substr((string) $r['time_to_resolve'], 0, 16)];
            }
            break;

        case 'sla_by_policy':
        case 'sla_ttr':
            $headers = [__('SLA'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $sql = "SELECT COALESCE(s.name, '—') AS name, COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW() THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
LEFT JOIN glpi_slas s ON s.id = glpi_tickets.slas_id_ttr
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.slas_id_ttr IS NOT NULL AND glpi_tickets.slas_id_ttr > 0
GROUP BY glpi_tickets.slas_id_ttr ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $total = (int) $r['total'];
                $late = (int) $r['late'];
                $rows[] = [$r['name'], $total, $late, ($total ? round($late / $total * 100) : 0) . '%'];
            }
            break;

        case 'sla_tto':
            $headers = [__('SLA TTO'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $sql = "SELECT COALESCE(s.name, '—') AS name, COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.time_to_own IS NOT NULL AND glpi_tickets.time_to_own < NOW()
            AND glpi_tickets.takeintoaccount_delay_stat = 0 THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
LEFT JOIN glpi_slas s ON s.id = glpi_tickets.slas_id_tto
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.slas_id_tto IS NOT NULL AND glpi_tickets.slas_id_tto > 0
GROUP BY glpi_tickets.slas_id_tto ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $total = (int) $r['total'];
                $late = (int) $r['late'];
                $rows[] = [$r['name'], $total, $late, ($total ? round($late / $total * 100) : 0) . '%'];
            }
            break;

        case 'ola_ttr':
            $headers = [__('OLA TTR'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $sql = "SELECT COALESCE(o.name, '—') AS name, COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.internal_time_to_resolve IS NOT NULL AND glpi_tickets.internal_time_to_resolve < NOW() THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
LEFT JOIN glpi_olas o ON o.id = glpi_tickets.olas_id_ttr
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.olas_id_ttr IS NOT NULL AND glpi_tickets.olas_id_ttr > 0
GROUP BY glpi_tickets.olas_id_ttr ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $total = (int) $r['total'];
                $late = (int) $r['late'];
                $rows[] = [$r['name'], $total, $late, ($total ? round($late / $total * 100) : 0) . '%'];
            }
            break;

        case 'ola_tto':
            $headers = [__('OLA TTO'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
            $sql = "SELECT COALESCE(o.name, '—') AS name, COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.internal_time_to_own IS NOT NULL AND glpi_tickets.internal_time_to_own < NOW() THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
LEFT JOIN glpi_olas o ON o.id = glpi_tickets.olas_id_tto
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.olas_id_tto IS NOT NULL AND glpi_tickets.olas_id_tto > 0
GROUP BY glpi_tickets.olas_id_tto ORDER BY total DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $total = (int) $r['total'];
                $late = (int) $r['late'];
                $rows[] = [$r['name'], $total, $late, ($total ? round($late / $total * 100) : 0) . '%'];
            }
            break;

        case 'breach_radar':
            $headers = ['#', __('Title'), __('SLA'), __('Due'), __('Hours left', 'paineldebordo')];
            $sql = "SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.time_to_resolve,
  COALESCE(s.name, '—') AS sla_name,
  TIMESTAMPDIFF(HOUR, NOW(), glpi_tickets.time_to_resolve) AS hours_left
FROM glpi_tickets
LEFT JOIN glpi_slas s ON s.id = glpi_tickets.slas_id_ttr
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.time_to_resolve IS NOT NULL
AND glpi_tickets.time_to_resolve >= NOW()
ORDER BY glpi_tickets.time_to_resolve ASC LIMIT 100";
            foreach ($fetch($sql) as $r) {
                $rows[] = [(int) $r['id'], $r['name'], $r['sla_name'], substr((string) $r['time_to_resolve'], 0, 16), (int) $r['hours_left']];
            }
            break;

        case 'csat_detail':
            $headers = [__('Technician'), __('Responses', 'paineldebordo'), __('Avg score', 'paineldebordo')];
            $sql = "SELECT TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,''))) AS name,
  COUNT(sat.id) AS responses, ROUND(AVG(sat.satisfaction), 2) AS avg_score
FROM glpi_ticketsatisfactions sat
INNER JOIN glpi_tickets ON glpi_tickets.id = sat.tickets_id
LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2
LEFT JOIN glpi_users u ON u.id = tu.users_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND sat.date_answered IS NOT NULL
AND sat.date_answered >= '{$period['start']}' AND sat.date_answered <= '{$period['end']}'
GROUP BY u.id ORDER BY responses DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [trim((string) $r['name']) ?: '—', (int) $r['responses'], (float) $r['avg_score']];
            }
            break;

        case 'csat_vs_sla':
            $headers = [__('Bucket', 'paineldebordo'), __('Responses', 'paineldebordo'), __('Avg score', 'paineldebordo')];
            $sql = "SELECT CASE
    WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.solvedate > glpi_tickets.time_to_resolve THEN 'Late'
    ELSE 'On time'
  END AS bucket,
  COUNT(sat.id) AS responses, ROUND(AVG(sat.satisfaction), 2) AS avg_score
FROM glpi_ticketsatisfactions sat
INNER JOIN glpi_tickets ON glpi_tickets.id = sat.tickets_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND sat.date_answered IS NOT NULL
AND sat.date_answered >= '{$period['start']}' AND sat.date_answered <= '{$period['end']}'
GROUP BY bucket";
            foreach ($fetch($sql) as $r) {
                $label = $r['bucket'] === 'Late' ? __('Late', 'paineldebordo') : __('On time', 'paineldebordo');
                $rows[] = [$label, (int) $r['responses'], (float) $r['avg_score']];
            }
            break;

        case 'cost_by_tech':
        case 'cost_by_requester':
        case 'cost_by_location':
        case 'cost_by_entity':
            $headers = [__('Name'), __('Tickets'), __('Cost', 'paineldebordo')];
            if ($report === 'cost_by_tech') {
                $label = "TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,'')))";
                $joins = 'INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2 INNER JOIN glpi_users u ON u.id = tu.users_id';
                $group = 'u.id';
            } elseif ($report === 'cost_by_requester') {
                $label = "TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,'')))";
                $joins = 'INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 1 INNER JOIN glpi_users u ON u.id = tu.users_id';
                $group = 'u.id';
            } elseif ($report === 'cost_by_location') {
                $label = "COALESCE(l.completename, l.name, '—')";
                $joins = 'LEFT JOIN glpi_locations l ON l.id = glpi_tickets.locations_id';
                $group = 'glpi_tickets.locations_id';
            } else {
                $label = 'e.name';
                $joins = 'INNER JOIN glpi_entities e ON e.id = glpi_tickets.entities_id';
                $group = 'e.id';
            }
            $sql = "SELECT $label AS name, COUNT(DISTINCT glpi_tickets.id) AS tickets,
  ROUND(SUM(COALESCE(c.cost_time,0) + COALESCE(c.cost_fixed,0) + COALESCE(c.cost_material,0)), 2) AS cost
FROM glpi_ticketcosts c
INNER JOIN glpi_tickets ON glpi_tickets.id = c.tickets_id
$joins
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} {$period['sql']}
GROUP BY $group ORDER BY cost DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [trim((string) $r['name']) ?: '—', (int) $r['tickets'], (float) $r['cost']];
            }
            break;

        case 'tasks_list':
            $headers = ['#', __('Ticket'), __('Date'), __('Duration', 'paineldebordo'), __('Technician')];
            $sql = "SELECT t.id AS tid, t.name, tk.date, tk.actiontime,
  TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,''))) AS tech
FROM glpi_tickettasks tk
INNER JOIN glpi_tickets t ON t.id = tk.tickets_id
LEFT JOIN glpi_users u ON u.id = COALESCE(tk.users_id_tech, tk.users_id)
" . str_replace('glpi_tickets.', 't.', $scope['group_join']) . '
WHERE t.is_deleted = 0 ' . str_replace('glpi_tickets.', 't.', $scope['entity_sql']) . ' ' . str_replace('glpi_tickets.', 't.', $scope['group_where']) . "
AND tk.date >= '{$period['start']}' AND tk.date <= '{$period['end']}'
ORDER BY tk.date DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $mins = (int) round(((int) $r['actiontime']) / 60);
                $rows[] = [(int) $r['tid'], $r['name'], substr((string) $r['date'], 0, 16), $mins . ' min', trim((string) $r['tech']) ?: '—'];
            }
            break;

        case 'tasks_by_ticket':
            $headers = ['#', __('Ticket'), __('Tasks'), __('Duration', 'paineldebordo')];
            $sql = "SELECT t.id AS tid, t.name, COUNT(tk.id) AS n, SUM(tk.actiontime) AS secs
FROM glpi_tickettasks tk
INNER JOIN glpi_tickets t ON t.id = tk.tickets_id
" . str_replace('glpi_tickets.', 't.', $scope['group_join']) . '
WHERE t.is_deleted = 0 ' . str_replace('glpi_tickets.', 't.', $scope['entity_sql']) . ' ' . str_replace('glpi_tickets.', 't.', $scope['group_where']) . "
AND tk.date >= '{$period['start']}' AND tk.date <= '{$period['end']}'
GROUP BY t.id ORDER BY secs DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [(int) $r['tid'], $r['name'], (int) $r['n'], ((int) round($r['secs'] / 60)) . ' min'];
            }
            break;

        case 'tasks_by_group':
            $headers = [__('Group'), __('Tasks'), __('Duration', 'paineldebordo')];
            $extra = plugin_paineldebordo_sqlGroupScope('g.id');
            $sql = "SELECT g.name, COUNT(tk.id) AS n, SUM(tk.actiontime) AS secs
FROM glpi_tickettasks tk
INNER JOIN glpi_tickets t ON t.id = tk.tickets_id
INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = t.id AND gt.type = 2
INNER JOIN glpi_groups g ON g.id = gt.groups_id
WHERE t.is_deleted = 0 " . str_replace('glpi_tickets.', 't.', $scope['entity_sql']) . " $extra
AND tk.date >= '{$period['start']}' AND tk.date <= '{$period['end']}'
GROUP BY g.id ORDER BY secs DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [$r['name'], (int) $r['n'], ((int) round($r['secs'] / 60)) . ' min'];
            }
            break;

        case 'tasks_by_req':
            $headers = [__('Requester'), __('Tasks'), __('Duration', 'paineldebordo')];
            $sql = "SELECT TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,''))) AS name,
  COUNT(tk.id) AS n, SUM(tk.actiontime) AS secs
FROM glpi_tickettasks tk
INNER JOIN glpi_tickets t ON t.id = tk.tickets_id
INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 1
INNER JOIN glpi_users u ON u.id = tu.users_id
" . str_replace('glpi_tickets.', 't.', $scope['group_join']) . '
WHERE t.is_deleted = 0 ' . str_replace('glpi_tickets.', 't.', $scope['entity_sql']) . ' ' . str_replace('glpi_tickets.', 't.', $scope['group_where']) . "
AND tk.date >= '{$period['start']}' AND tk.date <= '{$period['end']}'
GROUP BY u.id ORDER BY secs DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [trim((string) $r['name']) ?: '—', (int) $r['n'], ((int) round($r['secs'] / 60)) . ' min'];
            }
            break;

        case 'tasks_by_ent':
            $headers = [__('Entity'), __('Tasks'), __('Duration', 'paineldebordo')];
            $sql = "SELECT e.name, COUNT(tk.id) AS n, SUM(tk.actiontime) AS secs
FROM glpi_tickettasks tk
INNER JOIN glpi_tickets t ON t.id = tk.tickets_id
INNER JOIN glpi_entities e ON e.id = t.entities_id
" . str_replace('glpi_tickets.', 't.', $scope['group_join']) . '
WHERE t.is_deleted = 0 ' . str_replace('glpi_tickets.', 't.', $scope['entity_sql']) . ' ' . str_replace('glpi_tickets.', 't.', $scope['group_where']) . "
AND tk.date >= '{$period['start']}' AND tk.date <= '{$period['end']}'
GROUP BY e.id ORDER BY secs DESC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $rows[] = [$r['name'], (int) $r['n'], ((int) round($r['secs'] / 60)) . ' min'];
            }
            break;

        case 'assets_summary':
            $headers = [__('Type'), __('Items', 'paineldebordo'), __('With tickets', 'paineldebordo')];
            $types = [
                'Computer' => 'glpi_computers',
                'Monitor' => 'glpi_monitors',
                'NetworkEquipment' => 'glpi_networkequipments',
                'Printer' => 'glpi_printers',
                'Phone' => 'glpi_phones',
                'Peripheral' => 'glpi_peripherals',
            ];
            foreach ($types as $itemtype => $table) {
                if (!$DB->TableExists($table)) {
                    continue;
                }
                $total = 0;
                $with = 0;
                $r1 = $DB->doQuery("SELECT COUNT(*) AS c FROM `$table` WHERE is_deleted = 0");
                if ($r1 && ($row = $DB->fetchAssoc($r1))) {
                    $total = (int) $row['c'];
                }
                $r2 = $DB->doQuery(
                    "SELECT COUNT(DISTINCT gi.id) AS c FROM `$table` gi
INNER JOIN glpi_items_tickets it ON it.items_id = gi.id AND it.itemtype = '" . $DB->escape($itemtype) . "'
INNER JOIN glpi_tickets ON glpi_tickets.id = it.tickets_id
WHERE gi.is_deleted = 0 AND glpi_tickets.is_deleted = 0 {$scope['entity_sql']}"
                );
                if ($r2 && ($row = $DB->fetchAssoc($r2))) {
                    $with = (int) $row['c'];
                }
                $rows[] = [$itemtype, $total, $with];
            }
            break;

        case 'projects':
            $headers = ['#', __('Name'), __('Status'), __('%'), __('Entity')];
            if ($DB->TableExists('glpi_projects')) {
                $sql = "SELECT p.id, p.name, p.percent_done, e.name AS ename, COALESCE(ps.name, '—') AS st
FROM glpi_projects p
LEFT JOIN glpi_entities e ON e.id = p.entities_id
LEFT JOIN glpi_projectstates ps ON ps.id = p.projectstates_id
WHERE p.is_deleted = 0
ORDER BY p.id DESC LIMIT $limit";
                foreach ($fetch($sql) as $r) {
                    $rows[] = [(int) $r['id'], $r['name'], $r['st'], (int) $r['percent_done'] . '%', $r['ename'] ?: '—'];
                }
            }
            break;

        case 'project_tasks':
            $headers = ['#', __('Project'), __('Task'), __('Status'), __('%')];
            if ($DB->TableExists('glpi_projecttasks')) {
                $sql = "SELECT pt.id, p.name AS pname, pt.name, pt.percent_done, COALESCE(ps.name, '—') AS st
FROM glpi_projecttasks pt
INNER JOIN glpi_projects p ON p.id = pt.projects_id
LEFT JOIN glpi_projectstates ps ON ps.id = pt.projectstates_id
WHERE p.is_deleted = 0
ORDER BY pt.id DESC LIMIT $limit";
                foreach ($fetch($sql) as $r) {
                    $rows[] = [(int) $r['id'], $r['pname'], $r['name'], $r['st'], (int) $r['percent_done'] . '%'];
                }
            }
            break;

        case 'synth_overview':
        case 'synth_by_entity':
        case 'synth_by_tech':
        case 'synth_by_requester':
        case 'synth_by_group':
            $headers = [__('Metric', 'paineldebordo'), __('Value')];
            $list = plugin_paineldebordo_tickets_open_list(null, null, 1);
            $rows[] = [__('Open', 'paineldebordo'), $list['total']];
            $rows[] = [__('Late', 'paineldebordo'), $list['late']];
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} {$period['sql']}";
            $opened = 0;
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $opened = (int) $row['c'];
            }
            $rows[] = [__('Opened in period', 'paineldebordo'), $opened];
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.solvedate >= '{$period['start']}' AND glpi_tickets.solvedate <= '{$period['end']}'";
            $solved = 0;
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $solved = (int) $row['c'];
            }
            $rows[] = [__('Solved in period', 'paineldebordo'), $solved];
            $sql = "SELECT ROUND(AVG(sat.satisfaction), 2) AS avg_score, COUNT(sat.id) AS n
FROM glpi_ticketsatisfactions sat
INNER JOIN glpi_tickets ON glpi_tickets.id = sat.tickets_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND sat.date_answered IS NOT NULL
AND sat.date_answered >= '{$period['start']}' AND sat.date_answered <= '{$period['end']}'";
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $rows[] = [__('Avg satisfaction', 'paineldebordo'), (float) ($row['avg_score'] ?? 0)];
                $rows[] = [__('Satisfaction responses', 'paineldebordo'), (int) ($row['n'] ?? 0)];
            }
            if ($report === 'synth_by_entity') {
                $extra = $agg_dim('e.name', 'INNER JOIN glpi_entities e ON e.id = glpi_tickets.entities_id', 'e.id');
                $headers = [__('Entity'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
                $rows = array_slice($extra, 0, 50);
            } elseif ($report === 'synth_by_tech') {
                $headers = [__('Technician'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
                $rows = array_slice($agg_dim(
                    "TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,'')))",
                    'INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2 INNER JOIN glpi_users u ON u.id = tu.users_id',
                    'u.id'
                ), 0, 50);
            } elseif ($report === 'synth_by_requester') {
                $headers = [__('Requester'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
                $rows = array_slice($agg_dim(
                    "TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,'')))",
                    'INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 1 INNER JOIN glpi_users u ON u.id = tu.users_id',
                    'u.id'
                ), 0, 50);
            } elseif ($report === 'synth_by_group') {
                $headers = [__('Group'), __('Open', 'paineldebordo'), __('Late', 'paineldebordo'), __('Late %', 'paineldebordo')];
                $extra = plugin_paineldebordo_sqlGroupScope('g.id');
                $sql = "SELECT g.name AS name, COUNT(DISTINCT glpi_tickets.id) AS total,
  SUM(CASE WHEN glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW() THEN 1 ELSE 0 END) AS late
FROM glpi_tickets
INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2
INNER JOIN glpi_groups g ON g.id = gt.groups_id
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} $extra
GROUP BY g.id ORDER BY total DESC LIMIT 50";
                $rows = [];
                foreach ($fetch($sql) as $r) {
                    $total = (int) $r['total'];
                    $late = (int) $r['late'];
                    $rows[] = [$r['name'], $total, $late, ($total ? round($late / $total * 100) : 0) . '%'];
                }
            }
            break;

        case 'aging_buckets':
            $headers = [__('Bucket', 'paineldebordo'), __('Tickets')];
            $sql = "SELECT CASE
    WHEN TIMESTAMPDIFF(DAY, glpi_tickets.date, NOW()) <= 1 THEN '0-1d'
    WHEN TIMESTAMPDIFF(DAY, glpi_tickets.date, NOW()) <= 7 THEN '2-7d'
    WHEN TIMESTAMPDIFF(DAY, glpi_tickets.date, NOW()) <= 30 THEN '8-30d'
    ELSE '30d+'
  END AS bucket, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY bucket
ORDER BY FIELD(bucket,'0-1d','2-7d','8-30d','30d+')";
            foreach ($fetch($sql) as $r) {
                $rows[] = [$r['bucket'], (int) $r['c']];
            }
            break;

        case 'flow_open_vs_solved':
            $headers = [__('Metric', 'paineldebordo'), __('Value')];
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} {$period['sql']}";
            $opened = 0;
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $opened = (int) $row['c'];
            }
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.solvedate >= '{$period['start']}' AND glpi_tickets.solvedate <= '{$period['end']}'";
            $solved = 0;
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $solved = (int) $row['c'];
            }
            $rows[] = [__('Opened in period', 'paineldebordo'), $opened];
            $rows[] = [__('Solved in period', 'paineldebordo'), $solved];
            $rows[] = [__('Balance', 'paineldebordo'), $opened - $solved];
            break;

        case 'first_response_risk':
            $headers = ['#', __('Title'), __('TTO due', 'paineldebordo'), __('State', 'paineldebordo')];
            $sql = "SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.time_to_own,
  CASE WHEN glpi_tickets.time_to_own < NOW() THEN 'late' ELSE 'due_4h' END AS st
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.time_to_own IS NOT NULL
AND (glpi_tickets.takeintoaccount_delay_stat = 0 OR glpi_tickets.takeintoaccount_delay_stat IS NULL)
AND (glpi_tickets.time_to_own < NOW() OR glpi_tickets.time_to_own <= DATE_ADD(NOW(), INTERVAL 4 HOUR))
ORDER BY glpi_tickets.time_to_own ASC LIMIT $limit";
            foreach ($fetch($sql) as $r) {
                $st = $r['st'] === 'late' ? __('Late', 'paineldebordo') : __('Due in 4h', 'paineldebordo');
                $rows[] = [(int) $r['id'], $r['name'], substr((string) $r['time_to_own'], 0, 16), $st];
            }
            break;

        case 'reopen_rate':
            $headers = [__('Metric', 'paineldebordo'), __('Value')];
            $sql = "SELECT COUNT(*) AS c FROM glpi_logs l
INNER JOIN glpi_tickets ON glpi_tickets.id = l.items_id AND l.itemtype = 'Ticket'
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND l.id_search_option = 12
AND l.old_value IN ('5','6') AND l.new_value NOT IN ('5','6')
AND l.date_mod >= '{$period['start']}' AND l.date_mod <= '{$period['end']}'";
            $reopens = 0;
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $reopens = (int) $row['c'];
            }
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.solvedate >= '{$period['start']}' AND glpi_tickets.solvedate <= '{$period['end']}'";
            $solved = 0;
            $r = $DB->doQuery($sql);
            if ($r && ($row = $DB->fetchAssoc($r))) {
                $solved = (int) $row['c'];
            }
            $rows[] = [__('Reopens', 'paineldebordo'), $reopens];
            $rows[] = [__('Solved in period', 'paineldebordo'), $solved];
            $rows[] = [__('Reopen rate', 'paineldebordo'), ($solved > 0 ? round($reopens / $solved * 100, 1) : 0) . '%'];
            break;

        case 'workload_fairness':
            $headers = [__('Technician'), __('Open', 'paineldebordo'), __('vs avg', 'paineldebordo')];
            $sql = "SELECT TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,u.name,''))) AS name,
  COUNT(DISTINCT glpi_tickets.id) AS total
FROM glpi_tickets
INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2
INNER JOIN glpi_users u ON u.id = tu.users_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY u.id ORDER BY total DESC LIMIT $limit";
            $tmp = $fetch($sql);
            $avg = 0;
            if ($tmp) {
                $avg = array_sum(array_map(static fn ($r) => (int) $r['total'], $tmp)) / count($tmp);
            }
            foreach ($tmp as $r) {
                $delta = (int) $r['total'] - $avg;
                $rows[] = [trim((string) $r['name']) ?: '—', (int) $r['total'], ($delta >= 0 ? '+' : '') . round($delta, 1)];
            }
            break;

        case 'hot_categories':
            $headers = [__('Category'), __('This month', 'paineldebordo'), __('Prev month', 'paineldebordo'), __('Growth', 'paineldebordo')];
            $m0 = date('Y-m-01');
            $m1 = date('Y-m-01', strtotime('first day of last month'));
            $m1e = date('Y-m-t 23:59:59', strtotime('last month'));
            $sql = "SELECT COALESCE(c.completename, c.name, '—') AS name,
  SUM(CASE WHEN glpi_tickets.date >= '$m0' THEN 1 ELSE 0 END) AS cur,
  SUM(CASE WHEN glpi_tickets.date >= '$m1' AND glpi_tickets.date <= '$m1e' THEN 1 ELSE 0 END) AS prev
FROM glpi_tickets
LEFT JOIN glpi_itilcategories c ON c.id = glpi_tickets.itilcategories_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.date >= '$m1'
GROUP BY glpi_tickets.itilcategories_id
HAVING cur > 0 OR prev > 0
ORDER BY (cur - prev) DESC LIMIT 30";
            foreach ($fetch($sql) as $r) {
                $cur = (int) $r['cur'];
                $prev = (int) $r['prev'];
                $growth = $prev > 0 ? round(($cur - $prev) / $prev * 100) . '%' : ($cur > 0 ? 'new' : '0%');
                $rows[] = [$r['name'], $cur, $prev, $growth];
            }
            break;

        default:
            $headers = [__('Info')];
            $rows[] = [__('Unknown report', 'paineldebordo')];
    }

    $meta['truncated'] = count($rows);
    return ['headers' => $headers, 'rows' => $rows, 'meta' => $meta];
}
