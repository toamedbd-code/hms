<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\LaboratoryController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaboratoryModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $this->app['db']->purge('sqlite');

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('type')->default('asset');
            $table->string('account_group')->nullable();
            $table->boolean('is_profit_loss')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->date('date')->nullable();
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 15, 2);
            $table->string('entry_type');
            $table->text('narration')->nullable();
            $table->timestamps();
        });
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('loss', 15, 2)->default(0);
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_10_07_000006_create_standalone_lab_tables.php');
        $migration->up();
        $procurementMigration = require database_path('migrations/2026_10_07_000007_create_lab_procurement_tables.php');
        $procurementMigration->up();
        $moduleSettingsMigration = require database_path('migrations/2026_10_07_000008_create_module_management_settings_table.php');
        $moduleSettingsMigration->up();
    }

    public function test_lab_patient_order_sample_result_verification_and_payment_flow(): void
    {
        $controller = app(LaboratoryController::class);
        $controller->storeInventoryItem(Request::create('/lab/inventory', 'POST', [
            'name' => 'CBC Reagent',
            'category' => 'Hematology',
            'unit' => 'ml',
            'reorder_level' => 2,
            'opening_stock' => 10,
        ]));
        $inventoryItem = DB::table('lab_inventory_items')->first();

        $controller->storePatient(Request::create('/lab/patients', 'POST', [
            'name' => 'Lab Test Patient',
            'phone' => '01700000000',
            'gender' => 'Female',
        ]));
        $patient = DB::table('lab_patients')->first();

        $controller->storeTest(Request::create('/lab/tests', 'POST', [
            'test_code' => 'CBC-001',
            'name' => 'Complete Blood Count',
            'category' => 'Hematology',
            'specimen' => 'Blood',
            'unit' => 'cells/uL',
            'reference_range' => '4-11',
            'price' => 500,
            'turnaround_hours' => 4,
            'inventory_items' => [['lab_inventory_item_id' => $inventoryItem->id, 'quantity_per_test' => 2]],
        ]));
        $test = DB::table('lab_tests')->first();

        $controller->storeOrder(Request::create('/lab/orders', 'POST', [
            'lab_patient_id' => $patient->id,
            'tests' => [['lab_test_id' => $test->id, 'quantity' => 1]],
        ]));
        $order = DB::table('lab_orders')->first();
        $orderItem = DB::table('lab_order_items')->first();
        $this->assertSame('Registered', $order->status);

        $controller->collectSample(Request::create('/lab/order-items/collect', 'POST'), $orderItem->id);
        $this->assertSame('Collected', DB::table('lab_order_items')->where('id', $orderItem->id)->value('sample_status'));

        $controller->saveResult(Request::create('/lab/order-items/result', 'POST', ['result_text' => 'Within reference range']), $orderItem->id);
        $this->assertSame('Entered', DB::table('lab_order_items')->where('id', $orderItem->id)->value('result_status'));

        $controller->adjustInventory(Request::create('/lab/inventory/adjust', 'POST', [
            'movement_type' => 'out',
            'quantity' => 9,
            'reason' => 'Test low-stock verification guard',
        ]), $inventoryItem->id);
        try {
            $controller->verifyResult(Request::create('/lab/order-items/verify', 'POST'), $orderItem->id);
            $this->fail('Expected result verification to reject insufficient reagent stock.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('result', $exception->errors());
        }
        $this->assertSame('Entered', DB::table('lab_order_items')->where('id', $orderItem->id)->value('result_status'));
        $this->assertEquals(1, DB::table('lab_inventory_items')->where('id', $inventoryItem->id)->value('current_stock'));

        $controller->adjustInventory(Request::create('/lab/inventory/adjust', 'POST', [
            'movement_type' => 'in',
            'quantity' => 1,
            'reason' => 'Restock for verification',
        ]), $inventoryItem->id);
        $controller->verifyResult(Request::create('/lab/order-items/verify', 'POST'), $orderItem->id);
        $this->assertSame('Completed', DB::table('lab_orders')->where('id', $order->id)->value('status'));
        $this->assertEquals(0, DB::table('lab_inventory_items')->where('id', $inventoryItem->id)->value('current_stock'));
        $this->assertSame(4, DB::table('lab_inventory_movements')->count());

        $controller->storePayment(Request::create('/lab/orders/payment', 'POST', [
            'amount' => 200,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Cash',
        ]), $order->id);
        $this->assertEquals(200, DB::table('lab_payments')->where('lab_order_id', $order->id)->sum('amount'));
        $labPayment = DB::table('lab_payments')->where('lab_order_id', $order->id)->first();
        $this->assertSame(1, DB::table('ledger_transactions')
            ->where('reference_type', 'LabPayment')
            ->where('reference_id', $labPayment->id)
            ->count());
        $this->assertEquals(200, DB::table('ledger_entries')
            ->where('transaction_id', DB::table('ledger_transactions')
                ->where('reference_type', 'LabPayment')
                ->where('reference_id', $labPayment->id)
                ->value('id'))
            ->where('entry_type', 'credit')
            ->sum('amount'));

        try {
            $controller->storePayment(Request::create('/lab/orders/payment', 'POST', [
                'amount' => 301,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Cash',
            ]), $order->id);
            $this->fail('Expected overpayment to be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertEquals(200, DB::table('lab_payments')->where('lab_order_id', $order->id)->sum('amount'));
    }

    public function test_unmanaged_lab_verifies_without_stock_and_patient_payment_posts_to_ledger(): void
    {
        $controller = app(LaboratoryController::class);
        app(\App\Services\ModuleManagementSettings::class)->setLabManaged(false, null);
        $controller->storeInventoryItem(Request::create('/lab/inventory', 'POST', [
            'name' => 'Unavailable Reagent',
            'unit' => 'ml',
            'reorder_level' => 1,
            'opening_stock' => 0,
        ]));
        $inventoryItem = DB::table('lab_inventory_items')->first();
        $controller->storePatient(Request::create('/lab/patients', 'POST', ['name' => 'Unmanaged Patient']));
        $patient = DB::table('lab_patients')->first();
        $controller->storeTest(Request::create('/lab/tests', 'POST', [
            'test_code' => 'UNM-001',
            'name' => 'Unmanaged Test',
            'price' => 500,
            'turnaround_hours' => 24,
            'inventory_items' => [['lab_inventory_item_id' => $inventoryItem->id, 'quantity_per_test' => 5]],
        ]));
        $test = DB::table('lab_tests')->first();
        $controller->storeOrder(Request::create('/lab/orders', 'POST', [
            'lab_patient_id' => $patient->id,
            'tests' => [['lab_test_id' => $test->id, 'quantity' => 1]],
        ]));
        $order = DB::table('lab_orders')->first();
        $item = DB::table('lab_order_items')->first();
        $controller->collectSample(Request::create('/lab/order-items/collect', 'POST'), $item->id);
        $controller->saveResult(Request::create('/lab/order-items/result', 'POST', ['result_text' => 'Verified without stock']), $item->id);
        $controller->verifyResult(Request::create('/lab/order-items/verify', 'POST'), $item->id);

        $this->assertSame('Verified', DB::table('lab_order_items')->where('id', $item->id)->value('result_status'));
        $this->assertEquals(0, DB::table('lab_inventory_items')->where('id', $inventoryItem->id)->value('current_stock'));
        $this->assertSame(0, DB::table('lab_inventory_movements')->where('movement_type', 'out')->count());

        $controller->storePayment(Request::create('/lab/orders/payment', 'POST', [
            'amount' => 500,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Cash',
        ]), $order->id);
        $paymentId = DB::table('lab_payments')->value('id');
        $this->assertSame(1, DB::table('ledger_transactions')
            ->where('reference_type', 'LabPayment')
            ->where('reference_id', $paymentId)
            ->count());
        $this->assertEquals(500, app(\App\Services\ReportAccountingService::class)
            ->getLabPaymentTotal(['single_date_range' => [today()->toDateString(), today()->toDateString()]]));
    }

    public function test_lab_inventory_adjustments_track_movements_and_prevent_negative_balance(): void
    {
        $controller = app(LaboratoryController::class);
        $controller->storeInventoryItem(Request::create('/lab/inventory', 'POST', [
            'name' => 'CBC Reagent',
            'category' => 'Hematology',
            'unit' => 'ml',
            'reorder_level' => 2,
            'opening_stock' => 10,
        ]));
        $item = DB::table('lab_inventory_items')->first();

        $controller->adjustInventory(Request::create('/lab/inventory/adjust', 'POST', [
            'movement_type' => 'out',
            'quantity' => 3,
            'reason' => 'Daily lab use',
        ]), $item->id);

        $this->assertEquals(7, DB::table('lab_inventory_items')->where('id', $item->id)->value('current_stock'));
        $this->assertSame(2, DB::table('lab_inventory_movements')->count());

        try {
            $controller->adjustInventory(Request::create('/lab/inventory/adjust', 'POST', [
                'movement_type' => 'out',
                'quantity' => 8,
                'reason' => 'Overdraw attempt',
            ]), $item->id);
            $this->fail('Expected insufficient inventory to reject the adjustment.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertEquals(7, DB::table('lab_inventory_items')->where('id', $item->id)->value('current_stock'));
    }

    public function test_lab_inventory_item_can_be_created_as_json_for_purchase_order_form(): void
    {
        $response = app(LaboratoryController::class)->storeInventoryItem(
            Request::create('/lab/inventory', 'POST', [
                'name' => 'Order Reagent',
                'category' => 'Chemistry',
                'unit' => 'kit',
                'reorder_level' => 3,
                'opening_stock' => 2,
            ], [], [], ['HTTP_ACCEPT' => 'application/json'])
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Order Reagent', $response->getData(true)['item']['name']);
        $this->assertEquals(2, DB::table('lab_inventory_items')->where('name', 'Order Reagent')->value('current_stock'));
        $this->assertSame(1, DB::table('lab_inventory_movements')->count());
    }

    public function test_lab_supplier_can_be_created_as_json_for_purchase_order_form(): void
    {
        $response = app(LaboratoryController::class)->storeSupplier(
            Request::create('/lab/suppliers', 'POST', [
                'name' => 'Quick Supplier',
                'phone' => '01700000000',
                'contact_person' => 'Supplier Contact',
                'email' => 'supplier@example.test',
                'address' => 'Dhaka',
            ], [], [], ['HTTP_ACCEPT' => 'application/json'])
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Quick Supplier', $response->getData(true)['supplier']['name']);
        $this->assertSame(1, DB::table('lab_suppliers')->count());
    }

    public function test_lab_purchase_receipt_updates_consumable_inventory_and_supplier_payment_posts_expense(): void
    {
        $controller = app(LaboratoryController::class);
        $controller->storeInventoryItem(Request::create('/lab/inventory', 'POST', [
            'name' => 'Test Reagent',
            'unit' => 'bottle',
            'reorder_level' => 2,
            'opening_stock' => 1,
        ]));
        $item = DB::table('lab_inventory_items')->first();
        $controller->storeSupplier(Request::create('/lab/suppliers', 'POST', [
            'name' => 'Lab Vendor',
            'phone' => '01700000000',
        ]));
        $supplier = DB::table('lab_suppliers')->first();

        $controller->storePurchaseOrder(Request::create('/lab/purchase-orders', 'POST', [
            'lab_supplier_id' => $supplier->id,
            'order_date' => today()->toDateString(),
            'items' => [[
                'lab_inventory_item_id' => $item->id,
                'quantity' => 10,
                'unit_cost' => 13,
            ]],
        ]));
        $order = DB::table('lab_purchase_orders')->first();
        $orderItem = DB::table('lab_purchase_order_items')->first();

        $controller->storePurchaseReceipt(Request::create('/lab/purchase-orders/receive', 'POST', [
            'received_date' => today()->toDateString(),
            'items' => [[
                'lab_purchase_order_item_id' => $orderItem->id,
                'quantity' => 5,
                'batch_no' => 'LOT-001',
            ]],
        ]), $order->id, app(\App\Services\LabPurchaseLedgerService::class));

        $this->assertEquals(6, DB::table('lab_inventory_items')->where('id', $item->id)->value('current_stock'));
        $this->assertSame('Partially Received', DB::table('lab_purchase_orders')->where('id', $order->id)->value('status'));
        $this->assertSame(5.0, (float) DB::table('lab_purchase_receipt_items')->sum('quantity'));
        $this->assertSame('LAB_INV', DB::table('accounts')->where('name', 'Laboratory Inventory')->value('code'));
        $this->assertDatabaseHas('lab_inventory_movements', [
            'lab_inventory_item_id' => $item->id,
            'movement_type' => 'in',
            'quantity' => 5,
            'reason' => 'Received against Lab purchase order ' . $order->order_no,
        ]);

        $cashId = DB::table('accounts')->insertGetId([
            'code' => 'CASH',
            'name' => 'Cash',
            'type' => 'asset',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $controller->storePurchasePayment(Request::create('/lab/purchase-orders/pay', 'POST', [
            'amount' => 65,
            'payment_date' => today()->toDateString(),
            'payment_account_id' => $cashId,
        ]), $order->id, app(\App\Services\LabPurchaseLedgerService::class));

        $paymentLedger = DB::table('ledger_transactions')
            ->where('reference_type', 'LabPurchasePayment')
            ->first();
        $this->assertNotNull($paymentLedger);
        $this->assertSame(4, DB::table('ledger_entries')->where('transaction_id', $paymentLedger->id)->count());
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $paymentLedger->id,
            'account_id' => DB::table('accounts')->where('code', 'LAB_PURCHASE_EXP')->value('id'),
            'entry_type' => 'debit',
            'amount' => 65,
        ]);
        $this->assertSame(65.0, (float) DB::table('lab_purchase_payments')->sum('amount'));

        try {
            $controller->storePurchasePayment(Request::create('/lab/purchase-orders/pay', 'POST', [
                'amount' => 1,
                'payment_date' => today()->toDateString(),
                'payment_account_id' => $cashId,
            ]), $order->id, app(\App\Services\LabPurchaseLedgerService::class));
            $this->fail('Expected payment beyond received value to be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(65.0, (float) DB::table('lab_purchase_payments')->sum('amount'));
    }
}
