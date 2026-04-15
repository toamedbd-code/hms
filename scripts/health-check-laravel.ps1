param(
    [switch]$AutoFix
)

$ErrorActionPreference = 'Stop'

function Write-Step($msg) {
    Write-Output "[STEP] $msg"
}

function Write-Ok($msg) {
    Write-Output "[OK] $msg"
}

function Write-WarnMsg($msg) {
    Write-Output "[WARN] $msg"
}

function Write-Fail($msg) {
    Write-Output "[FAIL] $msg"
}

function Resolve-PHPExe {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    $candidates = @(
        'C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php.exe',
        'C:\laragon\bin\php\php-8.2.0-Win32-vs16-x64\php.exe'
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

$appRoot = Split-Path -Parent $PSScriptRoot
Set-Location $appRoot

Write-Step "Laravel health check শুরু: $appRoot"

$phpExe = Resolve-PHPExe
if (-not $phpExe) {
    Write-Fail "PHP executable পাওয়া যায়নি"
    exit 1
}
Write-Ok "PHP: $phpExe"

$envPath = Join-Path $appRoot '.env'
if (-not (Test-Path $envPath)) {
    Write-Fail ".env file পাওয়া যায়নি"
    exit 1
}
Write-Ok ".env file পাওয়া গেছে"

$envLine = Select-String -Path $envPath -Pattern '^APP_KEY=' | Select-Object -First 1
$hasEnvKey = $false
if ($envLine) {
    $raw = ($envLine.Line -replace '^APP_KEY=', '').Trim()
    $hasEnvKey = -not [string]::IsNullOrWhiteSpace($raw)
}

if ($hasEnvKey) {
    Write-Ok "APP_KEY .env এ সেট করা আছে"
} else {
    Write-WarnMsg "APP_KEY .env এ missing/empty"
    if ($AutoFix) {
        Write-Step "APP_KEY generate করা হচ্ছে..."
        & $phpExe artisan key:generate --force | Out-Host
        $envLine = Select-String -Path $envPath -Pattern '^APP_KEY=' | Select-Object -First 1
        $raw = if ($envLine) { ($envLine.Line -replace '^APP_KEY=', '').Trim() } else { '' }
        if ([string]::IsNullOrWhiteSpace($raw)) {
            Write-Fail "APP_KEY generate failed"
            exit 1
        }
        Write-Ok "APP_KEY generate সম্পন্ন"
    } else {
        Write-Fail "APP_KEY missing. Auto fix এর জন্য -AutoFix ব্যবহার করুন"
        exit 1
    }
}

Write-Step "Cache পরিষ্কার করা হচ্ছে"
& $phpExe artisan optimize:clear | Out-Host

Write-Step "Runtime environment verify"
& $phpExe artisan env | Out-Host

$configKey = (& $phpExe artisan tinker --execute "echo empty(config('app.key')) ? 'CONFIG_KEY_EMPTY' : 'CONFIG_KEY_OK';")
if ($configKey -match 'CONFIG_KEY_OK') {
    Write-Ok "Runtime config('app.key') ঠিক আছে"
} else {
    Write-Fail "Runtime config('app.key') empty"
    exit 1
}

$schedulerPath = Join-Path $appRoot 'scripts\run-scheduler.bat'
if (Test-Path $schedulerPath) {
    Write-Ok "Scheduler script পাওয়া গেছে"
} else {
    Write-WarnMsg "Scheduler script পাওয়া যায়নি"
}

$queueRunnerPath = Join-Path $appRoot 'scripts\run-queue-worker.bat'
if (Test-Path $queueRunnerPath) {
    Write-Ok "Queue runner script পাওয়া গেছে"
} else {
    Write-WarnMsg "Queue runner script পাওয়া যায়নি"
}

$queueService = Get-Service -Name 'hms-queue-worker' -ErrorAction SilentlyContinue
if ($queueService) {
    Write-Ok "Queue service status: $($queueService.Status)"
} else {
    Write-WarnMsg "Queue service install করা নেই (hms-queue-worker)"
}

Write-Output "[DONE] Laravel health check সফল"
exit 0
