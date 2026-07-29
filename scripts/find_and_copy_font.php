<?php
$user = getenv('USERPROFILE') ?: (getenv('HOME') ?: 'C:/Users/Default');
$targets = [
    $user . '/Downloads',
    $user . '/Desktop',
    $user . '/Documents',
    $user,
];
$found = null;
$pattern = '/Solaiman.*\\.ttf$/i';
foreach ($targets as $dir) {
    if (! $dir || ! is_dir($dir)) continue;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    } catch (\Throwable $e) {
        continue;
    }
    foreach ($it as $file) {
        try {
            if (! $file->isFile()) continue;
            if (preg_match($pattern, $file->getFilename())) {
                $found = $file->getPathname();
                break 2;
            }
        } catch (\Throwable $e) {
            // skip unreadable files
            continue;
        }
    }
}

if (! $found) {
    echo "notfound\n";
    exit(0);
}

$destDir = __DIR__ . '/../public/fonts';
if (! is_dir($destDir)) mkdir($destDir, 0777, true);
$dest = $destDir . '/SolaimanLipi.ttf';
if (copy($found, $dest)) {
    echo "copied:" . $dest . PHP_EOL;
    exit(0);
}

echo "copy_failed\n";
