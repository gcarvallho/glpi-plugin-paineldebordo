<?php

/**
 * BI Studio — canvas layout (replaces legacy Metrics page).
 */

include_once dirname(__DIR__) . '/filters.inc.php';
include_once __DIR__ . '/charts.php';
include_once __DIR__ . '/overview.php';
include_once __DIR__ . '/assets.php';

/**
 * Catalog of assets list widgets (mural alert lists).
 *
 * @return array<string,string> key => label
 */
function plugin_paineldebordo_bi_assets_list_catalog(): array
{
    return [
        'disks'     => __('Disks almost full', 'paineldebordo'),
        'ram'       => __('Low RAM candidates', 'paineldebordo'),
        'stale'     => __('Stale inventory', 'paineldebordo'),
        'warranty'  => __('Warranty ending', 'paineldebordo'),
        'licenses'  => __('Licenses expiring', 'paineldebordo'),
    ];
}

/**
 * Widget catalog for the palette.
 *
 * @return array{
 *   kpis: array<string,string>,
 *   charts: array<string,string>,
 *   tickets_kpis: array<string,string>,
 *   tickets_charts: array<string,string>,
 *   assets_kpis: array<string,string>,
 *   assets_charts: array<string,string>,
 *   assets_lists: array<string,string>
 * }
 */
function plugin_paineldebordo_bi_palette(): array
{
    $tickets_kpis = [];
    foreach (plugin_paineldebordo_overview_kpi_keys() as $key) {
        $labels = [
            'opened_period' => __('Opened in period', 'paineldebordo'),
            'solved_period' => __('Solved in period', 'paineldebordo'),
            'balance'       => __('Balance', 'paineldebordo'),
            'requesters'    => __('Requesters', 'paineldebordo'),
            'open'          => __('Backlog', 'paineldebordo'),
            'late'          => __('Late', 'paineldebordo'),
            'due_24h'       => __('Due in 24h', 'paineldebordo'),
            'ontime'        => __('On time %', 'paineldebordo'),
            'validation'    => __('Validation', 'paineldebordo'),
            'solution'      => __('Solution approved', 'paineldebordo'),
        ];
        $tickets_kpis[$key] = $labels[$key] ?? $key;
    }
    $tickets_charts = [];
    foreach (plugin_paineldebordo_charts_catalog() as $id => $meta) {
        $tickets_charts[$id] = (string) ($meta['title'] ?? $id);
    }

    $assets_kpis = [
        'assets.kpi.fleet'         => __('Park total', 'paineldebordo'),
        'assets.kpi.computers'     => __('Computers', 'paineldebordo'),
        'assets.kpi.dynamic'       => __('Dynamic inventory', 'paineldebordo'),
        'assets.kpi.with_tickets'  => __('With open tickets', 'paineldebordo'),
        'assets.kpi.warranty_30'   => __('Warranty ≤ 30 days', 'paineldebordo'),
        'assets.kpi.stale_inv'     => __('Stale inventory', 'paineldebordo'),
        'assets.kpi.disk_crit'     => __('Disks under 10% free', 'paineldebordo'),
        'assets.kpi.ram_low'       => __('Low RAM candidates', 'paineldebordo'),
        'assets.kpi.no_agent'      => __('No agent', 'paineldebordo'),
        'assets.kpi.no_loc'        => __('No location', 'paineldebordo'),
        'assets.kpi.licenses'      => __('Licenses expiring', 'paineldebordo'),
        'assets.kpi.stock'         => __('Cartridges in stock', 'paineldebordo'),
    ];
    foreach (plugin_paineldebordo_assets_types() as $itemtype => $meta) {
        $assets_kpis['assets.kpi.' . $itemtype] = (string) ($meta['label'] ?? $itemtype);
    }

    $assets_charts = [
        'assets.chart.fleet_types' => __('Park by type', 'paineldebordo'),
        'assets.chart.comp_states' => __('Computers by status', 'paineldebordo'),
        'assets.chart.comp_manuf'  => __('Computers by manufacturer', 'paineldebordo'),
        'assets.chart.os_mix'      => __('Operating systems', 'paineldebordo'),
    ];

    $assets_lists = [];
    foreach (plugin_paineldebordo_bi_assets_list_catalog() as $key => $label) {
        $assets_lists['assets.list.' . $key] = $label;
    }

    return [
        // Backward-compatible aliases (= Chamados)
        'kpis'           => $tickets_kpis,
        'charts'         => $tickets_charts,
        'tickets_kpis'   => $tickets_kpis,
        'tickets_charts' => $tickets_charts,
        'assets_kpis'    => $assets_kpis,
        'assets_charts'  => $assets_charts,
        'assets_lists'   => $assets_lists,
    ];
}

/**
 * Default BI layout (one page with KPIs + spotlight charts).
 *
 * @return array<string,mixed>
 */
