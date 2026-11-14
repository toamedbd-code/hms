<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MedicineCategory;
use App\Models\MedicineInventory;
use App\Models\MedicinePurchase;
use App\Models\MedicineSupplier;
use App\Models\PharmacyBill;
use App\Models\ProductReturn;
use App\Models\PurchaseItem;
use App\Models\ReturnItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PharmacyProfitLossReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_calculates_received_purchase_cost_and_sales_profit_for_custom_dates(): void
    {
        $this->withoutMiddleware();

        $admin = Admin::create([
            'first_name' => 'Report',
            'last_name' => 'Admin',
            'email' => 'profit-report@example.test',
            'phone' => '01911111111',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $supplier = MedicineSupplier::create([
            'name' => 'Report Supplier',
            'phone' => '01922222222',
            'contact_person_name' => 'Contact',
            'contact_person_phone' => '01933333333',
            'drug_lisence_no' => 'LIC-REPORT',
            'address' => 'Test address',
            'status' => 'Active',
        ]);
        $category = MedicineCategory::create(['name' => 'Tablet', 'status' => 'Active']);
        $medicine = MedicineInventory::create([
            'supplier_id' => $supplier->id,
            'medicine_category_id' => $category->id,
            'medicine_name' => 'Report Medicine',
            'medicine_unit_purchase_price' => 0,
            'medicine_unit_selling_price' => 10,
            'medicine_total_purchase_price' => 40,
            'medicine_total_selling_price' => 100,
            'medicine_quantity' => 7,
            'status' => 'Active',
        ]);

        $purchase = MedicinePurchase::create([
            'supplier_id' => $supplier->id,
            'purchase_number' => 'PHPN-REPORT-1',
            'purchase_date' => '2026-10-10',
            'total_amount' => 20,
            'paid_amount' => 20,
            'due_amount' => 0,
            'status' => 'received',
        ]);
        PurchaseItem::create([
            'medicine_purchase_id' => $purchase->id,
            'medicine_category_id' => $category->id,
            'medicine_name' => 'Report Medicine',
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_purchase_price' => 2,
            'unit_selling_price' => 10,
            'discount' => 0,
            'total_purchase_price' => 20,
        ]);

        $bill = PharmacyBill::create([
            'pharmacy_no' => 'PHAB-REPORT-1',
            'bill_no' => 'PHAB-BILL-REPORT-1',
            'date' => '2026-10-10',
            'products' => json_encode([[
                'productId' => $medicine->id,
                'productName' => 'Report Medicine',
                'quantity' => 3,
                'rate' => 0,
                'amount' => 0,
            ]]),
            'subtotal' => 30,
            'discount_amount' => 0,
            'extra_discount' => 0,
            'net_amount' => 30,
            'payment_mode' => 'Cash',
            'payment_amount' => 30,
            'created_by' => $admin->id,
            'status' => 'Active',
        ]);
        $return = ProductReturn::create([
            'return_number' => 'RET-REPORT-1',
            'return_type' => 'customer',
            'source_bill_no' => $bill->bill_no,
            'return_date' => '2026-10-10',
            'total_amount' => 10,
            'status' => 'processed',
        ]);
        ReturnItem::create([
            'product_return_id' => $return->id,
            'medicine_inventory_id' => $medicine->id,
            'quantity' => 1,
            'unit_price' => 10,
            'total_amount' => 10,
            'condition' => 'good',
        ]);

        $this->get(route('backend.pharmacy.profit-loss.report', [
            'period' => 'custom',
            'from' => '2026-10-10',
            'to' => '2026-10-10',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/PharmacyProfitLossReport/Index')
                ->where('summary.received_qty', 10)
                ->where('summary.purchase_cost', 20)
                ->where('summary.bill_count', 1)
                ->where('summary.sold_revenue', 20)
                ->where('summary.sold_cost', 4)
                ->where('summary.profit', 16)
                ->where('summary.item_count', 1)
                ->where('summary.net_sold_qty', 2)
                ->where('itemSummary.0.medicine_name', 'Report Medicine')
                ->where('itemSummary.0.sold_qty', 3)
                ->where('itemSummary.0.returned_qty', 1)
                ->where('itemSummary.0.net_qty', 2)
                ->where('itemSummary.0.sold_revenue', 20)
                ->where('itemSummary.0.sold_cost', 4)
                ->where('itemSummary.0.profit', 16)
                ->where('breakdown.0.date', '2026-10-10')
            );
    }
}
