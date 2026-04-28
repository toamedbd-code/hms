<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
// Boot the kernel so app() and facades work
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// Ensure facades have application instance
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
// Bootstrap the kernel (register config, providers, etc.)
$kernel->bootstrap();

$tables = ['billings', 'patients', 'admins'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    try {
        $rows = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `" . $t . "`");
        if (empty($rows)) {
            echo "(no columns returned)\n";
        }
        foreach ($rows as $r) {
            echo ($r->Field ?? $r['Field']) . "\t" . ($r->Type ?? $r['Type']) . "\n";
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
