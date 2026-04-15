<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hms_local;charset=utf8mb4', 'root', '');
    $stmt = $pdo->query('SHOW COLUMNS FROM payments');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo $r['Field'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
