<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('department_reagent_stocks')) {
            Schema::create('department_reagent_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->foreignId('store_item_id')->constrained('store_items')->cascadeOnDelete();
                $table->decimal('current_stock', 14, 2)->default(0);
                $table->decimal('reorder_level', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['department_id', 'store_item_id']);
            });
        }

        if (!Schema::hasTable('department_reagent_movements')) {
            Schema::create('department_reagent_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_reagent_stock_id')->constrained('department_reagent_stocks')->cascadeOnDelete();
                $table->unsignedBigInteger('department_id')->index();
                $table->unsignedBigInteger('store_item_id')->index();
                $table->string('movement_type', 30);
                $table->decimal('quantity', 14, 2);
                $table->decimal('unit_cost', 14, 2)->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->text('reason')->nullable();
                $table->date('movement_date');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['department_id', 'store_item_id', 'movement_date'], 'dept_reagent_movement_lookup');
            });
        }

        if (Schema::hasTable('store_requisitions') && !Schema::hasColumn('store_requisitions', 'department_id')) {
            Schema::table('store_requisitions', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            });
        }

        if (Schema::hasTable('reagent_test_usages') && !Schema::hasColumn('reagent_test_usages', 'department_id')) {
            Schema::table('reagent_test_usages', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            });
        }

        if (Schema::hasTable('reagent_bill_consumptions')) {
            Schema::table('reagent_bill_consumptions', function (Blueprint $table) {
                if (!Schema::hasColumn('reagent_bill_consumptions', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->index();
                }
                if (!Schema::hasColumn('reagent_bill_consumptions', 'department_movement_id')) {
                    $table->unsignedBigInteger('department_movement_id')->nullable();
                }
            });
        }

        $this->backfillRequisitionDepartments();
        $this->backfillApprovedReagentTransfers();
    }

    public function down(): void
    {
        Schema::dropIfExists('department_reagent_movements');
        Schema::dropIfExists('department_reagent_stocks');

        if (Schema::hasTable('reagent_bill_consumptions')) {
            Schema::table('reagent_bill_consumptions', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('reagent_bill_consumptions', 'department_id')) {
                    $columns[] = 'department_id';
                }
                if (Schema::hasColumn('reagent_bill_consumptions', 'department_movement_id')) {
                    $columns[] = 'department_movement_id';
                }
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        foreach (['store_requisitions', 'reagent_test_usages'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'department_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('department_id');
                });
            }
        }
    }

    private function backfillRequisitionDepartments(): void
    {
        if (!Schema::hasTable('store_requisitions') || !Schema::hasTable('departments')) {
            return;
        }

        $departments = DB::table('departments')->whereNull('deleted_at')->get(['id', 'name']);
        foreach (DB::table('store_requisitions')->whereNull('department_id')->get(['id', 'department']) as $requisition) {
            $department = $departments->first(function ($candidate) use ($requisition) {
                return mb_strtolower(trim((string) $candidate->name)) === mb_strtolower(trim((string) $requisition->department));
            });

            if ($department) {
                DB::table('store_requisitions')->where('id', $requisition->id)->update(['department_id' => $department->id]);
            }
        }
    }

    private function backfillApprovedReagentTransfers(): void
    {
        if (!Schema::hasTable('store_requisitions') || !Schema::hasTable('store_requisition_items') || !Schema::hasTable('store_items')) {
            return;
        }

        $transfers = DB::table('store_requisitions as requisitions')
            ->join('store_requisition_items as items', 'items.store_requisition_id', '=', 'requisitions.id')
            ->join('store_items as store_items', 'store_items.id', '=', 'items.store_item_id')
            ->where('requisitions.status', 'Approved')
            ->whereNotNull('requisitions.department_id')
            ->whereRaw('LOWER(store_items.category) = ?', ['reagent'])
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
                'reason' => 'Opening balance from previously approved reagent indent',
                'movement_date' => now()->toDateString(),
                'created_by' => $transfer->approved_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};