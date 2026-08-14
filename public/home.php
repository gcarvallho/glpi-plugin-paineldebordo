<?php
/**
 * Overview — NOC/BI mural: period-aware KPIs, queues, charts, fullscreen + export.
 */

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/overview.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');

global $CFG_GLPI;

$board = plugin_paineldebordo_overview_board();
$filters = plugin_paineldebordo_getFilters();
$theme = $filters['theme'];
$period_label = $board['period_label'] ?? plugin_paineldebordo_period_label($filters['period'] ?? '30d');
$plugin_web = ($GLOBALS['HO_LAYOUT']['plugin_web'] ?? '') ?: (($CFG_GLPI['root_doc'] ?? '') . '/plugins/paineldebordo/public');
$plugin_web = rtrim($plugin_web, '/');

$by_id = [];
foreach ($board['charts'] as $c) {
    $by_id[$c['id']] = $c;
}
$layout = $board['layout'] ?? plugin_paineldebordo_overview_layout_get();
$hero_id = (string) ($board['hero_id'] ?? $layout['hero'] ?? 'evolution');
$spotlight_ids = $board['spotlight_ids'] ?? ($layout['spotlight'] ?? ['flow', 'status', 'aging']);
if (!is_array($spotlight_ids)) {
    $spotlight_ids = ['flow', 'status', 'aging'];
}
$hero = $by_id[$hero_id] ?? null;
$spotlight = [];
foreach ($spotlight_ids as $sid) {
    if ($sid === $hero_id) {
        continue;
    }
    if (isset($by_id[$sid])) {
        $spotlight[] = $by_id[$sid];
    }
}
$used = [$hero_id];
foreach ($spotlight as $sc) {
    $used[] = $sc['id'];
}
$grid = [];
foreach ($board['charts'] as $c) {
    if (in_array($c['id'], $used, true)) {
        continue;
    }
    $grid[] = $c;
}

$show_queues = !empty($board['queues']) || !empty($board['oldest']);
$show_shortcuts = !empty($board['show_shortcuts']);
$layout_labels = plugin_paineldebordo_overview_layout_labels();
$csrf = plugin_paineldebordo_csrf_token();

$shortcuts = [
    ['id' => 'tickets', 'label' => __('Open tickets', 'paineldebordo'), 'desc' => __('Live backlog', 'paineldebordo'), 'href' => 'shell.php?' . http_build_query(['page' => 'tickets', 'theme' => $theme])],
    ['id' => 'charts', 'label' => __('Charts', 'paineldebordo'), 'desc' => __('Full catalog', 'paineldebordo'), 'href' => 'shell.php?' . http_build_query(['page' => 'charts', 'theme' => $theme])],
    ['id' => 'reports', 'label' => __('Reports', 'paineldebordo'), 'desc' => __('Exports & tables', 'paineldebordo'), 'href' => 'shell.php?' . http_build_query(['page' => 'reports', 'theme' => $theme])],
    ['id' => 'tv', 'label' => __('TV mode', 'paineldebordo'), 'desc' => __('Wallboard', 'paineldebordo'), 'href' => 'tv.php?theme=' . rawurlencode($theme)],
    ['id' => 'map', 'label' => __('Map', 'paineldebordo'), 'desc' => __('By location', 'paineldebordo'), 'href' => 'shell.php?' . http_build_query(['page' => 'map', 'theme' => $theme])],
    ['id' => 'metrics', 'label' => __('BI', 'paineldebordo'), 'desc' => __('Interactive canvas', 'paineldebordo'), 'href' => 'shell.php?' . http_build_query(['page' => 'metrics', 'theme' => $theme])],
];

$show_tv_pair = plugin_paineldebordo_canRead() && !plugin_paineldebordo_canConfigure();

/**
 * @param array<string,mixed> $c
 */
