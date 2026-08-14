# Build locales/pt_BR.mo from pt_BR.po (no msgfmt required)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$poPath = Join-Path $root 'locales\pt_BR.po'
$moPath = Join-Path $root 'locales\pt_BR.mo'

function Parse-Po($path) {
  $pairs = New-Object 'System.Collections.Generic.List[object]'
  $msgid = $null
  $msgstr = $null
  $mode = $null
  Get-Content -LiteralPath $path -Encoding UTF8 | ForEach-Object {
    $line = $_
    if ($line -match '^msgid\s+"(.*)"\s*$') {
      if ($msgid -ne $null -and $msgstr -ne $null -and $msgid -ne '') {
        $pairs.Add([pscustomobject]@{ Id = $msgid; Str = $msgstr })
      }
      $msgid = $Matches[1] -replace '\\n','`n' -replace '\\"','"' -replace '\\\\','\'
      # keep simple escapes
      $msgid = [regex]::Unescape(($Matches[1] -replace '\\"','"'))
      # fallback simple
      $msgid = $Matches[1].Replace('\n',[string][char]10).Replace('\"','"').Replace('\\','\')
      $msgstr = $null
      $mode = 'id'
      return
    }
    if ($line -match '^msgstr\s+"(.*)"\s*$') {
      $msgstr = $Matches[1].Replace('\n',[string][char]10).Replace('\"','"').Replace('\\','\')
      $mode = 'str'
      return
    }
    if ($line -match '^"(.*)"\s*$') {
      $cont = $Matches[1].Replace('\n',[string][char]10).Replace('\"','"').Replace('\\','\')
      if ($mode -eq 'id') { $msgid += $cont }
      elseif ($mode -eq 'str') { $msgstr += $cont }
    }
  }
  if ($msgid -ne $null -and $msgstr -ne $null -and $msgid -ne '') {
    $pairs.Add([pscustomobject]@{ Id = $msgid; Str = $msgstr })
  }
  return $pairs
}

# Simpler robust parser
function Parse-PoSimple($path) {
  $text = [IO.File]::ReadAllText($path)
  $rx = [regex]'(?ms)^msgid\s+"(.*?)"\s*^msgstr\s+"(.*?)"\s*'
  $list = New-Object 'System.Collections.Generic.List[object]'
  foreach ($m in $rx.Matches($text)) {
    $id = $m.Groups[1].Value.Replace('\n',"`n").Replace('\"','"').Replace('\\','\')
    $st = $m.Groups[2].Value.Replace('\n',"`n").Replace('\"','"').Replace('\\','\')
    # Fix: use real newline
    $id = $m.Groups[1].Value -replace '\\n',"`n" -replace '\\"','"' -replace '\\\\','\'
    $st = $m.Groups[2].Value -replace '\\n',"`n" -replace '\\"','"' -replace '\\\\','\'
    if ($id -ne '') { $list.Add([tuple]::Create([string]$id, [string]$st)) }
  }
  return $list
}

$utf8 = [Text.Encoding]::UTF8
$pairs = @()
$msgid = $null
$msgstr = $null
$bufMode = $null
foreach ($raw in [IO.File]::ReadAllLines($poPath, $utf8)) {
  if ($raw -match '^msgid "(.*)"$') {
    if ($null -ne $msgid -and $null -ne $msgstr -and $msgid -ne '') {
      $pairs += ,@($msgid, $msgstr)
    }
    $msgid = $Matches[1] -replace '\\n',"`n" -replace '\\"','"' -replace '\\\\','\'
    $msgstr = $null
    $bufMode = 'id'
    continue
  }
  if ($raw -match '^msgstr "(.*)"$') {
    $msgstr = $Matches[1] -replace '\\n',"`n" -replace '\\"','"' -replace '\\\\','\'
    $bufMode = 'str'
    continue
  }
  if ($raw -match '^"(.*)"$') {
    $c = $Matches[1] -replace '\\n',"`n" -replace '\\"','"' -replace '\\\\','\'
    if ($bufMode -eq 'id') { $msgid += $c }
    elseif ($bufMode -eq 'str') { $msgstr += $c }
  }
}
if ($null -ne $msgid -and $null -ne $msgstr -and $msgid -ne '') {
  $pairs += ,@($msgid, $msgstr)
}

# Sort by original string (required by MO)
$pairs = $pairs | Sort-Object { $_[0] }

$n = $pairs.Count
$origBytes = New-Object 'System.Collections.Generic.List[byte[]]'
$tranBytes = New-Object 'System.Collections.Generic.List[byte[]]'
foreach ($p in $pairs) {
  $ob = $utf8.GetBytes([string]$p[0] + [char]0)
  $tb = $utf8.GetBytes([string]$p[1] + [char]0)
  $origBytes.Add($ob)
  $tranBytes.Add($tb)
}

# Header: 7 uint32 + hash table empty
# magic, revision, N, o_offset, t_offset, hash_size=0, hash_offset
$headerSize = 28
$tableSize = $n * 8 * 2  # two tables of N * (len, offset)
$stringsOffset = $headerSize + $tableSize

$ms = New-Object IO.MemoryStream
$bw = New-Object IO.BinaryWriter $ms

function Write-U32([uint64]$v) {
  $u = [uint32]($v % [uint64]4294967296)
  $bw.Write([BitConverter]::GetBytes($u))
}

# magic 0x950412de little-endian
$bw.Write([byte[]](0xDE,0x12,0x04,0x95))
Write-U32 0
Write-U32 ([uint64]$n)
Write-U32 ([uint64]$headerSize)
Write-U32 ([uint64]($headerSize + $n * 8))
Write-U32 0
Write-U32 ([uint64]$stringsOffset)

# Compute string data offsets
$cur = $stringsOffset
$oLens = @(); $oOffs = @(); $tLens = @(); $tOffs = @()
for ($i=0; $i -lt $n; $i++) {
  $oLens += $origBytes[$i].Length - 1
  $oOffs += $cur
  $cur += $origBytes[$i].Length
}
for ($i=0; $i -lt $n; $i++) {
  $tLens += $tranBytes[$i].Length - 1
  $tOffs += $cur
  $cur += $tranBytes[$i].Length
}

for ($i=0; $i -lt $n; $i++) {
  Write-U32 ([uint32]$oLens[$i])
  Write-U32 ([uint32]$oOffs[$i])
}
for ($i=0; $i -lt $n; $i++) {
  Write-U32 ([uint32]$tLens[$i])
  Write-U32 ([uint32]$tOffs[$i])
}
for ($i=0; $i -lt $n; $i++) { $bw.Write($origBytes[$i]) }
for ($i=0; $i -lt $n; $i++) { $bw.Write($tranBytes[$i]) }

$bw.Flush()
[IO.File]::WriteAllBytes($moPath, $ms.ToArray())
$bw.Dispose(); $ms.Dispose()
Write-Host ("Wrote $moPath ($n strings, $((Get-Item $moPath).Length) bytes)")
