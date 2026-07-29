<?php
require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

$base = realpath(__DIR__ . '/..');
if (file_exists($base . '/.env')) {
    $dotenv = Dotenv::createImmutable($base);
    $dotenv->load();
}

$config = [
    'driver' => getenv('DB_CONNECTION') === 'sqlite' ? 'sqlite' : getenv('DB_CONNECTION'),
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'database' => getenv('DB_DATABASE') ?: $base . '/database/database.sqlite',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
];

$capsule = new Capsule;
$capsule->addConnection($config);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$billing = Capsule::table('billings')->orderByDesc('id')->first();
if (! $billing) {
    echo "No billing found.\n";
    exit(0);
}

echo "Latest billing id: {$billing->id}, bill_number: {$billing->bill_number}\n";
$items = Capsule::table('bill_items')->where('billing_id', $billing->id)->get();

foreach ($items as $it) {
    echo "id={$it->id} item_name={$it->item_name} category={$it->category} sample_collected_at={$it->sample_collected_at} reported_at={$it->reported_at} requires_sample={$it->requires_sample}\n";
}
