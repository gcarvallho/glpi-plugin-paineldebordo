<?php

/**
 * Access helpers for Painel de Bordo.
 *
 * Rights:
 * - plugin_paineldebordo READ → enter plugin (Overview + TV)
 * - plugin_paineldebordo_groups READ → wide group visibility (tickets/TV/filters)
 * - plugin_paineldebordo_tickets|analysis|resources → module ACL
 * - Admin / Configuration → Super-Admin only (strict)
 */

if (!defined('PLUGIN_PAINELDEBORDO_RIGHT')) {
    define('PLUGIN_PAINELDEBORDO_RIGHT', 'plugin_paineldebordo');
}

if (!defined('PLUGIN_PAINELDEBORDO_RIGHT_GROUPS')) {
    define('PLUGIN_PAINELDEBORDO_RIGHT_GROUPS', 'plugin_paineldebordo_groups');
}
if (!defined('PLUGIN_PAINELDEBORDO_RIGHT_TICKETS')) {
    define('PLUGIN_PAINELDEBORDO_RIGHT_TICKETS', 'plugin_paineldebordo_tickets');
}
if (!defined('PLUGIN_PAINELDEBORDO_RIGHT_ANALYSIS')) {
    define('PLUGIN_PAINELDEBORDO_RIGHT_ANALYSIS', 'plugin_paineldebordo_analysis');
}
if (!defined('PLUGIN_PAINELDEBORDO_RIGHT_RESOURCES')) {
    define('PLUGIN_PAINELDEBORDO_RIGHT_RESOURCES', 'plugin_paineldebordo_resources');
}

/**
 * @return array<string,string> module id => ProfileRight name
 */
function plugin_paineldebordo_module_rights(): array
{
    return [
        'tickets'   => PLUGIN_PAINELDEBORDO_RIGHT_TICKETS,
        'analysis'  => PLUGIN_PAINELDEBORDO_RIGHT_ANALYSIS,
        'resources' => PLUGIN_PAINELDEBORDO_RIGHT_RESOURCES,
    ];
}

/**
 * Require login + plugin READ (or UPDATE for legacy callers).
 */
function plugin_paineldebordo_checkAccess(int $right = READ): void
{
    Session::checkLoginUser();
    Session::checkRight(PLUGIN_PAINELDEBORDO_RIGHT, $right);
}

function plugin_paineldebordo_canRead(): bool
{
    return Session::haveRight(PLUGIN_PAINELDEBORDO_RIGHT, READ);
}

/**
 * JSON-safe equivalent of checkAccess() for AJAX endpoints.
 * Session::checkRight() renders a full HTML error page and dies, which breaks
 * JSON consumers — this returns a JSON 403 instead (matches requireModuleJson()).
 */
function plugin_paineldebordo_checkAccessJson(int $right = READ): void
{
    Session::checkLoginUser();
    if (!Session::haveRight(PLUGIN_PAINELDEBORDO_RIGHT, $right)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden', 'detail' => 'read']);
        exit;
    }
}

/**
 * Super-Admin only (Admin menu, Configuration, branding, destructive TV cleanup).
 */
function plugin_paineldebordo_isSuperAdminStrict(): bool
{
    $name = (string) ($_SESSION['glpiactiveprofile']['name'] ?? '');
    if ($name === 'Super-Admin' || $name === 'Super-Administrador') {
        return true;
    }
    return Session::haveRight('config', UPDATE);
}

/**
 * @deprecated Use isSuperAdminStrict — kept as alias for existing call sites.
 */
function plugin_paineldebordo_isSuperAdmin(): bool
{
    return plugin_paineldebordo_isSuperAdminStrict();
}

/**
 * Open Configuration / Admin menu — Super-Admin only.
 */
function plugin_paineldebordo_canConfigure(): bool
{
    return plugin_paineldebordo_isSuperAdminStrict();
}

/**
 * Wide ticket/TV group vision: explicit groups right or Super-Admin.
 * Master plugin UPDATE does NOT grant this (least privilege).
 */
