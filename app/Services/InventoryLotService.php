<?php

namespace App\Services;

use App\Models\StoreItem;
use App\Models\StoreItemLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLotService
{
    public function receive(
        int $storeItemId,
        ?int $departmentId,
        float $quantity,
        float $unitCost,
        ?string $batchNo,
        ?string $expiryDate,
        ?string $serialNumber,
        ?string $assetTag,
        ?string $receivedDate = null
    ): StoreItemLot {
        return StoreItemLot::create([
            'store_item_id' => $storeItemId,
            'department_id' => $departmentId,
            'batch_no' => $batchNo,
            'expiry_date' => $expiryDate,
            'serial_number' => $serialNumber,
            'asset_tag' => $assetTag,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => $receivedDate ?? now()->toDateString(),
        ]);
    }

    public function consumeFromLocation(
        StoreItem $item,
        ?int $departmentId,
        float $quantity,
        float $aggregateBalance,
        bool $excludeExpired = true
    ): array {
        $allLots = StoreItemLot::query()
            ->where('store_item_id', $item->id)
            ->where('department_id', $departmentId)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($allLots->isEmpty() && $aggregateBalance > 0) {
            $allLots = collect([$this->receive(
                $item->id,
                $departmentId,
                $aggregateBalance,
                (float) $item->unit_cost,
                'LEGACY-' . ($departmentId ? 'DEPT-' . $departmentId : 'STORE') . '-' . $item->id,
                null,
                null,
                null
            )]);
        }

        $today = today()->toDateString();
        $lots = $allLots->filter(function (StoreItemLot $lot) use ($excludeExpired, $today) {
            return (float) $lot->quantity > 0
                && (!$excludeExpired || !$lot->expiry_date || $lot->expiry_date->toDateString() >= $today);
        });

        $available = (float) $lots->sum('quantity');
        if ($available + 0.0001 < $quantity) {
            throw ValidationException::withMessages(['store_stock' => sprintf(
                'Insufficient unexpired stock for "%s". Required: %s, available: %s.',
                $item->item_name,
                number_format($quantity, 2, '.', ''),
                number_format($available, 2, '.', '')
            )]);
        }

        $remaining = $quantity;
        $allocations = [];
        foreach ($lots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }

            $used = min((float) $lot->quantity, $remaining);
            $lot->decrement('quantity', $used);
            $allocations[] = ['lot' => $lot, 'quantity' => $used];
            $remaining = round($remaining - $used, 4);
        }

        return $allocations;
    }
}