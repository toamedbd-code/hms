<#
Download specific face-api.js model manifests and shards from GitHub raw.
This script fetches manifests for: tiny_face_detector, face_landmark_68, face_recognition
and downloads the referenced shard files into public/models.
#>

$ErrorActionPreference = 'Stop'

$outDir = Join-Path $PSScriptRoot "..\public\models"
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir -Force | Out-Null }

$base = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js-models/master'
$models = @(
    'tiny_face_detector_model-weights_manifest.json',
    'face_landmark_68_model-weights_manifest.json',
    'face_recognition_model-weights_manifest.json'
)

foreach ($m in $models) {
    $url = "$base/$m"
    Write-Host "Fetching manifest: $m"
    try {
        $json = Invoke-RestMethod -Uri $url -UseBasicParsing -ErrorAction Stop
    } catch {
        Write-Warning "Manifest not found: $url"
        continue
    }

    $manifestPath = Join-Path $outDir $m
    $json | ConvertTo-Json -Depth 99 | Out-File -FilePath $manifestPath -Encoding UTF8

    # each entry in weights contains 'paths' array with file names
    foreach ($w in $json.weights) {
        foreach ($p in $w.paths) {
            $fileUrl = "$base/$p"
            $dest = Join-Path $outDir $p
            if (-not (Test-Path $dest)) {
                Write-Host "Downloading $p ..."
                try {
                    Invoke-WebRequest -Uri $fileUrl -OutFile $dest -UseBasicParsing -ErrorAction Stop
                } catch {
                    Write-Warning "Failed to download $fileUrl"
                }
            } else {
                Write-Host "$p already exists, skipping"
            }
        }
    }
}

Write-Host "Done. Files in: $outDir"
