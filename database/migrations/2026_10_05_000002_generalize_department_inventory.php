<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_items') && !Schema::hasColumn('store_items', 'stock_behavior')) {
            Schema::table('store_items', function (Blueprint $table) {
                $table->enum('stock_behavior', ['consumable', 'reusable'])->default('consumable')->after('category');
            });
        }

        if (Schema::hasTable('store_items')) {
            DB::table('store_items')
                ->where(function ($query) {
                    foreach (['device', 'equipment', 'instrument', 'machine', 'appliance'] as $keyword) {
                        $query->orWhereRaw('LOWER(category) LIKE ?', ['%' . $keyword . '%']);
                    }
                })
                ->update(['stock_behavior' => 'reusable']);
        }

        $this->backfillApprovedNonReagentTransfers();
    }

    public function down(): void
    {
        if (Schema::hasTable('store_items') && Schema::hasColumn('store_items', 'stock_behavior')) {
            Schema::table('store_items', function (Blueprint $table) {
                $table->dropColumn('stock_behavior');
            });
        }
    }

    private function backfillApprovedNonReagentTransfers(): void
    {
        if (!Schema::hasTable('store_requisitions')
            || !Schema::hasTable('store_requisition_items')
            || !Schema::hasTable('store_items')
            || !Schema::hasTable('department_reagent_stocks')
            || !Schema::hasTable('department_reagent_movements')) {
            return;
        }

        $transfers = DB::table('store_requisitions as requisitions')
            ->join('store_requisition_items as items', 'items.store_requisition_id', '=', 'requisitions.id')
            ->join('store_items as store_items', 'store_items.id', '=', 'items.store_item_id')
            ->where('requisitions.status', 'Approved')
            ->whereNotNull('requisitions.department_id')
            ->where(function ($query) {
                $query->whereNull('store_items.category')
                    ->orWhereRaw('LOWER(store_items.category) != ?', ['reagent']);
            })
            ->where('items.issued_qty', '>', 0)
            ->select([
                'requisitions.department_id',
                'requisitions.requisition_no',
                'requisitions.approved_by',
                'items.id as requisition_item_id',
                'items.store_item_id',
                'items.issued_qty',
                'store_items.unit_cost',
            ])
            ->get();

        foreach ($transfers as $transfer) {
            $stock = DB::table('department_reagent_stocks')
                ->where('department_id', $transfer->department_id)
                ->where('store_item_id', $transfer->store_item_id)
                ->first();

            if ($stock) {
                DB::table('department_reagent_stocks')->where('id', $stock->id)->increment('current_stock', $transfer->issued_qty);
            } else {
                $stockId = DB::table('department_reagent_stocks')->insertGetId([
                    'department_id' => $transfer->department_id,
                    'store_item_id' => $transfer->store_item_id,
                    'current_stock' => $transfer->issued_qty,
                    'reorder_level' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stock = (object) ['id' => $stockId];
            }

            DB::table('department_reagent_movements')->insert([
                'department_reagent_stock_id' => $stock->id,
                'department_id' => $transfer->department_id,
                'store_item_id' => $transfer->store_item_id,
                'movement_type' => 'transfer_in',
                'quantity' => $transfer->issued_qty,
                'unit_cost' => $transfer->unit_cost,
                'reference_no' => 'MIG-' . $transfer->requisition_no . '-' . $transfer->requisition_item_id,
                'reason' => 'Opening balance from previously approved department indent',
                'movement_date' => now()->toDateString(),
                'created_by' => $transfer->approved_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};