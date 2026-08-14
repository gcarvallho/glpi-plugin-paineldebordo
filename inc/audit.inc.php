<?php

/**
 * Logs & audit — records plugin sessions + sensitive actions (config change,
 * exports, TV pairing) and an approximate "who is online" presence, plus reads
 * GLPI-native login events. Retention inherits GLPI by default (no self-purge)
 * with an optional custom day override. Mirrors the tv_pair.inc.php patterns
 * (idempotent DDL, global config in users_id=0, throttled inline cleanup).
 */

/** Sensitive-action keys recorded in the access log. */
function plugin_paineldebordo_audit_actions(): array
{
    return [
        'session_open'     => __('Opened the dashboard', 'paineldebordo'),
        'access_denied'    => __('Access denied', 'paineldebordo'),
        'config_change'    => __('Changed configuration', 'paineldebordo'),
        'export_report'    => __('Exported a report', 'paineldebordo'),
        'export_chart'     => __('Exported a chart', 'paineldebordo'),
        'export_logs'      => __('Exported the audit log', 'paineldebordo'),
        'tv_pair_approve'  => __('Paired a TV device', 'paineldebordo'),
        'tv_device_revoke' => __('Revoked a TV device', 'paineldebordo'),
    ];
}

/** Human label for an action key (falls back to the raw key). */
function plugin_paineldebordo_audit_action_label(string $action): string
{
    $map = plugin_paineldebordo_audit_actions();
    return $map[$action] ?? $action;
}

function plugin_paineldebordo_audit_ensure_tables(): void
{
    global $DB;
    if (!isset($DB)) {
        return;
    }
    // NOTE: date columns are TIMESTAMP, not DATETIME — GLPI 11 emits a
    // deprecation warning ("Usage of DATETIME fields is discouraged") from
    // DBmysql::checkForDeprecatedTableOptions() on every CREATE/ALTER.
    if (!$DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        $DB->doQuery("CREATE TABLE IF NOT EXISTS `glpi_plugin_paineldebordo_accesslog` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `users_id` int unsigned NOT NULL DEFAULT 0,
          `user_name` varchar(255) NOT NULL DEFAULT '',
          `action` varchar(32) NOT NULL DEFAULT '',
          `detail` varchar(255) NOT NULL DEFAULT '',
          `page` varchar(50) NOT NULL DEFAULT '',
          `entities_id` int unsigned NOT NULL DEFAULT 0,
          `remote_ip` varchar(45) DEFAULT NULL,
          `user_agent` varchar(255) DEFAULT NULL,
          `date_creation` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `date_creation` (`date_creation`),
          KEY `users_id` (`users_id`),
          KEY `action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        plugin_paineldebordo_audit_modify_to_timestamp(
            'glpi_plugin_paineldebordo_accesslog',
            'date_creation'
        );
        // 2.34.0 added the active entity to each entry.
        if (!$DB->fieldExists('glpi_plugin_paineldebordo_accesslog', 'entities_id')) {
            try {
                $DB->doQuery("ALTER TABLE `glpi_plugin_paineldebordo_accesslog`
                    ADD COLUMN `entities_id` int unsigned NOT NULL DEFAULT 0 AFTER `page`");
            } catch (Throwable $e) {
                // Non-fatal — logging must never block install.
            }
        }
    }
    if (!$DB->TableExists('glpi_plugin_paineldebordo_presence')) {
        $DB->doQuery("CREATE TABLE IF NOT EXISTS `glpi_plugin_paineldebordo_presence` (
          `users_id` int unsigned NOT NULL,
          `user_name` varchar(255) NOT NULL DEFAULT '',
          `last_seen` timestamp NULL DEFAULT NULL,
          `last_page` varchar(50) NOT NULL DEFAULT '',
          `remote_ip` varchar(45) DEFAULT NULL,
          PRIMARY KEY (`users_id`),
          KEY `last_seen` (`last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        plugin_paineldebordo_audit_modify_to_timestamp(
            'glpi_plugin_paineldebordo_presence',
            'last_seen'
        );
    }
}

/**
 * Upgrade a legacy DATETIME column to TIMESTAMP (2.33.0 shipped these tables
 * with DATETIME, which GLPI 11 deprecates). Only runs when the column really
 * is datetime, so it is a no-op on fresh installs and on re-runs.
 */
function plugin_paineldebordo_audit_modify_to_timestamp(string $table, string $column): void
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists($table) || !$DB->fieldExists($table, $column)) {
        return;
    }
    try {
        $res = $DB->doQuery(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '" . $DB->escape($table) . "'
               AND COLUMN_NAME = '" . $DB->escape($column) . "' LIMIT 1"
        );
        if (!$res || $DB->numrows($res) === 0) {
            return;
        }
        if (strtolower((string) $DB->result($res, 0, 'DATA_TYPE')) !== 'datetime') {
            return;
        }
        $t = str_replace('`', '``', $table);
        $c = str_replace('`', '``', $column);
        $DB->doQuery("ALTER TABLE `$t` MODIFY COLUMN `$c` timestamp NULL DEFAULT NULL");
    } catch (Throwable $e) {
        // Non-fatal: logging must never block install/navigation.
    }
}

