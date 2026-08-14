<?php

/**
 * Install / uninstall progress helpers for GLPI Plugins UI.
 */

/**
 * Queue a message shown on Config → Plugins after redirect.
 * Always prefers Session flash; never depends on undefined INFO/ERROR defaults.
 */
function plugin_paineldebordo_install_log(string $msg, ?int $type = null): void
{
    if ($msg === '') {
        return;
    }
    if ($type === null) {
        $type = defined('INFO') ? (int) INFO : 0;
    }

    $queued = false;
    if (class_exists('Session') && method_exists('Session', 'addMessageAfterRedirect')) {
        Session::addMessageAfterRedirect($msg, false, $type);
        $queued = true;
    }

    // Hard fallback — some GLPI install flows clear typed helpers; keep flash visible
    if (!$queued || empty($_SESSION['MESSAGE_AFTER_REDIRECT'][$type])) {
        if (!isset($_SESSION['MESSAGE_AFTER_REDIRECT']) || !is_array($_SESSION['MESSAGE_AFTER_REDIRECT'])) {
            $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];
        }
        if (!isset($_SESSION['MESSAGE_AFTER_REDIRECT'][$type]) || !is_array($_SESSION['MESSAGE_AFTER_REDIRECT'][$type])) {
            $_SESSION['MESSAGE_AFTER_REDIRECT'][$type] = [];
        }
        if (!in_array($msg, $_SESSION['MESSAGE_AFTER_REDIRECT'][$type], true)) {
            $_SESSION['MESSAGE_AFTER_REDIRECT'][$type][] = $msg;
        }
    }

    // Also mirror to Administração → Logs (file + events)
    if (function_exists('plugin_paineldebordo_trace')) {
        plugin_paineldebordo_trace($msg, ($type === (defined('ERROR') ? (int) ERROR : 2)) ? 1 : 3);
    }
}

/**
 * Durable install/debug trace: files/_log/paineldebordo.log (Administração → Logs)
 * and glpi_events when Event::log is available.
 */
function plugin_paineldebordo_trace(string $msg, int $eventLevel = 3): void
{
    $line = '[install] ' . $msg;
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', $line . "\n");
    }
    try {
        if (class_exists(\Glpi\Event::class) && method_exists(\Glpi\Event::class, 'log')) {
            \Glpi\Event::log(0, 'system', $eventLevel, 'Paineldebordo', $msg);
        } elseif (class_exists('Event') && method_exists('Event', 'log')) {
            Event::log(0, 'system', $eventLevel, 'Paineldebordo', $msg);
        }
    } catch (Throwable $e) {
        // Never break install because of logging.
    }
}

/**
 * Log ERROR and signal install failure.
 */
function plugin_paineldebordo_install_fail(string $msg): bool
{
    $type = defined('ERROR') ? (int) ERROR : 2;
    plugin_paineldebordo_install_log($msg, $type);
    return false;
}

/**
 * Check DB query result; on failure log step + MySQL error.
 *
 * @param mixed $result Result of $DB->doQuery()
 */
function plugin_paineldebordo_db_ok($result, string $step): bool
{
    global $DB;

    if ($result !== false) {
        return true;
    }

    $err = '';
    if (isset($DB) && method_exists($DB, 'error')) {
        $err = (string) $DB->error();
    }
    $detail = $err !== '' ? (': ' . $err) : '';
    plugin_paineldebordo_install_fail(
        sprintf(
            __('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'),
            $step,
            $detail
        )
    );
    return false;
}

/**
 * Run ALTER/DDL that may already be applied (duplicate key / exists).
 * Returns true if OK or already applied; false on unexpected errors.
 *
 * @param mixed $result
 */
function plugin_paineldebordo_db_ok_idempotent($result, string $step): bool
{
    global $DB;

    if ($result !== false) {
        return true;
    }

    $err = '';
    if (isset($DB) && method_exists($DB, 'error')) {
        $err = (string) $DB->error();
    }
    return plugin_paineldebordo_db_benign_or_fail($err, $step);
}

/**
 * True when MySQL error means "already applied" (safe to skip).
 */
function plugin_paineldebordo_db_error_is_benign(string $err): bool
{
    $lower = strtolower($err);
    return (
        str_contains($lower, 'duplicate')
        || str_contains($lower, 'already exists')
        || str_contains($lower, 'multiple primary key')
        || str_contains($lower, "can't drop")
        || str_contains($lower, 'check that column/key exists')
        || str_contains($lower, '(1060)')
        || str_contains($lower, '(1061)')
        || str_contains($lower, '(1062)')
        || str_contains($lower, '(1068)')
        || str_contains($lower, '(1091)')
        || str_contains($lower, 'errno: 1060')
        || str_contains($lower, 'errno: 1061')
        || str_contains($lower, 'errno: 1062')
        || str_contains($lower, 'errno: 1068')
        || str_contains($lower, 'errno: 1091')
    );
}

