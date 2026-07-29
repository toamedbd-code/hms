<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\BillingController;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Pathology;
use App\Models\PharmacyBill;
use App\Models\Radiology;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        DB::purge('sqlite');
        DB::connection('sqlite')->getSchemaBuilder()->create('billings', function ($table) {
            $table->id();
            $table->string('invoice_number')->nullable();
            $table->string('bill_number')->nullable();
            $table->string('case_number')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('payable_amount', 15, 2)->default(0);
            $table->decimal('paid_amt', 15, 2)->default(0);
            $table->decimal('return_amt', 15, 2)->default(0);
            $table->decimal('extra_flat_discount', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_it_persists_created_at_when_updated_via_service(): void
    {
        $service = new BillingService(
            new Billing(),
            new BillItem(),
            new Pathology(),
            new Radiology(),
            new PharmacyBill()
        );

        $billing = Billing::create([]);

        $service->update(['created_at' => '2024-01-02 03:04:05'], $billing->id);

        $this->assertSame('2024-01-02 03:04:05', Billing::find($billing->id)->created_at->format('Y-m-d H:i:s'));
    }

    public function test_it_applies_requested_billing_date_when_editing_billing(): void
    {
        $controller = new class extends BillingController {
            public function __construct()
            {
            }

            public function resolveCreatedAtForTest($existingCreatedAt, array $data): ?string
            {
                return $this->resolvePersistedCreatedAt($existingCreatedAt, $data);
            }
        };

        $existingCreatedAt = Carbon::parse('2024-01-01 10:00:00');
        $resolvedCreatedAt = $controller->resolveCreatedAtForTest($existingCreatedAt, [
            'billing_date' => '2024-02-02',
            'billing_time' => '03:04:05',
        ]);

        $this->assertSame('2024-02-02 03:04:05', $resolvedCreatedAt);
    }

    public function test_it_preserves_existing_created_at_when_no_billing_date_is_provided(): void
    {
        $controller = new class extends BillingController {
            public function __construct()
            {
            }

            public function resolveCreatedAtForTest($existingCreatedAt, array $data): ?string
            {
                return $this->resolvePersistedCreatedAt($existingCreatedAt, $data);
            }
        };

        $existingCreatedAt = Carbon::parse('2024-01-01 10:00:00');
        $resolvedCreatedAt = $controller->resolveCreatedAtForTest($existingCreatedAt, []);

        $this->assertSame('2024-01-01 10:00:00', $resolvedCreatedAt);
    }
}
