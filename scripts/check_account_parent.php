<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check Account Management parent details
$acct = DB::table('menus')->where('id', 3)->first();
echo 'Account Mgmt parent: ' . json_encode((array)$acct, JSON_PRETTY_PRINT) . PHP_EOL;
