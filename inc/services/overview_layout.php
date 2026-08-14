<?php

/**
 * Overview mural personalization — per-user dashboard_layout config.
 */

include_once dirname(__DIR__) . '/filters.inc.php';
include_once __DIR__ . '/charts.php';

/**
 * Canonical KPI keys in default order.
 *
 * @return list<string>
 */
function plugin_paineldebordo_overview_kpi_keys(): array
{
    return [
        'opened_period', 'solved_period', 'balance', 'requesters',
        'open', 'late', 'due_24h', 'ontime', 'validation', 'solution',
    ];
}

/**
 * Default mural layout (everything visible, current NOC order).
 *
 * @return array<string,mixed>
 */
function plugin_paineldebordo_overview_layout_defaults(): array
{
    $kpi_keys = plugin_paineldebordo_overview_kpi_keys();
    $kpis = [];
    foreach ($kpi_keys as $k) {
        $kpis[$k] = true;
    }

    $chart_ids = [];
    if (function_exists('plugin_paineldebordo_charts_catalog')) {
        $chart_ids = array_keys(plugin_paineldebordo_charts_catalog());
    } else {
        $chart_ids = [
            'status', 'priority', 'evolution', 'flow', 'aging', 'breach',
            'groups', 'tech', 'category', 'entity', 'sla', 'type', 'source', 'location', 'requester',
        ];
    }
    $charts = [];
    foreach ($chart_ids as $id) {
        $charts[$id] = true;
    }

    return [
        'version'     => 1,
        'kpis'        => $kpis,
        'kpi_order'   => $kpi_keys,
        'sections'    => [
            'queues'    => true,
            'oldest'    => true,
            'shortcuts' => true,
        ],
        'charts'      => $charts,
        'chart_order' => $chart_ids,
        'hero'        => 'evolution',
        'spotlight'   => ['flow', 'status', 'aging'],
    ];
}

/**
 * Normalize / migrate a raw layout array against defaults.
 *
 * @param mixed $raw
 * @return array<string,mixed>
 */
function plugin_paineldebordo_overview_layout_normalize($raw): array
{
    $defaults = plugin_paineldebordo_overview_layout_defaults();
    if (!is_array($raw)) {
        return $defaults;
    }

    $out = $defaults;

    if (isset($raw['kpis']) && is_array($raw['kpis'])) {
        foreach ($defaults['kpis'] as $k => $_) {
            if (array_key_exists($k, $raw['kpis'])) {
                $out['kpis'][$k] = (bool) $raw['kpis'][$k];
            }
        }
    }

    if (isset($raw['kpi_order']) && is_array($raw['kpi_order'])) {
        $out['kpi_order'] = plugin_paineldebordo_overview_layout_order(
            $raw['kpi_order'],
            array_keys($defaults['kpis'])
        );
    }

    if (isset($raw['sections']) && is_array($raw['sections'])) {
        foreach ($defaults['sections'] as $k => $_) {
            if (array_key_exists($k, $raw['sections'])) {
                $out['sections'][$k] = (bool) $raw['sections'][$k];
            }
        }
    }

    if (isset($raw['charts']) && is_array($raw['charts'])) {
        foreach ($defaults['charts'] as $k => $_) {
            if (array_key_exists($k, $raw['charts'])) {
                $out['charts'][$k] = (bool) $raw['charts'][$k];
            }
        }
    }

    if (isset($raw['chart_order']) && is_array($raw['chart_order'])) {
        $out['chart_order'] = plugin_paineldebordo_overview_layout_order(
            $raw['chart_order'],
            array_keys($defaults['charts'])
        );
    }

    $hero = isset($raw['hero']) ? (string) $raw['hero'] : $defaults['hero'];
    if (!isset($defaults['charts'][$hero])) {
        $hero = $defaults['hero'];
    }
    $out['hero'] = $hero;

    $spot = [];
    if (isset($raw['spotlight']) && is_array($raw['spotlight'])) {
        foreach ($raw['spotlight'] as $id) {
            $id = (string) $id;
            if (isset($defaults['charts'][$id]) && !in_array($id, $spot, true)) {
                $spot[] = $id;
            }
            if (count($spot) >= 3) {
                break;
            }
        }
    }
    if ($spot === []) {
        $spot = $defaults['spotlight'];
    }
    $out['spotlight'] = $spot;
    $out['version'] = 1;

    return $out;
}

