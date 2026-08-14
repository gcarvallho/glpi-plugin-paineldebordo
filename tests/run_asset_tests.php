<?php

/**
 * Runtime tests for asset URLs + layout CSS (catches "texto sem estilo").
 *
 * Run: php tests/run_asset_tests.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/bootstrap_stub.php';
Plugin::$phpDir = $root;
Plugin::$webDir = '/glpi/plugins/paineldebordo';

$failed = 0;
$passed = 0;

function assert_true(bool $cond, string $msg): void
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "[PASS] $msg\n";
    } else {
        $failed++;
        echo "[FAIL] $msg\n";
    }
}

function assert_contains(string $haystack, string $needle, string $msg): void
{
    assert_true(str_contains($haystack, $needle), $msg);
}

// --- Static asset files ---
$cssPath = $root . '/public/css/dashboard-tokens.css';
$hcPath = $root . '/public/js/highcharts.js';
assert_true(is_file($cssPath), 'dashboard-tokens.css is_file');
$css = (string) @file_get_contents($cssPath);
assert_true(strlen($css) > 500, 'CSS tokens file not empty/tiny');
assert_contains($css, '.ho-app', 'CSS defines .ho-app');
assert_contains($css, '--ho-bg', 'CSS defines --ho-bg token');
assert_contains($css, '.ho-kpi', 'CSS defines .ho-kpi');
assert_true(is_file($hcPath), 'highcharts.js is_file');
assert_true(@filesize($hcPath) > 50000, 'highcharts.js looks like real build (>50KB)');

$home = (string) file_get_contents($root . '/public/home.php');
assert_contains($home, 'code.highcharts.com', 'home has CDN Highcharts fallback');
assert_contains($home, 'plugin_paineldebordo_asset_bases', 'home uses asset_bases');
assert_contains($home, 'ensureHighcharts', 'home ensureHighcharts present');
assert_contains($home, 'function renderAll', 'home renderAll charts present');

$chartShow = (string) file_get_contents($root . '/public/views/chart_show.php');
assert_contains($chartShow, 'Highcharts.chart', 'chart_show draws Highcharts.chart');
assert_contains($chartShow, 'code.highcharts.com', 'chart_show has CDN fallback');

$layoutSrc = (string) file_get_contents($root . '/inc/layout.inc.php');
assert_contains($layoutSrc, 'ho-tokens-inline', 'layout has inline CSS marker');
assert_contains($layoutSrc, 'plugin_paineldebordo_asset_bases', 'layout links dual asset bases');
assert_true(!preg_match('/href=["\']css\\/dashboard-tokens\\.css["\']/', $layoutSrc), 'layout does not use bare relative CSS href');
assert_contains($layoutSrc, 'ho-nav__icon', 'layout has nav icon class');
assert_true(is_file($root . '/inc/icons.inc.php'), 'icons.inc.php is_file');
require_once $root . '/inc/icons.inc.php';
$svg = plugin_paineldebordo_icon('home');
assert_contains($svg, '<svg', 'icon helper returns svg');
assert_contains($svg, 'ho-icon', 'icon has ho-icon class');

$tvSrc = (string) file_get_contents($root . '/public/tv.php');
assert_contains($tvSrc, 'ajax/tv_board.php', 'tv.php lists relative board endpoint');
assert_contains($tvSrc, 'boardEndpoints', 'tv.php uses boardEndpoints array');
assert_contains($tvSrc, 'ajax/tv_events.php', 'tv.php polls tv_events');
assert_contains($tvSrc, 'tv_toasts', 'tv.php has toast stack');
assert_contains($tvSrc, 'non-JSON', 'tv.php diagnoses non-JSON responses');
assert_contains($tvSrc, 'kpi_today', 'tv.php has today KPI');
assert_contains($tvSrc, 'kpi_validation', 'tv.php has validation KPI');
assert_contains($tvSrc, 'kpi_solution', 'tv.php has solution KPI');
assert_contains($tvSrc, 'ICON_VOLUME', 'tv.php volume toggle');
assert_contains($tvSrc, 'tv-card__tech', 'tv.php tech on cards');
assert_contains($tvSrc, 'pdb_tv_display', 'tv.php display prefs');
assert_contains($tvSrc, 'chime', 'tv.php chime');
assert_contains($tvSrc, 'View', 'tv.php View label');
assert_contains($tvSrc, 'sortTickets', 'tv.php sortTickets');
assert_contains($tvSrc, "sort: 'newest'", 'tv.php default newest sort');
assert_contains($tvSrc, 'PREF_ICONS', 'tv.php prefs icons');
assert_contains($tvSrc, 'prefIcon(key)', 'tv.php column title prefIcon');
assert_contains($tvSrc, 'tv-card__top', 'tv.php card top row');
assert_contains($tvSrc, 'tv-card__foot', 'tv.php card unified foot');
assert_contains($tvSrc, 'date_mod', 'tv.php date_mod field');
$vol = plugin_paineldebordo_icon('volume');
assert_contains($vol, '<svg', 'volume icon returns svg');
$fs = plugin_paineldebordo_icon('fullscreen');
assert_contains($fs, '<svg', 'fullscreen icon returns svg');
$ext = plugin_paineldebordo_icon('external_link');
assert_contains($ext, '<svg', 'external_link icon returns svg');
$cog = plugin_paineldebordo_icon('cog');
assert_contains($cog, 'circle', 'cog icon has gear circle');
assert_true(str_contains($cog, '19.4 15'), 'cog icon has gear path');
assert_true(!str_contains($cog, 'M12 3v2M12 19v2'), 'cog is not sun rays');

$tvPair = (string) file_get_contents($root . '/public/tv_pair.php');
assert_contains($tvPair, 'ho-tokens-inline', 'tv_pair inlines CSS');
$tv = (string) file_get_contents($root . '/public/tv.php');
assert_contains($tv, 'ho-tokens-inline', 'tv.php inlines CSS');

// --- Execute asset helpers ---
$_SERVER['SCRIPT_NAME'] = '/glpi/plugins/paineldebordo/public/shell.php';
require_once $root . '/inc/layout.inc.php';

$base = plugin_paineldebordo_asset_base();
assert_true(
    $base === '/glpi/plugins/paineldebordo/public',
    'asset_base from shell.php SCRIPT_NAME → …/public (got ' . $base . ')'
);

$url = plugin_paineldebordo_asset_url('css/dashboard-tokens.css');
assert_true(str_starts_with($url, '/'), 'asset_url is absolute path');
assert_contains($url, 'dashboard-tokens.css', 'asset_url ends with css file');

$bases = plugin_paineldebordo_asset_bases();
assert_true(count($bases) >= 2, 'asset_bases returns multiple candidates');
$joined = implode('|', $bases);
assert_true(str_contains($joined, 'paineldebordo'), 'asset_bases include plugin path');

ob_start();
plugin_paineldebordo_page_start(['title' => 'Test', 'active' => 'home']);
$htmlStart = (string) ob_get_clean();
ob_start();
plugin_paineldebordo_page_end();
$htmlEnd = (string) ob_get_clean();
$html = $htmlStart . $htmlEnd;

assert_contains($html, 'ho-tokens-inline', 'page_start emits inline tokens style');
assert_contains($html, '.ho-app', 'inline CSS payload includes .ho-app rules');
assert_contains($html, '--ho-bg', 'inline CSS payload includes --ho-bg');
assert_contains($html, 'dashboard-tokens.css', 'page_start also emits link candidates');
assert_contains($html, 'ho-app', 'page_start emits app chrome');
assert_contains($html, 'Painel de', 'page_start emits brand');
assert_true(
    !preg_match('/<link[^>]+href=["\']css\\/dashboard-tokens\\.css["\']/', $html),
    'rendered HTML has no bare relative CSS link'
);

require_once $root . '/inc/services/charts.php';
$pub = plugin_paineldebordo_public_url('js/highcharts.js');
assert_true(str_starts_with($pub, '/'), 'public_url absolute');
assert_contains($pub, 'highcharts.js', 'public_url targets highcharts');

$php = PHP_BINARY;
$scenarios = [
    [
        'name'   => 'marketplace shell without /public',
        'web'    => '/glpi/marketplace/paineldebordo',
        'script' => '/glpi/marketplace/paineldebordo/shell.php',
        'expect' => '/glpi/marketplace/paineldebordo',
    ],
    [
        'name'   => 'getWebDir already ends with /public',
        'web'    => '/glpi/plugins/paineldebordo/public',
        'script' => '/unrelated/index.php',
        'expect' => '/glpi/plugins/paineldebordo/public',
    ],
    [
        'name'   => 'classic public/tv.php',
        'web'    => '/glpi/plugins/paineldebordo',
        'script' => '/glpi/plugins/paineldebordo/public/tv.php',
        'expect' => '/glpi/plugins/paineldebordo/public',
    ],
];

foreach ($scenarios as $i => $sc) {
    $lines = [
        '<?php',
        "require_once dirname(__FILE__) . '/bootstrap_stub.php';",
        'Plugin::$phpDir = dirname(__DIR__);',
        'Plugin::$webDir = ' . var_export($sc['web'], true) . ';',
        "\$GLOBALS['CFG_GLPI']['root_doc'] = '/glpi';",
        '$_SERVER[\'SCRIPT_NAME\'] = ' . var_export($sc['script'], true) . ';',
        "require_once dirname(__DIR__) . '/inc/layout.inc.php';",
        'echo plugin_paineldebordo_asset_base();',
    ];
    $probe = __DIR__ . '/_probe_' . $i . '.php';
    file_put_contents($probe, implode("\n", $lines) . "\n");
    $out = [];
    $exit = 0;
    $cmd = escapeshellarg($php) . ' -n ' . escapeshellarg($probe);
    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd .= ' 2>NUL';
    } else {
        $cmd .= ' 2>/dev/null';
    }
    exec($cmd, $out, $exit);
    @unlink($probe);
    $got = '';
    foreach ($out as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, 'PHP Warning:') || str_starts_with($line, 'PHP Notice:')) {
            continue;
        }
        $got = $line;
    }
    assert_true(
        $exit === 0 && $got === $sc['expect'],
        $sc['name'] . ' (expected ' . $sc['expect'] . ', got ' . $got . ')'
    );
}

echo "\n---\nAsset passed: $passed  Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
