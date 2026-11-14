<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\MedicineInventoryController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicineInventoryCsvImportTest extends TestCase
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
        config()->set('cache.default', 'array');
        $this->app['db']->purge('sqlite');

        Schema::create('medicinesuppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('contact_person_name');
            $table->string('contact_person_phone');
            $table->string('drug_lisence_no');
            $table->string('address');
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('medicinecategories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('medicineinventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('medicine_category_id');
            $table->string('medicine_name');
            $table->string('generic_name')->nullable();
            $table->decimal('medicine_unit_purchase_price', 11, 2);
            $table->decimal('medicine_unit_selling_price', 11, 2);
            $table->decimal('medicine_total_purchase_price', 11, 2);
            $table->decimal('medicine_total_selling_price', 11, 2);
            $table->integer('medicine_quantity');
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });

        \App\Models\MedicineSupplier::create([
            'name' => 'Supplier A',
            'phone' => 'N/A',
            'contact_person_name' => 'N/A',
            'contact_person_phone' => 'N/A',
            'drug_lisence_no' => 'N/A',
            'address' => 'N/A',
            'status' => 'Active',
        ]);
        \App\Models\MedicineCategory::create(['name' => 'Tablet', 'status' => 'Active']);
    }

    public function test_csv_import_batches_rows_skips_duplicates_and_records_progress(): void
    {
        $importId = 'c4b8c2f0-7112-4c20-98ae-f6ed4dfb70d8';
        $csv = implode("\n", [
            'supplier,category,medicine_name,unit_purchase_price,unit_selling_price,quantity',
            'Supplier A,Tablet,Medicine Alpha,10,15,20',
            'Supplier A,Tablet,Medicine Beta,12,18,30',
            'Supplier A,Tablet,medicine alpha,10,15,20',
        ]);
        $request = \Illuminate\Http\Request::create(
            '/backend/medicineinventory/import-csv',
            'POST',
            ['import_id' => $importId, 'skip_duplicates' => '1'],
            [],
            ['csv_file' => UploadedFile::fake()->createWithContent('medicines.csv', $csv)],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response = app(MedicineInventoryController::class)->importCsv($request);
        $responseData = $response->getData(true);

        $this->assertTrue($responseData['status']);
        $this->assertSame(2, $responseData['imported']);
        $this->assertSame(1, $responseData['skipped']);
        $this->assertSame(2, Schema::getConnection()->table('medicineinventories')->count());
        $this->assertSame(
            'completed',
            Cache::get('medicine_csv_import::' . $importId)['status']
        );
        $this->assertSame(
            100,
            Cache::get('medicine_csv_import::' . $importId)['progress']
        );
    }

    public function test_csv_import_flushes_large_files_in_batches(): void
    {
        $importId = 'c4b8c2f0-7112-4c20-98ae-f6ed4dfb70d9';
        $rows = ['supplier,category,medicine_name,unit_purchase_price,unit_selling_price,quantity'];
        for ($index = 1; $index <= 501; $index++) {
            $rows[] = "Supplier A,Tablet,Medicine {$index},10,15,20";
        }

        $request = \Illuminate\Http\Request::create(
            '/backend/medicineinventory/import-csv',
            'POST',
            ['import_id' => $importId, 'skip_duplicates' => '1'],
            [],
            ['csv_file' => UploadedFile::fake()->createWithContent('large-medicines.csv', implode("\n", $rows))],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response = app(MedicineInventoryController::class)->importCsv($request);

        $this->assertTrue($response->getData(true)['status']);
        $this->assertSame(501, Schema::getConnection()->table('medicineinventories')->count());
    }
}
