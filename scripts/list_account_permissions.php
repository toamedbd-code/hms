<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;

$account = Permission::where('name', 'account-management')->first();
if ($account) {
    echo "Parent permission id: {$account->id}\n";
    $children = Permission::where('parent_id', $account->id)->get();
    foreach ($children as $c) {
        echo "- {$c->id} | {$c->name} | parent_id: {$c->parent_id}\n";
    }
    echo "Total child permissions under account-management: " . $children->count() . "\n\n";
}

// list permissions that contain account or ledger or journal or currency
$q = Permission::where('name','like','%account%')
    ->orWhere('name','like','%ledger%')
    ->orWhere('name','like','%journal%')
    ->orWhere('name','like','%currency%')
    ->orWhere('name','like','%exchange%')
    ->orderBy('id')
    ->get();

echo "Matching permissions (account/ledger/journal/currency/exchange):\n";
foreach ($q as $p) echo "- {$p->id} | {$p->name} | parent: {$p->parent_id}\n";
echo "Total matching: " . $q->count() . "\n";
