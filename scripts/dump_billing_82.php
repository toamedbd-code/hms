<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;

$id = 82;
$b = Billing::find($id);
if (!$b) {
    echo json_encode(['error' => "Billing {$id} not found"]);
    exit(0);
}

$items = $b->billItems->map(function($it){
    return [
        'id' => $it->id,
        'name' => $it->item_name,
        'item_id' => $it->item_id,
        'category' => $it->category,
        'params' => ($it->parameters ?? null),
        'groups' => ($it->parameter_groups ?? null),
        'saved' => ($it->saved_parameter_values ?? null),
    ];
})->toArray();

// Also include the referenced Test record (if any) for the first pathology item
$firstItem = $b->billItems->first();
$testInfo = null;
if ($firstItem && $firstItem->item_id) {
    $t = \App\Models\Test::find($firstItem->item_id);
    if ($t) {
        $testInfo = [
            'id' => $t->id,
            'test_name' => $t->test_name ?? null,
            'test_short_name' => $t->test_short_name ?? null,
            'test_parameters' => $t->test_parameters ?? null,
        ];
    }
}

// Recreate synthesized parameters + grouping as in ReportingController
$synthParams = [];
if ($testInfo) {
    $tname = trim(strtolower($testInfo['test_name'] ?? ''));
    $iname = trim(strtolower($firstItem->item_name ?? ''));
    $isUrineName = (str_contains($iname, 'urine') || str_contains($iname, 'r/e') || str_contains($iname, 'm/e') || str_contains($iname, 'r e'));
    if (($tname !== '' && str_contains($tname, 'urine')) || $isUrineName) {
        $synth = [
            // Physical
            'Colour', 'Appearance', 'Sediment', 'Specific gravity',
            // Chemical
            'Reaction', 'Phosphate', 'Albumin', 'Sugar', 'Bile Salt', 'Bile Pigment', 'Ketone body',
            // Microscopic
            'Pus cell', 'Epithelial cell', 'RBC', 'RBC Cast', 'Bacteria', 'Hyaline Cast',
            // Crystals
            'Cal-oxalate', 'Triple phosphate', 'Uric Acid', 'Amorphous Phosphate'
        ];

        foreach ($synth as $name) {
            $synthParams[] = [
                'id' => 'gen:' . preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($name)),
                'name' => $name,
                'reference_from' => '',
                'reference_to' => '',
                'unit' => '',
                'generated' => true,
            ];
        }
    }
}

$groupRules = [
    'Physical Examination' => ['colour', 'color', 'appearance', 'sediment', 'specific gravity', 'sg'],
    'Microscopic Examination' => ['pus', 'pus cell', 'pus cells', 'epithelial', 'epithelial cell', 'epithelial cells', 'rbc', 'rbc cast', 'rbc casts', 'bacteria', 'hyaline', 'crystal', 'casts', 'oxalate', 'triple', 'uric', 'cal-oxalate', 'amorphous'],
    'Chemical Examination' => ['reaction', 'phosphate', 'albumin', 'sugar', 'bile', 'bile salt', 'bile pigment', 'ketone', 'ph', 'bilirubin'],
];

$grouped = [];
foreach (array_keys($groupRules) as $g) {
    $grouped[$g] = [];
}
$grouped['Other'] = [];

foreach ($synthParams as $p) {
    $placed = false;
    $lname = mb_strtolower($p['name'] ?? '');
    foreach ($groupRules as $g => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_stripos($lname, $kw) !== false) {
                $grouped[$g][] = $p;
                $placed = true;
                break 2;
            }
        }
    }
    if (!$placed) {
        $grouped['Other'][] = $p;
    }
}

$paramGroups = [];
foreach ($grouped as $title => $rows) {
    $paramGroups[] = ['title' => $title, 'parameters' => array_values($rows)];
}

echo json_encode(['items'=>$items, 'first_test'=>$testInfo, 'synth_params'=>$synthParams, 'parameter_groups'=>$paramGroups], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
