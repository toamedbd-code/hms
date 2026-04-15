# NSSM installer script for HMS queue worker
# Usage: Run in an elevated PowerShell prompt: .\scripts\install-nssm-hms-queue.ps1

$phpPath = 'C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php.exe'
$appPath = 'C:\laragon\www\hms'
$serviceName = 'hms-queue-worker'
$logPath = Join-Path $appPath 'storage\logs\queue-worker.log'
$queueRunner = Join-Path $appPath 'scripts\run-queue-worker.bat'
$nssmUrl = 'https://nssm.cc/release/nssm-2.24.zip'

function New-DirectoryIfMissing($p){ if(-not (Test-Path $p)){ New-Item -ItemType Directory -Path $p -Force | Out-Null } }

# Prepare temp folder
$temp = Join-Path $env:TEMP 'nssm-install'
New-DirectoryIfMissing $temp
$zip = Join-Path $temp 'nssm.zip'

try {
    Write-Output "Preparing log file and folders..."
    New-DirectoryIfMissing (Split-Path $logPath)
    if (-not (Test-Path $logPath)) { New-Item -ItemType File -Path $logPath -Force | Out-Null }

    Write-Output "Checking for existing service '$serviceName'..."
    $svc = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ($svc) {
        Write-Output "Service exists - stopping and removing..."
        if ($svc.Status -eq 'Running') { Stop-Service -Name $serviceName -Force -ErrorAction SilentlyContinue }
        $installedNssm = Get-Command nssm -ErrorAction SilentlyContinue
        if ($installedNssm) { & $installedNssm.Source remove $serviceName confirm | Out-Null }
        else { sc.exe delete $serviceName | Out-Null }
        Start-Sleep -Seconds 1
    }

    Write-Output "Downloading NSSM from $nssmUrl..."
    Invoke-WebRequest -Uri $nssmUrl -OutFile $zip -UseBasicParsing -ErrorAction Stop

    Write-Output "Extracting NSSM..."
    Expand-Archive -LiteralPath $zip -DestinationPath $temp -Force

    Write-Output "Locating nssm.exe..."
    $nssmExe = Get-ChildItem -Path $temp -Filter nssm.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $nssmExe) { Write-Error "nssm.exe not found after extraction."; exit 1 }
    $nssmPath = $nssmExe.FullName
    Write-Output "Found nssm.exe at $nssmPath"

    # Install service using batch runner that verifies APP_KEY before starting worker.
    $serviceCmd = 'cmd.exe'
    $serviceArgs = '/c "' + $queueRunner + '"'

    Write-Output "Installing service '$serviceName'..."
    & $nssmPath install $serviceName $serviceCmd $serviceArgs

    Write-Output "Configuring service settings..."
    & $nssmPath set $serviceName AppDirectory $appPath
    & $nssmPath set $serviceName AppStdout $logPath
    & $nssmPath set $serviceName AppStderr $logPath
    & $nssmPath set $serviceName AppRotateFiles 1

    Write-Output "Starting service '$serviceName'..."
    & $nssmPath start $serviceName

    Write-Output "Service installed and started. Check logs at $logPath"
    Exit 0
}
catch {
    Write-Error $_.Exception.Message
    Exit 1
}