function plugin_paineldebordo_render_chart_panel(array $c, string $chartClass = 'ho-dash-chart'): void
{
    $id = htmlspecialchars((string) $c['id']);
    ?>
  <div class="ho-panel" data-chart-id="<?php echo $id; ?>">
    <div class="ho-panel__head">
      <h3 class="ho-panel__title">
        <?php echo plugin_paineldebordo_icon((string) $c['id'], 'ho-panel__icon'); ?>
        <span class="ho-tip" data-tip="<?php echo htmlspecialchars((string) $c['title']); ?>"><?php echo htmlspecialchars((string) $c['title']); ?></span>
      </h3>
      <div class="ho-panel__actions">
        <button type="button" class="ho-panel__btn ho-tip" data-chart-fs="<?php echo $id; ?>" data-tip="<?php echo htmlspecialchars(__('Fullscreen', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('fullscreen'); ?></button>
        <div class="ho-menu">
          <button type="button" class="ho-panel__btn ho-menu__trigger ho-tip" aria-haspopup="true" aria-expanded="false"
                  data-tip="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>"
                  aria-label="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>">
            <?php echo plugin_paineldebordo_icon('download'); ?><?php echo plugin_paineldebordo_icon('chevron_down'); ?>
          </button>
          <div class="ho-menu__panel" hidden>
            <button type="button" class="ho-menu__item" data-chart-jpg="<?php echo $id; ?>"><?php echo plugin_paineldebordo_icon('doc'); ?><?php echo htmlspecialchars(__('JPG', 'paineldebordo')); ?></button>
            <button type="button" class="ho-menu__item" data-chart-pdf="<?php echo $id; ?>"><?php echo plugin_paineldebordo_icon('doc'); ?><?php echo htmlspecialchars(__('PDF', 'paineldebordo')); ?></button>
          </div>
        </div>
        <a class="ho-panel__link ho-tip" href="<?php echo htmlspecialchars((string) $c['href']); ?>" data-tip="<?php echo htmlspecialchars(__('Open chart', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars(__('Open chart', 'paineldebordo')); ?>">↗</a>
      </div>
    </div>
    <?php if (!empty($c['desc'])) { ?>
      <p class="ho-panel__desc"><?php echo htmlspecialchars((string) $c['desc']); ?></p>
    <?php } ?>
    <?php if (empty($c['has_data'])) { ?>
      <p class="ho-empty" style="padding:1.5rem 0;"><?php echo __('No data', 'paineldebordo'); ?></p>
    <?php } ?>
    <div id="ho_ov_<?php echo $id; ?>" class="<?php echo htmlspecialchars($chartClass); ?>" style="<?php echo !empty($c['has_data']) ? '' : 'display:none;'; ?>"></div>
  </div>
    <?php
}
?>

<header class="ho-section-head ho-dash-intro">
  <?php echo plugin_paineldebordo_icon('home'); ?>
  <div>
    <h2 class="ho-dash-intro__title"><?php echo __('Overview', 'paineldebordo'); ?></h2>
    <p class="ho-dash-intro__meta" id="ho_ov_period_meta"><?php echo htmlspecialchars($period_label); ?> · <?php echo __('Operational mural', 'paineldebordo'); ?></p>
  </div>
  <div class="ho-dash-intro__actions">
    <button type="button" class="ho-panel__btn" id="ho_ov_prefs_btn" aria-expanded="false" aria-controls="ho_ov_prefs">
      <?php echo plugin_paineldebordo_icon('cog'); ?>
      <span><?php echo __('Customize mural', 'paineldebordo'); ?></span>
    </button>
  </div>
</header>

<aside class="ho-prefs" id="ho_ov_prefs" role="dialog" aria-label="<?php echo htmlspecialchars(__('Customize mural', 'paineldebordo')); ?>" hidden>
  <div class="ho-prefs__head">
    <strong><?php echo plugin_paineldebordo_icon('cog'); ?> <?php echo __('Customize mural', 'paineldebordo'); ?></strong>
    <button type="button" class="ho-panel__btn" id="ho_ov_prefs_close" aria-label="<?php echo htmlspecialchars(__('Close')); ?>">×</button>
  </div>
  <p class="ho-prefs__hint"><?php echo __('Show, hide and reorder widgets for your Overview.', 'paineldebordo'); ?></p>
  <div class="ho-prefs__group" data-pref-group="kpis">
    <h3><?php echo plugin_paineldebordo_icon('kpis'); ?> <?php echo __('KPIs', 'paineldebordo'); ?></h3>
  </div>
  <div class="ho-prefs__group" data-pref-group="sections">
    <h3><?php echo plugin_paineldebordo_icon('sections'); ?> <?php echo __('Sections', 'paineldebordo'); ?></h3>
  </div>
  <div class="ho-prefs__group" data-pref-group="charts">
    <h3><?php echo plugin_paineldebordo_icon('charts'); ?> <?php echo __('Charts', 'paineldebordo'); ?></h3>
  </div>
  <div class="ho-prefs__group" data-pref-group="roles">
    <h3><?php echo plugin_paineldebordo_icon('roles'); ?> <?php echo __('Layout roles', 'paineldebordo'); ?></h3>
    <label class="ho-prefs__row">
      <span class="ho-prefs__role-label"><?php echo plugin_paineldebordo_icon('hero'); ?> <?php echo __('Hero chart', 'paineldebordo'); ?></span>
      <select class="ho-select" id="ho_ov_pref_hero"></select>
    </label>
    <label class="ho-prefs__row">
      <span class="ho-prefs__role-label"><?php echo plugin_paineldebordo_icon('charts'); ?> <?php echo __('Spotlight 1', 'paineldebordo'); ?></span>
      <select class="ho-select" id="ho_ov_pref_spot0"></select>
    </label>
    <label class="ho-prefs__row">
      <span class="ho-prefs__role-label"><?php echo plugin_paineldebordo_icon('charts'); ?> <?php echo __('Spotlight 2', 'paineldebordo'); ?></span>
      <select class="ho-select" id="ho_ov_pref_spot1"></select>
    </label>
    <label class="ho-prefs__row">
      <span class="ho-prefs__role-label"><?php echo plugin_paineldebordo_icon('charts'); ?> <?php echo __('Spotlight 3', 'paineldebordo'); ?></span>
      <select class="ho-select" id="ho_ov_pref_spot2"></select>
    </label>
  </div>
  <div class="ho-prefs__foot">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="ho_ov_prefs_reset"><?php echo plugin_paineldebordo_icon('refresh'); ?> <?php echo __('Reset defaults', 'paineldebordo'); ?></button>
    <button type="button" class="btn btn-primary btn-sm" id="ho_ov_prefs_save"><?php echo plugin_paineldebordo_icon('solved'); ?> <?php echo __('Save', 'paineldebordo'); ?></button>
  </div>