function plugin_paineldebordo_hasWideVision(): bool
{
    if (plugin_paineldebordo_isSuperAdminStrict()) {
        return true;
    }
    return Session::haveRight(PLUGIN_PAINELDEBORDO_RIGHT_GROUPS, READ);
}

/**
 * Module ACL. Missing session key → deny (rows ensured by migrate).
 */
function plugin_paineldebordo_canModule(string $module, int $bit = READ): bool
{
    if (!plugin_paineldebordo_canRead()) {
        return false;
    }
    $map = plugin_paineldebordo_module_rights();
    if (!isset($map[$module])) {
        return false;
    }
    $name = $map[$module];
    if (!isset($_SESSION['glpiactiveprofile'][$name])) {
        return false;
    }
    return Session::haveRight($name, $bit);
}

/**
 * Hard check for pages (HTML). Prefer shell redirect for UX.
 */
function plugin_paineldebordo_checkModule(string $module, int $bit = READ): void
{
    Session::checkLoginUser();
    if (!plugin_paineldebordo_canModule($module, $bit)) {
        Session::checkRight(PLUGIN_PAINELDEBORDO_RIGHT, READ);
        Html::displayRightError();
        exit;
    }
}

/**
 * JSON 403 for AJAX when module right is missing.
 */
function plugin_paineldebordo_requireModuleJson(string $module, int $bit = READ): void
{
    Session::checkLoginUser();
    if (!plugin_paineldebordo_canRead()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden', 'detail' => 'read']);
        exit;
    }
    if (!plugin_paineldebordo_canModule($module, $bit)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden', 'detail' => $module]);
        exit;
    }
}

/**
 * Shell page → module id (null = Overview/TV — master READ only).
 */
function plugin_paineldebordo_page_module(string $page): ?string
{
    return match ($page) {
        'tickets', 'by_group', 'by_entity', 'for_me', 'opened_by_me', 'observer' => 'tickets',
        'charts', 'chart', 'reports', 'report', 'metrics' => 'analysis',
        'map', 'assets' => 'resources',
        'config' => null,
        default => null,
    };
}

/**
 * Alias: wide group list / filter across all entity groups.
 */
function plugin_paineldebordo_seesAllGroups(): bool
{
    return plugin_paineldebordo_hasWideVision();
}

/**
 * @return int[]
 */
function plugin_paineldebordo_getUserGroupIds(): array
{
    if (!empty($_SESSION['glpigroups']) && is_array($_SESSION['glpigroups'])) {
        return array_map('intval', array_values($_SESSION['glpigroups']));
    }

    global $DB;
    $ids = [];
    if (!isset($_SESSION['glpiID'])) {
        return $ids;
    }

    $iterator = $DB->request([
        'SELECT' => ['groups_id'],
        'FROM'   => 'glpi_groups_users',
        'WHERE'  => ['users_id' => (int) $_SESSION['glpiID']],
    ]);

    foreach ($iterator as $row) {
        $ids[] = (int) $row['groups_id'];
    }

    return array_values(array_unique($ids));
}

/**
 * SQL fragment: AND groups_id IN (...) for membership-limited users.
 */
function plugin_paineldebordo_sqlGroupScope(string $column = 'glpi_groups.id'): string
{
    if (plugin_paineldebordo_seesAllGroups()) {
        return '';
    }

    $ids = plugin_paineldebordo_getUserGroupIds();
    if ($ids === []) {
        return ' AND 0=1 ';
    }

    return ' AND ' . $column . ' IN (' . implode(',', $ids) . ') ';
}

/**
 * Ensure $group_id is allowed for current user.
 */
function plugin_paineldebordo_assertGroupAccess(int $group_id): void
{
    if ($group_id <= 0) {
        return;
    }

    if (plugin_paineldebordo_seesAllGroups()) {
        return;
    }

    $allowed = plugin_paineldebordo_getUserGroupIds();
    if (!in_array($group_id, $allowed, true)) {
        plugin_paineldebordo_setConfigValue('filter_group', '0');
    }
}
