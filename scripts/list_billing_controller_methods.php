<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$ref = new ReflectionClass(App\Http\Controllers\Backend\BillingController::class);

foreach ($ref->getMethods() as $method) {
    $name = $method->getName();
    if (stripos($name, 'invoice') !== false || stripos($name, 'print') !== false) {
        echo $name . PHP_EOL;
    }
}