function plugin_paineldebordo_bi_layout_defaults(): array
{
    $widgets = [];
    $x = 0;
    foreach (['open', 'late', 'due_24h', 'ontime', 'opened_period', 'solved_period'] as $i => $ref) {
        $widgets[] = [
            'id'   => 'k' . ($i + 1),
            'type' => 'kpi',
            'ref'  => $ref,
            'x'    => $x,
            'y'    => 0,
            'w'    => 2,
            'h'    => 2,
        ];
        $x += 2;
    }
    foreach ([['flow', 0], ['status', 4], ['aging', 8]] as $i => $row) {
        $widgets[] = [
            'id'   => 'c' . ($i + 1),
            'type' => 'chart',
            'ref'  => $row[0],
            'x'    => $row[1],
            'y'    => 2,
            'w'    => 4,
            'h'    => 4,
        ];
    }

    return [
        'version'     => 1,
        'active_page' => 'p1',
        'pages'       => [
            [
                'id'      => 'p1',
                'title'   => __('Operation', 'paineldebordo'),
                'filters' => ['period' => null],
                'widgets' => $widgets,
            ],
        ],
    ];
}

/**
 * @param string $ref
 */
function plugin_paineldebordo_bi_is_assets_ref(string $ref): bool
{
    return str_starts_with($ref, 'assets.');
}

/**
 * Build list widget payload from assets_board lists.* rows.
 *
 * @param string $key
 * @param array<int,array<string,mixed>> $rows
 * @return array{title:string,columns:array<int,string>,rows:array<int,array{cells:array<int,string>,href:?string}>}
 */
function plugin_paineldebordo_bi_assets_list_payload(string $key, array $rows): array
{
    $catalog = plugin_paineldebordo_bi_assets_list_catalog();
    $title = $catalog[$key] ?? $key;
    $columns = [];
    $out_rows = [];

    switch ($key) {
        case 'disks':
            $columns = [__('Computer', 'paineldebordo'), __('Partition', 'paineldebordo'), __('Free space', 'paineldebordo')];
            foreach ($rows as $r) {
                $id = (int) ($r['computer_id'] ?? 0);
                $free = ((string) ($r['free_pct'] ?? '')) . '% · ' . ((string) ($r['free_label'] ?? '')) . ' / ' . ((string) ($r['total_label'] ?? ''));
                $out_rows[] = [
                    'cells' => [(string) ($r['computer'] ?? '—'), (string) ($r['mount'] ?? '—'), $free],
                    'href'  => $id > 0 ? ('shell.php?page=assets&view=item&itemtype=Computer&id=' . $id . '&kind=disks') : null,
                ];
            }
            break;
        case 'ram':
            $columns = [__('Computer', 'paineldebordo'), __('RAM', 'paineldebordo')];
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                $out_rows[] = [
                    'cells' => [(string) ($r['name'] ?? '—'), (string) ($r['ram_label'] ?? '—')],
                    'href'  => $id > 0 ? ('shell.php?page=assets&view=item&itemtype=Computer&id=' . $id . '&kind=ram_low') : null,
                ];
            }
            break;
        case 'stale':
            $columns = [__('Computer', 'paineldebordo'), __('Last inventory', 'paineldebordo')];
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                $out_rows[] = [
                    'cells' => [(string) ($r['name'] ?? '—'), (string) ($r['last_inventory_update'] ?? '—')],
                    'href'  => $id > 0 ? ('shell.php?page=assets&view=item&itemtype=Computer&id=' . $id . '&kind=stale_inv') : null,
                ];
            }
            break;
        case 'warranty':
            $columns = [__('Item', 'paineldebordo'), __('Type', 'paineldebordo'), __('Expires', 'paineldebordo')];
            foreach ($rows as $r) {
                $id = (int) ($r['items_id'] ?? 0);
                $it = (string) ($r['itemtype'] ?? 'Computer');
                $out_rows[] = [
                    'cells' => [
                        (string) ($r['name'] ?? '—'),
                        (string) ($r['type_label'] ?? $it),
                        (string) ($r['expires_label'] ?? ($r['expires'] ?? '—')),
                    ],
                    'href'  => $id > 0 ? ('shell.php?page=assets&view=item&itemtype=' . rawurlencode($it) . '&id=' . $id . '&kind=warranty_30') : null,
                ];
            }
            break;
        case 'licenses':
            $columns = [__('License', 'paineldebordo'), __('Software', 'paineldebordo'), __('Expires', 'paineldebordo')];
            foreach ($rows as $r) {
                $out_rows[] = [
                    'cells' => [
                        (string) ($r['name'] ?? '—'),
                        (string) ($r['software'] ?? '—'),
                        (string) ($r['expire'] ?? '—'),
                    ],
                    'href'  => 'shell.php?page=assets&view=list&kind=licenses',
                ];
            }
            break;
        default:
            $columns = [__('Item', 'paineldebordo')];
            foreach ($rows as $r) {
                $out_rows[] = [
                    'cells' => [(string) ($r['name'] ?? json_encode($r))],
                    'href'  => null,
                ];
            }
    }

    return [
        'title'   => $title,
        'columns' => $columns,
        'rows'    => $out_rows,
        'has_data'=> $out_rows !== [],
    ];
}

