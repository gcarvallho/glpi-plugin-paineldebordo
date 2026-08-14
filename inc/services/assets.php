<?php

/**
 * Assets NOC board — fleet inventory metrics (all itemtypes + computer deep dive).
 */

include_once __DIR__ . '/tickets.php';

/**
 * Asset type catalog for the fleet mural.
 *
 * @return array<string,array{table:string,label:string,model_field:string,model_table:string,icon:string}>
 */
function plugin_paineldebordo_assets_types(): array
{
    return [
        'Computer' => [
            'table'       => 'glpi_computers',
            'label'       => __('Computers', 'paineldebordo'),
            'model_field' => 'computermodels_id',
            'model_table' => 'glpi_computermodels',
            'icon'        => 'Computer',
        ],
        'Monitor' => [
            'table'       => 'glpi_monitors',
            'label'       => __('Monitors', 'paineldebordo'),
            'model_field' => 'monitormodels_id',
            'model_table' => 'glpi_monitormodels',
            'icon'        => 'Monitor',
        ],
        'NetworkEquipment' => [
            'table'       => 'glpi_networkequipments',
            'label'       => __('Network equipment', 'paineldebordo'),
            'model_field' => 'networkequipmentmodels_id',
            'model_table' => 'glpi_networkequipmentmodels',
            'icon'        => 'NetworkEquipment',
        ],
        'Printer' => [
            'table'       => 'glpi_printers',
            'label'       => __('Printers', 'paineldebordo'),
            'model_field' => 'printermodels_id',
            'model_table' => 'glpi_printermodels',
            'icon'        => 'Printer',
        ],
        'Phone' => [
            'table'       => 'glpi_phones',
            'label'       => __('Phones', 'paineldebordo'),
            'model_field' => 'phonemodels_id',
            'model_table' => 'glpi_phonemodels',
            'icon'        => 'Phone',
        ],
        'Peripheral' => [
            'table'       => 'glpi_peripherals',
            'label'       => __('Peripherals', 'paineldebordo'),
            'model_field' => 'peripheralmodels_id',
            'model_table' => 'glpi_peripheralmodels',
            'icon'        => 'Peripheral',
        ],
    ];
}

/**
 * Entity scope for fleet inventory.
 * Uses force_group=0 so a stale ticket-group filter never aborts the Assets page.
 *
 * @return array{entity_ids:string,entity_sql:string}
 */
function plugin_paineldebordo_assets_scope(string $alias = 'gi'): array
{
    $scope = plugin_paineldebordo_tickets_scope(0);
    $ids = $scope['entity_ids'] ?: '0';
    return [
        'entity_ids' => $ids,
        'entity_sql' => "AND {$alias}.entities_id IN ($ids)",
    ];
}

function plugin_paineldebordo_assets_count(string $sql): int
{
    global $DB;
    try {
        $r = $DB->doQuery($sql);
        if (!$r) {
            return 0;
        }
        $row = $DB->fetchAssoc($r);
        return (int) ($row['c'] ?? $row['total'] ?? 0);
    } catch (Throwable $e) {
        if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
            Toolbox::logInFile('paineldebordo', '[assets_count] ' . $e->getMessage() . "\n");
        }
        return 0;
    }
}

/**
 * @return array<int,array<string,mixed>>
 */
function plugin_paineldebordo_assets_fetch(string $sql, int $limit = 50): array
{
    global $DB;
    $out = [];
    try {
        $r = $DB->doQuery($sql);
        if (!$r) {
            return $out;
        }
        while ($row = $DB->fetchAssoc($r)) {
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }
    } catch (Throwable $e) {
        if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
            Toolbox::logInFile('paineldebordo', '[assets_fetch] ' . $e->getMessage() . "\n");
        }
    }
    return $out;
}

function plugin_paineldebordo_assets_fmt_mib(int $mib): string
{
    if ($mib >= 1024 * 1024) {
        return round($mib / (1024 * 1024), 1) . ' TB';
    }
    if ($mib >= 1024) {
        return round($mib / 1024, 1) . ' GB';
    }
    return $mib . ' MB';
}

/**
 * Resolve asset display name.
 */
function plugin_paineldebordo_assets_item_name(string $itemtype, int $items_id): string
{
    global $DB;
    $types = plugin_paineldebordo_assets_types();
    if (!isset($types[$itemtype]) || $items_id <= 0) {
        return $itemtype . ' #' . $items_id;
    }
    $table = $types[$itemtype]['table'];
    if (!$DB->TableExists($table)) {
        return $itemtype . ' #' . $items_id;
    }
    $r = $DB->doQuery('SELECT name FROM `' . $table . '` WHERE id = ' . (int) $items_id . ' LIMIT 1');
    if ($r && ($row = $DB->fetchAssoc($r)) && $row['name'] !== '') {
        return (string) $row['name'];
    }
    return $itemtype . ' #' . $items_id;
}

/**
 * Build Assets NOC payload.
 *
 * @param int $ram_threshold_mb RAM upgrade candidates at or below this total (default 8192 = 8 GB)
 * @return array<string,mixed>
 */
