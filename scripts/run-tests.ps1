<#
Run project tests for developers (Windows/PowerShell).

Behavior:
 - If PHP has `pdo_sqlite` available, runs tests using in-memory SQLite.
 - Otherwise attempts to create a MySQL test database (defaults to `hms_test`) and runs tests against it.

Usage:
  .\scripts\run-tests.ps1
  .\scripts\run-tests.ps1 -DBUser root -DBPass '' -DBHost 127.0.0.1 -DBName hms_test

Note: Ensure `mysql` CLI is in PATH when falling back to MySQL.
#>

param(
    [string]$DBUser = $env:DB_USERNAME,
    [string]$DBPass = $env:DB_PASSWORD,
    [string]$DBHost = $env:DB_HOST,
    [string]$DBName = $env:DB_DATABASE_TEST
)

if ([string]::IsNullOrEmpty($DBUser)) { $DBUser = 'root' }
if ($null -eq $DBPass) { $DBPass = '' }
if ([string]::IsNullOrEmpty($DBHost)) { $DBHost = '127.0.0.1' }
if ([string]::IsNullOrEmpty($DBName)) { $DBName = 'hms_test' }

Write-Host "Checking for PHP pdo_sqlite extension..."
$hasSqlite = & php -r "echo extension_loaded('pdo_sqlite') ? '1' : '0';" 2>$null
if ($LASTEXITCODE -ne 0) { $hasSqlite = '0' }

if ($hasSqlite -eq '1') {
    Write-Host "pdo_sqlite detected — running tests with in-memory SQLite..."
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = ':memory:'
    php vendor\bin\phpunit --testsuite Unit --colors=never
    exit $LASTEXITCODE
}

Write-Host "pdo_sqlite not available. Falling back to MySQL test DB: $DBName"

# Check mysql CLI
$mysqlExists = (Get-Command mysql -ErrorAction SilentlyContinue) -ne $null
if (-not $mysqlExists) {
    Write-Error "mysql CLI not found in PATH. Install MySQL client or enable pdo_sqlite in PHP."
    exit 1
}

$args = @("-u$DBUser", "-h$DBHost")
if (-not [string]::IsNullOrEmpty($DBPass)) { $args += "-p$DBPass" }

$createSql = "CREATE DATABASE IF NOT EXISTS `$DBName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
try {
    & mysql @args -e $createSql
} catch {
    Write-Error "Failed to create database. $_"
    exit 1
}

# Run tests using MySQL test DB
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = $DBName
$env:DB_USERNAME = $DBUser
$env:DB_PASSWORD = $DBPass
$env:DB_HOST = $DBHost

php vendor\bin\phpunit --testsuite Unit --colors=never
exit $LASTEXITCODE
