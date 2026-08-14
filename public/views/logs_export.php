<?php
/**
 * Audit-log export (CSV/PDF) — raw file output, no chrome. Routed from
 * shell.php before the layout, mirroring views/report_run.php. Honors the
 * same filters as the on-screen table, and records the export itself as a
 * sensitive action (exporting an audit trail is auditable too).
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/tv_pair.inc.php');
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/audit.inc.php');

$fmt = (string) ($_GET['export'] ?? 'csv');
$opts = [
    'action'   => (string) ($_GET['flt_action'] ?? ''),
    'users_id' => (int) ($_GET['flt_user'] ?? 0),
    'q'        => trim((string) ($_GET['q'] ?? '')),
    'from'     => trim((string) ($_GET['from'] ?? '')),
    'to'       => trim((string) ($_GET['to'] ?? '')),
    // Export is not paginated — cap generously so a full window fits one file.
    'limit'    => 1000,
    'offset'   => 0,
];

$rows_raw = plugin_paineldebordo_audit_list($opts);

plugin_paineldebordo_audit_log('export_logs', $fmt, 'logs');

$headers = [
    __('User', 'paineldebordo'),
    __('Action', 'paineldebordo'),
    __('Detail', 'paineldebordo'),
    __('Page', 'paineldebordo'),
    __('Entity', 'paineldebordo'),
    __('Device', 'paineldebordo'),
    __('IP', 'paineldebordo'),
    __('Date', 'paineldebordo'),
];

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

$rows = [];
foreach ($rows_raw as $r) {
    $rows[] = [
        (string) ($r['user_name'] ?? ''),
        plugin_paineldebordo_audit_action_label((string) ($r['action'] ?? '')),
        (string) ($r['detail'] ?? ''),
        (string) ($r['page'] ?? ''),
        $entity_name((int) ($r['entities_id'] ?? 0)),
        plugin_paineldebordo_tv_parse_user_agent((string) ($r['user_agent'] ?? '')),
        (string) ($r['remote_ip'] ?? ''),
        (string) ($r['date_creation'] ?? ''),
    ];
}

$title = __('Painel de Bordo access', 'paineldebordo');
$stamp = date('Ymd-Hi');

if ($fmt === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="paineldebordo-audit-' . $stamp . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// PDF — reuses the same TCPDF wrapper as report exports (opens inline).
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/services/report_pdf.php');
$period = [];
if ($opts['from'] !== '') {
    $period[] = $opts['from'];
}
if ($opts['to'] !== '') {
    $period[] = $opts['to'];
}
$period_label = $period ? implode(' → ', $period) : __('All period', 'paineldebordo');
plugin_paineldebordo_report_pdf_output(
    $title,
    $headers,
    $rows,
    $period_label,
    'paineldebordo-audit-' . $stamp . '.pdf'
);
