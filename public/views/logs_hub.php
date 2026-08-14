<?php
/**
 * Logs & audit hub — Super-Admin only (shell.php already gated).
 * Sections: Active now, Log retention, Painel de Bordo access, GLPI logins.
 * Retention POST is handled in shell.php (PRG) via inc/audit.inc.php.
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/csrf.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/layout.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/audit.inc.php');

$msg = '';
$msg_ok = true;
if (!empty($_SESSION['hubops_config_flash']) && is_array($_SESSION['hubops_config_flash'])) {
    $msg = (string) ($_SESSION['hubops_config_flash']['msg'] ?? '');
    $msg_ok = !empty($_SESSION['hubops_config_flash']['ok']);
    unset($_SESSION['hubops_config_flash']);
}

$csrf = plugin_paineldebordo_csrf_field();
$theme = plugin_paineldebordo_getFilters()['theme'];

// Filters (GET) for the access-log table.
$f_action = (string) ($_GET['flt_action'] ?? '');
$f_user = (int) ($_GET['flt_user'] ?? 0);
$f_q = trim((string) ($_GET['q'] ?? ''));
$f_from = trim((string) ($_GET['from'] ?? ''));
$f_to = trim((string) ($_GET['to'] ?? ''));

$per_page = 100;
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page_no - 1) * $per_page;

$filter_opts = [
    'action'   => $f_action,
    'users_id' => $f_user,
    'q'        => $f_q,
    'from'     => $f_from,
    'to'       => $f_to,
];

$action_labels = plugin_paineldebordo_audit_actions();
$online = plugin_paineldebordo_audit_online(15);
$total = plugin_paineldebordo_audit_count($filter_opts);
$pages = max(1, (int) ceil($total / $per_page));
if ($page_no > $pages) {
    $page_no = $pages;
    $offset = ($page_no - 1) * $per_page;
}
$rows = plugin_paineldebordo_audit_list($filter_opts + ['limit' => $per_page, 'offset' => $offset]);
$log_users = plugin_paineldebordo_audit_users();
$logins = plugin_paineldebordo_audit_glpi_logins(100);

// Preserve filters across pagination/export links.
$base_q = [
    'page'       => 'logs',
    'theme'      => $theme,
    'flt_action' => $f_action,
    'flt_user'   => $f_user ?: '',
    'q'          => $f_q,
    'from'       => $f_from,
    'to'         => $f_to,
];
$link_with = static function (array $extra) use ($base_q): string {
    return 'shell.php?' . http_build_query(array_filter(
        array_merge($base_q, $extra),
        static function ($v) {
            return $v !== '' && $v !== null;
        }
    ));
};
$entity_name = static function (int $eid): string {
    static $cache = [];
    if (isset($cache[$eid])) {
        return $cache[$eid];
    }
    $name = '';
    if (class_exists('Dropdown') && method_exists('Dropdown', 'getDropdownName')) {
        $name = (string) Dropdown::getDropdownName('glpi_entities', $eid);
    }
    return $cache[$eid] = ($name !== '' && $name !== '&nbsp;') ? $name : (string) $eid;
};
$retention = plugin_paineldebordo_audit_retention_days();
$retention_opts = [0, 30, 60, 90, 180, 365];
if (!in_array($retention, $retention_opts, true)) {
    $retention_opts[] = $retention;
    sort($retention_opts);
}

$fmtDate = static function ($dt): string {
    $dt = (string) $dt;
    if ($dt === '' || strncmp($dt, '0000-00-00', 10) === 0) {
        return '—';
    }
    if (class_exists('Html') && method_exists('Html', 'convDateTime')) {
        return (string) Html::convDateTime($dt);
    }
    return $dt;
};
$badgeClass = static function (string $action): string {
    if ($action === 'access_denied') {
        return 'ho-log-badge ho-log-badge--denied';
    }
    if (strpos($action, 'config') !== false) {
        return 'ho-log-badge ho-log-badge--config';
    }
    if (strpos($action, 'export') !== false) {
        return 'ho-log-badge ho-log-badge--export';
    }
    if (strpos($action, 'tv') !== false) {
        return 'ho-log-badge ho-log-badge--tv';
    }
    return 'ho-log-badge';
};
$agoLabel = static function ($dt): string {
    $t = strtotime((string) $dt);
    if (!$t) {
        return '';
    }
    $m = (int) floor((time() - $t) / 60);
    if ($m <= 0) {
        return __('just now', 'paineldebordo');
    }
    return sprintf(__('%d min ago', 'paineldebordo'), $m);
};
?>
<?php if ($msg) { ?>
<div class="ho-msg <?php echo $msg_ok ? 'ho-msg--ok' : 'ho-msg--err'; ?>" role="status">
  <?php echo htmlspecialchars($msg); ?>
</div>
<?php } ?>

<header class="ho-section-head ho-dash-intro">
  <?php echo plugin_paineldebordo_icon('logs'); ?>
  <div>
    <h2 class="ho-dash-intro__title"><?php echo __('Logs & audit', 'paineldebordo'); ?></h2>
    <p class="ho-dash-intro__meta"><?php echo __('Who is using Painel de Bordo, sensitive actions, and GLPI logins.', 'paineldebordo'); ?></p>
  </div>
</header>

<!-- Active now -->
<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('observer'); ?>
    <span><?php echo __('Active now', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <?php if (!$online) { ?>
      <p class="ho-empty"><?php echo __('No active users in the last 15 minutes.', 'paineldebordo'); ?></p>
    <?php } else { ?>
      <div class="ho-online-grid">
        <?php foreach ($online as $o) {
            $uid = (int) ($o['users_id'] ?? 0);
            $name = (string) ($o['user_name'] ?? '');
            ?>
          <div class="ho-online-card">
            <span class="ho-online-card__dot" aria-hidden="true"></span>
            <?php echo plugin_paineldebordo_avatar_html($uid, $name); ?>
            <div>
              <div class="ho-online-card__name"><?php echo htmlspecialchars($name !== '' ? $name : ('#' . $uid)); ?></div>
              <div class="ho-online-card__meta">
                <?php echo htmlspecialchars((string) ($o['last_page'] ?? '')); ?> · <?php echo htmlspecialchars($agoLabel($o['last_seen'] ?? '')); ?>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
</div>

<!-- Log retention -->
<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('logs'); ?>
    <span><?php echo __('Log retention', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <form method="post" action="shell.php?page=logs">
      <?php echo $csrf; ?>
      <input type="hidden" name="logs_retention_save" value="1">
      <div class="ho-form-row">
        <div class="ho-field">
          <span class="ho-field__label"><?php echo __('Keep access logs for', 'paineldebordo'); ?></span>
          <select class="form-select ho-select" name="logs_retention_days">
            <?php foreach ($retention_opts as $d) { ?>
              <option value="<?php echo (int) $d; ?>" <?php echo $d === $retention ? 'selected' : ''; ?>>
                <?php echo $d === 0
                    ? __('Inherit GLPI (no auto-purge)', 'paineldebordo')
                    : sprintf(__('%d days', 'paineldebordo'), $d); ?>
              </option>
            <?php } ?>
          </select>
        </div>
        <div class="ho-form-actions">
          <button type="submit" class="btn btn-primary">
            <?php echo plugin_paineldebordo_icon('cog'); ?> <?php echo __('Save', 'paineldebordo'); ?>
          </button>
        </div>
      </div>
      <p class="ho-check-list__hint"><?php echo __('Default: 30 days. “Inherit GLPI” keeps entries indefinitely (managed by GLPI housekeeping); any other value auto-purges older entries.', 'paineldebordo'); ?></p>
    </form>
  </div>
</div>

<!-- Painel de Bordo access log -->
<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('backlog'); ?>
    <span><?php echo __('Painel de Bordo access', 'paineldebordo'); ?></span>
    <span class="ho-toolbar__spacer"></span>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($link_with(['export' => 'csv'])); ?>">
      <?php echo plugin_paineldebordo_icon('download'); ?> <?php echo __('Export CSV', 'paineldebordo'); ?>
    </a>
    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($link_with(['export' => 'pdf'])); ?>">
      <?php echo plugin_paineldebordo_icon('download'); ?> <?php echo __('Export PDF', 'paineldebordo'); ?>
    </a>
  </div>
  <div class="card-body">
    <form method="get" action="shell.php" class="ho-form-row" style="margin-bottom:0.9rem;">
      <input type="hidden" name="page" value="logs">
      <input type="hidden" name="theme" value="<?php echo htmlspecialchars($theme); ?>">
      <div class="ho-field">
        <span class="ho-field__label"><?php echo __('Action', 'paineldebordo'); ?></span>
        <select class="form-select ho-select" name="flt_action">
          <option value=""><?php echo __('All actions', 'paineldebordo'); ?></option>
          <?php foreach ($action_labels as $key => $label) { ?>
            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $f_action === $key ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label); ?>
            </option>
          <?php } ?>
        </select>
      </div>
      <div class="ho-field">
        <span class="ho-field__label"><?php echo __('User', 'paineldebordo'); ?></span>
        <select class="form-select ho-select" name="flt_user">
          <option value=""><?php echo __('All users', 'paineldebordo'); ?></option>
          <?php foreach ($log_users as $uid => $uname) { ?>
            <option value="<?php echo (int) $uid; ?>" <?php echo $f_user === (int) $uid ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($uname !== '' ? $uname : ('#' . $uid)); ?>
            </option>
          <?php } ?>
        </select>
      </div>
      <div class="ho-field">
        <span class="ho-field__label"><?php echo __('Search', 'paineldebordo'); ?></span>
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($f_q); ?>" placeholder="<?php echo htmlspecialchars(__('User or detail', 'paineldebordo')); ?>">
      </div>
      <div class="ho-field">
        <span class="ho-field__label"><?php echo __('From', 'paineldebordo'); ?></span>
        <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($f_from); ?>">
      </div>
      <div class="ho-field">
        <span class="ho-field__label"><?php echo __('To', 'paineldebordo'); ?></span>
        <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($f_to); ?>">
      </div>
      <div class="ho-form-actions">
        <button type="submit" class="btn btn-outline-secondary">
          <?php echo plugin_paineldebordo_icon('filter'); ?> <?php echo __('Filter', 'paineldebordo'); ?>
        </button>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th><?php echo __('User', 'paineldebordo'); ?></th>
            <th><?php echo __('Action', 'paineldebordo'); ?></th>
            <th><?php echo __('Detail', 'paineldebordo'); ?></th>
            <th><?php echo __('Entity', 'paineldebordo'); ?></th>
            <th><?php echo __('Device', 'paineldebordo'); ?></th>
            <th><?php echo __('IP', 'paineldebordo'); ?></th>
            <th><?php echo __('Date', 'paineldebordo'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows) { ?>
            <tr><td colspan="7" class="ho-empty"><?php echo __('No data', 'paineldebordo'); ?></td></tr>
          <?php } else { foreach ($rows as $r) {
              $uid = (int) ($r['users_id'] ?? 0);
              $name = (string) ($r['user_name'] ?? '');
              $act = (string) ($r['action'] ?? '');
              $detail = (string) ($r['detail'] ?? '');
              $ua = (string) ($r['user_agent'] ?? '');
              ?>
            <tr>
              <td>
                <span class="ho-log-user">
                  <?php echo plugin_paineldebordo_avatar_html($uid, $name); ?>
                  <span class="ho-log-user__name"><?php echo htmlspecialchars($name !== '' ? $name : ('#' . $uid)); ?></span>
                </span>
              </td>
              <td><span class="<?php echo $badgeClass($act); ?>"><?php echo htmlspecialchars(plugin_paineldebordo_audit_action_label($act)); ?></span></td>
              <td>
                <?php if (mb_strlen($detail) > 60) { ?>
                  <span class="ho-tip" data-tip="<?php echo htmlspecialchars($detail); ?>"><?php echo htmlspecialchars(mb_substr($detail, 0, 60) . '…'); ?></span>
                <?php } else { echo htmlspecialchars($detail); } ?>
              </td>
              <td><?php echo htmlspecialchars($entity_name((int) ($r['entities_id'] ?? 0))); ?></td>
              <td>
                <?php if ($ua !== '') { ?>
                  <span class="ho-tip" data-tip="<?php echo htmlspecialchars($ua); ?>"><?php echo htmlspecialchars(plugin_paineldebordo_tv_parse_user_agent($ua)); ?></span>
                <?php } else { ?>
                  <span class="ho-log-muted">—</span>
                <?php } ?>
              </td>
              <td><?php echo htmlspecialchars((string) ($r['remote_ip'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($fmtDate($r['date_creation'] ?? '')); ?></td>
            </tr>
          <?php } } ?>
        </tbody>
      </table>
    </div>
    <?php if ($total > 0) { ?>
      <div class="ho-toolbar" style="margin-top:0.75rem;">
        <span class="ho-log-muted">
          <?php echo sprintf(
              __('%1$s–%2$s of %3$s entries', 'paineldebordo'),
              number_format($offset + 1, 0, ',', '.'),
              number_format(min($offset + $per_page, $total), 0, ',', '.'),
              number_format($total, 0, ',', '.')
          ); ?>
        </span>
        <span class="ho-toolbar__spacer"></span>
        <?php if ($page_no > 1) { ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($link_with(['p' => $page_no - 1])); ?>">
            <?php echo plugin_paineldebordo_icon('back'); ?> <?php echo __('Previous', 'paineldebordo'); ?>
          </a>
        <?php } ?>
        <span class="ho-log-muted"><?php echo sprintf(__('Page %1$d of %2$d', 'paineldebordo'), $page_no, $pages); ?></span>
        <?php if ($page_no < $pages) { ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($link_with(['p' => $page_no + 1])); ?>">
            <?php echo __('Next', 'paineldebordo'); ?> <?php echo plugin_paineldebordo_icon('forward'); ?>
          </a>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
</div>

<!-- GLPI logins -->
<div class="card">
  <div class="card-header ho-card-head">
    <?php echo plugin_paineldebordo_icon('user'); ?>
    <span><?php echo __('GLPI logins', 'paineldebordo'); ?></span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th><?php echo __('User', 'paineldebordo'); ?></th>
            <th><?php echo __('Date', 'paineldebordo'); ?></th>
            <th><?php echo __('Event', 'paineldebordo'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$logins) { ?>
            <tr><td colspan="3" class="ho-empty"><?php echo __('No data', 'paineldebordo'); ?></td></tr>
          <?php } else { foreach ($logins as $l) {
              $uid = (int) ($l['users_id'] ?? 0);
              $name = (string) ($l['user_name'] ?? '');
              ?>
            <tr>
              <td>
                <?php if ($uid > 0 || $name !== '') { ?>
                  <span class="ho-log-user">
                    <?php echo plugin_paineldebordo_avatar_html($uid, $name); ?>
                    <span class="ho-log-user__name"><?php echo htmlspecialchars($name !== '' ? $name : ('#' . $uid)); ?></span>
                  </span>
                <?php } else { ?>
                  <span class="ho-log-muted">—</span>
                <?php } ?>
              </td>
              <td><?php echo htmlspecialchars($fmtDate($l['date'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($l['message'] ?? '')); ?></td>
            </tr>
          <?php } } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
