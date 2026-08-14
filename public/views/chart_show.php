<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/charts.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');

$catalog = plugin_paineldebordo_charts_catalog();
$chart = plugin_paineldebordo_chart_alias((string) ($_GET['chart'] ?? 'status'));
if (!isset($catalog[$chart])) {
    $chart = 'status';
}
$ds = plugin_paineldebordo_chart_dataset($chart);
$type = plugin_paineldebordo_chart_hc_type($chart);
$theme = plugin_paineldebordo_getFilters()['theme'];
$sum = array_sum($ds['data'] ?? []);
$has_data = !empty($ds['categories']) && $sum > 0;
$payload = [
    'id'         => $chart,
    'title'      => $catalog[$chart]['title'],
    'desc'       => $catalog[$chart]['desc'] ?? '',
    'type'       => $type,
    'categories' => $ds['categories'],
    'data'       => $ds['data'],
    'colors'     => $ds['colors'],
    'has_data'   => $has_data,
];

$hc_urls = [];
if (function_exists('plugin_paineldebordo_asset_bases')) {
    foreach (plugin_paineldebordo_asset_bases() as $b) {
        $hc_urls[] = rtrim($b, '/') . '/js/highcharts.js';
    }
}
$hc_urls[] = plugin_paineldebordo_public_url('js/highcharts.js');
$hc_urls[] = 'js/highcharts.js';
$hc_urls[] = 'https://code.highcharts.com/6.2.0/highcharts.js';
$hc_urls = array_values(array_unique(array_filter($hc_urls)));

$base = rtrim(plugin_paineldebordo_public_url(''), '/');
$export_urls = [
    $base . '/js/modules/exporting.js',
    $base . '/js/modules/offline-exporting.js',
    'https://code.highcharts.com/6.2.0/modules/exporting.js',
    'https://code.highcharts.com/6.2.0/modules/offline-exporting.js',
];
?>
<div class="ho-toolbar">
  <a class="btn btn-sm btn-outline-secondary" href="shell.php?page=charts&theme=<?php echo urlencode($theme); ?>">&larr; <?php echo __('Charts', 'paineldebordo'); ?></a>
  <span class="ho-toolbar__spacer"></span>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="ho_cs_fs"><?php echo plugin_paineldebordo_icon('fullscreen'); ?> <?php echo __('Fullscreen', 'paineldebordo'); ?></button>
  <div class="ho-menu">
    <button type="button" class="btn btn-sm btn-outline-secondary ho-menu__trigger ho-tip" aria-haspopup="true" aria-expanded="false"
            data-tip="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>"
            aria-label="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>">
      <?php echo plugin_paineldebordo_icon('download'); ?> <?php echo plugin_paineldebordo_icon('chevron_down'); ?>
    </button>
    <div class="ho-menu__panel" hidden>
      <button type="button" class="ho-menu__item" id="ho_cs_jpg"><?php echo plugin_paineldebordo_icon('doc'); ?><?php echo htmlspecialchars(__('JPG', 'paineldebordo')); ?></button>
      <button type="button" class="ho-menu__item" id="ho_cs_pdf"><?php echo plugin_paineldebordo_icon('doc'); ?><?php echo htmlspecialchars(__('PDF', 'paineldebordo')); ?></button>
    </div>
  </div>
</div>
<div class="card ho-panel" data-chart-id="<?php echo htmlspecialchars($chart); ?>">
  <div class="card-header ho-card-head"><?php echo htmlspecialchars($catalog[$chart]['title']); ?></div>
  <div class="card-body">
    <?php if (!empty($catalog[$chart]['desc'])) { ?>
      <p class="ho-panel__desc"><?php echo htmlspecialchars($catalog[$chart]['desc']); ?></p>
    <?php } ?>
    <?php if (!$has_data) { ?>
      <p class="ho-empty"><?php echo __('No data', 'paineldebordo'); ?></p>
    <?php } ?>
    <div id="ho_chart_main" class="ho-dash-chart ho-dash-chart--hero" style="<?php echo $has_data ? '' : 'display:none;'; ?>"></div>
  </div>
</div>

