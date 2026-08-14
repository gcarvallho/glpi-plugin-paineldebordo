<?php

/**
 * Shared global filters for Painel de Bordo (entity, period, status, group, theme).
 */

function plugin_paineldebordo_getConfigValue(string $name, string $default = ''): string
{
    global $DB;

    if (!isset($_SESSION['glpiID'])) {
        return $default;
    }

    $result = $DB->doQuery(
        "SELECT value FROM glpi_plugin_paineldebordo_config
         WHERE name = '" . $DB->escape($name) . "'
           AND users_id = " . (int) $_SESSION['glpiID']
    );

    if ($result && $DB->numrows($result) > 0) {
        $value = (string) $DB->result($result, 0, 'value');
        return $value !== '' ? $value : $default;
    }

    return $default;
}

function plugin_paineldebordo_setConfigValue(string $name, string $value): void
{
    global $DB;

    if (!isset($_SESSION['glpiID'])) {
        return;
    }

    $uid = (int) $_SESSION['glpiID'];
    $name_esc = $DB->escape($name);
    $value_esc = $DB->escape($value);

    $exists = $DB->doQuery(
        "SELECT id FROM glpi_plugin_paineldebordo_config WHERE name = '$name_esc' AND users_id = $uid"
    );

    if ($exists && $DB->numrows($exists) > 0) {
        $DB->doQuery(
            "UPDATE glpi_plugin_paineldebordo_config SET value = '$value_esc' WHERE name = '$name_esc' AND users_id = $uid"
        );
    } else {
        $DB->doQuery(
            "INSERT INTO glpi_plugin_paineldebordo_config (name, value, users_id) VALUES ('$name_esc', '$value_esc', '$uid')"
        );
    }
}

function plugin_paineldebordo_configExists(string $name): bool
{
    global $DB;

    if (!isset($_SESSION['glpiID'])) {
        return false;
    }

    $result = $DB->doQuery(
        "SELECT id FROM glpi_plugin_paineldebordo_config
         WHERE name = '" . $DB->escape($name) . "'
           AND users_id = " . (int) $_SESSION['glpiID'] . "
         LIMIT 1"
    );

    return $result && $DB->numrows($result) > 0;
}

/**
 * First visit: seed entity/group filters from GLPI session/membership (ACL-safe).
 * Does not overwrite an already saved preference.
 */
function plugin_paineldebordo_applyDefaultFilters(): void
{
    if (!isset($_SESSION['glpiID'])) {
        return;
    }

    if (!function_exists('plugin_paineldebordo_entity_options')) {
        include_once __DIR__ . '/services/tickets.php';
    }
    $entities = plugin_paineldebordo_entity_options();
    if (count($entities) === 1) {
        $only = (int) array_key_first($entities);
        plugin_paineldebordo_setConfigValue('filter_entity_focus', (string) $only);
    } elseif (!plugin_paineldebordo_configExists('filter_entity_focus')) {
        $active = (int) ($_SESSION['glpiactive_entity'] ?? -1);
        $allowed = array_map('intval', (array) ($_SESSION['glpiactiveentities'] ?? []));
        if ($active >= 0 && (isset($_SESSION['glpiactiveentities'][$active]) || in_array($active, $allowed, true))) {
            plugin_paineldebordo_setConfigValue('filter_entity_focus', (string) $active);
        }
    }

    if (!plugin_paineldebordo_configExists('filter_group')) {
        $options = plugin_paineldebordo_getGroupOptions();
        $default = 0;
        foreach (plugin_paineldebordo_getUserGroupIds() as $gid) {
            $gid = (int) $gid;
            if ($gid > 0 && isset($options[$gid])) {
                $default = $gid;
                break;
            }
        }
        plugin_paineldebordo_setConfigValue('filter_group', (string) $default);
    }
}

/**
 * Human label for period filter keys (today / 7d / month / ytd / all).
 */
function plugin_paineldebordo_period_label(string $period): string
{
    return match ($period) {
        'today' => __('Today', 'paineldebordo'),
        '7d'    => __('7 days', 'paineldebordo'),
        'month', '30d' => __('Month', 'paineldebordo'),
        'ytd'   => __('Year', 'paineldebordo'),
        'all'   => __('Overall', 'paineldebordo'),
        default => $period,
    };
}

/**
 * Normalize legacy period keys (30d → month).
 */
function plugin_paineldebordo_normalize_period(string $period): string
{
    if ($period === '30d' || $period === 'custom') {
        return 'month';
    }
    if (!in_array($period, ['today', '7d', 'month', 'ytd', 'all'], true)) {
        return 'month';
    }
    return $period;
}

/**
 * Persist theme + filter overrides from request.
 */
