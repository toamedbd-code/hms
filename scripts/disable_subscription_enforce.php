<?php
// Usage: php scripts/disable_subscription_enforce.php
// This script sets SUBSCRIPTION_ENFORCE=false in the project's .env
// and runs common artisan cache clear commands. Run on the server where
// the application is hosted (or locally for testing).

$envPath = __DIR__ . '/../.env';
if (! file_exists($envPath)) {
    echo ".env not found at: {$envPath}\n";
    exit(1);
}

$contents = file_get_contents($envPath);
if ($contents === false) {
    echo "Failed to read .env\n";
    exit(1);
}

if (preg_match('/^SUBSCRIPTION_ENFORCE=.*$/m', $contents)) {
    $new = preg_replace('/^SUBSCRIPTION_ENFORCE=.*$/m', 'SUBSCRIPTION_ENFORCE=false', $contents);
    echo "Replaced existing SUBSCRIPTION_ENFORCE value.\n";
} else {
    $new = rtrim($contents, "\n") . PHP_EOL . 'SUBSCRIPTION_ENFORCE=false' . PHP_EOL;
    echo "Appended SUBSCRIPTION_ENFORCE=false to .env.\n";
}

file_put_contents($envPath, $new);
echo ".env updated. Now clearing Laravel caches...\n";

$commands = [
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan route:clear',
    'php artisan view:clear',
];

foreach ($commands as $cmd) {
    echo "Running: {$cmd}\n";
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "Command returned exit code: {$exitCode}\n";
    }
}

echo "Done. If this is a production server, restart PHP-FPM / webserver as needed.\n";
