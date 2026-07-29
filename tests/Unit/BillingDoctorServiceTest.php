<?php

namespace Tests\Unit;

use App\Services\BillingDoctorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingDoctorServiceTest extends TestCase
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

        Schema::create('billing_doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_billing_doctors_are_returned_in_alphabetical_order(): void
    {
        $service = app(BillingDoctorService::class);

        $service->create(['name' => 'Zed', 'status' => 'Active']);
        $service->create(['name' => 'Alice', 'status' => 'Active']);
        $service->create(['name' => 'Bob', 'status' => 'Active']);

        $names = $service->list()->pluck('name')->all();

        $this->assertSame(['Alice', 'Bob', 'Zed'], $names);
    }
}