<div class="ho-chart-fs" id="ho_chart_fs" hidden>
  <div class="ho-chart-fs__bar">
    <strong><?php echo htmlspecialchars($catalog[$chart]['title']); ?></strong>
    <div class="ho-panel__actions">
      <div class="ho-menu">
        <button type="button" class="ho-panel__btn ho-menu__trigger ho-tip" aria-haspopup="true" aria-expanded="false"
                data-tip="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>"
                aria-label="<?php echo htmlspecialchars(__('Export', 'paineldebordo')); ?>">
          <?php echo plugin_paineldebordo_icon('download'); ?><?php echo plugin_paineldebordo_icon('chevron_down'); ?>
        </button>
        <div class="ho-menu__panel" hidden>
          <button type="button" class="ho-menu__item" id="ho_fs_jpg"><?php echo plugin_paineldebordo_icon('doc'); ?><?php echo htmlspecialchars(__('JPG', 'paineldebordo')); ?></button>
          <button type="button" class="ho-menu__item" id="ho_fs_pdf"><?php echo plugin_paineldebordo_icon('doc'); ?><?php echo htmlspecialchars(__('PDF', 'paineldebordo')); ?></button>
        </div>
      </div>
      <button type="button" class="ho-panel__btn" id="ho_fs_close" aria-label="<?php echo htmlspecialchars(__('Close')); ?>">×</button>
    </div>
  </div>
  <div id="ho_chart_fs_mount" class="ho-chart-fs__mount"></div>
</div>