/**
 * @return bool true if benign/skipped; false if hard fail logged
 */
function plugin_paineldebordo_db_benign_or_fail(string $err, string $step): bool
{
    if (plugin_paineldebordo_db_error_is_benign($err)) {
        $type = defined('WARNING') ? (int) WARNING : 1;
        plugin_paineldebordo_install_log(
            sprintf(
                __('Painel de Bordo — step already applied (skipped): %s', 'paineldebordo'),
                $step
            ),
            $type
        );
        return true;
    }

    $detail = $err !== '' ? (': ' . $err) : '';
    return plugin_paineldebordo_install_fail(
        sprintf(
            __('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'),
            $step,
            $detail
        )
    );
}

/**
 * Execute SQL idempotently. GLPI 11+ doQuery() throws on MySQL errors —
 * catch benign duplicates (1061 etc.) instead of aborting install.
 */
function plugin_paineldebordo_db_query_idempotent(string $sql, string $step): bool
{
    global $DB;

    try {
        $DB->doQuery($sql);
        return true;
    } catch (Throwable $e) {
        return plugin_paineldebordo_db_benign_or_fail($e->getMessage(), $step);
    }
}

/**
 * Whether an index/key name already exists on a table.
 */
function plugin_paineldebordo_db_has_index(string $table, string $keyName): bool
{
    global $DB;

    if (!$DB->TableExists($table)) {
        return false;
    }
    $escTable = str_replace('`', '``', $table);
    $escKey = method_exists($DB, 'escape') ? $DB->escape($keyName) : addslashes($keyName);
    try {
        $idx = $DB->doQuery("SHOW INDEX FROM `$escTable` WHERE Key_name = '$escKey'");
    } catch (Throwable $e) {
        return false;
    }
    if (!$idx) {
        return false;
    }
    if (method_exists($DB, 'numrows')) {
        return $DB->numrows($idx) > 0;
    }
    $row = $DB->fetchAssoc($idx);
    return is_array($row) && $row !== [];
}

/**
 * List secondary (non-PRIMARY) index names on a table.
 *
 * @return list<string>
 */
