<?php
$path = 'storage/app/public/test_invoice_10.pdf';
if (!file_exists($path)) { echo "missing\n"; exit(1); }
$content = file_get_contents($path);
$needle = 'রুম';
$found = mb_strpos($content, $needle) !== false;
echo $found ? 'found' : 'notfound';
