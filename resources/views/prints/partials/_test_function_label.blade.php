@php
    // Input: $text (test name or related text)
    $t = strtolower(trim((string) ($text ?? '')));
    $label = '';
    $patterns = [
        'Lipid Profile' => '/(?iu)\b(cholesterol|total cholesterol|hdl(-c)?|ldl(-c)?|vldl|tc|tg|triglycerid(e)?|triglyceride|apo b|apob|apo(a)?|lipid|lipoprotein|লিপিড|লিপিড প্রোফাইল)\b/',
        'Renal Function' => '/(?iu)\b(creat(inine)?|creat\.|creat\b|creatinine|ক্রিটিনাইন|ক্রিয়েটিনিন|urea|bun|blood urea nitrogen|uric acid|egfr|gfr|renal|kidney|কিডনি)\b/',
        'Glucose' => '/(?iu)\b(rbs|random blood sugar|random sugar|random|glucose|fbs|fasting blood sugar|ppbs|post prandial|hba1c|a1c|blood sugar|sugar|সুগার|শর্করা|রক্তে চিনি)\b/',
        'Liver Function' => '/(?iu)\b(alt|ast|sgpt|sgot|sgpt\/?sgot|sgot\/?sgpt|bilirubin|alk phos|alkaline phosphatase|alp|ggt|transaminase|lft|liver|liver function|liver panel|যকৃত|লিভার|এসজিপিটি|এসজিওটি)\b/',
        'Thyroid Function' => '/(?iu)\b(tsh|t3|t4|free t3|free t4|thyroid|ft3|ft4|থাইরয়েড)\b/',
        'CBC' => '/(?iu)\b(hemoglobin|hb|hematocrit|hct|wbc|rbc|platelet|plt|mpv|mcv|mch|mchc|cbc|esr|differential|হেমোগ্লোবিন|প্লেটলেট)\b/',
        'Electrolytes' => '/(?iu)\b(sodium|na|potassium|k|chloride|cl|calcium|ca|magnesium|mg|electrolyte|ইলেকট্রোলাইট|সোডিয়াম|পটাশিয়াম)\b/',
        'Liver / Renal Panel' => '/(?iu)\b(liver panel|renal panel|liver function test|rft|lft|রেনাল প্যানেল|লিভার প্যানেল)\b/',
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
