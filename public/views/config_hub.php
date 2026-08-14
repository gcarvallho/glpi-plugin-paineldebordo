<?php
/**
 * Configuration hub — Super-Admin only (shell already gated).
 * Sections: Scope, Personalization, Map, TV, Access, System.
 * POST is handled in shell.php (PRG) via config_post.inc.php.
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/branding.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');

global $DB, $CFG_GLPI;

$msg = '';
$msg_ok = true;
if (!empty($_SESSION['hubops_config_flash']) && is_array($_SESSION['hubops_config_flash'])) {
    $msg = (string) ($_SESSION['hubops_config_flash']['msg'] ?? '');
    $msg_ok = !empty($_SESSION['hubops_config_flash']['ok']);
    unset($_SESSION['hubops_config_flash']);
}

$entity = plugin_paineldebordo_getConfigValue('entity', '');
$entities = plugin_paineldebordo_entity_options();
$selected = array_filter(array_map('intval', explode(',', $entity)));
$devices = plugin_paineldebordo_tv_pair_list_all(50);
$csrf = plugin_paineldebordo_csrf_field();
$root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
$plugin_web = function_exists('plugin_paineldebordo_asset_base')
    ? plugin_paineldebordo_asset_base()
    : (($GLOBALS['HO_LAYOUT']['plugin_web'] ?? '') ?: ($root . '/plugins/paineldebordo/public'));
$plugin_web = rtrim((string) $plugin_web, '/');
$tv_pair_url = function_exists('plugin_paineldebordo_absolute_url')
    ? plugin_paineldebordo_absolute_url('tv_pair.php')
    : ($plugin_web . '/tv_pair.php');
$tv_pair_url_path = function_exists('plugin_paineldebordo_asset_url')
    ? plugin_paineldebordo_asset_url('tv_pair.php')
    : ($plugin_web . '/tv_pair.php');
$tv_pair_debug = !empty($_GET['tv_pair_debug']);
$url_debug = ($tv_pair_debug && function_exists('plugin_paineldebordo_url_debug'))
    ? plugin_paineldebordo_url_debug()
    : null;
$can_delete_tv = function_exists('plugin_paineldebordo_isSuperAdmin') && plugin_paineldebordo_isSuperAdmin();
$tv_ttl = plugin_paineldebordo_tv_pair_ttl_seconds();
$brand = plugin_paineldebordo_branding_get();
$version = '2.29.8';
if (function_exists('plugin_version_paineldebordo')) {
    $v = plugin_version_paineldebordo();
    $version = (string) ($v['version'] ?? $version);
}

$map_by_ent = [];
if ($DB->TableExists('glpi_plugin_paineldebordo_map')) {
    $mres = $DB->doQuery('SELECT entities_id, lat, lng, location FROM glpi_plugin_paineldebordo_map');
    if ($mres) {
        while ($row = $DB->fetchAssoc($mres)) {
            $map_by_ent[(int) $row['entities_id']] = $row;
        }
    }
}

$tables = [
    'glpi_plugin_paineldebordo_config',
    'glpi_plugin_paineldebordo_map',
    'glpi_plugin_paineldebordo_tv_devices',
    'glpi_plugin_paineldebordo_count',
];

$profile_url = $root . '/front/profile.php';
?>
<?php if ($msg) { ?>
<div class="ho-msg <?php echo $msg_ok ? 'ho-msg--ok' : 'ho-msg--err'; ?>" role="status">
  <?php echo htmlspecialchars($msg); ?>
</div>
<?php } ?>

<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('by_entity'); ?>
    <span><?php echo __('Scope', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <form method="post" action="shell.php?page=config">
      <?php echo $csrf; ?>
      <input type="hidden" name="scope_save" value="1">
      <div class="ho-field">
        <span class="ho-field__label"><?php echo __('Default entities filter', 'paineldebordo'); ?></span>
        <div class="ho-check-list" role="group" aria-label="<?php echo htmlspecialchars(__('Default entities filter', 'paineldebordo')); ?>">
          <?php if (!$entities) { ?>
            <p class="ho-empty"><?php echo __('No entities available', 'paineldebordo'); ?></p>
          <?php } else { foreach ($entities as $id => $name) { ?>
            <label class="ho-check-list__row">
              <input type="checkbox" name="entity[]" value="<?php echo (int) $id; ?>"
                <?php echo in_array((int) $id, $selected, true) ? 'checked' : ''; ?>>
              <span><?php echo htmlspecialchars($name); ?></span>
            </label>
          <?php } } ?>
        </div>
        <p class="ho-check-list__hint"><?php echo __('Leave empty to use active GLPI entities.', 'paineldebordo'); ?></p>
      </div>
      <div class="ho-form-actions">
        <button type="submit" class="btn btn-primary">
          <?php echo plugin_paineldebordo_icon('solved'); ?>
          <span><?php echo __('Save'); ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('palette'); ?>
    <span><?php echo __('Personalization', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <?php
      $logo_is_upload = (bool) preg_match('#^/?img/brand/#', (string) $brand['logo_url']);
      $logo_collapsed_is_upload = (bool) preg_match('#^/?img/brand/#', (string) $brand['logo_collapsed_url']);
    ?>
    <form method="post" action="shell.php?page=config" enctype="multipart/form-data">
      <?php echo $csrf; ?>
      <input type="hidden" name="branding_save" value="1">
      <div class="ho-form-row" style="flex-wrap:wrap;gap:1rem;">
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Primary / sidebar', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_primary" value="<?php echo htmlspecialchars($brand['primary']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Accent', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_accent" value="<?php echo htmlspecialchars($brand['accent']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Favicon', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_favicon" value="<?php echo htmlspecialchars($brand['favicon'] ?? $brand['accent']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Background', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_bg" value="<?php echo htmlspecialchars($brand['bg']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Surface', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_surface" value="<?php echo htmlspecialchars($brand['surface']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Text', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_text" value="<?php echo htmlspecialchars($brand['text']); ?>">
        </label>
      </div>
      <p class="ho-field__label" style="margin:0.75rem 0 0.35rem;display:flex;align-items:center;gap:0.35rem;">
        <?php echo plugin_paineldebordo_icon('moon'); ?>
        <span><?php echo __('Dark mode', 'paineldebordo'); ?></span>
      </p>
      <div class="ho-form-row" style="flex-wrap:wrap;gap:1rem;">
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Primary / sidebar', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_primary_dark" value="<?php echo htmlspecialchars($brand['primary_dark']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Background', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_bg_dark" value="<?php echo htmlspecialchars($brand['bg_dark']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Surface', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_surface_dark" value="<?php echo htmlspecialchars($brand['surface_dark']); ?>">
        </label>
        <label class="ho-field">
          <span class="ho-field__label"><?php echo __('Text', 'paineldebordo'); ?></span>
          <input class="form-control form-control-color" type="color" name="brand_text_dark" value="<?php echo htmlspecialchars($brand['text_dark']); ?>">
        </label>
      </div>
      <div class="ho-form-row" style="flex-wrap:wrap;gap:1rem;margin-top:1rem;">
        <label class="ho-field" style="flex:1 1 14rem;">
          <span class="ho-field__label"><?php echo __('Eyebrow', 'paineldebordo'); ?></span>
          <input class="form-control" name="brand_eyebrow" maxlength="80" value="<?php echo htmlspecialchars($brand['eyebrow']); ?>">
        </label>
        <label class="ho-field" style="flex:1 1 14rem;">
          <span class="ho-field__label"><?php echo __('Product name', 'paineldebordo'); ?></span>
          <input class="form-control" name="brand_product_name" maxlength="80" value="<?php echo htmlspecialchars($brand['product_name']); ?>">
        </label>
      </div>
      <div class="ho-form-row" style="flex-wrap:wrap;gap:1rem;margin-top:1rem;align-items:flex-end;">
        <div class="ho-field" style="flex:0 0 auto;">
          <span class="ho-field__label"><?php echo __('Default logos (bundled)', 'paineldebordo'); ?></span>
          <div class="ho-copy-row" style="gap:1rem;align-items:center;">
            <span class="ho-brand__logo" style="width:140px;height:44px;background:url(<?php echo htmlspecialchars($brand['_logo']); ?>) center/contain no-repeat;display:inline-block;"></span>
            <span class="ho-brand__logo" style="width:40px;height:40px;background:url(<?php echo htmlspecialchars($brand['_logo_collapsed']); ?>) center/contain no-repeat;display:inline-block;"></span>
          </div>
        </div>
        <div class="ho-field ho-logo-source" style="flex:1 1 16rem;" data-logo-slot="expanded">
          <span class="ho-field__label"><?php echo __('Custom logo (expanded)', 'paineldebordo'); ?></span>
          <label class="ho-filter-label" style="margin-bottom:0.35rem;cursor:pointer;">
            <span class="ho-switch ho-logo-source__switch<?php echo $logo_is_upload ? ' is-on' : ''; ?>" aria-hidden="true"><span class="ho-switch__thumb"></span></span>
            <input type="checkbox" class="ho-logo-source__check" <?php echo $logo_is_upload ? 'checked' : ''; ?>
                   style="position:absolute;opacity:0;width:1px;height:1px;">
            <span class="ho-logo-source__label"><?php echo $logo_is_upload ? __('File', 'paineldebordo') : __('URL', 'paineldebordo'); ?></span>
          </label>
          <input type="hidden" name="brand_logo_source" class="ho-logo-source__hidden" value="<?php echo $logo_is_upload ? 'file' : 'url'; ?>">
          <input class="form-control ho-logo-source__url" name="brand_logo_url" placeholder="https://…"
                 value="<?php echo htmlspecialchars($brand['logo_url']); ?>"
                 <?php echo $logo_is_upload ? 'hidden' : ''; ?>>
          <input class="form-control ho-logo-source__file" type="file" name="brand_logo_file"
                 accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
                 <?php echo $logo_is_upload ? '' : 'hidden'; ?>>
        </div>
        <div class="ho-field ho-logo-source" style="flex:1 1 16rem;" data-logo-slot="collapsed">
          <span class="ho-field__label"><?php echo __('Custom logo (collapsed)', 'paineldebordo'); ?></span>
          <label class="ho-filter-label" style="margin-bottom:0.35rem;cursor:pointer;">
            <span class="ho-switch ho-logo-source__switch<?php echo $logo_collapsed_is_upload ? ' is-on' : ''; ?>" aria-hidden="true"><span class="ho-switch__thumb"></span></span>
            <input type="checkbox" class="ho-logo-source__check" <?php echo $logo_collapsed_is_upload ? 'checked' : ''; ?>
                   style="position:absolute;opacity:0;width:1px;height:1px;">
            <span class="ho-logo-source__label"><?php echo $logo_collapsed_is_upload ? __('File', 'paineldebordo') : __('URL', 'paineldebordo'); ?></span>
          </label>
          <input type="hidden" name="brand_logo_collapsed_source" class="ho-logo-source__hidden" value="<?php echo $logo_collapsed_is_upload ? 'file' : 'url'; ?>">
          <input class="form-control ho-logo-source__url" name="brand_logo_collapsed_url" placeholder="https://…"
                 value="<?php echo htmlspecialchars($brand['logo_collapsed_url']); ?>"
                 <?php echo $logo_collapsed_is_upload ? 'hidden' : ''; ?>>
          <input class="form-control ho-logo-source__file" type="file" name="brand_logo_collapsed_file"
                 accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
                 <?php echo $logo_collapsed_is_upload ? '' : 'hidden'; ?>>
        </div>
      </div>
      <p class="ho-check-list__hint">
        <?php echo __('Toggle URL/File per logo. Uploads accept SVG/PNG/JPG/WEBP up to 1 MB. Leave empty to use the Inovare files shipped with the plugin.', 'paineldebordo'); ?>
      </p>
      <div class="ho-form-actions" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <button type="submit" class="btn btn-primary">
          <?php echo plugin_paineldebordo_icon('solved'); ?>
          <span><?php echo __('Save'); ?></span>
        </button>
        <button type="submit" form="ho_branding_reset" class="btn btn-outline-secondary">
          <?php echo plugin_paineldebordo_icon('refresh'); ?>
          <span><?php echo __('Restore Inovare defaults', 'paineldebordo'); ?></span>
        </button>
      </div>
    </form>
    <form method="post" action="shell.php?page=config" id="ho_branding_reset" hidden
          data-confirm="<?php echo htmlspecialchars(__('Restore Inovare default branding?', 'paineldebordo')); ?>"
          data-confirm-title="<?php echo htmlspecialchars(__('Restore Inovare defaults', 'paineldebordo')); ?>">
      <?php echo $csrf; ?>
      <input type="hidden" name="branding_reset" value="1">
    </form>
    <script>
    (function () {
      document.querySelectorAll('.ho-logo-source').forEach(function (wrap) {
        var check = wrap.querySelector('.ho-logo-source__check');
        var sw = wrap.querySelector('.ho-logo-source__switch');
        var hidden = wrap.querySelector('.ho-logo-source__hidden');
        var urlInput = wrap.querySelector('.ho-logo-source__url');
        var fileInput = wrap.querySelector('.ho-logo-source__file');
        var label = wrap.querySelector('.ho-logo-source__label');
        var urlLabel = <?php echo json_encode(__('URL', 'paineldebordo')); ?>;
        var fileLabel = <?php echo json_encode(__('File', 'paineldebordo')); ?>;
        if (!check || !hidden || !urlInput || !fileInput) return;
        check.addEventListener('change', function () {
          var isFile = check.checked;
          hidden.value = isFile ? 'file' : 'url';
          if (sw) sw.classList.toggle('is-on', isFile);
          if (label) label.textContent = isFile ? fileLabel : urlLabel;
          urlInput.hidden = isFile;
          fileInput.hidden = !isFile;
        });
      });
    })();
    </script>
  </div>
</div>

<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('map'); ?>
    <span><?php echo __('Map', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-vcenter card-table" id="ho_map_table">
        <thead>
          <tr>
            <th><?php echo __('Entity'); ?></th>
            <th><?php echo __('Latitude'); ?></th>
            <th><?php echo __('Longitude'); ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($entities as $id => $name) {
            $coords = $map_by_ent[$id] ?? null;
            $lat = $coords['lat'] ?? '';
            $lng = $coords['lng'] ?? '';
            ?>
          <tr data-entity="<?php echo (int) $id; ?>">
            <td><?php echo htmlspecialchars($name); ?></td>
            <td><input class="form-control form-control-sm ho-map-lat ho-map-coords" type="text" value="<?php echo htmlspecialchars((string) $lat); ?>"></td>
            <td><input class="form-control form-control-sm ho-map-lng ho-map-coords" type="text" value="<?php echo htmlspecialchars((string) $lng); ?>"></td>
            <td>
              <div class="ho-actions-inline">
                <button type="button" class="btn btn-sm btn-primary ho-map-save">
                  <?php echo plugin_paineldebordo_icon('solved'); ?>
                  <span><?php echo __('Save'); ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary ho-map-clear">
                  <?php echo plugin_paineldebordo_icon('trash'); ?>
                  <span><?php echo __('Clear', 'paineldebordo'); ?></span>
                </button>
              </div>
            </td>
          </tr>
        <?php } ?>
        <?php if (!$entities) { ?>
          <tr><td colspan="4" class="ho-empty"><?php echo __('No entities available', 'paineldebordo'); ?></td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <p id="ho_map_msg" class="ho-msg ho-msg--info" hidden></p>
  </div>
</div>

<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('tv'); ?>
    <span><?php echo __('TV devices', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <div class="ho-tv-setup">
      <div class="ho-field ho-tv-setup__link">
        <span class="ho-field__label"><?php echo __('TV pairing link', 'paineldebordo'); ?></span>
        <div class="ho-copy-row">
          <input class="form-control" id="ho_tv_pair_url" type="text" readonly
                 value="<?php echo htmlspecialchars($tv_pair_url); ?>"
                 aria-label="<?php echo htmlspecialchars(__('TV pairing link', 'paineldebordo')); ?>">
          <button type="button" class="btn btn-outline-secondary" id="ho_tv_copy_link">
            <?php echo plugin_paineldebordo_icon('copy'); ?>
            <span><?php echo __('Copy link', 'paineldebordo'); ?></span>
          </button>
          <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($tv_pair_url); ?>" target="_blank" rel="noopener">
            <?php echo plugin_paineldebordo_icon('external_link'); ?>
            <span><?php echo __('Open link', 'paineldebordo'); ?></span>
          </a>
        </div>
        <p class="ho-check-list__hint"><?php echo __('Open this URL on the display (no login). Authorize from phone/PC with the code or QR.', 'paineldebordo'); ?></p>
        <?php if ($url_debug) { ?>
        <pre class="ho-tv-debug" id="ho_tv_url_debug"><?php echo htmlspecialchars(json_encode($url_debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
        <p class="ho-check-list__hint"><?php echo htmlspecialchars(__('Path (relative to site):', 'paineldebordo') . ' ' . $tv_pair_url_path); ?></p>
        <?php } else { ?>
        <p class="ho-check-list__hint">
          <a href="shell.php?page=config&amp;tv_pair_debug=1"><?php echo __('Show TV URL debug', 'paineldebordo'); ?></a>
        </p>
        <?php } ?>
      </div>

      <div class="ho-form-row ho-tv-setup__pair">
        <label class="ho-field" style="flex:1 1 12rem;">
          <span class="ho-field__label"><?php echo __('Pairing code', 'paineldebordo'); ?></span>
          <input class="form-control" id="tv_pair_code_input" maxlength="12" placeholder="AB12-CD34" autocomplete="off">
        </label>
        <button type="button" class="btn btn-primary" id="tv_pair_link_btn">
          <?php echo plugin_paineldebordo_icon('solved'); ?>
          <span><?php echo __('Link TV', 'paineldebordo'); ?></span>
        </button>
        <a class="btn btn-outline-secondary" href="tv.php">
          <?php echo plugin_paineldebordo_icon('eye'); ?>
          <span><?php echo __('Open logged-in TV', 'paineldebordo'); ?></span>
        </a>
      </div>

      <form method="post" action="shell.php?page=config" class="ho-form-row ho-tv-setup__ttl">
        <?php echo $csrf; ?>
        <input type="hidden" name="tv_ttl_save" value="1">
        <label class="ho-field" style="flex:0 1 14rem;">
          <span class="ho-field__label"><?php echo __('Pairing code lifetime', 'paineldebordo'); ?></span>
          <select class="ho-select form-control" name="tv_pair_ttl">
            <option value="180" <?php echo $tv_ttl === 180 ? 'selected' : ''; ?>><?php echo __('3 minutes', 'paineldebordo'); ?></option>
            <option value="300" <?php echo $tv_ttl === 300 ? 'selected' : ''; ?>><?php echo __('5 minutes', 'paineldebordo'); ?></option>
            <option value="600" <?php echo $tv_ttl === 600 ? 'selected' : ''; ?>><?php echo __('10 minutes', 'paineldebordo'); ?></option>
          </select>
        </label>
        <button type="submit" class="btn btn-outline-secondary">
          <?php echo plugin_paineldebordo_icon('solved'); ?>
          <span><?php echo __('Save'); ?></span>
        </button>
      </form>

      <p id="tv_pair_ajax_msg" class="ho-msg ho-msg--info" hidden></p>
      <p id="ho_tv_copy_msg" class="ho-msg ho-msg--ok" hidden></p>
    </div>

    <form method="post" action="shell.php?page=config" id="tv_pair_fallback" hidden>
      <?php echo $csrf; ?>
      <input type="hidden" name="tv_pair_code" id="tv_pair_code_hidden" value="">
    </form>

    <?php if ($devices) { ?>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead><tr>
          <th><?php echo __('Nickname', 'paineldebordo'); ?></th>
          <th><?php echo __('Code', 'paineldebordo'); ?></th>
          <th><?php echo __('Device', 'paineldebordo'); ?></th>
          <th><?php echo __('IP / timezone', 'paineldebordo'); ?></th>
          <th><?php echo __('User'); ?></th>
          <th><?php echo __('Status'); ?></th>
          <th><?php echo __('Linked at', 'paineldebordo'); ?></th>
          <th class="w-1"><?php echo __('Actions', 'paineldebordo'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($devices as $d) {
            $st = (string) ($d['status'] ?? '');
            $st_class = 'ho-status';
            if ($st === 'linked') {
                $st_class .= ' ho-status--ok';
            } elseif ($st === 'pending') {
                $st_class .= ' ho-status--warn';
            } elseif ($st === 'revoked' || $st === 'expired') {
                $st_class .= ' ho-status--muted';
            }
            $nick = trim((string) ($d['nickname'] ?? ''));
            $dev = trim((string) ($d['device_label'] ?? ''));
            $ip = trim((string) ($d['remote_ip'] ?? ''));
            $tz = trim((string) ($d['timezone'] ?? ''));
            $geo = trim($ip . ($ip !== '' && $tz !== '' ? ' · ' : '') . $tz);
            ?>
          <tr>
            <td>
              <form method="post" action="shell.php?page=config" class="ho-nick-form">
                <?php echo $csrf; ?>
                <input type="hidden" name="tv_nickname_id" value="<?php echo (int) $d['id']; ?>">
                <div class="ho-copy-row" style="flex-wrap:nowrap;gap:0.3rem;">
                  <input class="form-control form-control-sm" name="tv_nickname" maxlength="80"
                         value="<?php echo htmlspecialchars($nick); ?>"
                         placeholder="<?php echo htmlspecialchars($dev !== '' ? $dev : __('Nickname', 'paineldebordo')); ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary ho-tip" data-tip="<?php echo htmlspecialchars(__('Save')); ?>">
                    <?php echo plugin_paineldebordo_icon('solved'); ?>
                  </button>
                </div>
              </form>
            </td>
            <td><code class="ho-code"><?php echo htmlspecialchars((string) $d['code']); ?></code></td>
            <td><?php echo htmlspecialchars($dev !== '' ? $dev : '—'); ?></td>
            <td><span class="ho-check-list__hint" style="margin:0;"><?php echo htmlspecialchars($geo !== '' ? $geo : '—'); ?></span></td>
            <td><?php echo htmlspecialchars((string) ($d['user_name'] ?? ('#' . ($d['users_id'] ?? '—')))); ?></td>
            <td><span class="<?php echo $st_class; ?>"><?php echo htmlspecialchars(plugin_paineldebordo_tv_pair_status_label($st)); ?></span></td>
            <td><?php echo htmlspecialchars((string) ($d['linked_at'] ?? '—')); ?></td>
            <td>
              <div class="ho-actions-inline">
                <?php if ($st === 'linked') { ?>
                <form method="post" action="shell.php?page=config" class="ho-actions-inline">
                  <?php echo $csrf; ?>
                  <input type="hidden" name="tv_revoke_id" value="<?php echo (int) $d['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <?php echo plugin_paineldebordo_icon('ban'); ?>
                    <span><?php echo __('Revoke', 'paineldebordo'); ?></span>
                  </button>
                </form>
                <?php } ?>
                <?php if ($can_delete_tv) { ?>
                <form method="post" action="shell.php?page=config" class="ho-actions-inline"
                      data-confirm="<?php echo htmlspecialchars(__('Delete this device record permanently?', 'paineldebordo')); ?>"
                      data-confirm-title="<?php echo htmlspecialchars(__('Delete', 'paineldebordo')); ?>"
                      data-confirm-ok="<?php echo htmlspecialchars(__('Delete', 'paineldebordo')); ?>">
                  <?php echo $csrf; ?>
                  <input type="hidden" name="tv_delete_id" value="<?php echo (int) $d['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <?php echo plugin_paineldebordo_icon('trash'); ?>
                    <span><?php echo __('Delete', 'paineldebordo'); ?></span>
                  </button>
                </form>
                <?php } ?>
              </div>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php if ($can_delete_tv) { ?>
      <p class="ho-check-list__hint"><?php echo __('Revoke ends access; Delete removes the history row.', 'paineldebordo'); ?></p>
    <?php } ?>
    <?php } else { ?>
      <p class="ho-empty"><?php echo __('No TV devices yet', 'paineldebordo'); ?></p>
    <?php } ?>
  </div>
</div>

<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('users'); ?>
    <span><?php echo __('Access', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <div class="ho-catalog" style="margin-bottom:1rem;">
      <div class="card ho-tile" style="margin:0;">
        <div class="card-body">
          <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon('list'); ?></span>
          <p class="ho-tile__title"><?php echo __('Panel access', 'paineldebordo'); ?></p>
          <p class="ho-check-list__hint"><?php echo __('Enter the app (Overview and TV mode).', 'paineldebordo'); ?></p>
        </div>
      </div>
      <div class="card ho-tile" style="margin:0;">
        <div class="card-body">
          <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon('by_group'); ?></span>
          <p class="ho-tile__title"><?php echo __('Wide group vision', 'paineldebordo'); ?></p>
          <p class="ho-check-list__hint"><?php echo __('See all groups in ticket filters and TV (not only your groups).', 'paineldebordo'); ?></p>
        </div>
      </div>
      <div class="card ho-tile" style="margin:0;">
        <div class="card-body">
          <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon('opened'); ?></span>
          <p class="ho-tile__title"><?php echo __('Tickets', 'paineldebordo'); ?></p>
          <p class="ho-check-list__hint"><?php echo __('Access the Tickets module.', 'paineldebordo'); ?></p>
        </div>
      </div>
      <div class="card ho-tile" style="margin:0;">
        <div class="card-body">
          <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon('charts'); ?></span>
          <p class="ho-tile__title"><?php echo __('Analysis', 'paineldebordo'); ?></p>
          <p class="ho-check-list__hint"><?php echo __('Read: charts, reports, BI. Update: save BI layout and Overview mural.', 'paineldebordo'); ?></p>
        </div>
      </div>
      <div class="card ho-tile" style="margin:0;">
        <div class="card-body">
          <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon('computer'); ?></span>
          <p class="ho-tile__title"><?php echo __('Resources', 'paineldebordo'); ?></p>
          <p class="ho-check-list__hint"><?php echo __('Read: map and assets. Update: edit map coordinates.', 'paineldebordo'); ?></p>
        </div>
      </div>
      <div class="card ho-tile" style="margin:0;">
        <div class="card-body">
          <span class="ho-tile__icon"><?php echo plugin_paineldebordo_icon('cog'); ?></span>
          <p class="ho-tile__title"><?php echo __('Admin', 'paineldebordo'); ?></p>
          <p class="ho-check-list__hint"><?php echo __('Only Super-Admin (or GLPI config Update) opens this Configuration hub.', 'paineldebordo'); ?></p>
        </div>
      </div>
    </div>
    <a class="btn btn-primary" href="<?php echo htmlspecialchars($profile_url); ?>" target="_blank" rel="noopener">
      <?php echo plugin_paineldebordo_icon('external_link'); ?>
      <span><?php echo __('Open GLPI Profiles', 'paineldebordo'); ?></span>
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('cog'); ?>
    <span><?php echo __('System', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <p class="ho-field__label"><?php echo __('Version'); ?></p>
    <p style="margin:0 0 0.75rem;font-weight:700;"><?php echo htmlspecialchars($version); ?></p>
    <p class="ho-field__label"><?php echo __('Authors', 'paineldebordo'); ?></p>
    <p style="margin:0 0 0.75rem;">
      <a href="https://wa.me/5591985390491" target="_blank" rel="noopener">gcarvallho.dev</a>
      /
      <a href="https://inovareempreendimentos.com.br" target="_blank" rel="noopener">Inovare</a>
      / Stevenes Donato
    </p>
    <ul class="ho-check-list__hint" style="margin:0 0 1rem;padding-left:1.25rem;">
      <?php foreach ($tables as $t) {
          $ok = $DB->TableExists($t);
          echo '<li>' . htmlspecialchars($t) . ' — ' . ($ok ? __('OK') : __('Missing', 'paineldebordo')) . '</li>';
      } ?>
    </ul>
    <form method="post" action="shell.php?page=config"
          data-confirm="<?php echo htmlspecialchars(__('Purge orphan legacy keys for your user?', 'paineldebordo')); ?>"
          data-confirm-title="<?php echo htmlspecialchars(__('Purge orphan config keys', 'paineldebordo')); ?>">
      <?php echo $csrf; ?>
      <button type="submit" name="purge_orphan_keys" value="1" class="btn btn-outline-secondary">
        <?php echo plugin_paineldebordo_icon('trash'); ?>
        <span><?php echo __('Purge orphan config keys', 'paineldebordo'); ?></span>
      </button>
    </form>
  </div>
</div>

<div class="ho-ds-modal" id="ho_cfg_modal" hidden>
  <div class="ho-ds-modal__backdrop" data-cfg-modal-cancel></div>
  <div class="ho-ds-modal__card" role="dialog" aria-modal="true" aria-labelledby="ho_cfg_modal_title">
    <h3 id="ho_cfg_modal_title" class="ho-ds-modal__title"><?php echo __('Confirm', 'paineldebordo'); ?></h3>
    <p class="ho-ds-modal__body" id="ho_cfg_modal_body"></p>
    <div class="ho-ds-modal__actions">
      <button type="button" class="btn btn-outline-secondary" data-cfg-modal-cancel>
        <?php echo plugin_paineldebordo_icon('ban'); ?>
        <span><?php echo __('Cancel', 'paineldebordo'); ?></span>
      </button>
      <button type="button" class="btn btn-primary" id="ho_cfg_modal_ok">
        <?php echo plugin_paineldebordo_icon('solved'); ?>
        <span><?php echo __('Confirm', 'paineldebordo'); ?></span>
      </button>
    </div>
  </div>
</div>

<script>
(function(){
  var csrf = <?php echo json_encode(plugin_paineldebordo_csrf_token()); ?>;
  var mapEndpoint = <?php echo json_encode(rtrim($plugin_web, '/') . '/ajax/map_coord.php'); ?>;
  var pairEndpoint = <?php echo json_encode(rtrim($plugin_web, '/') . '/ajax/tv_pair_approve.php'); ?>;
  var mapMsg = document.getElementById('ho_map_msg');

  function showMapMsg(ok, text) {
    if (!mapMsg) return;
    mapMsg.hidden = false;
    mapMsg.className = 'ho-msg ' + (ok ? 'ho-msg--ok' : 'ho-msg--err');
    mapMsg.textContent = text;
  }

  document.querySelectorAll('#ho_map_table tbody tr[data-entity]').forEach(function(tr){
    var eid = tr.getAttribute('data-entity');
    var latEl = tr.querySelector('.ho-map-lat');
    var lngEl = tr.querySelector('.ho-map-lng');
    var saveBtn = tr.querySelector('.ho-map-save');
    var clearBtn = tr.querySelector('.ho-map-clear');
    async function postMap(extra) {
      var body = new FormData();
      body.append('entities_id', eid);
      if (csrf) body.append('_glpi_csrf_token', csrf);
      Object.keys(extra || {}).forEach(function(k){ body.append(k, extra[k]); });
      var headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (csrf) headers['X-Glpi-Csrf-Token'] = csrf;
      var res = await fetch(mapEndpoint, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: headers
      });
      var data = await res.json().catch(function(){ return null; });
      if (!data || !data.ok) {
        showMapMsg(false, <?php echo json_encode(__('Save failed', 'paineldebordo')); ?>);
        return;
      }
      showMapMsg(true, <?php echo json_encode(__('Saved', 'paineldebordo')); ?>);
      if (data.cleared) { latEl.value = ''; lngEl.value = ''; }
      else { latEl.value = data.lat; lngEl.value = data.lng; }
    }
    if (saveBtn) saveBtn.addEventListener('click', function(){
      postMap({ lat: latEl.value, lng: lngEl.value });
    });
    if (clearBtn) clearBtn.addEventListener('click', function(){
      postMap({ clear: '1', lat: '', lng: '' });
    });
  });

  var copyBtn = document.getElementById('ho_tv_copy_link');
  var copyInput = document.getElementById('ho_tv_pair_url');
  var copyMsg = document.getElementById('ho_tv_copy_msg');
  if (copyBtn && copyInput) {
    copyBtn.addEventListener('click', async function () {
      var text = copyInput.value || '';
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          copyInput.select();
          document.execCommand('copy');
        }
        if (copyMsg) {
          copyMsg.hidden = false;
          copyMsg.className = 'ho-msg ho-msg--ok';
          copyMsg.textContent = <?php echo json_encode(__('Link copied', 'paineldebordo')); ?>;
          setTimeout(function () { copyMsg.hidden = true; }, 2000);
        }
      } catch (e) {
        copyInput.select();
      }
    });
  }

  var btn = document.getElementById('tv_pair_link_btn');
  var input = document.getElementById('tv_pair_code_input');
  var msg = document.getElementById('tv_pair_ajax_msg');
  if (btn && input && msg) {
  function fallbackForm(code) {
    document.getElementById('tv_pair_code_hidden').value = code;
    document.getElementById('tv_pair_fallback').hidden = false;
    document.getElementById('tv_pair_fallback').submit();
  }
  btn.addEventListener('click', async function(){
    var code = (input.value || '').trim();
    msg.hidden = false;
    if (!code) {
      msg.className = 'ho-msg ho-msg--err';
      msg.textContent = <?php echo json_encode(__('Invalid or expired code', 'paineldebordo')); ?>;
      return;
    }
    msg.className = 'ho-msg ho-msg--info';
    msg.textContent = '…';
    try {
      var body = new FormData();
      body.append('code', code);
      body.append('tv_pair_code', code);
      if (csrf) body.append('_glpi_csrf_token', csrf);
      var headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (csrf) headers['X-Glpi-Csrf-Token'] = csrf;
      var res = await fetch(pairEndpoint, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: headers
      });
      var text = await res.text();
      var data = null;
      try { data = JSON.parse(text); } catch (e) { data = null; }
      if (!data) { fallbackForm(code); return; }
      msg.className = 'ho-msg ' + (data.ok ? 'ho-msg--ok' : 'ho-msg--err');
      msg.textContent = data.message || (data.ok
        ? <?php echo json_encode(__('TV linked successfully', 'paineldebordo')); ?>
        : <?php echo json_encode(__('Invalid or expired code', 'paineldebordo')); ?>);
      // GET navigate — never reload() after a prior POST (revoke/save),
      // or the browser re-POSTs with a spent CSRF token → GLPI "Access denied".
      if (data.ok) setTimeout(function(){ location.href = 'shell.php?page=config'; }, 800);
      else if (data.error === 'csrf') fallbackForm(code);
    } catch (e) {
      fallbackForm(code);
    }
  });
  }
})();
</script>
<script>
(function () {
  var modal = document.getElementById('ho_cfg_modal');
  var titleEl = document.getElementById('ho_cfg_modal_title');
  var bodyEl = document.getElementById('ho_cfg_modal_body');
  var okBtn = document.getElementById('ho_cfg_modal_ok');
  var pendingForm = null;
  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    pendingForm = null;
  }
  function openModal(form) {
    if (!modal || !bodyEl || !okBtn) {
      form.submit();
      return;
    }
    pendingForm = form;
    if (titleEl) titleEl.textContent = form.getAttribute('data-confirm-title') || <?php echo json_encode(__('Confirm', 'paineldebordo')); ?>;
    bodyEl.textContent = form.getAttribute('data-confirm') || '';
    okBtn.textContent = form.getAttribute('data-confirm-ok') || <?php echo json_encode(__('Confirm', 'paineldebordo')); ?>;
    modal.hidden = false;
  }
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (form.getAttribute('data-confirm-ok-ready') === '1') {
        form.removeAttribute('data-confirm-ok-ready');
        return;
      }
      ev.preventDefault();
      openModal(form);
    });
  });
  if (okBtn) {
    okBtn.addEventListener('click', function () {
      var form = pendingForm;
      closeModal();
      if (!form) return;
      form.setAttribute('data-confirm-ok-ready', '1');
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    });
  }
  if (modal) {
    modal.querySelectorAll('[data-cfg-modal-cancel]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
  }
})();
</script>
