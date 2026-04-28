<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contracts = [
    'Laravel\\Jetstream\\Contracts\\CreatesTeams',
    'Laravel\\Jetstream\\Contracts\\AddsTeamMembers',
    'Laravel\\Jetstream\\Contracts\\InvitesTeamMembers',
    'Laravel\\Jetstream\\Contracts\\RemovesTeamMembers',
    'Laravel\\Jetstream\\Contracts\\DeletesTeams',
    'Laravel\\Jetstream\\Contracts\\DeletesUsers',
    'Laravel\\Jetstream\\Contracts\\UpdatesTeamNames',
];

foreach ($contracts as $c) {
    echo $c . ': ' . ($app->bound($c) ? 'bound' : 'unbound') . PHP_EOL;
}
