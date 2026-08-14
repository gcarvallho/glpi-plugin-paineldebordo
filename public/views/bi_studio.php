<?php
/**
 * BI Studio — canvas (replaces Metrics). View + edit, tabs, filters, GridStack.
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/bi.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');

global $CFG_GLPI;

$board = plugin_paineldebordo_bi_board();
$layout = $board['layout'] ?? plugin_paineldebordo_bi_layout_get();
$palette = $board['palette'] ?? plugin_paineldebordo_bi_palette();
$page = $board['page'] ?? ($layout['pages'][0] ?? null);
$plugin_web = ($GLOBALS['HO_LAYOUT']['plugin_web'] ?? '') ?: (($CFG_GLPI['root_doc'] ?? '') . '/plugins/paineldebordo/public');
$plugin_web = rtrim($plugin_web, '/');
$csrf = plugin_paineldebordo_csrf_token();
$filters = plugin_paineldebordo_getFilters();
$shell_period = $filters['period'] ?? 'month';
?>

<link rel="stylesheet" href="<?php echo htmlspecialchars($plugin_web); ?>/js/gridstack/gridstack.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack.min.css">

<div id="ho_bi_host">
<div id="ho_bi_root" class="ho-bi-root">
<header class="ho-section-head ho-dash-intro">
  <?php echo plugin_paineldebordo_icon('charts'); ?>
  <div>
    <h2 class="ho-dash-intro__title"><?php echo __('BI', 'paineldebordo'); ?></h2>
    <p class="ho-dash-intro__meta" id="ho_bi_meta"><?php echo htmlspecialchars($board['period_label'] ?? ''); ?> · <?php echo __('Interactive canvas', 'paineldebordo'); ?></p>
  </div>
  <div class="ho-dash-intro__actions">
    <button type="button" class="ho-panel__btn" id="ho_bi_view_btn" aria-pressed="true">
      <?php echo plugin_paineldebordo_icon('eye'); ?>
      <span><?php echo __('View', 'paineldebordo'); ?></span>
    </button>
    <button type="button" class="ho-panel__btn" id="ho_bi_edit_btn" aria-pressed="false">
      <?php echo plugin_paineldebordo_icon('cog'); ?>
      <span><?php echo __('Edit', 'paineldebordo'); ?></span>
    </button>
    <button type="button" class="ho-panel__btn ho-bi-edit-only" id="ho_bi_save_btn" hidden>
      <?php echo plugin_paineldebordo_icon('solved'); ?>
      <span><?php echo __('Save', 'paineldebordo'); ?></span>
    </button>
    <button type="button" class="ho-panel__btn ho-bi-edit-only" id="ho_bi_cancel_btn" hidden>
      <?php echo plugin_paineldebordo_icon('exit'); ?>
      <span><?php echo __('Cancel', 'paineldebordo'); ?></span>
    </button>
    <button type="button" class="ho-panel__btn ho-bi-edit-only" id="ho_bi_reset_btn" hidden>
      <?php echo plugin_paineldebordo_icon('refresh'); ?>
      <span><?php echo __('Reset defaults', 'paineldebordo'); ?></span>
    </button>
    <button type="button" class="ho-panel__btn" id="ho_bi_fs_btn" aria-pressed="false">
      <?php echo plugin_paineldebordo_icon('fullscreen'); ?>
      <span id="ho_bi_fs_label"><?php echo __('Fullscreen', 'paineldebordo'); ?></span>
    </button>
  </div>
</header>

<div class="ho-bi-tabs" id="ho_bi_tabs" role="tablist"></div>

<div class="ho-bi-filters" id="ho_bi_filters">
  <span class="ho-bi-filters__label"><?php echo plugin_paineldebordo_icon('calendar'); ?> <?php echo __('Page period', 'paineldebordo'); ?></span>
  <div class="ho-filters__period ho-bi-period-chips" id="ho_bi_period" role="group" aria-label="<?php echo htmlspecialchars(__('Page period', 'paineldebordo')); ?>">
    <button type="button" class="ho-chip" data-period=""><?php echo __('Same as shell', 'paineldebordo'); ?></button>
    <button type="button" class="ho-chip" data-period="today"><?php echo __('Today', 'paineldebordo'); ?></button>
    <button type="button" class="ho-chip" data-period="7d"><?php echo __('7 days', 'paineldebordo'); ?></button>
    <button type="button" class="ho-chip" data-period="month"><?php echo __('Month', 'paineldebordo'); ?></button>
    <button type="button" class="ho-chip" data-period="ytd"><?php echo __('Year', 'paineldebordo'); ?></button>
    <button type="button" class="ho-chip" data-period="all"><?php echo __('Overall', 'paineldebordo'); ?></button>
  </div>
  <button type="button" class="ho-panel__btn ho-bi-edit-only" id="ho_bi_add_page" hidden>
    <?php echo plugin_paineldebordo_icon('opened'); ?>
    <span><?php echo __('New page', 'paineldebordo'); ?></span>
  </button>
</div>

<p id="ho_bi_toast" class="ho-msg ho-msg--ok" hidden role="status" aria-live="polite"></p>

<div class="ho-ds-modal ho-bi-modal" id="ho_bi_modal" hidden>
  <div class="ho-ds-modal__backdrop ho-bi-modal__backdrop" data-bi-modal-cancel></div>
  <div class="ho-ds-modal__card ho-bi-modal__card" role="dialog" aria-modal="true" aria-labelledby="ho_bi_modal_title">
    <h3 id="ho_bi_modal_title" class="ho-ds-modal__title ho-bi-modal__title"><?php echo __('Reset defaults', 'paineldebordo'); ?></h3>
    <p class="ho-ds-modal__body ho-bi-modal__body" id="ho_bi_modal_body"></p>
    <div class="ho-ds-modal__actions ho-bi-modal__actions">
      <button type="button" class="btn btn-outline-secondary" data-bi-modal-cancel><?php echo plugin_paineldebordo_icon('ban'); ?><span><?php echo __('Cancel', 'paineldebordo'); ?></span></button>
      <button type="button" class="btn btn-primary" id="ho_bi_modal_ok"><?php echo plugin_paineldebordo_icon('refresh'); ?><span><?php echo __('Reset defaults', 'paineldebordo'); ?></span></button>
    </div>
  </div>
</div>

<div class="ho-bi-workspace" id="ho_bi_workspace">
  <aside class="ho-bi-palette ho-bi-edit-only" id="ho_bi_palette" hidden>
    <h3><?php echo plugin_paineldebordo_icon('list'); ?> <?php echo __('Widgets', 'paineldebordo'); ?></h3>
    <p class="ho-prefs__hint"><?php echo __('Click a widget to add it to the canvas.', 'paineldebordo'); ?></p>

    <div class="ho-bi-palette__acc is-open" data-acc="tickets">
      <button type="button" class="ho-bi-palette__acc-btn" aria-expanded="true">
        <span class="ho-bi-palette__acc-caret" aria-hidden="true"></span>
        <?php echo plugin_paineldebordo_icon('opened'); ?>
        <span><?php echo __('Tickets', 'paineldebordo'); ?></span>
      </button>
      <div class="ho-bi-palette__acc-panel">
        <div class="ho-bi-palette__group">
          <h4><?php echo plugin_paineldebordo_icon('kpis'); ?> <?php echo __('KPIs', 'paineldebordo'); ?></h4>
          <div id="ho_bi_pal_kpis" class="ho-bi-palette__list"></div>
        </div>
        <div class="ho-bi-palette__group">
          <h4><?php echo plugin_paineldebordo_icon('charts'); ?> <?php echo __('Charts', 'paineldebordo'); ?></h4>
          <div id="ho_bi_pal_charts" class="ho-bi-palette__list"></div>
        </div>
      </div>
    </div>

    <div class="ho-bi-palette__acc" data-acc="assets">
      <button type="button" class="ho-bi-palette__acc-btn" aria-expanded="false">
        <span class="ho-bi-palette__acc-caret" aria-hidden="true"></span>
        <?php echo plugin_paineldebordo_icon('computer'); ?>
        <span><?php echo __('Assets', 'paineldebordo'); ?></span>
      </button>
      <div class="ho-bi-palette__acc-panel" hidden>
        <div class="ho-bi-palette__group">
          <h4><?php echo plugin_paineldebordo_icon('kpis'); ?> <?php echo __('Indicators', 'paineldebordo'); ?></h4>
          <div id="ho_bi_pal_assets_kpis" class="ho-bi-palette__list"></div>
        </div>
        <div class="ho-bi-palette__group">
          <h4><?php echo plugin_paineldebordo_icon('charts'); ?> <?php echo __('Charts', 'paineldebordo'); ?></h4>
          <div id="ho_bi_pal_assets_charts" class="ho-bi-palette__list"></div>
        </div>
        <div class="ho-bi-palette__group">
          <h4><?php echo plugin_paineldebordo_icon('bell'); ?> <?php echo __('Alert lists', 'paineldebordo'); ?></h4>
          <div id="ho_bi_pal_assets_lists" class="ho-bi-palette__list"></div>
        </div>
      </div>
    </div>

    <div class="ho-bi-palette__group ho-bi-palette__group--text">
      <h4><?php echo plugin_paineldebordo_icon('title'); ?> <?php echo __('Text', 'paineldebordo'); ?></h4>
      <button type="button" class="ho-bi-palette__item" data-add-type="text" data-add-ref="<?php echo htmlspecialchars(__('Title', 'paineldebordo')); ?>">
        <?php echo plugin_paineldebordo_icon('title'); ?>
        <span><?php echo __('Title', 'paineldebordo'); ?></span>
      </button>
    </div>
  </aside>
  <div class="ho-bi-canvas-wrap">
    <div class="grid-stack ho-bi-canvas" id="ho_bi_canvas"></div>
    <p class="ho-empty" id="ho_bi_empty" hidden><?php echo __('Empty page. Switch to Edit and add widgets.', 'paineldebordo'); ?></p>
  </div>
</div>
</div>
</div>

<script>
(function () {
  var pluginWeb = <?php echo json_encode($plugin_web); ?>;
  var boardEndpoint = pluginWeb + '/ajax/bi_board.php';
  var layoutEndpoint = pluginWeb + '/ajax/bi_layout.php';
  var csrf = <?php echo json_encode($csrf); ?>;
  var layout = <?php echo json_encode($layout, JSON_UNESCAPED_UNICODE); ?>;
  var palette = <?php echo json_encode($palette, JSON_UNESCAPED_UNICODE); ?>;
  var widgets = <?php echo json_encode($board['widgets'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
  var shellPeriod = <?php echo json_encode($shell_period); ?>;
  var ticketLabel = <?php echo json_encode(__('Tickets', 'paineldebordo')); ?>;
  var failLabel = <?php echo json_encode(__('failed to load', 'paineldebordo')); ?>;
  var chartColors = <?php echo json_encode(function_exists('plugin_paineldebordo_chart_colors') ? plugin_paineldebordo_chart_colors() : ['#09141F', '#E73E11']); ?>;
  var i18n = {
    page: <?php echo json_encode(__('Page', 'paineldebordo')); ?>,
    remove: <?php echo json_encode(__('Remove', 'paineldebordo')); ?>,
    rename: <?php echo json_encode(__('Rename page', 'paineldebordo')); ?>,
    renameConfirm: <?php echo json_encode(__('Apply name', 'paineldebordo')); ?>,
    renameDone: <?php echo json_encode(__('Tab name updated — click Save to keep it.', 'paineldebordo')); ?>,
    fsEnter: <?php echo json_encode(__('Fullscreen', 'paineldebordo')); ?>,
    fsExit: <?php echo json_encode(__('Exit fullscreen', 'paineldebordo')); ?>,
    resetConfirm: <?php echo json_encode(__('Restore the Operation page to the factory default layout? Other unsaved edits will be lost.', 'paineldebordo')); ?>,
    saved: <?php echo json_encode(__('Layout saved', 'paineldebordo')); ?>,
    saveFailed: <?php echo json_encode(__('Could not save the layout. Try again.', 'paineldebordo')); ?>,
    resetDone: <?php echo json_encode(__('Defaults restored', 'paineldebordo')); ?>,
    pageAdded: <?php echo json_encode(__('Page added — click Save to keep it.', 'paineldebordo')); ?>,
    loadFailed: <?php echo json_encode(__('Could not load page data. Try again.', 'paineldebordo')); ?>,
    unsaved: <?php echo json_encode(__('You have unsaved BI changes.', 'paineldebordo')); ?>,
    leaveTitle: <?php echo json_encode(__('Unsaved changes', 'paineldebordo')); ?>,
    leaveBody: <?php echo json_encode(__('You have unsaved BI changes. Discard them and continue?', 'paineldebordo')); ?>,
    leaveOk: <?php echo json_encode(__('Discard', 'paineldebordo')); ?>,
    resetTitle: <?php echo json_encode(__('Reset defaults', 'paineldebordo')); ?>,
    resetOk: <?php echo json_encode(__('Reset defaults', 'paineldebordo')); ?>,
    noData: <?php echo json_encode(__('No data', 'paineldebordo')); ?>
  };

  var editing = false;
  var dirty = false;
  var kiosk = false;
  var grid = null;
  var chartInstances = {};
  var uid = 1;
  var DRAFT_KEY = 'pdb_bi_draft_v1';
  var rootEl = document.getElementById('ho_bi_root');
  var hostEl = document.getElementById('ho_bi_host');
  var fsBtn = document.getElementById('ho_bi_fs_btn');
  var fsLabel = document.getElementById('ho_bi_fs_label');
  var toastEl = document.getElementById('ho_bi_toast');
  var toastTimer = null;

  var bases = <?php
    $bases = function_exists('plugin_paineldebordo_asset_bases') ? plugin_paineldebordo_asset_bases() : [$plugin_web];
    $urls = [];
    foreach ($bases as $b) {
      if ($b) $urls[] = rtrim($b, '/') . '/js/highcharts.js';
    }
    $urls[] = $plugin_web . '/js/highcharts.js';
    $urls[] = 'https://code.highcharts.com/6.2.0/highcharts.js';
    echo json_encode(array_values(array_unique($urls)));
  ?>;

  var gsBases = <?php
    $gs = [];
    $bases = function_exists('plugin_paineldebordo_asset_bases') ? plugin_paineldebordo_asset_bases() : [$plugin_web];
    foreach ($bases as $b) {
      if ($b) {
        $gs[] = rtrim($b, '/') . '/js/gridstack/gridstack-all.js';
      }
    }
    $gs[] = $plugin_web . '/js/gridstack/gridstack-all.js';
    $gs[] = 'https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-all.js';
    $gs[] = 'https://unpkg.com/gridstack@10.3.1/dist/gridstack-all.js';
    echo json_encode(array_values(array_unique($gs)));
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
  async function ensureGridStack() {
    if (typeof GridStack !== 'undefined') return true;
    for (var i = 0; i < gsBases.length; i++) {
      if (await loadScript(gsBases[i]) && typeof GridStack !== 'undefined') return true;
    }
    return typeof GridStack !== 'undefined';
  }

  function showToast(text, ok) {
    if (!toastEl) return;
    toastEl.hidden = false;
    toastEl.className = 'ho-msg ' + (ok ? 'ho-msg--ok' : 'ho-msg--err');
    toastEl.textContent = text || '';
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.hidden = true; }, 4200);
  }

  function markDirty() {
    dirty = true;
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(layout)); } catch (e) {}
  }

  function clearDraft() {
    dirty = false;
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
  }

  function widgetLabel(w) {
    var p = w.payload;
    if (w.type === 'kpi') {
      return (p && p.label)
        || (palette.assets_kpis && palette.assets_kpis[w.ref])
        || (palette.tickets_kpis && palette.tickets_kpis[w.ref])
        || (palette.kpis && palette.kpis[w.ref])
        || w.ref;
    }
    if (w.type === 'chart') {
      return (p && p.title)
        || (palette.assets_charts && palette.assets_charts[w.ref])
        || (palette.tickets_charts && palette.tickets_charts[w.ref])
        || (palette.charts && palette.charts[w.ref])
        || w.ref;
    }
    if (w.type === 'list') {
      return (p && p.title)
        || (palette.assets_lists && palette.assets_lists[w.ref])
        || w.ref;
    }
    if (w.type === 'text') {
      return (p && p.text) || w.ref;
    }
    return w.ref;
  }

  function activePage() {
    var id = layout.active_page;
    for (var i = 0; i < layout.pages.length; i++) {
      if (layout.pages[i].id === id) return layout.pages[i];
    }
    return layout.pages[0];
  }

  function syncPeriodSelect() {
    var p = activePage();
    var cur = (p.filters && p.filters.period) ? p.filters.period : '';
    document.querySelectorAll('#ho_bi_period .ho-chip').forEach(function (chip) {
      var v = chip.getAttribute('data-period') || '';
      chip.classList.toggle('is-active', v === cur);
    });
  }

  /** Merge page layout geometry with hydrated payloads (keeps draft widgets). */
  function syncWidgetsFromPage() {
    var page = activePage();
    var byId = {};
    (widgets || []).forEach(function (w) { byId[w.id] = w; });
    widgets = (page.widgets || []).map(function (w) {
      var prev = byId[w.id];
      var payload = null;
      if (prev && prev.ref === w.ref && prev.type === w.type) {
        payload = prev.payload;
      } else if (w.type === 'text') {
        payload = { text: w.ref };
      }
      return {
        id: w.id, type: w.type, ref: w.ref,
        x: w.x, y: w.y, w: w.w, h: w.h,
        payload: payload
      };
    });
  }

  function applyGridInteract() {
    if (!grid) return;
    try {
      if (typeof grid.setStatic === 'function') {
        grid.setStatic(!editing);
      }
      if (typeof grid.enableMove === 'function') {
        grid.enableMove(!!editing);
      }
      if (typeof grid.enableResize === 'function') {
        grid.enableResize(!!editing);
      }
    } catch (e) {}
  }

  function renderTabs() {
    var root = document.getElementById('ho_bi_tabs');
    root.innerHTML = '';
    layout.pages.forEach(function (p) {
      var wrap = document.createElement('div');
      wrap.className = 'ho-bi-tab-wrap';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ho-bi-tab' + (p.id === layout.active_page ? ' is-active' : '');
      btn.textContent = p.title;
      btn.addEventListener('click', function () {
        if (p.id === layout.active_page) return;
        collectGridIntoLayout();
        layout.active_page = p.id;
        markDirty();
        renderTabs();
        syncPeriodSelect();
        // Do NOT buildGrid from client widgets here: payloads are empty until
        // refreshBoard hydrates. A failed POST used to leave Operação as "—".
        refreshBoard({ skipCollect: true });
      });
      wrap.appendChild(btn);
      if (editing) {
        var ren = document.createElement('button');
        ren.type = 'button';
        ren.className = 'ho-panel__btn ho-bi-tab-rename';
        ren.title = i18n.rename;
        ren.setAttribute('aria-label', i18n.rename);
        ren.textContent = '✎';
        ren.addEventListener('click', function (ev) {
          ev.stopPropagation();
          if (wrap.querySelector('.ho-bi-tab-input')) return;
          var input = document.createElement('input');
          input.type = 'text';
          input.className = 'ho-select ho-bi-tab-input';
          input.value = p.title;
          input.setAttribute('aria-label', i18n.rename);
          var okBtn = document.createElement('button');
          okBtn.type = 'button';
          okBtn.className = 'ho-panel__btn ho-bi-tab-rename-ok';
          okBtn.title = i18n.renameConfirm;
          okBtn.setAttribute('aria-label', i18n.renameConfirm);
          okBtn.textContent = '✓';
          var committed = false;
          function commit() {
            if (committed) return;
            committed = true;
            var t = (input.value || '').trim();
            if (t && t !== p.title) {
              p.title = t;
              markDirty();
              showToast(i18n.renameDone, true);
            }
            renderTabs();
          }
          function cancel() {
            if (committed) return;
            committed = true;
            renderTabs();
          }
          wrap.replaceChild(input, btn);
          wrap.insertBefore(okBtn, ren);
          ren.hidden = true;
          input.focus();
          input.select();
          okBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            commit();
          });
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); commit(); }
            if (e.key === 'Escape') { e.preventDefault(); cancel(); }
          });
          // Blur still applies the name (no data loss) but OK button makes it explicit
          input.addEventListener('blur', function () {
            setTimeout(function () {
              if (document.activeElement === okBtn) return;
              commit();
            }, 120);
          });
        });
        wrap.appendChild(ren);
      }
      root.appendChild(wrap);
    });
  }

  function chartOpts(c) {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var muted = isDark ? '#9aa3ad' : '#626976';
    var gridC = isDark ? 'rgba(231,62,17,0.25)' : 'rgba(98,105,118,0.16)';
    var seriesData = c.data;
    var chartType = c.type === 'bar' ? 'column' : c.type;
    if (c.type === 'pie') {
      seriesData = (c.categories || []).map(function (name, i) {
        return { name: name, y: c.data[i] || 0 };
      });
    }
    return {
      chart: { type: chartType, backgroundColor: 'transparent', style: { fontFamily: 'Source Sans 3, Segoe UI, sans-serif' }, height: null },
      title: { text: null },
      credits: { enabled: false },
      colors: c.colors || chartColors,
      xAxis: { categories: c.categories, labels: { style: { color: muted } }, lineColor: gridC, tickColor: gridC },
      yAxis: { labels: { style: { color: muted } }, gridLineColor: gridC, title: { text: null }, allowDecimals: false, min: 0 },
      legend: { enabled: c.type === 'pie', itemStyle: { color: muted } },
      series: [{ name: (c.title || ticketLabel), data: seriesData, colorByPoint: c.type !== 'areaspline', color: (chartColors && chartColors[1]) || '#E73E11' }]
    };
  }

  function widgetHtml(w) {
    var head = '<div class="ho-bi-widget__head"><span class="ho-bi-widget__title"></span>';
    if (editing) {
      head += '<button type="button" class="ho-panel__btn ho-bi-widget__rm ho-tip" data-rm="' + w.id + '" data-tip="' + i18n.remove + '">×</button>';
    }
    head += '</div><div class="ho-bi-widget__body" id="ho_bi_w_' + w.id + '"></div>';
    return head;
  }

  function fillWidgetContent(el, w) {
    var title = el.querySelector('.ho-bi-widget__title');
    var body = el.querySelector('.ho-bi-widget__body');
    var p = w.payload;
    function setTitle(text) {
      if (!title) return;
      title.textContent = text || '';
      if (text) title.setAttribute('title', text);
      else title.removeAttribute('title');
    }
    if (w.type === 'kpi') {
      setTitle(widgetLabel(w));
      body.innerHTML = '<p class="ho-bi-kpi__value">' + (p ? p.value : '—') + '</p>' +
        '<p class="ho-bi-kpi__meta">' + (p && p.meta ? p.meta : '') + '</p>';
      if (p && p.href && String(w.ref || '').indexOf('assets.') === 0) {
        el.classList.add('ho-bi-widget--link');
        el.addEventListener('click', function (ev) {
          if (ev.target.closest('[data-rm]')) return;
          window.location.href = p.href;
        });
      }
    } else if (w.type === 'text') {
      setTitle('');
      body.innerHTML = '<p class="ho-bi-text">' + widgetLabel(w) + '</p>';
    } else if (w.type === 'list') {
      setTitle(widgetLabel(w));
      body.innerHTML = '';
      var wrap = document.createElement('div');
      wrap.className = 'ho-bi-list ho-assets-table-wrap';
      if (!p || !p.rows || !p.rows.length) {
        wrap.innerHTML = '<p class="ho-empty">' + i18n.noData + '</p>';
      } else {
        var table = document.createElement('table');
        table.className = 'ho-assets-table ho-assets-table--interactive';
        var thead = document.createElement('thead');
        var hr = document.createElement('tr');
        (p.columns || []).forEach(function (col) {
          var th = document.createElement('th');
          th.textContent = col;
          hr.appendChild(th);
        });
        thead.appendChild(hr);
        table.appendChild(thead);
        var tbody = document.createElement('tbody');
        p.rows.forEach(function (row) {
          var tr = document.createElement('tr');
          tr.className = 'ho-as-row';
          if (row.href) {
            tr.tabIndex = 0;
            tr.addEventListener('click', function () { window.location.href = row.href; });
            tr.addEventListener('keydown', function (e) {
              if (e.key === 'Enter') window.location.href = row.href;
            });
          }
          (row.cells || []).forEach(function (cell) {
            var td = document.createElement('td');
            td.textContent = cell;
            tr.appendChild(td);
          });
          tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
      }
      body.appendChild(wrap);
    } else if (w.type === 'chart') {
      setTitle(widgetLabel(w));
      body.innerHTML = '';
      var mount = document.createElement('div');
      mount.className = 'ho-bi-chart';
      mount.id = 'ho_bi_c_' + w.id;
      body.appendChild(mount);
      if (p && p.has_data) {
        ensureHC().then(function (ok) {
          if (!ok) { mount.innerHTML = '<p class="ho-empty">Highcharts ' + failLabel + '</p>'; return; }
          if (chartInstances[w.id]) try { chartInstances[w.id].destroy(); } catch (e) {}
          chartInstances[w.id] = Highcharts.chart(mount.id, chartOpts(p));
        });
      } else {
        mount.innerHTML = '<p class="ho-empty">' + i18n.noData + '</p>';
      }
    }
  }

  function destroyCharts() {
    Object.keys(chartInstances).forEach(function (k) {
      try { chartInstances[k].destroy(); } catch (e) {}
    });
    chartInstances = {};
  }

  function collectGridIntoLayout() {
    if (!grid) return;
    var page = activePage();
    if (!page) return;
    var items = [];
    try {
      items = grid.getGridItems ? grid.getGridItems() : [];
    } catch (e) {
      items = [];
    }
    // Never wipe a page full of widgets while the grid is mid-rebuild
    if ((!items || items.length === 0) && (page.widgets || []).length > 0) {
      return;
    }
    var next = [];
    items.forEach(function (el) {
      var n = el.gridstackNode;
      if (!n) return;
      var type = el.getAttribute('data-type');
      var ref = el.getAttribute('data-ref');
      if (!type) return;
      next.push({
        id: el.getAttribute('gs-id') || n.id,
        type: type,
        ref: ref,
        x: n.x, y: n.y, w: n.w, h: n.h
      });
    });
    page.widgets = next;
  }

  function buildGrid(list) {
    destroyCharts();
    var canvas = document.getElementById('ho_bi_canvas');
    canvas.innerHTML = '';
    document.getElementById('ho_bi_empty').hidden = list.length > 0;

    if (grid) {
      try { grid.destroy(false); } catch (e) {}
      grid = null;
    }

    list.forEach(function (w) {
      var el = document.createElement('div');
      el.className = 'grid-stack-item';
      el.setAttribute('gs-id', w.id);
      el.setAttribute('gs-x', w.x);
      el.setAttribute('gs-y', w.y);
      el.setAttribute('gs-w', w.w);
      el.setAttribute('gs-h', w.h);
      el.setAttribute('data-type', w.type);
      el.setAttribute('data-ref', w.ref);
      var content = document.createElement('div');
      content.className = 'grid-stack-item-content ho-bi-widget';
      content.innerHTML = widgetHtml(w);
      el.appendChild(content);
      canvas.appendChild(el);
      fillWidgetContent(content, w);
    });

    if (typeof GridStack === 'undefined') {
      document.getElementById('ho_bi_empty').hidden = false;
      document.getElementById('ho_bi_empty').textContent = 'GridStack ' + failLabel;
      return;
    }

    grid = GridStack.init({
      column: 12,
      cellHeight: 72,
      margin: 8,
      float: true,
      staticGrid: !editing,
      disableResize: !editing,
      disableDrag: !editing,
      animate: true,
      draggable: { handle: '.ho-bi-widget__head', appendTo: 'body', scroll: true },
      resizable: { handles: 'e, se, s, sw, w' }
    }, canvas);
    applyGridInteract();
    if (typeof grid.on === 'function') {
      grid.on('change', function () {
        if (editing) markDirty();
      });
    }

    canvas.querySelectorAll('[data-rm]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-rm');
        var item = canvas.querySelector('.grid-stack-item[gs-id="' + id + '"]');
        if (item && grid) {
          grid.removeWidget(item);
          collectGridIntoLayout();
          widgets = widgets.filter(function (w) { return w.id !== id; });
          document.getElementById('ho_bi_empty').hidden = widgets.length > 0;
          markDirty();
        }
      });
    });
  }

  function applyChrome() {
    rootEl.classList.toggle('is-editing-ui', editing);
    document.getElementById('ho_bi_palette').hidden = !editing;
    document.getElementById('ho_bi_save_btn').hidden = !editing;
    document.getElementById('ho_bi_cancel_btn').hidden = !editing;
    document.getElementById('ho_bi_reset_btn').hidden = !editing;
    document.getElementById('ho_bi_add_page').hidden = !editing;
    document.getElementById('ho_bi_edit_btn').setAttribute('aria-pressed', editing ? 'true' : 'false');
    document.getElementById('ho_bi_view_btn').setAttribute('aria-pressed', editing ? 'false' : 'true');
    document.getElementById('ho_bi_workspace').classList.toggle('is-editing', editing);
  }

  /** Place new widgets just below the current content (not far off-screen). */
  function nextPlacement(type) {
    var w = type === 'kpi' ? 2 : (type === 'list' ? 6 : 4);
    var h = type === 'kpi' ? 2 : (type === 'text' ? 1 : (type === 'list' ? 5 : 4));
    var page = activePage();
    var maxBottom = 0;
    (page.widgets || []).forEach(function (item) {
      var bottom = (parseInt(item.y, 10) || 0) + (parseInt(item.h, 10) || 1);
      if (bottom > maxBottom) maxBottom = bottom;
    });
    return { x: 0, y: maxBottom, w: w, h: h };
  }

  function refreshBoard(opts) {
    opts = opts || {};
    if (!opts.skipCollect) {
      collectGridIntoLayout();
    }
    var pageId = layout.active_page;
    var body = new FormData();
    body.append('page_id', pageId);
    body.append('layout', JSON.stringify(layout));
    if (csrf) body.append('_glpi_csrf_token', csrf);
    // XHR + CSRF header → GLPI preserves token (same as Save); bare POST was 403 → empty KPIs
    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    if (csrf) headers['X-Glpi-Csrf-Token'] = csrf;
    return fetch(boardEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
      cache: 'no-store',
      headers: headers
    })
      .then(function (r) {
        return r.text().then(function (text) {
          try { return JSON.parse(text); } catch (e) {
            return { ok: false, error: 'json', detail: String(text).slice(0, 160) };
          }
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          showToast(i18n.loadFailed, false);
          return;
        }
        // Keep client layout intact — only hydrate widget payloads
        widgets = data.widgets || [];
        if (data.period_label) {
          document.getElementById('ho_bi_meta').textContent = data.period_label + ' · <?php echo htmlspecialchars(__('Interactive canvas', 'paineldebordo'), ENT_QUOTES); ?>';
        }
        if (data.palette) { palette = data.palette; buildPalette(); }
        renderTabs();
        syncPeriodSelect();
        buildGrid(widgets);
        applyGridInteract();
      }).catch(function () {
        showToast(i18n.loadFailed, false);
      });
  }

  function loadFromServer(pageId) {
    var url = boardEndpoint + (pageId ? ('?page_id=' + encodeURIComponent(pageId)) : '');
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;
        layout = data.layout;
        widgets = data.widgets || [];
        if (data.palette) { palette = data.palette; buildPalette(); }
        if (data.period_label) {
          document.getElementById('ho_bi_meta').textContent = data.period_label + ' · <?php echo htmlspecialchars(__('Interactive canvas', 'paineldebordo'), ENT_QUOTES); ?>';
        }
        renderTabs();
        syncPeriodSelect();
        buildGrid(widgets);
      }).catch(function () {});
  }

  function setEditMode(on, opts) {
    opts = opts || {};
    editing = !!on;
    applyChrome();
    collectGridIntoLayout();
    syncWidgetsFromPage();
    renderTabs();
    // Rebuild immediately so drag/resize/remove match mode (don't wait for AJAX)
    buildGrid(widgets);
    applyGridInteract();
    if (!opts.skipRefresh) {
      refreshBoard({ skipCollect: true });
    }
  }

  function buildPalette() {
    function fill(rootId, map, type) {
      var root = document.getElementById(rootId);
      if (!root) return;
      root.innerHTML = '';
      Object.keys(map || {}).forEach(function (ref) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ho-bi-palette__item';
        btn.setAttribute('data-add-type', type);
        btn.setAttribute('data-add-ref', ref);
        btn.innerHTML = '<span>' + map[ref] + '</span>';
        root.appendChild(btn);
      });
    }
    var ticketKpis = palette.tickets_kpis || palette.kpis || {};
    var ticketCharts = palette.tickets_charts || palette.charts || {};
    fill('ho_bi_pal_kpis', ticketKpis, 'kpi');
    fill('ho_bi_pal_charts', ticketCharts, 'chart');
    fill('ho_bi_pal_assets_kpis', palette.assets_kpis || {}, 'kpi');
    fill('ho_bi_pal_assets_charts', palette.assets_charts || {}, 'chart');
    fill('ho_bi_pal_assets_lists', palette.assets_lists || {}, 'list');
  }

  document.getElementById('ho_bi_palette').addEventListener('click', function (ev) {
    var accBtn = ev.target.closest('.ho-bi-palette__acc-btn');
    if (accBtn) {
      var acc = accBtn.closest('.ho-bi-palette__acc');
      if (!acc) return;
      var open = !acc.classList.contains('is-open');
      acc.classList.toggle('is-open', open);
      accBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      var panel = acc.querySelector('.ho-bi-palette__acc-panel');
      if (panel) panel.hidden = !open;
      return;
    }
  });

  function addWidget(type, ref) {
    collectGridIntoLayout();
    var page = activePage();
    var id = 'w' + Date.now().toString(36) + (uid++);
    var place = nextPlacement(type);
    var w = {
      id: id,
      type: type,
      ref: ref,
      x: place.x,
      y: place.y,
      w: place.w,
      h: place.h
    };
    page.widgets.push(w);
    syncWidgetsFromPage();
    // Paint immediately so the card is visible below the current layout
    buildGrid(widgets);
    applyGridInteract();
    try {
      var workspace = document.getElementById('ho_bi_workspace');
      if (workspace) workspace.scrollTop = workspace.scrollHeight;
    } catch (e) {}
    // skipCollect: otherwise refreshBoard() would rebuild widgets from the old grid and drop this add
    refreshBoard({ skipCollect: true });
  }

  function cancelEdits() {
    function doCancel() {
      editing = false;
      clearDraft();
      applyChrome();
      loadFromServer(layout.active_page);
    }
    if (dirty) {
      openBiModal({
        title: i18n.leaveTitle,
        body: i18n.leaveBody,
        okLabel: i18n.leaveOk,
        onOk: doCancel
      });
      return;
    }
    doCancel();
  }

  function resetToDefaults() {
    openBiModal({
      title: i18n.resetTitle,
      body: i18n.resetConfirm,
      okLabel: i18n.resetOk,
      onOk: function () {
        var btn = document.getElementById('ho_bi_reset_btn');
        if (btn) btn.disabled = true;
        postLayout('reset').then(function (data) {
          if (btn) btn.disabled = false;
          if (data && data.ok) {
            layout = data.layout;
            editing = false;
            clearDraft();
            applyChrome();
            showToast(i18n.resetDone, true);
            loadFromServer(layout.active_page);
          } else {
            showToast(i18n.saveFailed, false);
          }
        }).catch(function () {
          if (btn) btn.disabled = false;
          showToast(i18n.saveFailed, false);
        });
      }
    });
  }

  function openBiModal(opts) {
    opts = opts || {};
    var modal = document.getElementById('ho_bi_modal');
    var titleEl = document.getElementById('ho_bi_modal_title');
    var body = document.getElementById('ho_bi_modal_body');
    var ok = document.getElementById('ho_bi_modal_ok');
    if (!modal || !body || !ok) {
      if (window.confirm(opts.body || i18n.resetConfirm)) {
        if (typeof opts.onOk === 'function') opts.onOk();
      }
      return;
    }
    if (titleEl) titleEl.textContent = opts.title || i18n.resetTitle;
    body.textContent = opts.body || '';
    ok.textContent = opts.okLabel || i18n.resetOk;
    modal.hidden = false;
    function close() {
      modal.hidden = true;
      ok.onclick = null;
      modal.querySelectorAll('[data-bi-modal-cancel]').forEach(function (el) {
        el.onclick = null;
      });
    }
    ok.onclick = function () {
      close();
      if (typeof opts.onOk === 'function') opts.onOk();
    };
    modal.querySelectorAll('[data-bi-modal-cancel]').forEach(function (el) {
      el.onclick = function () { close(); };
    });
  }

  function openResetModal(onOk) {
    openBiModal({
      title: i18n.resetTitle,
      body: i18n.resetConfirm,
      okLabel: i18n.resetOk,
      onOk: onOk
    });
  }

  function confirmLeaveIfDirty(onProceed) {
    if (!dirty) {
      onProceed();
      return;
    }
    openBiModal({
      title: i18n.leaveTitle,
      body: i18n.leaveBody,
      okLabel: i18n.leaveOk,
      onOk: function () {
        clearDraft();
        onProceed();
      }
    });
  }

  function postLayout(action) {
    collectGridIntoLayout();
    var body = new FormData();
    body.append('action', action || 'save');
    body.append('layout', JSON.stringify(layout));
    if (csrf) body.append('_glpi_csrf_token', csrf);
    // X-Requested-With → GLPI preserve_token (non-XHR fetch consumes CSRF after first save)
    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    if (csrf) headers['X-Glpi-Csrf-Token'] = csrf;
    return fetch(layoutEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
      cache: 'no-store',
      headers: headers
    }).then(function (r) {
      return r.text().then(function (text) {
        try { return JSON.parse(text); } catch (e) { return { ok: false, error: 'json', detail: text.slice(0, 200) }; }
      });
    });
  }

  document.getElementById('ho_bi_view_btn').addEventListener('click', function () {
    confirmLeaveIfDirty(function () { setEditMode(false); });
  });
  document.getElementById('ho_bi_edit_btn').addEventListener('click', function () { setEditMode(true); });
  document.getElementById('ho_bi_save_btn').addEventListener('click', function () {
    var btn = document.getElementById('ho_bi_save_btn');
    btn.disabled = true;
    postLayout('save').then(function (data) {
      btn.disabled = false;
      if (data && data.ok) {
        layout = data.layout;
        editing = false;
        clearDraft();
        applyChrome();
        showToast(i18n.saved, true);
        loadFromServer(layout.active_page);
      } else {
        showToast(i18n.saveFailed, false);
      }
    }).catch(function () {
      btn.disabled = false;
      showToast(i18n.saveFailed, false);
    });
  });
  document.getElementById('ho_bi_cancel_btn').addEventListener('click', cancelEdits);
  document.getElementById('ho_bi_reset_btn').addEventListener('click', resetToDefaults);
  document.getElementById('ho_bi_add_page').addEventListener('click', function () {
    collectGridIntoLayout();
    var id = 'p' + Date.now().toString(36);
    layout.pages.push({
      id: id,
      title: i18n.page + ' ' + (layout.pages.length + 1),
      filters: { period: null },
      widgets: []
    });
    layout.active_page = id;
    widgets = [];
    markDirty();
    renderTabs();
    syncPeriodSelect();
    buildGrid([]);
    showToast(i18n.pageAdded, true);
  });
  document.getElementById('ho_bi_period').addEventListener('click', function (ev) {
    var chip = ev.target.closest('.ho-chip');
    if (!chip) return;
    var p = activePage();
    p.filters = p.filters || {};
    p.filters.period = chip.getAttribute('data-period') || null;
    if (p.filters.period === '') p.filters.period = null;
    markDirty();
    syncPeriodSelect();
    refreshBoard({ skipCollect: true });
  });
  document.getElementById('ho_bi_palette').addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-add-type]');
    if (!t) return;
    addWidget(t.getAttribute('data-add-type'), t.getAttribute('data-add-ref'));
    markDirty();
  });

  function reflowBoard() {
    if (grid && typeof grid.on === 'function') {
      try { grid.cellHeight(kiosk ? 96 : 72); } catch (e) {}
    }
    Object.keys(chartInstances).forEach(function (k) {
      try { chartInstances[k].reflow(); } catch (e) {}
    });
    window.dispatchEvent(new Event('resize'));
  }

  function syncFsChrome() {
    rootEl.classList.toggle('is-kiosk', kiosk);
    document.body.classList.toggle('ho-bi-kiosk-open', kiosk);
    fsBtn.setAttribute('aria-pressed', kiosk ? 'true' : 'false');
    fsLabel.textContent = kiosk ? i18n.fsExit : i18n.fsEnter;
    var icon = fsBtn.querySelector('svg');
    if (icon) {
      // keep fullscreen icon; label carries meaning
    }
  }

  function enterKiosk() {
    if (kiosk) return;
    kiosk = true;
    if (rootEl.parentNode !== document.body) {
      document.body.appendChild(rootEl);
    }
    syncFsChrome();
    var req = rootEl.requestFullscreen || rootEl.webkitRequestFullscreen;
    if (req) {
      try {
        var p = req.call(rootEl);
        if (p && typeof p.catch === 'function') p.catch(function () {});
      } catch (e) {}
    }
    setTimeout(reflowBoard, 80);
  }

  function exitKiosk() {
    if (!kiosk) return;
    kiosk = false;
    if (document.fullscreenElement) {
      var ex = document.exitFullscreen || document.webkitExitFullscreen;
      if (ex) try { ex.call(document); } catch (e) {}
    }
    if (hostEl && rootEl.parentNode !== hostEl) {
      hostEl.appendChild(rootEl);
    }
    syncFsChrome();
    setTimeout(reflowBoard, 80);
  }

  function toggleKiosk() {
    if (kiosk) exitKiosk();
    else enterKiosk();
  }

  fsBtn.addEventListener('click', toggleKiosk);
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && kiosk && !document.fullscreenElement) exitKiosk();
  });
  document.addEventListener('fullscreenchange', function () {
    if (!document.fullscreenElement && kiosk) exitKiosk();
  });
  window.addEventListener('resize', function () {
    if (kiosk) reflowBoard();
  });

  buildPalette();
  try {
    var draftRaw = localStorage.getItem(DRAFT_KEY);
    if (draftRaw) {
      var draft = JSON.parse(draftRaw);
      if (draft && Array.isArray(draft.pages) && draft.pages.length) {
        layout = draft;
        dirty = true;
        var ap = activePage();
        widgets = (ap && ap.widgets) ? ap.widgets.slice() : [];
        showToast(i18n.unsaved, true);
      }
    }
  } catch (e) {}
  renderTabs();
  syncPeriodSelect();
  applyChrome();

  window.addEventListener('beforeunload', function (ev) {
    if (!dirty) return;
    ev.preventDefault();
    ev.returnValue = i18n.unsaved;
  });

  document.addEventListener('click', function (ev) {
    if (!dirty) return;
    var a = ev.target.closest('a');
    if (!a) return;
    if (!a.closest('.ho-nav') && !a.closest('.ho-topnav') && !a.closest('.ho-sider')) return;
    var href = a.getAttribute('href');
    if (!href || href === '#' || href.indexOf('javascript:') === 0) return;
    ev.preventDefault();
    confirmLeaveIfDirty(function () {
      window.location.href = href;
    });
  }, true);

  ensureGridStack().then(function (ok) {
    if (!ok) {
      var empty = document.getElementById('ho_bi_empty');
      empty.hidden = false;
      empty.textContent = 'GridStack ' + failLabel;
      return;
    }
    if (dirty) {
      syncWidgetsFromPage();
      buildGrid(widgets);
      refreshBoard({ skipCollect: true });
    } else {
      buildGrid(widgets);
    }
  });
})();
</script>
