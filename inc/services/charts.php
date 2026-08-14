<?php

/**
 * Charts catalog + datasets for modern UI (Highcharts).
 */

include_once __DIR__ . '/tickets.php';
include_once __DIR__ . '/reports.php';

/**
 * @return array<string, array{title:string,desc:string}>
 */
function plugin_paineldebordo_charts_catalog(): array
{
    return [
        'status'     => ['title' => __('Open by status', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'priority'   => ['title' => __('Open by priority', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'evolution'  => ['title' => __('Evolution', 'paineldebordo'), 'desc' => __('Opened in selected period', 'paineldebordo')],
        'flow'       => ['title' => __('Opened vs solved', 'paineldebordo'), 'desc' => __('Flow in selected period', 'paineldebordo')],
        'aging'      => ['title' => __('Aging buckets', 'paineldebordo'), 'desc' => __('Open backlog age', 'paineldebordo')],
        'breach'     => ['title' => __('Breach radar', 'paineldebordo'), 'desc' => __('Soonest SLA due (open)', 'paineldebordo')],
        'groups'     => ['title' => __('By group', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'tech'       => ['title' => __('By technician', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'category'   => ['title' => __('By category', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'entity'     => ['title' => __('By entity', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'sla'        => ['title' => __('SLA late', 'paineldebordo'), 'desc' => __('Late vs on-time open', 'paineldebordo')],
        'type'       => ['title' => __('By type', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'source'     => ['title' => __('By source', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'location'   => ['title' => __('By location', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
        'requester'  => ['title' => __('By requester', 'paineldebordo'), 'desc' => __('Open now', 'paineldebordo')],
    ];
}

/**
 * Resolve legacy graph filenames / aliases to catalog keys.
 */
function plugin_paineldebordo_chart_alias(string $chart): string
{
    $chart = strtolower(trim($chart));
    $map = [
        'tecnicos' => 'tech', 'tecnico' => 'tech', 'graf_tecnico' => 'tech', 'graf_tech' => 'tech',
        'grupos' => 'groups', 'grupo' => 'groups', 'graf_grupo' => 'groups',
        'entidades' => 'entity', 'entidade' => 'entity', 'graf_entidade' => 'entity',
        'categorias' => 'category', 'categoria' => 'category', 'graf_categoria' => 'category',
        'prioridade' => 'priority', 'prio' => 'priority',
        'status' => 'status', 'geral' => 'status',
        'satisfacao' => 'sla', 'sla' => 'sla', 'slas' => 'sla',
        'evolucao' => 'evolution', 'times' => 'evolution',
        'usuarios' => 'requester', 'usuario' => 'requester', 'graf_usuario' => 'requester',
        'local' => 'location', 'localidade' => 'location', 'graf_localidade' => 'location',
        'tipo' => 'type', 'graf_tipo' => 'type',
        'origem' => 'source',
        'flow' => 'flow', 'aging' => 'aging', 'breach' => 'breach',
        'aging_buckets' => 'aging', 'flow_open_vs_solved' => 'flow', 'breach_radar' => 'breach',
    ];
    return $map[$chart] ?? $chart;
}

/**
 * Highcharts chart type for a catalog id.
 */
function plugin_paineldebordo_chart_hc_type(string $chart): string
{
    $chart = plugin_paineldebordo_chart_alias($chart);
    if (in_array($chart, ['evolution', 'flow'], true)) {
        return $chart === 'flow' ? 'column' : 'areaspline';
    }
    if (in_array($chart, ['sla', 'type', 'status'], true)) {
        return 'pie';
    }
    return 'bar';
}

/**
 * URL to an asset under the plugin public web root (Highcharts, CSS, img).
 * Always absolute when layout helpers exist; never bare-relative alone.
 */
function plugin_paineldebordo_public_url(string $rel = ''): string
{
    $rel = ltrim($rel, '/');
    if (function_exists('plugin_paineldebordo_asset_url')) {
        return $rel === '' ? plugin_paineldebordo_asset_base() : plugin_paineldebordo_asset_url($rel);
    }

    global $CFG_GLPI;
    $base = $GLOBALS['HO_PLUGIN_WEB'] ?? null;
    if (!$base && class_exists('Plugin') && method_exists('Plugin', 'getWebDir')) {
        $base = (string) Plugin::getWebDir('paineldebordo', false);
    }
    if (!$base) {
        $base = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/paineldebordo/public';
    }
    $base = rtrim((string) $base, '/');
    return $rel === '' ? $base : ($base . '/' . $rel);
}

/**
 * @return array{categories:string[],data:int[],colors:string[],series?:array<int,array{name:string,data:int[]}>}
 */
function plugin_paineldebordo_chart_dataset(string $chart): array
{
    global $DB;

    $chart = plugin_paineldebordo_chart_alias($chart);
    $scope = plugin_paineldebordo_tickets_scope();
    $status_open = plugin_paineldebordo_tickets_open_status_sql();
    $period = plugin_paineldebordo_report_period('glpi_tickets.date');
    $colors = ['#09141F', '#E73E11', '#09141f99', '#E73E1199', '#626976', '#e73f1140', '#2a2a2a', '#ff8a65'];

    $categories = [];
    $data = [];
    $series = null;

    switch ($chart) {
        case 'priority':
            $sql = "SELECT glpi_tickets.priority AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY glpi_tickets.priority ORDER BY glpi_tickets.priority";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = plugin_paineldebordo_priority_label((int) $row['k']);
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'evolution': {
            $startTs = strtotime($period['start']);
            $endTs = strtotime($period['end']);
            $days = (int) max(1, (int) ceil(($endTs - $startTs) / 86400) + 1);
            // Cap daily points; for ytd use weekly buckets
            if ($days > 45) {
                $cursor = strtotime(date('Y-m-d', $startTs) . ' Monday this week');
                if ($cursor > $startTs) {
                    $cursor = strtotime('-7 days', $cursor);
                }
                while ($cursor <= $endTs) {
                    $wStart = date('Y-m-d 00:00:00', $cursor);
                    $wEnd = date('Y-m-d 23:59:59', strtotime('+6 days', $cursor));
                    if ($wEnd > $period['end']) {
                        $wEnd = $period['end'];
                    }
                    $categories[] = date('d/m', $cursor);
                    $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.date >= '" . $DB->escape($wStart) . "' AND glpi_tickets.date <= '" . $DB->escape($wEnd) . "'";
                    $c = 0;
                    $res = $DB->doQuery($sql);
                    if ($res && ($row = $DB->fetchAssoc($res))) {
                        $c = (int) $row['c'];
                    }
                    $data[] = $c;
                    $cursor = strtotime('+7 days', $cursor);
                }
            } else {
                for ($t = $startTs; $t <= $endTs; $t += 86400) {
                    $d = date('Y-m-d', $t);
                    $categories[] = date('d/m', $t);
                    $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND DATE(glpi_tickets.date) = '" . $DB->escape($d) . "'";
                    $c = 0;
                    $res = $DB->doQuery($sql);
                    if ($res && ($row = $DB->fetchAssoc($res))) {
                        $c = (int) $row['c'];
                    }
                    $data[] = $c;
                }
            }
            break;
        }

        case 'flow': {
            $opened = 0;
            $solved = 0;
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} {$period['sql']}";
            $res = $DB->doQuery($sql);
            if ($res && ($row = $DB->fetchAssoc($res))) {
                $opened = (int) $row['c'];
            }
            $sql = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.solvedate >= '{$period['start']}' AND glpi_tickets.solvedate <= '{$period['end']}'";
            $res = $DB->doQuery($sql);
            if ($res && ($row = $DB->fetchAssoc($res))) {
                $solved = (int) $row['c'];
            }
            $categories = [__('Opened in period', 'paineldebordo'), __('Solved in period', 'paineldebordo')];
            $data = [$opened, $solved];
            break;
        }

        case 'aging': {
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
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = (string) $row['bucket'];
                    $data[] = (int) $row['c'];
                }
            }
            break;
        }

        case 'breach': {
            $sql = "SELECT glpi_tickets.id, glpi_tickets.name,
  TIMESTAMPDIFF(HOUR, NOW(), glpi_tickets.time_to_resolve) AS hours_left
FROM glpi_tickets
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.time_to_resolve IS NOT NULL
AND glpi_tickets.time_to_resolve >= NOW()
ORDER BY glpi_tickets.time_to_resolve ASC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = '#' . (int) $row['id'];
                    $data[] = max(0, (int) $row['hours_left']);
                }
            }
            break;
        }

        case 'groups':
            $extra = plugin_paineldebordo_sqlGroupScope('gt.groups_id');
            $sql = "SELECT g.name AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2
INNER JOIN glpi_groups g ON g.id = gt.groups_id
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} $extra
GROUP BY g.id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = $row['k'];
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'tech':
            $sql = "SELECT CONCAT(u.firstname,' ',u.realname) AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
INNER JOIN glpi_tickets_users tu ON tu.tickets_id = glpi_tickets.id AND tu.type = 2
INNER JOIN glpi_users u ON u.id = tu.users_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY u.id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = trim($row['k']) ?: ('#' . count($categories));
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'category':
            $sql = "SELECT COALESCE(c.completename, c.name, '—') AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
LEFT JOIN glpi_itilcategories c ON c.id = glpi_tickets.itilcategories_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY glpi_tickets.itilcategories_id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = $row['k'];
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'entity':
            $sql = "SELECT e.name AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
INNER JOIN glpi_entities e ON e.id = glpi_tickets.entities_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY e.id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = $row['k'];
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'sla':
            $list = plugin_paineldebordo_tickets_open_list();
            $late = $list['late'];
            $ok = max(0, $list['total'] - $late);
            $categories = [__('On time', 'paineldebordo'), __('Late', 'paineldebordo')];
            $data = [$ok, $late];
            break;

        case 'type':
            $sql = "SELECT glpi_tickets.type AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY glpi_tickets.type ORDER BY c DESC";
            $res = $DB->doQuery($sql);
            $type_map = [
                1 => __('Incident'),
                2 => __('Request'),
            ];
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $t = (int) $row['k'];
                    $categories[] = $type_map[$t] ?? ('#' . $t);
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'source':
            $sql = "SELECT COALESCE(rs.name, '—') AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
LEFT JOIN glpi_requesttypes rs ON rs.id = glpi_tickets.requesttypes_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY glpi_tickets.requesttypes_id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = $row['k'];
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'location':
            $sql = "SELECT COALESCE(l.completename, l.name, '—') AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
LEFT JOIN glpi_locations l ON l.id = glpi_tickets.locations_id
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY glpi_tickets.locations_id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = $row['k'];
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'requester':
            $sql = "SELECT CONCAT(u.firstname,' ',u.realname) AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets
INNER JOIN glpi_users u ON u.id = glpi_tickets.users_id_recipient
{$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY u.id ORDER BY c DESC LIMIT 12";
            $res = $DB->doQuery($sql);
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $categories[] = trim($row['k']) ?: ('#' . count($categories));
                    $data[] = (int) $row['c'];
                }
            }
            break;

        case 'status':
        default:
            $sql = "SELECT glpi_tickets.status AS k, COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
GROUP BY glpi_tickets.status ORDER BY c DESC";
            $res = $DB->doQuery($sql);
            $status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
            if ($res) {
                while ($row = $DB->fetchAssoc($res)) {
                    $st = (int) $row['k'];
                    $categories[] = $status_map[$st] ?? plugin_paineldebordo_status_label($st);
                    $data[] = (int) $row['c'];
                }
            }
            break;
    }

    $out = ['categories' => $categories, 'data' => $data, 'colors' => $colors];
    if ($series !== null) {
        $out['series'] = $series;
    }
    return $out;
}