function plugin_paineldebordo_db_secondary_index_names(string $table): array
{
    global $DB;

    $names = [];
    $escTable = method_exists($DB, 'escape') ? $DB->escape($table) : addslashes($table);

    // Prefer information_schema — more reliable than SHOW INDEX + fetchAssoc loops on some GLPI/MySQL setups.
    try {
        $sql = "SELECT DISTINCT INDEX_NAME AS idx_name
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '$escTable'
                  AND INDEX_NAME <> 'PRIMARY'";
        $res = $DB->doQuery($sql);
        if ($res && method_exists($DB, 'fetchAssoc')) {
            while ($row = $DB->fetchAssoc($res)) {
                if (!is_array($row)) {
                    break;
                }
                $key = (string) ($row['idx_name'] ?? $row['INDEX_NAME'] ?? '');
                if ($key !== '') {
                    $names[$key] = true;
                }
            }
        }
    } catch (Throwable $e) {
        // Fall through to SHOW INDEX.
    }

    if ($names === []) {
        try {
            $idx = $DB->doQuery('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '`');
            if ($idx && method_exists($DB, 'fetchAssoc')) {
                while ($row = $DB->fetchAssoc($idx)) {
                    if (!is_array($row)) {
                        break;
                    }
                    $key = (string) ($row['Key_name'] ?? '');
                    if ($key !== '' && strcasecmp($key, 'PRIMARY') !== 0) {
                        $names[$key] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            // Caller handles empty list.
        }
    }

    return array_keys($names);
}

/**
 * Widen glpi_plugin_paineldebordo_config.value to TEXT without MySQL 1170.
 *
 * MODIFY fails on legacy DBs that index `value`. Rebuild the table instead:
 * create clean schema → copy rows → atomic RENAME → drop old.
 * Traces go to Administração → Logs (paineldebordo.log + events).
 */
function plugin_paineldebordo_widen_config_value_column(): bool
{
    global $DB;

    $table = 'glpi_plugin_paineldebordo_config';
    $tmp = 'glpi_plugin_paineldebordo_config_pdbnew';
    $old = 'glpi_plugin_paineldebordo_config_pdbold';
    $step = __('Widen config value column', 'paineldebordo');

    if (!$DB->TableExists($table)) {
        plugin_paineldebordo_trace('config table missing — skip widen');
        return true;
    }

    // Snapshot schema for Administração → Logs
    try {
        $cr = $DB->doQuery("SHOW CREATE TABLE `$table`");
        $crow = ($cr && method_exists($DB, 'fetchAssoc')) ? $DB->fetchAssoc($cr) : null;
        if (is_array($crow)) {
            $ddl = '';
            foreach ($crow as $k => $v) {
                if (stripos((string) $k, 'create') !== false) {
                    $ddl = (string) $v;
                    break;
                }
            }
            plugin_paineldebordo_trace('config SHOW CREATE: ' . substr($ddl, 0, 1500));
        }
        $idxs = plugin_paineldebordo_db_secondary_index_names($table);
        plugin_paineldebordo_trace('config secondary indexes: ' . ($idxs === [] ? '(none)' : implode(', ', $idxs)));
    } catch (Throwable $e) {
        plugin_paineldebordo_trace('config schema snapshot failed: ' . $e->getMessage());
    }

    // Already TEXT?
    try {
        $cols = $DB->doQuery("SHOW COLUMNS FROM `$table` LIKE 'value'");
        $col = ($cols && method_exists($DB, 'fetchAssoc')) ? $DB->fetchAssoc($cols) : null;
        if (is_array($col)) {
            $type = strtolower((string) ($col['Type'] ?? ''));
            plugin_paineldebordo_trace('config.value type=' . $type);
            if (
                str_starts_with($type, 'text')
                || str_starts_with($type, 'tinytext')
                || str_starts_with($type, 'mediumtext')
                || str_starts_with($type, 'longtext')
            ) {
                plugin_paineldebordo_trace('config.value already TEXT — skip');
                return true;
            }
        }
    } catch (Throwable $e) {
        plugin_paineldebordo_trace('SHOW COLUMNS value failed: ' . $e->getMessage());
    }

    // Cleanup leftovers from a previous failed attempt
    foreach ([$tmp, $old] as $t) {
        if ($DB->TableExists($t)) {
            plugin_paineldebordo_trace("dropping leftover `$t`");
            try {
                $DB->doQuery("DROP TABLE `$t`");
            } catch (Throwable $e) {
                return plugin_paineldebordo_install_fail(
                    sprintf(__('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'), $step, ': ' . $e->getMessage())
                );
            }
        }
    }

    $create = "CREATE TABLE `$tmp` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(50) NOT NULL,
      `value` text NOT NULL,
      `users_id` varchar(25) NOT NULL DEFAULT '',
      PRIMARY KEY (`id`),
      UNIQUE KEY `name_users` (`name`,`users_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    plugin_paineldebordo_trace('creating clean config table for TEXT widen');
    try {
        $DB->doQuery($create);
    } catch (Throwable $e) {
        return plugin_paineldebordo_install_fail(
            sprintf(__('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'), $step, ': ' . $e->getMessage())
        );
    }

    // Copy data — CAST keeps int/varchar users_id and long values portable
    $copy = "INSERT INTO `$tmp` (`id`, `name`, `value`, `users_id`)
             SELECT `id`, `name`, CAST(`value` AS CHAR), CAST(`users_id` AS CHAR)
             FROM `$table`";
    plugin_paineldebordo_trace('copying config rows into clean table');
    try {
        $DB->doQuery($copy);
    } catch (Throwable $e) {
        try {
            $DB->doQuery("DROP TABLE `$tmp`");
        } catch (Throwable $e2) {
        }
        return plugin_paineldebordo_install_fail(
            sprintf(__('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'), $step, ': ' . $e->getMessage())
        );
    }

    plugin_paineldebordo_trace('swapping config tables (RENAME)');
    try {
        $DB->doQuery("RENAME TABLE `$table` TO `$old`, `$tmp` TO `$table`");
    } catch (Throwable $e) {
        try {
            $DB->doQuery("DROP TABLE IF EXISTS `$tmp`");
        } catch (Throwable $e2) {
        }
        return plugin_paineldebordo_install_fail(
            sprintf(__('Painel de Bordo — step failed: %1$s%2$s', 'paineldebordo'), $step, ': ' . $e->getMessage())
        );
    }

    try {
        $DB->doQuery("DROP TABLE `$old`");
    } catch (Throwable $e) {
        plugin_paineldebordo_trace('drop old config table warning: ' . $e->getMessage());
        // New table is live — do not fail install for leftover old table.
    }

    plugin_paineldebordo_trace('config.value widened via table rebuild OK');
    return true;
}
