<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Patient Diagnostic Report</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header {
            border: 1px solid #dbe7f3;
            background: #f5f9ff;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .title { margin: 0; font-size: 22px; letter-spacing: 0.4px; }
        .sub { margin-top: 4px; color: #475569; font-size: 11px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .meta td { border: 1px solid #dbe7f3; padding: 6px 8px; vertical-align: top; }
        .meta .label { color: #1d4ed8; font-weight: 700; }
        .category-title {
            margin-top: 14px;
            padding: 6px 8px;
            border-left: 4px solid #2563eb;
            background: #eff6ff;
            font-size: 13px;
            font-weight: 700;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #d1d5db; padding: 7px; vertical-align: top; }
        th { background: #f8fafc; text-align: left; color: #0f172a; }
        .sl { width: 7%; text-align: center; }
        .test { width: 31%; }
        .range { width: 22%; }
        .result { white-space: pre-wrap; }
        .footer-note {
            margin-top: 14px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            padding: 8px;
            font-size: 11px;
            color: #334155;
        }
    </style>
</head>
<body>
    @includeIf('prints.partials._header')

    <div class="header">
        <h1 class="title">Patient Diagnostic Report</h1>
        <div class="sub">Generated: {{ $generatedAt }} | Reported: {{ $reportedAt }}</div>
    </div>

    <table class="meta">
        <tr>
            <td><span class="label">Bill No:</span> {{ $billing->bill_number ?? ('BILL-' . $billing->id) }}</td>
            <td><span class="label">Bill Date:</span> {{ optional($billing->created_at)->format('d-M-Y h:i A') }}</td>
        </tr>
        <tr>
            <td><span class="label">Patient:</span> {{ $patient->name ?? 'N/A' }} (ID: {{ $patient->id ?? 'N/A' }})</td>
            <td><span class="label">Phone:</span> {{ $contactNo ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><span class="label">Gender:</span> {{ $gender ?? 'N/A' }}</td>
            <td><span class="label">Refd. Doctor:</span> {{ $doctorName ?? 'N/A' }}</td>
        </tr>
    </table>


    @php
        // Flatten all groupedReportItems into a single collection of items
        $all = collect([]);
        if (!empty($groupedReportItems) && is_array($groupedReportItems)) {
            foreach ($groupedReportItems as $catItems) {
                foreach ($catItems as $it) {
                    $all->push((object) [
                        'name' => $it->charge_name ?? $it->item_name ?? $it->name ?? 'N/A',
                        'note' => $it->report_note ?? '',
                        'range' => $it->report_range ?? '',
                    ]);
                }
            }
        }

        // inference closure (same logic as partial)
        $inferCategory = function (?string $text) {
            $t = strtolower(trim((string) $text));
            if ($t === '') return '';

            $map = [
                'Lipid Profile' => '/\b(cholesterol|total cholesterol|hdl(-c)?|ldl(-c)?|vldl|tc|tg|triglycerid(e)?|triglyceride|apo b|apob|apo(a)?|lipid|lipoprotein)\b/',
                'Renal Function' => '/\b(creat(inine)?|creat\.|creat\b|urea|bun|blood urea nitrogen|uric acid|egfr|gfr|renal|kidney)\b/',
                'Glucose' => '/\b(rbs|random blood sugar|random sugar|random|glucose|fbs|fasting blood sugar|ppbs|post prandial|hba1c|a1c|blood sugar|sugar)\b/',
                'Liver Function' => '/\b(alt|ast|sgpt|sgot|bilirubin|alk phos|alkaline phosphatase|alp|ggt|sgot\/sgpt|sgpt\/sgot|transaminase)\b/',
                'Thyroid Function' => '/\b(tsh|t3|t4|free t3|free t4|thyroid|ft3|ft4)\b/',
                'CBC' => '/\b(hemoglobin|hb|hematocrit|hct|wbc|rbc|platelet|plt|mpv|mcv|mch|mchc|cbc|esr|differential)\b/',
                'Electrolytes' => '/\b(sodium|na|potassium|k|chloride|cl|calcium|ca|magnesium|mg|electrolyte)\b/',
            ];

            foreach ($map as $label => $pattern) {
                try {
                    if (@preg_match($pattern, $t) === 1) return $label;
                } catch (\\Throwable $_) {
                }
            }

            return '';
        };

        $grouped = $all->groupBy(function ($it) use ($inferCategory) {
            $lbl = $inferCategory($it->name);
            return $lbl ?: 'Other Tests';
        });
    @endphp

    @foreach($grouped as $label => $items)
        <div class="category-title">{{ $label }}</div>
        <table>
            <thead>
                <tr>
                    <th class="sl">SL</th>
                    <th class="test">Item Name</th>
                    <th>Result</th>
                    <th class="range">Normal Range</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $idx => $item)
                    <tr>
                        <td class="sl">{{ $idx + 1 }}</td>
                        <td>{{ $item->name }}</td>
                        <td class="result">{{ $item->note }}</td>
                        <td>{{ $item->range }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if(!empty($billing->remarks))
        <div class="footer-note"><strong>Remarks:</strong> {{ $billing->remarks }}</div>
    @endif

    @includeIf('prints.partials._footer')
</body>
</html>
