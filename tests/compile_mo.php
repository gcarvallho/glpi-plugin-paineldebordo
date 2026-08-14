<?php
/**
 * Minimal .po → .mo compiler (no gettext msgfmt required).
 * Usage: php tests/compile_mo.php
 */
$root = dirname(__DIR__);
$poPath = $root . '/locales/pt_BR.po';
$moPath = $root . '/locales/pt_BR.mo';
$po = file_get_contents($poPath);
if ($po === false) {
    fwrite(STDERR, "Cannot read $poPath\n");
    exit(1);
}

$entries = [];
if (preg_match_all('/msgid\s+((?:"(?:\\\\.|[^"\\\\])*"(?:\s*))+)msgstr\s+((?:"(?:\\\\.|[^"\\\\])*"(?:\s*))+)/s', $po, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
        $id = po_unquote($m[1]);
        $str = po_unquote($m[2]);
        if ($id === '' && $str === '') {
            continue;
        }
        $entries[$id] = $str;
    }
}

if (!$entries) {
    fwrite(STDERR, "No entries parsed from $poPath\n");
    exit(1);
}

$keys = array_keys($entries);
$n = count($keys);
$idsBlob = '';
$strsBlob = '';
$idMeta = [];
$strMeta = [];
foreach ($keys as $k) {
    $idMeta[] = [strlen($k), strlen($idsBlob)];
    $idsBlob .= $k . "\0";
    $v = $entries[$k];
    $strMeta[] = [strlen($v), strlen($strsBlob)];
    $strsBlob .= $v . "\0";
}

$headerSize = 28;
$tableSize = $n * 8;
$idsOffset = $headerSize + $tableSize + $tableSize;
$strsOffset = $idsOffset + strlen($idsBlob);

$out = pack('V*', 0x950412de, 0, $n, $headerSize, $headerSize + $tableSize, 0, 0);
foreach ($idMeta as $o) {
    $out .= pack('VV', $o[0], $idsOffset + $o[1]);
}
foreach ($strMeta as $o) {
    $out .= pack('VV', $o[0], $strsOffset + $o[1]);
}
$out .= $idsBlob . $strsBlob;

if (file_put_contents($moPath, $out) === false) {
    fwrite(STDERR, "Cannot write $moPath\n");
    exit(1);
}

echo "Compiled $n entries → $moPath (" . strlen($out) . " bytes)\n";
exit(0);

function po_unquote(string $chunk): string
{
    $out = '';
    if (!preg_match_all('/"((?:\\\\.|[^"\\\\])*)"/s', $chunk, $parts)) {
        return '';
    }
    foreach ($parts[1] as $p) {
        $out .= stripcslashes($p);
    }
    return $out;
}
