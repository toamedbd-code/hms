<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use App\Models\Role;

$query = Admin::whereNull('admins.deleted_at')
    ->leftJoin('admin_details as ad', 'ad.admin_id', '=', 'admins.id')
    ->leftJoin('roles as r', 'admins.role_id', '=', 'r.id')
    ->select('admins.*', 'ad.staff_id as staff_id', 'r.name as role_name')
    ->orderByRaw('COALESCE(ad.staff_id, admins.id) ASC');

$results = $query->get()->map(function($row) {
    return [
        'index' => $row->staff_id ?? $row->id,
        'id' => $row->id,
        'staff_id' => $row->staff_id ?? null,
        'email' => $row->email,
        'role_id' => $row->role_id,
        'role_name' => $row->role_name ?? null,
        'created_at' => $row->created_at,
    ];
});

echo json_encode($results->toArray(), JSON_PRETTY_PRINT);
