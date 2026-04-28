<?php
// Fix ternary :class expressions that have " text-white" appended outside
// Example broken: :class="cond ? 'a' : 'b' text-white"
// Fixed to: :class="cond ? 'a text-white' : 'b text-white'"

$root = __DIR__ . '/../resources/js/Pages';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$fixed = 0;

foreach ($it as $file) {
    if (! $file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'vue') continue;

    $path = $file->getRealPath();
    $content = file_get_contents($path);
    if ($content === false) continue;

    $pattern = '/:class\s*=\s*"([^\"]*?)\?\s*\'([^\']*)\'\s*:\s*\'([^\']*)\'\s*text-white"/s';

    $new = preg_replace_callback($pattern, function ($m) {
        $cond = $m[1];
        $a = $m[2];
        $b = $m[3];
        // trim
        $cond = trim($cond);
        return ':class="' . $cond . ' ? \'' . trim($a) . ' text-white\' : \'' . trim($b) . ' text-white\'"';
    }, $content, -1, $count);

    if ($count > 0 && $new !== $content) {
        $bak = $path . '.bak2';
        if (! file_exists($bak)) file_put_contents($bak, $content);
        file_put_contents($path, $new);
        echo "Fixed ternary in: {$path}\n";
        $fixed++;
    }
}

echo "Done. Files fixed: {$fixed}\n";
