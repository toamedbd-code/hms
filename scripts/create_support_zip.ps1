$now = Get-Date -Format yyyyMMdd_HHmmss
$files = @(
    "storage\logs\bkash-signed-dump-20260728_153004.json",
    "storage\logs\bkash-test-dump-20260728_152426.json",
    "storage\logs\bkash-support-message.txt"
)
$dest = Join-Path "storage\logs" ("bkash-support-package-" + $now + ".zip")
Compress-Archive -Path $files -DestinationPath $dest -Force
Write-Output $dest