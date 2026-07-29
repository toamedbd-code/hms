<?php
// Usage: php scripts/delete_charges.php 1 2 3
// Deletes Charges by ID after asking for confirmation.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Charge;

$ids = array_slice($argv, 1);
if (empty($ids)) {
    echo "Usage: php scripts/delete_charges.php <id1> [id2] [id3]...\n";
    exit(1);
}

echo "Charges to delete: " . implode(', ', $ids) . "\n";
echo "Type 'yes' to confirm: ";
$handle = fopen('php://stdin', 'r');
$line = trim(fgets($handle));
if ($line !== 'yes') {
    echo "Aborted.\n";
    exit(1);
}

foreach ($ids as $id) {
    $c = Charge::find($id);
    if (!$c) {
        echo "Charge ID {$id} not found.\n";
        continue;
    }
    echo "Deleting Charge ID {$id} - {$c->name} ... ";
    try {
        $c->delete();
        echo "done.\n";
    } catch (\Exception $e) {
        echo "failed: " . $e->getMessage() . "\n";
    }
}

echo "Finished.\n";
