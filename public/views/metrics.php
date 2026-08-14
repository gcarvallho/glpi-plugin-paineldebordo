<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/tickets.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');
$list = plugin_paineldebordo_tickets_open_list();
$scope = plugin_paineldebordo_tickets_scope();
global $DB;

$today = date('Y-m-d');
$sql_today = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']} AND DATE(glpi_tickets.date)='$today'";
$opened_today = 0;
$res = $DB->doQuery($sql_today);
if ($res && ($row = $DB->fetchAssoc($res))) {
    $opened_today = (int) $row['c'];
}

$sql_solved = "SELECT COUNT(DISTINCT glpi_tickets.id) AS c FROM glpi_tickets {$scope['group_join']}
WHERE glpi_tickets.is_deleted = 0 {$scope['entity_sql']} {$scope['group_where']}
AND glpi_tickets.status IN (5,6) AND DATE(glpi_tickets.solvedate)='$today'";
$solved_today = 0;
$res = $DB->doQuery($sql_solved);
if ($res && ($row = $DB->fetchAssoc($res))) {
    $solved_today = (int) $row['c'];
}
$pct = $list['total'] > 0 ? round((($list['total'] - $list['late']) / $list['total']) * 100) : 100;
?>
<section class="ho-kpi-grid">
  <article class="ho-kpi ho-kpi--accent">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('backlog'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Backlog', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo (int) $list['total']; ?></p>
  </article>
  <article class="ho-kpi ho-kpi--danger">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('late'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Late', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo (int) $list['late']; ?></p>
  </article>
  <article class="ho-kpi ho-kpi--info">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('opened'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Opened today', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo $opened_today; ?></p>
  </article>
  <article class="ho-kpi ho-kpi--primary">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('solved'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Solved today', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo $solved_today; ?></p>
  </article>
  <article class="ho-kpi ho-kpi--accent">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('percent'); ?></span>
    <p class="ho-kpi__label"><?php echo __('On time %', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo $pct; ?>%</p>
  </article>
  <article class="ho-kpi ho-kpi--info">
    <span class="ho-kpi__icon"><?php echo plugin_paineldebordo_icon('list'); ?></span>
    <p class="ho-kpi__label"><?php echo __('Listed', 'paineldebordo'); ?></p>
    <p class="ho-kpi__value"><?php echo count($list['rows']); ?></p>
  </article>
</section>