/**
 * @param mixed $raw
 * @return array<string,mixed>
 */
function plugin_paineldebordo_bi_layout_normalize($raw): array
{
    $defaults = plugin_paineldebordo_bi_layout_defaults();
    if (!is_array($raw) || empty($raw['pages']) || !is_array($raw['pages'])) {
        return $defaults;
    }

    $palette = plugin_paineldebordo_bi_palette();
    $kpi_ok = $palette['kpis'] + $palette['assets_kpis'];
    $chart_ok = $palette['charts'] + $palette['assets_charts'];
    $list_ok = $palette['assets_lists'];

    $pages = [];
    foreach ($raw['pages'] as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($p['id'] ?? '')) ?: ('p' . (count($pages) + 1));
        $title = trim((string) ($p['title'] ?? ''));
        if ($title === '') {
            $title = __('Page', 'paineldebordo') . ' ' . (count($pages) + 1);
        }
        $period = $p['filters']['period'] ?? null;
        if ($period !== null && !in_array($period, ['today', '7d', 'month', 'ytd', 'all', '30d'], true)) {
            $period = null;
        }
        $widgets = [];
        if (!empty($p['widgets']) && is_array($p['widgets'])) {
            foreach ($p['widgets'] as $w) {
                if (!is_array($w)) {
                    continue;
                }
                $type = (string) ($w['type'] ?? '');
                $ref = (string) ($w['ref'] ?? '');
                if ($type === 'kpi' && !isset($kpi_ok[$ref])) {
                    continue;
                }
                if ($type === 'chart' && !isset($chart_ok[$ref])) {
                    continue;
                }
                if ($type === 'list' && !isset($list_ok[$ref])) {
                    continue;
                }
                if ($type === 'text') {
                    $ref = mb_substr($ref !== '' ? $ref : __('Title', 'paineldebordo'), 0, 120);
                } elseif ($type !== 'kpi' && $type !== 'chart' && $type !== 'list') {
                    continue;
                }
                $default_w = ($type === 'kpi') ? 2 : (($type === 'list') ? 6 : 4);
                $default_h = ($type === 'kpi') ? 2 : (($type === 'text') ? 1 : (($type === 'list') ? 5 : 4));
                $wid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($w['id'] ?? '')) ?: ('w' . (count($widgets) + 1));
                $widgets[] = [
                    'id'   => $wid,
                    'type' => $type,
                    'ref'  => $ref,
                    'x'    => max(0, min(11, (int) ($w['x'] ?? 0))),
                    'y'    => max(0, (int) ($w['y'] ?? 0)),
                    'w'    => max(1, min(12, (int) ($w['w'] ?? $default_w))),
                    'h'    => max(1, min(12, (int) ($w['h'] ?? $default_h))),
                ];
            }
        }
        $pages[] = [
            'id'      => $pid,
            'title'   => $title,
            'filters' => ['period' => $period],
            'widgets' => $widgets,
        ];
    }

    if ($pages === []) {
        return $defaults;
    }

    $active = (string) ($raw['active_page'] ?? $pages[0]['id']);
    $ids = array_column($pages, 'id');
    if (!in_array($active, $ids, true)) {
        $active = $pages[0]['id'];
    }

    return [
        'version'     => 1,
        'active_page' => $active,
        'pages'       => $pages,
    ];
}

/**
 * @return array<string,mixed>
 */
function plugin_paineldebordo_bi_layout_get(): array
{
    $raw = plugin_paineldebordo_getConfigValue('bi_layout', '');
    if ($raw === '') {
        return plugin_paineldebordo_bi_layout_defaults();
    }
    return plugin_paineldebordo_bi_layout_normalize(json_decode($raw, true));
}

/**
 * @param array<string,mixed> $layout
 * @return array<string,mixed>
 */
