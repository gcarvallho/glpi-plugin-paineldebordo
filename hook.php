<?php

include_once __DIR__ . '/inc/install.inc.php';

function plugin_paineldebordo_install()
{
    global $DB;

    try {
        plugin_paineldebordo_install_log(
            __('Painel de Bordo — starting installation…', 'paineldebordo'),
            defined('INFO') ? INFO : 0
        );

        // 1) Migrate tables from old plugin folder name "dashboard"
        $renames = [
            'glpi_plugin_dashboard_count'  => 'glpi_plugin_paineldebordo_count',
            'glpi_plugin_dashboard_map'    => 'glpi_plugin_paineldebordo_map',
            'glpi_plugin_dashboard_config' => 'glpi_plugin_paineldebordo_config',
        ];
        $migrated = 0;
        foreach ($renames as $old => $new) {
            if ($DB->TableExists($old) && !$DB->TableExists($new)) {
                if (!plugin_paineldebordo_db_ok(
                    $DB->doQuery("RENAME TABLE `$old` TO `$new`"),
                    sprintf(__('Rename table %1$s → %2$s', 'paineldebordo'), $old, $new)
                )) {
                    return false;
                }
                $migrated++;
            }
        }
        if ($migrated > 0) {
            plugin_paineldebordo_install_log(
                sprintf(__('Painel de Bordo — migrated %d legacy table(s)', 'paineldebordo'), $migrated),
                defined('INFO') ? INFO : 0
            );
        } else {
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — no legacy tables to rename', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        }

        // 1b) Warn while the old ST-Dashboard is still registered. Its uninstall
        // runs DROP TABLE without IF EXISTS on the three tables we just renamed,
        // so clicking "Uninstall" there now raises an exception on GLPI 11 (and,
        // before migrating, would have destroyed the very data we carry over).
        // The safe path is Disable + delete the folder — say so on screen, at the
        // exact moment the admin is looking at the plugin list.
        try {
            if ($DB->TableExists('glpi_plugins')) {
                $legacy = $DB->doQuery(
                    "SELECT id FROM glpi_plugins WHERE directory = 'dashboard' LIMIT 1"
                );
                if ($legacy && $DB->numrows($legacy) > 0) {
                    plugin_paineldebordo_install_log(
                        __('Painel de Bordo — ST-Dashboard detected: its data was migrated. Disable it and delete the dashboard folder. Do NOT use Uninstall on it: that would drop the migrated tables and end in error.', 'paineldebordo'),
                        defined('WARNING') ? WARNING : 1
                    );
                }
            }
        } catch (Throwable $e) {
            // Detection is advisory only — never block the install over it.
        }

        // 2) Migrate profile right name (idempotent)
        if ($DB->TableExists('glpi_profilerights')) {
            if (!plugin_paineldebordo_db_query_idempotent(
                "UPDATE glpi_profilerights SET name = 'plugin_paineldebordo' WHERE name = 'plugin_dashboard'",
                __('Migrate profile right name', 'paineldebordo')
            )) {
                return false;
            }
        }
        plugin_paineldebordo_install_log(
            __('Painel de Bordo — profile rights checked', 'paineldebordo'),
            defined('INFO') ? INFO : 0
        );

        // 3) Count table — correct schema from the start (avoid broken AUTO_INCREMENT PK + ALTER hang)
        if (!$DB->TableExists('glpi_plugin_paineldebordo_count')) {
            $query = "CREATE TABLE `glpi_plugin_paineldebordo_count` (
              `type` int NOT NULL DEFAULT 0,
              `id` int NOT NULL DEFAULT 0,
              `quant` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`type`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!plugin_paineldebordo_db_ok($DB->doQuery($query), __('Create table count', 'paineldebordo'))) {
                return false;
            }
            plugin_paineldebordo_db_ok(
                $DB->doQuery("INSERT INTO glpi_plugin_paineldebordo_count (type, id, quant) VALUES (1, 0, 1)"),
                __('Seed table count', 'paineldebordo')
            );
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — table count created', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        } else {
            // Only ALTER if composite PK is missing (skip when already applied — prevents long locks)
            $need_pk = true;
            $idx = $DB->doQuery('SHOW INDEX FROM `glpi_plugin_paineldebordo_count` WHERE Key_name = \'PRIMARY\'');
            if ($idx) {
                $cols = [];
                while ($row = $DB->fetchAssoc($idx)) {
                    $cols[] = strtolower((string) ($row['Column_name'] ?? ''));
                }
                if (in_array('type', $cols, true) && in_array('id', $cols, true) && count($cols) >= 2) {
                    $need_pk = false;
                }
            }
            if ($need_pk) {
                if (!plugin_paineldebordo_db_query_idempotent(
                    'ALTER TABLE `glpi_plugin_paineldebordo_count` DROP PRIMARY KEY, ADD PRIMARY KEY(`type`,`id`)',
                    __('Adjust count primary key', 'paineldebordo')
                )) {
                    return false;
                }
            }
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — table count already exists', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        }

        // 4) Map table
        if (!$DB->TableExists('glpi_plugin_paineldebordo_map')) {
            $query_map = "CREATE TABLE IF NOT EXISTS `glpi_plugin_paineldebordo_map` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `entities_id` int unsigned NOT NULL DEFAULT 0,
              `location` varchar(50) DEFAULT NULL,
              `lat` double NOT NULL DEFAULT 0,
              `lng` double NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `entities_id` (`entities_id`),
              UNIQUE KEY `location` (`location`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!plugin_paineldebordo_db_ok($DB->doQuery($query_map), __('Create table map', 'paineldebordo'))) {
                return false;
            }
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — table map created', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        } else {
            if (!plugin_paineldebordo_db_has_index('glpi_plugin_paineldebordo_map', 'location')) {
                // GLPI 11 doQuery throws on duplicate key — skip ALTER when index already exists
                if (!plugin_paineldebordo_db_query_idempotent(
                    'ALTER TABLE `glpi_plugin_paineldebordo_map` ADD UNIQUE KEY `location` (`location`)',
                    __('Add unique index on map.location', 'paineldebordo')
                )) {
                    return false;
                }
            }
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — table map already exists', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        }

        // 5) Config table (value TEXT — dashboard_layout JSON exceeds 255)
        if (!$DB->TableExists('glpi_plugin_paineldebordo_config')) {
            $query_conf = "CREATE TABLE IF NOT EXISTS `glpi_plugin_paineldebordo_config` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(50) NOT NULL,
              `value` text NOT NULL,
              `users_id` varchar(25) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`),
              UNIQUE KEY `name_users` (`name`,`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!plugin_paineldebordo_db_ok($DB->doQuery($query_conf), __('Create table config', 'paineldebordo'))) {
                return false;
            }
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — table config created', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        } else {
            if (!plugin_paineldebordo_widen_config_value_column()) {
                return false;
            }
            // Normalize legacy entity='-1' in one statement (no per-row loop)
            if (!plugin_paineldebordo_db_query_idempotent(
                "UPDATE glpi_plugin_paineldebordo_config SET value = '' WHERE name = 'entity' AND value = '-1'",
                __('Normalize entity filter value', 'paineldebordo')
            )) {
                return false;
            }
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — table config already exists', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        }

        plugin_paineldebordo_install_log(
            __('Painel de Bordo — schema adjustments done', 'paineldebordo'),
            defined('INFO') ? INFO : 0
        );

        // 6) Profile rights (never abort install if Super-Admin lookup fails)
        include_once __DIR__ . '/inc/profile.class.php';
        try {
            if (isset($_SESSION['glpiactiveprofile']['id'])) {
                PluginPaineldebordoProfile::createFirstAccess((int) $_SESSION['glpiactiveprofile']['id']);
            }
            $names = ['Super-Admin', 'Super-Administrador'];
            foreach ($names as $pname) {
                $super = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_profiles',
                    'WHERE'  => ['name' => $pname],
                    'LIMIT'  => 1,
                ]);
                foreach ($super as $row) {
                    PluginPaineldebordoProfile::createFirstAccess((int) $row['id']);
                }
            }
            plugin_paineldebordo_install_log(
                __('Painel de Bordo — profile rights granted', 'paineldebordo'),
                defined('INFO') ? INFO : 0
            );
        } catch (Throwable $e) {
            plugin_paineldebordo_install_log(
                sprintf(__('Painel de Bordo — profile rights failed: %s', 'paineldebordo'), $e->getMessage()),
                defined('WARNING') ? WARNING : 1
            );
        }

        // Module ACL (tickets / analysis / resources) for all profiles that already have master READ
        try {
            PluginPaineldebordoProfile::migrateModuleRights();
        } catch (Throwable $e) {
            plugin_paineldebordo_install_log(
                sprintf(__('Painel de Bordo — module rights migrate failed: %s', 'paineldebordo'), $e->getMessage()),
                defined('WARNING') ? WARNING : 1
            );
        }

        // 7) TV devices table
        include_once __DIR__ . '/inc/tv_pair.inc.php';
        plugin_paineldebordo_tv_pair_ensure_table();
        if (!$DB->TableExists('glpi_plugin_paineldebordo_tv_devices')) {
            return plugin_paineldebordo_install_fail(
                __('Painel de Bordo — TV devices table was not created', 'paineldebordo')
            );
        }
        plugin_paineldebordo_install_log(
            __('Painel de Bordo — TV pairing table ready', 'paineldebordo'),
            defined('INFO') ? INFO : 0
        );

        // 8) Logs & audit tables (access log + presence)
        include_once __DIR__ . '/inc/audit.inc.php';
        plugin_paineldebordo_audit_ensure_tables();
        plugin_paineldebordo_install_log(
            __('Painel de Bordo — logs & audit tables ready', 'paineldebordo'),
            defined('INFO') ? INFO : 0
        );

        // 9) Retention cron — inline purge stays as a fallback, this makes
        // retention hold even when nobody opens the plugin for a while.
        try {
            include_once __DIR__ . '/inc/audit.class.php';
            if (class_exists('CronTask') && method_exists('CronTask', 'register')) {
                CronTask::register(
                    'PluginPaineldebordoAudit',
                    'auditpurge',
                    defined('DAY_TIMESTAMP') ? DAY_TIMESTAMP : 86400,
                    [
                        'comment' => 'Painel de Bordo — audit log retention',
                        'mode'    => defined('CronTask::MODE_EXTERNAL') ? CronTask::MODE_EXTERNAL : 2,
                    ]
                );
            }
        } catch (Throwable $e) {
            plugin_paineldebordo_install_log(
                sprintf(__('Painel de Bordo — audit cron not registered: %s', 'paineldebordo'), $e->getMessage()),
                defined('WARNING') ? WARNING : 1
            );
        }

        plugin_paineldebordo_install_log(
            __('Painel de Bordo installed successfully.', 'paineldebordo'),
            defined('INFO') ? INFO : 0
        );
        return true;
    } catch (Throwable $e) {
        // GLPI 11 doQuery() throws on MySQL errors — duplicate index must not abort install
        if (plugin_paineldebordo_db_error_is_benign($e->getMessage())) {
            plugin_paineldebordo_install_log(
                sprintf(
                    __('Painel de Bordo — step already applied (skipped): %s', 'paineldebordo'),
                    $e->getMessage()
                ),
                defined('WARNING') ? WARNING : 1
            );
            include_once __DIR__ . '/inc/tv_pair.inc.php';
            plugin_paineldebordo_tv_pair_ensure_table();
            if ($DB->TableExists('glpi_plugin_paineldebordo_map')
                && $DB->TableExists('glpi_plugin_paineldebordo_config')
                && $DB->TableExists('glpi_plugin_paineldebordo_tv_devices')
            ) {
                plugin_paineldebordo_install_log(
                    __('Painel de Bordo installed successfully.', 'paineldebordo'),
                    defined('INFO') ? INFO : 0
                );
                return true;
            }
        }
        return plugin_paineldebordo_install_fail(
            sprintf(__('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'), $e->getMessage(), '')
        );
    }
}function plugin_paineldebordo_redefine_menus($menu)
{
    if (empty($menu)) {
        return $menu;
    }

    if (!Session::haveRight('plugin_paineldebordo', READ)) {
        return $menu;
    }

    if (empty($menu['inovarehub'])) {
        $menu['inovarehub'] = [
            'title'   => __('Inovare - Hub', 'paineldebordo'),
            'icon'    => 'ti ti-building-community',
            'types'   => [],
            'content' => [],
        ];
    }

    $menu['inovarehub']['content']['paineldebordo'] = [
        'title' => __('Painel de Bordo', 'paineldebordo'),
        'page'  => '/plugins/paineldebordo/public/index.php',
        'icon'  => 'ti ti-chart-histogram',
    ];

    if (!empty($menu['inovarehub']['content']) && is_array($menu['inovarehub']['content'])) {
        uasort($menu['inovarehub']['content'], static function ($a, $b) {
            return strcmp($a['title'] ?? '', $b['title'] ?? '');
        });
    }

    return $menu;
}

