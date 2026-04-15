<?php
set_time_limit(0);
$root = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$outDir = $root . 'public' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR;
$base = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
$manifests = glob($outDir . '*-weights_manifest.json');
if (!$manifests) {
    echo "No manifest files found in $outDir\n";
    exit(1);
}

function fetch_url($url, &$httpCode = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP model downloader');
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($data === false) {
        throw new Exception('curl failed: ' . $err);
    }
    return $data;
}

foreach ($manifests as $mPath) {
    echo "Processing manifest: $mPath\n";
    $json = file_get_contents($mPath);
    $obj = json_decode($json, true);
    if ($obj === null) {
        echo "Failed to parse JSON: $mPath\n";
        continue;
    }
    // manifest may be array or object
    $entries = is_array($obj) && array_keys($obj) === range(0, count($obj)-1) ? $obj : [$obj];
    foreach ($entries as $entry) {
        if (!isset($entry['weights'])) continue;
        foreach ($entry['weights'] as $w) {
            if (!isset($w['paths'])) continue;
            foreach ($w['paths'] as $p) {
                $url = $base . '/' . $p;
                $dest = $outDir . $p;
                if (file_exists($dest) && filesize($dest) > 0) {
                    echo "Exists: $p\n";
                    continue;
                }
                echo "Downloading: $url -> $dest\n";
                try {
                    $data = fetch_url($url, $code);
                } catch (Exception $e) {
                    echo "Error fetching $url: " . $e->getMessage() . "\n";
                    continue;
                }
                if ($code !== 200) {
                    echo "HTTP $code for $url\n";
                    continue;
                }
                $dir = dirname($dest);
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                file_put_contents($dest, $data);
                echo "Saved: $p (" . strlen($data) . " bytes)\n";
            }
        }
    }
}

echo "Done. Current files in public/models:\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outDir));
foreach ($it as $file) {
    if ($file->isFile()) {
        $rel = substr($file->getPathname(), strlen($root));
        echo $rel . "\t" . $file->getSize() . "\n";
    }
}
