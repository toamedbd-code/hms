$sig = Get-Content -Raw 'signature.txt'
$body = Get-Content -Raw 'payload.json'
try {
    $response = Invoke-RestMethod -Uri 'http://localhost/api/attendance/device/webhook' -Method Post -Body $body -ContentType 'application/json' -Headers @{ 'X-Device-Signature' = "sha256=$sig" } -UseBasicParsing -ErrorAction Stop
    $response | ConvertTo-Json -Compress
} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
}
