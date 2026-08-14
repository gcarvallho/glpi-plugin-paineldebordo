<?php
declare(strict_types=1);

/**
 * Standalone unit tests for Painel de Bordo (no GLPI bootstrap required).
 *
 * Run: php tests/run_unit_tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/inc/tv.inc.php';

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

function assert_eq($expected, $actual, string $msg): void
{
    assert_true($expected === $actual, $msg . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

// Catalog
$catalog = plugin_paineldebordo_tv_event_catalog();
assert_true(isset($catalog['novo'], $catalog['reabertura'], $catalog['solucao_negada'], $catalog['solucao_aceita'], $catalog['atualizacao'], $catalog['sla_atrasado']), 'catalog has core event types');
assert_true($catalog['solucao_negada']['priority'] > $catalog['novo']['priority'], 'denied solution outranks new ticket');
assert_true($catalog['solucao_aceita']['priority'] > $catalog['novo']['priority'], 'accepted solution outranks new ticket');
assert_true(in_array('solucao_aceita', plugin_paineldebordo_tv_toast_types(), true), 'toast types include solucao_aceita');
assert_true(in_array('solucao_negada', plugin_paineldebordo_tv_toast_types(), true), 'toast types include solucao_negada');
assert_true(in_array('sla_atrasado', plugin_paineldebordo_tv_toast_types(), true), 'toast types include sla_atrasado');
assert_true(!in_array('atualizacao', plugin_paineldebordo_tv_toast_types(), true), 'toast types exclude atualizacao');
assert_true(function_exists('plugin_paineldebordo_tv_date_label'), 'tv_date_label exists');
assert_true(function_exists('plugin_paineldebordo_tv_observers_sql'), 'tv_observers_sql exists');
assert_true(function_exists('plugin_paineldebordo_tv_validation_waiting_sql'), 'validation_waiting_sql exists');
assert_true(function_exists('plugin_paineldebordo_tv_solution_waiting_sql'), 'solution_waiting_sql exists');
assert_eq('22/07 09:15', plugin_paineldebordo_tv_date_label('2026-07-22 09:15:00'), 'date_label format');
assert_true(str_contains(plugin_paineldebordo_tv_observers_sql(), 'uo.name'), 'observers fallback to login name');
assert_true(str_contains(plugin_paineldebordo_tv_observers_sql(), 'groups_tickets'), 'observers include groups');
assert_true(str_contains(plugin_paineldebordo_tv_observers_sql(), 'CONCAT_WS'), 'observers uses CONCAT_WS (no derived-table correlation)');
assert_true(!preg_match('/FROM\s*\(\s*SELECT/i', plugin_paineldebordo_tv_observers_sql()), 'observers avoids nested FROM derived table');
require_once $root . '/inc/icons.inc.php';
assert_true(str_contains(plugin_paineldebordo_icon('cog'), 'circle'), 'cog icon is gear not doc');
assert_true(!str_contains(plugin_paineldebordo_icon('cog'), 'l4 4v12'), 'cog icon is not doc path');
assert_true(str_contains(plugin_paineldebordo_icon('cog'), '19.4 15'), 'cog icon has gear teeth path');
assert_true(!str_contains(plugin_paineldebordo_icon('cog'), 'M12 3v2M12 19v2'), 'cog icon is not sun rays');
assert_true(plugin_paineldebordo_icon('cog') !== plugin_paineldebordo_icon('sun'), 'cog differs from sun');
assert_true(str_contains(plugin_paineldebordo_icon('hash'), 'M5 9h14'), 'hash icon resolves');
assert_true(str_contains(plugin_paineldebordo_icon('calendar'), 'rect'), 'calendar icon resolves');
assert_true(str_contains(plugin_paineldebordo_icon('refresh'), 'M21 12'), 'refresh icon resolves');
assert_true(str_contains(plugin_paineldebordo_icon('sort'), 'M7 4v16'), 'sort icon resolves');
assert_true(str_contains(plugin_paineldebordo_icon('title'), 'M5 7h14'), 'title icon resolves');
assert_true(str_contains(plugin_paineldebordo_icon('bell'), 'M6 8a6'), 'bell icon resolves');
assert_true(!str_contains(plugin_paineldebordo_icon('hash'), 'l4 4v12'), 'hash is not doc');
assert_true(!str_contains(plugin_paineldebordo_icon('columns'), 'l4 4v12'), 'columns is not doc');
assert_true($catalog['reabertura']['priority'] > $catalog['atualizacao']['priority'], 'reopen outranks update');

// Sort
$sorted = plugin_paineldebordo_tv_sort_events([
    ['type' => 'atualizacao', 'ts' => '2026-07-21 10:00:00'],
    ['type' => 'solucao_negada', 'ts' => '2026-07-21 09:00:00'],
    ['type' => 'novo', 'ts' => '2026-07-21 11:00:00'],
]);
assert_eq('solucao_negada', $sorted[0]['type'], 'first event is solucao_negada');
assert_eq('novo', $sorted[1]['type'], 'second event is novo');
assert_eq('atualizacao', $sorted[2]['type'], 'third event is atualizacao');

// Same priority → newer first
$sorted2 = plugin_paineldebordo_tv_sort_events([
    ['type' => 'novo', 'ts' => '2026-07-21 10:00:00'],
    ['type' => 'novo', 'ts' => '2026-07-21 12:00:00'],
]);
assert_eq('2026-07-21 12:00:00', $sorted2[0]['ts'], 'newer novo comes first');

// since validation (relative to now — window is 1 hour)
$recent = (new DateTime('-5 minutes'))->format('Y-m-d H:i:s');
$valid = plugin_paineldebordo_tv_validate_since($recent);
assert_eq($recent, $valid, 'recent valid since preserved');

$invalid = plugin_paineldebordo_tv_validate_since('not-a-date');
assert_true((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $invalid), 'invalid since falls back to datetime');

$old = plugin_paineldebordo_tv_validate_since('2000-01-01 00:00:00');
$floor = new DateTime('-1 hour -30 seconds');
$ceil = new DateTime('-1 hour +30 seconds');
$oldDt = DateTime::createFromFormat('Y-m-d H:i:s', $old);
assert_true(
    $oldDt instanceof DateTime && $oldDt >= $floor && $oldDt <= $ceil,
    'since older than 1h is capped near 1h window'
);
// entity / group SQL helpers
assert_eq('1,2,3', plugin_paineldebordo_tv_entity_in([1, 2, 2, 3]), 'entity in dedupes');
assert_eq('0', plugin_paineldebordo_tv_entity_in([]), 'empty entities becomes 0');
assert_eq('10,20', plugin_paineldebordo_tv_group_in([10, 20, 0, -1]), 'group in filters invalid');
assert_eq('', plugin_paineldebordo_tv_group_in([]), 'empty groups fragment empty');

// reopen classification
assert_eq('reabertura', plugin_paineldebordo_tv_classify_status_change(5, 2), 'solved→assigned = reopen');
assert_eq('reabertura', plugin_paineldebordo_tv_classify_status_change(6, 1), 'closed→new = reopen');
assert_eq('', plugin_paineldebordo_tv_classify_status_change(2, 5), 'assigned→solved not reopen');
assert_eq('', plugin_paineldebordo_tv_classify_status_change(1, 2), 'new→assigned not reopen');

// Smoke: critical PHP files parse
$files = [
    $root . '/setup.php',
    $root . '/hook.php',
    $root . '/inc/access.inc.php',
    $root . '/inc/filters.inc.php',
    $root . '/inc/tv.inc.php',
    $root . '/inc/install.inc.php',
    $root . '/inc/layout.inc.php',
    $root . '/inc/profile.class.php',
    $root . '/inc/services/tickets.php',
    $root . '/public/tv.php',
    $root . '/public/ajax/tv_events.php',
    $root . '/public/shell.php',
    $root . '/public/home.php',
    $root . '/public/index.php',
];
foreach ($files as $file) {
    assert_true(is_file($file), 'exists ' . basename($file));
    $out = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    assert_true($code === 0, 'php -l ' . basename(dirname($file)) . '/' . basename($file) . ' → ' . implode(' ', $out));
}

// Hook naming conventions for GLPI
$setup = file_get_contents($root . '/setup.php');
assert_true(str_contains($setup, 'function plugin_init_paineldebordo'), 'plugin_init_paineldebordo exists');
assert_true(str_contains($setup, 'function plugin_version_paineldebordo'), 'plugin_version_paineldebordo exists');
assert_true(str_contains($setup, "'version'         => '2.34.1'"), 'version is 2.34.1');
assert_true(!str_contains($setup, '::createFirstAccess'), 'setup init does not createFirstAccess');
assert_true(!str_contains($setup, 'migrateModuleRights'), 'setup init does not migrate all profiles');
assert_true(str_contains($setup, "config_page']['paineldebordo'] = 'public/config.php'"), 'config_page opens Configuration');
assert_true(is_file($root . '/public/config.php'), 'public/config.php exists');
assert_true(str_contains((string) file_get_contents($root . '/public/config.php'), "page' => 'config'"), 'config.php redirects to config');
assert_true(str_contains($setup, 'gcarvallho.dev'), 'author credits gcarvallho.dev');
assert_true(str_contains($setup, 'wa.me/5591985390491'), 'author WhatsApp link');
assert_true(str_contains($setup, 'inovareempreendimentos.com.br'), 'author Inovare link');
assert_true(!str_contains($setup, 'Service TIC'), 'author no longer Service TIC');
assert_true(str_contains($setup, 'addPluginStrategyForLegacyScripts'), 'Firewall strategies registered');
assert_true(str_contains($setup, '#^/ajax/tv_unpair\\.php#'), 'Firewall NO_CHECK for tv_unpair');
assert_true(str_contains($setup, '#^/tv_pair\\.php#'), 'Firewall NO_CHECK uses plugin_resource path');
$layout = file_get_contents($root . '/inc/layout.inc.php');
assert_true(str_contains($layout, 'ho-tokens-inline'), 'layout inlines CSS');
assert_true(str_contains($layout, 'function plugin_paineldebordo_asset_base'), 'asset_base exists');
assert_true(str_contains($layout, "getWebDir('paineldebordo', true)"), 'asset_base uses getWebDir full');
assert_true(str_contains($layout, 'function plugin_paineldebordo_absolute_url'), 'absolute_url exists');
assert_true(str_contains($layout, 'function plugin_paineldebordo_url_debug'), 'url_debug exists');
assert_true(str_contains((string) file_get_contents($root . '/public/views/config_hub.php'), 'absolute_url'), 'config uses absolute_url');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), 'PAIR_AJAX_LIST'), 'tv_pair multi ajax candidates');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'grid-row: 1 / span 3'), 'park/KPI icon grid span');
assert_true(str_contains($layout, 'ho-nav__icon'), 'layout emits nav icons class');
assert_true(str_contains($layout, 'canConfigure'), 'nav Configuration gated by canConfigure');
assert_true(str_contains($layout, "'config'"), 'nav has config item');
assert_true(str_contains($layout, 'function plugin_paineldebordo_nav_tree'), 'nav_tree exists');
assert_true(str_contains($layout, 'ho-nav-group'), 'layout renders nav groups');
assert_true(str_contains($layout, '!$isTop && $isActiveGroup'), 'top nav does not auto-open groups');
assert_true(str_contains($layout, 'All entities'), 'layout All entities msgid');
assert_true(str_contains($layout, 'ho-chip'), 'layout period chips');
assert_true(str_contains($layout, 'ho-select'), 'layout group ho-select');
assert_true(str_contains($layout, 'ho-filters__period'), 'layout filters period group');
assert_true(!str_contains($layout, 'form-select-sm'), 'layout filter bar no form-select-sm');
assert_true(str_contains($layout, "'config', 'setup'"), 'layout hides filters on config');
assert_true(str_contains($layout, 'plugin_paineldebordo_period_label'), 'layout uses period_label');
assert_true(!preg_match("/\\\$items\\['setup'\\]/", $layout), 'nav no longer uses setup key');
$filters = file_get_contents($root . '/inc/filters.inc.php');
assert_true(str_contains($filters, 'function plugin_paineldebordo_period_label'), 'period_label in filters');
assert_true(str_contains($filters, "'7 days'"), 'period_label has 7 days msgid');
assert_true(str_contains($filters, "'Year'"), 'period_label maps ytd to Year');
assert_true(is_file($root . '/inc/icons.inc.php'), 'icons.inc.php exists');
$icons = file_get_contents($root . '/inc/icons.inc.php');
assert_true(str_contains($icons, 'function plugin_paineldebordo_icon'), 'icon helper exists');
$home = file_get_contents($root . '/public/home.php');
assert_true(str_contains($home, 'code.highcharts.com'), 'home CDN fallback for Highcharts');
assert_true(str_contains($home, 'ho-kpi__icon'), 'home KPIs have icons');
assert_true(str_contains($home, 'ho_home_tv'), 'home has READ TV pairing shortcut');
assert_true(str_contains($home, 'plugin_paineldebordo_overview_board'), 'home uses overview_board');
assert_true(str_contains($home, 'ho-dash-grid'), 'home has dash mosaic grid');
assert_true(str_contains($home, 'ho-kpi-grid--rich'), 'home has rich KPI strip');
assert_true(str_contains($home, 'ho-quick-nav'), 'home has shortcut cards');
assert_true(str_contains($home, 'ho-dash-spotlight'), 'home has spotlight charts');
assert_true(str_contains($home, 'ho_ov_'), 'home uses ho_ov_ chart mount prefix');
assert_true(str_contains($home, "'ho_ov_' +") || str_contains($home, '"ho_ov_" +') || str_contains($home, 'ho_ov_\' +'), 'home boots Highcharts on ho_ov_ + id');
assert_true(str_contains($home, 'ho-chart-fs'), 'home has fullscreen modal');
assert_true(str_contains($home, 'exportChartLocal') || str_contains($home, 'data-chart-jpg'), 'home chart export JPG');
assert_true(str_contains($home, 'overview_board.php'), 'home polls overview_board');
assert_true(str_contains($home, 'ho-queue-strip'), 'home has queue strip');
$overview = file_get_contents($root . '/inc/services/overview.php');
assert_true(str_contains($overview, 'plugin_paineldebordo_chart_dataset'), 'overview uses chart_dataset');
assert_true(str_contains($overview, 'plugin_paineldebordo_charts_catalog'), 'overview loops charts catalog');
assert_true(str_contains($overview, "__('Opened in period', 'paineldebordo')"), 'overview period KPI msgid');
assert_true(str_contains($overview, "__('Open now', 'paineldebordo')"), 'overview snapshot KPI msgid');
assert_true(str_contains($filters, "__('7 days', 'paineldebordo')") || str_contains($filters, "'7 days'"), 'period_label 7 days domain');
assert_true(str_contains($filters, "__('Year', 'paineldebordo')") || str_contains($filters, "'Year'"), 'period_label Year domain');
$catalogIds = ['status', 'priority', 'evolution', 'flow', 'aging', 'breach', 'groups', 'tech', 'category', 'entity', 'sla', 'type', 'source', 'location', 'requester'];
$chartsSrc = file_get_contents($root . '/inc/services/charts.php');
foreach ($catalogIds as $cid) {
    assert_true(str_contains($chartsSrc, "'" . $cid . "'"), 'catalog has ' . $cid);
}
assert_true(str_contains($chartsSrc, 'plugin_paineldebordo_report_period'), 'charts use report_period');
assert_true(is_file($root . '/inc/services/overview.php'), 'overview.php service exists');
assert_true(is_file($root . '/public/ajax/overview_board.php'), 'overview_board.php exists');
assert_true(is_file($root . '/inc/services/overview_layout.php'), 'overview_layout.php exists');
assert_true(is_file($root . '/public/ajax/overview_layout.php'), 'overview_layout ajax exists');
$ovLayout = file_get_contents($root . '/inc/services/overview_layout.php');
assert_true(str_contains($ovLayout, 'dashboard_layout'), 'layout uses dashboard_layout config');
assert_true(str_contains($ovLayout, 'plugin_paineldebordo_overview_layout_apply'), 'layout apply helper');
assert_true(str_contains($home, 'Customize mural'), 'home Customize mural msgid');
assert_true(str_contains($home, 'ho-prefs'), 'home prefs panel class');
assert_true(str_contains($home, 'overview_layout.php'), 'home posts overview_layout');
$shell = file_get_contents($root . '/public/shell.php');
assert_true(str_contains($shell, "page === 'config'"), 'shell has config page');
assert_true(str_contains($shell, 'canConfigure'), 'config requires Super-Admin canConfigure');
assert_true(str_contains($shell, 'page_module'), 'shell maps pages to modules');
assert_true(str_contains($shell, 'Only Super-Admin'), 'config blocked message msgid');
assert_true(str_contains($shell, "page === 'setup'"), 'shell redirects setup');
assert_true(is_file($root . '/public/views/config_hub.php'), 'config_hub.php exists');
$configHub = file_get_contents($root . '/public/views/config_hub.php');
assert_true(str_contains($configHub, 'ho_map_table'), 'config hub has map table');
assert_true(str_contains($configHub, 'ho-check-list'), 'config hub entity check-list');
assert_true(!str_contains($configHub, 'multiple size='), 'config hub no legacy multi-select size');
assert_true(str_contains($configHub, 'ho-card-head'), 'config hub card heads');
assert_true(str_contains($configHub, 'tv_pair_list_all') || str_contains($configHub, 'plugin_paineldebordo_tv_pair_list_all'), 'config hub lists all TVs');
assert_true(str_contains($configHub, 'ho_tv_copy_link'), 'config hub copy pairing link');
assert_true(str_contains($configHub, 'tv_pair_ttl'), 'config hub TV TTL');
assert_true(is_file($root . '/inc/branding.inc.php'), 'branding.inc.php');
assert_true(is_file($root . '/public/img/logo-inovare.svg'), 'logo-inovare.svg');
assert_true(is_file($root . '/public/img/logo-inovare-mark.svg'), 'logo-inovare-mark.svg');
assert_true(str_contains($configHub, 'branding_save'), 'config personalization');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-app--rail'), 'rail CSS');
assert_true(str_contains((string) file_get_contents($root . '/inc/layout.inc.php'), 'ho_nav_rail_btn'), 'rail toggle');
assert_true(str_contains($configHub, 'tv_nickname'), 'config hub TV nickname');
assert_true(!str_contains($configHub, 'location.reload()'), 'config hub no reload after TV link');
assert_true(str_contains($configHub, "location.href = 'shell.php?page=config'"), 'config hub GET after TV link');
assert_true(str_contains((string) file_get_contents($root . '/inc/tv_pair.inc.php'), 'function plugin_paineldebordo_tv_pair_ttl_seconds'), 'ttl helper');
assert_true(str_contains((string) file_get_contents($root . '/inc/tv_pair.inc.php'), 'function plugin_paineldebordo_tv_pair_set_ttl'), 'ttl setter global');
assert_true(str_contains((string) file_get_contents($root . '/inc/tv_pair.inc.php'), "users_id = 0"), 'ttl stored globally');
assert_true(str_contains((string) file_get_contents($root . '/inc/tv_pair.inc.php'), 'function plugin_paineldebordo_tv_parse_user_agent'), 'UA parser');
assert_true(str_contains((string) file_get_contents($root . '/inc/tv_pair.inc.php'), 'function plugin_paineldebordo_tv_pair_set_nickname'), 'nickname setter');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), 'tv-pair__main'), 'tv_pair side-by-side layout');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), 'renewCode'), 'tv_pair auto-renew');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), 'tv_pair_bar'), 'tv_pair progress bar');
assert_true(str_contains($configHub, 'tv_delete_id'), 'config hub delete device');
assert_true(str_contains((string) file_get_contents($root . '/inc/tv_pair.inc.php'), 'function plugin_paineldebordo_tv_pair_delete'), 'tv_pair_delete exists');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Apagar"'), 'pt_BR Delete');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Copiar link"'), 'pt_BR Copy link');
assert_true(is_file($root . '/public/ajax/map_coord.php'), 'map_coord.php exists');
$mapCoord = file_get_contents($root . '/public/ajax/map_coord.php');
assert_true(str_contains($mapCoord, 'requireModuleJson'), 'map_coord requires resources module');
assert_true(str_contains($mapCoord, 'resources'), 'map_coord resources UPDATE');
$insert = file_get_contents($root . '/public/map/insert_coord.php');
assert_true(str_contains($insert, 'checkModule'), 'insert_coord requires module');
assert_true(str_contains($insert, 'resources'), 'insert_coord resources UPDATE');
assert_true(!str_contains($insert, 'or die'), 'insert_coord has no or die');
$tv = file_get_contents($root . '/public/tv.php');
assert_true(str_contains($tv, "'ajax/tv_board.php'"), 'TV uses relative ajax endpoint');
assert_true(str_contains($tv, 'ajax/tv_events.php'), 'TV polls tv_events');
assert_true(str_contains($tv, 'tv_toasts'), 'TV has toast stack container');
assert_true(str_contains($tv, 'TOAST_TTL'), 'TV toast TTL configured');
assert_true(str_contains($setup, '#^/ajax/tv_board\\.php#'), 'firewall includes tv_board NO_CHECK');
assert_true(str_contains($setup, '#^/tv\\.php#'), 'firewall includes tv.php NO_CHECK');
$readme = file_get_contents($root . '/README.md');
assert_true(str_contains($readme, '2.34.1'), 'README mentions 2.34.1');
assert_true(str_contains((string) file_get_contents($root . '/inc/install.inc.php'), 'function plugin_paineldebordo_trace'), 'install trace helper');
assert_true(str_contains((string) file_get_contents($root . '/inc/install.inc.php'), "logInFile('paineldebordo'"), 'trace writes paineldebordo.log');
assert_true(str_contains((string) file_get_contents($root . '/inc/install.inc.php'), 'function plugin_paineldebordo_widen_config_value_column'), 'widen config helper');
assert_true(str_contains((string) file_get_contents($root . '/inc/install.inc.php'), 'config_pdbnew'), 'widen rebuilds via new table');
assert_true(str_contains((string) file_get_contents($root . '/inc/install.inc.php'), 'RENAME TABLE'), 'widen uses RENAME TABLE');
assert_true(!str_contains((string) file_get_contents($root . '/inc/install.inc.php'), 'MODIFY `value` text NOT NULL'), 'widen no longer uses MODIFY TEXT');
assert_true(str_contains((string) file_get_contents($root . '/hook.php'), 'plugin_paineldebordo_widen_config_value_column'), 'hook uses widen config helper');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'flex: 0 0 28px'), 'avatar face fixed 28px');
assert_true(str_contains($layout, 'ho_user_menu'), 'layout has user avatar menu');
assert_true(is_file($root . '/logo.png'), 'marketplace logo.png exists');
$homePhp = (string) file_get_contents($root . '/public/home.php');
assert_true(str_contains($homePhp, "span class=\"ho-tip\" data-tip=\"<?php echo htmlspecialchars((string) \$c['title']); ?>\""), 'overview panel title tooltip');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'tv-col__list::-webkit-scrollbar'), 'TV column scrollbar styled');
assert_true(str_contains((string) file_get_contents($root . '/public/favicon.php'), "\$_GET['c']"), 'favicon.php honors ?c=');
assert_true(!str_contains($layout, 'alternate icon'), 'layout has no static alternate favicon');
assert_true(preg_match('/:root\s*\{[^}]*--ho-font/s', (string) file_get_contents($root . '/public/css/dashboard-tokens.css')) === 1, 'ho-font on bare :root');
assert_true(str_contains($configHub, 'ho_branding_reset'), 'config restore beside save');
assert_true(str_contains($configHub, 'wa.me/5591985390491'), 'config authors WhatsApp');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-app--rail .ho-nav-group.is-open'), 'rail flyout opens on is-open');
assert_true(!str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-app--rail .ho-nav-group:hover'), 'rail has no hover flyout');
assert_true(str_contains((string) file_get_contents($root . '/inc/layout.inc.php'), 'pdb-nav-rail-change'), 'rail change closes groups');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), '.ho-app--rail .ho-sider') && str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'overflow: visible'), 'rail sider overflow visible');
assert_true(str_contains((string) file_get_contents($root . '/inc/icons.inc.php'), "'resources' =>"), 'resources icon path');
assert_true(str_contains($layout, "plugin_paineldebordo_icon((string) \$gid"), 'nav group uses gid icon alias');
assert_true(is_file($root . '/public/img/favicon.svg'), 'favicon.svg exists');
assert_true(str_contains((string) file_get_contents($root . '/public/img/favicon.svg'), '#e73e11'), 'favicon uses brand accent');
assert_true(is_file($root . '/public/favicon.php'), 'favicon.php dynamic exists');
assert_true(str_contains((string) file_get_contents($root . '/inc/branding.inc.php'), "'favicon'"), 'branding has favicon color');
assert_true(str_contains((string) file_get_contents($root . '/public/views/config_hub.php'), 'brand_favicon'), 'config has favicon color input');
assert_true(str_contains($setup, '#^/favicon\\.php#'), 'Firewall NO_CHECK for favicon.php');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), 'Date.now() + ttlSec'), 'countdown uses ttl_sec not Date.parse');
assert_true(str_contains((string) file_get_contents($root . '/hook.php'), 'table count created'), 'install logs count step');
assert_true(str_contains((string) file_get_contents($root . '/hook.php'), 'TV pairing table ready'), 'install logs TV step');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), "min '"), 'tv_pair countdown shows minutes');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'rememberMute: false, muted: false'), 'tv audio defaults unmuted');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), '_audioDefaults2273'), 'tv audio one-time migrate');
assert_true(str_contains((string) file_get_contents($root . '/public/tv_pair.php'), "'GET',"), 'tv_pair create uses GET');
assert_true(str_contains($setup, 'registerPluginStatelessPath'), 'setup registers TV paths as stateless');
assert_true(str_contains($setup, 'tv_pair_api'), 'firewall includes tv_pair_api');
assert_true(is_file($root . '/public/tv_pair_api.php'), 'tv_pair_api.php exists');
assert_true(str_contains($layout, "'tv_pair'"), 'nav has Pair TV item');
assert_true(str_contains($layout, 'Open logged-in TV'), 'nav has Open logged-in TV');
assert_true(str_contains($layout, 'plugin_paineldebordo_url_is_loopback'), 'absolute_url ignores loopback url_base');
assert_true(str_contains($layout, 'plugin_paineldebordo_request_base_url'), 'request_base_url helper');
assert_true(is_file($root . '/inc/services/assets.php'), 'assets.php service exists');
assert_true(is_file($root . '/public/ajax/assets_board.php'), 'assets_board.php exists');
assert_true(is_file($root . '/public/ajax/assets_list.php'), 'assets_list.php exists');
assert_true(is_file($root . '/public/ajax/assets_item.php'), 'assets_item.php exists');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'plugin_paineldebordo_assets_board'), 'assets_board function');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'plugin_paineldebordo_assets_list'), 'assets_list function');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'plugin_paineldebordo_assets_item'), 'assets_item function');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'Disk usage by partition'), 'assets detail disk chart msgid');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'glpi_items_disks'), 'assets uses items_disks');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'glpi_items_devicememories'), 'assets uses memories');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), "INNER JOIN glpi_agents a ON a.items_id = gi.id"), 'assets agents JOIN before WHERE');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'plugin_paineldebordo_tickets_scope(0)'), 'assets scope skips group assert');
assert_true(str_contains((string) file_get_contents($root . '/inc/access.inc.php'), "filter_group', '0'"), 'invalid group soft-clears');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'Could not load assets board'), 'assets view catches board errors');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'ho_as_kpis'), 'assets view KPIs');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'ho_as_view_list'), 'assets list view');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'ho_as_view_item'), 'assets item view');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'assets_board.php'), 'assets polls board');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'assets_list.php'), 'assets list endpoint');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'Open in GLPI'), 'assets Open in GLPI msgid');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-assets-lists'), 'assets CSS');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-as-crumb'), 'assets crumb CSS');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'IT park inventory'), 'pt_BR assets park');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Parque"'), 'pt_BR Parque');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Discos quase cheios"'), 'pt_BR disks almost full');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Abrir no GLPI"'), 'pt_BR Abrir no GLPI');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Voltar"'), 'pt_BR Voltar');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Usado"'), 'pt_BR Usado');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Livre"'), 'pt_BR Livre');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Sem resultados"'), 'pt_BR Sem resultados');
assert_true(!str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Frota"'), 'pt_BR no longer uses Frota');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'IT park inventory'), 'assets view park label');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), 'Park total'), 'assets service park total');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/assets.php'), "Computers', 'paineldebordo'"), 'assets types use plugin domain');
assert_true(str_contains((string) file_get_contents($root . '/public/views/map.php'), 'map/css/leaflet.css'), 'map loads leaflet.css from css/');
assert_true(str_contains((string) file_get_contents($root . '/public/views/map.php'), 'invalidateSize'), 'map invalidateSize');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'leaflet-tile'), 'CSS protects leaflet tiles');
assert_true(!str_contains((string) file_get_contents($root . '/public/views/map.php'), 'map/js/leaflet.css'), 'map does not use wrong js/leaflet.css');
assert_true(is_file($root . '/public/js/gridstack/gridstack-all.js'), 'gridstack-all.js UMD vendored');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'gridstack-all.js'), 'bi studio loads gridstack-all');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ensureGridStack'), 'bi studio ensureGridStack');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'skipCollect'), 'bi addWidget skipCollect');
assert_true(str_contains((string) file_get_contents($root . '/inc/access.inc.php'), 'canModule'), 'access canModule helper');
assert_true(str_contains((string) file_get_contents($root . '/inc/access.inc.php'), 'isSuperAdminStrict'), 'access SuperAdminStrict');
assert_true(str_contains((string) file_get_contents($root . '/inc/profile.class.php'), 'plugin_paineldebordo_analysis'), 'profile analysis right');
assert_true(str_contains((string) file_get_contents($root . '/inc/profile.class.php'), 'migrateModuleRights'), 'profile migrateModuleRights');
$profileSrc = (string) file_get_contents($root . '/inc/profile.class.php');
$initPos = strpos($profileSrc, 'function initProfile()');
$nextPos = strpos($profileSrc, 'function removeRightsFromSession()', $initPos !== false ? $initPos : 0);
$initBody = ($initPos !== false && $nextPos !== false)
    ? substr($profileSrc, $initPos, $nextPos - $initPos)
    : '';
assert_true($initBody !== '' && !str_contains($initBody, 'migrateModuleRights'), 'initProfile does not call migrateModuleRights');
assert_true(str_contains($profileSrc, 'Must stay cheap'), 'initProfile documents cheap sync');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'pushTipToast'), 'tv tip toast helper');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'rememberMute ? !!prefs.audio.muted : false'), 'tv mute init fixed');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'ctrlKey'), 'tv CTRL+M');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), "code === 'Space'"), 'tv CTRL+Space theme');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'tv_theme_btn'), 'tv theme toggle button');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), "?? 'light'"), 'tv default theme light');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'soundEnabled'), 'tv sound enabled toast');
assert_true(str_contains((string) file_get_contents($root . '/public/tv.php'), 'darkEnabled'), 'tv dark enabled toast');
assert_true(str_contains((string) file_get_contents($root . '/inc/branding.inc.php'), ':root:not([data-theme="dark"])'), 'branding does not override dark via :root');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), ':root:not([data-theme="dark"])'), 'tokens light selector excludes dark');
assert_true(is_file($root . '/docs/PERMISSIONS.md'), 'PERMISSIONS.md exists');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'X-Requested-With'), 'bi save uses XHR CSRF preserve');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho-bi-palette__acc'), 'bi palette accordion');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'confirmLeaveIfDirty'), 'bi leave unsaved confirm');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho_bi_pal_assets_lists'), 'bi assets lists palette');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/bi.php'), 'assets.list.'), 'bi assets list refs');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/bi.php'), "'list'"), 'bi normalize list type');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'scrollbar-color'), 'nav scrollbar tokens');
assert_true(!str_contains((string) file_get_contents($root . '/public/views/config_hub.php'), 'return confirm('), 'config hub no native confirm');
assert_true(str_contains((string) file_get_contents($root . '/public/views/config_hub.php'), 'ho_cfg_modal'), 'config hub DS modal');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'X-Glpi-Csrf-Token'), 'bi refreshBoard sends CSRF');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho-bi-tab-rename-ok'), 'bi tab rename confirm button');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'Tab name updated'), 'bi tab rename toast');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), "grid.on('change'"), 'bi markDirty on grid change');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'is-editing-ui'), 'bi editing-ui class');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho-bi-edit-only'), 'bi edit-only chrome');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-bi-edit-only[hidden]'), 'CSS hides edit-only when hidden');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'function nextPlacement'), 'bi nextPlacement for new widgets');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho_bi_cancel_btn'), 'bi Cancel button');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho_bi_reset_btn'), 'bi Reset defaults in toolbar');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'function cancelEdits'), 'bi cancelEdits helper');
assert_true(!str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'y: 100'), 'bi no longer parks new widgets at y:100');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Cancelar"'), 'pt_BR Cancel');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'Clique em um widget'), 'pt_BR click to add hint');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'function refreshBoard'), 'bi studio refreshBoard draft');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), "body.append('layout'"), 'bi studio posts draft layout');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho-bi-tab-rename'), 'bi studio inline rename');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'ho_bi_fs_btn'), 'bi studio fullscreen button');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'enterKiosk'), 'bi studio kiosk mode');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-bi-kiosk-open'), 'BI kiosk CSS');
assert_true(str_contains((string) file_get_contents($root . '/public/ajax/bi_board.php'), 'layout_override'), 'bi_board draft override');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/bi.php'), 'layout_override'), 'bi_board accepts layout override');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/bi.php'), "'draft'"), 'bi_board draft flag');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgid "Exit fullscreen"'), 'pt_BR Exit fullscreen');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Sair da tela cheia"'), 'pt_BR Exit fullscreen translation');
assert_true(str_contains((string) file_get_contents($root . '/inc/install.inc.php'), 'config_pdbnew'), 'hook widens config value via table rebuild');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgid "Open now"'), 'pt_BR has Open now');
assert_true(str_contains((string) file_get_contents($root . '/locales/pt_BR.po'), 'msgstr "Aberto agora"'), 'pt_BR translates Open now');
assert_true(str_contains($home, 'PREF_ICONS'), 'home prefs icons map');
assert_true(str_contains($home, 'appendChild(modal)'), 'home fullscreen moves to body');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'flex: 1 1 9.5rem'), 'KPI flex fill row');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'z-index: 4000'), 'fullscreen above sider');
assert_true(str_contains((string) file_get_contents($root . '/docs/VERSIONING.md'), 'Interface em PT-BR'), 'VERSIONING PT-BR golden rule');
assert_true(is_file($root . '/inc/services/bi.php'), 'bi.php exists');
assert_true(is_file($root . '/public/views/bi_studio.php'), 'bi_studio.php exists');
assert_true(is_file($root . '/public/ajax/bi_board.php'), 'bi_board.php exists');
assert_true(is_file($root . '/public/ajax/bi_layout.php'), 'bi_layout.php exists');
assert_true(is_file($root . '/public/js/gridstack/gridstack.min.js'), 'gridstack.js vendored');
assert_true(str_contains((string) file_get_contents($root . '/public/shell.php'), 'bi_studio.php'), 'shell metrics → bi_studio');
assert_true(str_contains((string) file_get_contents($root . '/inc/layout.inc.php'), "__('BI', 'paineldebordo')"), 'nav BI label');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/bi.php'), 'bi_layout'), 'bi_layout config key');
assert_true(str_contains((string) file_get_contents($root . '/public/views/bi_studio.php'), 'GridStack'), 'bi studio GridStack');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), 'ho-bi-canvas'), 'BI canvas CSS');
assert_true(is_file($root . '/docs/VERSIONING.md'), 'VERSIONING.md exists');
assert_true(str_contains((string) file_get_contents($root . '/docs/VERSIONING.md'), 'PATCH'), 'VERSIONING documents PATCH');
assert_true(str_contains((string) file_get_contents($root . '/docs/VERSIONING.md'), 'Nunca retirar'), 'VERSIONING no-remove golden rule');
assert_true(is_file($root . '/docs/ARCHITECTURE.md'), 'ARCHITECTURE.md exists');
assert_true(str_contains((string) file_get_contents($root . '/docs/ARCHITECTURE.md'), 'nav_tree'), 'ARCHITECTURE docs nav_tree');
assert_true(str_contains((string) file_get_contents($root . '/docs/ARCHITECTURE.md'), 'config_hub'), 'ARCHITECTURE docs config hub');
assert_true(str_contains((string) file_get_contents($root . '/docs/ARCHITECTURE.md'), 'ho-dash-grid') || str_contains((string) file_get_contents($root . '/docs/ARCHITECTURE.md'), 'full dashboard'), 'ARCHITECTURE docs Overview mural');
assert_true(str_contains((string) file_get_contents($root . '/docs/TESTING.md'), 'Kaspersky'), 'TESTING mentions Kaspersky');
$po = (string) file_get_contents($root . '/locales/pt_BR.po');
assert_true(str_contains($po, 'msgstr "Configuração"'), 'pt_BR has Configuração');
assert_true(str_contains($po, 'msgstr "Ano"'), 'pt_BR has Ano');
assert_true(str_contains($po, 'msgstr "7 dias"'), 'pt_BR has 7 dias');
assert_true(str_contains($po, 'msgstr "Chamados"'), 'pt_BR has Chamados');
assert_true(is_file($root . '/locales/pt_BR.mo'), 'pt_BR.mo exists');
$css = file_get_contents($root . '/public/css/dashboard-tokens.css');
assert_true(str_contains($css, '.ho-nav-group'), 'CSS has nav groups');
assert_true(str_contains($css, '.ho-dash-grid'), 'CSS has dash grid');
assert_true(str_contains($css, '.ho-dash-hero'), 'CSS has dash hero');
assert_true(str_contains($css, '.ho-panel__head'), 'CSS has panel head');
$tvInc = file_get_contents($root . '/inc/tv_pair.inc.php');
assert_true(str_contains($tvInc, 'function plugin_paineldebordo_tv_pair_list_all'), 'tv_pair_list_all exists');
$reports = file_get_contents($root . '/inc/services/reports.php');
assert_true(str_contains($reports, 'function plugin_paineldebordo_reports_legacy_map'), 'legacy map function');
assert_true(str_contains($reports, "'breach_radar'"), 'breach_radar report');
assert_true(str_contains($reports, "'cost_by_tech'"), 'cost_by_tech report');
assert_true(str_contains($reports, "'aging_buckets'"), 'aging_buckets report');
assert_true(str_contains($reports, "'csat_detail'"), 'csat_detail report');
assert_true(str_contains($reports, 'plugin_paineldebordo_period_label'), 'reports uses period_label');
$run = file_get_contents($root . '/public/views/report_run.php');
assert_true(str_contains($run, "export") && str_contains($run, 'csv'), 'report_run CSV export');
assert_true(is_file($root . '/_legacy_ref/README.md'), 'legacy ref README');
$relSla = file_get_contents($root . '/public/reports/rel_sla.php');
assert_true(str_contains($relSla, 'sla_by_policy'), 'rel_sla maps to sla_by_policy');
assert_true(is_file($root . '/locales/pt_BR.mo'), 'pt_BR.mo exists');
assert_true(is_file($root . '/inc/csrf.inc.php'), 'csrf.inc.php exists');
$approve = file_get_contents($root . '/public/ajax/tv_pair_approve.php');
assert_true(!str_contains($approve, 'validateCSRF'), 'approve avoids double CSRF');
$charts = file_get_contents($root . '/inc/services/charts.php');
assert_true(str_contains($charts, 'plugin_paineldebordo_public_url'), 'charts has public_url helper');
assert_true(str_contains($charts, "'requester'"), 'charts catalog has requester');
assert_true(str_contains($charts, 'function plugin_paineldebordo_chart_hc_type'), 'chart_hc_type helper exists');
assert_true(is_file($root . '/inc/install.inc.php'), 'install.inc.php exists');
if (!defined('INFO')) {
    define('INFO', 0);
}
require_once $root . '/inc/install.inc.php';
assert_true(function_exists('plugin_paineldebordo_db_query_idempotent'), 'db_query_idempotent exists');
assert_true(function_exists('plugin_paineldebordo_db_has_index'), 'db_has_index exists');
assert_true(function_exists('plugin_paineldebordo_db_error_is_benign'), 'db_error_is_benign exists');
assert_true(
    plugin_paineldebordo_db_error_is_benign("MySQL query error: Duplicate key name 'location' (1061) in SQL query \"ALTER\""),
    '1061 duplicate key name is benign'
);
assert_true(!plugin_paineldebordo_db_error_is_benign('MySQL query error: syntax error (1064)'), '1064 is not benign');
$hookFull = file_get_contents($root . '/hook.php');
assert_true(!str_contains($hookFull, 'or die('), 'hook has no or die(');
assert_true(str_contains($hookFull, 'plugin_paineldebordo_install_log'), 'install uses progress log');
assert_true(str_contains($hookFull, 'plugin_paineldebordo_db_has_index'), 'hook checks map index before ALTER');
assert_true(str_contains($hookFull, 'plugin_paineldebordo_db_query_idempotent'), 'hook uses query_idempotent');
assert_true(str_contains($hookFull, "db_has_index('glpi_plugin_paineldebordo_map', 'location')"), 'hook skips existing location key');

$tvAjax = file_get_contents($root . '/public/ajax/tv_events.php');
assert_true(str_contains($tvAjax, 'plugin_paineldebordo_tickets_scope'), 'TV reuses tickets_scope');
assert_true(str_contains($tvAjax, 'plugin_paineldebordo_tv_pair_auth_token') || str_contains($tvAjax, 'plugin_paineldebordo_tv_resolve_auth'), 'TV accepts device token');
assert_true(str_contains($tvAjax, 'plugin_paineldebordo_tv_resolve_auth'), 'tv_events uses resolve_auth');
assert_true(str_contains($tvAjax, 'items_id'), 'TV uses items_id for followups');
assert_true(str_contains($tvAjax, "itemtype = 'Ticket'"), 'TV filters Ticket itemtype');
assert_true(str_contains($tvAjax, 'solucao_aceita'), 'TV events has solucao_aceita');
assert_true(str_contains($tvAjax, 's.status = 4'), 'TV refused uses GLPI11 status 4');
assert_true(str_contains($tvAjax, 'group_name'), 'TV events include group_name');
assert_true(str_contains($tvInc, 'function plugin_paineldebordo_tv_resolve_auth'), 'tv_resolve_auth helper');
assert_true(str_contains($tvInc, 'function plugin_paineldebordo_tv_pair_revoke_by_token'), 'revoke_by_token helper');
assert_true(is_file($root . '/public/ajax/tv_unpair.php'), 'tv_unpair.php exists');
assert_true(is_file($root . '/public/tv_approve.php'), 'tv_approve.php exists');
assert_true(is_file($root . '/public/js/qrcode.min.js'), 'qrcode.min.js vendored');
$tvPairPage = file_get_contents($root . '/public/tv_pair.php');
assert_true(str_contains($tvPairPage, 'qrcode.min.js'), 'tv_pair loads QR lib');
assert_true(str_contains($tvPairPage, 'tv_approve.php'), 'tv_pair QR points to approve');
assert_true(str_contains($tvPairPage, 'validateStoredToken'), 'tv_pair validates stored token');
assert_true(str_contains($tv, 'ajax/tv_unpair.php'), 'TV Exit calls unpair');
assert_true(str_contains($tv, 'tv_exit_btn'), 'TV Exit button in device mode');
$tvApprove = file_get_contents($root . '/public/tv_approve.php');
assert_true(str_contains($tvApprove, 'plugin_paineldebordo_checkAccess'), 'tv_approve requires login+READ');
assert_true(str_contains($tvApprove, 'tv_pair_approve.php'), 'tv_approve posts to approve ajax');
$tvUnpair = file_get_contents($root . '/public/ajax/tv_unpair.php');
assert_true(str_contains($tvUnpair, 'plugin_paineldebordo_tv_pair_revoke_by_token'), 'unpair revokes by token');
$tvBoard = file_get_contents($root . '/public/ajax/tv_board.php');
assert_true(str_contains($tvBoard, 'plugin_paineldebordo_tv_resolve_auth'), 'tv_board uses resolve_auth');
assert_true(str_contains($po, 'msgstr "Autorizar esta TV"'), 'pt_BR Authorize this TV');
assert_true(str_contains($po, 'msgstr "Escaneie com o celular'), 'pt_BR QR scan hint');
$ticketsSvc = file_get_contents($root . '/inc/services/tickets.php');
assert_true(str_contains($ticketsSvc, 'plugin_paineldebordo_tv_group_name_sql'), 'TV queues use group subquery');
assert_true(str_contains($ticketsSvc, 'plugin_paineldebordo_tv_ticket_card'), 'TV queues map via ticket_card');
assert_true(str_contains($ticketsSvc, 'plugin_paineldebordo_tv_tech_name_sql'), 'TV queues use tech subquery');
assert_true(str_contains($ticketsSvc, 'glpi_tickets.date DESC'), 'TV queues newest-first by date');
assert_true(str_contains($ticketsSvc, 'glpi_tickets.date_mod'), 'TV queues select date_mod');
assert_true(str_contains($tvIncCore = file_get_contents($root . '/inc/tv.inc.php'), "'group'"), 'TV card payload has group');
assert_true(str_contains($tvIncCore, "'tech'"), 'TV card payload has tech');
assert_true(str_contains($tvIncCore, "'date_mod'"), 'TV card payload has date_mod');
assert_true(str_contains($tvIncCore, 'date_mod_label'), 'TV card has date_mod_label');
assert_true(str_contains($tv, 'sortTickets'), 'TV client sortTickets');
assert_true(str_contains($tv, "sort: 'newest'"), 'TV default sort newest');
assert_true(str_contains($tv, 'date_mod'), 'TV prefs include date_mod');
assert_true(str_contains($tv, "oldest: false"), 'TV oldest KPI default off');
assert_true(str_contains($tv, '.tv-kpis') && str_contains($tv, 'flex: 1 1 0'), 'TV KPIs flex auto-fill');
assert_true(str_contains($tv, 'PREF_ICONS'), 'TV prefs icons map');
assert_true(str_contains($tv, 'tv-col__title') && str_contains($tv, 'prefIcon(key)'), 'TV column title uses prefIcon');
assert_true(str_contains($tv, 'tv-card__top'), 'TV card has top row');
assert_true(str_contains($tv, 'tv-card__foot'), 'TV card has unified foot');
assert_true(str_contains($tv, 'data-pref-group="sort"'), 'TV prefs sort group');
assert_true(str_contains($po, 'msgstr "ID do chamado"'), 'pt_BR Ticket ID');
assert_true(str_contains($po, 'msgstr "Última atualização"'), 'pt_BR Last update');
assert_true(str_contains($po, 'msgstr "Mais novos primeiro"'), 'pt_BR Newest first');
assert_true(str_contains($ticketsSvc, "'today'"), 'TV KPIs include today');
assert_true(str_contains($ticketsSvc, "'oldest'"), 'TV KPIs include oldest');
assert_true(str_contains($ticketsSvc, 'validation_waiting'), 'TV KPIs include validation_waiting key');
assert_true(str_contains($ticketsSvc, 'solution_waiting'), 'TV KPIs include solution_waiting');
assert_true(str_contains($ticketsSvc, "'validation'"), 'TV queues include validation column');
assert_true(str_contains($ticketsSvc, "'solution'"), 'TV queues include solution column');
assert_true(str_contains($ticketsSvc, 'observers') || str_contains($tvIncCore, 'observers'), 'TV queues expose observers');
assert_true(str_contains($tvIncCore, 'date_label'), 'TV queues expose date_label');
assert_true(str_contains($tvIncCore, 'function plugin_paineldebordo_tv_tech_name_sql'), 'tv_tech_name_sql helper');
assert_true(str_contains($icons, "'volume'"), 'icons has volume');
assert_true(str_contains($icons, "'fullscreen'"), 'icons has fullscreen');
assert_true(str_contains($icons, "'external_link'"), 'icons has external_link');
assert_true(str_contains($icons, "'cog' => 'cog'") || preg_match("/'cog'\\s*=>\\s*'cog'/", $icons), 'icons alias has cog identity');
assert_true(str_contains($tv, 'kpi_today'), 'TV has today KPI');
assert_true(str_contains($tv, 'kpi_oldest'), 'TV has oldest KPI');
assert_true(str_contains($tv, 'kpi_validation'), 'TV has validation KPI');
assert_true(str_contains($tv, 'kpi_solution'), 'TV has solution KPI');
assert_true(str_contains($tv, 'ICON_VOLUME'), 'TV uses volume icon toggle');
assert_true(str_contains($tv, 'tv-card__tech'), 'TV card renders tech');
assert_true(str_contains($tv, 'pdb_tv_display'), 'TV display prefs localStorage');
assert_true(str_contains($tv, 'ticket.form.php') || str_contains($tv, 'TICKET_BASE'), 'TV open ticket URL');
assert_true(str_contains($tv, 'chime'), 'TV chime audio');
assert_true(str_contains($tv, "__('View', 'paineldebordo')") || str_contains($tv, "'View'"), 'TV uses View msgid');
assert_true(!str_contains($tv, 'tv_mute_btn'), 'TV has no separate mute button');
assert_true(str_contains($po, 'msgstr "Abertos hoje"'), 'pt_BR has Abertos hoje');
assert_true(str_contains($po, 'msgstr "Mais antigo"'), 'pt_BR has Mais antigo');
assert_true(str_contains($po, 'msgstr "Validação"'), 'pt_BR has Validação');
assert_true(str_contains($po, 'msgstr "Ver"'), 'pt_BR has Ver');
assert_true(str_contains($po, 'msgstr "Abertos"'), 'pt_BR Open → Abertos');
assert_true(str_contains($po, 'msgstr "Em atendimento"'), 'pt_BR In progress → Em atendimento');
assert_true(str_contains($po, 'msgid "In progress"'), 'pt_BR has In progress msgid');
assert_true(str_contains($po, 'msgstr "No mês"'), 'pt_BR This month → No mês');
assert_true(str_contains($po, 'msgstr "Para mim"'), 'pt_BR For me');
assert_true(substr_count($po, 'msgid "Open"') === 1, 'pt_BR has single Open msgid');
assert_true(str_contains($tv, 'kpi_month'), 'TV has month KPI');
assert_true(str_contains($tv, 'data-pref-group="views"'), 'TV prefs extra views group');
assert_true(str_contains($tv, "[data-theme=\"dark\"] .tv-kpi") || str_contains($tv, "[data-theme=\"dark\"] .tv-kpi "), 'TV dark KPI stripe CSS');
assert_true(str_contains($tv, "'In progress'"), 'TV uses In progress label');
assert_true(str_contains($tv, 'white-space: nowrap'), 'TV column title nowrap');
assert_true(str_contains($tv, 'text-overflow: ellipsis'), 'TV column title ellipsis');
assert_true(!str_contains($tv, 'Processing (assigned)'), 'TV dropped Processing (assigned) label');
assert_true(str_contains($tv, "tv-kpi__label"), 'TV KPI labels present');
assert_true(str_contains($tv, "__('Approved'"), 'TV uses Approved short label');
assert_true(str_contains($tv, "ICON_AGE"), 'TV card age icon const');
assert_true(str_contains($tv, "ICON_OBS"), 'TV card observers icon const');
assert_true(str_contains($tv, "ICON_GROUP"), 'TV card group icon const');
assert_true(str_contains($tv, "ICON_USER"), 'TV card tech icon const');
assert_true(str_contains($tv, 'data-cols="1"] .tv-card'), 'TV comfortable scale cols 1');
assert_true(str_contains($tv, 'bottom: 0.75rem; right: 0.75rem'), 'TV toasts bottom-right');
assert_true(!str_contains($tv, 'top: 0.75rem; right: 0.75rem'), 'TV toasts not top-right');
assert_true(str_contains($tv, 'translateY(12px)'), 'TV toast anim from bottom');
assert_true(!preg_match('/data-cols="6"\] \.tv-card__obs[\s\S]{0,200}display:\s*none/', $tv), 'TV dense does not hide observers');
assert_true(str_contains($tv, 'tv-card__open-label'), 'TV dense open label class');
assert_true(str_contains($tv, 'data-cols="6"] .tv-card'), 'TV dense card CSS by data-cols');
assert_true(!str_contains($tv, 'max-width: 1400px'), 'TV dropped 1400px force-columns media');
assert_true(!preg_match('/grid-template-columns:[^;]+!important/', $tv), 'TV media does not !important override cols');
assert_true(str_contains($po, 'msgid "Approved"'), 'pt_BR has Approved msgid');
assert_true(str_contains($po, 'msgstr "Aprovado"'), 'pt_BR Approved → Aprovado');
assert_true(str_contains($po, 'msgid "Week"'), 'pt_BR has Week msgid');
assert_true(str_contains($design = (string) file_get_contents($root . '/docs/DESIGN.md'), 'data-cols'), 'DESIGN docs TV density');
assert_true(str_contains($design, 'Aprovado'), 'DESIGN docs Approved label');
assert_true(str_contains($ticketsSvc, "'month'"), 'TV KPIs include month');
assert_true(str_contains($ticketsSvc, 'view_for_me') || str_contains($ticketsSvc, 'for_me') || str_contains($ticketsSvc, "'for_me'"), 'TV queues support for_me');
assert_true(str_contains($filters, "'today'"), 'filters allow today period');
assert_true(str_contains($filters, 'plugin_paineldebordo_applyDefaultFilters') || str_contains($filters, 'applyDefaultFilters'), 'default filters helper');
assert_true(str_contains($filters, 'entities_id IN') && !str_contains($filters, 'OR is_recursive = 1'), 'group options no recursive leak');
assert_true(str_contains($layout, "'for_me'"), 'nav has for_me');
assert_true(str_contains($layout, "'opened_by_me'"), 'nav has opened_by_me');
assert_true(str_contains($layout, "['today', '7d', 'month', 'ytd', 'all']"), 'layout periods month+all');
assert_true(str_contains($layout, 'ho-topnav') && str_contains($layout, 'user_menu_html'), 'topnav can host avatar menu');
assert_true(is_file($root . '/public/views/tickets_for_me.php'), 'tickets_for_me view exists');
assert_true(is_file($root . '/public/views/tickets_opened_by_me.php'), 'tickets_opened_by_me view exists');
assert_true(is_file($root . '/public/views/_tickets_table.php'), 'shared tickets table partial exists');
$tkTable = (string) file_get_contents($root . '/public/views/_tickets_table.php');
assert_true(!str_contains($tkTable, 'in_array((int) $sid, [5, 6]'), 'tickets status dropdown includes 5/6');
assert_true(str_contains($tkTable, "__('No tickets'"), 'tickets empty state No tickets');
$tkSvc = (string) file_get_contents($root . '/inc/services/tickets.php');
assert_true(str_contains($tkSvc, '$open_status_sql'), 'tickets list KPI uses open_status_sql');
assert_true(str_contains($tkSvc, '$list_status_sql'), 'tickets list has list_status_sql');
assert_true(str_contains($tkSvc, 'Solved(5) and Closed(6)'), 'tickets list docs Todos includes 5/6');
assert_true(str_contains($po, 'msgid "No tickets"'), 'pt_BR No tickets msgid');
assert_true(str_contains($design = (string) file_get_contents($root . '/docs/DESIGN.md'), 'Status → Todos'), 'DESIGN docs ticket Status All');
assert_true(str_contains((string) file_get_contents($root . '/public/views/tickets_open.php'), 'ticket.form.php'), 'open tickets link to GLPI');
assert_true(str_contains((string) file_get_contents($root . '/inc/profile.class.php'), "__('Read', 'paineldebordo')"), 'profile Read uses plugin domain');
assert_true(str_contains((string) file_get_contents($root . '/inc/services/reports.php'), "CURDATE()"), 'report_period today uses CURDATE');
$tvBoard = file_get_contents($root . '/public/ajax/tv_board.php');
assert_true(str_contains($tvBoard, "'kpis'"), 'tv_board emits kpis');
assert_true(str_contains($tvBoard, 'view_for_me'), 'tv_board accepts view_for_me');
assert_true(str_contains($tvBoard, 'view_opened_by_me'), 'tv_board accepts view_opened_by_me');
assert_true(str_contains($tvAjax, 'date_approval'), 'TV events use date_approval');

$tvInc = file_get_contents($root . '/inc/tv.inc.php');
assert_true(str_contains($tvInc, 'plugin_paineldebordo_tv_solution_approved_sql'), 'solution approved SQL helper');
assert_true(str_contains($tvInc, 'status = 3'), 'solution approved uses ACCEPTED=3');
assert_true(str_contains($po, 'msgstr "Solução aprovada"'), 'pt_BR Solution approved');
assert_true(str_contains($po, 'msgstr "Abertos por mim"'), 'pt_BR Opened by me');
assert_true(str_contains($po, 'msgstr "Geral"'), 'pt_BR Overall');
assert_true(str_contains($ticketsSvc, 'plugin_paineldebordo_tickets_requester_sql'), 'requester SQL helper');
assert_true(str_contains($ticketsSvc, 'plugin_paineldebordo_tickets_list_query_opts'), 'tickets list sort/filter opts');
foreach (['today', 'week', 'month'] as $kpiKey) {
    assert_true(
        preg_match('/\$sql_' . $kpiKey . '\s*=\s*"(.*?)"\s*;/s', $ticketsSvc, $m) === 1
            && !str_contains($m[1], 'status_open'),
        "sql_$kpiKey without status_open"
    );
}
assert_true(str_contains($filters, "'month'") && str_contains($filters, "'all'"), 'filters allow month and all');
assert_true(str_contains($filters, '30d') && str_contains($filters, 'month'), 'filters migrate 30d to month');
$branding = file_get_contents($root . '/inc/branding.inc.php');
assert_true(str_contains($branding, 'plugin_paineldebordo_chart_colors'), 'chart colors helper');
assert_true(str_contains($icons, "'nav_collapse'"), 'nav collapse icon present');
assert_true(str_contains((string) file_get_contents($root . '/public/views/assets.php'), 'itemChartsGen'), 'asset item charts generation token');
assert_true(str_contains((string) file_get_contents($root . '/public/css/dashboard-tokens.css'), '#ho_as_item_charts'), 'asset item charts CSS');
assert_true(str_contains((string) file_get_contents($root . '/public/home.php'), 'chartColors'), 'overview uses chartColors');

$access = file_get_contents($root . '/inc/access.inc.php');
assert_true(str_contains($access, 'PLUGIN_PAINELDEBORDO_RIGHT_GROUPS'), 'groups right constant');
assert_true(str_contains($access, 'plugin_paineldebordo_groups'), 'groups right name');
assert_true(!preg_match('/function plugin_paineldebordo_hasWideVision.*?haveRight\(PLUGIN_PAINELDEBORDO_RIGHT,\s*UPDATE\)/s', $access), 'wide vision not from master UPDATE');
assert_true(str_contains($access, 'isSuperAdminStrict') && str_contains($access, 'RIGHT_GROUPS'), 'wide vision uses groups or Super-Admin');
$prof = file_get_contents($root . '/inc/profile.class.php');
assert_true(str_contains($prof, 'plugin_paineldebordo_groups'), 'profile matrix has groups right');
assert_true(str_contains($prof, 'Wide group vision') || str_contains($prof, 'Panel access'), 'profile honest labels');
assert_true(str_contains($prof, 'How rights work'), 'profile help block');
assert_true(str_contains($tv, 'view_mode') && str_contains($tv, 'Board view'), 'TV board view pref');
assert_true(str_contains($tv, 'Extra views'), 'TV keeps Extra views');
assert_true(str_contains($tvBoard, 'view_mode'), 'tv_board accepts view_mode');
assert_true(str_contains($layout, 'entity_locked'), 'layout entity lock flag');
assert_true(str_contains((string) file_get_contents($root . '/docs/PERMISSIONS.md'), 'plugin_paineldebordo_groups'), 'PERMISSIONS docs groups right');
assert_true(str_contains($po, 'msgstr "Visão ampla de grupos"'), 'pt_BR Wide group vision');
assert_true(str_contains($po, 'msgstr "Visão do mural"'), 'pt_BR Board view');

$layout = file_get_contents($root . '/inc/layout.inc.php');
assert_true(str_contains($layout, 'Overview'), 'layout nav English msgid');
assert_true(str_contains($layout, 'nav_layout'), 'layout supports nav_layout');
assert_true(is_file($root . '/inc/tv_pair.inc.php'), 'tv_pair.inc.php exists');
assert_true(is_file($root . '/locales/pt_BR.po'), 'pt_BR.po exists');

$hook = file_get_contents($root . '/hook.php');
assert_true(str_contains($hook, 'function plugin_paineldebordo_install'), 'install hook exists');
assert_true(str_contains($hook, 'glpi_plugin_dashboard_config'), 'migration from old dashboard tables referenced');
assert_true(str_contains($hook, 'plugin_paineldebordo'), 'new right name referenced');

echo "\n---\nPassed: $passed  Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
