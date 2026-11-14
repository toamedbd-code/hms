<?php

namespace Tests\Unit;

use App\Models\BillItem;
use App\Models\ReagentCategoryUsage;
use App\Models\ReagentBillConsumption;
use App\Models\ReagentTestUsage;
use App\Models\DepartmentReagentStock;
use App\Models\StoreItem;
use App\Models\StoreStockMovement;
use App\Services\ReagentConsumptionService;
use App\Services\DepartmentInventoryTransferService;
use App\Services\InventoryLotService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReagentConsumptionServiceTest extends TestCase
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

        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('category')->nullable();
            $table->string('stock_behavior')->default('consumable');
            $table->string('unit')->default('ml');
            $table->decimal('current_stock', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('department_reagent_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('store_item_id');
            $table->decimal('current_stock', 14, 2)->default(0);
            $table->decimal('reorder_level', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('department_reagent_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_reagent_stock_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('store_item_id');
            $table->unsignedBigInteger('inventory_lot_id')->nullable();
            $table->string('movement_type');
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('reference_no')->nullable();
            $table->text('reason')->nullable();
            $table->date('movement_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('store_item_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_item_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('asset_tag')->nullable();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->date('received_date')->nullable();
            $table->timestamps();
        });

        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('item_id');
            $table->string('item_name');
            $table->string('category');
            $table->decimal('quantity', 10, 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_item_id');
            $table->unsignedBigInteger('inventory_lot_id')->nullable();
            $table->string('movement_type');
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->text('reason')->nullable();
            $table->date('movement_date');
            $table->string('reference_no')->nullable();
            $table->string('department')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('reagent_test_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_item_id');
            $table->unsignedBigInteger('test_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('quantity_per_test', 14, 2);
            $table->timestamps();
        });

        Schema::create('reagent_category_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_item_id');
            $table->string('category_key', 100);
            $table->unsignedBigInteger('department_id');
            $table->decimal('quantity_per_test', 14, 2);
            $table->timestamps();
        });

        Schema::create('main_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('testcategories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('category_type');
            $table->unsignedBigInteger('main_category_id')->nullable();
            $table->unsignedBigInteger('test_category_id')->nullable();
            $table->string('test_name')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reagent_bill_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_item_id');
            $table->unsignedBigInteger('store_item_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('inventory_lot_id')->nullable();
            $table->unsignedBigInteger('department_movement_id')->nullable();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->decimal('quantity', 14, 2);
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_consumes_configured_reagent_amount_and_restores_it_once(): void
    {
        $reagent = StoreItem::create([
            'item_name' => 'CBC Reagent',
            'category' => 'Reagent',
            'current_stock' => 10,
            'unit_cost' => 3.5,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $reagent->id,
            'current_stock' => 10,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'BILL-1']);
        $billItem = $this->createPathologyBillItem($billingId, 100, 2);
        ReagentTestUsage::create([
            'store_item_id' => $reagent->id,
            'test_id' => 100,
            'department_id' => $departmentId,
            'quantity_per_test' => 1.5,
        ]);

        $service = app(ReagentConsumptionService::class);
        $service->prepareForBillItems([[
            'id' => $billItem->item_id,
            'category' => $billItem->category,
        ]]);
        $service->consumeForBillItem($billItem);

        $this->assertSame('10.00', $reagent->fresh()->current_stock);
        $this->assertSame('7.00', $departmentStock->fresh()->current_stock);
        $this->assertDatabaseHas('department_reagent_movements', [
            'store_item_id' => $reagent->id,
            'department_id' => $departmentId,
            'movement_type' => 'consumption',
            'quantity' => 3,
        ]);

        $service->restoreForBilling($billingId);
        $service->restoreForBilling($billingId);

        $this->assertSame('10.00', $departmentStock->fresh()->current_stock);
        $this->assertSame(1, ReagentBillConsumption::whereNotNull('reversed_at')->count());

        $updatedBillItem = $this->createPathologyBillItem($billingId, 100, 1);
        $service->consumeForBillItem($updatedBillItem);

        $this->assertSame('10.00', $reagent->fresh()->current_stock);
        $this->assertSame('8.50', $departmentStock->fresh()->current_stock);
    }

    public function test_insufficient_reagent_stock_does_not_partially_consume(): void
    {
        $reagent = StoreItem::create([
            'item_name' => 'CBC Reagent',
            'category' => 'Reagent',
            'current_stock' => 1,
            'unit_cost' => 3.5,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $reagent->id,
            'current_stock' => 1,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'BILL-2']);
        $billItem = $this->createPathologyBillItem($billingId, 101, 2);
        ReagentTestUsage::create([
            'store_item_id' => $reagent->id,
            'test_id' => 101,
            'department_id' => $departmentId,
            'quantity_per_test' => 1,
        ]);

        try {
            DB::transaction(fn () => app(ReagentConsumptionService::class)->consumeForBillItem($billItem));
            $this->fail('Expected insufficient stock to stop the bill transaction.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Insufficient department stock', $exception->errors()['reagent_stock'][0]);
        }

        $this->assertSame('1.00', $reagent->fresh()->current_stock);
        $this->assertSame(0, StoreStockMovement::count());
        $this->assertSame(0, DB::table('department_reagent_movements')->count());
        $this->assertSame(0, ReagentBillConsumption::count());
    }

    public function test_indent_issue_moves_stock_from_central_store_to_department(): void
    {
        $reagent = StoreItem::create([
            'item_name' => 'CBC Reagent',
            'category' => 'Reagent',
            'current_stock' => 12,
            'unit_cost' => 3.5,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);

        app(DepartmentInventoryTransferService::class)->issueFromCentral(
            $reagent->id,
            $departmentId,
            4,
            'SRQ-000001',
            null
        );

        $departmentStock = DepartmentReagentStock::where('department_id', $departmentId)
            ->where('store_item_id', $reagent->id)
            ->firstOrFail();

        $this->assertSame('8.00', $reagent->fresh()->current_stock);
        $this->assertSame('4.00', $departmentStock->current_stock);
        $this->assertDatabaseHas('store_stock_movements', [
            'reference_no' => 'SRQ-000001',
            'movement_type' => 'decrease',
        ]);
        $this->assertDatabaseHas('department_reagent_movements', [
            'reference_no' => 'SRQ-000001',
            'movement_type' => 'transfer_in',
        ]);
    }

    public function test_department_use_updates_balance_and_writes_auditable_movement(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Tissue Box',
            'category' => 'General Supplies',
            'unit' => 'box',
            'current_stock' => 10,
            'unit_cost' => 25,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Outpatient']);

        $transferService = app(DepartmentInventoryTransferService::class);
        $transferService->issueFromCentral($item->id, $departmentId, 6, 'SRQ-SUPPLY-01', null);
        $departmentStock = DepartmentReagentStock::query()
            ->where('department_id', $departmentId)
            ->where('store_item_id', $item->id)
            ->firstOrFail();

        $transferService->recordDepartmentUse(
            $departmentStock->id,
            2,
            'Tissue used in outpatient department',
            'USE-OUTPATIENT-01',
            null
        );

        $this->assertSame('4.00', $item->fresh()->current_stock);
        $this->assertSame('4.00', $departmentStock->fresh()->current_stock);
        $this->assertDatabaseHas('department_reagent_movements', [
            'department_reagent_stock_id' => $departmentStock->id,
            'movement_type' => 'department_use',
            'quantity' => 2,
            'reference_no' => 'USE-OUTPATIENT-01',
            'reason' => 'Tissue used in outpatient department',
        ]);
        $this->assertSame(4.0, (float) DB::table('store_item_lots')
            ->where('store_item_id', $item->id)
            ->where('department_id', $departmentId)
            ->sum('quantity'));
    }

    public function test_department_use_cannot_deduct_more_than_the_available_balance(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Soap',
            'category' => 'General Supplies',
            'unit' => 'piece',
            'current_stock' => 5,
            'unit_cost' => 10,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Ward']);
        $transferService = app(DepartmentInventoryTransferService::class);
        $transferService->issueFromCentral($item->id, $departmentId, 2, 'SRQ-SOAP-01', null);
        $departmentStock = DepartmentReagentStock::query()
            ->where('department_id', $departmentId)
            ->where('store_item_id', $item->id)
            ->firstOrFail();

        try {
            $transferService->recordDepartmentUse($departmentStock->id, 3, 'Ward use', null, null);
            $this->fail('Expected department use above the available balance to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('cannot exceed department stock', $exception->errors()['quantity'][0]);
        }

        $this->assertSame('3.00', $item->fresh()->current_stock);
        $this->assertSame('2.00', $departmentStock->fresh()->current_stock);
        $this->assertSame(0, DB::table('department_reagent_movements')->where('movement_type', 'department_use')->count());
    }

    public function test_reusable_devices_are_not_decremented_by_bill_usage(): void
    {
        $device = StoreItem::create([
            'item_name' => 'Imaging Unit',
            'category' => 'Device',
            'stock_behavior' => 'reusable',
            'current_stock' => 2,
            'unit_cost' => 1200,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Radiology']);
        DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $device->id,
            'current_stock' => 2,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'BILL-DEVICE']);
        $billItem = $this->createPathologyBillItem($billingId, 102, 1);
        ReagentTestUsage::create([
            'store_item_id' => $device->id,
            'test_id' => 102,
            'department_id' => $departmentId,
            'quantity_per_test' => 1,
        ]);

        app(ReagentConsumptionService::class)->consumeForBillItem($billItem);

        $this->assertSame('2.00', DepartmentReagentStock::where('department_id', $departmentId)->firstOrFail()->current_stock);
        $this->assertSame(0, ReagentBillConsumption::count());
    }

    public function test_radiology_bill_consumes_department_film_stock(): void
    {
        $film = StoreItem::create([
            'item_name' => 'X-Ray Film',
            'category' => 'Film',
            'stock_behavior' => 'consumable',
            'current_stock' => 20,
            'unit_cost' => 2.25,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Radiology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $film->id,
            'current_stock' => 20,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'RAD-BILL-1']);
        $billItem = $this->createPathologyBillItem($billingId, 103, 2, 'Radiology');
        ReagentTestUsage::create([
            'store_item_id' => $film->id,
            'test_id' => 103,
            'department_id' => $departmentId,
            'quantity_per_test' => 1,
        ]);

        app(ReagentConsumptionService::class)->consumeForBillItem($billItem);

        $this->assertSame('20.00', $film->fresh()->current_stock);
        $this->assertSame('18.00', $departmentStock->fresh()->current_stock);
        $this->assertDatabaseHas('department_reagent_movements', [
            'store_item_id' => $film->id,
            'department_id' => $departmentId,
            'movement_type' => 'consumption',
            'quantity' => 2,
        ]);
    }

    public function test_radiology_test_bill_consumes_each_configured_consumable_kit_and_film(): void
    {
        $kit = StoreItem::create([
            'item_name' => 'Radiology Contrast Kit',
            'category' => 'Kit',
            'stock_behavior' => 'consumable',
            'current_stock' => 15,
            'unit_cost' => 12,
            'status' => 'Active',
        ]);
        $film = StoreItem::create([
            'item_name' => 'X-Ray Film',
            'category' => 'Film',
            'stock_behavior' => 'consumable',
            'current_stock' => 25,
            'unit_cost' => 2.25,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Radiology']);
        $kitStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $kit->id,
            'current_stock' => 15,
        ]);
        $filmStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $film->id,
            'current_stock' => 25,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'RAD-BILL-2']);
        $billItem = $this->createPathologyBillItem($billingId, 105, 2, 'X-Ray Test');
        ReagentTestUsage::create([
            'store_item_id' => $kit->id,
            'test_id' => 105,
            'department_id' => $departmentId,
            'quantity_per_test' => 1,
        ]);
        ReagentTestUsage::create([
            'store_item_id' => $film->id,
            'test_id' => 105,
            'department_id' => $departmentId,
            'quantity_per_test' => 2,
        ]);

        app(ReagentConsumptionService::class)->consumeForBillItem($billItem);

        $this->assertSame('13.00', $kitStock->fresh()->current_stock);
        $this->assertSame('23.00', $filmStock->fresh()->current_stock);
        $this->assertSame('15.00', $kit->fresh()->current_stock);
        $this->assertSame('25.00', $film->fresh()->current_stock);
        $this->assertSame(2, ReagentBillConsumption::where('bill_item_id', $billItem->id)->count());
    }

    public function test_direct_store_inventory_bill_consumes_department_stock_only(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Diagnostic Tissue Pack',
            'category' => 'Diagnostic Supply',
            'stock_behavior' => 'consumable',
            'current_stock' => 30,
            'unit_cost' => 4,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $item->id,
            'current_stock' => 12,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'STORE-BILL-1']);
        $billItem = BillItem::create([
            'billing_id' => $billingId,
            'department_id' => $departmentId,
            'item_id' => $item->id,
            'item_name' => $item->item_name,
            'category' => 'Store Inventory',
            'quantity' => 3,
        ]);

        $service = app(ReagentConsumptionService::class);
        $service->consumeForBillItem($billItem);

        $this->assertSame('30.00', $item->fresh()->current_stock);
        $this->assertSame('9.00', $departmentStock->fresh()->current_stock);
        $this->assertDatabaseHas('department_reagent_movements', [
            'store_item_id' => $item->id,
            'department_id' => $departmentId,
            'movement_type' => 'consumption',
            'quantity' => 3,
        ]);

        $service->restoreForBilling($billingId);

        $this->assertSame('12.00', $departmentStock->fresh()->current_stock);
        $this->assertSame(1, ReagentBillConsumption::whereNotNull('reversed_at')->count());
    }

    public function test_film_size_is_consumed_only_for_its_configured_test(): void
    {
        $film = StoreItem::create([
            'item_name' => 'X-Ray Film',
            'category' => 'Film',
            'current_stock' => 20,
            'unit_cost' => 2,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Radiology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $film->id,
            'current_stock' => 10,
        ]);
        $testSpecificFilm = StoreItem::create([
            'item_name' => 'Test-specific Film',
            'category' => 'Film',
            'current_stock' => 20,
            'unit_cost' => 2,
            'status' => 'Active',
        ]);
        $testSpecificStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $testSpecificFilm->id,
            'current_stock' => 10,
        ]);
        $generalSupply = StoreItem::create([
            'item_name' => 'X-Ray Disposable Sheet',
            'category' => 'Diagnostic Supply',
            'current_stock' => 20,
            'unit_cost' => 2,
            'status' => 'Active',
        ]);
        $generalSupplyStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $generalSupply->id,
            'current_stock' => 10,
        ]);
        $xrayCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'X-RAY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $testId = DB::table('tests')->insertGetId([
            'category_type' => 'Radiology',
            'main_category_id' => $xrayCategoryId,
            'test_name' => 'Chest X-Ray',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ReagentCategoryUsage::create([
            'store_item_id' => $film->id,
            'category_key' => 'xray',
            'department_id' => $departmentId,
            'quantity_per_test' => 1,
        ]);
        ReagentCategoryUsage::create([
            'store_item_id' => $testSpecificFilm->id,
            'category_key' => 'xray',
            'department_id' => $departmentId,
            'quantity_per_test' => 5,
        ]);
        ReagentCategoryUsage::create([
            'store_item_id' => $generalSupply->id,
            'category_key' => 'xray',
            'department_id' => $departmentId,
            'quantity_per_test' => 2,
        ]);
        ReagentTestUsage::create([
            'store_item_id' => $testSpecificFilm->id,
            'test_id' => $testId,
            'department_id' => $departmentId,
            'quantity_per_test' => 0.5,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'XRAY-BILL-1']);
        $billItem = $this->createPathologyBillItem($billingId, $testId, 2);
        $billItem->update(['category' => 'Radiology']);

        app(ReagentConsumptionService::class)->consumeForBillItem($billItem);

        $this->assertSame('10.00', $departmentStock->fresh()->current_stock);
        $this->assertSame('8.00', $testSpecificStock->fresh()->current_stock);
        $this->assertSame('6.00', $generalSupplyStock->fresh()->current_stock);
        $this->assertDatabaseMissing('reagent_bill_consumptions', [
            'bill_item_id' => $billItem->id,
            'store_item_id' => $film->id,
            'department_id' => $departmentId,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('reagent_bill_consumptions', [
            'bill_item_id' => $billItem->id,
            'store_item_id' => $testSpecificFilm->id,
            'department_id' => $departmentId,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('reagent_bill_consumptions', [
            'bill_item_id' => $billItem->id,
            'store_item_id' => $generalSupply->id,
            'department_id' => $departmentId,
            'quantity' => 4,
        ]);

        app(ReagentConsumptionService::class)->restoreForBilling($billingId);

        $this->assertSame('10.00', $departmentStock->fresh()->current_stock);
        $this->assertSame('10.00', $testSpecificStock->fresh()->current_stock);
        $this->assertSame('10.00', $generalSupplyStock->fresh()->current_stock);
    }

    public function test_category_film_usage_is_used_when_test_has_no_size_override(): void
    {
        $film = StoreItem::create([
            'item_name' => '8x10 Film',
            'category' => 'Film',
            'current_stock' => 20,
            'unit_cost' => 2,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Radiology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $film->id,
            'current_stock' => 10,
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'XRAY-CATEGORY-BILL']);
        $testId = DB::table('tests')->insertGetId([
            'category_type' => 'Radiology',
            'test_name' => 'Chest X-Ray',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ReagentCategoryUsage::create([
            'store_item_id' => $film->id,
            'category_key' => 'radiology',
            'department_id' => $departmentId,
            'quantity_per_test' => 150,
        ]);
        $billItem = $this->createPathologyBillItem($billingId, $testId, 1, 'Radiology');

        app(ReagentConsumptionService::class)->consumeForBillItem($billItem);

        $this->assertSame('9.00', $departmentStock->fresh()->current_stock);
        $this->assertDatabaseHas('reagent_bill_consumptions', [
            'bill_item_id' => $billItem->id,
            'store_item_id' => $film->id,
            'quantity' => 1,
        ]);
    }

    public function test_legacy_central_bill_consumption_reversal_creates_a_traceable_lot(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Legacy Supply',
            'category' => 'Reagent',
            'current_stock' => 4,
            'unit_cost' => 6,
            'status' => 'Active',
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'LEGACY-BILL']);
        $billItem = $this->createPathologyBillItem($billingId, 104, 1);
        ReagentBillConsumption::create([
            'bill_item_id' => $billItem->id,
            'store_item_id' => $item->id,
            'quantity' => 2,
        ]);

        app(ReagentConsumptionService::class)->restoreForBilling($billingId);

        $this->assertSame('6.00', $item->fresh()->current_stock);
        $this->assertDatabaseHas('store_item_lots', [
            'store_item_id' => $item->id,
            'department_id' => null,
            'quantity' => 2,
            'batch_no' => 'LEGACY-REV-' . ReagentBillConsumption::first()->id,
        ]);
    }

    public function test_bill_consumes_nearest_expiry_lot_first_and_reverses_to_same_lots(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Diagnostic Reagent',
            'category' => 'Reagent',
            'current_stock' => 20,
            'unit_cost' => 5,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $item->id,
            'current_stock' => 7,
        ]);
        $firstExpiryLotId = DB::table('store_item_lots')->insertGetId([
            'store_item_id' => $item->id,
            'department_id' => $departmentId,
            'batch_no' => 'BATCH-EARLY',
            'expiry_date' => today()->addDays(10)->toDateString(),
            'quantity' => 2,
            'unit_cost' => 5,
            'received_date' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $laterExpiryLotId = DB::table('store_item_lots')->insertGetId([
            'store_item_id' => $item->id,
            'department_id' => $departmentId,
            'batch_no' => 'BATCH-LATE',
            'expiry_date' => today()->addDays(50)->toDateString(),
            'quantity' => 5,
            'unit_cost' => 5,
            'received_date' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'FEFO-BILL']);
        $billItem = BillItem::create([
            'billing_id' => $billingId,
            'department_id' => $departmentId,
            'item_id' => $item->id,
            'item_name' => $item->item_name,
            'category' => 'Store Inventory',
            'quantity' => 4,
        ]);

        $service = app(ReagentConsumptionService::class);
        $service->consumeForBillItem($billItem);

        $this->assertEquals(0, DB::table('store_item_lots')->where('id', $firstExpiryLotId)->value('quantity'));
        $this->assertEquals(3, DB::table('store_item_lots')->where('id', $laterExpiryLotId)->value('quantity'));
        $this->assertSame(2, ReagentBillConsumption::where('bill_item_id', $billItem->id)->count());

        $service->restoreForBilling($billingId);

        $this->assertEquals(2, DB::table('store_item_lots')->where('id', $firstExpiryLotId)->value('quantity'));
        $this->assertEquals(5, DB::table('store_item_lots')->where('id', $laterExpiryLotId)->value('quantity'));
        $this->assertSame('7.00', $departmentStock->fresh()->current_stock);
    }

    public function test_inventory_consumption_reads_stock_lots_once_and_skips_expired_lots(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Lot Query Reagent',
            'category' => 'Reagent',
            'current_stock' => 8,
            'unit_cost' => 5,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        $expiredLotId = DB::table('store_item_lots')->insertGetId([
            'store_item_id' => $item->id,
            'department_id' => $departmentId,
            'batch_no' => 'EXPIRED',
            'expiry_date' => today()->subDay()->toDateString(),
            'quantity' => 5,
            'unit_cost' => 5,
            'received_date' => today()->subDays(30)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $validLotId = DB::table('store_item_lots')->insertGetId([
            'store_item_id' => $item->id,
            'department_id' => $departmentId,
            'batch_no' => 'VALID',
            'expiry_date' => today()->addDays(10)->toDateString(),
            'quantity' => 3,
            'unit_cost' => 5,
            'received_date' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::enableQueryLog();
        try {
            DB::transaction(fn () => app(InventoryLotService::class)->consumeFromLocation(
                $item,
                $departmentId,
                2,
                8,
                true
            ));
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $lotSelects = array_filter($queries, fn ($query) =>
            str_starts_with(strtolower(ltrim($query['query'])), 'select')
            && str_contains(strtolower($query['query']), 'store_item_lots')
        );

        $this->assertCount(1, $lotSelects);
        $this->assertSame(5.0, (float) DB::table('store_item_lots')->where('id', $expiredLotId)->value('quantity'));
        $this->assertSame(1.0, (float) DB::table('store_item_lots')->where('id', $validLotId)->value('quantity'));
    }

    public function test_expired_department_lot_cannot_be_billed(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Expired Diagnostic Reagent',
            'category' => 'Reagent',
            'current_stock' => 10,
            'unit_cost' => 5,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        $departmentStock = DepartmentReagentStock::create([
            'department_id' => $departmentId,
            'store_item_id' => $item->id,
            'current_stock' => 3,
        ]);
        DB::table('store_item_lots')->insert([
            'store_item_id' => $item->id,
            'department_id' => $departmentId,
            'batch_no' => 'EXPIRED-BATCH',
            'expiry_date' => today()->subDay()->toDateString(),
            'quantity' => 3,
            'unit_cost' => 5,
            'received_date' => today()->subDays(30)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $billingId = DB::table('billings')->insertGetId(['bill_number' => 'EXPIRED-BILL']);
        $billItem = BillItem::create([
            'billing_id' => $billingId,
            'department_id' => $departmentId,
            'item_id' => $item->id,
            'item_name' => $item->item_name,
            'category' => 'Store Inventory',
            'quantity' => 1,
        ]);

        try {
            app(ReagentConsumptionService::class)->consumeForBillItem($billItem);
            $this->fail('Expected expired lot to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Insufficient unexpired stock', $exception->errors()['store_stock'][0]);
        }

        $this->assertSame('3.00', $departmentStock->fresh()->current_stock);
        $this->assertSame(0, DB::table('department_reagent_movements')->count());
    }

    public function test_indent_transfer_preserves_batches_and_uses_fefo(): void
    {
        $item = StoreItem::create([
            'item_name' => 'Buffered Reagent',
            'category' => 'Reagent',
            'current_stock' => 6,
            'unit_cost' => 8,
            'status' => 'Active',
        ]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Pathology']);
        $earlyLotId = DB::table('store_item_lots')->insertGetId([
            'store_item_id' => $item->id,
            'batch_no' => 'CENTRAL-EARLY',
            'expiry_date' => today()->addDays(20)->toDateString(),
            'quantity' => 2,
            'unit_cost' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $laterLotId = DB::table('store_item_lots')->insertGetId([
            'store_item_id' => $item->id,
            'batch_no' => 'CENTRAL-LATE',
            'expiry_date' => today()->addDays(90)->toDateString(),
            'quantity' => 4,
            'unit_cost' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DepartmentInventoryTransferService::class)->issueFromCentral(
            $item->id,
            $departmentId,
            4,
            'SRQ-FEFO',
            null
        );

        $this->assertEquals(0, DB::table('store_item_lots')->where('id', $earlyLotId)->value('quantity'));
        $this->assertEquals(2, DB::table('store_item_lots')->where('id', $laterLotId)->value('quantity'));
        $this->assertSame(2, DB::table('store_item_lots')->where('store_item_id', $item->id)->where('department_id', $departmentId)->count());
        $this->assertEquals(2, DB::table('store_item_lots')->where('department_id', $departmentId)->where('batch_no', 'CENTRAL-EARLY')->value('quantity'));
        $this->assertEquals(2, DB::table('store_item_lots')->where('department_id', $departmentId)->where('batch_no', 'CENTRAL-LATE')->value('quantity'));
    }

    private function createPathologyBillItem(int $billingId, int $testId, float $quantity, string $category = 'Pathology'): BillItem
    {
        return BillItem::create([
            'billing_id' => $billingId,
            'item_id' => $testId,
            'item_name' => 'CBC',
            'category' => $category,
            'quantity' => $quantity,
        ]);
    }
}