<?php

/**
 * ACL / TV contract tests (no GLPI session required).
 *
 * Run: php tests/run_acl_tests.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = 0;
$passed = 0;

function acl_assert(bool $ok, string $msg): void
{
    global $failed, $passed;
    if ($ok) {
        echo "[PASS] $msg\n";
        $passed++;
    } else {
        echo "[FAIL] $msg\n";
        $failed++;
    }
}

// Stubs if GLPI constants missing (CLI without includes.php)
if (!defined('READ')) {
    define('READ', 1);
}
if (!defined('UPDATE')) {
    define('UPDATE', 2);
}

// Minimal Session stub so access.inc.php can load (functions that call Session are not invoked)
if (!class_exists('Session', false)) {
    class Session
    {
        public static function checkLoginUser(): void
        {
        }
        public static function checkRight($right, $bit): void
        {
        }
        public static function haveRight($right, $bit): bool
        {
            return false;
        }
    }
}
if (!class_exists('Html', false)) {
    class Html
    {
        public static function displayRightError(): void
        {
        }
    }
}

require_once $root . '/inc/access.inc.php';

acl_assert(defined('PLUGIN_PAINELDEBORDO_RIGHT'), 'PLUGIN_PAINELDEBORDO_RIGHT defined');
acl_assert(defined('PLUGIN_PAINELDEBORDO_RIGHT_TICKETS'), 'TICKETS right constant');
acl_assert(defined('PLUGIN_PAINELDEBORDO_RIGHT_ANALYSIS'), 'ANALYSIS right constant');
acl_assert(defined('PLUGIN_PAINELDEBORDO_RIGHT_RESOURCES'), 'RESOURCES right constant');

$mods = plugin_paineldebordo_module_rights();
acl_assert(isset($mods['tickets'], $mods['analysis'], $mods['resources']), 'module_rights has 3 keys');
acl_assert(count($mods) === 3, 'module_rights exactly 3 modules');
acl_assert($mods['tickets'] === 'plugin_paineldebordo_tickets', 'tickets field name');
acl_assert($mods['analysis'] === 'plugin_paineldebordo_analysis', 'analysis field name');
acl_assert($mods['resources'] === 'plugin_paineldebordo_resources', 'resources field name');

$map = [
    'tickets'   => 'tickets',
    'by_group'  => 'tickets',
    'by_entity' => 'tickets',
    'charts'    => 'analysis',
    'chart'     => 'analysis',
    'reports'   => 'analysis',
    'report'    => 'analysis',
    'metrics'   => 'analysis',
    'map'       => 'resources',
    'assets'    => 'resources',
    'config'    => null,
    'home'      => null,
    'tv'        => null,
    'setup'     => null,
    ''          => null,
    'nope'      => null,
];
foreach ($map as $page => $expect) {
    $got = plugin_paineldebordo_page_module($page);
    acl_assert($got === $expect, "page_module('$page') => " . var_export($expect, true));
}

$shell = (string) file_get_contents($root . '/public/shell.php');
acl_assert(str_contains($shell, 'plugin_paineldebordo_page_module'), 'shell uses page_module');
acl_assert(str_contains($shell, 'Only Super-Admin can open Configuration'), 'shell Super-Admin msgid');
acl_assert(str_contains($shell, 'You do not have permission for this module'), 'shell module deny msgid');
acl_assert(str_contains($shell, 'canConfigure()'), 'shell config via canConfigure');
acl_assert(!preg_match("/page === 'config'[\\s\\S]{0,200}checkAccess\\(UPDATE\\)/", $shell), 'shell config not checkAccess(UPDATE)');

$profile = (string) file_get_contents($root . '/inc/profile.class.php');
acl_assert(str_contains($profile, 'migrateModuleRights'), 'profile migrateModuleRights');
acl_assert(str_contains($profile, 'plugin_paineldebordo_tickets'), 'profile tickets field');
acl_assert(str_contains($profile, 'getReadUpdateRights'), 'profile READ/UPDATE matrix helper');
acl_assert(str_contains($profile, 'getReadOnlyRights'), 'profile tickets READ-only helper');
acl_assert(!preg_match('/^\s*ProfileRight::addProfileRights\s*\(/m', $profile), 'migrate does not call addProfileRights');
acl_assert(str_contains($profile, 'catch (Throwable'), 'migrate catches Throwable');
acl_assert(str_contains($profile, 'Do NOT call ProfileRight::addProfileRights'), 'migrate documents 1062 avoid');

$access = (string) file_get_contents($root . '/inc/access.inc.php');
acl_assert(str_contains($access, 'isSuperAdminStrict'), 'access isSuperAdminStrict');
acl_assert(str_contains($access, 'requireModuleJson'), 'access requireModuleJson');
acl_assert(str_contains($access, 'hasWideVision'), 'access hasWideVision');
acl_assert(str_contains($access, 'PLUGIN_PAINELDEBORDO_RIGHT_GROUPS'), 'access groups right constant');
acl_assert(!preg_match('/function plugin_paineldebordo_hasWideVision[\s\S]*?haveRight\(PLUGIN_PAINELDEBORDO_RIGHT,\s*UPDATE\)/', $access), 'wide vision not master UPDATE');
acl_assert(str_contains($profile, 'plugin_paineldebordo_groups'), 'profile groups field');
acl_assert(str_contains($profile, 'createMinimalAccess'), 'profile minimal bootstrap');

$ajaxGates = [
    'public/ajax/bi_board.php'        => "requireModuleJson('analysis', READ)",
    'public/ajax/bi_layout.php'       => "requireModuleJson('analysis', UPDATE)",
    'public/ajax/overview_layout.php' => "requireModuleJson('analysis', UPDATE)",
    'public/ajax/assets_board.php'    => "requireModuleJson('resources', READ)",
    'public/ajax/assets_list.php'     => "requireModuleJson('resources', READ)",
    'public/ajax/assets_item.php'     => "requireModuleJson('resources', READ)",
    'public/ajax/map_coord.php'       => "requireModuleJson('resources', UPDATE)",
];
foreach ($ajaxGates as $rel => $needle) {
    $src = (string) file_get_contents($root . '/' . $rel);
    acl_assert(str_contains($src, $needle), "$rel gates $needle");
}

$ovBoard = (string) file_get_contents($root . '/public/ajax/overview_board.php');
acl_assert(str_contains($ovBoard, 'checkAccessJson(READ)'), 'overview_board master READ (JSON-safe)');
acl_assert(!str_contains($ovBoard, 'requireModuleJson'), 'overview_board no module gate');

$biLayout = (string) file_get_contents($root . '/public/ajax/bi_layout.php');
acl_assert(str_contains($biLayout, "requireModuleJson('analysis', READ)"), 'bi_layout GET analysis READ');

$tv = (string) file_get_contents($root . '/public/tv.php');
acl_assert(str_contains($tv, 'rememberMute: false, muted: false'), 'tv defaults unmuted');
acl_assert(str_contains($tv, 'rememberMute ? !!prefs.audio.muted : false'), 'tv mute init not forced true');
acl_assert(str_contains($tv, 'function pushTipToast'), 'tv pushTipToast');
acl_assert(str_contains($tv, 'pushTipToast(I18N.muteTip, undefined, 3000)'), 'tv tip every load 3s');
acl_assert(str_contains($tv, 'function toggleAudioFromUser'), 'tv toggleAudioFromUser');
acl_assert(str_contains($tv, 'ctrlKey'), 'tv CTRL+M');
acl_assert(str_contains($tv, 'CTRL+M turns sound on or off'), 'tv mute tip msgid');

$layout = (string) file_get_contents($root . '/inc/layout.inc.php');
acl_assert(str_contains($layout, "canModule('tickets'"), 'nav filters tickets module');
acl_assert(str_contains($layout, "canModule('analysis'"), 'nav filters analysis module');
acl_assert(str_contains($layout, "canModule('resources'"), 'nav filters resources module');
acl_assert(str_contains($layout, 'canConfigure()'), 'nav Admin canConfigure');

acl_assert(is_file($root . '/docs/QA_ADVERSARIAL.md'), 'QA_ADVERSARIAL.md exists');
acl_assert(is_file($root . '/docs/PERMISSIONS.md'), 'PERMISSIONS.md exists');
$qa = (string) file_get_contents($root . '/docs/QA_ADVERSARIAL.md');
acl_assert(str_contains($qa, 'P8'), 'QA doc has persona P8');
acl_assert(str_contains($qa, 'map_coord'), 'QA doc has map_coord matrix');
acl_assert(str_contains($qa, 'CTRL+M'), 'QA doc has CTRL+M cases');

echo "\n---\nPassed: $passed  Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
