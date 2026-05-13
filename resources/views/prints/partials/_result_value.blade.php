@php
    $valRaw = trim((string) ($value ?? ''));
    $rangeRaw = trim((string) ($range ?? ''));
    $outside = false;
    $valNum = null;
    if (preg_match('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $valRaw, $vm)) {
        $valNum = floatval(str_replace(',', '.', $vm[0]));
    }

    if ($valNum !== null && preg_match('/^\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)\s*[-\x{2013}\x{2014}]\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)\s*$/u', $rangeRaw, $m)) {
        $min = floatval(str_replace(',', '.', $m[1]));
        $max = floatval(str_replace(',', '.', $m[2]));
        if ($valNum < $min || $valNum > $max) $outside = true;
    }

    if ($valNum !== null && !$outside && preg_match('/^\s*([<>]=?)\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)\s*$/u', $rangeRaw, $m2)) {
        $op = $m2[1];
        $limit = floatval(str_replace(',', '.', $m2[2]));
        if ($op === '<' && !($valNum < $limit)) $outside = true;
        if ($op === '<=' && !($valNum <= $limit)) $outside = true;
        if ($op === '>' && !($valNum > $limit)) $outside = true;
        if ($op === '>=' && !($valNum >= $limit)) $outside = true;
    }

    if ($valNum !== null && !$outside && preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $rangeRaw, $nums) && count($nums[0]) >= 2) {
        $min = floatval(str_replace(',', '.', $nums[0][0]));
        $max = floatval(str_replace(',', '.', $nums[0][1]));
        if ($valNum < $min || $valNum > $max) $outside = true;
    }
@endphp

@if($outside)
    <span style="font-weight:700">{{ $value }}</span>
@else
    {{ $value }}
@endif
