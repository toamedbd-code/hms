<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('store_item_lots')) {
            Schema::create('store_item_lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_item_id')->constrained('store_items')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('batch_no', 100)->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('serial_number', 120)->nullable();
                $table->string('asset_tag', 120)->nullable();
                $table->decimal('quantity', 14, 2)->default(0);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->date('received_date')->nullable();
                $table->timestamps();
                $table->index(['store_item_id', 'department_id', 'expiry_date'], 'store_item_lot_stock_lookup');
            });
        }

        $this->addNullableLotReference('store_grn_items');
        $this->addNullableLotReference('store_stock_movements');
        $this->addNullableLotReference('department_reagent_movements');
        $this->addNullableLotReference('reagent_bill_consumptions');

        $this->backfillAggregateLots();
    }

    public function down(): void
    {
        foreach (['store_grn_items', 'store_stock_movements', 'department_reagent_movements', 'reagent_bill_consumptions'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $columns = [];
                    foreach (['inventory_lot_id', 'batch_no', 'expiry_date', 'serial_number', 'asset_tag'] as $column) {
                        if (Schema::hasColumn($tableName, $column)) {
                            $columns[] = $column;
                        }
                    }
                    if ($columns) {
                        $table->dropColumn($columns);
                    }
                });
            }
        }

        Schema::dropIfExists('store_item_lots');
    }

    private function addNullableLotReference(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'inventory_lot_id')) {
                $table->unsignedBigInteger('inventory_lot_id')->nullable()->index();
            }
            foreach (['batch_no' => 100, 'serial_number' => 120, 'asset_tag' => 120] as $column => $length) {
                if ($tableName === 'store_grn_items' && !Schema::hasColumn($tableName, $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
            if ($tableName === 'store_grn_items' && !Schema::hasColumn($tableName, 'expiry_date')) {
                $table->date('expiry_date')->nullable();
            }
        });
    }

    private function backfillAggregateLots(): void
    {
        if (Schema::hasTable('store_items')) {
            foreach (DB::table('store_items')->where('current_stock', '>', 0)->get(['id', 'current_stock', 'unit_cost', 'created_at']) as $item) {
                $exists = DB::table('store_item_lots')
                    ->where('store_item_id', $item->id)
                    ->whereNull('department_id')
                    ->exists();
                if (!$exists) {
                    DB::table('store_item_lots')->insert([
                        'store_item_id' => $item->id,
                        'batch_no' => 'LEGACY-CENTRAL-' . $item->id,
                        'quantity' => $item->current_stock,
                        'unit_cost' => $item->unit_cost,
                        'received_date' => $item->created_at ? substr($item->created_at, 0, 10) : now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('department_reagent_stocks')) {
            foreach (DB::table('department_reagent_stocks')->where('current_stock', '>', 0)->get(['id', 'department_id', 'store_item_id', 'current_stock', 'created_at']) as $stock) {
                $exists = DB::table('store_item_lots')
                    ->where('store_item_id', $stock->store_item_id)
                    ->where('department_id', $stock->department_id)
                    ->exists();
                if (!$exists) {
                    DB::table('store_item_lots')->insert([
                        'store_item_id' => $stock->store_item_id,
                        'department_id' => $stock->department_id,
                        'batch_no' => 'LEGACY-DEPT-' . $stock->id,
                        'quantity' => $stock->current_stock,
                        'unit_cost' => DB::table('store_items')->where('id', $stock->store_item_id)->value('unit_cost') ?? 0,
                        'received_date' => $stock->created_at ? substr($stock->created_at, 0, 10) : now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};