function plugin_paineldebordo_assets_board(int $ram_threshold_mb = 8192): array
{
    global $DB;

    $as = plugin_paineldebordo_assets_scope('gi');
    $ent = $as['entity_sql'];
    $types = plugin_paineldebordo_assets_types();
    $status_open = plugin_paineldebordo_tickets_open_status_sql();
    $stale_days = 7;
    $ram_threshold_mb = max(1024, min(65536, $ram_threshold_mb));

    $tiles = [];
    $fleet_total = 0;
    $dynamic_total = 0;
    $manual_total = 0;
    $no_loc = 0;
    $no_user = 0;
    $no_manuf = 0;
    $with_tickets = 0;

    $type_cats = [];
    $type_data = [];

    foreach ($types as $itemtype => $meta) {
        $table = $meta['table'];
        if (!$DB->TableExists($table)) {
            $tiles[] = [
                'key'   => $itemtype,
                'label' => $meta['label'],
                'value' => 0,
                'meta'  => __('Table missing', 'paineldebordo'),
                'mod'   => 'info',
                'icon'  => $meta['icon'],
            ];
            continue;
        }
        $base = "FROM `$table` gi WHERE gi.is_deleted = 0";
        if ($DB->fieldExists($table, 'is_template')) {
            $base .= ' AND gi.is_template = 0';
        }
        $base .= " $ent";

        $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
        $fleet_total += $total;
        $type_cats[] = $meta['label'];
        $type_data[] = $total;

        $dyn = 0;
        if ($DB->fieldExists($table, 'is_dynamic')) {
            $dyn = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base AND gi.is_dynamic = 1");
            $dynamic_total += $dyn;
            $manual_total += max(0, $total - $dyn);
        }

        if ($DB->fieldExists($table, 'locations_id')) {
            $no_loc += plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c $base AND (gi.locations_id IS NULL OR gi.locations_id = 0)"
            );
        }
        if ($DB->fieldExists($table, 'users_id')) {
            $no_user += plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c $base AND (gi.users_id IS NULL OR gi.users_id = 0)"
            );
        }
        if ($DB->fieldExists($table, 'manufacturers_id')) {
            $no_manuf += plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c $base AND (gi.manufacturers_id IS NULL OR gi.manufacturers_id = 0)"
            );
        }

        $wt = 0;
        if ($DB->TableExists('glpi_items_tickets') && $DB->TableExists('glpi_tickets')) {
            $tpl = $DB->fieldExists($table, 'is_template') ? ' AND gi.is_template = 0' : '';
            $wt = plugin_paineldebordo_assets_count(
                "SELECT COUNT(DISTINCT gi.id) AS c
FROM `$table` gi
INNER JOIN glpi_items_tickets it ON it.items_id = gi.id AND it.itemtype = '" . $DB->escape($itemtype) . "'
INNER JOIN glpi_tickets ON glpi_tickets.id = it.tickets_id
WHERE gi.is_deleted = 0 $tpl $ent
AND glpi_tickets.is_deleted = 0 $status_open"
            );
        }
        $with_tickets += $wt;

        $tiles[] = [
            'key'   => $itemtype,
            'label' => $meta['label'],
            'value' => $total,
            'meta'  => sprintf(__('%d dynamic · %d with tickets', 'paineldebordo'), $dyn, $wt),
            'mod'   => $itemtype === 'Computer' ? 'accent' : 'info',
            'icon'  => $meta['icon'],
        ];
    }

    $warranty_30 = 0;
    $warranty_90 = 0;
    $warranty_list = [];
    if ($DB->TableExists('glpi_infocoms')) {
        $itemtype_in = "'" . implode("','", array_map(static function ($t) use ($DB) {
            return $DB->escape($t);
        }, array_keys($types))) . "'";
        $expire_expr = 'DATE_ADD(ic.warranty_date, INTERVAL ic.warranty_duration MONTH)';
        $warranty_30 = plugin_paineldebordo_assets_count(
            "SELECT COUNT(*) AS c FROM glpi_infocoms ic
WHERE ic.itemtype IN ($itemtype_in)
AND ic.warranty_date IS NOT NULL AND ic.warranty_duration > 0
AND $expire_expr BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
AND ic.entities_id IN ({$as['entity_ids']})"
        );
        $warranty_90 = plugin_paineldebordo_assets_count(
            "SELECT COUNT(*) AS c FROM glpi_infocoms ic
WHERE ic.itemtype IN ($itemtype_in)
AND ic.warranty_date IS NOT NULL AND ic.warranty_duration > 0
AND $expire_expr BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
AND ic.entities_id IN ({$as['entity_ids']})"
        );
        $warranty_list = plugin_paineldebordo_assets_fetch(
            "SELECT ic.itemtype, ic.items_id, $expire_expr AS expires
FROM glpi_infocoms ic
WHERE ic.itemtype IN ($itemtype_in)
AND ic.warranty_date IS NOT NULL AND ic.warranty_duration > 0
AND $expire_expr BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
AND ic.entities_id IN ({$as['entity_ids']})
ORDER BY expires ASC LIMIT 15",
            15
        );
        foreach ($warranty_list as &$w) {
            $w['name'] = plugin_paineldebordo_assets_item_name((string) $w['itemtype'], (int) $w['items_id']);
            $w['expires_label'] = (string) $w['expires'];
            $w['type_label'] = plugin_paineldebordo_assets_type_label((string) $w['itemtype']);
        }
        unset($w);
    }

    $charts = [];
    $charts[] = [
        'id'         => 'fleet_types',
        'title'      => __('Park by type', 'paineldebordo'),
        'type'       => 'pie',
        'categories' => $type_cats,
        'data'       => $type_data,
        'colors'     => ['#09141F', '#E73E11', '#626976', '#1a7f4b', '#c9a227', '#4a6fa5'],
        'has_data'   => array_sum($type_data) > 0,
        'href'       => 'shell.php?page=assets',
    ];

    $state_cats = [];
    $state_data = [];
    if ($DB->TableExists('glpi_states') && $DB->TableExists('glpi_computers') && $DB->fieldExists('glpi_computers', 'states_id')) {
        $none = $DB->escape(__('None', 'paineldebordo'));
        foreach (plugin_paineldebordo_assets_fetch(
            "SELECT COALESCE(s.name, '$none') AS k, COUNT(*) AS c
FROM glpi_computers gi
LEFT JOIN glpi_states s ON s.id = gi.states_id
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
GROUP BY gi.states_id ORDER BY c DESC LIMIT 10",
            10
        ) as $row) {
            $state_cats[] = (string) $row['k'];
            $state_data[] = (int) $row['c'];
        }
    }
    $charts[] = [
        'id'         => 'comp_states',
        'title'      => __('Computers by status', 'paineldebordo'),
        'type'       => 'bar',
        'categories' => $state_cats,
        'data'       => $state_data,
        'colors'     => ['#E73E11'],
        'has_data'   => array_sum($state_data) > 0,
        'href'       => 'shell.php?page=assets',
    ];

    $manuf_cats = [];
    $manuf_data = [];
    if ($DB->TableExists('glpi_manufacturers') && $DB->TableExists('glpi_computers')) {
        $none = $DB->escape(__('None', 'paineldebordo'));
        foreach (plugin_paineldebordo_assets_fetch(
            "SELECT COALESCE(m.name, '$none') AS k, COUNT(*) AS c
FROM glpi_computers gi
LEFT JOIN glpi_manufacturers m ON m.id = gi.manufacturers_id
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
GROUP BY gi.manufacturers_id ORDER BY c DESC LIMIT 10",
            10
        ) as $row) {
            $manuf_cats[] = (string) $row['k'];
            $manuf_data[] = (int) $row['c'];
        }
    }
    $charts[] = [
        'id'         => 'comp_manuf',
        'title'      => __('Computers by manufacturer', 'paineldebordo'),
        'type'       => 'bar',
        'categories' => $manuf_cats,
        'data'       => $manuf_data,
        'colors'     => ['#09141F'],
        'has_data'   => array_sum($manuf_data) > 0,
        'href'       => 'shell.php?page=assets',
    ];

    $computers = 0;
    $stale_inv = 0;
    $agents_ok = 0;
    $agents_stale = 0;
    $no_agent = 0;
    $disk_crit = 0;
    $disk_warn = 0;
    $ram_low = 0;
    $ram_unknown = 0;
    $disk_encrypted = 0;
    $disk_plain = 0;
    $os_cats = [];
    $os_data = [];
    $disk_list = [];
    $ram_list = [];
    $stale_list = [];

    if ($DB->TableExists('glpi_computers')) {
        $cbase = "FROM glpi_computers gi WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent";
        $computers = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $cbase");

        if ($DB->fieldExists('glpi_computers', 'last_inventory_update')) {
            $stale_inv = plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c $cbase
AND (gi.last_inventory_update IS NULL OR gi.last_inventory_update < DATE_SUB(NOW(), INTERVAL $stale_days DAY))"
            );
            $stale_list = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, gi.last_inventory_update
$cbase
AND (gi.last_inventory_update IS NULL OR gi.last_inventory_update < DATE_SUB(NOW(), INTERVAL $stale_days DAY))
ORDER BY gi.last_inventory_update IS NULL DESC, gi.last_inventory_update ASC LIMIT 15",
                15
            );
        }

        if ($DB->TableExists('glpi_agents')) {
            // JOIN must come before WHERE (GLPI 11 doQuery throws on SQL errors).
            $agents_ok = plugin_paineldebordo_assets_count(
                "SELECT COUNT(DISTINCT gi.id) AS c
FROM glpi_computers gi
INNER JOIN glpi_agents a ON a.items_id = gi.id AND a.itemtype = 'Computer'
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND a.last_contact >= DATE_SUB(NOW(), INTERVAL $stale_days DAY)"
            );
            $agents_stale = plugin_paineldebordo_assets_count(
                "SELECT COUNT(DISTINCT gi.id) AS c
FROM glpi_computers gi
INNER JOIN glpi_agents a ON a.items_id = gi.id AND a.itemtype = 'Computer'
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND (a.last_contact IS NULL OR a.last_contact < DATE_SUB(NOW(), INTERVAL $stale_days DAY))"
            );
            $no_agent = plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c
FROM glpi_computers gi
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND NOT EXISTS (SELECT 1 FROM glpi_agents a WHERE a.items_id = gi.id AND a.itemtype = 'Computer')"
            );
        }

        if ($DB->TableExists('glpi_items_disks')) {
            $disk_crit = plugin_paineldebordo_assets_count(
                "SELECT COUNT(DISTINCT d.id) AS c
FROM glpi_items_disks d
INNER JOIN glpi_computers gi ON gi.id = d.items_id AND d.itemtype = 'Computer'
WHERE d.is_deleted = 0 AND gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND d.totalsize > 0 AND (100 * d.freesize / d.totalsize) < 10"
            );
            $disk_warn = plugin_paineldebordo_assets_count(
                "SELECT COUNT(DISTINCT d.id) AS c
FROM glpi_items_disks d
INNER JOIN glpi_computers gi ON gi.id = d.items_id AND d.itemtype = 'Computer'
WHERE d.is_deleted = 0 AND gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND d.totalsize > 0 AND (100 * d.freesize / d.totalsize) < 15
AND (100 * d.freesize / d.totalsize) >= 10"
            );
            if ($DB->fieldExists('glpi_items_disks', 'encryption_status')) {
                $disk_encrypted = plugin_paineldebordo_assets_count(
                    "SELECT COUNT(DISTINCT d.id) AS c
FROM glpi_items_disks d
INNER JOIN glpi_computers gi ON gi.id = d.items_id AND d.itemtype = 'Computer'
WHERE d.is_deleted = 0 AND gi.is_deleted = 0 AND gi.is_template = 0 $ent AND d.encryption_status > 0"
                );
                $disk_plain = plugin_paineldebordo_assets_count(
                    "SELECT COUNT(DISTINCT d.id) AS c
FROM glpi_items_disks d
INNER JOIN glpi_computers gi ON gi.id = d.items_id AND d.itemtype = 'Computer'
WHERE d.is_deleted = 0 AND gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND (d.encryption_status IS NULL OR d.encryption_status = 0)"
                );
            }
            $disk_list = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id AS computer_id, gi.name AS computer, d.mountpoint, d.device, d.totalsize, d.freesize,
ROUND(100 * d.freesize / NULLIF(d.totalsize, 0), 1) AS free_pct
FROM glpi_items_disks d
INNER JOIN glpi_computers gi ON gi.id = d.items_id AND d.itemtype = 'Computer'
WHERE d.is_deleted = 0 AND gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND d.totalsize > 0 AND (100 * d.freesize / d.totalsize) < 15
ORDER BY free_pct ASC LIMIT 15",
                15
            );
            foreach ($disk_list as &$d) {
                $d['total_label'] = plugin_paineldebordo_assets_fmt_mib((int) $d['totalsize']);
                $d['free_label'] = plugin_paineldebordo_assets_fmt_mib((int) $d['freesize']);
                $d['free_pct'] = (float) $d['free_pct'];
                $d['mount'] = trim((string) ($d['mountpoint'] ?: $d['device'] ?: '—'));
            }
            unset($d);
        }

        if ($DB->TableExists('glpi_items_devicememories')) {
            $ram_rows = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, COALESCE(SUM(m.size), 0) AS ram_mb
FROM glpi_computers gi
LEFT JOIN glpi_items_devicememories m
  ON m.items_id = gi.id AND m.itemtype = 'Computer' AND m.is_deleted = 0
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
GROUP BY gi.id, gi.name
HAVING ram_mb > 0 AND ram_mb <= $ram_threshold_mb
ORDER BY ram_mb ASC LIMIT 15",
                15
            );
            foreach ($ram_rows as $r) {
                $ram_list[] = [
                    'id'        => (int) $r['id'],
                    'name'      => (string) $r['name'],
                    'ram_mb'    => (int) $r['ram_mb'],
                    'ram_label' => plugin_paineldebordo_assets_fmt_mib((int) $r['ram_mb']),
                ];
            }
            $ram_low = plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c FROM (
SELECT gi.id, COALESCE(SUM(m.size), 0) AS ram_mb
FROM glpi_computers gi
LEFT JOIN glpi_items_devicememories m
  ON m.items_id = gi.id AND m.itemtype = 'Computer' AND m.is_deleted = 0
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
GROUP BY gi.id
HAVING ram_mb > 0 AND ram_mb <= $ram_threshold_mb
) t"
            );
            $ram_unknown = plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c FROM (
SELECT gi.id, COALESCE(SUM(m.size), 0) AS ram_mb
FROM glpi_computers gi
LEFT JOIN glpi_items_devicememories m
  ON m.items_id = gi.id AND m.itemtype = 'Computer' AND m.is_deleted = 0
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
GROUP BY gi.id
HAVING ram_mb = 0
) t"
            );
        }

        if ($DB->TableExists('glpi_items_operatingsystems') && $DB->TableExists('glpi_operatingsystems')) {
            $unk = $DB->escape(__('Unknown', 'paineldebordo'));
            foreach (plugin_paineldebordo_assets_fetch(
                "SELECT COALESCE(os.name, '$unk') AS k, COUNT(*) AS c
FROM glpi_items_operatingsystems ios
INNER JOIN glpi_computers gi ON gi.id = ios.items_id AND ios.itemtype = 'Computer'
LEFT JOIN glpi_operatingsystems os ON os.id = ios.operatingsystems_id
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
GROUP BY ios.operatingsystems_id ORDER BY c DESC LIMIT 10",
                10
            ) as $row) {
                $os_cats[] = (string) $row['k'];
                $os_data[] = (int) $row['c'];
            }
        }
    }

    $charts[] = [
        'id'         => 'os_mix',
        'title'      => __('Operating systems', 'paineldebordo'),
        'type'       => 'pie',
        'categories' => $os_cats,
        'data'       => $os_data,
        'colors'     => ['#09141F', '#E73E11', '#626976', '#1a7f4b', '#c9a227'],
        'has_data'   => array_sum($os_data) > 0,
        'href'       => 'shell.php?page=assets',
    ];

    $software_n = 0;
    $licenses_expire = 0;
    $license_list = [];
    if ($DB->TableExists('glpi_softwares')) {
        $software_n = plugin_paineldebordo_assets_count(
            "SELECT COUNT(*) AS c FROM glpi_softwares gi WHERE gi.is_deleted = 0 $ent"
        );
    }
    if ($DB->TableExists('glpi_softwarelicenses')) {
        $licenses_expire = plugin_paineldebordo_assets_count(
            "SELECT COUNT(*) AS c FROM glpi_softwarelicenses gi
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND gi.expire IS NOT NULL AND gi.expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"
        );
        $license_list = plugin_paineldebordo_assets_fetch(
            "SELECT gi.id, gi.name, gi.expire, COALESCE(s.name, '') AS software
FROM glpi_softwarelicenses gi
LEFT JOIN glpi_softwares s ON s.id = gi.softwares_id
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent
AND gi.expire IS NOT NULL AND gi.expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
ORDER BY gi.expire ASC LIMIT 15",
            15
        );
    }

    $consumables_out = 0;
    $cartridges_out = 0;
    if ($DB->TableExists('glpi_consumables') && $DB->fieldExists('glpi_consumables', 'date_out')) {
        $consumables_out = plugin_paineldebordo_assets_count(
            'SELECT COUNT(*) AS c FROM glpi_consumables WHERE date_out IS NULL'
        );
    }
    if ($DB->TableExists('glpi_cartridges') && $DB->fieldExists('glpi_cartridges', 'date_out')) {
        $cartridges_out = plugin_paineldebordo_assets_count(
            'SELECT COUNT(*) AS c FROM glpi_cartridges WHERE date_out IS NULL'
        );
    }

    $kpis = [
        [
            'key' => 'fleet', 'label' => __('Park total', 'paineldebordo'),
            'value' => $fleet_total, 'meta' => __('All types', 'paineldebordo'), 'mod' => 'accent', 'kind' => 'fleet',
        ],
        [
            'key' => 'computers', 'label' => __('Computers', 'paineldebordo'),
            'value' => $computers, 'meta' => __('In operation', 'paineldebordo'), 'mod' => 'primary', 'kind' => 'fleet',
        ],
        [
            'key' => 'dynamic', 'label' => __('Dynamic inventory', 'paineldebordo'),
            'value' => $dynamic_total, 'meta' => sprintf(__('%d manual', 'paineldebordo'), $manual_total), 'mod' => 'info', 'kind' => 'fleet',
        ],
        [
            'key' => 'with_tickets', 'label' => __('With open tickets', 'paineldebordo'),
            'value' => $with_tickets, 'meta' => __('Linked assets', 'paineldebordo'), 'mod' => 'danger', 'kind' => 'fleet',
        ],
        [
            'key' => 'warranty_30', 'label' => __('Warranty ≤ 30 days', 'paineldebordo'),
            'value' => $warranty_30, 'meta' => sprintf(__('%d within 90 days', 'paineldebordo'), $warranty_90), 'mod' => 'danger', 'kind' => 'alert',
        ],
        [
            'key' => 'stale_inv', 'label' => __('Stale inventory', 'paineldebordo'),
            'value' => $stale_inv, 'meta' => sprintf(__('More than %d days', 'paineldebordo'), $stale_days), 'mod' => 'danger', 'kind' => 'alert',
        ],
        [
            'key' => 'disk_crit', 'label' => __('Disks under 10% free', 'paineldebordo'),
            'value' => $disk_crit, 'meta' => sprintf(__('%d under 15%%', 'paineldebordo'), $disk_crit + $disk_warn), 'mod' => 'danger', 'kind' => 'alert',
        ],
        [
            'key' => 'ram_low', 'label' => __('Low RAM candidates', 'paineldebordo'),
            'value' => $ram_low, 'meta' => '≤ ' . plugin_paineldebordo_assets_fmt_mib($ram_threshold_mb), 'mod' => 'accent', 'kind' => 'alert',
        ],
        [
            'key' => 'no_agent', 'label' => __('No agent', 'paineldebordo'),
            'value' => $no_agent, 'meta' => sprintf(__('%d stale agents', 'paineldebordo'), $agents_stale), 'mod' => 'info', 'kind' => 'alert',
        ],
        [
            'key' => 'no_loc', 'label' => __('No location', 'paineldebordo'),
            'value' => $no_loc, 'meta' => sprintf(__('%d without user', 'paineldebordo'), $no_user), 'mod' => 'info', 'kind' => 'fleet',
        ],
        [
            'key' => 'licenses', 'label' => __('Licenses expiring', 'paineldebordo'),
            'value' => $licenses_expire, 'meta' => sprintf(__('%d software titles', 'paineldebordo'), $software_n), 'mod' => 'danger', 'kind' => 'alert',
        ],
        [
            'key' => 'stock', 'label' => __('Cartridges in stock', 'paineldebordo'),
            'value' => $cartridges_out, 'meta' => sprintf(__('%d consumables', 'paineldebordo'), $consumables_out), 'mod' => 'info', 'kind' => 'fleet',
        ],
    ];

    return [
        'ok'        => true,
        'server_ts' => date('Y-m-d H:i:s'),
        'tiles'     => $tiles,
        'kpis'      => $kpis,
        'charts'    => $charts,
        'lists'     => [
            'disks'    => $disk_list,
            'ram'      => $ram_list,
            'stale'    => $stale_list,
            'warranty' => $warranty_list,
            'licenses' => $license_list,
        ],
        'meta'      => [
            'ram_threshold_mb' => $ram_threshold_mb,
            'stale_days'       => $stale_days,
            'fleet_total'      => $fleet_total,
            'no_manufacturer'  => $no_manuf,
            'agents_ok'        => $agents_ok,
            'disk_encrypted'   => $disk_encrypted,
            'disk_plain'       => $disk_plain,
            'ram_unknown'      => $ram_unknown,
        ],
    ];
}

