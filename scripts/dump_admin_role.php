<?php
require __DIR__ . '/../vendor/autoload.php';
$env = file_get_contents(__DIR__ . '/../.env');
function envValue($env, $key) {
    if (preg_match('/^' . preg_quote($key) . "=(.*)$/m", $env, $m)) {
        return trim(trim($m[1], " \t\r\n\""));
    }
    return null;
}
$db_host = envValue($env, 'DB_HOST') ?: '127.0.0.1';
$db_port = envValue($env, 'DB_PORT') ?: '3306';
$db_name = envValue($env, 'DB_DATABASE') ?: 'hms_local';
$db_user = envValue($env, 'DB_USERNAME') ?: 'root';
$db_pass = envValue($env, 'DB_PASSWORD') ?: '';
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
if ($mysqli->connect_error) { echo "DB connect error: " . $mysqli->connect_error . "\n"; exit(1);} 

$roleName = 'admin';
$role = $mysqli->query('SELECT id,name FROM roles WHERE name = "' . $mysqli->real_escape_string($roleName) . '" LIMIT 1')->fetch_assoc();
if (!$role) { echo "Role not found: $roleName\n"; exit(0); }
echo "Role: {$role['id']} {$role['name']}\n";

// role permissions via model tables
$res = $mysqli->query('SELECT p.id,p.name FROM permissions p JOIN role_has_permissions r ON r.permission_id=p.id WHERE r.role_id=' . intval($role['id']) . ' ORDER BY p.id');
echo "Permissions assigned to role (count: " . $res->num_rows . "):\n";
$hasDashboardSetting = false;
while ($r = $res->fetch_assoc()) {
    echo "  {$r['id']} {$r['name']}\n";
    if ($r['name'] === 'dashboard-setting') $hasDashboardSetting = true;
}

// list of parent permissions returned to Role edit page
echo "\nParent permissions (what Role edit receives):\n";
$parents = $mysqli->query("SELECT id,name FROM permissions WHERE parent_id IS NULL AND guard_name='admin' ORDER BY id");
while ($p = $parents->fetch_assoc()) {
    echo "Parent {$p['id']} {$p['name']}\n";
    $children = $mysqli->query('SELECT id,name,parent_id FROM permissions WHERE parent_id=' . intval($p['id']) . " ORDER BY id");
    while ($c = $children->fetch_assoc()) {
        echo "  Child {$c['id']} {$c['name']}\n";
    }
}

echo "\nResult: dashboard-setting assigned to role? " . ($hasDashboardSetting ? 'YES' : 'NO') . "\n";
$mysqli->close();
