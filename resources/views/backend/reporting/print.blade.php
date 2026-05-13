<!DOCTYPE html>
<html lang="en">
@php
    $signatureMarginTop = max((int) ($signatureMarginTop ?? 160), 0);
    $signatureMarginLeft = max((int) ($signatureMarginLeft ?? 96), 0);
    $pageMarginTop = max((int) ($pageMarginTop ?? 0), 0);
    $barcodeValue = (string) ($billing->bill_number ?? ('BILL-' . ($billing->id ?? '')));
    $barcodePng = DNS1D::getBarcodePNG($barcodeValue, 'C128', 1.8, 52);
    $barcodeDataUri = 'data:image/png;base64,' . $barcodePng;
    $isUltrasonogramReport = (bool) ($isUltrasonogramReport ?? false);
    $fullPageMarker = '[[FULL_PAGE]]';
    $primaryRawNote = trim((string) ($primaryItem->report_note ?? ''));
    $hasFullPageMarker = str_starts_with($primaryRawNote, $fullPageMarker);
    $primaryNoteBody = $hasFullPageMarker
        ? trim(substr($primaryRawNote, strlen($fullPageMarker)))
        : $primaryRawNote;

    $detectText = strtolower(trim(
        (string) ($primaryItem->item_name ?? '') . ' '
        . (string) ($primaryItem->category ?? '') . ' '
        . (string) ($reportTitle ?? '')
    ));
    $isXrayReport = str_contains($detectText, 'xray')
        || str_contains($detectText, 'x-ray')
        || str_contains($detectText, 'radiography');
    $isFullPageReport = $isUltrasonogramReport || $isXrayReport || $hasFullPageMarker;
    $noteLooksHtml = preg_match('/<[^>]+>/', $primaryNoteBody) === 1;
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Print</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 0; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 0; font-size: 16px; line-height: 1.3; }
        .title { font-size: 18px; font-weight: bold; }
        .report-title { font-size: 20px; font-weight: bold; font-family: Verdana, Geneva, Tahoma, sans-serif; margin: 0; letter-spacing: 2px; }
        .meta { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .section { margin-top: 16px; }
        .label { font-weight: 600; }
        .note { white-space: pre-wrap; border: 1px solid #e5e7eb; padding: 10px; border-radius: 6px; min-height: 120px; }
        .header-section {
            width: 100%;
            padding-left: 0;
            padding-right: 0;
            margin-top: var(--report-page-top-margin, 0px);
            text-align: center;
            margin-bottom: 5px;
            height: var(--report-header-height, 115px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-placeholder { width: 100%; height: var(--report-header-height, 115px); visibility: hidden; }
        .header-image { width: 100%; height: 100%; object-fit: fill; display: block; }
        .patient-details-table td { font-size: 12px; }
        .title-section-table { width: 100%; margin-bottom: 12px; }
        .barcode-cell-left { width: 20%; text-align: left; vertical-align: top; }
        .barcode-cell-right { width: 20%; text-align: right; vertical-align: top; }
        .title-cell-center { width: 60%; text-align: center; }
        .barcode-image { width: 150px; height: 34px; display: block; }
        .content-section {
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            padding-bottom: 110px;
        }
        .footer-section {
            position: static;
            width: 100%;
            padding-left: 0;
            padding-right: 0;
            text-align: center;
            padding-bottom: 0;
            min-height: var(--report-footer-height, 70px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }
        .footer-placeholder { width: 100%; height: var(--report-footer-height, 70px); visibility: hidden; }
        .footer-image { width: 100%; height: auto; max-height: var(--report-footer-height, 70px); object-fit: contain; display: block; }
        .footer-content { text-align: center; width: 100%; }
        .footer-date-time { font-size: 12px; color: #4b5563; margin-bottom: 4px; }

        .ultra-test-name {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .ultra-report-body {
            min-height: 220px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 12px;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            page-break-inside: auto;
            break-inside: auto;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .ultra-report-body table {
            width: 100% !important;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
            break-inside: auto;
        }
        .ultra-report-body tr,
        .ultra-report-body td,
        .ultra-report-body th {
            page-break-inside: avoid;
            break-inside: avoid;
            vertical-align: top;
        }
        .ultra-report-body img {
            max-width: 100% !important;
            height: auto !important;
        }
        .ultra-range {
            margin-top: 10px;
            font-size: 12px;
        }
        .ultra-layout .content-section { padding-bottom: 80px; }
        .ultra-layout .signature-row {
            margin-top: 24px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .ultra-layout .footer-image { max-height: 56px; }
        .ultra-layout .signature-image,
        .ultra-layout .signature-top-line {
            height: 46px;
        }

        /* Paper-size locking to keep report print identical across printers */
        @media print and (min-width: 149mm) {
            .header-section { height: 1.2in; }
            .header-placeholder { height: 1.2in; }
            .header-image { height: 100%; }
            .footer-placeholder { height: 70px; }
            .footer-image { max-height: 80px; }
            .content-section { padding-bottom: 110px; }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body { font-size: 12px; }
            .header-section { height: 1.2in; }
            .header-placeholder { height: 1.2in; }
            .header-image { height: 100%; }
            .footer-placeholder { height: 52px; }
            .footer-image { max-height: 56px; }
            .footer-date-time { font-size: 10px; margin-bottom: 2px; }
            .content-section { padding-bottom: 88px; }
            .report-title { font-size: 16px; }
        }
        .signature-block {
            font-size: 12px;
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .signature-row {
            margin-top: var(--signature-top-margin, 160px);
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            width: 100%;
        }
        .signature-row .signature-block {
            flex: 1 1 0;
            max-width: 33%;
            min-width: 140px;
            text-align: center;
        }
        .signature-row > div[style*="flex: 1 1 0;"] {
            /* keep center placeholder proportional */
            flex: 1 1 0;
            max-width: 33%;
        }
        .signature-top-line {
            width: 150px;
            height: 56px;
            border-bottom: 1px solid #6b7280;
            margin-bottom: 8px;
            margin-left: auto;
            margin-right: auto;
        }
        /* Name underline that matches the name width */
        .name-with-line {
            display: inline-block;
            border-top: 1px solid #111827;
            padding-top: 6px;
            margin-top: 6px;
            white-space: pre-line;
            font-weight: 700; /* force bold */
        }
        .signature-image {
            width: 150px;
            height: 56px;
            object-fit: contain;
            display: block;
            margin: 0 auto 8px auto;
        }
        .signature-block .meta {
            font-size: 12px;
            word-break: break-word;
        }
        .signature-line { display: none; }
        .signature-block .label { min-height: 18px; width: 100%; }
        .signature-block .meta { min-height: 16px; width: 100%; text-align: center; }
        .signature-block .meta.multiline { white-space: pre-line; }
        @media print {
            .content-section { padding-bottom: 72px; }
            .footer-section {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
            }
            .ultra-layout .content-section { padding-bottom: 56px; }
            .ultra-layout .signature-row {
                margin-top: 12px;
                margin-bottom: 8px;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .ultra-layout .ultra-report-body {
                min-height: 0;
            }
        }

        
    </style>
</head>
<body class="{{ $isUltrasonogramReport ? 'ultra-layout' : '' }}" style="--report-left-margin: {{ $signatureMarginLeft }}px; --signature-top-margin: {{ $signatureMarginTop }}px; --report-page-top-margin: {{ $pageMarginTop }}px; --report-header-height: {{ $reportHeaderHeight ?? 115 }}px; --report-footer-height: {{ $reportFooterHeight ?? 70 }}px;">
    <div class="header-section">
        @if(!empty($header_image))
            <img src="{{ $header_image }}" alt="Header" class="header-image">
        @else
            <div class="header-placeholder"></div>
        @endif
    </div>

    <div class="content-section">
    <table class="title-section-table">
        <tr>
            <td class="barcode-cell-left">
                <img src="{{ $barcodeDataUri }}" alt="Barcode Left" class="barcode-image">
            </td>
            <td class="title-cell-center">
                <div class="report-title">{{ strtoupper((string) ($reportTitle ?? 'Test Report')) }}</div>
            </td>
            <td class="barcode-cell-right">
                <img src="{{ $barcodeDataUri }}" alt="Barcode Right" class="barcode-image" style="margin-left:auto;">
            </td>
        </tr>
    </table>
    <table class="patient-details-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Bill No</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $billing->bill_number ?? 'N/A' }}</td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Date & Time</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $reportDateTime }}</td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Name</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $patientName }}</td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Age</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $age }}</td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Contact No</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $contact_no }}</td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Gender</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $gender }}</td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Refd. By</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td colspan="4" style="width: 78%; vertical-align: top; padding: 2px 0;">{{ $refd_by }}</td>
        </tr>
    </table>

    <div class="section">
        @if($isFullPageReport)
            <div class="ultra-test-name">{{ $primaryItem->item_name ?? 'Ultrasonogram' }}</div>
            <div class="ultra-report-body">{!! $noteLooksHtml ? $primaryNoteBody : nl2br(e($primaryNoteBody)) !!}</div>
            @if(!empty($primaryItem->report_range))
                <div class="ultra-range"><strong>Reference:</strong> {{ $primaryItem->report_range }}</div>
            @endif
        @else
            @php
                $paramRows = [];
                foreach ($items as $it) {
                    $params = (!empty($it->printed_parameter_rows) && is_array($it->printed_parameter_rows)) ? $it->printed_parameter_rows : [];
                    if (count($params) > 0) {
                                foreach ($params as $pr) {
                                    // Prefer structured fields if controller provided them
                                    if (array_key_exists('param', $pr) || array_key_exists('value', $pr)) {
                                        $pName = trim((string) ($pr['param'] ?? ''));
                                        $pVal = trim((string) ($pr['value'] ?? ''));
                                    } else {
                                        $rHtml = $pr['result_html'] ?? '';
                                        $pos = strpos($rHtml, ':');
                                        if ($pos !== false) {
                                            $pName = trim(strip_tags(substr($rHtml, 0, $pos)));
                                            $pVal = trim(strip_tags(substr($rHtml, $pos + 1)));
                                        } else {
                                            $pName = '';
                                            $pVal = trim(strip_tags($rHtml));
                                        }
                                    }

                                    $paramRows[] = [
                                        'param' => $pName ?: trim((string) ($it->item_name ?? '')),
                                        'value' => $pVal,
                                        'range' => $pr['normal_range'] ?? ($it->report_range ?? ''),
                                        'item_name' => $it->item_name ?? '',
                                    ];
                                }
                    } else {
                        $paramRows[] = [
                            'param' => trim((string) ($it->item_name ?? '')) ?: 'N/A',
                            'value' => trim((string) ($it->report_note ?? '')),
                            'range' => $it->report_range ?? '',
                            'item_name' => $it->item_name ?? '',
                        ];
                    }
                }

                $allParams = collect($paramRows);

                // Simple keyword-based inference for test function/category (AI-like)
                $inferCategory = function (?string $text) {
                    $t = trim((string) $text);
                    if ($t === '') return '';

                    $map = [
                        'Lipid Profile' => '/(?iu)\b(cholesterol|total cholesterol|hdl(-c)?|ldl(-c)?|vldl|tc|tg|triglycerid(e)?|triglyceride|apo b|apob|apo(a)?|lipid|lipoprotein|লিপিড|লিপিড প্রোফাইল)\b/',
                        'Renal Function' => '/(?iu)\b(creat(inine)?|creat\.|creat\b|creatinine|ক্রিটিনাইন|ক্রিয়েটিনিন|urea|bun|blood urea nitrogen|uric acid|egfr|gfr|renal|kidney|কিডনি)\b/',
                        'Glucose' => '/(?iu)\b(rbs|random blood sugar|random sugar|random|glucose|fbs|fasting blood sugar|ppbs|post prandial|hba1c|a1c|blood sugar|sugar|সুগার|শর্করা|রক্তে চিনি)\b/',
                        'Liver Function' => '/(?iu)\b(alt|ast|sgpt|sgot|sgpt\/?sgot|sgot\/?sgpt|bilirubin|alk phos|alkaline phosphatase|alp|ggt|transaminase|lft|liver|liver function|liver panel|যকৃত|লিভার|এসজিপিটি|এসজিওটি)\b/',
                        'Thyroid Function' => '/(?iu)\b(tsh|t3|t4|free t3|free t4|thyroid|ft3|ft4|থাইরয়েড)\b/',
                        'CBC' => '/(?iu)\b(hemoglobin|hb|hematocrit|hct|wbc|rbc|platelet|plt|mpv|mcv|mch|mchc|cbc|esr|differential|হেমোগ্লোবিন|প্লেটলেট)\b/',
                        'Electrolytes' => '/(?iu)\b(sodium|na|potassium|k|chloride|cl|calcium|ca|magnesium|mg|electrolyte|ইলেকট্রোলাইট|সোডিয়াম|পটাশিয়াম)\b/',
                    ];

                    // explicit substring tokens (fast path)
                    $explicit = [
                        'sgpt' => 'Liver Function', 'sgot' => 'Liver Function', 'alt' => 'Liver Function', 'ast' => 'Liver Function', 'এএসজিপিটি' => 'Liver Function', 'এএসজিওটি' => 'Liver Function',
                        'creatinine' => 'Renal Function', 'ক্রিটিনাইন' => 'Renal Function', 'urea' => 'Renal Function', 'bun' => 'Renal Function',
                        // Ensure uric acid variants map to Renal Function
                        'uric acid' => 'Renal Function', 'uricacid' => 'Renal Function', 'uric' => 'Renal Function', 'ইউরিক' => 'Renal Function',
                        'rbs' => 'Glucose', 'hba1c' => 'Glucose', 'a1c' => 'Glucose', 'glucose' => 'Glucose', 'সুগার' => 'Glucose', 'শর্করা' => 'Glucose',
                        'cholesterol' => 'Lipid Profile', 'hdl' => 'Lipid Profile', 'ldl' => 'Lipid Profile', 'triglyceride' => 'Lipid Profile', 'ট্রাইগ্লিসারাইড' => 'Lipid Profile', 'লিপিড' => 'Lipid Profile',
                        // Liver tokens: include alkaline phosphatase variants
                        'alk phos' => 'Liver Function', 'alk-phos' => 'Liver Function', 'alkaline phosphatase' => 'Liver Function', 'alp' => 'Liver Function', 'ggt' => 'Liver Function',
                        // Thyroid tokens
                        'tsh' => 'Thyroid Function', 't3' => 'Thyroid Function', 't4' => 'Thyroid Function', 'ft3' => 'Thyroid Function', 'ft4' => 'Thyroid Function', 'thyroid' => 'Thyroid Function', 'thyroxine' => 'Thyroid Function',
                        // FSH variants (fertility)
                        'fsh' => 'Fertility Function', 'এফএসএইচ' => 'Fertility Function', 'এফ এস এইচ' => 'Fertility Function',
                        // Bone marrow => Hematology / CBC
                        'bone marrow' => 'CBC', 'bone-marrow' => 'CBC', 'marrow' => 'CBC', 'বোন ম্যারো' => 'CBC', 'বোনম্যারো' => 'CBC',
                    ];

                    foreach ($explicit as $token => $lbl) {
                        try {
                            if (mb_stripos($t, $token) !== false) return $lbl;
                        } catch (\Throwable $_) {
                        }
                    }

                    foreach ($map as $label => $pattern) {
                        try {
                            if (@preg_match($pattern, $t) === 1) return $label;
                        } catch (\Throwable $_) {
                        }
                    }

                    // Fallback: synthesize a label from the test name so every test gets a function
                    try {
                        if ($t !== '' && preg_match_all('/[\p{L}\p{N}]{2,}/u', $t, $m) && !empty($m[0])) {
                            usort($m[0], function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
                            $tok = $m[0][0];
                            $gen = (mb_strtoupper($tok, 'UTF-8') === $tok) ? $tok : mb_convert_case($tok, MB_CASE_TITLE, 'UTF-8');
                            return $gen;
                        }
                    } catch (\Throwable $_) {
                        // ignore
                    }

                    return 'Misc Tests';
                };

                $lipid = $allParams->filter(function ($r) {
                    return preg_match('/cholesterol|triglycerid|triglyceride|hdl|ldl|vldl|lipid/i', $r['param'] . ' ' . $r['item_name']);
                })->values();

                $creatinine = $allParams->filter(function ($r) {
                    return preg_match('/creatinine/i', $r['param'] . ' ' . $r['item_name']);
                })->values();

                $rbs = $allParams->filter(function ($r) {
                    return preg_match('/\brbs\b|random blood sugar|random sugar|glucose|blood sugar|sugar/i', $r['param'] . ' ' . $r['item_name']);
                })->values();

                $others = $allParams->reject(function ($r) use ($lipid, $creatinine, $rbs) {
                    $key = $r['param'] . '|' . $r['item_name'];
                    return $lipid->contains(fn($x) => ($x['param'] . '|' . $x['item_name']) === $key)
                        || $creatinine->contains(fn($x) => ($x['param'] . '|' . $x['item_name']) === $key)
                        || $rbs->contains(fn($x) => ($x['param'] . '|' . $x['item_name']) === $key);
                })->values();
            @endphp

            {{-- Group tests by inferred function/category and render each group once --}}
            @php
                $groupedByFunction = $allParams->groupBy(function ($r) use ($inferCategory) {
                    $lbl = $inferCategory(($r['param'] ?? '') . ' ' . ($r['item_name'] ?? ''));
                    return $lbl ?: 'Other Tests';
                });
            @endphp

            @foreach($groupedByFunction as $label => $rows)
                <div style="margin-top:8px; font-weight:700;">{{ $label }}</div>
                <table style="width:100%; border-collapse: collapse; font-size: 12px; margin-top:6px; table-layout: fixed;">
                    <colgroup>
                        <col style="width:50%" />
                        <col style="width:25%" />
                        <col style="width:25%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="testname-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:left;">Test</th>
                            <th class="result-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center;">Result</th>
                            <th class="range-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center;">Normal Range</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $valRaw = trim((string) ($row['value'] ?? ''));
                                $rangeRaw = trim((string) ($row['range'] ?? ''));
                                $outside = false;

                                // try parse numeric value: extract first numeric token (handles units and notes)
                                $valNum = null;
                                if (preg_match('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $valRaw, $vm)) {
                                    $valNum = floatval(str_replace(',', '.', $vm[0]));
                                }

                                // normalize rangeRaw for matching (use hyphen OR en-dash/em-dash)
                                $rangeForMatch = $rangeRaw;

                                // check range formats like "min - max" (allow -, –, —)
                                if ($valNum !== null && preg_match('/^\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)\s*[-\x{2013}\x{2014}]\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)\s*$/u', $rangeForMatch, $m)) {
                                    $min = floatval(str_replace(',', '.', $m[1]));
                                    $max = floatval(str_replace(',', '.', $m[2]));
                                    if ($valNum < $min || $valNum > $max) $outside = true;
                                }

                                // check range formats like "<5", "<=5", ">5", ">=5" (allow spaces)
                                if ($valNum !== null && !$outside && preg_match('/^\s*([<>]=?)\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)\s*$/u', $rangeForMatch, $m2)) {
                                    $op = $m2[1];
                                    $limit = floatval(str_replace(',', '.', $m2[2]));
                                    if ($op === '<' && !($valNum < $limit)) $outside = true;
                                    if ($op === '<=' && !($valNum <= $limit)) $outside = true;
                                    if ($op === '>' && !($valNum > $limit)) $outside = true;
                                    if ($op === '>=' && !($valNum >= $limit)) $outside = true;
                                }

                                // fallback: if two numbers appear anywhere, treat as min/max
                                if ($valNum !== null && !$outside && preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $rangeForMatch, $nums) && count($nums[0]) >= 2) {
                                    $min = floatval(str_replace(',', '.', $nums[0][0]));
                                    $max = floatval(str_replace(',', '.', $nums[0][1]));
                                    if ($valNum < $min || $valNum > $max) $outside = true;
                                }
                            @endphp
                            <tr>
                                <td class="testname-cell" style="border:1px solid #e5e7eb; padding:8px;">{{ $row['param'] }}</td>
                                        <td class="result-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center; vertical-align: middle;">@if($outside)<span style="font-weight:700">{{ $row['value'] }}</span>@else{{ $row['value'] }}@endif</td>
                                <td class="range-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center; vertical-align: middle;">{{ $row['range'] ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif
    </div>

    <div
        class="section signature-row"
    >
        @php
            $rawName = isset($pathologistNameRaw) ? trim((string) $pathologistNameRaw) : '';
            $rawDesignation = isset($pathologistDesignationRaw) ? trim((string) $pathologistDesignationRaw) : '';
            $hasPathologistIdentity = ($rawName !== '') || ($rawDesignation !== '');
        @endphp
            @if($hasPathologistIdentity)
                <div class="signature-block">
                    @if($technologistSignature)
                        <img src="{{ $technologistSignature }}" alt="tech-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    @if($sampleCollectedBySignature)
                        <img src="{{ $sampleCollectedBySignature }}" alt="sample-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    @if($pathologistSignature)
                        <img src="{{ $pathologistSignature }}" alt="path-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $pathologistName ?? '' }}</span></div>
                    <div class="signature-designation">{{ $pathologistDesignation ?? '' }}</div>
                </div>
            @else
                <div class="signature-block">
                    @if($sampleCollectedBySignature)
                        <img src="{{ $sampleCollectedBySignature }}" alt="sample-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    {{-- center placeholder --}}
                </div>

                <div class="signature-block" style="text-align:right;">
                    @if($technologistSignature)
                        <img src="{{ $technologistSignature }}" alt="tech-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '') }}</div>
                </div>
            @endif
    </div>

       <div class="footer-placeholder"></div>
    </div>

    <div class="footer-section">
        @php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', ''));
            $footerPrintedAt = trim((string) ($reportDateTime ?? ''));
        @endphp

        @if(!empty($footer_image))
            @if(!empty($footer_content))
                <div class="footer-content">{!! $footer_content !!}</div>
            @elseif(!empty($footerFallbackLine))
                <div class="footer-content">{{ $footerFallbackLine }}@if(!empty($footerPrintedAt)), Printing Date: <span class="print-datetime">{{ $footerPrintedAt }}</span>@endif</div>
            @endif
            <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
        @else
            @if(!empty($footer_content))
                <div class="footer-content">{!! $footer_content !!}</div>
            @elseif(!empty($footerFallbackLine))
                <div class="footer-content">{{ $footerFallbackLine }}@if(!empty($footerPrintedAt)), Printing Date: <span class="print-datetime">{{ $footerPrintedAt }}</span>@endif</div>
            @else
                <div class="footer-placeholder"></div>
            @endif
        @endif
    </div>

</body>

@if (empty($isPdf) || !$isPdf)
<script>
    window.addEventListener('load', function () {
        // update any printing datetime placeholders to the current browser time
        function formatPrintDate(d) {
            try {
                var dd = String(d.getDate()).padStart(2, '0');
                var monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var m = monthNames[d.getMonth()];
                var yyyy = d.getFullYear();
                var hh = d.getHours();
                var ampm = hh >= 12 ? 'PM' : 'AM';
                hh = hh % 12; hh = hh ? hh : 12; // 0 -> 12
                hh = String(hh).padStart(2, '0');
                var min = String(d.getMinutes()).padStart(2, '0');
                var sec = String(d.getSeconds()).padStart(2, '0');
                return dd + '-' + m + '-' + yyyy + ' ' + hh + ':' + min + ':' + sec + ' ' + ampm;
            } catch (e) { return d.toLocaleString(); }
        }

        var els = document.querySelectorAll('.print-datetime');
        if (els && els.length) {
            var now = new Date();
            var txt = formatPrintDate(now);
            els.forEach(function (el) { el.textContent = txt; });
        }

        setTimeout(function () { window.print(); }, 180);
    });
</script>
@endif

</html>
