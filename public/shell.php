<?php

/**
 * Painel de Bordo — single app router (zero iframe).
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}

global $DB, $CFG_GLPI;

error_reporting(E_ERROR | E_PARSE);

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/filters.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/layout.inc.php');

plugin_paineldebordo_checkAccess(READ);

$page = $_GET['page'] ?? 'home';

// Audit: presence heartbeat + one session_open per session, plus a throttled
// retention purge. Never fatal — wrapped so logging can't break navigation.
try {
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/audit.inc.php');
    plugin_paineldebordo_audit_touch((string) $page);
    plugin_paineldebordo_audit_purge();
} catch (Throwable $e) {
    // swallow — auditing must never block the app
}

// Legacy Setup → Configuration hub
if ($page === 'setup') {
    $theme = plugin_paineldebordo_getFilters()['theme'];
    header('Location: shell.php?' . http_build_query(['page' => 'config', 'theme' => $theme]));
    exit;
}

$view_map = [
    'home'      => ['title' => __('Overview', 'paineldebordo'), 'file' => 'home.php'],
    'tickets'   => ['title' => __('Open tickets', 'paineldebordo'), 'file' => 'tickets_open.php'],
    'for_me'        => ['title' => __('For me', 'paineldebordo'), 'file' => 'tickets_for_me.php'],
    'opened_by_me'  => ['title' => __('Opened by me', 'paineldebordo'), 'file' => 'tickets_opened_by_me.php'],
    'observer'      => ['title' => __('Observer', 'paineldebordo'), 'file' => 'tickets_observer.php'],
    'by_group'      => ['title' => __('By group', 'paineldebordo'), 'file' => 'tickets_by_group.php'],
    'by_entity'     => ['title' => __('By entity', 'paineldebordo'), 'file' => 'tickets_by_entity.php'],
    'charts'    => ['title' => __('Charts', 'paineldebordo'), 'file' => 'charts_hub.php'],
    'chart'     => ['title' => __('Chart', 'paineldebordo'), 'file' => 'chart_show.php'],
    'reports'   => ['title' => __('Reports', 'paineldebordo'), 'file' => 'reports_hub.php'],
    'report'    => ['title' => __('Report', 'paineldebordo'), 'file' => 'report_run.php'],
    'metrics'   => ['title' => __('BI', 'paineldebordo'), 'file' => 'bi_studio.php'],
    'map'       => ['title' => __('Map', 'paineldebordo'), 'file' => 'map.php'],
    'assets'    => ['title' => __('Assets', 'paineldebordo'), 'file' => 'assets.php'],
    'config'    => ['title' => __('Configuration', 'paineldebordo'), 'file' => 'config_hub.php'],
    'logs'      => ['title' => __('Logs & audit', 'paineldebordo'), 'file' => 'logs_hub.php'],
];

if ($page === 'tv') {
    $theme = plugin_paineldebordo_getFilters()['theme'];
    header('Location: tv.php?theme=' . rawurlencode($theme));
    exit;
}

// Configuration — Super-Admin only (canConfigure)
if ($page === 'config') {
    if (!plugin_paineldebordo_canConfigure()) {
        if (function_exists('plugin_paineldebordo_audit_log')) {
            plugin_paineldebordo_audit_log('access_denied', 'config (Super-Admin)', 'config');
        }
        Session::addMessageAfterRedirect(
            __('Only Super-Admin can open Configuration.', 'paineldebordo'),
            false,
            ERROR
        );
        $theme = plugin_paineldebordo_getFilters()['theme'];
        header('Location: shell.php?' . http_build_query(['page' => 'home', 'theme' => $theme]));
        exit;
    }
    // PRG: handle POST before any HTML (avoids CSRF Access denied on refresh)
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/branding.inc.php');
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/config_post.inc.php');
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');
        $_SESSION['hubops_config_flash'] = plugin_paineldebordo_config_hub_process_post();
        $theme = plugin_paineldebordo_getFilters()['theme'];
        header('Location: shell.php?' . http_build_query(['page' => 'config', 'theme' => $theme]));
        exit;
    }
}

// Logs & audit — Super-Admin only (canConfigure), same gate as Configuration
if ($page === 'logs') {
    if (!plugin_paineldebordo_canConfigure()) {
        if (function_exists('plugin_paineldebordo_audit_log')) {
            plugin_paineldebordo_audit_log('access_denied', 'logs (Super-Admin)', 'logs');
        }
        Session::addMessageAfterRedirect(
            __('Only Super-Admin can open Logs & audit.', 'paineldebordo'),
            false,
            ERROR
        );
        $theme = plugin_paineldebordo_getFilters()['theme'];
        header('Location: shell.php?' . http_build_query(['page' => 'home', 'theme' => $theme]));
        exit;
    }
    // PRG: retention save before any HTML (same pattern as Configuration)
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        include_once(Plugin::getPhpDir('paineldebordo') . '/inc/audit.inc.php');
        if (isset($_POST['logs_retention_save'])) {
            plugin_paineldebordo_audit_set_retention_days((int) ($_POST['logs_retention_days'] ?? 0));
            $_SESSION['hubops_config_flash'] = ['ok' => true, 'msg' => __('Retention updated.', 'paineldebordo')];
        }
        $theme = plugin_paineldebordo_getFilters()['theme'];
        header('Location: shell.php?' . http_build_query(['page' => 'logs', 'theme' => $theme]));
        exit;
    }
}

// legacy alias
if ($page === 'groups') {
    header('Location: shell.php?' . http_build_query(['page' => 'chart', 'chart' => 'groups', 'theme' => plugin_paineldebordo_getFilters()['theme']]));
    exit;
}

if (!isset($view_map[$page])) {
    $page = 'home';
}

// Module ACL (Overview/home has no module — master READ only)
$mod = plugin_paineldebordo_page_module($page);
if ($mod !== null && !plugin_paineldebordo_canModule($mod, READ)) {
    if (function_exists('plugin_paineldebordo_audit_log')) {
        plugin_paineldebordo_audit_log('access_denied', 'module: ' . $mod, $page);
    }
    Session::addMessageAfterRedirect(
        __('You do not have permission for this module.', 'paineldebordo'),
        false,
        ERROR
    );
    $theme = plugin_paineldebordo_getFilters()['theme'];
    header('Location: shell.php?' . http_build_query(['page' => 'home', 'theme' => $theme]));
    exit;
}

// CSV/PDF export must run before HTML layout (raw file output, no chrome)
if ($page === 'report' && in_array($_GET['export'] ?? '', ['csv', 'pdf'], true)) {
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/reports.php');
    include __DIR__ . '/views/report_run.php';
    exit;
}
if ($page === 'logs' && in_array($_GET['export'] ?? '', ['csv', 'pdf'], true)) {
    include __DIR__ . '/views/logs_export.php';
    exit;
}

$meta = $view_map[$page];
plugin_paineldebordo_page_start([
    'title'  => $meta['title'],
    'active' => in_array($page, ['chart'], true) ? 'charts' : (in_array($page, ['report'], true) ? 'reports' : $page),
]);

$view_file = __DIR__ . '/views/' . $meta['file'];
if (is_file($view_file)) {
    include $view_file;
} else {
    echo '<div class="card"><div class="card-body">' . htmlspecialchars(__('View not found', 'paineldebordo')) . '</div></div>';
}

plugin_paineldebordo_page_end();
