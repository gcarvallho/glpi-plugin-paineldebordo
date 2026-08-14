<?php

/**
 * Inline SVG icons (Tabler-style strokes) for Painel de Bordo chrome.
 * No CDN / no Font Awesome dependency in the modern shell.
 */

/**
 * Raw SVG path/markup keyed by icon id (24x24 viewBox).
 *
 * @return array<string,string>
 */
function plugin_paineldebordo_icon_paths(): array
{
    // stroke icons — single path or grouped paths inside viewBox 0 0 24 24
    return [
        'home'      => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-8"/>',
        'glpi_home' => '<path d="M5 12l7-7 7 7"/><path d="M19 12v7a1 1 0 01-1 1h-3v-5H9v5H6a1 1 0 01-1-1v-7"/>',
        'tv'        => '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'tickets'   => '<path d="M4 7a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 00-2 2 2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2a2 2 0 012-2 2 2 0 00-2-2V7z"/><path d="M9 12h6"/>',
        'by_group'  => '<circle cx="9" cy="8" r="3"/><circle cx="16" cy="9" r="2.5"/><path d="M4 19c1.5-3 4-4.5 5-4.5S12.5 16 14 19M14 19c.5-2 2-3.5 2-3.5s2 1 3 3.5"/>',
        'by_entity' => '<path d="M4 20V6l8-3 8 3v14"/><path d="M9 20v-6h6v6"/><path d="M9 10h6"/>',
        'charts'    => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-8"/>',
        'reports'   => '<path d="M7 4h8l4 4v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/><path d="M15 4v4h4"/><path d="M9 13h6M9 17h6"/>',
        'metrics'   => '<path d="M4 19h16"/><path d="M7 16l3-6 3 3 4-8"/>',
        'map'       => '<path d="M9 4l-5 2v14l5-2 6 2 5-2V4l-5 2-6-2z"/><path d="M9 4v14M15 6v14"/>',
        // Recursos: racks / inventário (mapa+ativos) — não confundir com engrenagem
        'resources' => '<rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/><path d="M7 7h.01M7 17h.01M11 7h.01M11 17h.01"/>',
        'assets'    => '<rect x="4" y="5" width="16" height="12" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M8 9h.01M12 9h.01"/>',
        'setup'     => '<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'layout_side' => '<rect x="3" y="4" width="6" height="16" rx="1"/><rect x="11" y="4" width="10" height="16" rx="1"/>',
        'layout_top'  => '<rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="11" width="18" height="9" rx="1"/>',
        'nav_collapse'=> '<rect x="3" y="4" width="6" height="16" rx="1"/><path d="M14 12h7M17 9l-3 3 3 3"/>',
        'nav_expand'  => '<rect x="3" y="4" width="6" height="16" rx="1"/><path d="M10 12h7M14 9l3 3-3 3"/>',
        'palette'     => '<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 22a8 8 0 01-8-8c0-5 4-8 8-8a2 2 0 012 2v1a2 2 0 002 2h1a8 8 0 01-5 11z"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M3 12h2M19 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>',
        'chevron_down' => '<path d="M6 9l6 6 6-6"/>',
        'today'     => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 11h16"/>',
        'month'     => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 11h16M9 15h2M13 15h2"/>',
        'year'      => '<path d="M8 6h8M12 6v12M8 18h8"/>',
        'late'      => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
        'backlog'   => '<path d="M5 6h14M5 12h14M5 18h10"/>',
        'users'     => '<circle cx="9" cy="8" r="3"/><circle cx="16" cy="9" r="2.5"/><path d="M4 19c1.5-3 4-4.5 5-4.5S12.5 16 14 19"/>',
        'user'      => '<circle cx="12" cy="8" r="3.5"/><path d="M5 19c1.8-3.5 4.5-5 7-5s5.2 1.5 7 5"/>',
        'computer'  => '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'monitor'   => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'network'   => '<circle cx="12" cy="6" r="2"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M12 8v4M12 12l-4 4M12 12l4 4"/>',
        'printer'   => '<path d="M7 8V4h10v4"/><rect x="5" y="8" width="14" height="8" rx="2"/><path d="M7 16h10v4H7z"/>',
        'phone'     => '<rect x="8" y="3" width="8" height="18" rx="2"/><path d="M11 17h2"/>',
        'peripheral'=> '<rect x="4" y="7" width="16" height="10" rx="2"/><path d="M8 17v3M16 17v3M9 12h6"/>',
        'chart_pie' => '<path d="M12 3a9 9 0 109 9h-9V3z"/><path d="M14 3.2A9 9 0 0120.8 10H14V3.2z"/>',
        'chart_bar' => '<path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-8"/>',
        'chart_line'=> '<path d="M4 19h16M4 15l5-5 4 3 6-7"/>',
        'doc'       => '<path d="M7 4h8l4 4v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/><path d="M15 4v4h4"/>',
        // Real gear (not sun rays) — must stay visually distinct from sun/setup
        'cog'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82-.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82-.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9c.26.604.852 1.02 1.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
        'opened'    => '<path d="M12 5v14M5 12h14"/>',
        'solved'    => '<path d="M5 12l4 4L19 6"/>',
        'percent'   => '<circle cx="8" cy="8" r="2.5"/><circle cx="16" cy="16" r="2.5"/><path d="M7 17L17 7"/>',
        'list'      => '<path d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01"/>',
        'volume'    => '<path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M15.5 8.5a4 4 0 010 7"/><path d="M18 6a7 7 0 010 12"/>',
        'volume_off'=> '<path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M22 9l-6 6M16 9l6 6"/>',
        'fullscreen'=> '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
        'external_link'=> '<path d="M14 4h6v6M10 14L20 4M20 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1h5"/>',
        'exit'      => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'hash'      => '<path d="M5 9h14M5 15h14M10 3L8 21M16 3l-2 18"/>',
        'calendar'  => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 11h16"/>',
        'refresh'   => '<path d="M21 12a9 9 0 11-2.6-6.4"/><path d="M21 3v6h-6"/>',
        'sort'      => '<path d="M7 4v16M7 20l-3-3M7 20l3-3M13 6h8M13 12h6M13 18h4"/>',
        'eye'       => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'title'     => '<path d="M5 7h14M12 7v10M9 17h6"/>',
        'columns'   => '<rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="16" rx="1"/><rect x="17" y="4" width="4" height="16" rx="1"/>',
        'bell'      => '<path d="M6 8a6 6 0 0112 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 20a2 2 0 004 0"/>',
        'density'   => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'audio'     => '<path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M15.5 8.5a4 4 0 010 7"/>',
        'prio'      => '<path d="M12 3l2.5 7.5H22l-6 4.5 2.5 7.5L12 18l-6.5 4.5L8 15 2 10.5h7.5z"/>',
        'observer'  => '<circle cx="9" cy="8" r="3"/><path d="M3 19c1.5-3 4-4.5 6-4.5s4.5 1.5 6 4.5"/><path d="M16 11l2 2 4-4"/>',
        'copy'      => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 012-2h10"/>',
        'trash'     => '<path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12"/>',
        'ban'       => '<circle cx="12" cy="12" r="9"/><path d="M5.5 5.5l13 13"/>',
        'back'      => '<path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/>',
        'forward'   => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
        'filter'    => '<path d="M4 4h16l-6 8v6l-4 2v-8z"/>',
        'headset'   => '<path d="M4 13a8 8 0 0116 0"/><rect x="3" y="13" width="4" height="7" rx="2"/><rect x="17" y="13" width="4" height="7" rx="2"/><path d="M19 20v.5a2.5 2.5 0 01-2.5 2.5H15"/>',
        'download'  => '<path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/><path d="M7 11l5 5 5-5"/><path d="M12 4v12"/>',
        'logs'      => '<path d="M9 5h9a1 1 0 011 1v13a1 1 0 01-1 1H6a1 1 0 01-1-1V6a1 1 0 011-1h1"/><rect x="8" y="3" width="6" height="4" rx="1"/><path d="M8 11h6M8 15h4"/><circle cx="17.5" cy="16.5" r="3.5"/><path d="M17.5 15v1.6l1 1"/>',
    ];
}

