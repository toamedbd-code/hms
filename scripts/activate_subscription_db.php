<?php
// Usage: php scripts/activate_subscription_db.php
// Boots the framework and creates/updates a Subscription record to be active.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use Carbon\Carbon;

echo "Bootstrapped Laravel application.\n";

$sub = Subscription::first();
if (! $sub) {
    // use a safe future date within MySQL TIMESTAMP range (add 10 years)
    $sub = Subscription::create([
        'is_active' => true,
        'expires_at' => Carbon::now()->addYears(10),
    ]);
    echo "Created subscription (id={$sub->id}).\n";
} else {
    $sub->is_active = true;
    $sub->expires_at = Carbon::now()->addYears(10);
    $sub->save();
    echo "Updated subscription (id={$sub->id}).\n";
}

echo "Subscription active until: " . $sub->expires_at->toDateTimeString() . "\n";
