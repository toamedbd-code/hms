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
if ($mysqli->connect_error) { echo "DB connect error: " . $mysqli->connect_error . "\n"; exit(1);} 

function dumpTree($mysqli, $exclude = []) {
    $exclPlaceholders = '';
    if ($exclude) {
        $in = implode(',', array_map(function($i){return "'".addslashes($i)."'";}, $exclude));
        $query = "SELECT id,name FROM permissions WHERE parent_id IS NULL AND guard_name='admin' AND name NOT IN ($in) ORDER BY id";
    } else {
        $query = "SELECT id,name FROM permissions WHERE parent_id IS NULL AND guard_name='admin' ORDER BY id";
    }
    echo "Query: $query\n";
    $res = $mysqli->query($query);
    while ($r = $res->fetch_assoc()) {
        echo "Parent: {$r['id']} {$r['name']}\n";
        $children = $mysqli->query('SELECT id,name,parent_id FROM permissions WHERE parent_id=' . intval($r['id']) . " ORDER BY id");
        while ($c = $children->fetch_assoc()) {
            echo "  Child: {$c['id']} {$c['name']}\n";
            $grand = $mysqli->query('SELECT id,name,parent_id FROM permissions WHERE parent_id=' . intval($c['id']) . " ORDER BY id");
            while ($g = $grand->fetch_assoc()) {
                echo "    Grand: {$g['id']} {$g['name']}\n";
            }
        }
    }
}

echo "=== With exclusion ===\n";
dumpTree($mysqli, ['dutyroaster-management', 'salary-management']);

echo "\n=== Without exclusion ===\n";
dumpTree($mysqli, []);
$mysqli->close();
