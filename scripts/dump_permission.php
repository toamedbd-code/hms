<?php
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
if ($mysqli->connect_error) {
    echo "DB connect error: " . $mysqli->connect_error . "\n";
    exit(1);
}

$names = [
    'dashboard-setting',
    'dashboard',
    'websetting-add',
    'general-setting-add'
];

foreach ($names as $n) {
    $stmt = $mysqli->prepare('SELECT id,name,parent_id,guard_name FROM permissions WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $n);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row) {
        echo "Found: {$row['id']} {$row['name']} parent_id={$row['parent_id']} guard={$row['guard_name']}\n";
        if ($row['parent_id']) {
            $p = $mysqli->query('SELECT id,name FROM permissions WHERE id=' . intval($row['parent_id']))->fetch_assoc();
            if ($p) echo "  Parent: {$p['id']} {$p['name']}\n";
        }
    } else {
        echo "Not found: $n\n";
    }
}

// list top-level websetting parent
$res = $mysqli->query("SELECT id,name FROM permissions WHERE name LIKE '%websetting%' OR name LIKE '%web-setting%' OR name LIKE '%web-setting%' LIMIT 20");
while ($r = $res->fetch_assoc()) {
    echo "Row: {$r['id']} {$r['name']}\n";
}

$mysqli->close();