/**
 * Human label for an asset itemtype (never show raw class name in UI).
 */
function plugin_paineldebordo_assets_type_label(string $itemtype): string
{
    $types = plugin_paineldebordo_assets_types();
    if (isset($types[$itemtype])) {
        return $types[$itemtype]['label'];
    }
    if ($itemtype === 'SoftwareLicense') {
        return __('Licenses', 'paineldebordo');
    }
    return $itemtype;
}

/**
 * GLPI front form URL for an asset (relative to root_doc).
 */
function plugin_paineldebordo_assets_glpi_url(string $itemtype, int $id): string
{
    global $CFG_GLPI;
    $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
    $map = [
        'Computer'         => 'computer',
        'Monitor'          => 'monitor',
        'NetworkEquipment' => 'networkequipment',
        'Printer'          => 'printer',
        'Phone'            => 'phone',
        'Peripheral'       => 'peripheral',
        'SoftwareLicense'  => 'softwarelicense',
    ];
    $slug = $map[$itemtype] ?? strtolower($itemtype);
    return $root . '/front/' . $slug . '.form.php?id=' . (int) $id;
}

/**
 * Title + default columns for a list kind.
 *
 * @return array{title:string,columns:array<int,array{key:string,label:string}>}
 */
function plugin_paineldebordo_assets_list_meta(string $kind, int $ram_threshold_mb = 8192): array
{
    $types = plugin_paineldebordo_assets_types();
    if (isset($types[$kind])) {
        return [
            'title'   => $types[$kind]['label'],
            'columns' => [
                ['key' => 'name', 'label' => __('Name', 'paineldebordo')],
                ['key' => 'state', 'label' => __('Status', 'paineldebordo')],
                ['key' => 'location', 'label' => __('Location', 'paineldebordo')],
            ],
        ];
    }

    $map = [
        'fleet' => [
            'title'   => __('Park total', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Name', 'paineldebordo')],
                ['key' => 'type_label', 'label' => __('Type', 'paineldebordo')],
                ['key' => 'location', 'label' => __('Location', 'paineldebordo')],
            ],
        ],
        'computers' => [
            'title'   => __('Computers', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Computer', 'paineldebordo')],
                ['key' => 'state', 'label' => __('Status', 'paineldebordo')],
                ['key' => 'location', 'label' => __('Location', 'paineldebordo')],
            ],
        ],
        'dynamic' => [
            'title'   => __('Dynamic inventory', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Name', 'paineldebordo')],
                ['key' => 'type_label', 'label' => __('Type', 'paineldebordo')],
                ['key' => 'location', 'label' => __('Location', 'paineldebordo')],
            ],
        ],
        'with_tickets' => [
            'title'   => __('With open tickets', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Name', 'paineldebordo')],
                ['key' => 'type_label', 'label' => __('Type', 'paineldebordo')],
                ['key' => 'tickets', 'label' => __('Open tickets', 'paineldebordo')],
            ],
        ],
        'warranty_30' => [
            'title'   => __('Warranty ≤ 30 days', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Item', 'paineldebordo')],
                ['key' => 'type_label', 'label' => __('Type', 'paineldebordo')],
                ['key' => 'expires_label', 'label' => __('Expires', 'paineldebordo')],
            ],
        ],
        'stale_inv' => [
            'title'   => __('Stale inventory', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Computer', 'paineldebordo')],
                ['key' => 'last_inventory_update', 'label' => __('Last inventory', 'paineldebordo')],
            ],
        ],
        'disk_crit' => [
            'title'   => __('Disks under 10% free', 'paineldebordo'),
            'columns' => [
                ['key' => 'computer', 'label' => __('Computer', 'paineldebordo')],
                ['key' => 'mount', 'label' => __('Partition', 'paineldebordo')],
                ['key' => 'free_display', 'label' => __('Free space', 'paineldebordo')],
            ],
        ],
        'disks' => [
            'title'   => __('Disks almost full', 'paineldebordo'),
            'columns' => [
                ['key' => 'computer', 'label' => __('Computer', 'paineldebordo')],
                ['key' => 'mount', 'label' => __('Partition', 'paineldebordo')],
                ['key' => 'free_display', 'label' => __('Free space', 'paineldebordo')],
            ],
        ],
        'ram_low' => [
            'title'   => __('Low RAM candidates', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Computer', 'paineldebordo')],
                ['key' => 'ram_label', 'label' => __('RAM', 'paineldebordo')],
            ],
        ],
        'no_agent' => [
            'title'   => __('No agent', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Computer', 'paineldebordo')],
                ['key' => 'location', 'label' => __('Location', 'paineldebordo')],
            ],
        ],
        'no_loc' => [
            'title'   => __('No location', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Name', 'paineldebordo')],
                ['key' => 'type_label', 'label' => __('Type', 'paineldebordo')],
            ],
        ],
        'licenses' => [
            'title'   => __('Licenses expiring', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('License', 'paineldebordo')],
                ['key' => 'software', 'label' => __('Software', 'paineldebordo')],
                ['key' => 'expire', 'label' => __('Expires', 'paineldebordo')],
            ],
        ],
        'stock' => [
            'title'   => __('Cartridges in stock', 'paineldebordo'),
            'columns' => [
                ['key' => 'name', 'label' => __('Item', 'paineldebordo')],
                ['key' => 'meta', 'label' => __('Detail', 'paineldebordo')],
            ],
        ],
    ];

    if (isset($map[$kind])) {
        $meta = $map[$kind];
        if ($kind === 'ram_low') {
            $meta['title'] = __('Low RAM candidates', 'paineldebordo')
                . ' (≤ ' . plugin_paineldebordo_assets_fmt_mib($ram_threshold_mb) . ')';
        }
        return $meta;
    }

    return [
        'title'   => __('Assets', 'paineldebordo'),
        'columns' => [
            ['key' => 'name', 'label' => __('Name', 'paineldebordo')],
        ],
    ];
}