/** Global (users_id=0) config read — same pattern as the TV TTL setting. */
function plugin_paineldebordo_audit_config_get(string $name, string $default = ''): string
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_config')) {
        return $default;
    }
    $res = $DB->doQuery(
        "SELECT value FROM glpi_plugin_paineldebordo_config
         WHERE name = '" . $DB->escape($name) . "' AND users_id = 0 LIMIT 1"
    );
    if ($res && $DB->numrows($res) > 0) {
        return (string) $DB->result($res, 0, 'value');
    }
    return $default;
}

/** Global (users_id=0) config write — upsert. */
function plugin_paineldebordo_audit_config_set(string $name, string $value): void
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_config')) {
        return;
    }
    $n = $DB->escape($name);
    $v = $DB->escape($value);
    $exists = $DB->doQuery(
        "SELECT id FROM glpi_plugin_paineldebordo_config WHERE name = '$n' AND users_id = 0 LIMIT 1"
    );
    if ($exists && $DB->numrows($exists) > 0) {
        $DB->doQuery("UPDATE glpi_plugin_paineldebordo_config SET value = '$v' WHERE name = '$n' AND users_id = 0");
    } else {
        $DB->doQuery("INSERT INTO glpi_plugin_paineldebordo_config (name, value, users_id) VALUES ('$n', '$v', 0)");
    }
}

/**
 * Record a sensitive action / session event in the access log.
 * Silently no-ops if the table is missing (e.g. pre-install).
 */
function plugin_paineldebordo_audit_log(string $action, string $detail = '', string $page = ''): void
{
    global $DB;
    if (!isset($DB)) {
        return;
    }
    plugin_paineldebordo_audit_ensure_tables();
    if (!$DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        return;
    }
    $uid  = (int) ($_SESSION['glpiID'] ?? 0);
    $name = (string) ($_SESSION['glpiname'] ?? '');
    $ip   = function_exists('plugin_paineldebordo_tv_remote_ip')
        ? plugin_paineldebordo_tv_remote_ip()
        : mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ua   = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ent  = (int) ($_SESSION['glpiactive_entity'] ?? 0);
    $now  = date('Y-m-d H:i:s');

    $has_ent = $DB->fieldExists('glpi_plugin_paineldebordo_accesslog', 'entities_id');
    $cols = 'users_id, user_name, action, detail, page, remote_ip, user_agent, date_creation'
        . ($has_ent ? ', entities_id' : '');
    $vals = "$uid,"
        . "'" . $DB->escape(mb_substr($name, 0, 255)) . "',"
        . "'" . $DB->escape(mb_substr($action, 0, 32)) . "',"
        . "'" . $DB->escape(mb_substr($detail, 0, 255)) . "',"
        . "'" . $DB->escape(mb_substr($page, 0, 50)) . "',"
        . "'" . $DB->escape($ip) . "',"
        . "'" . $DB->escape($ua) . "',"
        . "'" . $DB->escape($now) . "'"
        . ($has_ent ? ", $ent" : '');

    $DB->doQuery("INSERT INTO glpi_plugin_paineldebordo_accesslog ($cols) VALUES ($vals)");
}

/**
 * Per-page-load presence upsert + one session_open log per session.
 * Called from shell.php on every plugin load.
 */
function plugin_paineldebordo_audit_touch(string $page = ''): void
{
    global $DB;
    if (!isset($DB)) {
        return;
    }
    plugin_paineldebordo_audit_ensure_tables();
    $uid = (int) ($_SESSION['glpiID'] ?? 0);
    if ($uid <= 0) {
        return;
    }
    $name = (string) ($_SESSION['glpiname'] ?? '');
    $ip   = function_exists('plugin_paineldebordo_tv_remote_ip')
        ? plugin_paineldebordo_tv_remote_ip()
        : mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $now  = date('Y-m-d H:i:s');

    if ($DB->TableExists('glpi_plugin_paineldebordo_presence')) {
        $n = $DB->escape(mb_substr($name, 0, 255));
        $p = $DB->escape(mb_substr($page, 0, 50));
        $i = $DB->escape($ip);
        $t = $DB->escape($now);
        $DB->doQuery(
            "INSERT INTO glpi_plugin_paineldebordo_presence (users_id, user_name, last_seen, last_page, remote_ip)
             VALUES ($uid, '$n', '$t', '$p', '$i')
             ON DUPLICATE KEY UPDATE user_name = '$n', last_seen = '$t', last_page = '$p', remote_ip = '$i'"
        );
    }

    // One "opened the dashboard" event per PHP session.
    if (empty($_SESSION['pdb_audit_session'])) {
        $_SESSION['pdb_audit_session'] = 1;
        plugin_paineldebordo_audit_log('session_open', '', $page);
    }
}

