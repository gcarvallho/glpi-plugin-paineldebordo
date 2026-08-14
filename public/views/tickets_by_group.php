<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');

global $CFG_GLPI;

$groups = plugin_paineldebordo_getGroupOptions();
$filters = plugin_paineldebordo_getFilters();
$sel = isset($_GET['grp']) ? (int) $_GET['grp'] : (int) ($filters['group'] ?? 0);
if ($sel <= 0) {
    $sel = (int) plugin_paineldebordo_getConfigValue('filter_group', '0');
}
$data = null;
if ($sel > 0) {
    plugin_paineldebordo_assertGroupAccess($sel);
    $data = plugin_paineldebordo_tickets_open_list($sel, null);
}
$ticket_base = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/front/ticket.form.php?id=';
$status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
?>
<?php if ($sel <= 0) { ?>
<div class="card ho-picker-card">
  <div class="card-header ho-card-head"><?php echo __('Select a group', 'paineldebordo'); ?></div>
  <div class="card-body">
    <p class="ho-check-list__hint"><?php echo __('Choose a group in the filter bar above, or pick one here.', 'paineldebordo'); ?></p>
    <form method="get" action="shell.php" class="ho-form-row" style="margin-bottom:0;">
      <input type="hidden" name="page" value="by_group">
      <input type="hidden" name="theme" value="<?php echo htmlspecialchars($filters['theme']); ?>">
      <input type="hidden" name="period" value="<?php echo htmlspecialchars($filters['period'] ?? 'month'); ?>">
      <label class="ho-field">
        <span class="ho-field__label"><?php echo __('Group', 'paineldebordo'); ?></span>
        <select class="ho-select" name="grp" onchange="this.form.submit()">
          <?php foreach ($groups as $id => $name) { ?>
            <option value="<?php echo (int) $id; ?>" <?php echo (int) $id === $sel ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
          <?php } ?>
        </select>
      </label>
    </form>
  </div>
</div>
<?php } ?>
<?php if ($data !== null) {
    $list_page = 'by_group';
    $list_title = (string) ($groups[$sel] ?? __('By group', 'paineldebordo'));
    $list_extra = ['grp' => (string) $sel];
    ?>
<section class="ho-kpi-grid ho-kpi-grid--2">
  <article class="ho-kpi ho-kpi--accent"><span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('backlog'); ?></span><p class="ho-kpi__label"><?php echo __('Open', 'paineldebordo'); ?></p><p class="ho-kpi__value"><?php echo (int) $data['total']; ?></p></article>
  <article class="ho-kpi ho-kpi--danger"><span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('late'); ?></span><p class="ho-kpi__label"><?php echo __('Late', 'paineldebordo'); ?></p><p class="ho-kpi__value"><?php echo (int) $data['late']; ?></p></article>
</section>
<?php include __DIR__ . '/_tickets_table.php'; ?>
<?php } ?>
