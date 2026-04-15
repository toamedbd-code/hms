<#
Download and extract face-api.js models into public/models

Usage (PowerShell):
  .\scripts\download-face-models.ps1

This script attempts to download the models archive from GitHub and extract
the `models` directory into `public/models`.
#>

$ErrorActionPreference = 'Stop'

$outDir = Join-Path $PSScriptRoot "..\public\models"
if (-not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

$tmp = Join-Path $env:TEMP ([System.Guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $tmp | Out-Null

# Official community models archive (may change). If this URL breaks, update it.
$url = 'https://github.com/justadudewhohacks/face-api.js-models/archive/refs/heads/master.zip'
$zip = Join-Path $tmp 'models.zip'

Write-Host "Downloading models from $url ..."
Invoke-WebRequest -Uri $url -OutFile $zip

Write-Host "Extracting..."
Expand-Archive -LiteralPath $zip -DestinationPath $tmp -Force
Write-Host "Extracting..."
Expand-Archive -LiteralPath $zip -DestinationPath $tmp -Force

# try to locate known face-api.js model manifest files in the extracted tree
$knownManifests = @(
    'face_recognition_model-weights_manifest.json',
    'face_landmark_68_model-weights_manifest.json',
    'tiny_face_detector_model-weights_manifest.json',
    'ssd_mobilenetv1_model-weights_manifest.json'
)

$found = $null
foreach ($m in $knownManifests) {
    $f = Get-ChildItem -Path $tmp -Recurse -Filter $m -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($f) { $found = $f.DirectoryName; break }
}

if (-not $found) {
    # Fallback: find any directory that contains .bin files or JSON model files
    $f = Get-ChildItem -Path $tmp -Recurse -Include '*.bin','*_weights_manifest.json','*model.json' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($f) { $found = $f.DirectoryName }
}

if (-not $found) {
    Write-Host "Could not find recognizable model files inside the archive. Please extract manually and place model files into public/models." -ForegroundColor Yellow
    Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
    exit 1
}

Write-Host "Copying models to public/models... (from $found)"
Copy-Item -Path (Join-Path $found '*') -Destination $outDir -Recurse -Force

Write-Host "Cleaning up..."
Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "Models downloaded to public/models"
