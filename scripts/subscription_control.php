<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;
use Carbon\Carbon;

$mode = $argv[1] ?? 'status';
$sub = Subscription::first();

if (! $sub) {
    $sub = Subscription::create([
        'is_active' => false,
        'expires_at' => Carbon::now()->subDay(),
    ]);
    echo "Created subscription record.\n";
}

if ($mode === 'off') {
    $sub->is_active = false;
    $sub->expires_at = Carbon::now()->subDay();
    $sub->save();
    Subscription::clearCurrentCache();
    echo "Subscription set to INACTIVE for testing.\n";
    echo "Run: php scripts/subscription_control.php on  to reactivate.\n";
} elseif ($mode === 'on') {
    $sub->is_active = true;
    $sub->expires_at = Carbon::now()->addYear();
    $sub->save();
    Subscription::clearCurrentCache();
    echo "Subscription set to ACTIVE.\n";
} else {
    echo "Subscription status:\n";
    echo "is_active: " . ($sub->is_active ? 'true' : 'false') . "\n";
    echo "expires_at: " . ($sub->expires_at ? $sub->expires_at : 'null') . "\n";
}
