# Overwrite legacy PHP entry points with redirect stubs to shell.php
$ErrorActionPreference = 'Stop'
$public = Join-Path (Split-Path $PSScriptRoot -Parent) 'public'

function Write-Redirect($relPath, $page, $extra = @{}) {
  $full = Join-Path $public $relPath
  if (-not (Test-Path $full)) { return }
  # Skip if already a tiny redirect
  $cur = Get-Content $full -Raw -ErrorAction SilentlyContinue
  if ($cur -match 'Location:.*shell\.php' -and ($cur.Length -lt 400)) {
    Write-Host "SKIP (already) $relPath"
    return
  }
  $qs = "page=$page"
  foreach ($k in $extra.Keys) { $qs += "&$k=$($extra[$k])" }
  $depth = ($relPath -split '[\\/]').Count - 1
  $prefix = if ($depth -gt 0) { ('../' * $depth) } else { '' }
  $body = @"
<?php
/**
 * Legacy entry — redirected to modern shell (Painel de Bordo 2.4.0).
 */
header('Location: ${prefix}shell.php?$qs');
exit;
"@
  Set-Content -Path $full -Value $body -Encoding UTF8
  Write-Host "REDIRECT $relPath -> $page"
}

# Old shell
@(
  'sh\main.php','sh\template.php','sh\config1.php','sh\index.php',
  'info.php',
  'map\index.php','map\map_loc.php','map\map_key.php',
  'metrics\indexb.php','metrics\indexw.php','metrics\select_ent.php','metrics\select_grupo.php'
) | ForEach-Object {
  $p = if ($_ -like 'map*') { 'map' } elseif ($_ -like 'metrics*') { 'metrics' } elseif ($_ -eq 'info.php') { 'home' } else { 'home' }
  Write-Redirect $_ $p
}

# Graphs -> modern chart (force rewrite even if already redirect)
function Write-RedirectForce($relPath, $page, $extra = @{}) {
  $full = Join-Path $public $relPath
  if (-not (Test-Path $full)) { return }
  $qs = "page=$page"
  foreach ($k in $extra.Keys) { $qs += "&$k=$($extra[$k])" }
  $depth = ($relPath -split '[\\/]').Count - 1
  $prefix = if ($depth -gt 0) { ('../' * $depth) } else { '' }
  $body = @"
<?php
/**
 * Legacy entry — redirected to modern shell (Painel de Bordo 2.4.2).
 */
header('Location: ${prefix}shell.php?$qs');
exit;
"@
  Set-Content -Path $full -Value $body -Encoding UTF8
  Write-Host "REDIRECT $relPath -> $page $qs"
}

Get-ChildItem (Join-Path $public 'graphs') -Filter '*.php' -File | ForEach-Object {
  $chart = switch -Regex ($_.BaseName) {
    'tecnic|graf_tech|graf_tecnico' { 'tech'; break }
    'grupo'  { 'groups'; break }
    'entid'  { 'entity'; break }
    'categ'  { 'category'; break }
    'prior'  { 'priority'; break }
    'status|geral' { 'status'; break }
    'sla|satisf' { 'sla'; break }
    'evol|times' { 'evolution'; break }
    'usuari|graf_usuario' { 'requester'; break }
    'local' { 'location'; break }
    'tipo|graf_tipo' { 'type'; break }
    'origem|source' { 'source'; break }
    default  { $null }
  }
  if ($chart) { Write-RedirectForce ("graphs\" + $_.Name) 'chart' @{ chart = $chart } }
  else { Write-RedirectForce ("graphs\" + $_.Name) 'charts' }
}

# Reports -> reports hub
Get-ChildItem (Join-Path $public 'reports') -Filter 'rel_*.php' -File | ForEach-Object {
  if ($_.Name -eq 'rel_tickets.php') { return }
  Write-Redirect ("reports\" + $_.Name) 'reports'
}

# Assets drill-downs
Get-ChildItem (Join-Path $public 'assets') -Filter '*.php' -File | ForEach-Object {
  if ($_.Name -eq 'assets.php') { return }
  Write-Redirect ("assets\" + $_.Name) 'assets'
}

Write-Host 'DONE'