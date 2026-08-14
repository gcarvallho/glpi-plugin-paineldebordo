<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');

global $CFG_GLPI;

$entities = plugin_paineldebordo_entity_options();
$filters = plugin_paineldebordo_getFilters();
$sel = isset($_GET['ent']) ? (int) $_GET['ent'] : -1;
$data = null;
if ($sel >= 0 && isset($entities[$sel])) {
    $data = plugin_paineldebordo_tickets_open_list(null, $sel);
}
$ticket_base = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/front/ticket.form.php?id=';
$status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
?>
<div class="card ho-picker-card">
  <div class="card-header ho-card-head"><?php echo __('Select an entity', 'paineldebordo'); ?></div>
  <div class="card-body">
    <form method="get" action="shell.php" class="ho-form-row" style="margin-bottom:0;">
      <input type="hidden" name="page" value="by_entity">
      <input type="hidden" name="theme" value="<?php echo htmlspecialchars($filters['theme']); ?>">
      <input type="hidden" name="period" value="<?php echo htmlspecialchars($filters['period'] ?? 'month'); ?>">
      <label class="ho-field">
        <span class="ho-field__label"><?php echo __('Entity'); ?></span>
        <select class="ho-select" name="ent" onchange="this.form.submit()">
          <option value="-1"><?php echo __('Select an entity', 'paineldebordo'); ?></option>
          <?php foreach ($entities as $id => $name) { ?>
            <option value="<?php echo (int) $id; ?>" <?php echo (int) $id === $sel ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
          <?php } ?>
        </select>
      </label>
    </form>
  </div>
</div>
<?php if ($data !== null) {
    $list_page = 'by_entity';
    $list_title = (string) ($entities[$sel] ?? __('By entity', 'paineldebordo'));
    $list_extra = ['ent' => (string) $sel];
    ?>
<section class="ho-kpi-grid ho-kpi-grid--2">
  <article class="ho-kpi ho-kpi--accent"><span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('backlog'); ?></span><p class="ho-kpi__label"><?php echo __('Open', 'paineldebordo'); ?></p><p class="ho-kpi__value"><?php echo (int) $data['total']; ?></p></article>
  <article class="ho-kpi ho-kpi--danger"><span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('late'); ?></span><p class="ho-kpi__label"><?php echo __('Late', 'paineldebordo'); ?></p><p class="ho-kpi__value"><?php echo (int) $data['late']; ?></p></article>
</section>
<?php include __DIR__ . '/_tickets_table.php'; ?>
<?php } ?>
