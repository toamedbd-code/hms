<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Models\InvoiceDesign;

$id = $argv[1] ?? 10;
$billing = Billing::with(['patient'])->find($id);
if (! $billing) { echo "no-billing\n"; exit(1); }

$module = 'billing';
$invoiceDesign = InvoiceDesign::where('status', 'Active')
    ->where(function($q) use($module){
        $q->whereRaw('LOWER(TRIM(module)) = ?', [strtolower($module)])
          ->orWhereNull('module');
    })->first();

echo "Billing ID: $id\n";
echo "Patient name: " . ($billing->patient->name ?? 'N/A') . "\n";
echo "InvoiceDesign ID: " . ($invoiceDesign?->id ?? 'none') . "\n";
echo "Footer content (raw):\n";
echo $invoiceDesign?->footer_content . "\n";
echo "Footer image path: " . ($invoiceDesign?->footer_photo_path ?? 'none') . "\n";