/**
 * Paginated asset list filtered by mural KPI / tile / alert kind.
 *
 * @param array{page?:int,limit?:int,q?:string,ram_mb?:int,itemtype?:string} $opts
 * @return array<string,mixed>
 */
function plugin_paineldebordo_assets_list(string $kind, array $opts = []): array
{
    global $DB;

    $page = max(1, (int) ($opts['page'] ?? 1));
    $limit = max(10, min(100, (int) ($opts['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    $q = trim((string) ($opts['q'] ?? ''));
    $ram_mb = (int) ($opts['ram_mb'] ?? 8192);
    if (!in_array($ram_mb, [4096, 8192, 16384], true)) {
        $ram_mb = 8192;
    }
    $stale_days = 7;

    $as = plugin_paineldebordo_assets_scope('gi');
    $ent = $as['entity_sql'];
    $types = plugin_paineldebordo_assets_types();
    $status_open = plugin_paineldebordo_tickets_open_status_sql();
    $meta = plugin_paineldebordo_assets_list_meta($kind, $ram_mb);

    $rows = [];
    $total = 0;
    $q_sql = '';
    if ($q !== '') {
        $qe = $DB->escape($q);
        $q_sql = " AND gi.name LIKE '%$qe%'";
    }

    $enrich_location = static function (array &$row) use ($DB): void {
        $row['location'] = '—';
        if (!empty($row['locations_id']) && $DB->TableExists('glpi_locations')) {
            $r = $DB->doQuery('SELECT name FROM glpi_locations WHERE id = ' . (int) $row['locations_id'] . ' LIMIT 1');
            if ($r && ($loc = $DB->fetchAssoc($r)) && $loc['name'] !== '') {
                $row['location'] = (string) $loc['name'];
            }
        }
    };

    $enrich_state = static function (array &$row) use ($DB): void {
        $row['state'] = '—';
        if (!empty($row['states_id']) && $DB->TableExists('glpi_states')) {
            $r = $DB->doQuery('SELECT name FROM glpi_states WHERE id = ' . (int) $row['states_id'] . ' LIMIT 1');
            if ($r && ($st = $DB->fetchAssoc($r)) && $st['name'] !== '') {
                $row['state'] = (string) $st['name'];
            }
        }
    };

    // Tile / type list
    if (isset($types[$kind])) {
        $itemtype = $kind;
        $table = $types[$itemtype]['table'];
        if ($DB->TableExists($table)) {
            $base = "FROM `$table` gi WHERE gi.is_deleted = 0";
            if ($DB->fieldExists($table, 'is_template')) {
                $base .= ' AND gi.is_template = 0';
            }
            $base .= " $ent $q_sql";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
            $sel = 'gi.id, gi.name';
            if ($DB->fieldExists($table, 'locations_id')) {
                $sel .= ', gi.locations_id';
            }
            if ($DB->fieldExists($table, 'states_id')) {
                $sel .= ', gi.states_id';
            }
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT $sel $base ORDER BY gi.name ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $row = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => $itemtype,
                    'type_label' => $types[$itemtype]['label'],
                    'name'       => (string) ($r['name'] ?? ''),
                    'locations_id' => (int) ($r['locations_id'] ?? 0),
                    'states_id'  => (int) ($r['states_id'] ?? 0),
                    'clickable'  => true,
                ];
                $enrich_location($row);
                $enrich_state($row);
                $rows[] = $row;
            }
        }
    } elseif ($kind === 'computers' || $kind === 'fleet') {
        // fleet: all computers as primary park drill (v1)
        if ($DB->TableExists('glpi_computers')) {
            $base = "FROM glpi_computers gi WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, gi.locations_id, gi.states_id $base ORDER BY gi.name ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $row = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => 'Computer',
                    'type_label' => $types['Computer']['label'],
                    'name'       => (string) $r['name'],
                    'locations_id' => (int) ($r['locations_id'] ?? 0),
                    'states_id'  => (int) ($r['states_id'] ?? 0),
                    'clickable'  => true,
                ];
                $enrich_location($row);
                $enrich_state($row);
                $rows[] = $row;
            }
        }
    } elseif ($kind === 'dynamic') {
        foreach ($types as $itemtype => $tmeta) {
            $table = $tmeta['table'];
            if (!$DB->TableExists($table) || !$DB->fieldExists($table, 'is_dynamic')) {
                continue;
            }
            $tpl = $DB->fieldExists($table, 'is_template') ? ' AND gi.is_template = 0' : '';
            $base = "FROM `$table` gi WHERE gi.is_deleted = 0 $tpl AND gi.is_dynamic = 1 $ent $q_sql";
            $total += plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
        }
        // Page across types: fetch computers first then others until limit filled (simple v1: Computer only if enough)
        if ($DB->TableExists('glpi_computers') && $DB->fieldExists('glpi_computers', 'is_dynamic')) {
            $base = "FROM glpi_computers gi WHERE gi.is_deleted = 0 AND gi.is_template = 0 AND gi.is_dynamic = 1 $ent $q_sql";
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, gi.locations_id $base ORDER BY gi.name ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $row = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => 'Computer',
                    'type_label' => $types['Computer']['label'],
                    'name'       => (string) $r['name'],
                    'locations_id' => (int) ($r['locations_id'] ?? 0),
                    'clickable'  => true,
                ];
                $enrich_location($row);
                $rows[] = $row;
            }
        }
    } elseif ($kind === 'with_tickets') {
        if ($DB->TableExists('glpi_items_tickets') && $DB->TableExists('glpi_tickets') && $DB->TableExists('glpi_computers')) {
            $base = "FROM glpi_computers gi
INNER JOIN glpi_items_tickets it ON it.items_id = gi.id AND it.itemtype = 'Computer'
INNER JOIN glpi_tickets ON glpi_tickets.id = it.tickets_id
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql
AND glpi_tickets.is_deleted = 0 $status_open";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(DISTINCT gi.id) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, COUNT(DISTINCT glpi_tickets.id) AS tickets
$base
GROUP BY gi.id, gi.name
ORDER BY tickets DESC, gi.name ASC
LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $rows[] = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => 'Computer',
                    'type_label' => $types['Computer']['label'],
                    'name'       => (string) $r['name'],
                    'tickets'    => (int) $r['tickets'],
                    'clickable'  => true,
                ];
            }
        }
    } elseif ($kind === 'warranty_30') {
        if ($DB->TableExists('glpi_infocoms')) {
            $itemtype_in = "'" . implode("','", array_map(static function ($t) use ($DB) {
                return $DB->escape($t);
            }, array_keys($types))) . "'";
            $expire_expr = 'DATE_ADD(ic.warranty_date, INTERVAL ic.warranty_duration MONTH)';
            $name_filter = '';
            // name filter applied after resolve
            $where = "ic.itemtype IN ($itemtype_in)
AND ic.warranty_date IS NOT NULL AND ic.warranty_duration > 0
AND $expire_expr BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
AND ic.entities_id IN ({$as['entity_ids']})";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c FROM glpi_infocoms ic WHERE $where");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT ic.itemtype, ic.items_id, $expire_expr AS expires
FROM glpi_infocoms ic
WHERE $where
ORDER BY expires ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $name = plugin_paineldebordo_assets_item_name((string) $r['itemtype'], (int) $r['items_id']);
                if ($q !== '' && stripos($name, $q) === false) {
                    continue;
                }
                $rows[] = [
                    'id'            => (int) $r['items_id'],
                    'items_id'      => (int) $r['items_id'],
                    'itemtype'      => (string) $r['itemtype'],
                    'type_label'    => plugin_paineldebordo_assets_type_label((string) $r['itemtype']),
                    'name'          => $name,
                    'expires_label' => (string) $r['expires'],
                    'clickable'     => true,
                ];
            }
        }
    } elseif ($kind === 'stale_inv') {
        if ($DB->TableExists('glpi_computers') && $DB->fieldExists('glpi_computers', 'last_inventory_update')) {
            $base = "FROM glpi_computers gi WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql
AND (gi.last_inventory_update IS NULL OR gi.last_inventory_update < DATE_SUB(NOW(), INTERVAL $stale_days DAY))";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, gi.last_inventory_update $base
ORDER BY gi.last_inventory_update IS NULL DESC, gi.last_inventory_update ASC
LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $rows[] = [
                    'id'                     => (int) $r['id'],
                    'items_id'               => (int) $r['id'],
                    'itemtype'               => 'Computer',
                    'name'                   => (string) $r['name'],
                    'last_inventory_update'  => $r['last_inventory_update'] ?: '—',
                    'clickable'              => true,
                ];
            }
        }
    } elseif ($kind === 'disk_crit' || $kind === 'disks') {
        if ($DB->TableExists('glpi_items_disks') && $DB->TableExists('glpi_computers')) {
            $pct_max = ($kind === 'disk_crit') ? 10 : 15;
            $name_q = '';
            if ($q !== '') {
                $qe = $DB->escape($q);
                $name_q = " AND gi.name LIKE '%$qe%'";
            }
            $base = "FROM glpi_items_disks d
INNER JOIN glpi_computers gi ON gi.id = d.items_id AND d.itemtype = 'Computer'
WHERE d.is_deleted = 0 AND gi.is_deleted = 0 AND gi.is_template = 0 $ent $name_q
AND d.totalsize > 0 AND (100 * d.freesize / d.totalsize) < $pct_max";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(DISTINCT d.id) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id AS computer_id, gi.name AS computer, d.mountpoint, d.device, d.totalsize, d.freesize,
ROUND(100 * d.freesize / NULLIF(d.totalsize, 0), 1) AS free_pct
$base
ORDER BY free_pct ASC
LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $d) {
                $mount = trim((string) ($d['mountpoint'] ?: $d['device'] ?: '—'));
                $free_label = plugin_paineldebordo_assets_fmt_mib((int) $d['freesize']);
                $total_label = plugin_paineldebordo_assets_fmt_mib((int) $d['totalsize']);
                $rows[] = [
                    'id'           => (int) $d['computer_id'],
                    'items_id'     => (int) $d['computer_id'],
                    'itemtype'     => 'Computer',
                    'computer_id'  => (int) $d['computer_id'],
                    'computer'     => (string) $d['computer'],
                    'name'         => (string) $d['computer'],
                    'mount'        => $mount,
                    'free_pct'     => (float) $d['free_pct'],
                    'free_label'   => $free_label,
                    'total_label'  => $total_label,
                    'free_display' => ((float) $d['free_pct']) . '% · ' . $free_label . ' / ' . $total_label,
                    'clickable'    => true,
                ];
            }
        }
    } elseif ($kind === 'ram_low') {
        if ($DB->TableExists('glpi_items_devicememories') && $DB->TableExists('glpi_computers')) {
            $base = "FROM glpi_computers gi
LEFT JOIN glpi_items_devicememories m
  ON m.items_id = gi.id AND m.itemtype = 'Computer' AND m.is_deleted = 0
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql
GROUP BY gi.id, gi.name
HAVING ram_mb > 0 AND ram_mb <= $ram_mb";
            $total = plugin_paineldebordo_assets_count(
                "SELECT COUNT(*) AS c FROM (
SELECT gi.id, COALESCE(SUM(m.size), 0) AS ram_mb
FROM glpi_computers gi
LEFT JOIN glpi_items_devicememories m
  ON m.items_id = gi.id AND m.itemtype = 'Computer' AND m.is_deleted = 0
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql
GROUP BY gi.id
HAVING ram_mb > 0 AND ram_mb <= $ram_mb
) t"
            );
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, COALESCE(SUM(m.size), 0) AS ram_mb
$base
ORDER BY ram_mb ASC
LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $rows[] = [
                    'id'        => (int) $r['id'],
                    'items_id'  => (int) $r['id'],
                    'itemtype'  => 'Computer',
                    'name'      => (string) $r['name'],
                    'ram_mb'    => (int) $r['ram_mb'],
                    'ram_label' => plugin_paineldebordo_assets_fmt_mib((int) $r['ram_mb']),
                    'clickable' => true,
                ];
            }
        }
    } elseif ($kind === 'no_agent') {
        if ($DB->TableExists('glpi_computers') && $DB->TableExists('glpi_agents')) {
            $base = "FROM glpi_computers gi
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql
AND NOT EXISTS (SELECT 1 FROM glpi_agents a WHERE a.items_id = gi.id AND a.itemtype = 'Computer')";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, gi.locations_id $base ORDER BY gi.name ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $row = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => 'Computer',
                    'name'       => (string) $r['name'],
                    'locations_id' => (int) ($r['locations_id'] ?? 0),
                    'clickable'  => true,
                ];
                $enrich_location($row);
                $rows[] = $row;
            }
        }
    } elseif ($kind === 'no_loc') {
        if ($DB->TableExists('glpi_computers')) {
            $base = "FROM glpi_computers gi WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $q_sql
AND (gi.locations_id IS NULL OR gi.locations_id = 0)";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name $base ORDER BY gi.name ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $rows[] = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => 'Computer',
                    'type_label' => $types['Computer']['label'],
                    'name'       => (string) $r['name'],
                    'clickable'  => true,
                ];
            }
        }
    } elseif ($kind === 'licenses') {
        if ($DB->TableExists('glpi_softwarelicenses')) {
            $name_q = $q !== '' ? (" AND (gi.name LIKE '%" . $DB->escape($q) . "%' OR s.name LIKE '%" . $DB->escape($q) . "%')") : '';
            $base = "FROM glpi_softwarelicenses gi
LEFT JOIN glpi_softwares s ON s.id = gi.softwares_id
WHERE gi.is_deleted = 0 AND gi.is_template = 0 $ent $name_q
AND gi.expire IS NOT NULL AND gi.expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
            $total = plugin_paineldebordo_assets_count("SELECT COUNT(*) AS c $base");
            $raw = plugin_paineldebordo_assets_fetch(
                "SELECT gi.id, gi.name, gi.expire, COALESCE(s.name, '') AS software
$base ORDER BY gi.expire ASC LIMIT $offset, $limit",
                $limit
            );
            foreach ($raw as $r) {
                $rows[] = [
                    'id'         => (int) $r['id'],
                    'items_id'   => (int) $r['id'],
                    'itemtype'   => 'SoftwareLicense',
                    'name'       => (string) $r['name'],
                    'software'   => (string) $r['software'],
                    'expire'     => (string) $r['expire'],
                    'clickable'  => true,
                    'glpi_only'  => true,
                ];
            }
        }
    } elseif ($kind === 'stock') {
        if ($DB->TableExists('glpi_cartridges') && $DB->fieldExists('glpi_cartridges', 'date_out')) {
            $total = plugin_paineldebordo_assets_count(
                'SELECT COUNT(*) AS c FROM glpi_cartridges WHERE date_out IS NULL'
            );
            $raw = plugin_paineldebordo_assets_fetch(
                'SELECT id, date_in FROM glpi_cartridges WHERE date_out IS NULL ORDER BY id DESC LIMIT ' . (int) $offset . ', ' . (int) $limit,
                $limit
            );
            foreach ($raw as $r) {
                $rows[] = [
                    'id'        => (int) $r['id'],
                    'items_id'  => (int) $r['id'],
                    'itemtype'  => 'Cartridge',
                    'name'      => sprintf(__('Cartridge #%d', 'paineldebordo'), (int) $r['id']),
                    'meta'      => (string) ($r['date_in'] ?? '—'),
                    'clickable' => false,
                ];
            }
        }
    }

    $pages = $limit > 0 ? (int) max(1, ceil($total / $limit)) : 1;

    return [
        'ok'      => true,
        'kind'    => $kind,
        'title'   => $meta['title'],
        'columns' => $meta['columns'],
        'rows'    => $rows,
        'total'   => $total,
        'page'    => $page,
        'limit'   => $limit,
        'pages'   => $pages,
        'q'       => $q,
    ];
}

