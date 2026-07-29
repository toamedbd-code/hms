<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;

$sub = Subscription::first();
if (! $sub) {
    echo "No subscription row found.\n";
    exit(1);
}
$sub->is_active = false;
$sub->expires_at = now()->subDays(1);
$sub->save();
Subscription::clearCurrentCache();
echo "Subscription reset to inactive for testing. expires_at=" . $sub->expires_at->toDateTimeString() . "\n";
