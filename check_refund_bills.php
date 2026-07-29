<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Billing;
use Carbon\Carbon;

$today = Carbon::now()->format('Y-m-d');

$refundBills = Billing::where('status', 'Active')
    ->where('return_amt', '>', 0)
    ->whereDate('created_at', $today)
    ->select(['id', 'bill_number', 'total', 'return_amt', 'created_at'])
    ->get();

echo json_encode([
    'date' => $today,
    'refund_bills_count' => $refundBills->count(),
    'refund_bills' => $refundBills,
    'total_refunds' => $refundBills->sum('return_amt')
], JSON_PRETTY_PRINT);
