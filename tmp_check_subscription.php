<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use App\Models\Subscription;

$p = Payment::find(864);
echo 'payment_status=' . ($p ? $p->status : 'missing') . PHP_EOL;
$s = Subscription::getCurrent();
echo 'subscription_active=' . ($s ? ($s->is_active ? 'yes' : 'no') : 'missing') . PHP_EOL;
if ($s) {
    echo 'expires_at=' . $s->expires_at . PHP_EOL;
}