/**
 * Single asset detail for drill-down charts (Computer-first).
 *
 * @return array<string,mixed>
 */
function plugin_paineldebordo_assets_item(string $itemtype, int $id): array
{
    global $DB, $CFG_GLPI;

    $types = plugin_paineldebordo_assets_types();
    $as = plugin_paineldebordo_assets_scope('gi');
    $ent = $as['entity_sql'];
    $status_open = plugin_paineldebordo_tickets_open_status_sql();

    if ($itemtype === 'SoftwareLicense') {
        if (!$DB->TableExists('glpi_softwarelicenses') || $id <= 0) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $r = $DB->doQuery(
            "SELECT gi.id, gi.name, gi.expire, COALESCE(s.name, '') AS software
FROM glpi_softwarelicenses gi
LEFT JOIN glpi_softwares s ON s.id = gi.softwares_id
WHERE gi.id = $id AND gi.is_deleted = 0 $ent LIMIT 1"
        );
        $row = $r ? $DB->fetchAssoc($r) : null;
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return [
            'ok'         => true,
            'itemtype'   => 'SoftwareLicense',
            'type_label' => __('Licenses', 'paineldebordo'),
            'id'         => (int) $row['id'],
            'name'       => (string) $row['name'],
            'fields'     => [
                ['label' => __('Software', 'paineldebordo'), 'value' => (string) $row['software']],
                ['label' => __('Expires', 'paineldebordo'), 'value' => (string) ($row['expire'] ?: '—')],
            ],
            'disks'      => [],
            'ram_mb'     => 0,
            'ram_label'  => '—',
            'charts'     => [],
            'glpi_url'   => plugin_paineldebordo_assets_glpi_url('SoftwareLicense', (int) $row['id']),
            'open_tickets' => 0,
        ];
    }

    if (!isset($types[$itemtype]) || $id <= 0) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $table = $types[$itemtype]['table'];
    if (!$DB->TableExists($table)) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $tpl = $DB->fieldExists($table, 'is_template') ? ' AND gi.is_template = 0' : '';
    $cols = ['gi.id', 'gi.name', 'gi.entities_id'];
    foreach (['locations_id', 'users_id', 'manufacturers_id', 'states_id', 'is_dynamic', 'last_inventory_update', $types[$itemtype]['model_field']] as $f) {
        if ($f && $DB->fieldExists($table, $f)) {
            $cols[] = 'gi.`' . $f . '`';
        }
    }
    $sql = 'SELECT ' . implode(', ', $cols) . " FROM `$table` gi WHERE gi.id = $id AND gi.is_deleted = 0 $tpl $ent LIMIT 1";
    $r = $DB->doQuery($sql);
    $row = $r ? $DB->fetchAssoc($r) : null;
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $location = '—';
    if (!empty($row['locations_id']) && $DB->TableExists('glpi_locations')) {
        $lr = $DB->doQuery('SELECT name FROM glpi_locations WHERE id = ' . (int) $row['locations_id'] . ' LIMIT 1');
        if ($lr && ($l = $DB->fetchAssoc($lr)) && $l['name'] !== '') {
            $location = (string) $l['name'];
        }
    }
    $user = '—';
    if (!empty($row['users_id']) && $DB->TableExists('glpi_users')) {
        $ur = $DB->doQuery('SELECT name, realname, firstname FROM glpi_users WHERE id = ' . (int) $row['users_id'] . ' LIMIT 1');
        if ($ur && ($u = $DB->fetchAssoc($ur))) {
            $user = trim(((string) ($u['firstname'] ?? '')) . ' ' . ((string) ($u['realname'] ?? '')));
            if ($user === '') {
                $user = (string) ($u['name'] ?? '—');
            }
        }
    }
    $manuf = '—';
    if (!empty($row['manufacturers_id']) && $DB->TableExists('glpi_manufacturers')) {
        $mr = $DB->doQuery('SELECT name FROM glpi_manufacturers WHERE id = ' . (int) $row['manufacturers_id'] . ' LIMIT 1');
        if ($mr && ($m = $DB->fetchAssoc($mr)) && $m['name'] !== '') {
            $manuf = (string) $m['name'];
        }
    }
    $state = '—';
    if (!empty($row['states_id']) && $DB->TableExists('glpi_states')) {
        $sr = $DB->doQuery('SELECT name FROM glpi_states WHERE id = ' . (int) $row['states_id'] . ' LIMIT 1');
        if ($sr && ($s = $DB->fetchAssoc($sr)) && $s['name'] !== '') {
            $state = (string) $s['name'];
        }
    }
    $model = '—';
    $mf = $types[$itemtype]['model_field'];
    $mt = $types[$itemtype]['model_table'];
    if (!empty($row[$mf]) && $DB->TableExists($mt)) {
        $mdr = $DB->doQuery('SELECT name FROM `' . $mt . '` WHERE id = ' . (int) $row[$mf] . ' LIMIT 1');
        if ($mdr && ($md = $DB->fetchAssoc($mdr)) && $md['name'] !== '') {
            $model = (string) $md['name'];
        }
    }

    $fields = [
        ['label' => __('Type', 'paineldebordo'), 'value' => $types[$itemtype]['label']],
        ['label' => __('Status', 'paineldebordo'), 'value' => $state],
        ['label' => __('Manufacturer', 'paineldebordo'), 'value' => $manuf],
        ['label' => __('Model', 'paineldebordo'), 'value' => $model],
        ['label' => __('Location', 'paineldebordo'), 'value' => $location],
        ['label' => __('User', 'paineldebordo'), 'value' => $user],
    ];
    if (isset($row['is_dynamic'])) {
        $fields[] = [
            'label' => __('Dynamic inventory', 'paineldebordo'),
            'value' => ((int) $row['is_dynamic'] === 1)
                ? __('Yes', 'paineldebordo')
                : __('No', 'paineldebordo'),
        ];
    }
    $last_inv = (string) ($row['last_inventory_update'] ?? '');
    if ($last_inv !== '' || $itemtype === 'Computer') {
        $fields[] = [
            'label' => __('Last inventory', 'paineldebordo'),
            'value' => $last_inv !== '' ? $last_inv : '—',
        ];
    }

    $agent_contact = '';
    if ($itemtype === 'Computer' && $DB->TableExists('glpi_agents')) {
        $ar = $DB->doQuery(
            "SELECT last_contact FROM glpi_agents WHERE items_id = $id AND itemtype = 'Computer' ORDER BY last_contact DESC LIMIT 1"
        );
        if ($ar && ($a = $DB->fetchAssoc($ar))) {
            $agent_contact = (string) ($a['last_contact'] ?? '');
            $fields[] = [
                'label' => __('Agent last contact', 'paineldebordo'),
                'value' => $agent_contact !== '' ? $agent_contact : '—',
            ];
        } else {
            $fields[] = [
                'label' => __('Agent last contact', 'paineldebordo'),
                'value' => __('No agent', 'paineldebordo'),
            ];
        }
    }

    $os_name = '—';
    if ($itemtype === 'Computer' && $DB->TableExists('glpi_items_operatingsystems') && $DB->TableExists('glpi_operatingsystems')) {
        $or = $DB->doQuery(
            "SELECT COALESCE(os.name, '') AS osname, COALESCE(ios.operatingsystemversions_id, 0) AS ver
FROM glpi_items_operatingsystems ios
LEFT JOIN glpi_operatingsystems os ON os.id = ios.operatingsystems_id
WHERE ios.items_id = $id AND ios.itemtype = 'Computer' LIMIT 1"
        );
        if ($or && ($o = $DB->fetchAssoc($or)) && $o['osname'] !== '') {
            $os_name = (string) $o['osname'];
        }
        $fields[] = ['label' => __('Operating systems', 'paineldebordo'), 'value' => $os_name];
    }

    $warranty = '—';
    if ($DB->TableExists('glpi_infocoms')) {
        $expire_expr = 'DATE_ADD(ic.warranty_date, INTERVAL ic.warranty_duration MONTH)';
        $wr = $DB->doQuery(
            "SELECT $expire_expr AS expires FROM glpi_infocoms ic
WHERE ic.itemtype = '" . $DB->escape($itemtype) . "' AND ic.items_id = $id
AND ic.warranty_date IS NOT NULL AND ic.warranty_duration > 0 LIMIT 1"
        );
        if ($wr && ($w = $DB->fetchAssoc($wr)) && !empty($w['expires'])) {
            $warranty = (string) $w['expires'];
        }
        $fields[] = ['label' => __('Warranty ending', 'paineldebordo'), 'value' => $warranty];
    }

    $open_tickets = 0;
    if ($DB->TableExists('glpi_items_tickets') && $DB->TableExists('glpi_tickets')) {
        $open_tickets = plugin_paineldebordo_assets_count(
            "SELECT COUNT(DISTINCT glpi_tickets.id) AS c
FROM glpi_items_tickets it
INNER JOIN glpi_tickets ON glpi_tickets.id = it.tickets_id
WHERE it.items_id = $id AND it.itemtype = '" . $DB->escape($itemtype) . "'
AND glpi_tickets.is_deleted = 0 $status_open"
        );
        $fields[] = [
            'label' => __('Open tickets', 'paineldebordo'),
            'value' => (string) $open_tickets,
        ];
    }

    $disks = [];
    $ram_mb = 0;
    $charts = [];
    $inv_age_days = null;

    if ($itemtype === 'Computer' && $DB->TableExists('glpi_items_disks')) {
        $enc_sel = $DB->fieldExists('glpi_items_disks', 'encryption_status') ? ', d.encryption_status' : '';
        $drows = plugin_paineldebordo_assets_fetch(
            "SELECT d.mountpoint, d.device, d.totalsize, d.freesize,
ROUND(100 * d.freesize / NULLIF(d.totalsize, 0), 1) AS free_pct
$enc_sel
FROM glpi_items_disks d
WHERE d.is_deleted = 0 AND d.items_id = $id AND d.itemtype = 'Computer' AND d.totalsize > 0
ORDER BY d.mountpoint ASC",
            50
        );
        $cats = [];
        $used = [];
        $free = [];
        foreach ($drows as $d) {
            $mount = trim((string) ($d['mountpoint'] ?: $d['device'] ?: '—'));
            $total_m = (int) $d['totalsize'];
            $free_m = (int) $d['freesize'];
            $used_m = max(0, $total_m - $free_m);
            $entry = [
                'mount'       => $mount,
                'totalsize'   => $total_m,
                'freesize'    => $free_m,
                'usedsize'    => $used_m,
                'free_pct'    => (float) $d['free_pct'],
                'used_pct'    => $total_m > 0 ? round(100 * $used_m / $total_m, 1) : 0,
                'total_label' => plugin_paineldebordo_assets_fmt_mib($total_m),
                'free_label'  => plugin_paineldebordo_assets_fmt_mib($free_m),
                'used_label'  => plugin_paineldebordo_assets_fmt_mib($used_m),
            ];
            if (isset($d['encryption_status'])) {
                $entry['encrypted'] = ((int) $d['encryption_status']) > 0;
            }
            $disks[] = $entry;
            $cats[] = $mount;
            $used[] = round($used_m / 1024, 2);
            $free[] = round($free_m / 1024, 2);
        }
        if ($cats) {
            $charts[] = [
                'id'         => 'disks',
                'title'      => __('Disk usage by partition', 'paineldebordo'),
                'type'       => 'bar',
                'stacked'    => true,
                'categories' => $cats,
                'series'     => [
                    ['name' => __('Used', 'paineldebordo'), 'data' => $used, 'color' => '#E73E11'],
                    ['name' => __('Free', 'paineldebordo'), 'data' => $free, 'color' => '#1a7f4b'],
                ],
                'y_title'    => 'GB',
                'has_data'   => true,
            ];
        }
    }

    if ($itemtype === 'Computer' && $DB->TableExists('glpi_items_devicememories')) {
        $rr = $DB->doQuery(
            "SELECT COALESCE(SUM(m.size), 0) AS ram_mb
FROM glpi_items_devicememories m
WHERE m.items_id = $id AND m.itemtype = 'Computer' AND m.is_deleted = 0"
        );
        if ($rr && ($rm = $DB->fetchAssoc($rr))) {
            $ram_mb = (int) $rm['ram_mb'];
        }
        $fields[] = [
            'label' => __('RAM', 'paineldebordo'),
            'value' => $ram_mb > 0 ? plugin_paineldebordo_assets_fmt_mib($ram_mb) : '—',
        ];
        if ($ram_mb > 0) {
            $charts[] = [
                'id'         => 'ram',
                'title'      => __('Memory RAM', 'paineldebordo'),
                'type'       => 'bar',
                'categories' => [__('Total', 'paineldebordo')],
                'series'     => [
                    ['name' => __('RAM', 'paineldebordo'), 'data' => [round($ram_mb / 1024, 2)], 'color' => '#09141F'],
                ],
                'y_title'    => 'GB',
                'has_data'   => true,
            ];
        }
    }

    if ($last_inv !== '') {
        $ts = strtotime($last_inv);
        if ($ts) {
            $inv_age_days = (int) max(0, floor((time() - $ts) / 86400));
            $charts[] = [
                'id'         => 'inv_age',
                'title'      => __('Days since last inventory', 'paineldebordo'),
                'type'       => 'bar',
                'categories' => [__('Inventory age', 'paineldebordo')],
                'series'     => [
                    ['name' => __('Days', 'paineldebordo'), 'data' => [$inv_age_days], 'color' => '#c9a227'],
                ],
                'y_title'    => __('Days', 'paineldebordo'),
                'has_data'   => true,
            ];
        }
    }

    return [
        'ok'            => true,
        'itemtype'      => $itemtype,
        'type_label'    => $types[$itemtype]['label'],
        'id'            => (int) $row['id'],
        'name'          => (string) $row['name'],
        'fields'        => $fields,
        'disks'         => $disks,
        'ram_mb'        => $ram_mb,
        'ram_label'     => $ram_mb > 0 ? plugin_paineldebordo_assets_fmt_mib($ram_mb) : '—',
        'inv_age_days'  => $inv_age_days,
        'charts'        => $charts,
        'glpi_url'      => plugin_paineldebordo_assets_glpi_url($itemtype, (int) $row['id']),
        'open_tickets'  => $open_tickets,
        'as_of'         => $last_inv !== '' ? $last_inv : null,
        'as_of_label'   => $last_inv !== ''
            ? sprintf(__('Snapshot as of %s', 'paineldebordo'), $last_inv)
            : __('Inventory snapshot', 'paineldebordo'),
    ];
}
