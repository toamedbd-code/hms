<?php
$width = 320;
$height = 240;
$frames = 30;
$out = __DIR__ . '/../tests/fake_video.y4m';
$fp = fopen($out, 'wb');
if (!$fp) { echo "Failed to open $out\n"; exit(1); }
$header = "YUV4MPEG2 W{$width} H{$height} F25:1 Ip A0:0 C420\n";
fwrite($fp, $header);
for ($f = 0; $f < $frames; $f++) {
    fwrite($fp, "FRAME\n");
    // Y plane
    $ySize = $width * $height;
    $uSize = ($width/2) * ($height/2);
    $vSize = $uSize;
    // create a moving gray pattern
    $yVal = ($f * 5) % 256;
    $yData = str_repeat(chr($yVal), $ySize);
    $uData = str_repeat(chr(128), $uSize);
    $vData = str_repeat(chr(128), $vSize);
    fwrite($fp, $yData);
    fwrite($fp, $uData);
    fwrite($fp, $vData);
}
fclose($fp);
echo "Generated Y4M at $out\n";
