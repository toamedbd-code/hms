<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find all bill items that could be "print/5" context
// First look at bill_item_id=5 directly
$results = \App\Models\BillItemParameterResult::where('bill_item_id', 5)->get();
echo "BillItemParameterResult count for bill_item_id=5: " . $results->count() . "\n";

foreach ($results as $r) {
    $param = $r->pathology_test_parameter_id ? \App\Models\PathologyTestParameter::find($r->pathology_test_parameter_id) : null;
    echo "Name: {$r->name}, Value: {$r->value}, Unit: {$r->unit}\n";
    if ($param) {
        echo "  ref_from: [{$param->reference_from}], ref_to: [{$param->reference_to}]\n";
        // Simulate parseNumeric
        $parseNum = function(?string $s): ?float {
            if ($s === null) return null;
            $s = trim((string) $s);
            if ($s === '') return null;
            $s = str_replace(',', '', $s);
            if (preg_match('/-?\d+(?:\.\d+)?/', $s, $m)) {
                return (float) $m[0];
            }
            return null;
        };
        $val = trim((string) ($r->value ?? ''));
        $from = trim((string) ($param->reference_from ?? ''));
        $to = trim((string) ($param->reference_to ?? ''));
        $valNum = $parseNum($val);
        $fromNum = $parseNum($from);
        $toNum = $parseNum($to);
        echo "  valNum=$valNum, fromNum=$fromNum, toNum=$toNum\n";
        $isOut = false;
        if ($valNum !== null) {
            if ($fromNum !== null && $toNum !== null) {
                $low = min($fromNum, $toNum);
                $high = max($fromNum, $toNum);
                if ($valNum < $low || $valNum > $high) $isOut = true;
            } elseif ($fromNum !== null) {
                if ($valNum < $fromNum) $isOut = true;
            } elseif ($toNum !== null) {
                if ($valNum > $toNum) $isOut = true;
            }
        }
        echo "  isOut=" . ($isOut ? 'TRUE' : 'false') . "\n";
    } else {
        echo "  No param (id: {$r->pathology_test_parameter_id})\n";
    }
}
