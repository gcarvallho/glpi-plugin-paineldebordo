<?php
/**
 * Static error sweep for Painel de Bordo 2.16 — writes NDJSON to debug log.
 */
$root = dirname(__DIR__);
$logPath = dirname($root, 2) . '/debug-234820.log';
if (!is_file(dirname($root, 2) . '/debug-234820.log')) {
    // workspace root may be Hub-Controladoria
    $candidates = [
        dirname($root, 2) . '/debug-234820.log',
        dirname($root) . '/../debug-234820.log',
        'H:/6 - DESENVOLVIMENTO/Hub-Controladória/debug-234820.log',
    ];
}
$workspaceLog = 'H:/6 - DESENVOLVIMENTO/Hub-Controladória/debug-234820.log';

function dbg_log(string $hypothesisId, string $location, string $message, array $data = []): void
{
    global $workspaceLog;
    $line = json_encode([
        'sessionId' => '234820',
        'runId' => 'static-scan',
        'hypothesisId' => $hypothesisId,
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents($workspaceLog, $line . "\n", FILE_APPEND);
}

require_once $root . '/inc/icons.inc.php';

// H-A: icons resolve to doc/paper
$prefKeys = [
    'cog','hash','calendar','refresh','sort','eye','title','columns','bell','density','audio',
    'prio','observer','volume','volume_off','fullscreen','external_link','tickets','chart_bar',
    'by_group','late','opened','solved','users','today','month','exit','list',
];
$docNeedle = 'l4 4v12';
$badIcons = [];
foreach ($prefKeys as $k) {
    $svg = plugin_paineldebordo_icon($k);
    if (str_contains($svg, $docNeedle) && $k !== 'doc') {
        $badIcons[] = $k;
    }
}
dbg_log('A', 'audit_scan.php:icons', 'icon doc-fallback audit', [
    'bad' => $badIcons,
    'badCount' => count($badIcons),
    'checked' => count($prefKeys),
]);

// H-B: TV source coherence
$tv = (string) file_get_contents($root . '/public/tv.php');
$checks = [
    'sortTickets' => str_contains($tv, 'function sortTickets'),
    'defaultNewest' => str_contains($tv, "sort: 'newest'"),
    'date_mod' => str_contains($tv, 'date_mod'),
    'PREF_ICONS' => str_contains($tv, 'PREF_ICONS'),
    'buildPrefsPanel' => str_contains($tv, 'buildPrefsPanel'),
    'loadPrefs' => str_contains($tv, 'function loadPrefs'),
    'cardFields' => str_contains($tv, 'date_mod: true') || str_contains($tv, 'date_mod:true'),
];
dbg_log('B', 'audit_scan.php:tv', 'tv.php structural checks', $checks);

// H-C: backend date_mod + order
$tickets = (string) file_get_contents($root . '/inc/services/tickets.php');
$tvInc = (string) file_get_contents($root . '/inc/tv.inc.php');
dbg_log('C', 'audit_scan.php:sql', 'queue/card date_mod & order', [
    'select_date_mod' => str_contains($tickets, 'glpi_tickets.date_mod'),
    'order_date_desc' => str_contains($tickets, 'glpi_tickets.date DESC'),
    'card_date_mod' => str_contains($tvInc, "'date_mod'"),
    'card_date_mod_label' => str_contains($tvInc, 'date_mod_label'),
    // potential mismatch: server still late-first before date DESC
    'server_late_first' => str_contains($tickets, 'time_to_resolve IS NOT NULL AND glpi_tickets.time_to_resolve < NOW()'),
]);

// H-D: i18n coverage for 2.16 strings
$po = (string) file_get_contents($root . '/locales/pt_BR.po');
$need = [
    'Ticket ID' => 'ID do chamado',
    'Last update' => 'Última atualização',
    'Newest first' => 'Mais novos primeiro',
    'Oldest first' => 'Mais antigos primeiro',
    'Late, then newest' => 'Atrasados, depois novos',
    'Sort' => 'Ordenação',
    'Title' => 'Título',
    'Priority' => 'Prioridade',
    'Open date' => 'Data de abertura',
];
$missingPo = [];
foreach ($need as $msgid => $msgstr) {
    if (!str_contains($po, "msgid \"$msgid\"") || !str_contains($po, "msgstr \"$msgstr\"")) {
        $missingPo[$msgid] = $msgstr;
    }
}
$moOk = is_file($root . '/locales/pt_BR.mo') && filesize($root . '/locales/pt_BR.mo') > 100;
dbg_log('D', 'audit_scan.php:i18n', 'pt_BR coverage', [
    'missing' => $missingPo,
    'moExists' => $moOk,
    'moBytes' => $moOk ? filesize($root . '/locales/pt_BR.mo') : 0,
]);

// H-E: version alignment
$setup = (string) file_get_contents($root . '/setup.php');
$readme = (string) file_get_contents($root . '/README.md');
dbg_log('E', 'audit_scan.php:version', 'version alignment', [
    'setup216' => str_contains($setup, "'version'         => '2.32.5'"),
    'readme216' => str_contains($readme, '2.32.5'),
]);

// H-F: JS syntax risk — unbalanced braces in tv inline script
$scriptStart = strpos($tv, '<script>');
$scriptEnd = strrpos($tv, '</script>');
$js = $scriptStart !== false && $scriptEnd !== false
    ? substr($tv, $scriptStart + 8, $scriptEnd - $scriptStart - 8)
    : '';
$open = substr_count($js, '{');
$close = substr_count($js, '}');
$parenOpen = substr_count($js, '(');
$parenClose = substr_count($js, ')');
dbg_log('F', 'audit_scan.php:jsbalance', 'tv.php script brace balance', [
    'braces' => [$open, $close, $open - $close],
    'parens' => [$parenOpen, $parenClose, $parenOpen - $parenClose],
    'jsLen' => strlen($js),
]);

echo "Wrote audit to $workspaceLog\n";
