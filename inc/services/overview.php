<?php

/**
 * Overview NOC board — KPIs + charts payload (period/group/entity aware).
 */

include_once __DIR__ . '/tickets.php';
include_once __DIR__ . '/charts.php';
include_once __DIR__ . '/reports.php';
include_once __DIR__ . '/overview_layout.php';
include_once dirname(__DIR__) . '/tv.inc.php';

/**
 * Build Overview mural payload.
 *
 * @param bool $apply_layout When false, return full KPIs/charts (used by BI studio).
 * @return array<string,mixed>
 */
function plugin_paineldebordo_overview_board(bool $apply_layout = true): array
{
    global $DB;

    $scope = plugin_paineldebordo_tickets_scope();
    $status_open = plugin_paineldebordo_tickets_open_status_sql();
    $period = plugin_paineldebordo_report_period('glpi_tickets.date');
    $filters = plugin_paineldebordo_getFilters();
    $theme = $filters['theme'];

    $base = "FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}";

    $count = static function (string $sql) use ($DB): int {
        $r = $DB->doQuery($sql);
        if (!$r) {
            return 0;
        }
        $row = $DB->fetchAssoc($r);
        return (int) ($row['total'] ?? $row['c'] ?? 0);
    };

    $opened_period = $count("SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base {$period['sql']}");
    $solved_period = $count(
        "SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base
         AND glpi_tickets.solvedate >= '{$period['start']}' AND glpi_tickets.solvedate <= '{$period['end']}'"
    );
    $requesters_period = $count(
        "SELECT COUNT(DISTINCT glpi_tickets.users_id_recipient) AS total $base {$period['sql']}"
    );

    $open = $count("SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base $status_open");
    $late = $count(
        "SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base $status_open
         AND glpi_tickets.time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW()"
    );
    $due_24h = $count(
        "SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base $status_open
         AND glpi_tickets.time_to_resolve IS NOT NULL
         AND glpi_tickets.time_to_resolve BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 DAY)"
    );
    $ontime = $open > 0 ? (int) round((($open - $late) / $open) * 100) : 100;

    $val_sql = plugin_paineldebordo_tv_validation_waiting_sql('glpi_tickets');
    $sol_sql = plugin_paineldebordo_tv_solution_approved_sql('glpi_tickets');
    $validation = $count("SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base AND $val_sql");
    $solution = $count("SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base AND $sol_sql");

    $tickets_href = 'shell.php?' . http_build_query([
        'page' => 'tickets', 'theme' => $theme, 'period' => $period['period'],
    ]);
    $tv_href = 'tv.php?theme=' . rawurlencode($theme);
    $now_label = __('Open now', 'paineldebordo');
    $period_meta = $period['label'];

    $kpis = [
        [
            'key' => 'opened_period', 'label' => __('Opened in period', 'paineldebordo'),
            'value' => $opened_period, 'meta' => $period_meta, 'mod' => 'accent',
            'href' => $tickets_href, 'kind' => 'flow',
        ],
        [
            'key' => 'solved_period', 'label' => __('Solved in period', 'paineldebordo'),
            'value' => $solved_period, 'meta' => $period_meta, 'mod' => 'primary',
            'href' => $tickets_href, 'kind' => 'flow',
        ],
        [
            'key' => 'balance', 'label' => __('Balance', 'paineldebordo'),
            'value' => $opened_period - $solved_period, 'meta' => $period_meta, 'mod' => 'info',
            'href' => $tickets_href, 'kind' => 'flow',
        ],
        [
            'key' => 'requesters', 'label' => __('Requesters', 'paineldebordo'),
            'value' => $requesters_period, 'meta' => $period_meta, 'mod' => 'info',
            'href' => 'shell.php?' . http_build_query(['page' => 'chart', 'chart' => 'requester', 'theme' => $theme]),
            'kind' => 'flow',
        ],
        [
            'key' => 'open', 'label' => __('Backlog', 'paineldebordo'),
            'value' => $open, 'meta' => $now_label, 'mod' => 'accent',
            'href' => $tickets_href, 'kind' => 'snapshot',
        ],
        [
            'key' => 'late', 'label' => __('Late', 'paineldebordo'),
            'value' => $late, 'meta' => 'SLA · ' . $now_label, 'mod' => 'danger',
            'href' => $tickets_href, 'kind' => 'snapshot',
        ],
        [
            'key' => 'due_24h', 'label' => __('Due in 24h', 'paineldebordo'),
            'value' => $due_24h, 'meta' => $now_label, 'mod' => 'danger',
            'href' => $tickets_href, 'kind' => 'snapshot',
        ],
        [
            'key' => 'ontime', 'label' => __('On time %', 'paineldebordo'),
            'value' => $ontime . '%', 'meta' => $now_label, 'mod' => 'accent',
            'href' => $tickets_href, 'kind' => 'snapshot',
        ],
        [
            'key' => 'validation', 'label' => __('Validation', 'paineldebordo'),
            'value' => $validation, 'meta' => $now_label, 'mod' => 'accent',
            'href' => $tv_href, 'kind' => 'snapshot',
        ],
        [
            'key' => 'solution', 'label' => __('Solution approved', 'paineldebordo'),
            'value' => $solution, 'meta' => $now_label, 'mod' => 'primary',
            'href' => $tv_href, 'kind' => 'snapshot',
        ],
    ];

    // Oldest open ticket
    $tech_sql = plugin_paineldebordo_tv_tech_name_sql();
    $oldest = null;
    $sql_oldest = "
