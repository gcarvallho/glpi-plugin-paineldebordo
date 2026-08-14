<?php

/**
 * Device-code pairing for Modo TV (streaming-style).
 */

function plugin_paineldebordo_tv_pair_ensure_table(): void
{
    global $DB;
    if (!$DB->TableExists('glpi_plugin_paineldebordo_tv_devices')) {
        $DB->doQuery("CREATE TABLE IF NOT EXISTS `glpi_plugin_paineldebordo_tv_devices` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `code` varchar(16) NOT NULL,
          `token_hash` varchar(64) DEFAULT NULL,
          `token_plain` varchar(80) DEFAULT NULL,
          `users_id` int unsigned DEFAULT NULL,
          `status` varchar(16) NOT NULL DEFAULT 'pending',
          `entity_ids` varchar(255) NOT NULL DEFAULT '',
          `group_ids` varchar(255) NOT NULL DEFAULT '',
          `sees_all` tinyint(1) NOT NULL DEFAULT 0,
          `expires_at` datetime NOT NULL,
          `linked_at` datetime DEFAULT NULL,
          `last_seen` datetime DEFAULT NULL,
          `date_creation` datetime NOT NULL,
          `device_label` varchar(120) DEFAULT NULL,
          `user_agent` varchar(255) DEFAULT NULL,
          `remote_ip` varchar(45) DEFAULT NULL,
          `timezone` varchar(64) DEFAULT NULL,
          `nickname` varchar(80) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `code` (`code`),
          KEY `token_hash` (`token_hash`),
          KEY `users_id` (`users_id`),
          KEY `status_expires` (`status`,`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return;
    }
    $cols = [
        'token_plain'  => "ADD COLUMN `token_plain` varchar(80) DEFAULT NULL",
        'device_label' => "ADD COLUMN `device_label` varchar(120) DEFAULT NULL",
        'user_agent'   => "ADD COLUMN `user_agent` varchar(255) DEFAULT NULL",
        'remote_ip'    => "ADD COLUMN `remote_ip` varchar(45) DEFAULT NULL",
        'timezone'     => "ADD COLUMN `timezone` varchar(64) DEFAULT NULL",
        'nickname'     => "ADD COLUMN `nickname` varchar(80) DEFAULT NULL",
    ];
    foreach ($cols as $name => $ddl) {
        if (!$DB->fieldExists('glpi_plugin_paineldebordo_tv_devices', $name)) {
            $DB->doQuery('ALTER TABLE glpi_plugin_paineldebordo_tv_devices ' . $ddl);
        }
    }
}

/** Allowed pairing TTL values (seconds). */
function plugin_paineldebordo_tv_pair_ttl_options(): array
{
    return [180, 300, 600];
}

function plugin_paineldebordo_tv_pair_ttl_seconds(): int
{
    global $DB;
    $raw = 300;
    // Global setting (users_id=0) — TV create has no session.
    if (isset($DB) && $DB->TableExists('glpi_plugin_paineldebordo_config')) {
        $res = $DB->doQuery(
            "SELECT value FROM glpi_plugin_paineldebordo_config
             WHERE name = 'tv_pair_ttl' AND users_id = 0 LIMIT 1"
        );
        if ($res && $DB->numrows($res) > 0) {
            $raw = (int) $DB->result($res, 0, 'value');
        }
    }
    $opts = plugin_paineldebordo_tv_pair_ttl_options();
    return in_array($raw, $opts, true) ? $raw : 300;
}

/** Persist pairing TTL globally (hub setting for all TVs). */
function plugin_paineldebordo_tv_pair_set_ttl(int $ttl): void
{
    global $DB;
    $opts = plugin_paineldebordo_tv_pair_ttl_options();
    if (!in_array($ttl, $opts, true)) {
        $ttl = 300;
    }
    if (!$DB->TableExists('glpi_plugin_paineldebordo_config')) {
        return;
    }
    $val = (string) $ttl;
    $exists = $DB->doQuery(
        "SELECT id FROM glpi_plugin_paineldebordo_config WHERE name = 'tv_pair_ttl' AND users_id = 0 LIMIT 1"
    );
    if ($exists && $DB->numrows($exists) > 0) {
        $DB->doQuery(
            "UPDATE glpi_plugin_paineldebordo_config SET value = '" . $DB->escape($val) . "'
             WHERE name = 'tv_pair_ttl' AND users_id = 0"
        );
    } else {
        $DB->doQuery(
            "INSERT INTO glpi_plugin_paineldebordo_config (name, value, users_id)
             VALUES ('tv_pair_ttl', '" . $DB->escape($val) . "', 0)"
        );
    }
}

/**
 * Lightweight UA → "Browser · OS" (no external lib).
 */
function plugin_paineldebordo_tv_parse_user_agent(?string $ua): string
{
    $ua = trim((string) $ua);
    if ($ua === '') {
        return __('Unknown device', 'paineldebordo');
    }
    $browser = __('Browser', 'paineldebordo');
    if (preg_match('/Edg\//i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Chromium/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox\//i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome|Chromium|Edg/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/MSIE|Trident/i', $ua)) {
        $browser = 'IE';
    }

    $os = __('OS', 'paineldebordo');
    if (preg_match('/Android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/Windows NT/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/CrOS/i', $ua)) {
        $os = 'ChromeOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    } elseif (preg_match('/Smart-TV|SmartTV|HbbTV|Web0S|Tizen|BRAVIA|AppleTV/i', $ua)) {
        $os = 'Smart TV';
    }

    return $browser . ' · ' . $os;
}

function plugin_paineldebordo_tv_remote_ip(): string
{
    if (class_exists('Toolbox') && method_exists('Toolbox', 'getRemoteIpAddress')) {
        $ip = (string) Toolbox::getRemoteIpAddress();
        if ($ip !== '') {
            return mb_substr($ip, 0, 45);
        }
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return mb_substr($ip, 0, 45);
}

function plugin_paineldebordo_tv_pair_normalize_timezone(?string $tz): string
{
    $tz = trim((string) $tz);
    if ($tz === '' || strlen($tz) > 64) {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9_+\-\/]+$/', $tz)) {
        return '';
    }
    return $tz;
}

function plugin_paineldebordo_tv_pair_normalize_code(string $code): string
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if (strlen($code) === 8) {
        return substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }
    return $code;
}

function plugin_paineldebordo_tv_pair_generate_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = '';
    for ($i = 0; $i < 8; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
}

function plugin_paineldebordo_tv_pair_cleanup(): void
{
    global $DB;
    if (!$DB->TableExists('glpi_plugin_paineldebordo_tv_devices')) {
        return;
    }
    $now = $DB->escape(date('Y-m-d H:i:s'));
    $DB->doQuery("UPDATE glpi_plugin_paineldebordo_tv_devices SET status = 'expired'
        WHERE status = 'pending' AND expires_at < '$now'");

    // Hard-delete pending codes that have been expired for a full extra TTL
    // window — enough time for a TV still polling to have already seen
    // status='expired' before the row disappears from the admin list.
    $ttl = plugin_paineldebordo_tv_pair_ttl_seconds();
    $stale_before = $DB->escape(date('Y-m-d H:i:s', time() - $ttl));
    $DB->doQuery("DELETE FROM glpi_plugin_paineldebordo_tv_devices
        WHERE status = 'expired' AND expires_at < '$stale_before'");
}

/**
 * @param array{timezone?:string,user_agent?:string} $meta
 * @return array{id:int,code:string,expires_at:string,ttl_sec:int}
 */
function plugin_paineldebordo_tv_pair_create(array $meta = []): array
{
    global $DB;
    plugin_paineldebordo_tv_pair_ensure_table();
    plugin_paineldebordo_tv_pair_cleanup();

    $ttl = plugin_paineldebordo_tv_pair_ttl_seconds();
    $now = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', time() + $ttl);

    $ua = (string) ($meta['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $ua = mb_substr($ua, 0, 255);
    $label = plugin_paineldebordo_tv_parse_user_agent($ua);
    $ip = plugin_paineldebordo_tv_remote_ip();
    $tz = plugin_paineldebordo_tv_pair_normalize_timezone($meta['timezone'] ?? null);

    // Reopening the pairing screen on the same device before the previous code
    // expired reuses that code (and renews its TTL) instead of stacking a new
    // pending row every reload.
    if ($ip !== '' && $ua !== '') {
        $existing = $DB->doQuery(
            "SELECT id, code FROM glpi_plugin_paineldebordo_tv_devices
             WHERE status = 'pending' AND expires_at > '" . $DB->escape($now) . "'
               AND remote_ip = '" . $DB->escape($ip) . "' AND user_agent = '" . $DB->escape($ua) . "'
             ORDER BY id DESC LIMIT 1"
        );
        if ($existing && $DB->numrows($existing) > 0) {
            $row = $DB->fetchAssoc($existing);
            $DB->doQuery(
                "UPDATE glpi_plugin_paineldebordo_tv_devices SET expires_at = '" . $DB->escape($expires) . "'
                 WHERE id = " . (int) $row['id']
            );
            return [
                'id'         => (int) $row['id'],
                'code'       => (string) $row['code'],
                'expires_at' => $expires,
                'ttl_sec'    => $ttl,
                'expires_in' => $ttl,
            ];
        }
    }

    $code = plugin_paineldebordo_tv_pair_generate_code();
    for ($tries = 0; $tries < 5; $tries++) {
        $exists = $DB->doQuery("SELECT id FROM glpi_plugin_paineldebordo_tv_devices WHERE code = '" . $DB->escape($code) . "' LIMIT 1");
        if (!$exists || $DB->numrows($exists) === 0) {
            break;
        }
        $code = plugin_paineldebordo_tv_pair_generate_code();
    }

    $DB->doQuery(
        "INSERT INTO glpi_plugin_paineldebordo_tv_devices
        (code, status, expires_at, date_creation, device_label, user_agent, remote_ip, timezone)
        VALUES (
          '" . $DB->escape($code) . "',
          'pending',
          '" . $DB->escape($expires) . "',
          '" . $DB->escape($now) . "',
          '" . $DB->escape($label) . "',
          '" . $DB->escape($ua) . "',
          '" . $DB->escape($ip) . "',
          " . ($tz !== '' ? ("'" . $DB->escape($tz) . "'") : 'NULL') . "
        )"
    );

    return [
        'id'         => (int) $DB->insertId(),
        'code'       => $code,
        'expires_at' => $expires,
        'ttl_sec'    => $ttl,
        'expires_in' => $ttl,
    ];
}

function plugin_paineldebordo_tv_pair_approve(string $code): bool
{
    global $DB;
    plugin_paineldebordo_tv_pair_ensure_table();
    plugin_paineldebordo_tv_pair_cleanup();

    if (!function_exists('plugin_paineldebordo_canRead') || !plugin_paineldebordo_canRead() || !isset($_SESSION['glpiID'])) {
        return false;
    }

    $code = plugin_paineldebordo_tv_pair_normalize_code($code);
    $res = $DB->doQuery(
        "SELECT id FROM glpi_plugin_paineldebordo_tv_devices
         WHERE code = '" . $DB->escape($code) . "' AND status = 'pending'
           AND expires_at >= '" . $DB->escape(date('Y-m-d H:i:s')) . "' LIMIT 1"
    );
    if (!$res || $DB->numrows($res) === 0) {
        return false;
    }
    $id = (int) $DB->result($res, 0, 'id');

    // Build scope snapshot WITHOUT assertGroupAccess (avoids displayErrorAndDie → "Access denied")
    include_once __DIR__ . '/filters.inc.php';
    include_once __DIR__ . '/access.inc.php';

    $sel_ent = plugin_paineldebordo_getConfigValue('entity', '');
    if ($sel_ent === '' || $sel_ent === '-1') {
        $entities = $_SESSION['glpiactiveentities'] ?? [];
        $entity_ids = $entities ? implode(',', array_map('intval', (array) $entities)) : '0';
    } else {
        $entity_ids = preg_replace('/[^0-9,]/', '', $sel_ent) ?: '0';
    }

    $sees_all = plugin_paineldebordo_seesAllGroups() ? 1 : 0;
    $group_ids = '';
    if (!$sees_all) {
        $allowed = plugin_paineldebordo_getUserGroupIds();
        $gid = (int) plugin_paineldebordo_getConfigValue('filter_group', '0');
        if ($gid > 0 && in_array($gid, $allowed, true)) {
            $group_ids = (string) $gid;
        } else {
            $group_ids = implode(',', $allowed);
        }
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $now = date('Y-m-d H:i:s');

    $ok = $DB->doQuery(
        "UPDATE glpi_plugin_paineldebordo_tv_devices SET
            status = 'linked',
            users_id = " . (int) $_SESSION['glpiID'] . ",
            token_hash = '" . $DB->escape($hash) . "',
            token_plain = '" . $DB->escape($token) . "',
            entity_ids = '" . $DB->escape($entity_ids) . "',
            group_ids = '" . $DB->escape($group_ids) . "',
            sees_all = $sees_all,
            linked_at = '" . $DB->escape($now) . "',
            last_seen = '" . $DB->escape($now) . "'
         WHERE id = $id"
    );
    return (bool) $ok;
}

/**
 * @return array{status:string,token?:string,expires_at?:string}|null
 */
function plugin_paineldebordo_tv_pair_poll(string $code): ?array
{
    global $DB;
    plugin_paineldebordo_tv_pair_ensure_table();
    plugin_paineldebordo_tv_pair_cleanup();

    $code = plugin_paineldebordo_tv_pair_normalize_code($code);
    $res = $DB->doQuery(
        "SELECT id, status, expires_at, token_plain FROM glpi_plugin_paineldebordo_tv_devices
         WHERE code = '" . $DB->escape($code) . "' LIMIT 1"
    );
    if (!$res || $DB->numrows($res) === 0) {
        return null;
    }
    $row = $DB->fetchAssoc($res);
    $out = [
        'status'     => (string) $row['status'],
        'expires_at' => (string) $row['expires_at'],
    ];
    if ($row['status'] === 'linked' && !empty($row['token_plain'])) {
        $out['token'] = (string) $row['token_plain'];
        $DB->doQuery('UPDATE glpi_plugin_paineldebordo_tv_devices SET token_plain = NULL WHERE id = ' . (int) $row['id']);
    }
    return $out;
}

/**
 * @return array{users_id:int,entity_ids:string,group_ids:string,sees_all:bool,id:int}|null
 */
function plugin_paineldebordo_tv_pair_auth_token(?string $token): ?array
{
    global $DB;
    if ($token === null || $token === '') {
        return null;
    }
    plugin_paineldebordo_tv_pair_ensure_table();
    $hash = hash('sha256', $token);
    $res = $DB->doQuery(
        "SELECT id, users_id, entity_ids, group_ids, sees_all FROM glpi_plugin_paineldebordo_tv_devices
         WHERE token_hash = '" . $DB->escape($hash) . "' AND status = 'linked' LIMIT 1"
    );
    if (!$res || $DB->numrows($res) === 0) {
        return null;
    }
    $row = $DB->fetchAssoc($res);
    $DB->doQuery(
        "UPDATE glpi_plugin_paineldebordo_tv_devices SET last_seen = '" . $DB->escape(date('Y-m-d H:i:s')) . "'
         WHERE id = " . (int) $row['id']
    );
    return [
        'id'         => (int) $row['id'],
        'users_id'   => (int) $row['users_id'],
        'entity_ids' => (string) $row['entity_ids'],
        'group_ids'  => (string) $row['group_ids'],
        'sees_all'   => (bool) $row['sees_all'],
    ];
}

/**
 * @param array{entity_ids:string,group_ids:string,sees_all:bool} $device
 * @return array{entity_sql:string,entity_ids:string,group_join:string,group_where:string}
 */
function plugin_paineldebordo_tv_pair_scope(array $device): array
{
    $ent = preg_replace('/[^0-9,]/', '', $device['entity_ids'] ?? '') ?: '0';
    $entity_sql = "AND glpi_tickets.entities_id IN ($ent)";
    $group_join = '';
    $group_where = '';
    if (empty($device['sees_all'])) {
        $gids = preg_replace('/[^0-9,]/', '', $device['group_ids'] ?? '');
        if ($gids !== '') {
            $group_join = ' INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = glpi_tickets.id AND gt.type = 2 ';
            $group_where = " AND gt.groups_id IN ($gids) ";
        } else {
            $group_where = ' AND 0=1 ';
        }
    }
    return [
        'entity_sql'  => $entity_sql,
        'entity_ids'  => $ent,
        'group_join'  => $group_join,
        'group_where' => $group_where,
    ];
}

function plugin_paineldebordo_tv_pair_revoke(int $id): void
{
    global $DB;
    plugin_paineldebordo_tv_pair_ensure_table();
    $uid = (int) ($_SESSION['glpiID'] ?? 0);
    if ($uid <= 0 || $id <= 0) {
        return;
    }
    // UPDATE can revoke any device; READ only own
    if (function_exists('plugin_paineldebordo_canConfigure') && plugin_paineldebordo_canConfigure()) {
        $DB->doQuery(
            "UPDATE glpi_plugin_paineldebordo_tv_devices SET status = 'revoked', token_hash = NULL, token_plain = NULL
             WHERE id = $id"
        );
        return;
    }
    $DB->doQuery(
        "UPDATE glpi_plugin_paineldebordo_tv_devices SET status = 'revoked', token_hash = NULL, token_plain = NULL
         WHERE id = $id AND users_id = $uid"
    );
}

/**
 * Permanently delete a device row (config / Super-Admin only).
 */
function plugin_paineldebordo_tv_pair_delete(int $id): bool
{
    global $DB;
    if ($id <= 0) {
        return false;
    }
    if (!function_exists('plugin_paineldebordo_isSuperAdmin') || !plugin_paineldebordo_isSuperAdmin()) {
        return false;
    }
    plugin_paineldebordo_tv_pair_ensure_table();
    if (!$DB->TableExists('glpi_plugin_paineldebordo_tv_devices')) {
        return false;
    }
    $DB->doQuery("DELETE FROM glpi_plugin_paineldebordo_tv_devices WHERE id = $id");
    return true;
}

/**
 * Set display nickname (Configuration hub).
 */
function plugin_paineldebordo_tv_pair_set_nickname(int $id, string $nickname): bool
{
    global $DB;
    if ($id <= 0) {
        return false;
    }
    if (!function_exists('plugin_paineldebordo_canConfigure') || !plugin_paineldebordo_canConfigure()) {
        return false;
    }
    plugin_paineldebordo_tv_pair_ensure_table();
    $nickname = trim(mb_substr($nickname, 0, 80));
    if ($nickname === '') {
        $DB->doQuery("UPDATE glpi_plugin_paineldebordo_tv_devices SET nickname = NULL WHERE id = $id");
    } else {
        $DB->doQuery(
            "UPDATE glpi_plugin_paineldebordo_tv_devices SET nickname = '" . $DB->escape($nickname) . "' WHERE id = $id"
        );
    }
    return true;
}

/**
 * Human-readable status label (msgid → gettext).
 */
function plugin_paineldebordo_tv_pair_status_label(string $status): string
{
    switch ($status) {
        case 'linked':
            return __('Linked', 'paineldebordo');
        case 'pending':
            return __('Pending', 'paineldebordo');
        case 'revoked':
            return __('Revoked', 'paineldebordo');
        case 'expired':
            return __('Expired', 'paineldebordo');
        default:
            return $status;
    }
}

/**
 * Revoke the device that owns this bearer token (no GLPI session required).
 */
function plugin_paineldebordo_tv_pair_revoke_by_token(?string $token): bool
{
    global $DB;
    $device = plugin_paineldebordo_tv_pair_auth_token($token);
    if ($device === null) {
        return false;
    }
    $id = (int) $device['id'];
    plugin_paineldebordo_tv_pair_ensure_table();
    $DB->doQuery(
        "UPDATE glpi_plugin_paineldebordo_tv_devices SET status = 'revoked', token_hash = NULL, token_plain = NULL
         WHERE id = $id AND status = 'linked'"
    );
    return true;
}

/**
 * Resolve TV auth: device token, or session when no token was sent.
 * If a token was sent and is invalid/revoked → forbidden (no session fallback).
 *
 * @return array{ok:bool,device:?array,session:bool}
 */
function plugin_paineldebordo_tv_resolve_auth(): array
{
    $token = plugin_paineldebordo_tv_extract_bearer();
    $token_sent = ($token !== null && $token !== '');
    $device = plugin_paineldebordo_tv_pair_auth_token($token);

    if ($device !== null) {
        return ['ok' => true, 'device' => $device, 'session' => false];
    }
    if ($token_sent) {
        return ['ok' => false, 'device' => null, 'session' => false];
    }
    if (function_exists('plugin_paineldebordo_canRead') && plugin_paineldebordo_canRead()) {
        return ['ok' => true, 'device' => null, 'session' => true];
    }
    return ['ok' => false, 'device' => null, 'session' => false];
}

/**
 * @return array<int, array<string,mixed>>
 */
function plugin_paineldebordo_tv_pair_list_for_user(int $users_id): array
{
    global $DB;
    plugin_paineldebordo_tv_pair_ensure_table();
    if ($users_id <= 0) {
        return [];
    }
    $out = [];
    $res = $DB->doQuery(
        "SELECT id, code, status, linked_at, last_seen, users_id FROM glpi_plugin_paineldebordo_tv_devices
         WHERE users_id = $users_id AND status IN ('linked','revoked','pending')
         ORDER BY id DESC LIMIT 20"
    );
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * All TV devices (Configuration hub — requires UPDATE).
 *
 * @return array<int, array<string,mixed>>
 */
function plugin_paineldebordo_tv_pair_list_all(int $limit = 50): array
{
    global $DB;
    plugin_paineldebordo_tv_pair_ensure_table();
    plugin_paineldebordo_tv_pair_cleanup();
    $limit = max(1, min(200, $limit));
    $out = [];
    $res = $DB->doQuery(
        "SELECT d.id, d.code, d.status, d.linked_at, d.last_seen, d.users_id, d.expires_at,
                d.device_label, d.remote_ip, d.timezone, d.nickname,
                u.name AS user_name
         FROM glpi_plugin_paineldebordo_tv_devices d
         LEFT JOIN glpi_users u ON u.id = d.users_id
         WHERE d.status IN ('linked','pending','revoked')
         ORDER BY d.id DESC LIMIT $limit"
    );
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $out[] = $row;
        }
    }
    return $out;
}

function plugin_paineldebordo_tv_extract_bearer(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (is_string($hdr) && preg_match('/^Bearer\s+(\S+)/i', $hdr, $m)) {
        return $m[1];
    }
    if (!empty($_GET['tv_token']) && is_string($_GET['tv_token'])) {
        return $_GET['tv_token'];
    }
    return null;
}
