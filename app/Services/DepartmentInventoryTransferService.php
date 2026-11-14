<?php

namespace App\Services;

use App\Models\DepartmentReagentMovement;
use App\Models\DepartmentReagentStock;
use App\Models\StoreItem;
use App\Models\StoreStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentInventoryTransferService
{
    public function issueFromCentral(int $storeItemId, int $departmentId, float $quantity, string $reference, ?int $createdBy): void
    {
        DB::transaction(function () use ($storeItemId, $departmentId, $quantity, $reference, $createdBy) {
            $storeItem = StoreItem::query()->lockForUpdate()->findOrFail($storeItemId);
            if ($quantity <= 0 || (float) $storeItem->current_stock < $quantity) {
                throw ValidationException::withMessages(['items' => sprintf(
                    'Insufficient central stock for "%s". Required: %s, available: %s.',
                    $storeItem->item_name,
                    number_format($quantity, 2, '.', ''),
                    number_format((float) $storeItem->current_stock, 2, '.', '')
                )]);
            }

            $allocations = app(InventoryLotService::class)->consumeFromLocation(
                $storeItem,
                null,
                $quantity,
                (float) $storeItem->current_stock,
                true
            );
            $storeItem->decrement('current_stock', $quantity);

            $stock = DepartmentReagentStock::query()->firstOrCreate(
                ['department_id' => $departmentId, 'store_item_id' => $storeItem->id],
                ['current_stock' => 0, 'reorder_level' => $storeItem->reorder_level ?? 0]
            );
            $stock = DepartmentReagentStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $stock->increment('current_stock', $quantity);

            foreach ($allocations as $allocation) {
                $sourceLot = $allocation['lot'];
                $transferQty = (float) $allocation['quantity'];
                $departmentLot = app(InventoryLotService::class)->receive(
                    $storeItem->id,
                    $departmentId,
                    $transferQty,
                    (float) $sourceLot->unit_cost,
                    $sourceLot->batch_no,
                    $sourceLot->expiry_date?->toDateString(),
                    $sourceLot->serial_number,
                    $sourceLot->asset_tag,
                    $sourceLot->received_date?->toDateString()
                );

                StoreStockMovement::create([
                    'store_item_id' => $storeItem->id,
                    'inventory_lot_id' => $sourceLot->id,
                    'movement_type' => 'decrease',
                    'quantity' => $transferQty,
                    'unit_price' => $sourceLot->unit_cost,
                    'reason' => 'Transferred to department on indent',
                    'movement_date' => now()->toDateString(),
                    'reference_no' => $reference,
                    'department' => DB::table('departments')->where('id', $departmentId)->value('name'),
                    'created_by' => $createdBy,
                ]);

                DepartmentReagentMovement::create([
                    'department_reagent_stock_id' => $stock->id,
                    'department_id' => $departmentId,
                    'store_item_id' => $storeItem->id,
                    'inventory_lot_id' => $departmentLot->id,
                    'movement_type' => 'transfer_in',
                    'quantity' => $transferQty,
                    'unit_cost' => $sourceLot->unit_cost,
                    'reference_no' => $reference,
                    'reason' => 'Issued from central store against department indent',
                    'movement_date' => now()->toDateString(),
                    'created_by' => $createdBy,
                ]);
            }
        });
    }

    public function recordDepartmentUse(int $departmentStockId, float $quantity, string $reason, ?string $reference, ?int $createdBy): void
    {
        DB::transaction(function () use ($departmentStockId, $quantity, $reason, $reference, $createdBy) {
            $stock = DepartmentReagentStock::query()->lockForUpdate()->findOrFail($departmentStockId);
            $item = StoreItem::query()->lockForUpdate()->findOrFail($stock->store_item_id);
            $available = (float) $stock->current_stock;

            if (($item->stock_behavior ?? 'consumable') !== 'consumable') {
                throw ValidationException::withMessages([
                    'quantity' => 'Reusable assets must be returned or transferred, not recorded as consumed.',
                ]);
            }

            if ($quantity <= 0 || $available + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Usage quantity cannot exceed department stock for "%s". Available: %s %s.',
                        $item->item_name,
                        number_format($available, 2, '.', ''),
                        $item->unit
                    ),
                ]);
            }

            $allocations = app(InventoryLotService::class)->consumeFromLocation(
                $item,
                (int) $stock->department_id,
                $quantity,
                $available,
                true
            );
            $stock->decrement('current_stock', $quantity);

            foreach ($allocations as $allocation) {
                DepartmentReagentMovement::query()->create([
                    'department_reagent_stock_id' => $stock->id,
                    'department_id' => $stock->department_id,
                    'store_item_id' => $item->id,
                    'inventory_lot_id' => $allocation['lot']->id,
                    'movement_type' => 'department_use',
                    'quantity' => $allocation['quantity'],
                    'unit_cost' => $allocation['lot']->unit_cost,
                    'reference_no' => $reference,
                    'reason' => $reason,
                    'movement_date' => now()->toDateString(),
                    'created_by' => $createdBy,
                ]);
            }
        });
    }
}