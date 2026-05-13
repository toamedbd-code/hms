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

    // Explicit substring map (fast path) for common test tokens
    $explicit = [
        'sgpt' => 'Liver Function', 'sgot' => 'Liver Function', 'alt' => 'Liver Function', 'ast' => 'Liver Function', 'এসজিপিটি' => 'Liver Function', 'এসজিওটি' => 'Liver Function',
        'creatinine' => 'Renal Function', 'ক্রিটিনাইন' => 'Renal Function', 'urea' => 'Renal Function', 'bun' => 'Renal Function',
        'rbs' => 'Glucose', 'hba1c' => 'Glucose', 'a1c' => 'Glucose', 'glucose' => 'Glucose', 'সুগার' => 'Glucose', 'শর্করা' => 'Glucose',
        'cholesterol' => 'Lipid Profile', 'hdl' => 'Lipid Profile', 'ldl' => 'Lipid Profile', 'triglyceride' => 'Lipid Profile', 'ট্রাইগ্লিসারাইড' => 'Lipid Profile', 'লিপিড' => 'Lipid Profile',
        // Thyroid tokens
        'tsh' => 'Thyroid Function', 't3' => 'Thyroid Function', 't4' => 'Thyroid Function', 'ft3' => 'Thyroid Function', 'ft4' => 'Thyroid Function', 'thyroid' => 'Thyroid Function', 'thyroxine' => 'Thyroid Function',
        // FSH (fertility test) - map to Fertility Function
        'fsh' => 'Fertility Function', 'এফএসএইচ' => 'Fertility Function', 'এফ এস এইচ' => 'Fertility Function',
    ];

    foreach ($explicit as $token => $lbl) {
        try {
            if ($t !== '' && mb_stripos($t, $token) !== false) {
                $label = $lbl;
                break;
            }
        } catch (\Throwable $_) {
            // ignore errors
        }
    }

    if ($label === '') {
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
    }
    // If still empty, synthesize a human-friendly label from the test name
    if ($label === '') {
        try {
            if ($t !== '' && preg_match_all('/[\p{L}\p{N}]{2,}/u', $t, $m) && !empty($m[0])) {
                // pick the longest token as most descriptive
                usort($m[0], function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
                $tok = $m[0][0];
                // map common single-token test names back to function labels if possible
                $tok_l = mb_strtolower($tok, 'UTF-8');
                foreach ($explicit as $token => $lbl) {
                    if (mb_stripos($tok_l, $token) !== false || $tok_l === $token) {
                        $label = $lbl;
                        break;
                    }
                }

                if ($label === '') {
                    $label = (mb_strtoupper($tok, 'UTF-8') === $tok)
                        ? $tok
                        : mb_convert_case($tok, MB_CASE_TITLE, 'UTF-8');
                }
            }
        } catch (\Throwable $_) {
            // ignore
        }
    }
@endphp

@if($label !== '')
    <div class="test-function-label" style="font-size:11px;color:#374151;margin-bottom:4px;font-weight:600;">{{ $label }}</div>
@else
    <div class="test-function-label" style="font-size:11px;color:#374151;margin-bottom:4px;font-weight:600;">Misc Tests</div>
@endif
