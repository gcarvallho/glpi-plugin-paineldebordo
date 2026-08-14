<?php

/**
 * JSON board for Modo TV — open ticket queues by status (excludes solved/closed).
 * Same visibility as Chamados abertos.
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
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv.inc.php');

    $auth = plugin_paineldebordo_tv_resolve_auth();
    $warning = null;
    $wide_view = false;

    if (!$auth['ok']) {
        http_response_code(403);
        echo json_encode([
            'ok'      => false,
            'error'   => 'forbidden',
            'warning' => __('You do not have permission for Painel de Bordo.', 'paineldebordo'),
            'open'    => 0,
            'late'    => 0,
            'queues'  => [],
            'events'  => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $device = $auth['device'];
    $users_id = (int) ($_SESSION['glpiID'] ?? 0);
    if ($device !== null) {
        $scope = plugin_paineldebordo_tv_pair_scope($device);
        $wide_view = !empty($device['sees_all']);
        $users_id = (int) ($device['users_id'] ?? 0);
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

    $extras = [
        'view_mode'      => (string) ($_GET['view_mode'] ?? 'all'),
        'for_me'         => isset($_GET['view_for_me']) && $_GET['view_for_me'] === '1',
        'opened_by_me'   => isset($_GET['view_opened_by_me']) && $_GET['view_opened_by_me'] === '1',
        'by_group'       => isset($_GET['view_by_group']) && $_GET['view_by_group'] === '1',
        'by_entity'      => isset($_GET['view_by_entity']) && $_GET['view_by_entity'] === '1',
        'observer'       => isset($_GET['view_observer']) && $_GET['view_observer'] === '1',
    ];
    if (!in_array($extras['view_mode'], ['all', 'for_me', 'opened_by_me', 'my_groups'], true)) {
        $extras['view_mode'] = 'all';
    }

    $board = plugin_paineldebordo_tv_queues($scope, 40, $extras, $users_id);

    echo json_encode([
        'ok'        => true,
        'server_ts' => date('Y-m-d H:i:s'),
        'open'      => $board['open'],
        'late'      => $board['late'],
        'kpis'      => $board['kpis'] ?? [],
        'queues'    => $board['queues'],
        'events'    => [],
        'wide_view' => $wide_view,
        'warning'   => $warning,
        'catalog'   => plugin_paineldebordo_tv_event_catalog(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[tv_board] ' . $detail . "\n");
    }
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'warning' => __('TV mode connection failed', 'paineldebordo'),
        'detail'  => $detail,
        'open'    => 0,
        'late'    => 0,
        'queues'  => [],
        'events'  => [],
    ], JSON_UNESCAPED_UNICODE);
}
