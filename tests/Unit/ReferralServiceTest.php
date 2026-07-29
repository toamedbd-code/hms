<?php

namespace Tests\Unit;

use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
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

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->default('Active');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('referralpeople', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('patient_mobile')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_referrals_are_ordered_by_payee_name_alphabetically(): void
    {
        $service = app(ReferralService::class);

        $payeeZ = \App\Models\ReferralPerson::create(['name' => 'Zed', 'status' => 'Active']);
        $payeeA = \App\Models\ReferralPerson::create(['name' => 'Alice', 'status' => 'Active']);
        $payeeB = \App\Models\ReferralPerson::create(['name' => 'Bob', 'status' => 'Active']);

        Referral::create(['billing_id' => 1, 'payee_id' => $payeeZ->id, 'date' => '2026-01-01', 'status' => 'Active']);
        Referral::create(['billing_id' => 2, 'payee_id' => $payeeA->id, 'date' => '2026-01-02', 'status' => 'Active']);
        Referral::create(['billing_id' => 3, 'payee_id' => $payeeB->id, 'date' => '2026-01-03', 'status' => 'Active']);

        $payeeNames = $service->list()->get()->map(function ($referral) {
            return $referral->payee?->name;
        })->all();

        $this->assertSame(['Alice', 'Bob', 'Zed'], $payeeNames);
    }
}