</aside>

<?php if (!empty($board['kpis'])) { ?>
<section class="ho-section-head"><span><?php echo __('Indicators', 'paineldebordo'); ?></span></section>
<section class="ho-kpi-grid ho-kpi-grid--rich" id="ho_ov_kpis">
<?php foreach ($board['kpis'] as $k) { ?>
  <a class="ho-kpi ho-kpi--<?php echo htmlspecialchars($k['mod']); ?> ho-kpi--link" href="<?php echo htmlspecialchars($k['href']); ?>" data-kpi="<?php echo htmlspecialchars($k['key']); ?>">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon($k['key'] === 'due_24h' ? 'late' : ($k['key'] === 'opened_period' || $k['key'] === 'solved_period' || $k['key'] === 'balance' ? 'opened' : ($k['key'] === 'validation' || $k['key'] === 'solution' ? 'solved' : ($k['key'] === 'ontime' ? 'percent' : ($k['key'] === 'requesters' ? 'users' : 'backlog'))))); ?></span>
    <p class="ho-kpi__label"><?php echo htmlspecialchars($k['label']); ?></p>
    <p class="ho-kpi__value"><?php echo htmlspecialchars((string) $k['value']); ?></p>
    <p class="ho-kpi__meta"><?php echo htmlspecialchars($k['meta']); ?></p>
  </a>
<?php } ?>
</section>
<?php } ?>

<?php if ($show_queues) { ?>
<section class="ho-section-head" id="ho_ov_queues_head"><span><?php echo __('Queues', 'paineldebordo'); ?></span></section>
<section class="ho-queue-strip" id="ho_ov_queues">
<?php foreach ($board['queues'] as $q) { ?>
  <article class="ho-queue-chip">
    <span class="ho-queue-chip__label"><?php echo htmlspecialchars($q['label']); ?></span>
    <strong class="ho-queue-chip__count"><?php echo (int) $q['count']; ?></strong>
  </article>
<?php } ?>
<?php if (!empty($board['oldest'])) {
    $o = $board['oldest']; ?>
  <a class="ho-queue-chip ho-queue-chip--oldest<?php echo !empty($o['late']) ? ' is-late' : ''; ?>" href="<?php echo htmlspecialchars($o['href']); ?>">
    <span class="ho-queue-chip__label"><?php echo __('Oldest ticket', 'paineldebordo'); ?> #<?php echo (int) $o['id']; ?></span>
    <strong class="ho-queue-chip__count"><?php echo htmlspecialchars($o['age']); ?></strong>
    <span class="ho-queue-chip__sub"><?php echo htmlspecialchars($o['title']); ?></span>
  </a>
<?php } ?>
</section>
<?php } ?>

<?php if ($show_shortcuts) { ?>
<section class="ho-section-head" id="ho_ov_shortcuts_head"><span><?php echo __('Shortcuts', 'paineldebordo'); ?></span></section>
<section class="ho-quick-nav" id="ho_ov_shortcuts">
<?php foreach ($shortcuts as $s) { ?>
  <a class="card ho-tile ho-quick-nav__tile" href="<?php echo htmlspecialchars($s['href']); ?>">
    <div class="card-body">
      <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon($s['id']); ?></span>
      <p class="ho-tile__title"><?php echo htmlspecialchars($s['label']); ?></p>
      <p class="ho-check-list__hint"><?php echo htmlspecialchars($s['desc']); ?></p>
    </div>
  </a>
<?php } ?>
</section>
<?php } ?>