function plugin_paineldebordo_uninstall()
{
    global $DB;

    include_once __DIR__ . '/inc/install.inc.php';

    plugin_paineldebordo_install_log(
        __('Painel de Bordo — starting uninstall…', 'paineldebordo'),
        defined('INFO') ? INFO : 0
    );

    // Drop the audit retention cron task (leaving it behind would show a
    // broken entry under Setup > Automatic actions).
    try {
        if (class_exists('CronTask') && method_exists('CronTask', 'unregister')) {
            CronTask::unregister('paineldebordo');
        }
    } catch (Throwable $e) {
        // Non-fatal — keep uninstalling.
    }

    $tables = [
        'glpi_plugin_paineldebordo_count',
        'glpi_plugin_paineldebordo_map',
        'glpi_plugin_paineldebordo_config',
        'glpi_plugin_paineldebordo_tv_devices',
        'glpi_plugin_paineldebordo_accesslog',
        'glpi_plugin_paineldebordo_presence',
    ];
    foreach ($tables as $table) {
        if (!plugin_paineldebordo_db_ok(
            $DB->doQuery("DROP TABLE IF EXISTS `$table`"),
            sprintf(__('Drop table %s', 'paineldebordo'), $table)
        )) {
            return false;
        }
    }

    try {
        include_once __DIR__ . '/inc/profile.class.php';
        PluginPaineldebordoProfile::uninstallProfile();
    } catch (Throwable $e) {
        return plugin_paineldebordo_install_fail(
            sprintf(__('Painel de Bordo — uninstall profile failed: %s', 'paineldebordo'), $e->getMessage())
        );
    }

    plugin_paineldebordo_install_log(
        __('Painel de Bordo uninstalled successfully.', 'paineldebordo'),
        defined('INFO') ? INFO : 0
    );

    return true;
}
