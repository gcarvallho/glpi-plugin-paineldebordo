<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/charts.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');
$catalog = plugin_paineldebordo_charts_catalog();
$theme = plugin_paineldebordo_getFilters()['theme'];
?>
<?php if (!$catalog) { ?>
  <div class="card"><div class="card-body ho-empty"><?php echo __('No charts available', 'paineldebordo'); ?></div></div>
<?php } else { ?>
<div class="ho-report-list">
<?php foreach ($catalog as $id => $meta) { ?>
  <a class="card ho-tip" data-tip="<?php echo htmlspecialchars($meta['desc'] ?? ''); ?>" href="shell.php?<?php echo http_build_query(['page' => 'chart', 'chart' => $id, 'theme' => $theme]); ?>">
    <span class="ho-report-list__icon"><?php echo plugin_paineldebordo_icon($id); ?></span>
    <p class="ho-report-list__title"><?php echo htmlspecialchars($meta['title']); ?></p>
  </a>
<?php } ?>
</div>
<?php } ?>
