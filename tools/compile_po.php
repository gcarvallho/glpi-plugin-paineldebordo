<?php
/**
 * Compile locales/pt_BR.po → locales/pt_BR.mo (no msgfmt required).
 * Run: php tools/compile_po.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$poFile = $root . '/locales/pt_BR.po';
$moFile = $root . '/locales/pt_BR.mo';

if (!is_file($poFile)) {
    fwrite(STDERR, "Missing $poFile\n");
    exit(1);
}

$raw = file_get_contents($poFile);
if ($raw === false) {
    fwrite(STDERR, "Cannot read po\n");
    exit(1);
}

// Strip UTF-8 BOM
if (str_starts_with($raw, "\xEF\xBB\xBF")) {
    $raw = substr($raw, 3);
}

$entries = [];
$blocks = preg_split("/\n\n+/", str_replace("\r\n", "\n", $raw)) ?: [];
foreach ($blocks as $block) {
    if (!preg_match('/^msgid\s+/m', $block)) {
        continue;
    }
    $msgid = null;
    $msgstr = null;
    $mode = null;
    $buf = '';
    foreach (explode("\n", $block) as $line) {
        if (preg_match('/^msgid\s+"(.*)"\s*$/', $line, $m)) {
            if ($mode === 'msgstr') {
                $msgstr = stripcslashes($buf);
            }
            $mode = 'msgid';
            $buf = $m[1];
            continue;
        }
        if (preg_match('/^msgstr\s+"(.*)"\s*$/', $line, $m)) {
            if ($mode === 'msgid') {
                $msgid = stripcslashes($buf);
            }
            $mode = 'msgstr';
            $buf = $m[1];
            continue;
        }
        if (preg_match('/^"(.*)"\s*$/', $line, $m) && $mode !== null) {
            $buf .= $m[1];
            continue;
        }
    }
    if ($mode === 'msgstr') {
        $msgstr = stripcslashes($buf);
    }
    if ($msgid === null || $msgstr === null) {
        continue;
    }
    if ($msgid === '') {
        continue; // header
    }
    if ($msgstr === '') {
        continue;
    }
    $entries[$msgid] = $msgstr;
}

ksort($entries, SORT_STRING);

// Build GNU MO (little-endian)
$ids = '';
$strs = '';
$offsets = [];
foreach ($entries as $id => $str) {
    $offsets[] = [strlen($ids), strlen($id), strlen($strs), strlen($str)];
    $ids .= $id . "\0";
    $strs .= $str . "\0";
}
$count = count($entries);
$keystart = 28;
$valuestart = $keystart + 8 * $count;
$idstart = $valuestart + 8 * $count;
$strstart = $idstart + strlen($ids);

$mo = pack('V', 0x950412de); // magic
$mo .= pack('V', 0);         // revision
$mo .= pack('V', $count);
$mo .= pack('V', $keystart);
$mo .= pack('V', $valuestart);
$mo .= pack('V', 0);         // hash size
$mo .= pack('V', 0);         // hash offset

foreach ($offsets as $o) {
    $mo .= pack('VV', $o[1], $idstart + $o[0]);
}
foreach ($offsets as $o) {
    $mo .= pack('VV', $o[3], $strstart + $o[2]);
}
$mo .= $ids;
$mo .= $strs;

if (file_put_contents($moFile, $mo) === false) {
    fwrite(STDERR, "Cannot write $moFile\n");
    exit(1);
}

echo 'Compiled ' . $count . " strings → $moFile\n";
