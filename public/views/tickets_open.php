<?php
/**
 * View: open tickets.
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');

global $CFG_GLPI;

$data = plugin_paineldebordo_tickets_open_list();
$status_map = class_exists('Ticket') ? Ticket::getAllStatusArray() : [];
$ticket_base = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/front/ticket.form.php?id=';
$filters = plugin_paineldebordo_getFilters();
$list_page = 'tickets';
$view = $data['opts']['view'] ?? null;
if ($view === 'validation') {
    $list_title = __('Waiting for validation', 'paineldebordo');
} elseif ($view === 'solution') {
    $list_title = __('Solution approved', 'paineldebordo');
} else {
    $list_title = __('Open tickets', 'paineldebordo');
}
$list_extra = [];
?>
<section class="ho-kpi-grid ho-kpi-grid--3">
  <article class="ho-kpi ho-kpi--accent">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('backlog'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Open', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value" id="tk_total"><?php echo (int) $data['total']; ?></p>
  </article>
  <article class="ho-kpi ho-kpi--danger">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('late'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Late', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value" id="tk_late"><?php echo (int) $data['late']; ?></p>
  </article>
  <article class="ho-kpi ho-kpi--primary">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('list'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Listed', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo count($data['rows']); ?></p>
  </article>
</section>

<?php include __DIR__ . '/_tickets_table.php'; ?>
