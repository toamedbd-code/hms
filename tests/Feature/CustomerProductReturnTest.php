<?php

namespace Tests\Feature;

use App\Models\MedicineInventory;
use App\Models\Patient;
use App\Models\PharmacyBill;
use App\Models\ProductReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerProductReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_return_loads_and_restocks_medicine_from_billing_module_invoice(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'email' => 'return-test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = new \Illuminate\Auth\GenericUser([
            'id' => $adminId,
            'name' => 'Return Test',
            'email' => 'return-test@example.com',
        ]);
        $this->withoutMiddleware();
        $this->actingAs($admin, 'admin');

        $patient = Patient::create([
            'name' => 'Return Patient',
            'phone' => '01700000000',
        ]);
        $medicine = MedicineInventory::create([
            'supplier_id' => 1,
            'medicine_category_id' => 1,
            'medicine_name' => 'Test Medicine',
            'medicine_unit_purchase_price' => 8,
            'medicine_unit_selling_price' => 10,
            'medicine_total_purchase_price' => 80,
            'medicine_total_selling_price' => 100,
            'medicine_quantity' => 4,
            'status' => 'Active',
        ]);
        $legacyMedicine = MedicineInventory::create([
            'supplier_id' => 1,
            'medicine_category_id' => 1,
            'medicine_name' => 'Legacy Medicine',
            'medicine_unit_purchase_price' => 6,
            'medicine_unit_selling_price' => 8,
            'medicine_total_purchase_price' => 24,
            'medicine_total_selling_price' => 32,
            'medicine_quantity' => 4,
            'status' => 'Active',
        ]);
        PharmacyBill::create([
            'pharmacy_no' => 'PHAB-RETURN-TEST',
            'bill_no' => 'BILL-RETURN-TEST',
            'date' => today(),
            'patient_id' => $patient->id,
            'products' => json_encode([[
                'medicine_id' => $medicine->id,
                'medicine_name' => $medicine->medicine_name,
                'quantity' => 2,
                'unit_price' => 10,
            ], [
                'productId' => $legacyMedicine->id,
                'productName' => $legacyMedicine->medicine_name,
                'quantity' => 1,
                'rate' => 8,
            ]]),
            'subtotal' => 28,
            'net_amount' => 28,
            'payment_mode' => 'Cash',
            'payment_amount' => 20,
            'created_by' => $adminId,
            'status' => 'Active',
        ]);

        $this->get(route('backend.productreturn.create', [
            'return_type' => 'customer',
            'source_bill_no' => 'BILL-RETURN-TEST',
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/ProductReturn/Form')
                ->where('sourceBillFound', true)
                ->where('sourceBillItems.0.medicine_inventory_id', $medicine->id)
                ->where('sourceBillItems.0.medicine_name', 'Test Medicine')
                ->where('sourceBillItems.0.billed_quantity', 2)
                ->where('sourceBillItems.0.unit_price', 10)
                ->where('sourceBillItems.1.medicine_inventory_id', $legacyMedicine->id)
                ->where('sourceBillItems.1.medicine_name', 'Legacy Medicine')
                ->where('sourceBillItems.1.billed_quantity', 1)
                ->where('sourceBillItems.1.unit_price', 8)
            );

        $this->post(route('backend.productreturn.store'), [
            'return_type' => 'customer',
            'source_bill_no' => 'BILL-RETURN-TEST',
            'customer_name' => 'Return Patient',
            'return_date' => today()->toDateString(),
            'items' => [[
                'medicine_inventory_id' => $medicine->id,
                'quantity' => 1,
                'unit_price' => 10,
                'condition' => 'good',
            ], [
                'medicine_inventory_id' => $legacyMedicine->id,
                'quantity' => 1,
                'unit_price' => 8,
                'condition' => 'good',
            ]],
        ])->assertRedirect(route('backend.productreturn.index'));

        $this->assertSame(5, $medicine->fresh()->medicine_quantity);
        $this->assertSame(5, $legacyMedicine->fresh()->medicine_quantity);
        $this->assertDatabaseHas('product_returns', [
            'source_bill_no' => 'BILL-RETURN-TEST',
            'total_amount' => 18,
        ]);

        $this->get(route('backend.productreturn.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/ProductReturn/Index')
                ->where('returnType', 'all')
                ->has('returns.data', 1)
                ->where('returns.data.0.source_bill_no', 'BILL-RETURN-TEST')
                ->where('returns.data.0.customer_name', 'Return Patient')
                ->where('returns.data.0.paid_amount', '0.00')
                ->where('returns.data.0.due_amount', 18)
                ->where('returns.data.0.status', 'pending')
                ->where('returns.data.0.payment_status', 'unpaid')
            );

        foreach (range(1, 24) as $returnNumber) {
            ProductReturn::query()->create([
                'return_number' => 'RET-PAGINATION-' . $returnNumber,
                'return_type' => 'customer',
                'source_bill_no' => 'BILL-RETURN-TEST',
                'customer_name' => 'Return Patient',
                'return_date' => today(),
                'total_amount' => $returnNumber === 24 ? 987654321.25 : 10,
                'paid_amount' => $returnNumber === 24 ? 123456789.25 : 0,
                'payment_status' => $returnNumber === 24 ? 'partial' : 'unpaid',
                'status' => 'pending',
            ]);
        }

        $pdfResponse = $this->get(route('backend.productreturn.print', ['return_type' => 'all']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
        $this->assertStringContainsString('no-store', $pdfResponse->headers->get('Cache-Control'));
        $this->assertLessThan(
            5,
            (int) $pdfResponse->headers->get('X-PDF-Page-Count'),
            'The filtered return list should paginate rows instead of creating a page per cell.'
        );
    }
}
