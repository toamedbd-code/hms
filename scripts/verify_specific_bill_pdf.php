<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$billNo = 'BILL2026030005';
$billing = App\Models\Billing::query()->where('bill_number', $billNo)->first();

if (!$billing) {
    echo "billing_not_found={$billNo}\n";
    exit(1);
}

$modules = ['billing', 'pathology', 'radiology', 'pharmacy', ''];
$controller = app(App\Http\Controllers\Backend\InvoiceController::class);

foreach ($modules as $module) {
    $request = Illuminate\Http\Request::create('/download-invoice', 'GET', [
        'id' => $billing->id,
        'module' => $module,
    ]);

    $response = $controller->downloadInvoice($request);
    $pdfData = $response->getContent();
    $imageCount = substr_count((string)$pdfData, '/Subtype /Image');

    echo "bill_id={$billing->id} module=" . ($module === '' ? 'empty' : $module) . " bytes=" . strlen((string)$pdfData) . " images={$imageCount}\n";
}
