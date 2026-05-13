<?php
// Conservative button text-color migration script
// - Finds class attributes containing a `bg-` token in Vue page files under resources/js/Pages
// - If the background token is not a light background (whitelist), it ensures a `text-white` token
//   is present. If a different `text-...` token exists, it will be replaced by `text-white`.
// - Makes a `.bak` copy before modifying a file.

$root = __DIR__ . '/../resources/js/Pages';
$allowedExt = ['vue'];

$lightBgPatterns = [
    'bg-white', 'bg-gray-50', 'bg-gray-100', 'bg-gray-200', 'bg-slate-100', 'bg-blue-50', 'bg-blue-100', 'bg-amber-50', 'bg-amber-100',
];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$updatedFiles = 0;
foreach ($it as $file) {
    if (! $file->isFile()) continue;
    $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    if (! in_array(strtolower($ext), $allowedExt)) continue;

    $path = $file->getRealPath();
    $content = file_get_contents($path);
    if ($content === false) continue;

    $callback = function ($m) use ($lightBgPatterns) {
        $full = $m[0];
        $classes = $m[1];

        // If any light-bg token present, skip modification for this class attr
        foreach ($lightBgPatterns as $lb) {
            if (stripos($classes, $lb) !== false) {
                return $full;
            }
        }

        // If already has text-white, nothing to do
        if (preg_match('/\btext-white\b/i', $classes)) {
            return $full;
        }

        // If contains a text-... token, replace the first with text-white
        if (preg_match('/\btext-[^\s"\']+\b/i', $classes, $tc)) {
            $new = preg_replace('/\btext-[^\s"\']+\b/i', 'text-white', $classes, 1);
        } else {
            // append text-white
            $new = trim($classes) . ' text-white';
        }

        return 'class="' . $new . '"';
    };

    // Replace class="...bg-..." patterns (multiline aware)
    $updated = preg_replace_callback('/class\s*=\s*"([^"]*bg-[^"]*)"/is', $callback, $content, -1, $count);

    if ($count > 0 && $updated !== $content) {
        // backup
        $bak = $path . '.bak';
        if (! file_exists($bak)) {
            file_put_contents($bak, $content);
        }
        file_put_contents($path, $updated);
        echo "Patched: {$path}\n";
        $updatedFiles++;
    }
}

echo "Done. Files updated: {$updatedFiles}\n";