<script>
(function(){
  var c = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE); ?>;
  var ticketLabel = <?php echo json_encode(__('Tickets', 'paineldebordo')); ?>;
  var failLabel = <?php echo json_encode(__('failed to load', 'paineldebordo')); ?>;
  var chartColors = <?php echo json_encode(function_exists('plugin_paineldebordo_chart_colors') ? plugin_paineldebordo_chart_colors() : ['#09141F', '#E73E11']); ?>;
  var tries = <?php echo json_encode($hc_urls); ?>;
  var exportMods = <?php echo json_encode($export_urls); ?>;
  var mainChart = null;
  var fsChart = null;

  function loadScript(src) {
    return new Promise(function (resolve) {
      var s = document.createElement('script');
      s.src = src;
      s.onload = function () { resolve(true); };
      s.onerror = function () { resolve(false); };
      document.head.appendChild(s);
    });
  }

  async function ensureHC() {
    if (typeof Highcharts !== 'undefined') return true;
    for (var i = 0; i < tries.length; i++) {
      if (await loadScript(tries[i]) && typeof Highcharts !== 'undefined') return true;
    }
    return false;
  }

  async function ensureExport() {
    if (!await ensureHC()) return false;
    if (Highcharts.Chart.prototype.exportChartLocal) return true;
    for (var i = 0; i < exportMods.length; i++) {
      await loadScript(exportMods[i]);
      if (Highcharts.Chart.prototype.exportChartLocal) return true;
    }
    return !!(Highcharts.Chart.prototype.exportChartLocal || Highcharts.Chart.prototype.exportChart);
  }

  function opts() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var muted = isDark ? '#9aa3ad' : '#626976';
    var grid = isDark ? 'rgba(231,62,17,0.25)' : 'rgba(98,105,118,0.16)';
    var seriesData = c.data;
    if (c.type === 'pie') {
      seriesData = (c.categories || []).map(function (name, i) {
        return { name: name, y: c.data[i] || 0 };
      });
    }
    return {
      chart: { type: c.type, backgroundColor: 'transparent', style: { fontFamily: 'Source Sans 3, Segoe UI, sans-serif' } },
      title: { text: null },
      credits: { enabled: false },
      colors: c.colors || chartColors,
      exporting: { enabled: true, fallbackToExportServer: false },
      xAxis: { categories: c.categories, labels: { style: { color: muted } }, lineColor: grid, tickColor: grid, title: { text: null } },
      yAxis: { labels: { style: { color: muted } }, gridLineColor: grid, title: { text: null }, allowDecimals: false, min: 0 },
      legend: { enabled: c.type === 'pie', itemStyle: { color: muted } },
      series: [{ name: ticketLabel, data: seriesData, colorByPoint: c.type !== 'areaspline', color: (chartColors && chartColors[1]) || '#E73E11' }]
    };
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

  function doExport(type) {
    ensureExport().then(function (ok) {
      var ch = fsChart || mainChart;
      if (!ok || !ch) return;
      auditExport(c.id, type);
      var fn = 'paineldebordo-' + c.id;
      // Chart title is a separate HTML heading, not the Highcharts title
      // (kept null to avoid duplicating it) — inject it via exportChartLocal's
      // own chartOptions argument (a throwaway export-only clone, doesn't
      // touch the live chart). Also pin a web-safe font: the export renderer
      // (canvg) doesn't reliably pick up the page's custom web font.
      var exportChartOptions = {
        title: { text: c.title || '' },
        // Opaque white background: the live chart is 'transparent', but JPG has
        // no alpha, so transparent pixels encode as BLACK (that's why JPG came
        // out dark). Force white + light label/grid so the export is always
        // light regardless of the on-screen theme (export-only clone).
        chart: { backgroundColor: '#ffffff', style: { fontFamily: 'Arial, Helvetica, sans-serif' } },
        xAxis: { labels: { style: { color: '#626976' } }, lineColor: 'rgba(98,105,118,0.16)', tickColor: 'rgba(98,105,118,0.16)' },
        yAxis: { labels: { style: { color: '#626976' } }, gridLineColor: 'rgba(98,105,118,0.16)' },
        legend: { itemStyle: { color: '#626976' } }
      };
      // PDF: preview in a new tab instead of forcing an immediate download
      // (same one-shot Highcharts.downloadURL swap as public/home.php).
      var previewPdf = type === 'application/pdf' && typeof Highcharts.downloadURL === 'function';
      var originalDownloadURL = Highcharts.downloadURL;
      if (previewPdf) {
        Highcharts.downloadURL = function (url, name) {
          Highcharts.downloadURL = originalDownloadURL;
          try {
            var target = (typeof url === 'string' && typeof Highcharts.dataURLtoBlob === 'function')
              ? Highcharts.dataURLtoBlob(url) : url;
            window.open(target, '_blank');
          } catch (e) {
            originalDownloadURL(url, name);
          }
        };
      }
      try {
        if (ch.exportChartLocal) ch.exportChartLocal({ type: type, filename: fn }, exportChartOptions);
        else if (ch.exportChart) ch.exportChart({ type: type, filename: fn });
      } catch (e) {
        Highcharts.downloadURL = originalDownloadURL;
        if (type.indexOf('jpeg') >= 0 && ch.exportChartLocal) {
          try { ch.exportChartLocal({ type: 'image/png', filename: fn }, exportChartOptions); } catch (e2) {}
        }
      }
    });
  }

  function openFs() {
    if (!c.has_data) return;
    var modal = document.getElementById('ho_chart_fs');
    if (modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }
    modal.hidden = false;
    document.body.classList.add('ho-chart-fs-open');
    ensureHC().then(function () {
      if (fsChart) try { fsChart.destroy(); } catch (e) {}
      fsChart = Highcharts.chart('ho_chart_fs_mount', opts());
    });
  }
  function closeFs() {
    document.getElementById('ho_chart_fs').hidden = true;
    document.body.classList.remove('ho-chart-fs-open');
    if (fsChart) { try { fsChart.destroy(); } catch (e) {} fsChart = null; }
  }

  document.getElementById('ho_cs_fs').addEventListener('click', openFs);
  document.getElementById('ho_cs_jpg').addEventListener('click', function () { doExport('image/jpeg'); });
  document.getElementById('ho_cs_pdf').addEventListener('click', function () { doExport('application/pdf'); });
  document.getElementById('ho_fs_close').addEventListener('click', closeFs);
  document.getElementById('ho_fs_jpg').addEventListener('click', function () { doExport('image/jpeg'); });
  document.getElementById('ho_fs_pdf').addEventListener('click', function () { doExport('application/pdf'); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closeFs(); });

  (async function () {
    if (!c.has_data) return;
    if (!(await ensureHC())) {
      var el = document.getElementById('ho_chart_main');
      if (el) el.outerHTML = '<p class="ho-empty">Highcharts JS ' + failLabel + '</p>';
      return;
    }
    mainChart = Highcharts.chart('ho_chart_main', opts());
  })();
})();
</script>