/**
 * Users seen within the last $minutes (approximate "online now").
 * @return array<int, array<string,mixed>>
 */
function plugin_paineldebordo_audit_online(int $minutes = 15): array
{
    global $DB;
    $out = [];
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_presence')) {
        return $out;
    }
    $minutes = max(1, min(1440, $minutes));
    $since = $DB->escape(date('Y-m-d H:i:s', time() - $minutes * 60));
    $res = $DB->doQuery(
        "SELECT users_id, user_name, last_seen, last_page, remote_ip
         FROM glpi_plugin_paineldebordo_presence
         WHERE last_seen >= '$since'
         ORDER BY last_seen DESC LIMIT 100"
    );
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * Build the shared WHERE clause for access-log queries.
 * @param array{action?:string,users_id?:int,q?:string,from?:string,to?:string} $opts
 */
function plugin_paineldebordo_audit_where(array $opts = []): string
{
    global $DB;
    $where = ['1=1'];
    if (!empty($opts['action'])) {
        $where[] = "action = '" . $DB->escape((string) $opts['action']) . "'";
    }
    if (!empty($opts['users_id'])) {
        $where[] = 'users_id = ' . (int) $opts['users_id'];
    }
    if (!empty($opts['q'])) {
        $q = $DB->escape((string) $opts['q']);
        $where[] = "(detail LIKE '%$q%' OR user_name LIKE '%$q%')";
    }
    if (!empty($opts['from'])) {
        $where[] = "date_creation >= '" . $DB->escape((string) $opts['from']) . " 00:00:00'";
    }
    if (!empty($opts['to'])) {
        $where[] = "date_creation <= '" . $DB->escape((string) $opts['to']) . " 23:59:59'";
    }
    return implode(' AND ', $where);
}

/**
 * Access-log rows, newest first, with optional filters + pagination.
 * @param array{action?:string,users_id?:int,q?:string,from?:string,to?:string,limit?:int,offset?:int} $opts
 * @return array<int, array<string,mixed>>
 */
function plugin_paineldebordo_audit_list(array $opts = []): array
{
    global $DB;
    $out = [];
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        return $out;
    }
    $limit = max(1, min(1000, (int) ($opts['limit'] ?? 100)));
    $offset = max(0, (int) ($opts['offset'] ?? 0));
    $ent = $DB->fieldExists('glpi_plugin_paineldebordo_accesslog', 'entities_id')
        ? 'entities_id' : '0 AS entities_id';
    $sql = "SELECT id, users_id, user_name, action, detail, page, $ent,
                   remote_ip, user_agent, date_creation
            FROM glpi_plugin_paineldebordo_accesslog
            WHERE " . plugin_paineldebordo_audit_where($opts) . "
            ORDER BY date_creation DESC, id DESC
            LIMIT $limit OFFSET $offset";
    $res = $DB->doQuery($sql);
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $out[] = $row;
        }
    }
    return $out;
}

/** Total access-log rows matching the same filters (for pagination). */
function plugin_paineldebordo_audit_count(array $opts = []): int
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        return 0;
    }
    $res = $DB->doQuery(
        "SELECT COUNT(*) AS n FROM glpi_plugin_paineldebordo_accesslog
         WHERE " . plugin_paineldebordo_audit_where($opts)
    );
    if ($res && $DB->numrows($res) > 0) {
        return (int) $DB->result($res, 0, 'n');
    }
    return 0;
}

/**
 * Distinct users present in the access log, for the filter dropdown.
 * @return array<int,string> users_id => display name
 */
function plugin_paineldebordo_audit_users(): array
{
    global $DB;
    $out = [];
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        return $out;
    }
    $res = $DB->doQuery(
        "SELECT users_id, MAX(user_name) AS user_name
         FROM glpi_plugin_paineldebordo_accesslog
         WHERE users_id > 0
         GROUP BY users_id
         ORDER BY user_name ASC LIMIT 200"
    );
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $out[(int) $row['users_id']] = (string) ($row['user_name'] ?? '');
        }
    }
    return $out;
}

