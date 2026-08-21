<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new class extends App\Http\Controllers\Payment\BkashController {
    public function probe(): ?string
    {
        return $this->grantToken();
    }
};

$token = $controller->probe();
var_dump($token);
