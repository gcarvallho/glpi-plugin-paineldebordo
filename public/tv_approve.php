<?php

/**
 * Authenticated TV authorization page (phone/PC).
 * Requires GLPI login + plugin READ. QR from tv_pair points here with ?code=.
 */
// GLPI 11 boots core before its LegacyFileLoadController require()s this file;
// only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/layout.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');

plugin_paineldebordo_checkAccess(READ);

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$code = plugin_paineldebordo_tv_pair_normalize_code($code);

$lang = function_exists('plugin_paineldebordo_html_lang') ? plugin_paineldebordo_html_lang() : 'en';
$csrf = plugin_paineldebordo_csrf_token();
$approve_endpoint = 'ajax/tv_pair_approve.php';
$brand = function_exists('plugin_paineldebordo_branding_get')
    ? plugin_paineldebordo_branding_get()
    : ['eyebrow' => 'Inovare - Hub'];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(__('Authorize TV', 'paineldebordo')); ?> — Painel de Bordo</title>
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
  }
  if (function_exists('plugin_paineldebordo_branding_emit_style')) {
      plugin_paineldebordo_branding_emit_style();
  }
  ?>
  <style>
    body.tv-approve-body {
      margin: 0; min-height: 100vh; background: var(--ho-sider, #09141F); color: #fff;
      font-family: var(--ho-font, "Source Sans 3", sans-serif);
      display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    }
    .tv-approve { max-width: 26rem; width: 100%; text-align: center; }
    .tv-approve__brand { color: var(--ho-accent, #E73E11); font-size: 0.8rem; letter-spacing: 0.12em; text-transform: uppercase; margin: 0 0 0.5rem; }
    .tv-approve__title { font-size: 1.65rem; margin: 0 0 0.75rem; font-weight: 700; }
    .tv-approve__hint { color: rgba(255,255,255,0.7); font-size: 1rem; line-height: 1.45; margin: 0 0 1.25rem; }
    .tv-approve__code {
      font-size: 2rem; font-weight: 700; letter-spacing: 0.18em;
      padding: 1rem 1.25rem; border: 2px solid #E73E11; border-radius: 4px;
      background: #0f1a24; margin: 0 0 1.25rem;
    }
    .tv-approve__btn {
      display: inline-flex; align-items: center; justify-content: center;
      min-height: 2.75rem; padding: 0.65rem 1.35rem; border: 0; border-radius: 4px;
      background: #E73E11; color: #fff; font-weight: 700; font-size: 1rem; cursor: pointer; width: 100%;
    }
    .tv-approve__btn:disabled { opacity: 0.55; cursor: not-allowed; }
    .tv-approve__msg { margin-top: 1rem; min-height: 1.5rem; font-weight: 600; }
    .tv-approve__msg.is-ok { color: #3ddc84; }
    .tv-approve__msg.is-err { color: #ff6b6b; }
    .tv-approve__empty { color: rgba(255,255,255,0.65); }
  </style>
</head>
<body class="tv-approve-body">
  <div class="tv-approve">
    <p class="tv-approve__brand"><?php echo htmlspecialchars($brand['eyebrow']); ?></p>
    <h1 class="tv-approve__title"><?php echo htmlspecialchars(__('Authorize TV', 'paineldebordo')); ?></h1>
    <?php if ($code === '') { ?>
      <p class="tv-approve__empty"><?php echo htmlspecialchars(__('No pairing code in this link. Open the QR from the TV screen or enter the code in Setup.', 'paineldebordo')); ?></p>
    <?php } else { ?>
      <p class="tv-approve__hint"><?php echo htmlspecialchars(__('Confirm that you want to authorize this display to show Painel de Bordo.', 'paineldebordo')); ?></p>
      <div class="tv-approve__code" id="tv_approve_code"><?php echo htmlspecialchars($code); ?></div>
      <button type="button" class="tv-approve__btn" id="tv_approve_btn"><?php echo htmlspecialchars(__('Authorize this TV', 'paineldebordo')); ?></button>
      <p class="tv-approve__msg" id="tv_approve_msg" role="status"></p>
    <?php } ?>
  </div>
<?php if ($code !== '') { ?>
<script>
(function () {
  var csrf = <?php echo json_encode($csrf); ?>;
  var endpoint = <?php echo json_encode($approve_endpoint); ?>;
  var code = <?php echo json_encode($code); ?>;
  var btn = document.getElementById('tv_approve_btn');
  var msg = document.getElementById('tv_approve_msg');
  var I18N = {
    ok: <?php echo json_encode(__('TV linked successfully', 'paineldebordo')); ?>,
    fail: <?php echo json_encode(__('Invalid or expired code', 'paineldebordo')); ?>,
    working: <?php echo json_encode(__('Authorizing…', 'paineldebordo')); ?>
  };
  if (!btn) return;
  btn.addEventListener('click', async function () {
    btn.disabled = true;
    msg.className = 'tv-approve__msg';
    msg.textContent = I18N.working;
    try {
      var body = new FormData();
      body.append('code', code);
      body.append('tv_pair_code', code);
      if (csrf) body.append('_glpi_csrf_token', csrf);
      var headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (csrf) headers['X-Glpi-Csrf-Token'] = csrf;
      var res = await fetch(endpoint, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: headers
      });
      var data = await res.json().catch(function () { return null; });
      if (data && data.ok) {
        msg.className = 'tv-approve__msg is-ok';
        msg.textContent = data.message || I18N.ok;
        return;
      }
      msg.className = 'tv-approve__msg is-err';
      msg.textContent = (data && data.message) ? data.message : I18N.fail;
      btn.disabled = false;
    } catch (e) {
      msg.className = 'tv-approve__msg is-err';
      msg.textContent = I18N.fail;
      btn.disabled = false;
    }
  });
})();
</script>
<?php } ?>
</body>
</html>