<?php if ($show_tv_pair) { ?>
<div class="card" id="ho_home_tv" style="margin-bottom:1rem;">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('tv'); ?>
    <span><?php echo __('TV mode', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body ho-form-row" style="margin-bottom:0;">
    <a class="btn btn-outline-secondary btn-sm" href="tv_pair.php" target="_blank" rel="noopener"><?php echo __('Open TV pairing link', 'paineldebordo'); ?></a>
    <a class="btn btn-outline-secondary btn-sm" href="tv.php"><?php echo __('Open logged-in TV', 'paineldebordo'); ?></a>
  </div>
</div>
<?php } ?>

<section class="ho-section-head"><span><?php echo __('Trends', 'paineldebordo'); ?></span></section>
<?php if ($hero) {
    plugin_paineldebordo_render_chart_panel($hero, 'ho-dash-chart ho-dash-chart--hero');
} elseif ($grid === [] && $spotlight === []) { ?>
  <p class="ho-empty"><?php echo __('No charts enabled. Use Customize mural to show widgets.', 'paineldebordo'); ?></p>
<?php } ?>

<?php if ($spotlight) { ?>
<section class="ho-section-head"><span><?php echo __('Spotlight', 'paineldebordo'); ?></span></section>
<section class="ho-dash-spotlight">
<?php foreach ($spotlight as $c) {
    plugin_paineldebordo_render_chart_panel($c, 'ho-dash-chart ho-dash-chart--spot');
} ?>
</section>
<?php } ?>

<?php if ($grid) { ?>
<section class="ho-section-head"><span><?php echo __('Distribution', 'paineldebordo'); ?></span></section>
<section class="ho-dash-grid ho-dash-grid--3">
<?php foreach ($grid as $c) {
    plugin_paineldebordo_render_chart_panel($c);
} ?>
</section>
<?php } ?>

<div class="ho-chart-fs" id="ho_chart_fs" hidden>
  <div class="ho-chart-fs__bar">
    <strong id="ho_chart_fs_title"></strong>
    <div class="ho-panel__actions">
      <div class="ho-menu">
        <button type="button" class="ho-panel__btn ho-menu__trigger ho-tip" aria-haspopup="true" aria-expanded="false"
                data-tip="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>"
                aria-label="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>">
          <?php echo plugin_paineldebordo_icon('download'); ?><?php echo plugin_paineldebordo_icon('chevron_down'); ?>
        </button>
        <div class="ho-menu__panel" hidden>
          <button type="button" class="ho-menu__item" id="ho_chart_fs_jpg"><?php echo plugin_paineldebordo_icon('doc'); ?>JPG</button>
          <button type="button" class="ho-menu__item" id="ho_chart_fs_pdf"><?php echo plugin_paineldebordo_icon('doc'); ?>PDF</button>
        </div>
      </div>
      <button type="button" class="ho-panel__btn" id="ho_chart_fs_close" aria-label="<?php echo htmlspecialchars(__('Close')); ?>">×</button>
    </div>
  </div>
  <div id="ho_chart_fs_mount" class="ho-chart-fs__mount"></div>
</div>

<script>
(function () {
  var boardEndpoint = <?php echo json_encode($plugin_web . '/ajax/overview_board.php'); ?>;
  var charts = <?php echo json_encode($board['charts'], JSON_UNESCAPED_UNICODE); ?>;
  var ticketLabel = <?php echo json_encode(__('Tickets', 'paineldebordo')); ?>;
  var failLabel = <?php echo json_encode(__('failed to load', 'paineldebordo')); ?>;
  var chartColors = <?php echo json_encode(plugin_paineldebordo_chart_colors()); ?>;
  var chartInstances = {};
  var fsChart = null;
  var fsId = null;

  var bases = <?php
    $bases = function_exists('plugin_paineldebordo_asset_bases') ? plugin_paineldebordo_asset_bases() : [$plugin_web];
    $urls = [];
    foreach ($bases as $b) {
      if ($b) {
        $urls[] = rtrim($b, '/') . '/js/highcharts.js';
      }
    }
    $urls[] = $plugin_web . '/js/highcharts.js';
    $urls[] = 'https://code.highcharts.com/6.2.0/highcharts.js';
    echo json_encode(array_values(array_unique($urls)));
  ?>;
  var exportMods = <?php
    $mods = [];
    foreach (['modules/exporting.js', 'modules/offline-exporting.js'] as $rel) {
      $mods[] = $plugin_web . '/js/' . $rel;
      $mods[] = 'https://code.highcharts.com/6.2.0/' . $rel;
    }
    echo json_encode($mods);
  ?>;

  function loadScript(src) {
    return new Promise(function (resolve) {
      var s = document.createElement('script');
      s.src = src;
      s.onload = function () { resolve(true); };
      s.onerror = function () { resolve(false); };
      document.head.appendChild(s);
    });
  }

  async function ensureHighcharts() {
    if (typeof Highcharts !== 'undefined') return true;
    for (var i = 0; i < bases.length; i++) {
      if (await loadScript(bases[i]) && typeof Highcharts !== 'undefined') return true;
    }
    return typeof Highcharts !== 'undefined';
  }

  async function ensureExporting() {
    if (!await ensureHighcharts()) return false;
    if (Highcharts.Chart && Highcharts.Chart.prototype.exportChartLocal) return true;
    for (var i = 0; i < exportMods.length; i++) {
      await loadScript(exportMods[i]);
      if (Highcharts.Chart && Highcharts.Chart.prototype.exportChartLocal) return true;
    }
    return !!(Highcharts.Chart && (Highcharts.Chart.prototype.exportChartLocal || Highcharts.Chart.prototype.exportChart));
  }

  function chartOpts(c) {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var muted = isDark ? '#9aa3ad' : '#626976';
    var grid = isDark ? 'rgba(231,62,17,0.25)' : 'rgba(98,105,118,0.16)';
    var seriesData = c.data;
    if (c.type === 'pie') {
      seriesData = (c.categories || []).map(function (name, i) {
        return { name: name, y: c.data[i] || 0 };
      });
    }
    var opts = {
      chart: { type: c.type, backgroundColor: 'transparent', style: { fontFamily: 'Source Sans 3, Segoe UI, sans-serif' } },
      title: { text: null },
      credits: { enabled: false },
      colors: c.colors || chartColors,
      xAxis: { categories: c.categories, labels: { style: { color: muted } }, lineColor: grid, tickColor: grid, title: { text: null } },
      yAxis: { labels: { style: { color: muted } }, gridLineColor: grid, title: { text: null }, allowDecimals: false, min: 0 },
      legend: { itemStyle: { color: muted }, enabled: c.type === 'pie' },
      exporting: { enabled: true, fallbackToExportServer: false },
      series: [{
        name: ticketLabel,
        data: seriesData,
        colorByPoint: c.type !== 'areaspline',
        color: '#E73E11'
      }]
    };
    if (c.type === 'areaspline') {
      opts.tooltip = { shared: true };
      opts.series[0].fillColor = {
        linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
        stops: [[0, 'rgba(231,62,17,0.35)'], [1, 'rgba(231,62,17,0.02)']]
      };
      opts.legend.enabled = false;
    } else if (c.type !== 'pie') {
      opts.legend.enabled = false;
    }
    return opts;
  }

  function findChart(id) {
    for (var i = 0; i < charts.length; i++) if (charts[i].id === id) return charts[i];
    return null;
  }

  function renderAll() {
    if (typeof Highcharts === 'undefined') {
      charts.forEach(function (c) {
        var el = document.getElementById('ho_ov_' + c.id);
        if (el) el.outerHTML = '<p class="ho-empty">Highcharts JS ' + failLabel + '</p>';
      });
      return;
    }
    charts.forEach(function (c) {
      if (!c.has_data) return;
      var elId = 'ho_ov_' + c.id;
      if (!document.getElementById(elId)) return;
      if (chartInstances[c.id]) {
        try { chartInstances[c.id].destroy(); } catch (e) {}
      }
      chartInstances[c.id] = Highcharts.chart(elId, chartOpts(c));
    });
  }

  function auditExport(id, type) {
    try {
      var fmt = type.indexOf('pdf') >= 0 ? 'pdf' : (type.indexOf('jpeg') >= 0 ? 'jpg' : type);
      var body = new FormData();
      body.append('action', 'export_chart');
      body.append('detail', id + ' ' + fmt);
      if (navigator.sendBeacon) { navigator.sendBeacon('ajax/audit.php', body); }
      else { fetch('ajax/audit.php', { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {}); }
    } catch (e) {}
  }

  function exportChart(id, type) {
    ensureExporting().then(function (ok) {
      var ch = chartInstances[id] || (fsId === id ? fsChart : null);
      if (!ok || !ch) return;
      auditExport(id, type);
      var filename = 'paineldebordo-' + id;
      // The on-screen title is a separate HTML <h3>, not the Highcharts
      // title (chart.title stays null so it doesn't duplicate that HTML
      // heading) — so the export needs it injected via exportChartLocal's
      // own chartOptions argument (merged into a throwaway export-only
      // clone, doesn't touch the live chart). Also pin a web-safe font:
      // the export renderer (canvg) doesn't reliably pick up the page's
      // custom web font, so leaving it unset produces a mismatched fallback.
      var srcChart = findChart(id);
      var exportChartOptions = {
        title: { text: (srcChart && srcChart.title) || '' },
        // Opaque white background: the live chart uses backgroundColor
        // 'transparent', but JPG has no alpha channel, so transparent pixels
        // encode as BLACK — that's why JPG exports came out dark. Force white
        // and light label/grid colors so the export is always light regardless
        // of the current on-screen theme (this is an export-only clone).
        chart: { backgroundColor: '#ffffff', style: { fontFamily: 'Arial, Helvetica, sans-serif' } },
        xAxis: { labels: { style: { color: '#626976' } }, lineColor: 'rgba(98,105,118,0.16)', tickColor: 'rgba(98,105,118,0.16)' },
        yAxis: { labels: { style: { color: '#626976' } }, gridLineColor: 'rgba(98,105,118,0.16)' },
        legend: { itemStyle: { color: '#626976' } }
      };
      // PDF: open a preview in a new tab instead of forcing an immediate
      // download. Highcharts.downloadURL is the module's own public hook for
      // this (it already falls back to window.open() on browsers that lack
      // the <a download> attribute) — swap it in for this one call only, so
      // JPG export keeps its normal direct-download behavior.
      var previewPdf = type === 'application/pdf' && typeof Highcharts.downloadURL === 'function';
      var originalDownloadURL = Highcharts.downloadURL;
      if (previewPdf) {
        Highcharts.downloadURL = function (url, name) {
          Highcharts.downloadURL = originalDownloadURL;
          try {
            // Highcharts.dataURLtoBlob() already returns a ready-to-use
            // blob: URL string (it creates the object URL internally) —
            // not a Blob instance, so it goes straight into window.open().
            var target = (typeof url === 'string' && typeof Highcharts.dataURLtoBlob === 'function')
              ? Highcharts.dataURLtoBlob(url) : url;
            window.open(target, '_blank');
          } catch (e) {
            originalDownloadURL(url, name);
          }
        };
      }
      try {
        if (ch.exportChartLocal) {
          ch.exportChartLocal({ type: type, filename: filename }, exportChartOptions);
        } else if (ch.exportChart) {
          ch.exportChart({ type: type, filename: filename });
        }
      } catch (e) {
        Highcharts.downloadURL = originalDownloadURL;
        if (type.indexOf('jpeg') >= 0 && ch.exportChartLocal) {
          try { ch.exportChartLocal({ type: 'image/png', filename: filename }, exportChartOptions); } catch (e2) {}
        }
      }
    });
  }

  function openFs(id) {
    var c = findChart(id);
    if (!c || !c.has_data) return;
    fsId = id;
    var modal = document.getElementById('ho_chart_fs');
    if (modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }
    document.getElementById('ho_chart_fs_title').textContent = c.title || id;
    modal.hidden = false;
    document.body.classList.add('ho-chart-fs-open');
    ensureHighcharts().then(function () {
      var mount = document.getElementById('ho_chart_fs_mount');
      mount.innerHTML = '';
      if (fsChart) { try { fsChart.destroy(); } catch (e) {} }
      fsChart = Highcharts.chart('ho_chart_fs_mount', chartOpts(c));
    });
  }

  function closeFs() {
    var modal = document.getElementById('ho_chart_fs');
    modal.hidden = true;
    document.body.classList.remove('ho-chart-fs-open');
    if (fsChart) { try { fsChart.destroy(); } catch (e) {} fsChart = null; }
    fsId = null;
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-chart-fs],[data-chart-jpg],[data-chart-pdf]');
    if (!t) return;
    var id = t.getAttribute('data-chart-fs') || t.getAttribute('data-chart-jpg') || t.getAttribute('data-chart-pdf');
    if (t.hasAttribute('data-chart-fs')) openFs(id);
    else if (t.hasAttribute('data-chart-jpg')) exportChart(id, 'image/jpeg');
    else if (t.hasAttribute('data-chart-pdf')) exportChart(id, 'application/pdf');
  });
  document.getElementById('ho_chart_fs_close').addEventListener('click', closeFs);
  document.getElementById('ho_chart_fs_jpg').addEventListener('click', function () { if (fsId) exportChart(fsId, 'image/jpeg'); });
  document.getElementById('ho_chart_fs_pdf').addEventListener('click', function () { if (fsId) exportChart(fsId, 'application/pdf'); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closeFs(); });

  function applyKpis(kpis) {
    var root = document.getElementById('ho_ov_kpis');
    if (!root || !kpis) return;
    kpis.forEach(function (k) {
      var el = root.querySelector('[data-kpi="' + k.key + '"]');
      if (!el) return;
      var v = el.querySelector('.ho-kpi__value');
      var m = el.querySelector('.ho-kpi__meta');
      if (v) v.textContent = k.value;
      if (m) m.textContent = k.meta;
    });
  }

  function refreshBoard() {
    fetch(boardEndpoint, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;
        applyKpis(data.kpis || []);
        if (data.period_label) {
          var meta = document.getElementById('ho_ov_period_meta');
          if (meta) meta.textContent = data.period_label + ' · <?php echo htmlspecialchars(__('Operational mural', 'paineldebordo'), ENT_QUOTES); ?>';
        }
      }).catch(function () {});
  }

  ensureHighcharts().then(function (ok) {
    if (ok) renderAll();
    else renderAll();
  });
  setInterval(refreshBoard, 60000);

  /* —— Customize mural —— */
  var layoutEndpoint = <?php echo json_encode($plugin_web . '/ajax/overview_layout.php'); ?>;
  var csrfToken = <?php echo json_encode($csrf); ?>;
  var layout = <?php echo json_encode($layout, JSON_UNESCAPED_UNICODE); ?>;
  var labels = <?php echo json_encode($layout_labels, JSON_UNESCAPED_UNICODE); ?>;
  var PREF_ICONS = <?php
    $pref_icon_keys = array_merge(
        array_keys($layout_labels['kpis'] ?? []),
        array_keys($layout_labels['sections'] ?? []),
        array_keys($layout_labels['charts'] ?? []),
        ['kpis', 'sections', 'charts', 'roles', 'hero', 'sort']
    );
    $pref_icons = [];
    foreach (array_unique($pref_icon_keys) as $ik) {
        $pref_icons[$ik] = plugin_paineldebordo_icon((string) $ik);
    }
    echo json_encode($pref_icons, JSON_UNESCAPED_UNICODE);
  ?>;
  var i18nMove = {
    up: <?php echo json_encode(__('Move up', 'paineldebordo')); ?>,
    down: <?php echo json_encode(__('Move down', 'paineldebordo')); ?>
  };
  var prefsBtn = document.getElementById('ho_ov_prefs_btn');
  var prefsPanel = document.getElementById('ho_ov_prefs');
  var prefsClose = document.getElementById('ho_ov_prefs_close');
  var prefsSave = document.getElementById('ho_ov_prefs_save');
  var prefsReset = document.getElementById('ho_ov_prefs_reset');

  function deepClone(o) { return JSON.parse(JSON.stringify(o)); }

  function prefIcon(key) {
    return PREF_ICONS[key] || '';
  }

  function moveInOrder(order, id, dir) {
    var i = order.indexOf(id);
    if (i < 0) return order;
    var j = i + dir;
    if (j < 0 || j >= order.length) return order;
    var tmp = order[i];
    order[i] = order[j];
    order[j] = tmp;
    return order;
  }

  function addToggleRow(groupEl, id, label, checked, onToggle, onUp, onDown) {
    var row = document.createElement('div');
    row.className = 'ho-prefs__row ho-prefs__row--order';
    var lab = document.createElement('label');
    lab.className = 'ho-prefs__check';
    var input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!checked;
    input.addEventListener('change', function () { onToggle(input.checked); });
    lab.appendChild(input);
    var ic = prefIcon(id);
    if (ic) {
      var wrap = document.createElement('span');
      wrap.className = 'ho-prefs__icon';
      wrap.innerHTML = ic;
      while (wrap.firstChild) lab.appendChild(wrap.firstChild);
    }
    var txt = document.createElement('span');
    txt.textContent = label;
    lab.appendChild(txt);
    row.appendChild(lab);
    if (onUp && onDown) {
      var moves = document.createElement('span');
      moves.className = 'ho-prefs__moves';
      var up = document.createElement('button');
      up.type = 'button';
      up.className = 'ho-panel__btn';
      up.textContent = '↑';
      up.title = i18nMove.up;
      up.setAttribute('aria-label', i18nMove.up);
      up.addEventListener('click', onUp);
      var down = document.createElement('button');
      down.type = 'button';
      down.className = 'ho-panel__btn';
      down.textContent = '↓';
      down.title = i18nMove.down;
      down.setAttribute('aria-label', i18nMove.down);
      down.addEventListener('click', onDown);
      moves.appendChild(up);
      moves.appendChild(down);
      row.appendChild(moves);
    }
    groupEl.appendChild(row);
  }

  function fillRoleSelects() {
    var ids = layout.chart_order || Object.keys(layout.charts || {});
    var heroSel = document.getElementById('ho_ov_pref_hero');
    var sels = [
      document.getElementById('ho_ov_pref_spot0'),
      document.getElementById('ho_ov_pref_spot1'),
      document.getElementById('ho_ov_pref_spot2')
    ];
    function fill(sel, value, allowEmpty) {
      if (!sel) return;
      sel.innerHTML = '';
      if (allowEmpty) {
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = '—';
        sel.appendChild(opt0);
      }
      ids.forEach(function (id) {
        if (!layout.charts[id]) return;
        var opt = document.createElement('option');
        opt.value = id;
        opt.textContent = (labels.charts && labels.charts[id]) || id;
        if (id === value) opt.selected = true;
        sel.appendChild(opt);
      });
    }
    fill(heroSel, layout.hero || 'evolution', false);
    var spot = layout.spotlight || [];
    fill(sels[0], spot[0] || '', true);
    fill(sels[1], spot[1] || '', true);
    fill(sels[2], spot[2] || '', true);
  }

  function buildPrefsPanel() {
    var kpiG = prefsPanel.querySelector('[data-pref-group="kpis"]');
    var secG = prefsPanel.querySelector('[data-pref-group="sections"]');
    var chtG = prefsPanel.querySelector('[data-pref-group="charts"]');
    [kpiG, secG, chtG].forEach(function (g) {
      if (!g) return;
      while (g.children.length > 1) g.removeChild(g.lastChild);
    });

    (layout.kpi_order || Object.keys(layout.kpis || {})).forEach(function (key) {
      addToggleRow(
        kpiG, key,
        (labels.kpis && labels.kpis[key]) || key,
        layout.kpis[key] !== false,
        function (v) { layout.kpis[key] = v; },
        function () { layout.kpi_order = moveInOrder(layout.kpi_order.slice(), key, -1); buildPrefsPanel(); },
        function () { layout.kpi_order = moveInOrder(layout.kpi_order.slice(), key, 1); buildPrefsPanel(); }
      );
    });

    Object.keys(labels.sections || {}).forEach(function (key) {
      addToggleRow(
        secG, key,
        labels.sections[key],
        layout.sections[key] !== false,
        function (v) { layout.sections[key] = v; },
        null, null
      );
    });

    (layout.chart_order || Object.keys(layout.charts || {})).forEach(function (id) {
      addToggleRow(
        chtG, id,
        (labels.charts && labels.charts[id]) || id,
        layout.charts[id] !== false,
        function (v) { layout.charts[id] = v; fillRoleSelects(); },
        function () { layout.chart_order = moveInOrder(layout.chart_order.slice(), id, -1); buildPrefsPanel(); },
        function () { layout.chart_order = moveInOrder(layout.chart_order.slice(), id, 1); buildPrefsPanel(); }
      );
    });

    fillRoleSelects();
  }

  function readRolesFromUi() {
    var heroSel = document.getElementById('ho_ov_pref_hero');
    if (heroSel && heroSel.value) layout.hero = heroSel.value;
    layout.spotlight = [];
    [0, 1, 2].forEach(function (i) {
      var sel = document.getElementById('ho_ov_pref_spot' + i);
      if (sel && sel.value) layout.spotlight.push(sel.value);
    });
  }

  function postLayout(action) {
    readRolesFromUi();
    var body = new FormData();
    body.append('action', action || 'save');
    body.append('layout', JSON.stringify(layout));
    if (csrfToken) body.append('_glpi_csrf_token', csrfToken);
    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    if (csrfToken) headers['X-Glpi-Csrf-Token'] = csrfToken;
    return fetch(layoutEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
      cache: 'no-store',
      headers: headers
    }).then(function (r) { return r.json(); });
  }

  function openPrefs() {
    if (prefsPanel.parentNode !== document.body) {
      document.body.appendChild(prefsPanel);
    }
    prefsPanel.hidden = false;
    prefsBtn.setAttribute('aria-expanded', 'true');
    buildPrefsPanel();
  }
  function closePrefs() {
    prefsPanel.hidden = true;
    prefsBtn.setAttribute('aria-expanded', 'false');
  }

  if (prefsBtn && prefsPanel) {
    prefsBtn.addEventListener('click', function () {
      if (prefsPanel.hidden) openPrefs(); else closePrefs();
    });
    prefsClose.addEventListener('click', closePrefs);
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !prefsPanel.hidden) closePrefs();
    });
    prefsSave.addEventListener('click', function () {
      prefsSave.disabled = true;
      postLayout('save').then(function (data) {
        if (data && data.ok) location.reload();
        else prefsSave.disabled = false;
      }).catch(function () { prefsSave.disabled = false; });
    });
    prefsReset.addEventListener('click', function () {
      prefsReset.disabled = true;
      postLayout('reset').then(function (data) {
        if (data && data.ok) location.reload();
        else prefsReset.disabled = false;
      }).catch(function () { prefsReset.disabled = false; });
    });
  }
})();
</script>