/**
 * Map nav / KPI / asset keys to icon ids.
 * Known path keys resolve to themselves — never silently fall back to "doc".
 */
function plugin_paineldebordo_icon_alias(string $id): string
{
    static $map = [
        'home' => 'home', 'glpi_home' => 'glpi_home', 'tv' => 'tv', 'tv_pair' => 'tv', 'tv_mode' => 'tv', 'tickets' => 'tickets',
        'by_group' => 'by_group', 'by_entity' => 'by_entity', 'for_me' => 'user',
        'opened_by_me' => 'opened',
        'charts' => 'charts', 'reports' => 'reports', 'metrics' => 'metrics',
        'map' => 'map', 'assets' => 'assets',
        'setup' => 'cog', 'config' => 'cog',
        'cog' => 'cog', 'logs' => 'logs', 'audit' => 'logs',
        'analysis' => 'charts', 'resources' => 'resources', 'admin' => 'cog',
        'tickets_group' => 'tickets',
        'layout_side' => 'layout_side', 'layout_top' => 'layout_top',
        'nav_collapse' => 'nav_collapse', 'nav_expand' => 'nav_expand',
        'palette' => 'palette', 'branding' => 'palette',
        'sun' => 'sun', 'moon' => 'moon', 'chevron_down' => 'chevron_down',
        'today' => 'today', 'month' => 'month', 'year' => 'year',
        'late' => 'late', 'backlog' => 'backlog', 'users' => 'users', 'user' => 'user',
        'requesters' => 'users',
        'Computer' => 'computer', 'Monitor' => 'monitor',
        'NetworkEquipment' => 'network', 'Printer' => 'printer',
        'Phone' => 'phone', 'Peripheral' => 'peripheral',
        'status' => 'chart_pie', 'type' => 'list', 'sla' => 'late',
        'evolution' => 'chart_line', 'tech' => 'headset', 'group' => 'by_group',
        'entity' => 'by_entity', 'category' => 'list', 'location' => 'map',
        'priority' => 'prio', 'source' => 'opened', 'requester' => 'user',
        'volume' => 'volume', 'volume_off' => 'volume_off',
        'fullscreen' => 'fullscreen', 'exit' => 'exit',
        'external_link' => 'external_link', 'open' => 'external_link', 'eye' => 'eye',
        'settings' => 'cog', 'gear' => 'cog',
        'hash' => 'hash', 'id' => 'hash', 'calendar' => 'calendar', 'date' => 'calendar',
        'refresh' => 'refresh', 'updated' => 'refresh', 'sort' => 'sort',
        'title' => 'title', 'columns' => 'columns', 'bell' => 'bell', 'toasts' => 'bell',
        'density' => 'density', 'audio' => 'audio', 'prio' => 'prio',
        'observer' => 'observer', 'observers' => 'observer', 'kpis' => 'chart_bar',
        'copy' => 'copy', 'trash' => 'trash', 'delete' => 'trash', 'ban' => 'ban', 'revoke' => 'ban',
        'metrics' => 'charts', 'bi' => 'charts',
        'validation' => 'solved', 'solution' => 'solved', 'opened' => 'opened',
        'flow' => 'chart_bar', 'aging' => 'late', 'breach' => 'late',
        'opened_period' => 'opened', 'solved_period' => 'solved', 'balance' => 'chart_line',
        'due_24h' => 'late', 'ontime' => 'percent', 'queues' => 'columns',
        'oldest' => 'sort', 'shortcuts' => 'external_link', 'sections' => 'columns',
        'roles' => 'layout_side', 'hero' => 'fullscreen', 'chart_bar' => 'chart_bar',
        'groups' => 'by_group',
        // Reports catalog (docs/services/reports.php) — every id maps to a real icon.
        'open_list' => 'backlog', 'tickets_period' => 'calendar', 'by_status' => 'chart_pie', 'by_priority' => 'prio',
        'by_requester' => 'user', 'by_tech_detail' => 'headset', 'by_group_detail' => 'by_group',
        'group_tech' => 'headset', 'group_requester' => 'user', 'by_tech' => 'headset',
        'by_entity_detail' => 'by_entity', 'by_category_tree' => 'list', 'by_location' => 'map',
        'by_date' => 'calendar', 'by_category' => 'list',
        'sla_late' => 'late', 'sla_due' => 'late', 'sla_by_policy' => 'percent', 'sla_tto' => 'late',
        'sla_ttr' => 'late', 'ola_tto' => 'late', 'ola_ttr' => 'late', 'breach_radar' => 'late',
        'csat_detail' => 'percent', 'csat_vs_sla' => 'percent',
        'cost_by_tech' => 'chart_bar', 'cost_by_requester' => 'chart_bar', 'cost_by_location' => 'chart_bar', 'cost_by_entity' => 'chart_bar',
        'tasks_list' => 'list', 'tasks_by_ticket' => 'list', 'tasks_by_group' => 'by_group',
        'tasks_by_req' => 'user', 'tasks_by_ent' => 'by_entity',
        'assets_summary' => 'assets',
        'projects' => 'columns', 'project_tasks' => 'list',
        'synth_overview' => 'chart_bar', 'synth_by_entity' => 'by_entity', 'synth_by_tech' => 'headset',
        'synth_by_requester' => 'user', 'synth_by_group' => 'by_group',
        'aging_buckets' => 'late', 'flow_open_vs_solved' => 'chart_line', 'first_response_risk' => 'late',
        'reopen_rate' => 'refresh', 'workload_fairness' => 'by_group', 'hot_categories' => 'list',
    ];
    if (isset($map[$id])) {
        return $map[$id];
    }
    // Identity for any path that exists — prevents accidental "doc"/paper icons
    static $pathKeys = null;
    if ($pathKeys === null) {
        $pathKeys = array_fill_keys(array_keys(plugin_paineldebordo_icon_paths()), true);
    }
    if (isset($pathKeys[$id])) {
        return $id;
    }
    return 'doc';
}

/**
 * Render an inline SVG icon.
 *
 * @param string $id Icon or alias id
 * @param string $extraClass Extra CSS classes
 */
function plugin_paineldebordo_icon(string $id, string $extraClass = ''): string
{
    $key = plugin_paineldebordo_icon_alias($id);
    $paths = plugin_paineldebordo_icon_paths();
    $inner = $paths[$key] ?? $paths['doc'];
    $cls = trim('ho-icon ' . $extraClass);
    return '<svg class="' . htmlspecialchars($cls) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $inner . '</svg>';
}
