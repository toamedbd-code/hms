<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\Payment;
$p = Payment::orderBy('id','desc')->first();
if (! $p) { echo "no payments\n"; exit(1);} 
echo "id=".$p->id."\n";
echo "amount=".$p->amount."\n";
echo "status=".$p->status."\n";
echo "metadata=".json_encode($p->metadata)."\n";
