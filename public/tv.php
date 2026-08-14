<?php

/**
 * Modo TV — mural de filas (slim) + toasts ao vivo + prefs de exibição.
 * Escopo = mesmos chamados que Chamados abertos (+ coluna Aprovação).
 */
// GLPI 11 boots core (DB, session, classes) before its LegacyFileLoadController
// require()s this file, so inc/includes.php is already loaded — skip it. Do NOT
// compute the path via dirname(__DIR__): on symlink/volume mounts (Docker)
// __DIR__ is the resolved real path, which can sit outside the GLPI tree and
// makes the computed root wrong (e.g. /var). Only fall back to a CWD-relative
// bootstrap when core really isn't loaded — matching public/tv_pair.php.
if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/filters.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/layout.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');

include_once(Plugin::getPhpDir('paineldebordo') . '/inc/branding.inc.php');
$brand = plugin_paineldebordo_branding_get();
$device_mode = isset($_GET['device']);
if (!$device_mode) {
    plugin_paineldebordo_checkAccess(READ);
}

// TV opens in light by default; client may override via localStorage (theme toggle).
$theme = $_GET['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
$catalog = plugin_paineldebordo_tv_event_catalog();
$toast_types = plugin_paineldebordo_tv_toast_types();
$root = $CFG_GLPI['root_doc'] ?? '';
$plugin_web = function_exists('plugin_paineldebordo_asset_base')
    ? plugin_paineldebordo_asset_base()
    : (($root . '/plugins/paineldebordo/public'));
$lang = plugin_paineldebordo_html_lang();
$exit_href = $device_mode ? 'tv_pair.php' : ('shell.php?page=home&theme=' . urlencode($theme));
$ticket_base = rtrim((string) $root, '/') . '/front/ticket.form.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo __('TV mode', 'paineldebordo'); ?> — <?php echo htmlspecialchars($brand['product_name']); ?></title>
  <?php if (function_exists('plugin_paineldebordo_favicon_href')) { ?>
  <link rel="icon" href="<?php echo htmlspecialchars(plugin_paineldebordo_favicon_href($brand)); ?>" type="image/svg+xml">
  <?php } elseif (function_exists('plugin_paineldebordo_asset_url')) { ?>
  <link rel="icon" href="<?php echo htmlspecialchars(plugin_paineldebordo_asset_url('img/favicon.svg')); ?>" type="image/svg+xml">
  <?php } ?>
  <?php if ($root) { ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($root); ?>/css/palettes/auror.css" onerror="this.remove()">
  <?php } ?>
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
      echo '<link rel="stylesheet" href="' . htmlspecialchars($plugin_web) . '/css/dashboard-tokens.css">';
  }
  if (function_exists('plugin_paineldebordo_branding_emit_style')) {
      plugin_paineldebordo_branding_emit_style();
  }
  ?>
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <script>
  (function () {
    try {
      var t = localStorage.getItem('pdb_tv_theme');
      if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
      else document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {
      document.documentElement.setAttribute('data-theme', 'light');
    }
  })();
  </script>
  <style>
    body.tv-mode { margin: 0; background: var(--ho-bg); color: var(--ho-text); font-family: var(--ho-font); overflow: hidden; }
    .tv-wrap { height: 100vh; display: flex; flex-direction: column; padding: 0.65rem 0.85rem 0.55rem; box-sizing: border-box; position: relative; }
    .tv-top { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-shrink: 0; margin-bottom: 0.5rem; }
    .tv-brand { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
    .tv-brand__logo { width: 96px; height: 32px; background: var(--ho-logo) center/contain no-repeat; flex-shrink: 0; }
    .tv-brand__name { margin: 0; font-size: 1.05rem; font-weight: 700; white-space: nowrap; }
    .tv-brand__name span { color: var(--ho-accent); }
    .tv-clock { font-size: 1.85rem; font-weight: 700; letter-spacing: 0.04em; }
    .tv-actions { display: flex; gap: 0.4rem; align-items: center; position: relative; }
    .tv-icon-btn {
      width: 2.5rem; height: 2.5rem; display: inline-flex; align-items: center; justify-content: center;
      border: 1px solid transparent; background: transparent; color: var(--ho-text-muted);
      border-radius: 6px; cursor: pointer; text-decoration: none; padding: 0;
    }
    .tv-icon-btn svg { width: 1.25rem; height: 1.25rem; }
    .tv-icon-btn:hover { color: #E73E11; background: rgba(231, 62, 17, 0.08); }
    .tv-icon-btn.is-on { color: #E73E11; background: rgba(231, 62, 17, 0.12); }
    .tv-prefs {
      display: none; position: absolute; top: calc(100% + 0.35rem); right: 0; z-index: 40;
      width: min(20rem, calc(100vw - 1.5rem)); max-height: min(70vh, 28rem); overflow: auto;
      background: var(--ho-surface); border: 1px solid var(--ho-border); border-top: 2px solid #E73E11;
      border-radius: 6px; padding: 0.75rem 0.85rem; box-shadow: 0 12px 32px rgba(0,0,0,0.28);
    }
    .tv-prefs.is-open { display: block; }
    .tv-prefs__title { margin: 0 0 0.55rem; font-size: 0.85rem; font-weight: 700; }
    .tv-prefs__group { margin: 0 0 0.65rem; }
    .tv-prefs__group h3 {
      margin: 0 0 0.35rem; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em;
      color: var(--ho-text-muted); display: flex; align-items: center; gap: 0.35rem;
    }
    .tv-prefs__group h3 svg { width: 0.85rem; height: 0.85rem; flex-shrink: 0; }
    .tv-prefs__row { display: flex; align-items: center; gap: 0.45rem; margin: 0.28rem 0; font-size: 0.82rem; }
    .tv-prefs__row input { accent-color: #E73E11; flex-shrink: 0; }
    .tv-prefs__row > svg { width: 0.95rem; height: 0.95rem; color: var(--ho-text-muted); flex-shrink: 0; }
    .tv-prefs__row span { flex: 1; min-width: 0; }
    .tv-prefs__row select {
      flex: 1; border: 1px solid var(--ho-border); background: var(--ho-bg); color: var(--ho-text);
      border-radius: 4px; padding: 0.25rem 0.4rem; font-size: 0.82rem;
    }
    .tv-kpis {
      display: flex; flex-wrap: nowrap; gap: 0.55rem; margin-bottom: 0.55rem; flex-shrink: 0;
    }
    .tv-kpi {
      flex: 1 1 0; min-width: 0;
      background: var(--ho-surface); border: 1px solid var(--ho-border); border-radius: 6px;
      padding: 0.55rem 0.75rem; border-top: 3px solid #09141F; min-height: 4.2rem;
    }
    .tv-kpi--accent { border-top-color: #E73E11; }
    .tv-kpi--late { border-top-color: #E73E11; background: #e73f110f; }
    .tv-kpi--oldest { min-height: 5.2rem; }
    .tv-kpi.is-hidden { display: none; }
    [data-theme="dark"] .tv-kpi { border-top-color: var(--ho-text, #ffffff); }
    [data-theme="dark"] .tv-kpi--accent,
    [data-theme="dark"] .tv-kpi--late { border-top-color: #E73E11; }
    .tv-kpi__label {
      font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ho-text-muted); margin: 0 0 0.2rem;
      display: inline-flex; align-items: center; gap: 0.35rem; max-width: 100%;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tv-kpi__label svg { width: 0.85rem; height: 0.85rem; flex-shrink: 0; color: var(--ho-text-muted); }
    .tv-kpi__value { font-size: 1.45rem; font-weight: 700; margin: 0; line-height: 1.15; display: flex; align-items: center; gap: 0.4rem; }
    .tv-kpi__value a { color: inherit; text-decoration: none; }
    .tv-kpi__value a:hover { color: #E73E11; }
    .tv-kpi__title { margin: 0.2rem 0 0; font-size: 0.78rem; font-weight: 600; line-height: 1.2;
      display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    .tv-kpi__meta { margin: 0.2rem 0 0; font-size: 0.72rem; color: var(--ho-text-muted); font-weight: 600; }
    .tv-board {
      flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 0.55rem;
    }
    .tv-board[data-cols="1"] { grid-template-columns: 1fr; }
    .tv-board[data-cols="2"] { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tv-board[data-cols="3"] { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .tv-board[data-cols="4"] { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .tv-board[data-cols="5"] { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .tv-board[data-cols="6"] { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .tv-board[data-cols="7"] { grid-template-columns: repeat(7, minmax(0, 1fr)); }
    .tv-board[data-cols="8"] { grid-template-columns: repeat(8, minmax(0, 1fr)); }
    .tv-board[data-cols="9"] { grid-template-columns: repeat(9, minmax(0, 1fr)); }
    .tv-board[data-cols="10"] { grid-template-columns: repeat(10, minmax(0, 1fr)); }
    .tv-board[data-cols="11"] { grid-template-columns: repeat(11, minmax(0, 1fr)); }
    .tv-board[data-cols="12"] { grid-template-columns: repeat(12, minmax(0, 1fr)); }
    .tv-col {
      background: var(--ho-surface); border: 1px solid var(--ho-border); border-radius: 4px;
      display: flex; flex-direction: column; min-height: 0; overflow: hidden;
    }
    .tv-col.is-hidden { display: none; }
    .tv-col--approval .tv-col__head,
    .tv-col--validation .tv-col__head,
    .tv-col--solution .tv-col__head { border-bottom-color: rgba(231,62,17,0.35); }
    .tv-col__head {
      display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;
      padding: 0.5rem 0.65rem; border-bottom: 1px solid var(--ho-border); background: var(--ho-surface-2); flex-shrink: 0;
    }
    .tv-col__title {
      margin: 0; font-size: 0.9rem; font-weight: 700;
      display: inline-flex; align-items: center; gap: 0.4rem; min-width: 0;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tv-col__title svg { width: 1rem; height: 1rem; color: var(--ho-text-muted); flex-shrink: 0; }
    .tv-col__count { font-size: 1.05rem; font-weight: 700; color: #E73E11; flex-shrink: 0; }
    .tv-col__list { flex: 1; overflow-x: hidden; overflow-y: auto; padding: 0.35rem; overscroll-behavior: contain; -webkit-overflow-scrolling: touch;
      scrollbar-width: thin; scrollbar-color: rgba(231, 62, 17, 0.45) rgba(255, 255, 255, 0.06); }
    .tv-col__list::-webkit-scrollbar,
    .tv-prefs::-webkit-scrollbar,
    .tv-banner--diag::-webkit-scrollbar { width: 8px; }
    .tv-col__list::-webkit-scrollbar-track,
    .tv-prefs::-webkit-scrollbar-track,
    .tv-banner--diag::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.04); border-radius: 4px; }
    .tv-col__list::-webkit-scrollbar-thumb,
    .tv-prefs::-webkit-scrollbar-thumb,
    .tv-banner--diag::-webkit-scrollbar-thumb { background: rgba(231, 62, 17, 0.4); border-radius: 4px; }
    .tv-col__list::-webkit-scrollbar-thumb:hover,
    .tv-prefs::-webkit-scrollbar-thumb:hover,
    .tv-banner--diag::-webkit-scrollbar-thumb:hover { background: rgba(231, 62, 17, 0.65); }
    .tv-prefs { scrollbar-width: thin; scrollbar-color: rgba(231, 62, 17, 0.45) rgba(255, 255, 255, 0.06); }
    .tv-banner--diag { scrollbar-width: thin; scrollbar-color: rgba(231, 62, 17, 0.45) rgba(255, 255, 255, 0.06); }
    .tv-card {
      border: 1px solid var(--ho-border); border-radius: 4px; padding: 0.5rem 0.55rem; margin-bottom: 0.35rem;
      background: var(--ho-bg); border-left: 3px solid #09141F;
      display: flex; flex-direction: column; gap: 0.28rem;
    }
    .tv-card--late { border-left-color: #E73E11; background: #e73f1114; }
    .tv-card--flash { animation: tvFlash 1.1s ease; }
    .tv-card__top {
      display: flex; justify-content: space-between; align-items: center; gap: 0.45rem;
      font-size: 0.72rem; color: var(--ho-text-muted); font-weight: 700;
    }
    .tv-card__id { font-size: inherit; color: inherit; font-weight: inherit; }
    .tv-card__age {
      display: inline-flex; align-items: center; gap: 0.2rem; font-weight: 600; white-space: nowrap; flex-shrink: 0;
    }
    .tv-card__age svg { width: 0.75rem; height: 0.75rem; flex-shrink: 0; }
    .tv-card__title { font-size: 0.9rem; font-weight: 700; margin: 0; line-height: 1.25; color: var(--ho-text); }
    .tv-card__title-text {
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .tv-card__group {
      display: flex; align-items: center; gap: 0.28rem; min-width: 0; max-width: 100%;
      font-size: 0.7rem; font-weight: 600; color: #E73E11;
    }
    .tv-card__group svg { width: 0.75rem; height: 0.75rem; flex-shrink: 0; }
    .tv-card__group-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .tv-card__people { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .tv-card__tech, .tv-card__requester {
      display: flex; align-items: center; gap: 0.32rem; font-size: 0.78rem; font-weight: 700;
      border-radius: 4px; padding: 0.16rem 0.4rem; width: fit-content; max-width: 100%;
    }
    .tv-card__tech-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .tv-card__tech {
      color: var(--ho-primary, #09141F); background: rgba(231, 62, 17, 0.08);
      border: 1px solid rgba(231, 62, 17, 0.25);
    }
    [data-theme="dark"] .tv-card__tech { color: var(--ho-text); }
    .tv-card__tech svg { width: 0.85rem; height: 0.85rem; flex-shrink: 0; color: #E73E11; }
    .tv-card__requester {
      color: var(--ho-primary, #09141F); background: rgba(9, 20, 31, 0.06);
      border: 1px solid rgba(9, 20, 31, 0.18);
    }
    [data-theme="dark"] .tv-card__requester {
      color: var(--ho-text); background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.18);
    }
    .tv-card__requester svg { width: 0.85rem; height: 0.85rem; flex-shrink: 0; color: var(--ho-primary, #09141F); }
    [data-theme="dark"] .tv-card__requester svg { color: var(--ho-text); }
    .tv-card__requester-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .tv-card__obs svg, .tv-card__category svg {
      width: 0.75rem; height: 0.75rem; flex-shrink: 0;
    }
    .tv-card__obs, .tv-card__category {
      margin: 0; font-size: 0.68rem; color: var(--ho-text-muted); line-height: 1.3;
      display: inline-flex; align-items: center; gap: 0.25rem; min-width: 0; max-width: 100%;
    }
    .tv-card__obs-text, .tv-card__category-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .tv-card__dates { margin: 0; font-size: 0.68rem; color: var(--ho-text-muted); display: flex; flex-wrap: wrap; gap: 0.3rem 0.65rem; }
    .tv-card__dates span { display: inline-flex; align-items: center; gap: 0.2rem; }
    .tv-card__dates svg { width: 0.75rem; height: 0.75rem; }
    .tv-card__foot {
      display: flex; justify-content: space-between; align-items: center; gap: 0.45rem;
      margin-top: 0.1rem; padding-top: 0.28rem; border-top: 1px solid var(--ho-border);
    }
    .tv-card__prio { font-size: 0.72rem; font-weight: 700; color: var(--ho-text-muted); }
    .tv-card__prio::before {
      content: ''; display: inline-block; width: 0.4em; height: 0.4em; border-radius: 50%;
      background: currentColor; margin-right: 0.32em; vertical-align: middle;
    }
    .tv-card__prio[data-prio="1"] { color: #9aa3ad; }
    .tv-card__prio[data-prio="2"] { color: #626976; }
    .tv-card__prio[data-prio="3"] { color: #09141F; }
    [data-theme="dark"] .tv-card__prio[data-prio="3"] { color: #ffffff; }
    .tv-card__prio[data-prio="4"] { color: #ff8a65; }
    .tv-card__prio[data-prio="5"] { color: #E73E11; }
    .tv-card__prio[data-prio="6"] { color: #b00020; }
    [data-theme="dark"] .tv-card__prio[data-prio="6"] { color: #ff5c7a; }
    .tv-preview__meta { margin: 0 0 0.15rem; font-size: 0.78rem; color: var(--ho-text-muted); }
    #tv_preview_modal .ho-ds-modal__card { width: min(42rem, 100%); }
    #tv_preview_body { white-space: pre-wrap; max-height: 65vh; overflow-y: auto; padding-right: 0.3rem; }
    #tv_preview_body strong, #tv_preview_body b { color: var(--ho-primary, #09141F); }
    [data-theme="dark"] #tv_preview_body strong, [data-theme="dark"] #tv_preview_body b { color: var(--ho-text); }
    .tv-card__foot-actions { display: flex; align-items: center; gap: 0.2rem; margin-left: auto; }
    .tv-card__open, .tv-card__preview {
      display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.72rem; font-weight: 600;
      color: var(--ho-text-muted); text-decoration: none; border: 1px solid transparent; border-radius: 4px;
      padding: 0.15rem 0.35rem; background: none; cursor: pointer; font-family: inherit;
    }
    .tv-card__open svg, .tv-card__preview svg { width: 0.95rem; height: 0.95rem; }
    .tv-card__open:hover, .tv-card__preview:hover { color: #E73E11; background: rgba(231,62,17,0.08); }
    /* Comfortable: few columns → more content */
    .tv-board[data-cols="1"] .tv-card,
    .tv-board[data-cols="2"] .tv-card {
      padding: 0.65rem 0.75rem; gap: 0.4rem; margin-bottom: 0.45rem;
    }
    .tv-board[data-cols="1"] .tv-card__title,
    .tv-board[data-cols="2"] .tv-card__title {
      font-size: 1.05rem; -webkit-line-clamp: 3;
    }
    .tv-board[data-cols="3"] .tv-card {
      padding: 0.55rem 0.65rem; gap: 0.32rem;
    }
    .tv-board[data-cols="3"] .tv-card__title {
      font-size: 0.95rem; -webkit-line-clamp: 2;
    }
    .tv-board[data-cols="1"] .tv-card__group-text,
    .tv-board[data-cols="2"] .tv-card__group-text {
      white-space: normal; overflow: visible; text-overflow: unset;
    }
    /* Dense mural: many columns → compact; keep observers visible */
    .tv-board[data-cols="6"] .tv-card,
    .tv-board[data-cols="7"] .tv-card,
    .tv-board[data-cols="8"] .tv-card,
    .tv-board[data-cols="9"] .tv-card,
    .tv-board[data-cols="10"] .tv-card,
    .tv-board[data-cols="11"] .tv-card,
    .tv-board[data-cols="12"] .tv-card {
      padding: 0.35rem 0.4rem; gap: 0.16rem; margin-bottom: 0.28rem;
    }
    .tv-board[data-cols="6"] .tv-card__title,
    .tv-board[data-cols="7"] .tv-card__title,
    .tv-board[data-cols="8"] .tv-card__title,
    .tv-board[data-cols="9"] .tv-card__title,
    .tv-board[data-cols="10"] .tv-card__title,
    .tv-board[data-cols="11"] .tv-card__title,
    .tv-board[data-cols="12"] .tv-card__title {
      font-size: 0.8rem; -webkit-line-clamp: 2;
    }
    .tv-board[data-cols="6"] .tv-card__tech, .tv-board[data-cols="6"] .tv-card__requester,
    .tv-board[data-cols="7"] .tv-card__tech, .tv-board[data-cols="7"] .tv-card__requester,
    .tv-board[data-cols="8"] .tv-card__tech, .tv-board[data-cols="8"] .tv-card__requester,
    .tv-board[data-cols="9"] .tv-card__tech, .tv-board[data-cols="9"] .tv-card__requester,
    .tv-board[data-cols="10"] .tv-card__tech, .tv-board[data-cols="10"] .tv-card__requester,
    .tv-board[data-cols="11"] .tv-card__tech, .tv-board[data-cols="11"] .tv-card__requester,
    .tv-board[data-cols="12"] .tv-card__tech, .tv-board[data-cols="12"] .tv-card__requester {
      font-size: 0.68rem; padding: 0.1rem 0.3rem;
    }
    .tv-board[data-cols="6"] .tv-card__date-mod,
    .tv-board[data-cols="7"] .tv-card__date-mod,
    .tv-board[data-cols="8"] .tv-card__date-mod,
    .tv-board[data-cols="9"] .tv-card__date-mod,
    .tv-board[data-cols="10"] .tv-card__date-mod,
    .tv-board[data-cols="11"] .tv-card__date-mod,
    .tv-board[data-cols="12"] .tv-card__date-mod {
      display: none;
    }
    .tv-board[data-cols="6"] .tv-card__open-label, .tv-board[data-cols="6"] .tv-card__preview-label,
    .tv-board[data-cols="7"] .tv-card__open-label, .tv-board[data-cols="7"] .tv-card__preview-label,
    .tv-board[data-cols="8"] .tv-card__open-label, .tv-board[data-cols="8"] .tv-card__preview-label,
    .tv-board[data-cols="9"] .tv-card__open-label, .tv-board[data-cols="9"] .tv-card__preview-label,
    .tv-board[data-cols="10"] .tv-card__open-label, .tv-board[data-cols="10"] .tv-card__preview-label,
    .tv-board[data-cols="11"] .tv-card__open-label, .tv-board[data-cols="11"] .tv-card__preview-label,
    .tv-board[data-cols="12"] .tv-card__open-label, .tv-board[data-cols="12"] .tv-card__preview-label {
      display: none;
    }
    .tv-empty { padding: 1rem 0.65rem; text-align: center; color: var(--ho-text-muted); font-size: 0.8rem; }
    a.tv-empty--link { display: flex; align-items: center; justify-content: center; gap: 0.3rem; text-decoration: none; font-weight: 600; }
    a.tv-empty--link svg { width: 0.9rem; height: 0.9rem; flex-shrink: 0; }
    a.tv-empty--link:hover { color: #E73E11; text-decoration: underline; }
    .tv-banner { display: none; padding: 0.35rem 0.65rem; margin-bottom: 0.4rem; background: #e73f111c; color: #E73E11; border-radius: 4px; flex-shrink: 0; font-size: 0.85rem; }
    .tv-banner--diag {
      white-space: pre-wrap; font-family: ui-monospace, Consolas, monospace; font-size: 0.72rem;
      max-height: 40vh; overflow: auto; line-height: 1.35; color: var(--ho-text);
      background: rgba(0,0,0,0.35); border: 1px solid #E73E11;
    }
    .tv-banner__actions { margin-top: 0.35rem; display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .tv-banner__actions button {
      font: inherit; font-size: 0.75rem; padding: 0.2rem 0.5rem; cursor: pointer;
      border: 1px solid #E73E11; background: transparent; color: #E73E11; border-radius: 4px;
    }
    .tv-toasts {
      position: fixed; bottom: 0.75rem; right: 0.75rem; z-index: 50;
      display: flex; flex-direction: column; gap: 0.45rem; width: min(22rem, calc(100vw - 1.5rem));
      max-height: min(50vh, 22rem); overflow: hidden; pointer-events: none;
    }
    .tv-toast {
      background: var(--ho-surface); border: 1px solid var(--ho-border); border-left: 4px solid #E73E11;
      border-radius: 4px; padding: 0.65rem 0.75rem; box-shadow: 0 10px 28px rgba(0,0,0,0.28);
      animation: tvToastIn 0.22s ease; pointer-events: auto;
    }
    .tv-toast.is-out { animation: tvToastOut 0.2s ease forwards; }
    .tv-toast__type { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #E73E11; margin: 0 0 0.2rem; }
    .tv-toast__id { font-size: 0.78rem; font-weight: 700; color: var(--ho-text-muted); }
    .tv-toast__title { margin: 0.15rem 0 0; font-size: 0.95rem; font-weight: 600; line-height: 1.25;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .tv-toast__group { margin: 0.25rem 0 0; font-size: 0.8rem; color: #E73E11; font-weight: 600; }
    .tv-toast--ok { border-left-color: #2f9e44; }
    .tv-toast--ok .tv-toast__type { color: #2f9e44; }
    .tv-toast--tip { border-left-color: #c9a227; }
    .tv-toast--tip .tv-toast__type { color: #c9a227; }
    @keyframes tvFlash { from { box-shadow: 0 0 0 2px rgba(231,62,17,0.55); } to { box-shadow: none; } }
    @keyframes tvToastIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    @keyframes tvToastOut { to { opacity: 0; transform: translateY(16px); } }
    @media (max-width: 900px) {
      .tv-kpis { flex-wrap: wrap; }
      .tv-kpi { flex: 1 1 calc(50% - 0.3rem); }
      .tv-clock { font-size: 1.25rem; }
      /* Narrow window only — do not use !important (keeps data-cols on wide TVs) */
      .tv-board { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
  </style>
</head>
<body class="tv-mode">
<div class="tv-wrap">
  <header class="tv-top">
    <div class="tv-brand">
      <div class="tv-brand__logo" role="img" aria-label="Inovare"></div>
      <p class="tv-brand__name"><?php echo plugin_paineldebordo_branding_product_html($brand['product_name']); ?></p>
    </div>
    <div id="tv_clock" class="tv-clock">--:--:--</div>
    <div class="tv-actions">
      <button type="button" class="tv-icon-btn ho-tip" id="tv_prefs_btn" data-tip="<?php echo htmlspecialchars(__('Display settings', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars(__('Display settings', 'paineldebordo')); ?>" aria-expanded="false"><?php echo plugin_paineldebordo_icon('cog'); ?></button>
      <button type="button" class="tv-icon-btn ho-tip" id="tv_theme_btn" data-tip="<?php echo htmlspecialchars($theme === 'dark' ? __('Light', 'paineldebordo') : __('Dark', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars($theme === 'dark' ? __('Light', 'paineldebordo') : __('Dark', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon($theme === 'dark' ? 'sun' : 'moon'); ?></button>
      <button type="button" class="tv-icon-btn ho-tip" id="tv_audio_btn" data-tip="<?php echo htmlspecialchars(__('Enable audio', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars(__('Enable audio', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('volume_off'); ?></button>
      <button type="button" class="tv-icon-btn ho-tip" id="tv_fs_btn" data-tip="<?php echo htmlspecialchars(__('Fullscreen', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars(__('Fullscreen', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('fullscreen'); ?></button>
      <?php if ($device_mode) { ?>
      <button type="button" class="tv-icon-btn ho-tip" id="tv_exit_btn" data-tip="<?php echo htmlspecialchars(__('Exit TV', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars(__('Exit TV', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('exit'); ?></button>
      <?php } else { ?>
      <a class="tv-icon-btn ho-tip" href="<?php echo htmlspecialchars($exit_href); ?>" data-tip="<?php echo htmlspecialchars(__('Exit TV', 'paineldebordo')); ?>" aria-label="<?php echo htmlspecialchars(__('Exit TV', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('exit'); ?></a>
      <?php } ?>
      <div class="tv-prefs" id="tv_prefs" role="dialog" aria-label="<?php echo htmlspecialchars(__('Display settings', 'paineldebordo')); ?>">
        <p class="tv-prefs__title"><?php echo __('Display settings', 'paineldebordo'); ?></p>
        <div class="tv-prefs__group" data-pref-group="columns">
          <h3><?php echo __('Columns', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="view_mode">
          <h3><?php echo __('Board view', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="views">
          <h3><?php echo __('Extra views', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="kpis">
          <h3><?php echo __('KPIs', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="card">
          <h3><?php echo __('Card fields', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="sort">
          <h3><?php echo __('Sort', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="toasts">
          <h3><?php echo __('Toasts', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="audio">
          <h3><?php echo __('Audio', 'paineldebordo'); ?></h3>
        </div>
        <div class="tv-prefs__group" data-pref-group="density">
          <h3><?php echo __('Density', 'paineldebordo'); ?></h3>
        </div>
      </div>
    </div>
  </header>

  <div id="tv_banner" class="tv-banner" role="alert"></div>

  <section class="tv-kpis" id="tv_kpis">
    <article class="tv-kpi tv-kpi--accent" data-kpi="today">
      <p class="tv-kpi__label ho-tip" data-tip="<?php echo htmlspecialchars(__('Opened today', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('today'); ?> <?php echo __('Today', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_today">0</p>
    </article>
    <article class="tv-kpi" data-kpi="week">
      <p class="tv-kpi__label ho-tip" data-tip="<?php echo htmlspecialchars(__('This week', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('calendar'); ?> <?php echo __('Week', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_week">0</p>
    </article>
    <article class="tv-kpi" data-kpi="month">
      <p class="tv-kpi__label ho-tip" data-tip="<?php echo htmlspecialchars(__('This month', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('month'); ?> <?php echo __('Month', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_month">0</p>
    </article>
    <article class="tv-kpi tv-kpi--late" data-kpi="late">
      <p class="tv-kpi__label"><?php echo plugin_paineldebordo_icon('late'); ?> <?php echo __('Late', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_late">0</p>
    </article>
    <article class="tv-kpi tv-kpi--oldest" data-kpi="oldest">
      <p class="tv-kpi__label"><?php echo plugin_paineldebordo_icon('sort'); ?> <?php echo __('Oldest ticket', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_oldest">—</p>
      <p class="tv-kpi__title" id="kpi_oldest_title"></p>
      <p class="tv-kpi__meta" id="kpi_oldest_meta"></p>
    </article>
    <article class="tv-kpi tv-kpi--accent" data-kpi="validation_waiting">
      <p class="tv-kpi__label"><?php echo plugin_paineldebordo_icon('solved'); ?> <?php echo __('Validation', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_validation">0</p>
    </article>
    <article class="tv-kpi tv-kpi--accent" data-kpi="solution_waiting">
      <p class="tv-kpi__label ho-tip" data-tip="<?php echo htmlspecialchars(__('Solution approved', 'paineldebordo')); ?>"><?php echo plugin_paineldebordo_icon('solved'); ?> <?php echo __('Approved', 'paineldebordo'); ?></p>
      <p class="tv-kpi__value" id="kpi_solution">0</p>
    </article>
  </section>

  <section class="tv-board" id="tv_board" data-cols="6">
    <div class="tv-empty" style="grid-column:1/-1;"><?php echo __('Loading queues…', 'paineldebordo'); ?></div>
  </section>
</div>

<div class="tv-toasts" id="tv_toasts" aria-live="polite"></div>

<div class="ho-ds-modal" id="tv_preview_modal" hidden>
  <div class="ho-ds-modal__backdrop" data-preview-close></div>
  <div class="ho-ds-modal__card" role="dialog" aria-modal="true" aria-labelledby="tv_preview_title">
    <p class="tv-preview__meta" id="tv_preview_meta"></p>
    <h3 id="tv_preview_title" class="ho-ds-modal__title"></h3>
    <p class="ho-ds-modal__body" id="tv_preview_body"></p>
    <div class="ho-ds-modal__actions">
      <button type="button" class="btn btn-outline-secondary" data-preview-close><?php echo __('Close', 'paineldebordo'); ?></button>
      <a class="btn btn-primary" id="tv_preview_open" href="#" target="_blank" rel="noopener"><?php echo plugin_paineldebordo_icon('external_link'); ?> <span><?php echo __('Open full ticket', 'paineldebordo'); ?></span></a>
    </div>
  </div>
</div>

<script>
(function () {
  const CATALOG = <?php echo json_encode($catalog, JSON_UNESCAPED_UNICODE); ?>;
  const TOAST_TYPES_ALL = <?php echo json_encode($toast_types, JSON_UNESCAPED_UNICODE); ?>;
  const BOARD_MS = 20000;
  const EVENTS_MS = 7000;
  const TOAST_TTL = 5000;
  const TOAST_MAX = 5;
  const TICKET_BASE = <?php echo json_encode($ticket_base); ?>;
  const TV_DEBUG = <?php echo !empty($_GET['tv_debug']) ? 'true' : 'false'; ?>;
  const TV_PAGE = <?php echo json_encode((string) ($_SERVER['REQUEST_URI'] ?? '')); ?>;
  const PLUGIN_WEB = <?php echo json_encode((string) $plugin_web); ?>;
  const ICON_OPEN = <?php echo json_encode(plugin_paineldebordo_icon('external_link')); ?>;
  const ICON_VOLUME = <?php echo json_encode(plugin_paineldebordo_icon('volume')); ?>;
  const ICON_VOLUME_OFF = <?php echo json_encode(plugin_paineldebordo_icon('volume_off')); ?>;
  const ICON_SUN = <?php echo json_encode(plugin_paineldebordo_icon('sun')); ?>;
  const ICON_MOON = <?php echo json_encode(plugin_paineldebordo_icon('moon')); ?>;
  const THEME_KEY = 'pdb_tv_theme';
  const ICON_CAL = <?php echo json_encode(plugin_paineldebordo_icon('calendar')); ?>;
  const ICON_REFRESH = <?php echo json_encode(plugin_paineldebordo_icon('refresh')); ?>;
  const ICON_AGE = <?php echo json_encode(plugin_paineldebordo_icon('late')); ?>;
  const ICON_OBS = <?php echo json_encode(plugin_paineldebordo_icon('eye')); ?>;
  const ICON_GROUP = <?php echo json_encode(plugin_paineldebordo_icon('by_group')); ?>;
  const ICON_USER = <?php echo json_encode(plugin_paineldebordo_icon('headset')); ?>;
  const ICON_REQUESTER = <?php echo json_encode(plugin_paineldebordo_icon('user')); ?>;
  const ICON_CATEGORY = <?php echo json_encode(plugin_paineldebordo_icon('category')); ?>;
  const ICON_PREVIEW = <?php echo json_encode(plugin_paineldebordo_icon('eye')); ?>;
  const PREF_ICONS = <?php echo json_encode([
      'columns' => plugin_paineldebordo_icon('columns'),
      'kpis' => plugin_paineldebordo_icon('chart_bar'),
      'card' => plugin_paineldebordo_icon('tickets'),
      'sort' => plugin_paineldebordo_icon('sort'),
      'toasts' => plugin_paineldebordo_icon('bell'),
      'audio' => plugin_paineldebordo_icon('audio'),
      'density' => plugin_paineldebordo_icon('density'),
      'id' => plugin_paineldebordo_icon('hash'),
      'date' => plugin_paineldebordo_icon('calendar'),
      'date_mod' => plugin_paineldebordo_icon('refresh'),
      'title' => plugin_paineldebordo_icon('title'),
      'tech' => plugin_paineldebordo_icon('headset'),
      'group' => plugin_paineldebordo_icon('by_group'),
      'requester' => plugin_paineldebordo_icon('user'),
      'observers' => plugin_paineldebordo_icon('eye'),
      'category' => plugin_paineldebordo_icon('category'),
      'sla' => plugin_paineldebordo_icon('late'),
      'age' => plugin_paineldebordo_icon('late'),
      'prio' => plugin_paineldebordo_icon('prio'),
      'openBtn' => plugin_paineldebordo_icon('external_link'),
      '1' => plugin_paineldebordo_icon('opened'),
      '2' => plugin_paineldebordo_icon('users'),
      '3' => plugin_paineldebordo_icon('calendar'),
      '4' => plugin_paineldebordo_icon('late'),
      'validation' => plugin_paineldebordo_icon('solved'),
      'solution' => plugin_paineldebordo_icon('solved'),
      'today' => plugin_paineldebordo_icon('today'),
      'week' => plugin_paineldebordo_icon('month'),
      'month' => plugin_paineldebordo_icon('month'),
      'late' => plugin_paineldebordo_icon('late'),
      'oldest' => plugin_paineldebordo_icon('sort'),
      'validation_waiting' => plugin_paineldebordo_icon('solved'),
      'solution_waiting' => plugin_paineldebordo_icon('solved'),
      'for_me' => plugin_paineldebordo_icon('user'),
      'opened_by_me' => plugin_paineldebordo_icon('opened'),
      'observer' => plugin_paineldebordo_icon('eye'),
      'by_group' => plugin_paineldebordo_icon('by_group'),
      'by_entity' => plugin_paineldebordo_icon('by_entity'),
      'views' => plugin_paineldebordo_icon('columns'),
      'novo' => plugin_paineldebordo_icon('opened'),
      'solucao_aceita' => plugin_paineldebordo_icon('solved'),
      'solucao_negada' => plugin_paineldebordo_icon('exit'),
      'reabertura' => plugin_paineldebordo_icon('refresh'),
      'sla_atrasado' => plugin_paineldebordo_icon('late'),
      'volume_off' => plugin_paineldebordo_icon('volume_off'),
  ], JSON_UNESCAPED_UNICODE); ?>;
  const PREF_KEY = 'pdb_tv_display';
  const AUDIO_UNLOCK_KEY = 'pdb_tv_audio_unlocked';

  const boardEndpoints = <?php
    $eps = ['ajax/tv_board.php'];
    if (function_exists('plugin_paineldebordo_asset_bases')) {
        foreach (plugin_paineldebordo_asset_bases() as $b) {
            $eps[] = rtrim($b, '/') . '/ajax/tv_board.php';
        }
    } elseif (!empty($plugin_web)) {
        $eps[] = rtrim($plugin_web, '/') . '/ajax/tv_board.php';
    }
    echo json_encode(array_values(array_unique($eps)));
  ?>;
  const eventEndpoints = <?php
    $eps = ['ajax/tv_events.php'];
    if (function_exists('plugin_paineldebordo_asset_bases')) {
        foreach (plugin_paineldebordo_asset_bases() as $b) {
            $eps[] = rtrim($b, '/') . '/ajax/tv_events.php';
        }
    } elseif (!empty($plugin_web)) {
        $eps[] = rtrim($plugin_web, '/') . '/ajax/tv_events.php';
    }
    echo json_encode(array_values(array_unique($eps)));
  ?>;
  const DEVICE_MODE = <?php echo $device_mode ? 'true' : 'false'; ?>;
  const LOCALE = <?php echo json_encode(str_replace('_', '-', $_SESSION['glpilanguage'] ?? 'pt-BR')); ?>;
  const I18N = {
    empty: <?php echo json_encode(__('No open tickets in this queue', 'paineldebordo')); ?>,
    more: <?php echo json_encode(__('more', 'paineldebordo')); ?>,
    fail: <?php echo json_encode(__('TV mode connection failed', 'paineldebordo')); ?>,
    loadFail: <?php echo json_encode(__('Failed to load queues', 'paineldebordo')); ?>,
    copyLog: <?php echo json_encode(__('Copy log', 'paineldebordo')); ?>,
    copied: <?php echo json_encode(__('Copied', 'paineldebordo')); ?>,
    audioOn: <?php echo json_encode(__('Sound on', 'paineldebordo')); ?>,
    audioOff: <?php echo json_encode(__('Sound off', 'paineldebordo')); ?>,
    enableAudio: <?php echo json_encode(__('Enable audio', 'paineldebordo')); ?>,
    soundEnabled: <?php echo json_encode(__('Sound enabled', 'paineldebordo')); ?>,
    muteTip: <?php echo json_encode(__('CTRL+M turns sound on or off', 'paineldebordo')); ?>,
    themeTip: <?php echo json_encode(__('CTRL+Space switches light and dark theme', 'paineldebordo')); ?>,
    darkEnabled: <?php echo json_encode(__('Dark mode enabled', 'paineldebordo')); ?>,
    lightEnabled: <?php echo json_encode(__('Light mode enabled', 'paineldebordo')); ?>,
    themeLight: <?php echo json_encode(__('Light', 'paineldebordo')); ?>,
    themeDark: <?php echo json_encode(__('Dark', 'paineldebordo')); ?>,
    tipLabel: <?php echo json_encode(__('Tip', 'paineldebordo')); ?>,
    open: <?php echo json_encode(__('View', 'paineldebordo')); ?>,
    observers: <?php echo json_encode(__('Observers', 'paineldebordo')); ?>,
    sla: <?php echo json_encode('SLA'); ?>,
    cardsPerCol: <?php echo json_encode(__('Cards per column', 'paineldebordo')); ?>,
    chime: <?php echo json_encode(__('Chime sounds', 'paineldebordo')); ?>,
    rememberMute: <?php echo json_encode(__('Remember mute', 'paineldebordo')); ?>,
    col: {
      '1': <?php echo json_encode(__('New', 'paineldebordo')); ?>,
      '2': <?php echo json_encode(__('In progress', 'paineldebordo')); ?>,
      '3': <?php echo json_encode(__('Planned', 'paineldebordo')); ?>,
      '4': <?php echo json_encode(__('Pending', 'paineldebordo')); ?>,
      validation: <?php echo json_encode(__('Validation', 'paineldebordo')); ?>,
      solution: <?php echo json_encode(__('Approved', 'paineldebordo')); ?>
    },
    views: {
      for_me: <?php echo json_encode(__('For me', 'paineldebordo')); ?>,
      opened_by_me: <?php echo json_encode(__('Opened by me', 'paineldebordo')); ?>,
      observer: <?php echo json_encode(__('Observer', 'paineldebordo')); ?>,
      by_group: <?php echo json_encode(__('By group', 'paineldebordo')); ?>,
      by_entity: <?php echo json_encode(__('By entity', 'paineldebordo')); ?>
    },
    view_mode: {
      all: <?php echo json_encode(__('Overall', 'paineldebordo')); ?>,
      for_me: <?php echo json_encode(__('For me', 'paineldebordo')); ?>,
      opened_by_me: <?php echo json_encode(__('Opened by me', 'paineldebordo')); ?>,
      my_groups: <?php echo json_encode(__('My groups', 'paineldebordo')); ?>
    },
    kpi: {
      today: <?php echo json_encode(__('Today', 'paineldebordo')); ?>,
      week: <?php echo json_encode(__('Week', 'paineldebordo')); ?>,
      month: <?php echo json_encode(__('Month', 'paineldebordo')); ?>,
      late: <?php echo json_encode(__('Late', 'paineldebordo')); ?>,
      oldest: <?php echo json_encode(__('Oldest ticket', 'paineldebordo')); ?>,
      validation_waiting: <?php echo json_encode(__('Validation', 'paineldebordo')); ?>,
      solution_waiting: <?php echo json_encode(__('Approved', 'paineldebordo')); ?>
    },
    kpiFull: {
      today: <?php echo json_encode(__('Opened today', 'paineldebordo')); ?>,
      week: <?php echo json_encode(__('This week', 'paineldebordo')); ?>,
      month: <?php echo json_encode(__('This month', 'paineldebordo')); ?>,
      late: <?php echo json_encode(__('Late', 'paineldebordo')); ?>,
      oldest: <?php echo json_encode(__('Oldest ticket', 'paineldebordo')); ?>,
      validation_waiting: <?php echo json_encode(__('Validation', 'paineldebordo')); ?>,
      solution_waiting: <?php echo json_encode(__('Solution approved', 'paineldebordo')); ?>
    },
    age: <?php echo json_encode(__('Age', 'paineldebordo')); ?>,
    card: {
      id: <?php echo json_encode(__('Ticket ID', 'paineldebordo')); ?>,
      title: <?php echo json_encode(__('Title', 'paineldebordo')); ?>,
      date: <?php echo json_encode(__('Open date', 'paineldebordo')); ?>,
      date_mod: <?php echo json_encode(__('Last update', 'paineldebordo')); ?>,
      tech: <?php echo json_encode(__('Technician', 'paineldebordo')); ?>,
      requester: <?php echo json_encode(__('Requester', 'paineldebordo')); ?>,
      group: <?php echo json_encode(__('Group', 'paineldebordo')); ?>,
      observers: <?php echo json_encode(__('Observers', 'paineldebordo')); ?>,
      category: <?php echo json_encode(__('Category', 'paineldebordo')); ?>,
      prio: <?php echo json_encode(__('Priority', 'paineldebordo')); ?>,
      prioTip: <?php echo json_encode(__('Ticket priority', 'paineldebordo')); ?>,
      sla: <?php echo json_encode(__('SLA badge', 'paineldebordo')); ?>,
      age: <?php echo json_encode(__('Age', 'paineldebordo')); ?>,
      openBtn: <?php echo json_encode(__('View button', 'paineldebordo')); ?>,
      preview: <?php echo json_encode(__('Preview', 'paineldebordo')); ?>
    },
    sort: {
      newest: <?php echo json_encode(__('Newest first', 'paineldebordo')); ?>,
      oldest: <?php echo json_encode(__('Oldest first', 'paineldebordo')); ?>,
      late_newest: <?php echo json_encode(__('Late, then newest', 'paineldebordo')); ?>
    },
    toast: {
      novo: <?php echo json_encode(__('New ticket', 'paineldebordo')); ?>,
      solucao_aceita: <?php echo json_encode(__('Solution accepted', 'paineldebordo')); ?>,
      solucao_negada: <?php echo json_encode(__('Solution refused', 'paineldebordo')); ?>,
      reabertura: <?php echo json_encode(__('Reopened', 'paineldebordo')); ?>,
      sla_atrasado: <?php echo json_encode(__('SLA late', 'paineldebordo')); ?>
    }
  };

  const DEFAULT_PREFS = {
    columns: { '1': true, '2': true, '3': true, '4': true, validation: true, solution: true },
    view_mode: 'all',
    views: { for_me: false, opened_by_me: false, observer: false, by_group: false, by_entity: false },
    kpis: { today: true, week: true, month: true, late: true, oldest: false, validation_waiting: true, solution_waiting: true },
    card: {
      id: true, title: true, date: true, date_mod: true, tech: true, requester: true, group: true,
      observers: true, category: true, prio: true, sla: true, age: true, openBtn: true, preview: true
    },
    sort: 'newest',
    toasts: { novo: true, solucao_aceita: true, solucao_negada: true, reabertura: true, sla_atrasado: true },
    audio: { chime: true, rememberMute: false, muted: false },
    density: { perColumn: 40 }
  };

  function migratePrefs(p) {
    if (!p || typeof p !== 'object') return p;
    if (p.columns) {
      if (p.columns.approval != null && p.columns.solution == null) {
        p.columns.solution = !!p.columns.approval;
      }
      if (p.columns.validation == null) p.columns.validation = true;
      if (p.columns.solution == null) p.columns.solution = true;
      delete p.columns.approval;
    }
    if (p.kpis) {
      if (p.kpis.waiting_approval != null) {
        if (p.kpis.solution_waiting == null) p.kpis.solution_waiting = !!p.kpis.waiting_approval;
        if (p.kpis.validation_waiting == null) p.kpis.validation_waiting = true;
      }
      delete p.kpis.waiting_approval;
    }
    if (p.card) {
      if (p.card.id == null) p.card.id = true;
      if (p.card.title == null) p.card.title = true;
      if (p.card.date_mod == null) p.card.date_mod = true;
      if (p.card.prio == null) p.card.prio = true;
      if (p.card.requester == null) p.card.requester = true;
    }
    if (!p.sort || ['newest', 'oldest', 'late_newest'].indexOf(p.sort) < 0) {
      p.sort = 'newest';
    }
    if (!p.view_mode || ['all', 'for_me', 'opened_by_me', 'my_groups'].indexOf(p.view_mode) < 0) {
      p.view_mode = 'all';
    }
    // 2.27.3: old defaults were muted+rememberMute; one-time reset to sound-on
    if (!p.audio || typeof p.audio !== 'object') {
      p.audio = { chime: true, rememberMute: false, muted: false };
    } else if (!p._audioDefaults2273) {
      p.audio.muted = false;
      p.audio.rememberMute = false;
      if (p.audio.chime == null) p.audio.chime = true;
      p._audioDefaults2273 = 1;
    }
    return p;
  }

  function deepMerge(base, over) {
    const out = JSON.parse(JSON.stringify(base));
    if (!over || typeof over !== 'object') return out;
    Object.keys(over).forEach(function (k) {
      if (over[k] && typeof over[k] === 'object' && !Array.isArray(over[k])) {
        out[k] = deepMerge(out[k] || {}, over[k]);
      } else {
        out[k] = over[k];
      }
    });
    return out;
  }

  function loadPrefs() {
    try {
      const raw = localStorage.getItem(PREF_KEY);
      if (!raw) return JSON.parse(JSON.stringify(DEFAULT_PREFS));
      return deepMerge(DEFAULT_PREFS, migratePrefs(JSON.parse(raw)));
    } catch (e) {
      return JSON.parse(JSON.stringify(DEFAULT_PREFS));
    }
  }

  function savePrefs() {
    try { localStorage.setItem(PREF_KEY, JSON.stringify(prefs)); } catch (e) {}
  }

  let prefs = loadPrefs();
  if (!prefs.audio || typeof prefs.audio !== 'object') {
    prefs.audio = JSON.parse(JSON.stringify(DEFAULT_PREFS.audio));
  }
  if (!prefs.card || typeof prefs.card !== 'object') {
    prefs.card = JSON.parse(JSON.stringify(DEFAULT_PREFS.card));
  }
  let tvToken = localStorage.getItem('pdb_tv_token') || '';
  if (DEVICE_MODE && !tvToken) { location.href = 'tv_pair.php'; return; }

  let audioEnabled = false;
  // When "remember mute" is off, start unmuted (do not force muted every reload)
  let muted = prefs.audio.rememberMute ? !!prefs.audio.muted : false;
  let audioCtx = null;
  let prevIds = new Set();
  let firstLoad = true;
  let boardIdx = 0;
  let eventIdx = 0;
  let since = '';
  let seenKeys = new Set();
  let lastBoard = null;
  const toastRoot = document.getElementById('tv_toasts');
  const audioBtn = document.getElementById('tv_audio_btn');
  const themeBtn = document.getElementById('tv_theme_btn');
  const prefsBtn = document.getElementById('tv_prefs_btn');
  const prefsPanel = document.getElementById('tv_prefs');

  function ticketUrl(id) {
    return TICKET_BASE + '?id=' + encodeURIComponent(String(id));
  }

  function moreLink(key) {
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    if (key === 'validation') return 'shell.php?page=tickets&view=validation&theme=' + encodeURIComponent(theme);
    if (key === 'solution' || key === 'approval') return 'shell.php?page=tickets&view=solution&theme=' + encodeURIComponent(theme);
    if (/^\d+$/.test(key)) return 'shell.php?page=tickets&status=' + encodeURIComponent(key) + '&theme=' + encodeURIComponent(theme);
    return null;
  }

  function readStoredTheme() {
    try {
      const t = localStorage.getItem(THEME_KEY);
      if (t === 'light' || t === 'dark') return t;
    } catch (e) {}
    return 'light';
  }

  let tvTheme = readStoredTheme();

  function syncThemeBtn() {
    if (!themeBtn) return;
    const isDark = tvTheme === 'dark';
    themeBtn.innerHTML = isDark ? ICON_SUN : ICON_MOON;
    const label = isDark ? I18N.themeLight : I18N.themeDark;
    themeBtn.setAttribute('aria-label', label);
    themeBtn.setAttribute('title', label);
    themeBtn.classList.toggle('is-on', isDark);
  }

  function applyTheme(next, announce) {
    tvTheme = (next === 'dark') ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', tvTheme);
    try { localStorage.setItem(THEME_KEY, tvTheme); } catch (e) {}
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('theme', tvTheme);
      history.replaceState(null, '', url.toString());
    } catch (e) {}
    syncThemeBtn();
    if (announce) {
      pushTipToast(tvTheme === 'dark' ? I18N.darkEnabled : I18N.lightEnabled, '');
    }
  }

  function toggleThemeFromUser() {
    applyTheme(tvTheme === 'dark' ? 'light' : 'dark', true);
  }

  function syncAudioBtn() {
    // Icon follows mute preference (default: on). Browser may still need one gesture to unlock AudioContext.
    const on = !muted;
    audioBtn.classList.toggle('is-on', on);
    audioBtn.innerHTML = on ? ICON_VOLUME : ICON_VOLUME_OFF;
    const label = !audioEnabled
      ? I18N.enableAudio
      : (muted ? I18N.audioOff : I18N.audioOn);
    audioBtn.setAttribute('aria-label', label);
    audioBtn.setAttribute('title', label);
  }

  function tickClock() {
    document.getElementById('tv_clock').textContent = new Date().toLocaleTimeString(LOCALE, { hour12: false });
  }
  setInterval(tickClock, 1000);
  tickClock();

  function ensureAudio() {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === 'suspended') audioCtx.resume();
  }

  function playNote(freq, start, dur, vol) {
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(vol || 0.07, start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.start(start);
    osc.stop(start + dur + 0.02);
  }

  function chime(type) {
    if (!prefs.audio.chime || !audioEnabled || muted || !audioCtx) return;
    const t0 = audioCtx.currentTime;
    const motifs = {
      novo: [660, 784],
      solucao_aceita: [523, 659, 784],
      solucao_negada: [440, 370],
      reabertura: [494, 415],
      sla_atrasado: [392, 330],
      preview: [660, 784]
    };
    const notes = motifs[type] || motifs.novo;
    notes.forEach(function (f, i) {
      playNote(f, t0 + i * 0.12, 0.18, 0.065);
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function eventKey(ev) {
    return (ev.type || '') + ':' + (ev.ticket_id || '') + ':' + (ev.ts || '');
  }

  function pushToast(ev) {
    if (!prefs.toasts[ev.type]) return;
    while (toastRoot.children.length >= TOAST_MAX) {
      toastRoot.removeChild(toastRoot.firstChild);
    }
    const meta = CATALOG[ev.type] || {};
    const el = document.createElement('div');
    el.className = 'tv-toast' + (ev.type === 'solucao_aceita' ? ' tv-toast--ok' : '');
    el.innerHTML =
      '<p class="tv-toast__type">' + escapeHtml(meta.label || ev.type) + '</p>' +
      '<div class="tv-toast__id">#' + escapeHtml(String(ev.ticket_id || '')) + '</div>' +
      '<p class="tv-toast__title">' + escapeHtml(ev.title || '') + '</p>' +
      (ev.group ? '<p class="tv-toast__group">' + escapeHtml(ev.group) + '</p>' : '');
    toastRoot.appendChild(el);
    chime(ev.type);
    setTimeout(function () {
      el.classList.add('is-out');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 220);
    }, TOAST_TTL);
  }

  /** Stackable tip toast — no chime, ignores event toast prefs. */
  function pushTipToast(title, typeLabel, ttlMs) {
    if (!toastRoot) return;
    while (toastRoot.children.length >= TOAST_MAX) {
      toastRoot.removeChild(toastRoot.firstChild);
    }
    const el = document.createElement('div');
    el.className = 'tv-toast tv-toast--tip';
    const type = (typeLabel === undefined || typeLabel === null) ? I18N.tipLabel : typeLabel;
    el.innerHTML =
      (type ? '<p class="tv-toast__type">' + escapeHtml(type) + '</p>' : '') +
      '<p class="tv-toast__title">' + escapeHtml(title || '') + '</p>';
    toastRoot.appendChild(el);
    const ttl = (ttlMs != null && ttlMs > 0) ? ttlMs : Math.max(TOAST_TTL, 5000);
    setTimeout(function () {
      el.classList.add('is-out');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 220);
    }, ttl);
  }

  function toggleAudioFromUser() {
    ensureAudio();
    if (!audioEnabled) {
      audioEnabled = true;
      muted = false;
    } else {
      muted = !muted;
    }
    if (prefs.audio.rememberMute) {
      prefs.audio.muted = muted;
      savePrefs();
    }
    try { localStorage.setItem(AUDIO_UNLOCK_KEY, '1'); } catch (e) {}
    syncAudioBtn();
    if (!muted) {
      pushTipToast(I18N.soundEnabled, '');
      chime('preview');
    }
  }

  /**
   * Unattended TVs/kiosks never click the tiny speaker icon, so without this
   * the AudioContext (browser autoplay policy) is never unlocked and toasts
   * stay silent forever. Unlock on ANY first interaction with the page
   * (click/touch/key — e.g. entering fullscreen), not just the audio button.
   */
  function unlockAudioFromAnyGesture() {
    if (audioEnabled) return;
    ensureAudio();
    audioEnabled = true;
    if (!prefs.audio.rememberMute) muted = false;
    try { localStorage.setItem(AUDIO_UNLOCK_KEY, '1'); } catch (e) {}
    syncAudioBtn();
  }

  /** Best-effort silent unlock on load if this browser/kiosk unlocked audio before. */
  function tryEagerAudioUnlock() {
    var wasUnlocked = false;
    try { wasUnlocked = localStorage.getItem(AUDIO_UNLOCK_KEY) === '1'; } catch (e) {}
    if (!wasUnlocked) return;
    try {
      ensureAudio();
      if (audioCtx && audioCtx.state === 'running') {
        audioEnabled = true;
        if (!prefs.audio.rememberMute) muted = false;
        syncAudioBtn();
      }
      // If still "suspended", the browser is withholding autoplay for this
      // load; unlockAudioFromAnyGesture() below will catch the next gesture.
    } catch (e) {}
  }

  function sortTickets(list) {
    const mode = prefs.sort || 'newest';
    const arr = (list || []).slice();
    arr.sort(function (a, b) {
      if (mode === 'late_newest') {
        const al = a.late ? 0 : 1;
        const bl = b.late ? 0 : 1;
        if (al !== bl) return al - bl;
      }
      const da = String(a.date || '');
      const db = String(b.date || '');
      if (mode === 'oldest') return da < db ? -1 : (da > db ? 1 : 0);
      return da > db ? -1 : (da < db ? 1 : 0);
    });
    return arr;
  }

  function buildCardHtml(t, isNew) {
    const c = prefs.card;
    const cls = 'tv-card' + (t.late && c.sla ? ' tv-card--late' : '') + (isNew ? ' tv-card--flash' : '');
    let html = '<div class="' + cls + '">';

    const topLeft = [];
    if (c.id !== false) topLeft.push('<span class="tv-card__id ho-tip" data-tip="' + escapeHtml(I18N.card.id) + ': #' + t.id + '">#' + t.id + '</span>');
    if (t.late && c.sla) topLeft.push('<span class="tv-card__sla">' + escapeHtml(I18N.sla) + '</span>');
    const showAge = c.age && t.age;
    if (topLeft.length || showAge) {
      html += '<div class="tv-card__top">';
      html += '<div class="tv-card__top-left">' + (topLeft.length ? topLeft.join(' · ') : '') + '</div>';
      if (showAge) html += '<span class="tv-card__age ho-tip" data-tip="' + escapeHtml(I18N.age || I18N.card.age) + ': ' + escapeHtml(t.age) + '">' +
      ICON_AGE + ' ' + escapeHtml(t.age) + '</span>';
      html += '</div>';
    }

    if (c.title !== false) {
      html += '<div class="tv-card__title ho-tip" data-tip="' + escapeHtml(I18N.card.title) + ': ' + escapeHtml(t.title || '') + '"><span class="tv-card__title-text">' + escapeHtml(t.title || '') + '</span></div>';
    }

    if (c.category && t.category) {
      html += '<div class="tv-card__category ho-tip" data-tip="' + escapeHtml(I18N.card.category) + ': ' + escapeHtml(t.category) + '">' +
        ICON_CATEGORY + ' <span class="tv-card__category-text">' + escapeHtml(t.category) + '</span></div>';
    }

    if (c.group && t.group) {
      html += '<div class="tv-card__group ho-tip" data-tip="' + escapeHtml(I18N.card.group) + ': ' + escapeHtml(t.group) + '">' +
        ICON_GROUP + ' <span class="tv-card__group-text">' + escapeHtml(t.group) + '</span></div>';
    }

    const peopleParts = [];
    if (c.tech && t.tech) {
      peopleParts.push('<span class="tv-card__tech ho-tip" data-tip="' + escapeHtml(I18N.card.tech) + ': ' + escapeHtml(t.tech) + '">' +
        ICON_USER + ' <span class="tv-card__tech-text">' + escapeHtml(t.tech) + '</span></span>');
    }
    if (c.requester && t.requester) {
      peopleParts.push('<span class="tv-card__requester ho-tip" data-tip="' + escapeHtml(I18N.card.requester) + ': ' + escapeHtml(t.requester) + '">' +
        ICON_REQUESTER + ' <span class="tv-card__requester-name">' + escapeHtml(t.requester) + '</span></span>');
    }
    if (peopleParts.length) {
      html += '<div class="tv-card__people">' + peopleParts.join('') + '</div>';
    }

    if (c.observers && t.observers) {
      html += '<div class="tv-card__obs ho-tip" data-tip="' + escapeHtml(I18N.observers) + ': ' + escapeHtml(t.observers) + '">' +
        ICON_OBS + ' <span class="tv-card__obs-text">' + escapeHtml(t.observers) + '</span></div>';
    }

    if (c.date || c.date_mod) {
      html += '<div class="tv-card__dates">';
      if (c.date && t.date_label) html += '<span class="ho-tip" data-tip="' + escapeHtml(I18N.card.date) + ': ' + escapeHtml(t.date_label) + '">' + ICON_CAL + ' ' + escapeHtml(t.date_label) + '</span>';
      if (c.date_mod && t.date_mod_label) html += '<span class="tv-card__date-mod ho-tip" data-tip="' + escapeHtml(I18N.card.date_mod) + ': ' + escapeHtml(t.date_mod_label) + '">' + ICON_REFRESH + ' ' + escapeHtml(t.date_mod_label) + '</span>';
      html += '</div>';
    }

    const showPrio = c.prio !== false;
    const showOpen = c.openBtn && TICKET_BASE;
    const showPreview = c.preview !== false && !!t.description;
    if (showPrio || showOpen || showPreview) {
      html += '<div class="tv-card__foot">';
      if (showPrio) html += '<span class="tv-card__prio ho-tip" data-prio="' + escapeHtml(String(t.priority || 0)) + '" data-tip="' + escapeHtml(I18N.card.prioTip) + '">' + escapeHtml(t.prio || '') + '</span>';
      else html += '<span></span>';
      html += '<span class="tv-card__foot-actions">';
      if (showPreview) {
        html += '<button type="button" class="tv-card__preview ho-tip" data-preview-id="' + escapeHtml(String(t.id)) +
          '" data-preview-title="' + escapeHtml(t.title || '') + '" data-preview-category="' + escapeHtml(t.category || '') +
          '" data-preview-text="' + escapeHtml(t.description) + '" data-tip="' + escapeHtml(I18N.card.preview) + '" aria-label="' + escapeHtml(I18N.card.preview) + '">' +
          ICON_PREVIEW + ' <span class="tv-card__preview-label">' + escapeHtml(I18N.card.preview) + '</span></button>';
      }
      if (showOpen) {
        html += '<a class="tv-card__open ho-tip" href="' + escapeHtml(ticketUrl(t.id)) +
          '" target="_blank" rel="noopener" aria-label="' + escapeHtml(I18N.open) + '" data-tip="' + escapeHtml(I18N.open) + '">' +
          ICON_OPEN + ' <span class="tv-card__open-label">' + escapeHtml(I18N.open) + '</span></a>';
      }
      html += '</span>';
      html += '</div>';
    }

    html += '</div>';
    return html;
  }

  function renderKpis(data) {
    const kpis = data.kpis || {};
    document.querySelectorAll('[data-kpi]').forEach(function (el) {
      const key = el.getAttribute('data-kpi');
      el.classList.toggle('is-hidden', !prefs.kpis[key]);
    });
    document.getElementById('kpi_today').textContent = kpis.today != null ? kpis.today : 0;
    document.getElementById('kpi_week').textContent = kpis.week != null ? kpis.week : 0;
    const monthEl = document.getElementById('kpi_month');
    if (monthEl) monthEl.textContent = kpis.month != null ? kpis.month : 0;
    document.getElementById('kpi_late').textContent = (kpis.late != null ? kpis.late : (data.late || 0));
    document.getElementById('kpi_validation').textContent = kpis.validation_waiting != null ? kpis.validation_waiting : 0;
    document.getElementById('kpi_solution').textContent = kpis.solution_waiting != null ? kpis.solution_waiting : 0;

    const oldest = kpis.oldest || null;
    const oldestEl = document.getElementById('kpi_oldest');
    const titleEl = document.getElementById('kpi_oldest_title');
    const metaEl = document.getElementById('kpi_oldest_meta');
    if (oldest && oldest.id) {
      let val = '#' + oldest.id;
      if (prefs.card.openBtn && TICKET_BASE) {
        oldestEl.innerHTML = '<a href="' + escapeHtml(ticketUrl(oldest.id)) + '" target="_blank" rel="noopener">#' +
          oldest.id + '</a>' + ICON_OPEN;
      } else {
        oldestEl.textContent = val;
      }
      titleEl.textContent = oldest.title || '';
      const bits = [];
      if (oldest.date_label) bits.push(oldest.date_label);
      if (oldest.age) bits.push(oldest.age);
      if (oldest.late) bits.push(I18N.sla);
      if (oldest.tech) bits.push(oldest.tech);
      metaEl.textContent = bits.join(' · ');
    } else {
      oldestEl.textContent = '—';
      titleEl.textContent = '';
      metaEl.textContent = '';
    }
  }

  function renderBoard(data) {
    lastBoard = data;
    const board = document.getElementById('tv_board');
    const queues = data.queues || [];
    renderKpis(data);

    const nextIds = new Set();
    let html = '';
    let visibleCols = 0;
    const limit = Math.max(5, Math.min(80, parseInt(prefs.density.perColumn, 10) || 40));

    queues.forEach(function (q) {
      const key = q.key != null ? String(q.key) : String(q.status);
      let show;
      if (key === 'for_me') {
        show = !!(prefs.views && prefs.views.for_me);
      } else if (key === 'opened_by_me') {
        show = !!(prefs.views && prefs.views.opened_by_me);
      } else if (key === 'observer') {
        show = !!(prefs.views && prefs.views.observer);
      } else if (key.indexOf('group_') === 0) {
        show = !!(prefs.views && prefs.views.by_group);
      } else if (key.indexOf('entity_') === 0) {
        show = !!(prefs.views && prefs.views.by_entity);
      } else {
        show = prefs.columns[key] !== false;
      }
      if (show) visibleCols++;
      const iconKey = key.indexOf('group_') === 0 ? 'by_group' : (key.indexOf('entity_') === 0 ? 'by_entity' : key);
      html += '<article class="tv-col' +
        (key === 'validation' ? ' tv-col--validation' : '') +
        (key === 'solution' || key === 'approval' ? ' tv-col--solution' : '') +
        (show ? '' : ' is-hidden') + '" data-key="' + escapeHtml(key) + '" data-status="' + q.status + '">';
      html += '<header class="tv-col__head"><h2 class="tv-col__title">' + prefIcon(iconKey) +
        '<span>' + escapeHtml(q.label || '') + '</span></h2>';
      html += '<span class="tv-col__count">' + (q.count || 0) + '</span></header>';
      html += '<div class="tv-col__list">';
      const tickets = sortTickets(q.tickets || []).slice(0, limit);
      if (!tickets.length) {
        html += '<div class="tv-empty">' + escapeHtml(I18N.empty) + '</div>';
      } else {
        tickets.forEach(function (t) {
          nextIds.add(String(t.id));
          const isNew = !firstLoad && !prevIds.has(String(t.id));
          html += buildCardHtml(t, isNew);
        });
        if ((q.count || 0) > tickets.length) {
          const moreCount = (q.count || 0) - tickets.length;
          const moreHref = moreLink(key);
          if (moreHref) {
            html += '<a class="tv-empty tv-empty--link" href="' + escapeHtml(moreHref) + '">' + ICON_OPEN + ' <span>+' + moreCount + ' ' + escapeHtml(I18N.more) + '</span></a>';
          } else {
            html += '<div class="tv-empty">+' + moreCount + ' ' + escapeHtml(I18N.more) + '</div>';
          }
        }
      }
      html += '</div></article>';
    });
    board.setAttribute('data-cols', String(Math.max(1, Math.min(12, visibleCols || queues.length))));
    board.innerHTML = html || '<div class="tv-empty">' + escapeHtml(I18N.empty) + '</div>';
    prevIds = nextIds;
    firstLoad = false;
  }

  function prefIcon(key) {
    return PREF_ICONS[key] || '';
  }

  function addCheck(groupEl, id, label, checked, onChange) {
    const row = document.createElement('label');
    row.className = 'tv-prefs__row';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!checked;
    input.addEventListener('change', function () { onChange(input.checked); });
    row.appendChild(input);
    const ic = prefIcon(id);
    if (ic) {
      const wrap = document.createElement('span');
      wrap.innerHTML = ic;
      while (wrap.firstChild) row.appendChild(wrap.firstChild);
    }
    const txt = document.createElement('span');
    txt.textContent = label;
    row.appendChild(txt);
    groupEl.appendChild(row);
  }

  function buildPrefsPanel() {
    const colG = prefsPanel.querySelector('[data-pref-group="columns"]');
    const modeG = prefsPanel.querySelector('[data-pref-group="view_mode"]');
    const viewG = prefsPanel.querySelector('[data-pref-group="views"]');
    const kpiG = prefsPanel.querySelector('[data-pref-group="kpis"]');
    const cardG = prefsPanel.querySelector('[data-pref-group="card"]');
    const sortG = prefsPanel.querySelector('[data-pref-group="sort"]');
    const toastG = prefsPanel.querySelector('[data-pref-group="toasts"]');
    const audioG = prefsPanel.querySelector('[data-pref-group="audio"]');
    const densG = prefsPanel.querySelector('[data-pref-group="density"]');
    [colG, modeG, viewG, kpiG, cardG, sortG, toastG, audioG, densG].forEach(function (g) {
      if (!g) return;
      while (g.children.length > 1) g.removeChild(g.lastChild);
      const h = g.querySelector('h3');
      if (h && !h.querySelector('svg')) {
        const gk = g.getAttribute('data-pref-group');
        const ic = prefIcon(gk === 'view_mode' ? 'views' : gk);
        if (ic) h.innerHTML = ic + ' ' + h.textContent;
      }
    });

    if (!prefs.views) prefs.views = JSON.parse(JSON.stringify(DEFAULT_PREFS.views));
    if (!prefs.view_mode) prefs.view_mode = 'all';

    Object.keys(I18N.col).forEach(function (k) {
      addCheck(colG, k, I18N.col[k], prefs.columns[k], function (v) {
        prefs.columns[k] = v; savePrefs(); if (lastBoard) renderBoard(lastBoard);
      });
    });

    if (modeG && I18N.view_mode) {
      const modeRow = document.createElement('label');
      modeRow.className = 'tv-prefs__row';
      const sel = document.createElement('select');
      ['all', 'for_me', 'opened_by_me', 'my_groups'].forEach(function (k) {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = I18N.view_mode[k] || k;
        if ((prefs.view_mode || 'all') === k) opt.selected = true;
        sel.appendChild(opt);
      });
      sel.addEventListener('change', function () {
        prefs.view_mode = sel.value;
        savePrefs();
        pollBoard();
      });
      modeRow.appendChild(sel);
      modeG.appendChild(modeRow);
    }

    Object.keys(I18N.views).forEach(function (k) {
      addCheck(viewG, k, I18N.views[k], prefs.views[k], function (v) {
        prefs.views[k] = v; savePrefs(); pollBoard();
      });
    });
    Object.keys(I18N.kpi).forEach(function (k) {
      addCheck(kpiG, k, I18N.kpi[k], prefs.kpis[k], function (v) {
        prefs.kpis[k] = v; savePrefs(); if (lastBoard) renderBoard(lastBoard);
      });
    });
    Object.keys(I18N.card).forEach(function (k) {
      addCheck(cardG, k, I18N.card[k], prefs.card[k] !== false, function (v) {
        prefs.card[k] = v; savePrefs(); if (lastBoard) renderBoard(lastBoard);
      });
    });

    if (sortG) {
      const sortRow = document.createElement('label');
      sortRow.className = 'tv-prefs__row';
      const ic = prefIcon('sort');
      if (ic) {
        const wrap = document.createElement('span');
        wrap.innerHTML = ic;
        while (wrap.firstChild) sortRow.appendChild(wrap.firstChild);
      }
      const sel = document.createElement('select');
      ['newest', 'oldest', 'late_newest'].forEach(function (k) {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = I18N.sort[k] || k;
        if ((prefs.sort || 'newest') === k) opt.selected = true;
        sel.appendChild(opt);
      });
      sel.addEventListener('change', function () {
        prefs.sort = sel.value;
        savePrefs();
        if (lastBoard) renderBoard(lastBoard);
      });
      sortRow.appendChild(sel);
      sortG.appendChild(sortRow);
    }

    TOAST_TYPES_ALL.forEach(function (k) {
      addCheck(toastG, k, I18N.toast[k] || k, prefs.toasts[k], function (v) {
        prefs.toasts[k] = v; savePrefs();
      });
    });
    addCheck(audioG, 'audio', I18N.chime, prefs.audio.chime, function (v) {
      prefs.audio.chime = v; savePrefs();
    });
    addCheck(audioG, 'volume_off', I18N.rememberMute, prefs.audio.rememberMute, function (v) {
      prefs.audio.rememberMute = v; savePrefs();
    });

    const densRow = document.createElement('label');
    densRow.className = 'tv-prefs__row';
    const dic = prefIcon('density');
    if (dic) {
      const wrap = document.createElement('span');
      wrap.innerHTML = dic;
      while (wrap.firstChild) densRow.appendChild(wrap.firstChild);
    }
    const densTxt = document.createElement('span');
    densTxt.textContent = I18N.cardsPerCol;
    densRow.appendChild(densTxt);
    const densSel = document.createElement('select');
    [20, 40, 60].forEach(function (n) {
      const opt = document.createElement('option');
      opt.value = String(n);
      opt.textContent = String(n);
      if (n === prefs.density.perColumn) opt.selected = true;
      densSel.appendChild(opt);
    });
    densSel.addEventListener('change', function () {
      prefs.density.perColumn = parseInt(densSel.value, 10) || 40;
      savePrefs();
      if (lastBoard) renderBoard(lastBoard);
    });
    densRow.appendChild(densSel);
    densG.appendChild(densRow);
  }

  function showFail(banner, detail, attempts) {
    banner.style.display = 'block';
    banner.classList.add('tv-banner--diag');
    const lines = [];
    lines.push(I18N.fail);
    if (detail) lines.push(String(detail));
    lines.push('');
    lines.push('page: ' + TV_PAGE);
    lines.push('plugin_web: ' + PLUGIN_WEB);
    lines.push('device: ' + (DEVICE_MODE ? '1' : '0') + ' token: ' + (tvToken ? 'yes' : 'no'));
    lines.push('boardEndpoints: ' + JSON.stringify(boardEndpoints));
    if (attempts && attempts.length) {
      lines.push('');
      lines.push('attempts:');
      attempts.forEach(function (a, i) {
        lines.push(
          '  [' + i + '] ' + a.url +
          ' → status=' + (a.status != null ? a.status : '?') +
          ' err=' + (a.message || '') +
          (a.snippet ? ' body=' + a.snippet : '') +
          (a.detail ? ' detail=' + a.detail : '')
        );
      });
    }
    lines.push('');
    lines.push('Dica: abra com ?tv_debug=1 e copie este bloco.');
    const text = lines.join('\n');
    banner.textContent = '';
    const pre = document.createElement('div');
    pre.textContent = text;
    banner.appendChild(pre);
    const actions = document.createElement('div');
    actions.className = 'tv-banner__actions';
    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.textContent = I18N.copyLog || 'Copiar log';
    copyBtn.addEventListener('click', function () {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          copyBtn.textContent = I18N.copied || 'Copiado';
        }).catch(function () {
          window.prompt('Log TV', text);
        });
      } else {
        window.prompt('Log TV', text);
      }
    });
    actions.appendChild(copyBtn);
    banner.appendChild(actions);
    if (TV_DEBUG) {
      try { console.error('[paineldebordo TV]', text); } catch (e) {}
    }
  }

  async function tryFetch(baseUrl, query) {
    let url = baseUrl;
    if (query) {
      url += (url.indexOf('?') >= 0 ? '&' : '?') + query;
    }
    const headers = {};
    if (tvToken) {
      headers['Authorization'] = 'Bearer ' + tvToken;
      url += (url.indexOf('?') >= 0 ? '&' : '?') + 'tv_token=' + encodeURIComponent(tvToken);
    }
    const res = await fetch(url, { credentials: 'same-origin', headers: headers });
    const ct = (res.headers.get('content-type') || '').toLowerCase();
    const text = await res.text();
    if (!ct.includes('json') && text.trim().charAt(0) !== '{') {
      const err = new Error('HTTP ' + res.status + ' non-JSON');
      err.status = res.status;
      err.url = url;
      err.snippet = text.replace(/\s+/g, ' ').slice(0, 180);
      throw err;
    }
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      const err = new Error('HTTP ' + res.status + ' bad JSON');
      err.status = res.status;
      err.url = url;
      err.snippet = text.replace(/\s+/g, ' ').slice(0, 180);
      throw err;
    }
    return { res: res, data: data, url: url };
  }

  async function pollBoard() {
    const banner = document.getElementById('tv_banner');
    let lastErr = null;
    const attempts = [];
    const order = [];
    for (let i = 0; i < boardEndpoints.length; i++) {
      order.push((boardIdx + i) % boardEndpoints.length);
    }
    const viewQ = [];
    if (prefs.view_mode && prefs.view_mode !== 'all') {
      viewQ.push('view_mode=' + encodeURIComponent(prefs.view_mode));
    }
    if (prefs.views && prefs.views.for_me) viewQ.push('view_for_me=1');
    if (prefs.views && prefs.views.opened_by_me) viewQ.push('view_opened_by_me=1');
    if (prefs.views && prefs.views.observer) viewQ.push('view_observer=1');
    if (prefs.views && prefs.views.by_group) viewQ.push('view_by_group=1');
    if (prefs.views && prefs.views.by_entity) viewQ.push('view_by_entity=1');
    const viewQuery = viewQ.join('&');
    for (const idx of order) {
      const ep = boardEndpoints[idx];
      try {
        const { res, data, url } = await tryFetch(ep, viewQuery);
        boardIdx = idx;
        if (!data.ok) {
          attempts.push({
            url: url,
            status: res.status,
            message: data.error || 'not_ok',
            detail: data.detail || '',
            snippet: (data.warning || '').slice(0, 180)
          });
          if (res.status === 403 && DEVICE_MODE) {
            localStorage.removeItem('pdb_tv_token');
            location.href = 'tv_pair.php';
            return;
          }
          showFail(
            banner,
            (data.warning || data.error || I18N.loadFail) + (data.detail ? (' | ' + data.detail) : ''),
            attempts
          );
          return;
        }
        if (data.warning) {
          banner.classList.remove('tv-banner--diag');
          banner.style.display = 'block';
          banner.textContent = data.warning;
        } else {
          banner.classList.remove('tv-banner--diag');
          banner.style.display = 'none';
          banner.textContent = '';
        }
        renderBoard(data);
        return;
      } catch (e) {
        lastErr = e;
        attempts.push({
          url: e.url || ep,
          status: e.status || null,
          message: e.message || String(e),
          snippet: e.snippet || '',
          detail: ''
        });
      }
    }
    const detail = lastErr
      ? ((lastErr.status || '') + (lastErr.snippet ? ' ' + lastErr.snippet : (lastErr.message || '')))
      : '';
    showFail(banner, String(detail).trim(), attempts);
  }

  async function pollEvents() {
    let lastErr = null;
    const order = [];
    for (let i = 0; i < eventEndpoints.length; i++) {
      order.push((eventIdx + i) % eventEndpoints.length);
    }
    const q = since ? ('since=' + encodeURIComponent(since)) : '';
    for (const idx of order) {
      try {
        const { res, data } = await tryFetch(eventEndpoints[idx], q);
        eventIdx = idx;
        if (!data.ok) {
          if (res.status === 403 && DEVICE_MODE) {
            localStorage.removeItem('pdb_tv_token');
            location.href = 'tv_pair.php';
          }
          return;
        }
        if (data.server_ts) {
          since = data.server_ts;
        }
        const events = data.events || [];
        events.forEach(function (ev) {
          if (TOAST_TYPES_ALL.indexOf(ev.type) < 0) return;
          const key = eventKey(ev);
          if (seenKeys.has(key)) return;
          seenKeys.add(key);
          if (!q) return;
          pushToast(ev);
        });
        if (!q && data.server_ts) {
          events.forEach(function (ev) { seenKeys.add(eventKey(ev)); });
        }
        return;
      } catch (e) {
        lastErr = e;
      }
    }
  }

  prefsBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    const open = !prefsPanel.classList.contains('is-open');
    prefsPanel.classList.toggle('is-open', open);
    prefsBtn.classList.toggle('is-on', open);
    prefsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function (e) {
    if (!prefsPanel.classList.contains('is-open')) return;
    if (prefsPanel.contains(e.target) || prefsBtn.contains(e.target)) return;
    prefsPanel.classList.remove('is-open');
    prefsBtn.classList.remove('is-on');
    prefsBtn.setAttribute('aria-expanded', 'false');
  });

  audioBtn.addEventListener('click', function () {
    toggleAudioFromUser();
  });
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      toggleThemeFromUser();
    });
  }
  document.addEventListener('keydown', function (ev) {
    const tag = (ev.target && ev.target.tagName) ? ev.target.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (ev.target && ev.target.isContentEditable)) return;
    if (!(ev.ctrlKey || ev.metaKey)) return;
    if (ev.key === 'm' || ev.key === 'M') {
      ev.preventDefault();
      toggleAudioFromUser();
      return;
    }
    if (ev.code === 'Space' || ev.key === ' ' || ev.key === 'Spacebar') {
      ev.preventDefault();
      toggleThemeFromUser();
    }
  });
  document.getElementById('tv_fs_btn').addEventListener('click', function () {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {});
    else document.exitFullscreen();
  });

  (function () {
    var modal = document.getElementById('tv_preview_modal');
    var metaEl = document.getElementById('tv_preview_meta');
    var titleEl = document.getElementById('tv_preview_title');
    var bodyEl = document.getElementById('tv_preview_body');
    var openLink = document.getElementById('tv_preview_open');
    if (!modal) return;
    function closePreview() { modal.hidden = true; }
    function openPreview(btn) {
      var id = btn.getAttribute('data-preview-id') || '';
      var cat = btn.getAttribute('data-preview-category') || '';
      metaEl.textContent = '#' + id + (cat ? ' · ' + cat : '');
      titleEl.textContent = btn.getAttribute('data-preview-title') || '';
      bodyEl.innerHTML = btn.getAttribute('data-preview-text') || '';
      openLink.href = ticketUrl(id);
      modal.hidden = false;
    }
    document.getElementById('tv_board').addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-preview-id]');
      if (btn) openPreview(btn);
    });
    modal.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-preview-close]')) closePreview();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !modal.hidden) closePreview();
    });
  })();

  // Unlock audio on the first interaction ANYWHERE on the page (click, tap,
  // key), not only the small speaker icon — unattended TVs/kiosks otherwise
  // never unlock the browser's AudioContext and toasts stay silent forever.
  ['click', 'keydown', 'touchstart'].forEach(function (evt) {
    document.addEventListener(evt, unlockAudioFromAnyGesture, { once: true, passive: true, capture: true });
  });

  async function exitDeviceMode() {
    const token = tvToken || localStorage.getItem('pdb_tv_token') || '';
    localStorage.removeItem('pdb_tv_token');
    tvToken = '';
    if (token) {
      try {
        const body = new FormData();
        body.append('tv_token', token);
        await fetch('ajax/tv_unpair.php', {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { 'Authorization': 'Bearer ' + token }
        });
      } catch (e) { /* still leave pairing screen */ }
    }
    location.href = 'tv_pair.php';
  }

  const exitBtn = document.getElementById('tv_exit_btn');
  if (exitBtn) {
    exitBtn.addEventListener('click', function () {
      exitDeviceMode();
    });
  }

  buildPrefsPanel();
  applyTheme(tvTheme, false);
  tryEagerAudioUnlock();
  syncAudioBtn();
  // Shortcut tips every load (3s)
  setTimeout(function () { pushTipToast(I18N.muteTip, undefined, 3000); }, 600);
  setTimeout(function () { pushTipToast(I18N.themeTip, undefined, 3000); }, 1400);
  pollBoard();
  pollEvents();
  setInterval(pollBoard, BOARD_MS);
  setInterval(pollEvents, EVENTS_MS);
})();
</script>
<?php echo '<script>' . plugin_paineldebordo_tip_bubble_js() . '</script>'; ?>
</body>
</html>
