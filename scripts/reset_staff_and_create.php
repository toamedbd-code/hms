<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\Admin;
use App\Models\AdminDetail;
use App\Services\AdminService;

$adminService = app(AdminService::class);

echo "Starting staff reset and create...\n";

$devRole = Role::where('name', 'developer')->where('guard_name', 'admin')->first();
$devRoleId = $devRole->id ?? null;

$forceEmailsEnv = env('FORCE_FULL_SIDEBAR_EMAILS', '');
$excludeEmails = array_filter(array_map('trim', explode(',', (string) $forceEmailsEnv)));
$excludeEmails = array_map('strtolower', $excludeEmails);

$deleted = [];
$skipped = [];

$admins = Admin::whereNull('deleted_at')->get();
foreach ($admins as $a) {
    $emailLower = strtolower($a->email ?? '');
    // keep developer and force-full emails
    if (($devRoleId && $a->role_id == $devRoleId) || in_array($emailLower, $excludeEmails)) {
        $skipped[] = $a->email;
        continue;
    }
    try {
        $adminService->delete($a->id);
        $deleted[] = $a->email;
        echo "Deleted: {$a->email}\n";
    } catch (\Throwable $e) {
        echo "Failed to delete {$a->email}: " . $e->getMessage() . "\n";
    }
}

// Create Receptionist role if missing
$role = Role::where('name', 'Receiption')->where('guard_name', 'admin')->first();
if (! $role) {
    $role = Role::create(['name' => 'Receiption', 'guard_name' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);
    echo "Created role Receiption (id={$role->id})\n";
}

$email = 'receptionist+' . time() . '@example.com';
try {
    $admin = Admin::create([
        'first_name' => 'Reception',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '01234567890',
        'role_id' => $role->id,
        'password' => 'zxczxc',
    ]);

    // assign role and clear permission cache
    try {
        $admin->syncRoles([$role->name]);
        try { $admin->syncPermissions([]); } catch (\Throwable $_) {}
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        echo "Role assignment error: " . $e->getMessage() . "\n";
    }

    // create admin details with staff id '01'
    try {
        AdminDetail::create([
            'admin_id' => $admin->id,
            'staff_id' => '01',
        ]);
    } catch (\Throwable $e) {
        echo "AdminDetail create error: " . $e->getMessage() . "\n";
    }

    echo "Created admin: {$admin->id} ({$admin->email}) role={$role->name}\n";
} catch (\Throwable $e) {
    echo "Failed to create admin: " . $e->getMessage() . "\n";
}

echo "Skipped accounts: " . json_encode($skipped) . "\n";
echo "Deleted accounts: " . json_encode($deleted) . "\n";

echo "Done.\n";
