@php
    // Input: $text (test name or related text)
    $t = strtolower(trim((string) ($text ?? '')));
    $label = '';
    $patterns = [
        'Lipid Profile' => '/\b(cholesterol|total cholesterol|hdl(-c)?|ldl(-c)?|vldl|tc|tg|triglycerid(e)?|triglyceride|apo b|apob|apo(a)?|lipid|lipoprotein)\b/',
        'Renal Function' => '/\b(creat(inine)?|creat\.|creat\b|urea|bun|blood urea nitrogen|uric acid|egfr|gfr|renal|kidney)\b/',
        'Glucose' => '/\b(rbs|random blood sugar|random sugar|random|glucose|fbs|fasting blood sugar|ppbs|post prandial|hba1c|a1c|blood sugar|sugar)\b/',
        'Liver Function' => '/\b(alt|ast|sgpt|sgot|bilirubin|alk phos|alkaline phosphatase|alp|ggt|sgot\/sgpt|sgpt\/sgot|transaminase)\b/',
        'Thyroid Function' => '/\b(tsh|t3|t4|free t3|free t4|thyroid|ft3|ft4)\b/',
        'CBC' => '/\b(hemoglobin|hb|hematocrit|hct|wbc|rbc|platelet|plt|mpv|mcv|mch|mchc|cbc|esr|differential)\b/',
        'Electrolytes' => '/\b(sodium|na|potassium|k|chloride|cl|calcium|ca|magnesium|mg|electrolyte)\b/',
        'Liver / Renal Panel' => '/\b(liver panel|renal panel|liver function test|rft|lft)\b/',
    ];

    foreach ($patterns as $lbl => $pat) {
        try {
            if ($t !== '' && preg_match($pat, $t) === 1) {
                $label = $lbl;
                break;
            }
        } catch (\Throwable $_) {
            // ignore regex errors
        }
    }
@endphp

@if($label !== '')
    <div class="test-function-label" style="font-size:11px;color:#374151;margin-bottom:4px;font-weight:600;">{{ $label }}</div>
@endif
