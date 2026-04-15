<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\WebSetting;
$ws = WebSetting::first();
if (!$ws) {
    echo "<no websetting>\n";
    exit(0);
}
$val = trim((string) $ws->website_services_json);
echo ($val === '') ? "<empty>\n" : $val . "\n";
