# Run full local test suite (requires PHP 8.2+ in PATH)
$ErrorActionPreference = "Stop"
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
$php = (Get-Command php -ErrorAction Stop).Source
$root = Split-Path $PSScriptRoot -Parent
Write-Host "PHP: $php"
& $php "$PSScriptRoot\run_all.php"
exit $LASTEXITCODE
