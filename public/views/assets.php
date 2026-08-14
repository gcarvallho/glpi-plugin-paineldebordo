<?php
/**
 * Assets NOC — mural → listagem → detalhe (gráficos snapshot).
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/assets.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');

global $CFG_GLPI;

$ram_mb = isset($_GET['ram_mb']) ? (int) $_GET['ram_mb'] : 8192;
if (!in_array($ram_mb, [4096, 8192, 16384], true)) {
    $ram_mb = 8192;
}

$board_error = '';
try {
    $board = plugin_paineldebordo_assets_board($ram_mb);
} catch (Throwable $e) {
    $board_error = $e->getMessage();
    if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
        Toolbox::logInFile('paineldebordo', '[assets view] ' . $board_error . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n");
    }
    $board = [
        'ok' => false, 'tiles' => [], 'kpis' => [], 'charts' => [], 'lists' => [],
        'meta' => [],
    ];
}

$plugin_web = ($GLOBALS['HO_LAYOUT']['plugin_web'] ?? '') ?: (($CFG_GLPI['root_doc'] ?? '') . '/plugins/paineldebordo/public');
$plugin_web = rtrim($plugin_web, '/');
if (function_exists('plugin_paineldebordo_asset_base')) {
    $plugin_web = rtrim(plugin_paineldebordo_asset_base(), '/') ?: $plugin_web;
}

$kpi_icons = [
    'fleet' => 'Computer', 'computers' => 'Computer', 'dynamic' => 'refresh',
    'with_tickets' => 'tickets', 'warranty_30' => 'calendar', 'stale_inv' => 'late',
    'disk_crit' => 'volume', 'ram_low' => 'computer', 'no_agent' => 'bell',
    'no_loc' => 'map', 'licenses' => 'list', 'stock' => 'Printer',
];

?>
<?php if ($board_error !== '') { ?>
<div class="card ho-alert ho-alert--danger" role="alert">
  <div class="card-body">
    <p><?php echo htmlspecialchars(__('Could not load assets board.', 'paineldebordo')); ?></p>
    <p class="ho-check-list__hint"><?php echo htmlspecialchars($board_error); ?></p>
  </div>
</div>
<?php } ?>

<div class="ho-as" id="ho_as_root" data-view="mural">
  <nav class="ho-as-crumb" id="ho_as_crumb" aria-label="<?php echo htmlspecialchars(__('Breadcrumb', 'paineldebordo')); ?>">
    <button type="button" class="ho-as-crumb__link" data-crumb="mural"><?php echo __('Park', 'paineldebordo'); ?></button>
    <span class="ho-as-crumb__sep" aria-hidden="true">›</span>
    <button type="button" class="ho-as-crumb__link" data-crumb="list" hidden id="ho_as_crumb_list"></button>
    <span class="ho-as-crumb__sep" aria-hidden="true" id="ho_as_crumb_sep2" hidden>›</span>
    <span class="ho-as-crumb__current" id="ho_as_crumb_item" hidden></span>
  </nav>

  <!-- MURAL -->
  <div class="ho-as-view ho-as-view--mural" id="ho_as_view_mural">
    <header class="ho-section-head ho-dash-intro">
      <?php echo plugin_paineldebordo_icon('Computer'); ?>
      <div>
        <h2 class="ho-dash-intro__title"><?php echo __('Assets', 'paineldebordo'); ?></h2>
        <p class="ho-dash-intro__meta" id="ho_as_meta"><?php echo __('IT park inventory', 'paineldebordo'); ?></p>
      </div>
      <div class="ho-dash-intro__actions">
        <label class="ho-bi-filters__label" for="ho_as_ram">
          <?php echo plugin_paineldebordo_icon('computer'); ?>
          <span><?php echo __('RAM threshold', 'paineldebordo'); ?></span>
        </label>
        <select class="ho-select" id="ho_as_ram" aria-label="<?php echo htmlspecialchars(__('RAM threshold', 'paineldebordo')); ?>">
          <option value="4096"<?php echo $ram_mb === 4096 ? ' selected' : ''; ?>><?php echo __('Up to 4 GB', 'paineldebordo'); ?></option>
          <option value="8192"<?php echo $ram_mb === 8192 ? ' selected' : ''; ?>><?php echo __('Up to 8 GB', 'paineldebordo'); ?></option>
          <option value="16384"<?php echo $ram_mb === 16384 ? ' selected' : ''; ?>><?php echo __('Up to 16 GB', 'paineldebordo'); ?></option>
        </select>
      </div>
    </header>

    <section class="ho-section-head"><span><?php echo __('Park', 'paineldebordo'); ?></span></section>
    <section class="ho-catalog" id="ho_as_tiles">
    <?php foreach ($board['tiles'] ?? [] as $t) { ?>
      <button type="button" class="card ho-tile ho-tile--click" data-tile="<?php echo htmlspecialchars((string) $t['key']); ?>" data-kind="<?php echo htmlspecialchars((string) $t['key']); ?>">
        <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon((string) ($t['icon'] ?? $t['key'])); ?></span>
        <p class="ho-tile__title"><?php echo htmlspecialchars((string) $t['label']); ?></p>
        <p class="ho-tile__value"><?php echo (int) $t['value']; ?></p>
        <p class="ho-check-list__hint"><?php echo htmlspecialchars((string) ($t['meta'] ?? '')); ?></p>
      </button>
    <?php } ?>
    </section>

    <section class="ho-section-head"><span><?php echo __('Indicators', 'paineldebordo'); ?></span></section>
    <section class="ho-kpi-grid ho-kpi-grid--rich" id="ho_as_kpis">
    <?php foreach ($board['kpis'] ?? [] as $k) {
        $ik = $kpi_icons[$k['key']] ?? 'Computer';
        ?>
      <button type="button" class="ho-kpi ho-kpi--<?php echo htmlspecialchars((string) $k['mod']); ?> ho-kpi--click" data-kpi="<?php echo htmlspecialchars((string) $k['key']); ?>" data-kind="<?php echo htmlspecialchars((string) $k['key']); ?>">
        <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon($ik); ?></span>
        <p class="ho-kpi__label"><?php echo htmlspecialchars((string) $k['label']); ?></p>
        <p class="ho-kpi__value"><?php echo htmlspecialchars((string) $k['value']); ?></p>
        <p class="ho-kpi__meta"><?php echo htmlspecialchars((string) $k['meta']); ?></p>
      </button>
    <?php } ?>
    </section>

    <section class="ho-section-head"><span><?php echo __('Distribution', 'paineldebordo'); ?></span></section>
    <section class="ho-dash-grid ho-dash-grid--2" id="ho_as_charts">
    <?php foreach ($board['charts'] ?? [] as $c) {
        $id = htmlspecialchars((string) $c['id']);
        ?>
      <div class="ho-panel" data-chart-id="<?php echo $id; ?>">
        <div class="ho-panel__head">
          <h3 class="ho-panel__title">
            <?php echo plugin_paineldebordo_icon('charts', 'ho-panel__icon'); ?>
            <span class="ho-tip" data-tip="<?php echo htmlspecialchars((string) $c['title']); ?>"><?php echo htmlspecialchars((string) $c['title']); ?></span>
          </h3>
        </div>
        <?php if (empty($c['has_data'])) { ?>
          <p class="ho-empty" style="padding:1.5rem 0;"><?php echo __('No data', 'paineldebordo'); ?></p>
        <?php } ?>
        <div id="ho_as_c_<?php echo $id; ?>" class="ho-dash-chart" style="<?php echo !empty($c['has_data']) ? '' : 'display:none;'; ?>"></div>
      </div>
    <?php } ?>
    </section>

    <section class="ho-section-head"><span><?php echo __('Alerts', 'paineldebordo'); ?></span></section>
    <section class="ho-assets-lists" id="ho_as_lists">
      <div class="ho-panel">
        <div class="ho-panel__head">
          <h3 class="ho-panel__title">
            <button type="button" class="ho-as-list-title" data-kind="disks">
              <?php echo plugin_paineldebordo_icon('volume'); ?>
              <span><?php echo __('Disks almost full', 'paineldebordo'); ?></span>
            </button>
          </h3>
        </div>
        <div class="ho-assets-table-wrap" data-list="disks"></div>
      </div>
      <div class="ho-panel">
        <div class="ho-panel__head">
          <h3 class="ho-panel__title">
            <button type="button" class="ho-as-list-title" data-kind="ram_low">
              <?php echo plugin_paineldebordo_icon('computer'); ?>
              <span><?php echo __('Low RAM candidates', 'paineldebordo'); ?></span>
            </button>
          </h3>
        </div>
        <div class="ho-assets-table-wrap" data-list="ram"></div>
      </div>
      <div class="ho-panel">
        <div class="ho-panel__head">
          <h3 class="ho-panel__title">
            <button type="button" class="ho-as-list-title" data-kind="stale_inv">
              <?php echo plugin_paineldebordo_icon('late'); ?>
              <span><?php echo __('Stale inventory', 'paineldebordo'); ?></span>
            </button>
          </h3>
        </div>
        <div class="ho-assets-table-wrap" data-list="stale"></div>
      </div>
      <div class="ho-panel">
        <div class="ho-panel__head">
          <h3 class="ho-panel__title">
            <button type="button" class="ho-as-list-title" data-kind="warranty_30">
              <?php echo plugin_paineldebordo_icon('calendar'); ?>
              <span><?php echo __('Warranty ending', 'paineldebordo'); ?></span>
            </button>
          </h3>
        </div>
        <div class="ho-assets-table-wrap" data-list="warranty"></div>
      </div>
      <div class="ho-panel">
        <div class="ho-panel__head">
          <h3 class="ho-panel__title">
            <button type="button" class="ho-as-list-title" data-kind="licenses">
              <?php echo plugin_paineldebordo_icon('list'); ?>
              <span><?php echo __('Licenses expiring', 'paineldebordo'); ?></span>
            </button>
          </h3>
        </div>
        <div class="ho-assets-table-wrap" data-list="licenses"></div>
      </div>
    </section>
  </div>

  <!-- LISTAGEM -->
  <div class="ho-as-view ho-as-view--list" id="ho_as_view_list" hidden>
    <header class="ho-section-head ho-dash-intro">
      <button type="button" class="ho-panel__btn" id="ho_as_back_list"><?php echo plugin_paineldebordo_icon('back'); ?><span><?php echo __('Back', 'paineldebordo'); ?></span></button>
      <div>
        <h2 class="ho-dash-intro__title" id="ho_as_list_title"><?php echo __('Assets', 'paineldebordo'); ?></h2>
        <p class="ho-dash-intro__meta" id="ho_as_list_meta"></p>
      </div>
      <div class="ho-dash-intro__actions ho-as-list-tools">
        <label class="ho-bi-filters__label" for="ho_as_search">
          <span><?php echo __('Search', 'paineldebordo'); ?></span>
        </label>
        <input type="search" class="ho-select" id="ho_as_search" placeholder="<?php echo htmlspecialchars(__('Search by name…', 'paineldebordo')); ?>" autocomplete="off">
      </div>
    </header>
    <div class="ho-panel">
      <div class="ho-assets-table-wrap" id="ho_as_list_table">
        <p class="ho-empty"><?php echo __('Loading…', 'paineldebordo'); ?></p>
      </div>
      <div class="ho-as-pager" id="ho_as_pager" hidden>
        <button type="button" class="ho-panel__btn" id="ho_as_prev"><?php echo plugin_paineldebordo_icon('back'); ?><span><?php echo __('Previous', 'paineldebordo'); ?></span></button>
        <span id="ho_as_page_info"></span>
        <button type="button" class="ho-panel__btn" id="ho_as_next"><span><?php echo __('Next', 'paineldebordo'); ?></span><?php echo plugin_paineldebordo_icon('forward'); ?></button>
      </div>
    </div>
  </div>

  <!-- DETALHE -->
  <div class="ho-as-view ho-as-view--item" id="ho_as_view_item" hidden>
    <header class="ho-section-head ho-dash-intro">
      <button type="button" class="ho-panel__btn" id="ho_as_back_item"><?php echo plugin_paineldebordo_icon('back'); ?><span><?php echo __('Back', 'paineldebordo'); ?></span></button>
      <div>
        <h2 class="ho-dash-intro__title" id="ho_as_item_title"><?php echo __('Asset', 'paineldebordo'); ?></h2>
        <p class="ho-dash-intro__meta" id="ho_as_item_meta"></p>
      </div>
      <div class="ho-dash-intro__actions">
        <a class="ho-panel__btn" id="ho_as_glpi_link" href="#" target="_blank" rel="noopener"><?php echo plugin_paineldebordo_icon('external_link'); ?><span><?php echo __('Open in GLPI', 'paineldebordo'); ?></span></a>
      </div>
    </header>
    <section class="ho-as-item-fields" id="ho_as_item_fields"></section>
    <section class="ho-section-head"><span><?php echo __('Partitions', 'paineldebordo'); ?></span></section>
    <div class="ho-panel">
      <div class="ho-assets-table-wrap" id="ho_as_item_disks"></div>
    </div>
    <section class="ho-section-head"><span><?php echo __('Charts', 'paineldebordo'); ?></span></section>
    <section class="ho-dash-grid ho-dash-grid--2" id="ho_as_item_charts"></section>
  </div>
</div>

<script>
(function () {
  var pluginWeb = <?php echo json_encode($plugin_web); ?>;
  var boardEndpoint = pluginWeb + '/ajax/assets_board.php';
  var listEndpoint = pluginWeb + '/ajax/assets_list.php';
  var itemEndpoint = pluginWeb + '/ajax/assets_item.php';
  var failLabel = <?php echo json_encode(__('failed to load', 'paineldebordo')); ?>;
  var noData = <?php echo json_encode(__('No data', 'paineldebordo')); ?>;
  var noResults = <?php echo json_encode(__('No results', 'paineldebordo')); ?>;
  var loading = <?php echo json_encode(__('Loading…', 'paineldebordo')); ?>;
  var resultsOf = <?php echo json_encode(__('%d results', 'paineldebordo')); ?>;
  var pageOf = <?php echo json_encode(__('Page %1$d of %2$d', 'paineldebordo')); ?>;
  var charts = <?php echo json_encode($board['charts'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
  var boardLists = <?php echo json_encode($board['lists'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
  var chartColors = <?php echo json_encode(function_exists('plugin_paineldebordo_chart_colors') ? plugin_paineldebordo_chart_colors() : ['#09141F', '#E73E11']); ?>;
  var chartInstances = {};
  var itemChartInstances = {};
  var itemChartsGen = 0;
  var ramMb = <?php echo (int) $ram_mb; ?>;
  var root = document.getElementById('ho_as_root');
  var view = 'mural';
  var listState = { kind: '', title: '', page: 1, pages: 1, total: 0, q: '', columns: [], rows: [] };
  var pollTimer = null;
  var searchTimer = null;

  var bases = <?php
    $bases = function_exists('plugin_paineldebordo_asset_bases') ? plugin_paineldebordo_asset_bases() : [$plugin_web];
    $urls = [];
    foreach ($bases as $b) {
        if ($b) {
            $urls[] = rtrim($b, '/') . '/js/highcharts.js';
        }
    }
    $urls[] = 'https://code.highcharts.com/highcharts.js';
    echo json_encode($urls);
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
  async function ensureHC() {
    if (typeof Highcharts !== 'undefined') return true;
    for (var i = 0; i < bases.length; i++) {
      if (await loadScript(bases[i]) && typeof Highcharts !== 'undefined') return true;
    }
    return typeof Highcharts !== 'undefined';
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function chartOpts(c) {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var muted = isDark ? '#9aa3ad' : '#626976';
    var gridC = isDark ? 'rgba(231,62,17,0.25)' : 'rgba(98,105,118,0.16)';
    var seriesData = c.data;
    if (c.type === 'pie') {
      seriesData = (c.categories || []).map(function (name, i) {
        return { name: name, y: c.data[i] || 0 };
      });
    }
    return {
      chart: { type: c.type === 'pie' ? 'pie' : 'column', backgroundColor: 'transparent', style: { fontFamily: 'Source Sans 3, Segoe UI, sans-serif' }, height: 260 },
      title: { text: null },
      credits: { enabled: false },
      colors: c.colors || chartColors,
      xAxis: { categories: c.categories, labels: { style: { color: muted } }, lineColor: gridC, tickColor: gridC },
      yAxis: { labels: { style: { color: muted } }, gridLineColor: gridC, title: { text: null }, allowDecimals: false, min: 0 },
      legend: { enabled: c.type === 'pie', itemStyle: { color: muted } },
      series: [{ name: c.title, data: seriesData, colorByPoint: c.type === 'pie', color: (chartColors && chartColors[1]) || '#E73E11' }]
    };
  }

  function destroyCharts(map) {
    Object.keys(map).forEach(function (k) {
      try { map[k].destroy(); } catch (e) {}
    });
  }

  function renderCharts(list) {
    destroyCharts(chartInstances);
    chartInstances = {};
    ensureHC().then(function (ok) {
      (list || []).forEach(function (c) {
        var el = document.getElementById('ho_as_c_' + c.id);
        if (!el) return;
        if (!c.has_data) {
          el.style.display = 'none';
          return;
        }
        el.style.display = '';
        if (!ok) {
          el.innerHTML = '<p class="ho-empty">Highcharts ' + esc(failLabel) + '</p>';
          return;
        }
        chartInstances[c.id] = Highcharts.chart(el, chartOpts(c));
      });
    });
  }

  function renderAlertList(key, rows, cols) {
    var wrap = document.querySelector('[data-list="' + key + '"]');
    if (!wrap) return;
    if (!rows || !rows.length) {
      wrap.innerHTML = '<p class="ho-empty">' + esc(noData) + '</p>';
      return;
    }
    var html = '<table class="ho-assets-table"><thead><tr>';
    cols.forEach(function (c) { html += '<th>' + esc(c.label) + '</th>'; });
    html += '</tr></thead><tbody>';
    rows.forEach(function (r) {
      var itemtype = r.itemtype || 'Computer';
      var id = r.computer_id || r.items_id || r.id || 0;
      var click = id ? ' class="ho-as-row" data-itemtype="' + esc(itemtype) + '" data-id="' + esc(id) + '" tabindex="0" role="link"' : '';
      html += '<tr' + click + '>';
      cols.forEach(function (c) { html += '<td>' + esc(typeof c.get === 'function' ? c.get(r) : r[c.key]) + '</td>'; });
      html += '</tr>';
    });
    html += '</tbody></table>';
    wrap.innerHTML = html;
  }

  function applyBoard(data) {
    if (!data || !data.ok || view !== 'mural') return;
    if (data.tiles) {
      data.tiles.forEach(function (t) {
        var el = document.querySelector('[data-tile="' + t.key + '"]');
        if (!el) return;
        var v = el.querySelector('.ho-tile__value');
        var m = el.querySelector('.ho-check-list__hint');
        if (v) v.textContent = t.value;
        if (m) m.textContent = t.meta || '';
      });
    }
    if (data.kpis) {
      data.kpis.forEach(function (k) {
        var el = document.querySelector('[data-kpi="' + k.key + '"]');
        if (!el) return;
        var v = el.querySelector('.ho-kpi__value');
        var m = el.querySelector('.ho-kpi__meta');
        if (v) v.textContent = k.value;
        if (m) m.textContent = k.meta || '';
      });
    }
    charts = data.charts || charts;
    boardLists = data.lists || boardLists;
    renderCharts(charts);
    paintAlerts();
  }

  function paintAlerts() {
    var L = boardLists || {};
    (L.disks || []).forEach(function (r) { r.itemtype = 'Computer'; r.id = r.computer_id; });
    (L.ram || []).forEach(function (r) { r.itemtype = 'Computer'; });
    (L.stale || []).forEach(function (r) { r.itemtype = 'Computer'; });
    (L.warranty || []).forEach(function (r) { r.id = r.items_id; });
    (L.licenses || []).forEach(function (r) { r.itemtype = 'SoftwareLicense'; });
    renderAlertList('disks', L.disks, [
      { label: <?php echo json_encode(__('Computer', 'paineldebordo')); ?>, get: function (r) { return r.computer; } },
      { label: <?php echo json_encode(__('Partition', 'paineldebordo')); ?>, get: function (r) { return r.mount; } },
      { label: <?php echo json_encode(__('Free space', 'paineldebordo')); ?>, get: function (r) { return r.free_pct + '% · ' + r.free_label + ' / ' + r.total_label; } }
    ]);
    renderAlertList('ram', L.ram, [
      { label: <?php echo json_encode(__('Computer', 'paineldebordo')); ?>, key: 'name' },
      { label: <?php echo json_encode(__('RAM', 'paineldebordo')); ?>, key: 'ram_label' }
    ]);
    renderAlertList('stale', L.stale, [
      { label: <?php echo json_encode(__('Computer', 'paineldebordo')); ?>, key: 'name' },
      { label: <?php echo json_encode(__('Last inventory', 'paineldebordo')); ?>, get: function (r) { return r.last_inventory_update || '—'; } }
    ]);
    renderAlertList('warranty', L.warranty, [
      { label: <?php echo json_encode(__('Item', 'paineldebordo')); ?>, key: 'name' },
      { label: <?php echo json_encode(__('Type', 'paineldebordo')); ?>, get: function (r) { return r.type_label || r.itemtype; } },
      { label: <?php echo json_encode(__('Expires', 'paineldebordo')); ?>, key: 'expires_label' }
    ]);
    renderAlertList('licenses', L.licenses, [
      { label: <?php echo json_encode(__('License', 'paineldebordo')); ?>, key: 'name' },
      { label: <?php echo json_encode(__('Software', 'paineldebordo')); ?>, key: 'software' },
      { label: <?php echo json_encode(__('Expires', 'paineldebordo')); ?>, key: 'expire' }
    ]);
  }

  function setView(next) {
    view = next;
    root.setAttribute('data-view', next);
    document.getElementById('ho_as_view_mural').hidden = next !== 'mural';
    document.getElementById('ho_as_view_list').hidden = next !== 'list';
    document.getElementById('ho_as_view_item').hidden = next !== 'item';
    var crumbList = document.getElementById('ho_as_crumb_list');
    var crumbItem = document.getElementById('ho_as_crumb_item');
    var sep2 = document.getElementById('ho_as_crumb_sep2');
    crumbList.hidden = next === 'mural';
    crumbItem.hidden = next !== 'item';
    sep2.hidden = next !== 'item';
    if (next === 'mural') {
      startPoll();
    } else {
      stopPoll();
    }
    syncUrl();
  }

  function syncUrl() {
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('page', 'assets');
      if (view === 'list') {
        u.searchParams.set('view', 'list');
        u.searchParams.set('kind', listState.kind);
        u.searchParams.delete('itemtype');
        u.searchParams.delete('id');
      } else if (view === 'item') {
        u.searchParams.set('view', 'item');
        u.searchParams.set('itemtype', listState.itemtype || 'Computer');
        u.searchParams.set('id', String(listState.itemId || ''));
        if (listState.kind) u.searchParams.set('kind', listState.kind);
      } else {
        u.searchParams.delete('view');
        u.searchParams.delete('kind');
        u.searchParams.delete('itemtype');
        u.searchParams.delete('id');
      }
      history.replaceState(null, '', u.pathname + '?' + u.searchParams.toString());
    } catch (e) {}
  }

  function startPoll() {
    stopPoll();
    pollTimer = setInterval(refreshBoard, 120000);
  }
  function stopPoll() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function refreshBoard() {
    if (view !== 'mural') return;
    var url = boardEndpoint + '?ram_mb=' + encodeURIComponent(ramMb);
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(applyBoard)
      .catch(function () {});
  }

  function showList(kind, page, q) {
    listState.kind = kind;
    listState.page = page || 1;
    listState.q = (q == null ? listState.q : q) || '';
    document.getElementById('ho_as_search').value = listState.q;
    document.getElementById('ho_as_list_table').innerHTML = '<p class="ho-empty">' + esc(loading) + '</p>';
    setView('list');
    var url = listEndpoint
      + '?kind=' + encodeURIComponent(kind)
      + '&page=' + encodeURIComponent(listState.page)
      + '&limit=25'
      + '&ram_mb=' + encodeURIComponent(ramMb)
      + '&q=' + encodeURIComponent(listState.q);
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) {
          document.getElementById('ho_as_list_table').innerHTML = '<p class="ho-empty">' + esc(failLabel) + '</p>';
          return;
        }
        listState.title = data.title || kind;
        listState.columns = data.columns || [];
        listState.rows = data.rows || [];
        listState.total = data.total || 0;
        listState.pages = data.pages || 1;
        listState.page = data.page || 1;
        document.getElementById('ho_as_list_title').textContent = listState.title;
        document.getElementById('ho_as_crumb_list').textContent = listState.title;
        document.getElementById('ho_as_list_meta').textContent = resultsOf.replace('%d', String(listState.total));
        renderListTable();
        var pager = document.getElementById('ho_as_pager');
        pager.hidden = listState.pages <= 1;
        document.getElementById('ho_as_page_info').textContent = pageOf
          .replace('%1$d', String(listState.page))
          .replace('%2$d', String(listState.pages));
        document.getElementById('ho_as_prev').disabled = listState.page <= 1;
        document.getElementById('ho_as_next').disabled = listState.page >= listState.pages;
      })
      .catch(function () {
        document.getElementById('ho_as_list_table').innerHTML = '<p class="ho-empty">' + esc(failLabel) + '</p>';
      });
  }

  function renderListTable() {
    var wrap = document.getElementById('ho_as_list_table');
    var cols = listState.columns || [];
    var rows = listState.rows || [];
    if (!rows.length) {
      wrap.innerHTML = '<p class="ho-empty">' + esc(noResults) + '</p>';
      return;
    }
    var html = '<table class="ho-assets-table ho-assets-table--interactive"><thead><tr>';
    cols.forEach(function (c) { html += '<th>' + esc(c.label) + '</th>'; });
    html += '</tr></thead><tbody>';
    rows.forEach(function (r) {
      var can = r.clickable !== false && (r.id || r.items_id);
      var attrs = can
        ? ' class="ho-as-row" data-itemtype="' + esc(r.itemtype || 'Computer') + '" data-id="' + esc(r.id || r.items_id) + '" tabindex="0" role="link"'
        : '';
      html += '<tr' + attrs + '>';
      cols.forEach(function (c) {
        var val = r[c.key];
        if (val == null || val === '') val = '—';
        html += '<td>' + esc(val) + '</td>';
      });
      html += '</tr>';
    });
    html += '</tbody></table>';
    wrap.innerHTML = html;
  }

  function showItem(itemtype, id) {
    listState.itemtype = itemtype;
    listState.itemId = id;
    document.getElementById('ho_as_item_fields').innerHTML = '<p class="ho-empty">' + esc(loading) + '</p>';
    document.getElementById('ho_as_item_disks').innerHTML = '';
    document.getElementById('ho_as_item_charts').innerHTML = '';
    setView('item');
    var url = itemEndpoint
      + '?itemtype=' + encodeURIComponent(itemtype)
      + '&id=' + encodeURIComponent(id);
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) {
          document.getElementById('ho_as_item_fields').innerHTML = '<p class="ho-empty">' + esc(failLabel) + '</p>';
          return;
        }
        document.getElementById('ho_as_item_title').textContent = data.name || '';
        document.getElementById('ho_as_crumb_item').textContent = data.name || '';
        document.getElementById('ho_as_item_meta').textContent = data.as_of_label || (data.type_label || '');
        var link = document.getElementById('ho_as_glpi_link');
        if (data.glpi_url) {
          link.href = data.glpi_url;
          link.hidden = false;
        } else {
          link.hidden = true;
        }
        var fieldsHtml = '<dl class="ho-as-fields">';
        (data.fields || []).forEach(function (f) {
          fieldsHtml += '<div class="ho-as-fields__row"><dt>' + esc(f.label) + '</dt><dd>' + esc(f.value) + '</dd></div>';
        });
        fieldsHtml += '</dl>';
        document.getElementById('ho_as_item_fields').innerHTML = fieldsHtml;

        var disks = data.disks || [];
        if (!disks.length) {
          document.getElementById('ho_as_item_disks').innerHTML = '<p class="ho-empty">' + esc(noData) + '</p>';
        } else {
          var dh = '<table class="ho-assets-table"><thead><tr>'
            + '<th>' + esc(<?php echo json_encode(__('Partition', 'paineldebordo')); ?>) + '</th>'
            + '<th>' + esc(<?php echo json_encode(__('Used', 'paineldebordo')); ?>) + '</th>'
            + '<th>' + esc(<?php echo json_encode(__('Free', 'paineldebordo')); ?>) + '</th>'
            + '<th>' + esc(<?php echo json_encode(__('Total', 'paineldebordo')); ?>) + '</th>'
            + '</tr></thead><tbody>';
          disks.forEach(function (d) {
            dh += '<tr><td>' + esc(d.mount) + '</td><td>' + esc(d.used_label + ' (' + d.used_pct + '%)')
              + '</td><td>' + esc(d.free_label + ' (' + d.free_pct + '%)')
              + '</td><td>' + esc(d.total_label) + '</td></tr>';
          });
          dh += '</tbody></table>';
          document.getElementById('ho_as_item_disks').innerHTML = dh;
        }

        renderItemCharts(data.charts || []);
      })
      .catch(function () {
        document.getElementById('ho_as_item_fields').innerHTML = '<p class="ho-empty">' + esc(failLabel) + '</p>';
      });
  }

  function renderItemCharts(list) {
    destroyCharts(itemChartInstances);
    itemChartInstances = {};
    var gen = ++itemChartsGen;
    var host = document.getElementById('ho_as_item_charts');
    host.innerHTML = '';
    if (!list.length) {
      host.innerHTML = '<p class="ho-empty">' + esc(noData) + '</p>';
      return;
    }
    ensureHC().then(function (ok) {
      if (gen !== itemChartsGen) return;
      list.forEach(function (c) {
        if (gen !== itemChartsGen) return;
        var panel = document.createElement('div');
        panel.className = 'ho-panel';
        panel.innerHTML = '<div class="ho-panel__head"><h3 class="ho-panel__title"><span class="ho-tip" data-tip="' + esc(c.title) + '">' + esc(c.title) + '</span></h3></div>'
          + '<div class="ho-dash-chart" id="ho_as_ic_' + esc(c.id) + '"></div>';
        host.appendChild(panel);
        var el = document.getElementById('ho_as_ic_' + c.id);
        if (!ok) {
          el.innerHTML = '<p class="ho-empty">Highcharts ' + esc(failLabel) + '</p>';
          return;
        }
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var muted = isDark ? '#9aa3ad' : '#626976';
        var gridC = isDark ? 'rgba(231,62,17,0.25)' : 'rgba(98,105,118,0.16)';
        var maxY = 0;
        (c.series || []).forEach(function (s) {
          (s.data || []).forEach(function (v) {
            var n = typeof v === 'number' ? v : (v && v.y != null ? v.y : 0);
            if (n > maxY) maxY = n;
          });
        });
        if (c.stacked) {
          var cats = (c.categories || []).length || 1;
          var stackedMax = [];
          for (var i = 0; i < cats; i++) stackedMax[i] = 0;
          (c.series || []).forEach(function (s) {
            (s.data || []).forEach(function (v, i) {
              var n = typeof v === 'number' ? v : (v && v.y != null ? v.y : 0);
              stackedMax[i] = (stackedMax[i] || 0) + n;
            });
          });
          maxY = 0;
          stackedMax.forEach(function (n) { if (n > maxY) maxY = n; });
        }
        var series = (c.series || []).map(function (s) {
          return {
            name: s.name,
            data: s.data,
            color: s.color,
            stacking: c.stacked ? 'normal' : undefined,
            clip: true,
            dataLabels: { enabled: false }
          };
        });
        var chart = Highcharts.chart(el, {
          chart: {
            type: 'bar',
            backgroundColor: 'transparent',
            style: { fontFamily: 'Source Sans 3, Segoe UI, sans-serif' },
            height: 280,
            reflow: true
          },
          title: { text: null },
          credits: { enabled: false },
          colors: chartColors,
          xAxis: { categories: c.categories || [], labels: { style: { color: muted } } },
          yAxis: {
            min: 0,
            softMax: maxY > 0 ? maxY * 1.05 : undefined,
            endOnTick: true,
            title: { text: c.y_title || null, style: { color: muted } },
            labels: { style: { color: muted }, overflow: 'justify' },
            gridLineColor: gridC,
            stackLabels: {
              enabled: !!c.stacked,
              crop: true,
              overflow: 'allow',
              style: { color: muted, fontWeight: '600', textOutline: 'none' }
            }
          },
          legend: { itemStyle: { color: muted } },
          tooltip: { shared: true },
          plotOptions: {
            series: {
              stacking: c.stacked ? 'normal' : undefined,
              clip: true,
              dataLabels: { enabled: false }
            }
          },
          series: series
        });
        itemChartInstances[c.id] = chart;
        requestAnimationFrame(function () {
          if (gen !== itemChartsGen) return;
          try { if (chart && chart.reflow) chart.reflow(); } catch (e) {}
        });
      });
    });
  }

  function openRow(el) {
    var itemtype = el.getAttribute('data-itemtype');
    var id = parseInt(el.getAttribute('data-id'), 10);
    if (!itemtype || !id) return;
    if (itemtype === 'SoftwareLicense' || itemtype === 'Cartridge') {
      // licenses: still open detail (GLPI link); cartridges not clickable
      if (itemtype === 'Cartridge') return;
    }
    showItem(itemtype, id);
  }

  document.getElementById('ho_as_tiles').addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-kind]');
    if (t) showList(t.getAttribute('data-kind'), 1, '');
  });
  document.getElementById('ho_as_kpis').addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-kind]');
    if (t) showList(t.getAttribute('data-kind'), 1, '');
  });
  document.getElementById('ho_as_lists').addEventListener('click', function (ev) {
    var title = ev.target.closest('.ho-as-list-title');
    if (title) {
      showList(title.getAttribute('data-kind'), 1, '');
      return;
    }
    var row = ev.target.closest('.ho-as-row');
    if (row) openRow(row);
  });
  document.getElementById('ho_as_list_table').addEventListener('click', function (ev) {
    var row = ev.target.closest('.ho-as-row');
    if (row) openRow(row);
  });
  document.getElementById('ho_as_list_table').addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    var row = ev.target.closest('.ho-as-row');
    if (row) openRow(row);
  });

  document.getElementById('ho_as_back_list').addEventListener('click', function () { setView('mural'); });
  document.getElementById('ho_as_back_item').addEventListener('click', function () {
    if (listState.kind) showList(listState.kind, listState.page, listState.q);
    else setView('mural');
  });
  document.querySelector('[data-crumb="mural"]').addEventListener('click', function () { setView('mural'); });
  document.getElementById('ho_as_crumb_list').addEventListener('click', function () {
    if (listState.kind) showList(listState.kind, listState.page, listState.q);
  });

  document.getElementById('ho_as_prev').addEventListener('click', function () {
    if (listState.page > 1) showList(listState.kind, listState.page - 1, listState.q);
  });
  document.getElementById('ho_as_next').addEventListener('click', function () {
    if (listState.page < listState.pages) showList(listState.kind, listState.page + 1, listState.q);
  });
  document.getElementById('ho_as_search').addEventListener('input', function () {
    var q = this.value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { showList(listState.kind, 1, q); }, 320);
  });

  document.getElementById('ho_as_ram').addEventListener('change', function () {
    ramMb = parseInt(this.value, 10) || 8192;
    if (view === 'mural') refreshBoard();
    else if (view === 'list') showList(listState.kind, 1, listState.q);
  });

  paintAlerts();
  renderCharts(charts);
  startPoll();

  // Deep-link
  try {
    var params = new URLSearchParams(window.location.search);
    var v = params.get('view');
    var kind = params.get('kind');
    var itemtype = params.get('itemtype');
    var id = parseInt(params.get('id') || '0', 10);
    if (v === 'item' && itemtype && id) {
      if (kind) listState.kind = kind;
      showItem(itemtype, id);
    } else if (v === 'list' && kind) {
      showList(kind, 1, '');
    }
  } catch (e) {}
})();
</script>