/**
 * Stable order: preferred ids first (unique + known), then remaining known ids.
 *
 * @param list<mixed> $preferred
 * @param list<string> $known
 * @return list<string>
 */
function plugin_paineldebordo_overview_layout_order(array $preferred, array $known): array
{
    $known_flip = array_flip($known);
    $out = [];
    foreach ($preferred as $id) {
        $id = (string) $id;
        if (isset($known_flip[$id]) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }
    foreach ($known as $id) {
        if (!in_array($id, $out, true)) {
            $out[] = $id;
        }
    }
    return $out;
}

/**
 * Load current user's mural layout.
 *
 * @return array<string,mixed>
 */
function plugin_paineldebordo_overview_layout_get(): array
{
    $raw = plugin_paineldebordo_getConfigValue('dashboard_layout', '');
    if ($raw === '') {
        return plugin_paineldebordo_overview_layout_defaults();
    }
    $decoded = json_decode($raw, true);
    return plugin_paineldebordo_overview_layout_normalize($decoded);
}

/**
 * Persist mural layout for current user.
 *
 * @param array<string,mixed> $layout
 */
function plugin_paineldebordo_overview_layout_save(array $layout): array
{
    $normalized = plugin_paineldebordo_overview_layout_normalize($layout);
    plugin_paineldebordo_setConfigValue('dashboard_layout', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    return $normalized;
}

/**
 * Apply layout visibility + order to a board payload.
 *
 * @param array<string,mixed> $board
 * @param array<string,mixed>|null $layout
 * @return array<string,mixed>
 */
function plugin_paineldebordo_overview_layout_apply(array $board, ?array $layout = null): array
{
    $layout = $layout ?? plugin_paineldebordo_overview_layout_get();
    $layout = plugin_paineldebordo_overview_layout_normalize($layout);

    $kpis_by = [];
    foreach ($board['kpis'] ?? [] as $k) {
        if (isset($k['key'])) {
            $kpis_by[$k['key']] = $k;
        }
    }
    $kpis = [];
    foreach ($layout['kpi_order'] as $key) {
        if (!empty($layout['kpis'][$key]) && isset($kpis_by[$key])) {
            $kpis[] = $kpis_by[$key];
        }
    }
    $board['kpis'] = $kpis;

    if (empty($layout['sections']['oldest'])) {
        $board['oldest'] = null;
    }
    if (empty($layout['sections']['queues'])) {
        $board['queues'] = [];
    }

    $charts_by = [];
    foreach ($board['charts'] ?? [] as $c) {
        if (isset($c['id'])) {
            $charts_by[$c['id']] = $c;
        }
    }
    $charts = [];
    foreach ($layout['chart_order'] as $id) {
        if (!empty($layout['charts'][$id]) && isset($charts_by[$id])) {
            $charts[] = $charts_by[$id];
        }
    }
    $board['charts'] = $charts;
    $board['layout'] = $layout;
    $board['show_shortcuts'] = !empty($layout['sections']['shortcuts']);
    $board['hero_id'] = $layout['hero'];
    $board['spotlight_ids'] = $layout['spotlight'];

    return $board;
}

/**
 * Labels for customize UI (msgid → translated).
 *
 * @return array{kpis: array<string,string>, sections: array<string,string>, charts: array<string,string>}
 */
function plugin_paineldebordo_overview_layout_labels(): array
{
    $kpi_labels = [
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
    $sections = [
        'queues'    => __('Queues', 'paineldebordo'),
        'oldest'    => __('Oldest ticket', 'paineldebordo'),
        'shortcuts' => __('Shortcuts', 'paineldebordo'),
    ];
    $charts = [];
    if (function_exists('plugin_paineldebordo_charts_catalog')) {
        foreach (plugin_paineldebordo_charts_catalog() as $id => $meta) {
            $charts[$id] = (string) ($meta['title'] ?? $id);
        }
    }
    return [
        'kpis'     => $kpi_labels,
        'sections' => $sections,
        'charts'   => $charts,
    ];
}
