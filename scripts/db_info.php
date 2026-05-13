<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
try {
    $row = DB::selectOne('select database() as db, @@hostname as host, @@port as port, @@version as version, connection_id() as conn_id');
    echo "DB: " . ($row->db ?? '(null)') . PHP_EOL;
    echo "Host: " . ($row->host ?? '(null)') . PHP_EOL;
    echo "Port: " . ($row->port ?? '(null)') . PHP_EOL;
    echo "Version: " . ($row->version ?? '(null)') . PHP_EOL;
    echo "Conn ID: " . ($row->conn_id ?? '(null)') . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . (string) $e . PHP_EOL;
}