function plugin_paineldebordo_bi_layout_save(array $layout): array
{
    $normalized = plugin_paineldebordo_bi_layout_normalize($layout);
    plugin_paineldebordo_setConfigValue('bi_layout', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    return $normalized;
}

/**
 * @param array<string,mixed> $layout
 * @return array<string,mixed>|null
 */
function plugin_paineldebordo_bi_page(array $layout, ?string $page_id = null): ?array
{
    $page_id = $page_id ?: (string) ($layout['active_page'] ?? '');
    foreach ($layout['pages'] ?? [] as $p) {
        if (($p['id'] ?? '') === $page_id) {
            return $p;
        }
    }
    return $layout['pages'][0] ?? null;
}

/**
 * Build board payload for one page (KPIs + chart datasets + assets widgets).
 *
 * @param string|null $page_id
 * @param array<string,mixed>|null $layout_override Draft layout (unsaved client state)
 * @return array<string,mixed>
 */
function plugin_paineldebordo_bi_board(?string $page_id = null, ?array $layout_override = null): array
{
    $layout = $layout_override !== null
        ? plugin_paineldebordo_bi_layout_normalize($layout_override)
        : plugin_paineldebordo_bi_layout_get();
    if ($page_id) {
        $layout['active_page'] = $page_id;
    }
    $page = plugin_paineldebordo_bi_page($layout, $page_id);
    if ($page === null) {
        return ['ok' => false, 'error' => 'page', 'widgets' => [], 'layout' => $layout];
    }

    $needs_assets = false;
    foreach ($page['widgets'] as $w) {
        if (plugin_paineldebordo_bi_is_assets_ref((string) ($w['ref'] ?? ''))) {
            $needs_assets = true;
            break;
        }
    }

    $period_override = $page['filters']['period'] ?? null;
    $saved_period = null;
    if ($period_override && in_array($period_override, ['today', '7d', 'month', 'ytd', 'all', '30d'], true)) {
        $saved_period = plugin_paineldebordo_getConfigValue('period', 'month');
        if ($period_override === '30d') {
            $period_override = 'month';
        }
        plugin_paineldebordo_setConfigValue('period', $period_override);
    }

    try {
        $overview = plugin_paineldebordo_overview_board(false);
    } finally {
        if ($saved_period !== null) {
            plugin_paineldebordo_setConfigValue('period', $saved_period);
        }
    }

    $kpi_by = [];
    foreach ($overview['kpis'] ?? [] as $k) {
        $kpi_by[$k['key']] = $k;
    }
    $chart_by = [];
    foreach ($overview['charts'] ?? [] as $c) {
        $chart_by[$c['id']] = $c;
    }

    $assets_kpi_by = [];
    $assets_chart_by = [];
    $assets_list_by = [];
    if ($needs_assets) {
        $assets = plugin_paineldebordo_assets_board(8192);
        foreach ($assets['kpis'] ?? [] as $k) {
            $assets_kpi_by[(string) $k['key']] = $k;
        }
        foreach ($assets['tiles'] ?? [] as $t) {
            $assets_kpi_by[(string) $t['key']] = $t;
        }
        foreach ($assets['charts'] ?? [] as $c) {
            $assets_chart_by[(string) $c['id']] = $c;
        }
        $lists = $assets['lists'] ?? [];
        foreach (array_keys(plugin_paineldebordo_bi_assets_list_catalog()) as $lk) {
            $assets_list_by[$lk] = plugin_paineldebordo_bi_assets_list_payload($lk, is_array($lists[$lk] ?? null) ? $lists[$lk] : []);
        }
    }

    $widgets = [];
    foreach ($page['widgets'] as $w) {
        $item = $w;
        $item['payload'] = null;
        $ref = (string) ($w['ref'] ?? '');
        if ($w['type'] === 'kpi') {
            if (str_starts_with($ref, 'assets.kpi.')) {
                $key = substr($ref, strlen('assets.kpi.'));
                if (isset($assets_kpi_by[$key])) {
                    $item['payload'] = $assets_kpi_by[$key];
                }
            } elseif (isset($kpi_by[$ref])) {
                $item['payload'] = $kpi_by[$ref];
            }
        } elseif ($w['type'] === 'chart') {
            if (str_starts_with($ref, 'assets.chart.')) {
                $key = substr($ref, strlen('assets.chart.'));
                if (isset($assets_chart_by[$key])) {
                    $item['payload'] = $assets_chart_by[$key];
                }
            } elseif (isset($chart_by[$ref])) {
                $item['payload'] = $chart_by[$ref];
            }
        } elseif ($w['type'] === 'list' && str_starts_with($ref, 'assets.list.')) {
            $key = substr($ref, strlen('assets.list.'));
            if (isset($assets_list_by[$key])) {
                $item['payload'] = $assets_list_by[$key];
            }
        } elseif ($w['type'] === 'text') {
            $item['payload'] = ['text' => $w['ref']];
        }
        $widgets[] = $item;
    }

    return [
        'ok'           => true,
        'server_ts'    => date('Y-m-d H:i:s'),
        'layout'       => $layout,
        'page'         => $page,
        'widgets'      => $widgets,
        'period_label' => $overview['period_label'] ?? '',
        'palette'      => plugin_paineldebordo_bi_palette(),
        'draft'        => $layout_override !== null,
    ];
}
