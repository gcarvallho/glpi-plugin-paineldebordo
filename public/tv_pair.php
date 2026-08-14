<?php

/**
 * Public TV pairing screen — no login required on the display.
 * QR points to authenticated tv_approve.php for phone/PC authorization.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this file;
// only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/layout.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/branding.inc.php');

$lang = function_exists('plugin_paineldebordo_html_lang') ? plugin_paineldebordo_html_lang() : 'en';
$theme = $_GET['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
$approve_base = function_exists('plugin_paineldebordo_absolute_url')
    ? plugin_paineldebordo_absolute_url('tv_approve.php')
    : 'tv_approve.php';
$brand = function_exists('plugin_paineldebordo_branding_get')
    ? plugin_paineldebordo_branding_get()
    : ['eyebrow' => 'Inovare - Hub', 'product_name' => 'Painel de Bordo'];
$pair_ajax = function_exists('plugin_paineldebordo_asset_url')
    ? plugin_paineldebordo_asset_url('tv_pair_api.php')
    : 'tv_pair_api.php';
$pair_ajax_candidates = [];
if (function_exists('plugin_paineldebordo_asset_bases')) {
    foreach (plugin_paineldebordo_asset_bases() as $b) {
        $pair_ajax_candidates[] = rtrim($b, '/') . '/tv_pair_api.php';
        $pair_ajax_candidates[] = rtrim($b, '/') . '/ajax/tv_pair.php';
    }
}
$pair_ajax_candidates[] = 'tv_pair_api.php';
$pair_ajax_candidates[] = 'ajax/tv_pair.php';
$pair_ajax_candidates = array_values(array_unique($pair_ajax_candidates));
$tv_pair_debug = !empty($_GET['tv_pair_debug']);
$url_debug = function_exists('plugin_paineldebordo_url_debug')
    ? plugin_paineldebordo_url_debug()
    : [];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(__('TV mode', 'paineldebordo')); ?> — Painel de Bordo</title>
  <?php if (function_exists('plugin_paineldebordo_favicon_href')) { ?>
  <link rel="icon" href="<?php echo htmlspecialchars(plugin_paineldebordo_favicon_href($brand)); ?>" type="image/svg+xml">
  <?php } elseif (function_exists('plugin_paineldebordo_asset_url')) { ?>
  <link rel="icon" href="<?php echo htmlspecialchars(plugin_paineldebordo_asset_url('img/favicon.svg')); ?>" type="image/svg+xml">
  <?php } ?>
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <?php
  $css_file = Plugin::getPhpDir('paineldebordo') . '/public/css/dashboard-tokens.css';
  if (is_file($css_file)) {
      $css = @file_get_contents($css_file);
      if (is_string($css) && $css !== '') {
          echo '<style id="ho-tokens-inline">' . $css . '</style>';
      }
  }
  if (function_exists('plugin_paineldebordo_branding_emit_style')) {
      include_once(Plugin::getPhpDir('paineldebordo') . '/inc/branding.inc.php');
      plugin_paineldebordo_branding_emit_style();
  }
  if (function_exists('plugin_paineldebordo_asset_bases')) {
      foreach (plugin_paineldebordo_asset_bases() as $base) {
          echo '<link rel="stylesheet" href="' . htmlspecialchars(rtrim($base, '/')) . '/css/dashboard-tokens.css">';
      }
  } else {
      echo '<link rel="stylesheet" href="css/dashboard-tokens.css">';
  }
  if (function_exists('plugin_paineldebordo_branding_emit_style')) {
      plugin_paineldebordo_branding_emit_style();
  }
  ?>
  <style>
    body.tv-pair-body {
      margin:0; min-height:100vh; background:var(--ho-sider, #09141F); color:#fff;
      font-family:var(--ho-font, "Source Sans 3", sans-serif);
      display:flex; align-items:center; justify-content:center; padding:1.5rem;
    }
    .tv-pair { text-align:center; max-width:720px; width:100%; }
    .tv-pair__brand { color:var(--ho-accent, #E73E11); font-size:0.85rem; letter-spacing:0.12em; text-transform:uppercase; margin:0 0 0.5rem; }
    .tv-pair__title { font-size:1.85rem; margin:0 0 0.65rem; font-weight:700; }
    .tv-pair__hint { color:rgba(255,255,255,0.7); font-size:1rem; line-height:1.45; margin:0 0 1.25rem; }
    .tv-pair__main {
      display:flex; flex-wrap:wrap; align-items:center; justify-content:center;
      gap:1.5rem 2rem; margin:0 0 1.25rem;
    }
    .tv-pair__code {
      font-size:clamp(2.2rem, 6vw, 3.2rem); font-weight:700; letter-spacing:0.18em;
      padding:1.15rem 1.35rem; border:2px solid #E73E11; border-radius:4px;
      background:#0f1a24; min-width:min(100%, 16rem);
    }
    .tv-pair__qr {
      width:200px; height:200px; padding:0.65rem; background:#fff; border-radius:4px;
      display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .tv-pair__qr:empty { display:none; }
    .tv-pair__timer { margin:0 auto 0.85rem; max-width:28rem; }
    .tv-pair__timer-row {
      display:flex; justify-content:space-between; align-items:baseline;
      font-size:0.9rem; color:rgba(255,255,255,0.65); margin-bottom:0.35rem;
    }
    .tv-pair__timer-row strong { color:#fff; font-variant-numeric:tabular-nums; letter-spacing:0.04em; }
    .tv-pair__bar {
      height:0.45rem; border-radius:999px; background:rgba(255,255,255,0.12); overflow:hidden;
    }
    .tv-pair__bar > span {
      display:block; height:100%; width:100%; background:#E73E11;
      transform-origin:left center; transition:transform 0.2s linear;
    }
    .tv-pair__bar.is-warn > span { background:#f0a500; }
    .tv-pair__status { margin:0.75rem 0 0; color:#E73E11; font-weight:600; min-height:1.5rem; }
    .tv-pair__status.is-warn { color:#f0a500; }
    .tv-pair__status.is-ok { color:#3ddc84; }
    .tv-pair__qr-hint { color:rgba(255,255,255,0.55); font-size:0.9rem; margin:0.75rem 0 0; line-height:1.4; }
    .tv-pair__url { margin-top:0.75rem; font-size:0.8rem; color:rgba(255,255,255,0.4); word-break:break-all; }
    .tv-pair__debug {
      margin-top:1rem; text-align:left; background:#0f1a24; border:1px solid rgba(231,62,17,0.4);
      border-radius:4px; padding:0.75rem; max-width:40rem; margin-left:auto; margin-right:auto;
    }
    .tv-pair__debug-pre {
      margin:0 0 0.65rem; white-space:pre-wrap; word-break:break-word;
      font-size:0.72rem; color:rgba(255,255,255,0.75); font-family:ui-monospace,Consolas,monospace;
    }
    .tv-pair__debug-copy {
      background:#E73E11; color:#fff; border:0; border-radius:4px; padding:0.4rem 0.75rem;
      font:inherit; cursor:pointer;
    }
    @media (max-width:640px) {
      .tv-pair__main { flex-direction:column; }
    }
  </style>
  <script src="js/qrcode.min.js"></script>
</head>
<body class="tv-pair-body">
  <div class="tv-pair" id="tv_pair_root">
    <p class="tv-pair__brand"><?php echo htmlspecialchars($brand['eyebrow']); ?></p>
    <h1 class="tv-pair__title"><?php echo htmlspecialchars(__('TV mode', 'paineldebordo')); ?></h1>
    <p class="tv-pair__hint"><?php echo htmlspecialchars(__('Scan the QR code with your phone, or enter this code in Painel de Bordo → Setup to authorize this screen.', 'paineldebordo')); ?></p>

    <div class="tv-pair__main">
      <div class="tv-pair__code" id="tv_pair_code" aria-live="polite">····-····</div>
      <div class="tv-pair__qr" id="tv_pair_qr" aria-hidden="true"></div>
    </div>

    <div class="tv-pair__timer" id="tv_pair_timer" hidden>
      <div class="tv-pair__timer-row">
        <span><?php echo htmlspecialchars(__('Code expires in', 'paineldebordo')); ?></span>
        <strong id="tv_pair_countdown">--:--</strong>
      </div>
      <div class="tv-pair__bar" id="tv_pair_bar"><span id="tv_pair_bar_fill"></span></div>
    </div>

    <p class="tv-pair__status" id="tv_pair_status"><?php echo htmlspecialchars(__('Generating code…', 'paineldebordo')); ?></p>
    <p class="tv-pair__qr-hint"><?php echo htmlspecialchars(__('Scan with your phone; if you are not logged into GLPI, sign in and authorize this TV.', 'paineldebordo')); ?></p>
    <p class="tv-pair__url" id="tv_pair_url"></p>
    <div class="tv-pair__debug" id="tv_pair_debug" <?php echo $tv_pair_debug ? '' : 'hidden'; ?>></div>
  </div>
<script>
(function(){
  const I18N = {
    waiting: <?php echo json_encode(__('Waiting for authorization…', 'paineldebordo')); ?>,
    linked: <?php echo json_encode(__('Linked — loading wallboard…', 'paineldebordo')); ?>,
    error: <?php echo json_encode(__('Could not create pairing code.', 'paineldebordo')); ?>,
    validating: <?php echo json_encode(__('Checking saved link…', 'paineldebordo')); ?>,
    renewing: <?php echo json_encode(__('Code about to expire — refreshing soon…', 'paineldebordo')); ?>,
    renewed: <?php echo json_encode(__('Code updated. Scan or enter the new code.', 'paineldebordo')); ?>,
    copyLog: <?php echo json_encode(__('Copy log', 'paineldebordo')); ?>,
    copied: <?php echo json_encode(__('Copied', 'paineldebordo')); ?>,
    debugHint: <?php echo json_encode(__('Tip: open with ?tv_pair_debug=1 and copy this block.', 'paineldebordo')); ?>
  };
  const THEME = <?php echo json_encode($theme); ?>;
  const APPROVE_BASE = <?php echo json_encode($approve_base); ?>;
  const PAIR_AJAX_LIST = <?php echo json_encode($pair_ajax_candidates, JSON_UNESCAPED_SLASHES); ?>;
  const URL_DEBUG = <?php echo json_encode($url_debug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  const TV_PAIR_DEBUG = <?php echo $tv_pair_debug ? 'true' : 'false'; ?>;
  let PAIR_AJAX = PAIR_AJAX_LIST[0] || <?php echo json_encode($pair_ajax); ?>;
  const codeEl = document.getElementById('tv_pair_code');
  const statusEl = document.getElementById('tv_pair_status');
  const urlEl = document.getElementById('tv_pair_url');
  const qrEl = document.getElementById('tv_pair_qr');
  const timerEl = document.getElementById('tv_pair_timer');
  const countdownEl = document.getElementById('tv_pair_countdown');
  const barEl = document.getElementById('tv_pair_bar');
  const barFill = document.getElementById('tv_pair_bar_fill');
  const debugEl = document.getElementById('tv_pair_debug');

  let code = '';
  let token = localStorage.getItem('pdb_tv_token') || '';
  let pollTimer = null;
  let tickTimer = null;
  let expiresAtMs = 0;
  let ttlSec = 300;
  let warned = false;
  let stopped = false;
  let renewing = false;

  function clientTimezone() {
    try {
      return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e) {
      return '';
    }
  }

  function approveUrl(pairCode) {
    const u = new URL(APPROVE_BASE, location.href);
    u.searchParams.set('code', pairCode);
    return u.href;
  }

  function showDebug(detail, attempts) {
    if (!debugEl) return;
    debugEl.hidden = false;
    const lines = [];
    lines.push(I18N.error);
    if (detail) lines.push(String(detail));
    lines.push('');
    lines.push('page: ' + location.href);
    lines.push('PAIR_AJAX: ' + PAIR_AJAX);
    lines.push('PAIR_AJAX_LIST: ' + JSON.stringify(PAIR_AJAX_LIST));
    lines.push('APPROVE_BASE: ' + APPROVE_BASE);
    if (URL_DEBUG && typeof URL_DEBUG === 'object') {
      Object.keys(URL_DEBUG).forEach(function (k) {
        lines.push(k + ': ' + JSON.stringify(URL_DEBUG[k]));
      });
    }
    if (attempts && attempts.length) {
      lines.push('');
      lines.push('attempts:');
      attempts.forEach(function (a, i) {
        lines.push('  [' + i + '] ' + (a.method || '?') + ' ' + a.url
          + ' → status=' + (a.status != null ? a.status : '?')
          + ' err=' + (a.message || '')
          + (a.hint ? ' hint=' + a.hint : '')
          + (a.snippet ? ' body=' + a.snippet : ''));
      });
    }
    lines.push('');
    lines.push(I18N.debugHint);
    const text = lines.join('\n');
    debugEl.textContent = '';
    const pre = document.createElement('pre');
    pre.className = 'tv-pair__debug-pre';
    pre.textContent = text;
    debugEl.appendChild(pre);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'tv-pair__debug-copy';
    btn.textContent = I18N.copyLog;
    btn.addEventListener('click', function () {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          btn.textContent = I18N.copied;
        }).catch(function () { window.prompt('Log', text); });
      } else {
        window.prompt('Log', text);
      }
    });
    debugEl.appendChild(btn);
    if (TV_PAIR_DEBUG) {
      try { console.error('[paineldebordo tv_pair]', text); } catch (e) {}
    }
  }

  function renderQr(pairCode) {
    if (!qrEl || typeof QRCode === 'undefined') return;
    const href = approveUrl(pairCode);
    urlEl.textContent = href;
    qrEl.innerHTML = '';
    try {
      new QRCode(qrEl, {
        text: href,
        width: 184,
        height: 184,
        colorDark: '#09141F',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
    } catch (e) {}
  }

  function clearTimers() {
    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
  }

  function formatMmSs(sec) {
    sec = Math.max(0, Math.ceil(Number(sec) || 0));
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    const ss = (s < 10 ? '0' : '') + s;
    if (m <= 0) {
      return ss + ' s';
    }
    return m + ' min ' + ss + ' s';
  }

  function tickCountdown() {
    if (stopped || !expiresAtMs) return;
    const left = (expiresAtMs - Date.now()) / 1000;
    countdownEl.textContent = formatMmSs(left);
    const pct = ttlSec > 0 ? Math.max(0, Math.min(1, left / ttlSec)) : 0;
    barFill.style.transform = 'scaleX(' + pct + ')';
    if (left <= 5 && left > 0) {
      barEl.classList.add('is-warn');
      if (!warned) {
        warned = true;
        statusEl.className = 'tv-pair__status is-warn';
        statusEl.textContent = I18N.renewing;
      }
    } else if (left > 5) {
      barEl.classList.remove('is-warn');
    }
    if (left <= 0 && !renewing) {
      renewCode();
    }
  }

  function startCountdown(expiresAt, ttl) {
    // Always drive the UI from ttl_sec — parsing server datetime without TZ
    // shifts by hours (e.g. UTC-3 → ~180 min extra → "182 min").
    ttlSec = Math.max(1, parseInt(ttl, 10) || 300);
    expiresAtMs = Date.now() + ttlSec * 1000;
    warned = false;
    timerEl.hidden = false;
    barEl.classList.remove('is-warn');
    if (tickTimer) clearInterval(tickTimer);
    tickCountdown();
    tickTimer = setInterval(tickCountdown, 250);
  }

  function schedulePoll() {
    if (stopped || renewing) return;
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, 2500);
  }

  async function tryPairFetch(query, method, body) {
    const attempts = [];
    const list = PAIR_AJAX_LIST.length ? PAIR_AJAX_LIST : [PAIR_AJAX];
    const meth = (method || 'GET').toUpperCase();
    for (let i = 0; i < list.length; i++) {
      const base = list[i];
      let url = base + (base.indexOf('?') >= 0 ? '&' : '?') + query;
      try {
        const opts = {
          method: meth,
          credentials: 'same-origin',
          headers: {},
          cache: 'no-store'
        };
        // Avoid X-Requested-With on public pair API — GLPI may treat XHR+POST as CSRF
        if (meth !== 'GET' && meth !== 'HEAD') {
          opts.headers['X-Requested-With'] = 'XMLHttpRequest';
        }
        if (body) opts.body = body;
        const r = await fetch(url, opts);
        const text = await r.text();
        let data = null;
        try { data = JSON.parse(text); } catch (e) { data = null; }
        let hint = '';
        if (!data && text) {
          const m = text.match(/alert[^\"]*\"[^>]*>([^<]{8,120})/);
          if (m) hint = m[1].replace(/\s+/g, ' ').trim();
        }
        attempts.push({
          url: url,
          method: meth,
          status: r.status,
          message: data && data.error ? data.error : (!data ? 'not_json' : ''),
          hint: hint,
          snippet: (!data ? String(text).slice(0, 160) : '')
        });
        if (data && (data.ok || data.status)) {
          PAIR_AJAX = base;
          return { data: data, attempts: attempts };
        }
      } catch (e) {
        attempts.push({
          url: url,
          method: meth,
          status: null,
          message: String(e && e.message ? e.message : e)
        });
      }
    }
    return { data: null, attempts: attempts };
  }

  async function createCode(opts) {
    opts = opts || {};
    renewing = !!opts.renew;
    try {
      const res = await tryPairFetch(
        'action=create&timezone=' + encodeURIComponent(clientTimezone()),
        'GET',
        null
      );
      const data = res.data;
      if (!data || !data.ok) {
        throw { message: 'create_failed', attempts: res.attempts };
      }
      code = data.code;
      codeEl.textContent = code;
      renderQr(code);
      startCountdown(data.expires_at, data.ttl_sec || 300);
      statusEl.className = 'tv-pair__status' + (opts.renew ? ' is-ok' : '');
      statusEl.textContent = opts.renew ? I18N.renewed : I18N.waiting;
      if (debugEl && !TV_PAIR_DEBUG) debugEl.hidden = true;
      renewing = false;
      schedulePoll();
    } catch (e) {
      renewing = false;
      statusEl.className = 'tv-pair__status';
      statusEl.textContent = I18N.error;
      showDebug(e && e.message ? e.message : 'fail', e && e.attempts ? e.attempts : []);
    }
  }

  async function renewCode() {
    if (stopped || renewing) return;
    clearTimers();
    await createCode({ renew: true });
  }

  async function poll() {
    if (!code || stopped || renewing) return;
    try {
      const res = await tryPairFetch('action=poll&code=' + encodeURIComponent(code), 'GET', null);
      const data = res.data;
      if (!data || !data.ok) {
        schedulePoll();
        return;
      }
      if (data.status === 'pending') {
        if (!warned) {
          statusEl.className = 'tv-pair__status';
          statusEl.textContent = I18N.waiting;
        }
        schedulePoll();
        return;
      }
      if (data.status === 'expired') {
        renewCode();
        return;
      }
      if (data.status === 'linked') {
        stopped = true;
        clearTimers();
        if (data.token) {
          token = data.token;
          localStorage.setItem('pdb_tv_token', token);
        }
        statusEl.className = 'tv-pair__status is-ok';
        statusEl.textContent = I18N.linked;
        if (token) {
          location.href = 'tv.php?device=1&theme=' + encodeURIComponent(THEME);
        }
        return;
      }
      schedulePoll();
    } catch (e) {
      schedulePoll();
    }
  }

  async function validateStoredToken() {
    statusEl.textContent = I18N.validating;
    try {
      const headers = { 'Authorization': 'Bearer ' + token, 'X-Requested-With': 'XMLHttpRequest' };
      const boardCandidates = [];
      PAIR_AJAX_LIST.forEach(function (u) {
        boardCandidates.push(u.replace(/ajax\/tv_pair\.php.*$/, 'ajax/tv_board.php'));
      });
      boardCandidates.push('ajax/tv_board.php');
      let ok = false;
      for (let i = 0; i < boardCandidates.length; i++) {
        const base = boardCandidates[i];
        const url = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'tv_token=' + encodeURIComponent(token);
        try {
          const r = await fetch(url, { credentials: 'same-origin', headers: headers, cache: 'no-store' });
          if (r.status === 403) {
            localStorage.removeItem('pdb_tv_token');
            token = '';
            createCode();
            return;
          }
          const data = await r.json().catch(function () { return null; });
          if (data && data.ok) {
            ok = true;
            break;
          }
        } catch (e) {}
      }
      if (ok) {
        location.href = 'tv.php?device=1&theme=' + encodeURIComponent(THEME);
        return;
      }
      localStorage.removeItem('pdb_tv_token');
      token = '';
      createCode();
    } catch (e) {
      localStorage.removeItem('pdb_tv_token');
      token = '';
      createCode();
    }
  }

  if (TV_PAIR_DEBUG) {
    showDebug('debug_on', []);
  }

  if (token) {
    validateStoredToken();
  } else {
    createCode();
  }
})();
</script>
</body>
</html>
