<?php

namespace Tests\Unit;

use App\Services\ReferralCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralCategoryServiceTest extends TestCase
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

        Schema::create('referralcategories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_referral_categories_are_returned_in_alphabetical_order(): void
    {
        $service = app(ReferralCategoryService::class);

        $service->create(['name' => 'Zulu', 'status' => 'Active']);
        $service->create(['name' => 'Alpha', 'status' => 'Active']);
        $service->create(['name' => 'Mike', 'status' => 'Active']);

        $names = $service->list()->pluck('name')->all();

        $this->assertSame(['Alpha', 'Mike', 'Zulu'], $names);
    }
}
