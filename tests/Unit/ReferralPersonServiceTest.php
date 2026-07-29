<?php

namespace Tests\Unit;

use App\Services\ReferralPersonService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralPersonServiceTest extends TestCase
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

        Schema::create('referralpeople', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_referral_persons_are_returned_in_alphabetical_order(): void
    {
        $service = app(ReferralPersonService::class);

        $service->create(['name' => 'Zed', 'status' => 'Active']);
        $service->create(['name' => 'Alice', 'status' => 'Active']);
        $service->create(['name' => 'Bob', 'status' => 'Active']);

        $names = $service->list()->pluck('name')->all();

        $this->assertSame(['Alice', 'Bob', 'Zed'], $names);
    }
}
