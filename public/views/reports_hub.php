<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/reports.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');
$catalog = plugin_paineldebordo_reports_catalog();
$theme = plugin_paineldebordo_getFilters()['theme'];
$period = plugin_paineldebordo_getFilters()['period'];
?>
<div class="ho-period-chip">
  <span class="badge badge-secondary ho-tip" data-tip="<?php echo htmlspecialchars(__('Period filter', 'paineldebordo')); ?>"><?php echo htmlspecialchars(plugin_paineldebordo_period_label($period)); ?></span>
</div>
<?php foreach ($catalog as $section) { ?>
  <h3 class="ho-section-head">
    <?php echo plugin_paineldebordo_icon('reports'); ?>
    <span><?php echo htmlspecialchars($section['title']); ?></span>
  </h3>
  <div class="ho-report-list" style="margin-bottom:1.25rem;">
    <?php foreach ($section['items'] as $id => $label) { ?>
      <a class="card" href="shell.php?<?php echo http_build_query(['page' => 'report', 'report' => $id, 'theme' => $theme, 'period' => $period]); ?>">
        <span class="ho-report-list__icon"><?php echo plugin_paineldebordo_icon($id); ?></span>
        <p class="ho-report-list__title"><?php echo htmlspecialchars($label); ?></p>
      </a>
    <?php } ?>
  </div>
<?php } ?>
