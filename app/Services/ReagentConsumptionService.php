<?php

namespace App\Services;

use App\Models\BillItem;
use App\Models\DepartmentReagentMovement;
use App\Models\DepartmentReagentStock;
use App\Models\ReagentCategoryUsage;
use App\Models\ReagentBillConsumption;
use App\Models\ReagentTestUsage;
use App\Models\StoreItem;
use App\Models\StoreItemLot;
use App\Models\StoreStockMovement;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReagentConsumptionService
{
    private array $preparedTests = [];
    private array $preparedTestUsages = [];
    private array $preparedCategoryUsages = [];
    private bool $billingItemsPrepared = false;

    public function prepareForBillItems(array $items): void
    {
        $billableItems = collect($items)->filter(function ($item) {
            $category = strtolower(trim((string) ($item['category'] ?? '')));
            return $category !== 'store inventory'
                && in_array(self::normalizeCategoryKey($category), [
                    'pathology', 'radiology', 'imaging', 'ecg', 'ultrasound', 'xray',
                ], true);
        });

        $testIds = $billableItems->pluck('id')->filter()->unique()->values();
        $this->preparedTests = Test::query()
            ->with(['mainCategory:id,name', 'pathologyCategory:id,name'])
            ->whereKey($testIds)
            ->get()
            ->keyBy(fn (Test $test) => (int) $test->getKey())
            ->all();

        $testUsages = ReagentTestUsage::query()
            ->with([
                'storeItem:id,item_name,category,stock_behavior,status,unit_cost',
                'department:id,name',
            ])
            ->whereIn('test_id', $testIds)
            ->orderBy('store_item_id')
            ->get();
        $this->preparedTestUsages = $testUsages
            ->groupBy('test_id')
            ->all();

        $categoryKeys = [];
        foreach ($billableItems as $item) {
            $test = $this->preparedTests[(int) ($item['id'] ?? 0)] ?? null;
            if ($test) {
                $categoryKeys = array_merge($categoryKeys, $this->categoryKeysForTest($test));
            }
            $categoryKeys[] = self::normalizeCategoryKey((string) ($item['category'] ?? ''));
        }

        $this->preparedCategoryUsages = ReagentCategoryUsage::query()
            ->with([
                'storeItem:id,item_name,category,stock_behavior,status,unit_cost',
                'department:id,name',
            ])
            ->whereIn('category_key', array_values(array_unique(array_filter($categoryKeys))))
            ->orderBy('store_item_id')
            ->get()
            ->groupBy('category_key')
            ->all();
        $this->billingItemsPrepared = true;
    }

    public function consumeForBillItem(BillItem $billItem): void
    {
        $category = strtolower(trim((string) $billItem->category));
        if ($category === 'store inventory') {
            $this->consumeDirectStoreItem($billItem);
            return;
        }

        if (!in_array(self::normalizeCategoryKey($category), [
            'pathology', 'radiology', 'imaging', 'ecg', 'ultrasound', 'xray',
        ], true)) {
            return;
        }

        $consume = function () use ($billItem) {
            $testId = (int) $billItem->item_id;
            $usages = $this->billingItemsPrepared
                ? ($this->preparedTestUsages[$testId] ?? collect())
                : ReagentTestUsage::query()
                    ->with(['storeItem:id,item_name,category,stock_behavior,status,unit_cost', 'department:id,name'])
                    ->where('test_id', $testId)
                    ->orderBy('store_item_id')
                    ->get();

            $categoryKeys = $this->categoryKeysForBillItem($billItem);
            $hasTestSpecificFilmUsage = $usages->contains(
                fn ($usage) => $usage->storeItem && self::isFilmItem($usage->storeItem)
            );
            $categoryUsages = $this->billingItemsPrepared
                ? collect($categoryKeys)->flatMap(
                    fn ($categoryKey) => $this->preparedCategoryUsages[$categoryKey] ?? collect()
                )
                : ReagentCategoryUsage::query()
                    ->with(['storeItem:id,item_name,category,stock_behavior,status,unit_cost', 'department:id,name'])
                    ->whereIn('category_key', $categoryKeys)
                    ->orderBy('store_item_id')
                    ->get();
            $configuredItemIds = $usages->pluck('store_item_id')->map(fn ($id) => (int) $id)->all();
            $categoryUsagesByItem = [];

            foreach ($categoryKeys as $categoryKey) {
                foreach ($categoryUsages->where('category_key', $categoryKey) as $categoryUsage) {
                    $storeItemId = (int) $categoryUsage->store_item_id;
                    if (
                        $categoryUsage->storeItem
                        && (!$hasTestSpecificFilmUsage || !self::isFilmItem($categoryUsage->storeItem))
                        && !in_array($storeItemId, $configuredItemIds, true)
                        && !isset($categoryUsagesByItem[$storeItemId])
                    ) {
                        $categoryUsagesByItem[$storeItemId] = $categoryUsage;
                    }
                }
            }

            $usages = $usages->concat(array_values($categoryUsagesByItem));

            foreach ($usages as $usage) {
                $storeItem = $usage->storeItem ?? StoreItem::query()->findOrFail($usage->store_item_id);
                $quantityPerTest = self::isFilmItem($storeItem)
                    ? 1
                    : (float) $usage->quantity_per_test;
                $quantity = round((float) $billItem->quantity * $quantityPerTest, 2);
                if ($quantity <= 0) {
                    continue;
                }

                if (!$usage->department_id) {
                    throw ValidationException::withMessages([
                        'reagent_stock' => 'Configure a department for reagent usage before billing test: ' . $billItem->item_name,
                    ]);
                }

                if (($storeItem->stock_behavior ?? 'consumable') === 'reusable') {
                    continue;
                }
                if ($storeItem->status !== 'Active') {
                    throw ValidationException::withMessages([
                        'reagent_stock' => 'The configured store item "' . $storeItem->item_name . '" is inactive and cannot be consumed for billing.',
                    ]);
                }

                $departmentStock = DepartmentReagentStock::query()
                    ->where('store_item_id', $storeItem->id)
                    ->where('department_id', $usage->department_id)
                    ->lockForUpdate()
                    ->first();

                if (!$departmentStock || (float) $departmentStock->current_stock < $quantity) {
                    throw ValidationException::withMessages(['reagent_stock' => sprintf(
                        'Insufficient department stock in %s for "%s". Required: %s, available: %s.',
                        $usage->department?->name ?? 'selected department',
                        $storeItem->item_name,
                        number_format($quantity, 2, '.', ''),
                        number_format((float) ($departmentStock->current_stock ?? 0), 2, '.', '')
                    )]);
                }

                $this->consumeDepartmentLots($billItem, $storeItem, $departmentStock, $usage->department_id, $quantity, 'Used for bill item: ' . $billItem->item_name);
            }
        };

        if (DB::transactionLevel() > 0) {
            $consume();
            return;
        }

        DB::transaction($consume);
    }

    public static function normalizeCategoryKey(string $category): string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower(trim($category)));
        $key = preg_replace('/test$/', '', $key);

        return match ($key) {
            'ekg' => 'ecg',
            'usg', 'ultrasonogram', 'ultrasonography' => 'ultrasound',
            default => $key,
        };
    }

    public static function isFilmItem(StoreItem $item): bool
    {
        return str_contains(strtolower((string) $item->category), 'film')
            || str_contains(strtolower((string) $item->item_name), 'film');
    }

    public function categoryKeysForTest(Test $test): array
    {
        $keys = [];
        foreach ([$test->mainCategory?->name, $test->pathologyCategory?->name, $test->category_type] as $category) {
            if ($category) {
                $keys[] = self::normalizeCategoryKey((string) $category);
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private function categoryKeysForBillItem(BillItem $billItem): array
    {
        $test = $this->billingItemsPrepared
            ? ($this->preparedTests[(int) $billItem->item_id] ?? null)
            : Test::query()
                ->with(['mainCategory:id,name', 'pathologyCategory:id,name'])
                ->find($billItem->item_id);
        $keys = $test ? $this->categoryKeysForTest($test) : [];
        $keys[] = self::normalizeCategoryKey((string) $billItem->category);

        return array_values(array_unique(array_filter($keys)));
    }

    private function consumeDirectStoreItem(BillItem $billItem): void
    {
        DB::transaction(function () use ($billItem) {
            $storeItem = StoreItem::query()->lockForUpdate()->findOrFail($billItem->item_id);
            if (($storeItem->stock_behavior ?? 'consumable') === 'reusable') {
                return;
            }

            if (!$billItem->department_id) {
                throw ValidationException::withMessages([
                    'store_stock' => 'The billing user must belong to a department before billing department inventory.',
                ]);
            }

            if ($storeItem->status !== 'Active') {
                throw ValidationException::withMessages([
                    'store_stock' => 'This store item is inactive and cannot be billed.',
                ]);
            }

            $departmentStock = DepartmentReagentStock::query()
                ->where('store_item_id', $storeItem->id)
                ->where('department_id', $billItem->department_id)
                ->lockForUpdate()
                ->first();
            $quantity = round((float) $billItem->quantity, 2);

            if (!$departmentStock || (float) $departmentStock->current_stock < $quantity) {
                throw ValidationException::withMessages(['store_stock' => sprintf(
                    'Insufficient department stock for "%s". Required: %s, available: %s.',
                    $storeItem->item_name,
                    number_format($quantity, 2, '.', ''),
                    number_format((float) ($departmentStock->current_stock ?? 0), 2, '.', '')
                )]);
            }

            $this->consumeDepartmentLots($billItem, $storeItem, $departmentStock, $billItem->department_id, $quantity, 'Billed directly: ' . $billItem->item_name);
        });
    }

    private function consumeDepartmentLots(BillItem $billItem, StoreItem $storeItem, DepartmentReagentStock $departmentStock, int $departmentId, float $quantity, string $reason): void
    {
        $allocations = app(InventoryLotService::class)->consumeFromLocation(
            $storeItem,
            $departmentId,
            $quantity,
            (float) $departmentStock->current_stock,
            true
        );

        $departmentStock->decrement('current_stock', $quantity);
        foreach ($allocations as $allocation) {
            $lot = $allocation['lot'];
            $used = (float) $allocation['quantity'];
            $movement = DepartmentReagentMovement::create([
                'department_reagent_stock_id' => $departmentStock->id,
                'department_id' => $departmentId,
                'store_item_id' => $storeItem->id,
                'inventory_lot_id' => $lot->id,
                'movement_type' => 'consumption',
                'quantity' => $used,
                'unit_cost' => $lot->unit_cost,
                'reason' => $reason,
                'movement_date' => now()->toDateString(),
                'reference_no' => 'BILL-' . $billItem->billing_id . '-ITEM-' . $billItem->id,
                'created_by' => auth('admin')->id(),
            ]);

            ReagentBillConsumption::create([
                'bill_item_id' => $billItem->id,
                'store_item_id' => $storeItem->id,
                'department_id' => $departmentId,
                'inventory_lot_id' => $lot->id,
                'department_movement_id' => $movement->id,
                'quantity' => $used,
            ]);
        }
    }

    public function restoreForBilling(int $billingId): void
    {
        DB::transaction(function () use ($billingId) {
            $billItemIds = BillItem::withTrashed()
                ->where('billing_id', $billingId)
                ->pluck('id');

            $consumptions = ReagentBillConsumption::query()
                ->whereIn('bill_item_id', $billItemIds)
                ->whereNull('reversed_at')
                ->orderBy('store_item_id')
                ->get();

            foreach ($consumptions as $consumption) {
                $storeItem = StoreItem::query()->findOrFail($consumption->store_item_id);

                if ($consumption->department_id) {
                    $departmentStock = DepartmentReagentStock::query()
                        ->where('department_id', $consumption->department_id)
                        ->where('store_item_id', $consumption->store_item_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $lot = $consumption->inventory_lot_id
                        ? StoreItemLot::query()->findOrFail($consumption->inventory_lot_id)
                        : app(InventoryLotService::class)->receive(
                            $storeItem->id,
                            $consumption->department_id,
                            (float) $consumption->quantity,
                            (float) $storeItem->unit_cost,
                            'LEGACY-REV-' . $consumption->id,
                            null,
                            null,
                            null
                        );
                    if ($consumption->inventory_lot_id) {
                        $lot->increment('quantity', $consumption->quantity);
                    }
                    $departmentStock->increment('current_stock', $consumption->quantity);
                    $movement = DepartmentReagentMovement::create([
                        'department_reagent_stock_id' => $departmentStock->id,
                        'department_id' => $consumption->department_id,
                        'store_item_id' => $storeItem->id,
                        'inventory_lot_id' => $lot->id,
                        'movement_type' => 'reversal',
                        'quantity' => $consumption->quantity,
                        'unit_cost' => $storeItem->unit_cost,
                        'reason' => 'Item use reversed during bill edit',
                        'movement_date' => now()->toDateString(),
                        'reference_no' => 'BILL-' . $billingId . '-REV-' . $consumption->id,
                        'created_by' => auth('admin')->id(),
                    ]);
                    $consumption->department_movement_id = $movement->id;
                } else {
                    $lot = $consumption->inventory_lot_id
                        ? StoreItemLot::query()->findOrFail($consumption->inventory_lot_id)
                        : app(InventoryLotService::class)->receive(
                            $storeItem->id,
                            null,
                            (float) $consumption->quantity,
                            (float) $storeItem->unit_cost,
                            'LEGACY-REV-' . $consumption->id,
                            null,
                            null,
                            null
                        );
                    if ($consumption->inventory_lot_id) {
                        $lot->increment('quantity', $consumption->quantity);
                    }
                    $storeItem->increment('current_stock', $consumption->quantity);
                    $movement = StoreStockMovement::create([
                        'store_item_id' => $storeItem->id,
                        'inventory_lot_id' => $lot->id,
                        'movement_type' => 'increase',
                        'quantity' => $consumption->quantity,
                        'unit_price' => $storeItem->unit_cost,
                        'reason' => 'Legacy item use reversed during bill edit',
                        'movement_date' => now()->toDateString(),
                        'reference_no' => 'BILL-' . $billingId . '-REV-' . $consumption->id,
                        'department' => 'Laboratory',
                        'created_by' => auth('admin')->id(),
                    ]);
                    $consumption->stock_movement_id = $movement->id;
                }

                $consumption->reversed_at = now();
                $consumption->save();
            }
        });
    }
}