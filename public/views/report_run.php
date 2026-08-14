<?php
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/reports.php');

$flat = plugin_paineldebordo_reports_flat();
$report = $_GET['report'] ?? 'open_list';
if (!isset($flat[$report])) {
    $report = 'open_list';
}
$title = $flat[$report];
$result = plugin_paineldebordo_report_run($report);
$filters = plugin_paineldebordo_getFilters();
$theme = $filters['theme'];
$period = $result['meta']['period'] ?? $filters['period'];

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (function_exists('plugin_paineldebordo_audit_log')) {
        plugin_paineldebordo_audit_log('export_report', $report . ' csv', 'report');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="paineldebordo-' . preg_replace('/[^a-z0-9_]+/i', '-', $report) . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, $result['headers'], ';');
    foreach ($result['rows'] as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// PDF export — same headers/rows as CSV, styled like a printed report
// (title/branding header, bordered table, page footer with pagination).
// Uses GLPI core's own TCPDF (see inc/services/report_pdf.php) rather than
// vendoring a separate PDF library.
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    if (function_exists('plugin_paineldebordo_audit_log')) {
        plugin_paineldebordo_audit_log('export_report', $report . ' pdf', 'report');
    }
    include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/report_pdf.php');
    $filename = 'paineldebordo-' . preg_replace('/[^a-z0-9_]+/i', '-', $report) . '.pdf';
    plugin_paineldebordo_report_pdf_output(
        $title,
        $result['headers'],
        $result['rows'],
        plugin_paineldebordo_period_label((string) $period),
        $filename
    );
    exit;
}
?>
<div class="ho-toolbar">
  <a class="btn btn-sm btn-outline-secondary" href="shell.php?page=reports&theme=<?php echo urlencode($theme); ?>">&larr; <?php echo __('Reports', 'paineldebordo'); ?></a>
  <a class="btn btn-sm btn-primary" href="shell.php?<?php echo http_build_query(['page' => 'report', 'report' => $report, 'theme' => $theme, 'period' => $filters['period'], 'export' => 'csv']); ?>"><?php echo plugin_paineldebordo_icon('download'); ?> <?php echo __('Export CSV', 'paineldebordo'); ?></a>
  <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="shell.php?<?php echo http_build_query(['page' => 'report', 'report' => $report, 'theme' => $theme, 'period' => $filters['period'], 'export' => 'pdf']); ?>"><?php echo plugin_paineldebordo_icon('download'); ?> <?php echo __('Export PDF', 'paineldebordo'); ?></a>
  <span class="ho-toolbar__spacer"></span>
  <span class="badge badge-secondary"><?php echo htmlspecialchars(plugin_paineldebordo_period_label((string) $period)); ?></span>
  <span class="badge badge-orange"><?php echo (int) ($result['meta']['truncated'] ?? count($result['rows'])); ?></span>
</div>
<div class="card">
  <div class="card-header ho-card-head"><?php echo htmlspecialchars($title); ?></div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <?php foreach ($result['headers'] as $h) { ?>
            <th><?php echo htmlspecialchars((string) $h); ?></th>
          <?php } ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$result['rows']) { ?>
          <tr><td colspan="<?php echo max(1, count($result['headers'])); ?>" class="ho-empty"><?php echo __('No data', 'paineldebordo'); ?></td></tr>
        <?php } else { foreach ($result['rows'] as $row) { ?>
          <tr>
            <?php foreach ($row as $cell) { ?>
              <td><?php echo htmlspecialchars((string) $cell); ?></td>
            <?php } ?>
          </tr>
        <?php } } ?>
      </tbody>
    </table>
  </div>
  <?php if (count($result['rows']) >= 500) { ?>
    <div class="card-body ho-empty" style="padding-top:0;"><?php echo __('Showing first 500 rows', 'paineldebordo'); ?></div>
  <?php } ?>
</div>
