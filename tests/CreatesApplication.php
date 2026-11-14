<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (getenv('APP_ENV') === 'testing') {
            $testDatabase = 'hms_test';

            config([
                'app.env' => 'testing',
                'database.default' => 'mysql',
                'database.connections.mysql.host' => getenv('DB_HOST') ?: '127.0.0.1',
                'database.connections.mysql.port' => getenv('DB_PORT') ?: '3306',
                'database.connections.mysql.database' => $testDatabase,
                'database.connections.mysql.username' => getenv('DB_USERNAME') ?: 'root',
                'database.connections.mysql.password' => getenv('DB_PASSWORD') ?: '',
            ]);
            $app->make('db')->purge('mysql');
        }

        return $app;
    }
}
