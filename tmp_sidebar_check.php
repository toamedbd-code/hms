<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admins = App\Models\Admin::query()->select('id','first_name','last_name','email')->get();
foreach ($admins as $admin) {
    echo $admin->id . ' | ' . $admin->first_name . ' ' . $admin->last_name . ' | ' . $admin->email . PHP_EOL;
}
