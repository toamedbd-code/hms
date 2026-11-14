<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestoreMissingMedicineInventoriesTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->app['db']->purge('sqlite');
    }

    public function test_missing_medicine_inventory_table_is_restored_with_current_columns(): void
    {
        $migration = require database_path(
            'migrations/2026_10_29_000001_restore_missing_medicineinventories_table.php'
        );
        $migration->up();

        $columns = Schema::getColumnListing('medicineinventories');

        $this->assertContains('medicine_name', $columns);
        $this->assertContains('generic_name', $columns);
        $this->assertContains('expiry_date', $columns);
        $this->assertContains('standard_prescription_verified', $columns);
        $this->assertContains('deleted_at', $columns);
    }

    public function test_migration_does_not_replace_an_existing_inventory_table(): void
    {
        Schema::create('medicineinventories', function (Blueprint $table) {
            $table->id();
            $table->string('medicine_name');
        });

        $migration = require database_path(
            'migrations/2026_10_29_000001_restore_missing_medicineinventories_table.php'
        );
        $migration->up();

        $this->assertSame(
            ['id', 'medicine_name'],
            Schema::getColumnListing('medicineinventories')
        );
    }
}