/**
 * GLPI-native login events (glpi_events, service='login'), newest first.
 *
 * GLPI does NOT put the user id in glpi_events.items_id for login events
 * (it's 0/-1) — the login is the first token of the localized message
 * ("jdoe fez login no IP 1.2.3.4"). So resolve the user by matching that
 * token against glpi_users.name, falling back to items_id when it happens
 * to be a real id. Rows we can't resolve return users_id = 0 and the view
 * renders them without a fake "#0" avatar.
 *
 * @return array<int, array<string,mixed>>
 */
function plugin_paineldebordo_audit_glpi_logins(int $limit = 100): array
{
    global $DB;
    $out = [];
    if (!isset($DB) || !$DB->TableExists('glpi_events')) {
        return $out;
    }
    $limit = max(1, min(500, $limit));
    $res = $DB->doQuery(
        "SELECT e.id, e.items_id, e.date, e.level, e.message,
                u.id AS matched_id, u.name AS matched_name
         FROM glpi_events e
         LEFT JOIN glpi_users u
                ON u.name = SUBSTRING_INDEX(TRIM(e.message), ' ', 1)
         WHERE e.service = 'login'
         ORDER BY e.date DESC, e.id DESC LIMIT $limit"
    );
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $uid = (int) ($row['matched_id'] ?? 0);
            if ($uid <= 0) {
                $fallback = (int) ($row['items_id'] ?? 0);
                $uid = $fallback > 0 ? $fallback : 0;
            }
            $out[] = [
                'id'        => $row['id'] ?? 0,
                'users_id'  => $uid,
                'user_name' => (string) ($row['matched_name'] ?? ''),
                'date'      => $row['date'] ?? '',
                'level'     => $row['level'] ?? '',
                'message'   => (string) ($row['message'] ?? ''),
            ];
        }
    }
    return $out;
}

/** Default retention when nothing was configured yet (days). */
const PLUGIN_PAINELDEBORDO_RETENTION_DEFAULT = 30;

/** Retention in days; 0 = inherit GLPI (no self-purge). Default: 30 days. */
function plugin_paineldebordo_audit_retention_days(): int
{
    $raw = (int) plugin_paineldebordo_audit_config_get(
        'logs_retention_days',
        (string) PLUGIN_PAINELDEBORDO_RETENTION_DEFAULT
    );
    return ($raw < 0 || $raw > 3650) ? PLUGIN_PAINELDEBORDO_RETENTION_DEFAULT : $raw;
}

function plugin_paineldebordo_audit_set_retention_days(int $days): void
{
    if ($days < 0) {
        $days = 0;
    }
    if ($days > 3650) {
        $days = 3650;
    }
    plugin_paineldebordo_audit_config_set('logs_retention_days', (string) $days);
}

/**
 * Purge access-log rows older than the retention window. No-op when retention
 * is 0 (inherit GLPI). Throttled to at most once per hour (timestamp in config)
 * so it can be called cheaply on every plugin load.
 */
function plugin_paineldebordo_audit_purge(): void
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        return;
    }
    $days = plugin_paineldebordo_audit_retention_days();
    if ($days <= 0) {
        return;
    }
    $last = (int) plugin_paineldebordo_audit_config_get('logs_purge_at', '0');
    if ($last > 0 && (time() - $last) < 3600) {
        return;
    }
    plugin_paineldebordo_audit_config_set('logs_purge_at', (string) time());
    plugin_paineldebordo_audit_purge_now($days);
}

/**
 * Do the actual deletion for a retention window (days). Separate from the
 * throttled entry point so the GLPI cron task can call it directly.
 * Also drops stale presence rows (users who never came back).
 *
 * @return int rows deleted from the access log
 */
function plugin_paineldebordo_audit_purge_now(int $days): int
{
    global $DB;
    if (!isset($DB) || $days <= 0) {
        return 0;
    }
    $deleted = 0;
    $cutoff = $DB->escape(date('Y-m-d H:i:s', time() - $days * 86400));
    if ($DB->TableExists('glpi_plugin_paineldebordo_accesslog')) {
        $DB->doQuery("DELETE FROM glpi_plugin_paineldebordo_accesslog WHERE date_creation < '$cutoff'");
        if (method_exists($DB, 'affectedRows')) {
            $deleted = (int) $DB->affectedRows();
        }
    }
    if ($DB->TableExists('glpi_plugin_paineldebordo_presence')) {
        $DB->doQuery("DELETE FROM glpi_plugin_paineldebordo_presence WHERE last_seen < '$cutoff'");
    }
    return $deleted;
}