SELECT glpi_tickets.id, glpi_tickets.name, glpi_tickets.date, glpi_tickets.time_to_resolve,
  $tech_sql AS tech_name
FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$status_open} {$scope['entity_sql']} {$scope['group_where']}
ORDER BY glpi_tickets.date ASC LIMIT 1";
    $res = $DB->doQuery($sql_oldest);
    if ($res && ($row = $DB->fetchAssoc($res))) {
        $date = (string) $row['date'];
        $ttr = $row['time_to_resolve'] ?? null;
        $oldest = [
            'id'    => (int) $row['id'],
            'title' => (string) ($row['name'] ?? ''),
            'age'   => plugin_paineldebordo_tv_age_label($date),
            'late'  => $ttr !== null && $ttr !== '' && strtotime((string) $ttr) < time(),
            'tech'  => trim((string) ($row['tech_name'] ?? '')),
            'href'  => 'shell.php?' . http_build_query(['page' => 'tickets', 'theme' => $theme]),
        ];
    }

    // Mini status queues 1–4
    $status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
    $queues = [];
    foreach ([1, 2, 3, 4] as $st) {
        $c = $count(
            "SELECT COUNT(DISTINCT glpi_tickets.id) AS total $base
             AND glpi_tickets.status = $st"
        );
        $queues[] = [
            'status' => $st,
            'label'  => $status_map[$st] ?? plugin_paineldebordo_status_label($st),
            'count'  => $c,
        ];
    }

    $catalog = plugin_paineldebordo_charts_catalog();
    $charts = [];
    foreach ($catalog as $id => $meta) {
        $ds = plugin_paineldebordo_chart_dataset($id);
        $sum = 0;
        if (isset($ds['series']) && is_array($ds['series'])) {
            foreach ($ds['series'] as $serie) {
                $sum += array_sum($serie['data'] ?? []);
            }
        } else {
            $sum = array_sum($ds['data'] ?? []);
        }
        $charts[] = [
            'id'         => $id,
            'title'      => $meta['title'],
            'desc'       => $meta['desc'] ?? '',
            'type'       => plugin_paineldebordo_chart_hc_type($id),
            'categories' => $ds['categories'] ?? [],
            'data'       => $ds['data'] ?? [],
            'series'     => $ds['series'] ?? null,
            'colors'     => $ds['colors'] ?? ['#09141F', '#E73E11'],
            'has_data'   => !empty($ds['categories']) && $sum > 0,
            'href'       => 'shell.php?' . http_build_query(['page' => 'chart', 'chart' => $id, 'theme' => $theme]),
        ];
    }

    $board = [
        'ok'           => true,
        'server_ts'    => date('Y-m-d H:i:s'),
        'period'       => [
            'key'   => $period['period'],
            'label' => $period['label'],
            'start' => $period['start'],
            'end'   => $period['end'],
        ],
        'kpis'         => $kpis,
        'oldest'       => $oldest,
        'queues'       => $queues,
        'charts'       => $charts,
        'period_label' => $period['label'],
    ];

    if ($apply_layout) {
        return plugin_paineldebordo_overview_layout_apply($board);
    }
    return $board;
}
