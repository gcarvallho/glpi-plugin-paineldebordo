<#
.SYNOPSIS
    Gera o .zip de distribuicao do Painel de Bordo.

.DESCRIPTION
    Empacota o plugin com a estrutura que o GLPI espera (raiz interna
    "paineldebordo/") aplicando as regras aprendidas na pratica:

    - Caminhos com BARRA NORMAL (/) dentro do zip. Compress-Archive e
      ZipFile::CreateFromDirectory gravam "\" no Windows, o que quebra a
      extracao no Linux -- por isso as entradas sao criadas manualmente.
    - Exclui material que nao e distribuicao: _legacy_ref/ (museu de
      referencia), docs/img/ (capturas do README, ~1 MB que so interessam
      no GitHub), .git/, .claude/ e Thumbs.db.
    - Confere o resultado antes de entregar: conta entradas, garante zero
      barras invertidas e nenhum arquivo proibido.

    NOTA: mantenha este arquivo em ASCII puro. O Windows PowerShell 5.1 le
    scripts como ANSI e acentos/travessoes quebram o parser.

.PARAMETER OutputDir
    Onde salvar o .zip. Padrao: a pasta acima do plugin.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\package.ps1
#>
[CmdletBinding()]
param(
    [string] $OutputDir
)

$ErrorActionPreference = 'Stop'

$pluginRoot = Split-Path -Parent $PSScriptRoot
$pluginName = Split-Path -Leaf $pluginRoot
if (-not $OutputDir) { $OutputDir = Split-Path -Parent $pluginRoot }

# Versao vem do setup.php -- nunca digitada a mao, para nao divergir.
$setup = Get-Content (Join-Path $pluginRoot 'setup.php') -Raw
if ($setup -notmatch "'version'\s*=>\s*'([^']+)'") {
    throw "Nao foi possivel ler a versao em setup.php"
}
$version = $Matches[1]
Write-Host "Painel de Bordo $version" -ForegroundColor Cyan

$stage = Join-Path ([System.IO.Path]::GetTempPath()) ("pdb_pkg_" + [Guid]::NewGuid().ToString('N'))
$stagePlugin = Join-Path $stage $pluginName
New-Item -ItemType Directory -Force -Path $stagePlugin | Out-Null

# /XD = pastas excluidas, /XF = arquivos excluidos.
# robocopy usa exit codes < 8 para sucesso (1 = arquivos copiados).
$excludeDirs = @(
    '_legacy_ref',
    '.git',
    '.claude',
    '_tmp_probe',
    'staging_pkg',
    (Join-Path $pluginRoot 'docs\img')
)
$excludeFiles = @('Thumbs.db', 'desktop.ini')
robocopy $pluginRoot $stagePlugin /E /XD @excludeDirs /XF @excludeFiles /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy falhou (exit $LASTEXITCODE)" }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipPath = Join-Path $OutputDir "$pluginName-$version.zip"
if (Test-Path -LiteralPath $zipPath) { Remove-Item -Force -LiteralPath $zipPath }

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in (Get-ChildItem -Recurse -Force -File $stage)) {
        $rel = $file.FullName.Substring($stage.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip, $file.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $zip.Dispose()
}

Remove-Item -Recurse -Force -LiteralPath $stage

# Verificacao: um zip errado so aparece na hora de instalar no servidor.
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $entries     = $zip.Entries
    $backslashes = @($entries | Where-Object { $_.FullName -match '\\' }).Count
    $forbidden   = @($entries | Where-Object { $_.FullName -match '_legacy_ref|Thumbs\.db|\.git/|docs/img/' }).Count
    $hasSetup    = @($entries | Where-Object { $_.FullName -eq "$pluginName/setup.php" }).Count

    Write-Host ("  entradas............ " + $entries.Count)
    Write-Host ("  barras invertidas... " + $backslashes + " (esperado 0)")
    Write-Host ("  arquivos proibidos.. " + $forbidden + " (esperado 0)")
    Write-Host ("  setup.php na raiz... " + $hasSetup + " (esperado 1)")

    if ($backslashes -gt 0) { throw "Zip contem barras invertidas: nao extrai no Linux." }
    if ($forbidden -gt 0)   { throw "Zip contem arquivos que nao deveriam ser distribuidos." }
    if ($hasSetup -ne 1)    { throw "Zip sem $pluginName/setup.php na raiz." }
} finally {
    $zip.Dispose()
}

$sizeMb = [math]::Round((Get-Item -LiteralPath $zipPath).Length / 1MB, 2)
Write-Host ("OK: " + $zipPath + " (" + $sizeMb + " MB)") -ForegroundColor Green
