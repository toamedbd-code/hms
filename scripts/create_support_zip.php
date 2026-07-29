<?php
$files = [
    __DIR__ . '/../storage/logs/bkash-signed-dump-20260728_153004.json',
    __DIR__ . '/../storage/logs/bkash-test-dump-20260728_152426.json',
    __DIR__ . '/../storage/logs/bkash-support-message.txt',
];

$now = date('Ymd_His');
$dest = __DIR__ . "/../storage/logs/bkash-support-package-{$now}.zip";

$zip = new ZipArchive();
if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "Failed to create zip at: $dest\n";
    exit(1);
}

foreach ($files as $f) {
    if (file_exists($f)) {
        $zip->addFile($f, basename($f));
    }
}

$zip->close();
echo $dest . "\n";

return 0;
