<?php
// Temporary updater: set reporting.show_footer=false for web_settings id=3
$id = 3;
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WebSetting;

$ws = WebSetting::find($id);
if (! $ws) {
    echo "WebSetting id={$id} not found\n";
    exit(1);
}

$ws->attendance_device_options = [
    'reporting' => [
        'show_header' => true,
        'show_footer' => false,
        'layout' => [
            'header_height' => 115,
            'footer_height' => 0,
            'page_margin_top' => 0,
            'page_margin_bottom' => 0,
        ],
        'signature' => [
            'margin_top' => 160,
            'margin_left' => 96,
        ],
    ],
];

$ws->save();
echo "WebSetting id={$id} updated (reporting.show_footer=false)\n";
