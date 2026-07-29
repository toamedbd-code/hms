<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function j($v){ echo json_encode($v, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . PHP_EOL; }

$b = \App\Models\Billing::find(98);
echo "BILLING="; j($b ? $b->toArray() : null);
echo "PAYMENTS="; j(\App\Models\Payment::where('billing_id',98)->get()->toArray());
echo "PATHOLOGY="; j(\App\Models\Pathology::where('bill_no','BILL2026060098')->get()->toArray());
echo "RADIOLOGY="; j(\App\Models\Radiology::where('bill_no','BILL2026060098')->get()->toArray());
echo "PHARMACY="; j(\App\Models\PharmacyBill::where('bill_no','BILL2026060098')->get()->toArray());
echo "REFERRAL="; j(\App\Models\Referral::where('billing_id',98)->get()->toArray());
echo "EXPENSE="; j(\App\Models\Expense::where('bill_number','BILL2026060098')->get()->toArray());
