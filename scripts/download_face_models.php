<?php
set_time_limit(0);
$root = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$outDir = $root . 'public' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR;
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$base = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
$manifests = [
    'tiny_face_detector_model-weights_manifest.json',
    'face_landmark_68_model-weights_manifest.json',
    'face_recognition_model-weights_manifest.json',
];

function fetch_url($url, &$httpCode = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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

function save_file($path, $data)
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $data);
}

foreach ($manifests as $m) {
    $url = $base . '/' . $m;
    echo "Fetching manifest: $url\n";
    try {
        $json = fetch_url($url, $code);
    } catch (Exception $e) {
        echo "Failed to fetch manifest ($m): " . $e->getMessage() . "\n";
        continue;
    }
    if ($code !== 200) {
        echo "Manifest HTTP $code for $url\n";
        continue;
    }
    $manifestPath = $outDir . $m;
    save_file($manifestPath, $json);
    echo "Saved manifest to: $manifestPath\n";
    $obj = json_decode($json, true);
    if (!isset($obj['weights']) || !is_array($obj['weights'])) {
        echo "Manifest missing weights section: $m\n";
        continue;
    }
    foreach ($obj['weights'] as $w) {
        if (!isset($w['paths']) || !is_array($w['paths'])) continue;
        foreach ($w['paths'] as $p) {
            $fileUrl = $base . '/' . $p;
            $dest = $outDir . $p;
            if (file_exists($dest) && filesize($dest) > 0) {
                echo "Exists: $p\n";
                continue;
            }
            echo "Downloading: $fileUrl -> $dest\n";
            try {
                $data = fetch_url($fileUrl, $fcode);
            } catch (Exception $e) {
                echo "Failed download $fileUrl : " . $e->getMessage() . "\n";
                continue;
            }
            if ($fcode !== 200) {
                echo "HTTP $fcode for $fileUrl\n";
                continue;
            }
            save_file($dest, $data);
            echo "Saved: $p (" . strlen($data) . " bytes)\n";
        }
    }
}

echo "Done. Listing public/models contents:\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outDir));
foreach ($it as $file) {
    if ($file->isFile()) {
        $rel = substr($file->getPathname(), strlen($root));
        echo $rel . "\t" . $file->getSize() . "\n";
    }
}

echo "Finished.\n";