function plugin_paineldebordo_syncFiltersFromRequest(): void
{
    if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'], true)) {
        plugin_paineldebordo_setConfigValue('ui_theme', $_GET['theme']);
        $_SESSION['hubops_theme'] = $_GET['theme'];
    }

    if (isset($_GET['period'])) {
        $p = plugin_paineldebordo_normalize_period((string) $_GET['period']);
        if (in_array((string) $_GET['period'], ['today', '7d', '30d', 'month', 'ytd', 'all', 'custom'], true)) {
            plugin_paineldebordo_setConfigValue('period', $p);
        }
    }

    if (isset($_GET['sel_grp'])) {
        $gid = (int) $_GET['sel_grp'];
        if ($gid > 0
            && !plugin_paineldebordo_seesAllGroups()
            && !in_array($gid, plugin_paineldebordo_getUserGroupIds(), true)
        ) {
            $gid = 0;
        }
        plugin_paineldebordo_setConfigValue('filter_group', (string) $gid);
    }

    if (isset($_GET['sel_ent'])) {
        $eid = (int) $_GET['sel_ent'];
        if (!function_exists('plugin_paineldebordo_entity_options')) {
            include_once __DIR__ . '/services/tickets.php';
        }
        $entities = plugin_paineldebordo_entity_options();
        if (count($entities) === 1) {
            $eid = (int) array_key_first($entities);
        } elseif ($eid >= 0 && !isset($entities[$eid])) {
            $eid = -1;
        }
        plugin_paineldebordo_setConfigValue('filter_entity_focus', (string) $eid);
    }

    if (isset($_GET['nav_layout']) && in_array($_GET['nav_layout'], ['side', 'top'], true)) {
        plugin_paineldebordo_setConfigValue('nav_layout', $_GET['nav_layout']);
    }

    if (isset($_GET['nav_collapsed']) && in_array($_GET['nav_collapsed'], ['0', '1'], true)) {
        plugin_paineldebordo_setConfigValue('nav_collapsed', $_GET['nav_collapsed']);
    }
}

/**
 * @return array{theme:string,period:string,entity:string,group:int,status:string,nav_layout:string}
 */
function plugin_paineldebordo_getFilters(): array
{
    plugin_paineldebordo_syncFiltersFromRequest();
    plugin_paineldebordo_applyDefaultFilters();

    $theme = plugin_paineldebordo_getConfigValue('ui_theme', 'light');
    if (!in_array($theme, ['light', 'dark'], true)) {
        $theme = 'light';
    }

    $nav = plugin_paineldebordo_getConfigValue('nav_layout', 'side');
    if (!in_array($nav, ['side', 'top'], true)) {
        // Legacy layout key: 0 = side, 1 = top
        $legacy = plugin_paineldebordo_getConfigValue('layout', '');
        $nav = ($legacy === '1') ? 'top' : 'side';
    }

    $collapsed = plugin_paineldebordo_getConfigValue('nav_collapsed', '0') === '1';

    $period = plugin_paineldebordo_normalize_period(plugin_paineldebordo_getConfigValue('period', 'month'));
    if (plugin_paineldebordo_getConfigValue('period', 'month') === '30d') {
        plugin_paineldebordo_setConfigValue('period', 'month');
    }

    $group = (int) plugin_paineldebordo_getConfigValue('filter_group', '0');
    if ($group > 0
        && !plugin_paineldebordo_seesAllGroups()
        && !in_array($group, plugin_paineldebordo_getUserGroupIds(), true)
    ) {
        plugin_paineldebordo_setConfigValue('filter_group', '0');
        $group = 0;
    }

    $entity_focus = (int) plugin_paineldebordo_getConfigValue('filter_entity_focus', '-1');
    if (!function_exists('plugin_paineldebordo_entity_options')) {
        include_once __DIR__ . '/services/tickets.php';
    }
    $entities = plugin_paineldebordo_entity_options();
    if (count($entities) === 1) {
        $entity_focus = (int) array_key_first($entities);
        plugin_paineldebordo_setConfigValue('filter_entity_focus', (string) $entity_focus);
    } elseif ($entity_focus >= 0 && !isset($entities[$entity_focus])) {
        plugin_paineldebordo_setConfigValue('filter_entity_focus', '-1');
        $entity_focus = -1;
    }

    return [
        'theme'      => $theme,
        'period'     => $period,
        'entity'     => plugin_paineldebordo_getConfigValue('entity', ''),
        'entity_focus' => $entity_focus,
        'group'      => $group,
        'status'     => plugin_paineldebordo_getConfigValue('filter_status', 'open'),
        'nav_layout' => $nav,
        'nav_collapsed' => $collapsed,
    ];
}

/**
 * Groups available in filter dropdown for current user.
 *
 * @return array<int,string> id => name
 */
function plugin_paineldebordo_getGroupOptions(): array
{
    global $DB;

    $options = [0 => '-- ' . __('All my groups', 'paineldebordo') . ' --'];

    $entity_sql = '';
    $sel_ent = plugin_paineldebordo_getConfigValue('entity', '');
    if ($sel_ent === '' || $sel_ent === '-1') {
        $entities = Profile_User::getUserEntitiesForRight(
            $_SESSION['glpiID'],
            Ticket::$rightname,
            Ticket::READALL
        );
        if ($entities) {
            $ent = implode(',', array_map('intval', $entities));
            // Entity-scoped only — never bare OR is_recursive=1 (leaks all recursive groups).
            $entity_sql = " AND entities_id IN ($ent) ";
        }
    } else {
        $ent = preg_replace('/[^0-9,]/', '', $sel_ent);
        $entity_sql = " AND entities_id IN ($ent) ";
    }

    $group_scope = plugin_paineldebordo_sqlGroupScope('id');

    $sql = "SELECT id, name FROM glpi_groups WHERE 1=1 $entity_sql $group_scope ORDER BY name ASC";
    $result = $DB->doQuery($sql);
    if ($result) {
        while ($row = $DB->fetchAssoc($result)) {
            $options[(int) $row['id']] = $row['name'];
        }
    }

    return $options;
}
