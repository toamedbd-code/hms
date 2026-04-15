<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$ws = App\Models\WebSetting::query()->orderByDesc('id')->first();
if (!$ws) {
    echo "no-websetting\n";
    exit;
}
$rawLogo = (string) ($ws->getRawOriginal('logo') ?? '');
$rawIcon = (string) ($ws->getRawOriginal('icon') ?? '');
$pick = trim($rawLogo !== '' ? $rawLogo : $rawIcon);
$storagePath = $pick !== '' ? storage_path('app/public/' . ltrim($pick, '/')) : '';
$publicPath = $pick !== '' ? public_path(ltrim($pick, '/')) : '';
echo "raw_logo={$rawLogo}\n";
echo "raw_icon={$rawIcon}\n";
echo "chosen={$pick}\n";
echo "storage_exists=" . (($storagePath && file_exists($storagePath)) ? 'yes' : 'no') . "\n";
echo "public_exists=" . (($publicPath && file_exists($publicPath)) ? 'yes' : 'no') . "\n";
echo "logo_accessor=" . ($ws->logo ?? '') . "\n";
echo "icon_accessor=" . ($ws->icon ?? '') . "\n";
