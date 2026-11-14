<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MedicineInventory;
use App\Models\MedicinePurchase;
use App\Models\PharmacyBill;
use App\Models\ProductReturn;
use App\Models\PurchaseItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PharmacyProfitLossReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:stock-report-list');
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['daily', 'monthly', 'yearly', 'custom'])],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $period = $validated['period'] ?? 'daily';
        $today = Carbon::today();
        if ($period === 'monthly') {
            $start = Carbon::createFromFormat('Y-m', $validated['month'] ?? $today->format('Y-m'))->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } elseif ($period === 'yearly') {
            $year = (int) ($validated['year'] ?? $today->year);
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = $start->copy()->endOfYear();
        } elseif ($period === 'custom') {
            $start = Carbon::parse($validated['from'] ?? $today->toDateString())->startOfDay();
            $end = Carbon::parse($validated['to'] ?? $validated['from'] ?? $today->toDateString())->endOfDay();
        } else {
            $period = 'daily';
            $start = Carbon::parse($validated['date'] ?? $today->toDateString())->startOfDay();
            $end = $start->copy()->endOfDay();
        }

        $bills = PharmacyBill::query()
            ->where('status', 'Active')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'products', 'subtotal', 'discount_amount', 'extra_discount', 'net_amount']);
        $customerReturns = ProductReturn::query()
            ->with('returnItems:id,product_return_id,medicine_inventory_id,quantity,unit_price,total_amount,condition')
            ->where('return_type', 'customer')
            ->where('status', 'processed')
            ->whereBetween('return_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'return_date', 'total_amount', 'source_bill_no']);
        $returnBills = PharmacyBill::query()
            ->whereIn('bill_no', $customerReturns->pluck('source_bill_no')->filter()->unique())
            ->get(['bill_no', 'products'])
            ->keyBy('bill_no');

        $allSaleProducts = $bills->flatMap(function ($bill) {
            $products = is_array($bill->products) ? $bill->products : json_decode((string) $bill->products, true);
            return collect(is_array($products) ? $products : []);
        });
        $medicineIds = $allSaleProducts
            ->map(fn ($product) => $product['productId'] ?? $product['medicineId'] ?? $product['medicine_id'] ?? $product['id'] ?? null)
            ->merge($customerReturns->flatMap(fn ($return) => $return->returnItems->pluck('medicine_inventory_id')))
            ->filter()
            ->unique()
            ->values();

        $inventoryById = MedicineInventory::query()
            ->whereIn('id', $medicineIds)
            ->get(['id', 'medicine_name', 'medicine_unit_purchase_price'])
            ->keyBy('id');
        $purchaseCostByMedicine = $inventoryById->map(fn ($medicine) => $medicine->medicine_unit_purchase_price);
        $medicineNames = $allSaleProducts
            ->pluck('productName')
            ->merge($allSaleProducts->pluck('medicine_name'))
            ->merge($allSaleProducts->pluck('name'))
            ->merge($allSaleProducts->map(fn ($product) => $inventoryById->get($product['productId'] ?? $product['medicineId'] ?? $product['medicine_id'] ?? $product['id'] ?? null)?->medicine_name))
            ->merge($customerReturns->flatMap(fn ($return) => $return->returnItems->map(fn ($item) => $item->medicineInventory?->medicine_name)))
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->unique()
            ->values();
        $purchaseCostByName = PurchaseItem::query()
            ->join('medicine_purchases', 'medicine_purchases.id', '=', 'purchase_items.medicine_purchase_id')
            ->where('medicine_purchases.status', '!=', 'cancelled')
            ->where('purchase_items.received_quantity', '>', 0)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(purchase_items.medicine_name))'), $medicineNames)
            ->get([
                'purchase_items.medicine_name',
                'purchase_items.quantity',
                'purchase_items.received_quantity',
                'purchase_items.unit_purchase_price',
                'purchase_items.discount',
            ])
            ->groupBy(fn ($item) => mb_strtolower(trim((string) $item->medicine_name)))
            ->map(function ($items) {
                $received = (int) $items->sum('received_quantity');
                if ($received < 1) {
                    return 0.0;
                }
                $cost = $items->sum(function ($item) {
                    $ordered = max((int) $item->quantity, 1);
                    $receivedQty = (int) $item->received_quantity;
                    return max(
                        0,
                        $receivedQty * (float) $item->unit_purchase_price
                            - (float) $item->discount * ($receivedQty / $ordered)
                    );
                });

                return $cost / $received;
            });

        $salesByDate = [];
        $salesByMedicine = [];
        $estimatedCostLines = 0;
        foreach ($bills as $bill) {
            $products = is_array($bill->products) ? $bill->products : json_decode((string) $bill->products, true);
            $products = collect(is_array($products) ? $products : []);
            $subtotal = (float) ($bill->subtotal ?? 0);
            $netRevenue = max(0, $subtotal - (float) ($bill->discount_amount ?? 0) - (float) ($bill->extra_discount ?? 0));
            $costOfGoods = 0.0;
            $lineValues = $products->map(function ($product) use ($inventoryById) {
                $quantity = max(0, (float) ($product['quantity'] ?? 0));
                $lineValue = max(0, (float) ($product['amount'] ?? 0));
                if ($lineValue <= 0) {
                    $lineValue = max(0, (float) ($product['totalAmount'] ?? $product['total_amount'] ?? 0));
                }
                if ($lineValue <= 0) {
                    $unitPrice = max(0, (float) ($product['rate'] ?? $product['unit_price'] ?? $product['selling_price'] ?? $product['sale_price'] ?? 0));
                    if ($unitPrice <= 0) {
                        $medicineId = $product['productId'] ?? $product['medicineId'] ?? $product['medicine_id'] ?? $product['id'] ?? null;
                        $unitPrice = max(0, (float) ($inventoryById->get($medicineId)?->medicine_unit_selling_price ?? 0));
                    }
                    $lineValue = $unitPrice * $quantity;
                }

                return $lineValue;
            });
            $totalLineValue = $lineValues->sum();
            $totalQuantity = $products->sum(fn ($product) => max(0, (float) ($product['quantity'] ?? 0)));

            foreach ($products as $productIndex => $product) {
                $quantity = max(0, (float) ($product['quantity'] ?? 0));
                if ($quantity <= 0) {
                    continue;
                }

                $medicineId = $product['productId'] ?? $product['medicineId'] ?? $product['medicine_id'] ?? $product['id'] ?? null;
                $unitCost = isset($product['unit_purchase_price'])
                    ? max(0, (float) $product['unit_purchase_price'])
                    : 0;
                if ($unitCost <= 0) {
                    $unitCost = max(0, (float) ($purchaseCostByMedicine->get($medicineId) ?? 0));
                }
                if ($unitCost <= 0) {
                    $medicineName = $inventoryById->get($medicineId)?->medicine_name
                        ?? ($product['productName'] ?? $product['medicine_name'] ?? $product['name'] ?? '');
                    $unitCost = max(0, (float) ($purchaseCostByName->get(mb_strtolower(trim((string) $medicineName))) ?? 0));
                }
                if (!isset($product['unit_purchase_price']) || (float) $product['unit_purchase_price'] <= 0) {
                    $estimatedCostLines++;
                }
                $costOfGoods += $quantity * $unitCost;

                $medicineName = $inventoryById->get($medicineId)?->medicine_name
                    ?? ($product['productName'] ?? $product['medicine_name'] ?? $product['name'] ?? 'Unknown medicine');
                $medicineKey = $medicineId ? 'id:' . $medicineId : 'name:' . mb_strtolower(trim((string) $medicineName));
                $lineWeight = (float) ($lineValues[$productIndex] ?? 0);
                $lineRevenue = $totalLineValue > 0
                    ? $netRevenue * ($lineWeight / $totalLineValue)
                    : ($totalQuantity > 0 ? $netRevenue * ($quantity / $totalQuantity) : 0);
                $salesByMedicine[$medicineKey] ??= [
                    'medicine_id' => $medicineId,
                    'medicine_name' => (string) $medicineName,
                    'sold_qty' => 0,
                    'returned_qty' => 0,
                    'sold_revenue' => 0,
                    'sold_cost' => 0,
                ];
                $salesByMedicine[$medicineKey]['sold_qty'] += $quantity;
                $salesByMedicine[$medicineKey]['sold_revenue'] += $lineRevenue;
                $salesByMedicine[$medicineKey]['sold_cost'] += $quantity * $unitCost;
            }

            $date = Carbon::parse($bill->date)->toDateString();
            $salesByDate[$date] ??= ['sold_revenue' => 0, 'sold_cost' => 0, 'bill_count' => 0];
            $salesByDate[$date]['sold_revenue'] += $netRevenue;
            $salesByDate[$date]['sold_cost'] += $costOfGoods;
            $salesByDate[$date]['bill_count']++;
        }

        foreach ($customerReturns as $return) {
            $date = Carbon::parse($return->return_date)->toDateString();
            $returnRevenue = 0.0;
            $returnCost = 0.0;
            $sourceProducts = $returnBills->get($return->source_bill_no)?->products;
            $sourceProducts = is_array($sourceProducts) ? $sourceProducts : json_decode((string) $sourceProducts, true);
            $sourceProducts = collect(is_array($sourceProducts) ? $sourceProducts : []);
            foreach ($return->returnItems as $item) {
                $returnRevenue += (float) $item->total_amount;
                $sourceProduct = $sourceProducts->first(
                    fn ($product) => (int) ($product['productId'] ?? $product['medicineId'] ?? $product['medicine_id'] ?? $product['id'] ?? 0) === (int) $item->medicine_inventory_id
                );
                $unitCost = (float) ($sourceProduct['unit_purchase_price'] ?? 0);
                if ($unitCost <= 0) {
                    $unitCost = (float) ($purchaseCostByMedicine->get($item->medicine_inventory_id) ?? 0);
                }
                if ($unitCost <= 0) {
                    $unitCost = (float) ($purchaseCostByName->get(mb_strtolower(trim((string) $item->medicineInventory?->medicine_name))) ?? 0);
                }
                if (!isset($sourceProduct['unit_purchase_price']) || (float) $sourceProduct['unit_purchase_price'] <= 0) {
                    $estimatedCostLines++;
                }
                $itemCost = (int) $item->quantity * max(0, $unitCost);
                $medicineId = $item->medicine_inventory_id;
                $medicineName = $inventoryById->get($medicineId)?->medicine_name ?? 'Unknown medicine';
                $medicineKey = $medicineId ? 'id:' . $medicineId : 'name:' . mb_strtolower(trim((string) $medicineName));
                $salesByMedicine[$medicineKey] ??= [
                    'medicine_id' => $medicineId,
                    'medicine_name' => (string) $medicineName,
                    'sold_qty' => 0,
                    'returned_qty' => 0,
                    'sold_revenue' => 0,
                    'sold_cost' => 0,
                ];
                $salesByMedicine[$medicineKey]['returned_qty'] += (int) $item->quantity;
                $salesByMedicine[$medicineKey]['sold_revenue'] -= (float) $item->total_amount;
                if (strtolower((string) $item->condition) === 'good') {
                    $returnCost += $itemCost;
                    $salesByMedicine[$medicineKey]['sold_cost'] -= $itemCost;
                }
            }

            if ($returnRevenue <= 0 && $returnCost <= 0) {
                continue;
            }
            $salesByDate[$date] ??= ['sold_revenue' => 0, 'sold_cost' => 0, 'bill_count' => 0];
            $salesByDate[$date]['sold_revenue'] -= $returnRevenue;
            $salesByDate[$date]['sold_cost'] -= $returnCost;
        }

        $purchaseItems = MedicinePurchase::query()
            ->with(['purchaseItems:id,medicine_purchase_id,quantity,received_quantity,unit_purchase_price,discount,total_purchase_price'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'purchase_date']);

        $purchasesByDate = [];
        $receivedQuantity = 0;
        foreach ($purchaseItems as $purchase) {
            $date = Carbon::parse($purchase->purchase_date)->toDateString();
            foreach ($purchase->purchaseItems as $item) {
                $received = (int) ($item->received_quantity ?? 0);
                if ($received <= 0) {
                    continue;
                }

                $ordered = max((int) $item->quantity, 1);
                $grossCost = $received * (float) $item->unit_purchase_price;
                $discount = (float) ($item->discount ?? 0) * ($received / $ordered);
                $purchasesByDate[$date] ??= ['received_qty' => 0, 'purchase_cost' => 0];
                $purchasesByDate[$date]['received_qty'] += $received;
                $purchasesByDate[$date]['purchase_cost'] += max(0, $grossCost - $discount);
                $receivedQuantity += $received;
            }
        }

        $allDates = collect(array_unique(array_merge(array_keys($salesByDate), array_keys($purchasesByDate))))->sort()->values();
        $breakdown = $allDates->map(function ($date) use ($salesByDate, $purchasesByDate) {
            $sales = $salesByDate[$date] ?? [];
            $purchases = $purchasesByDate[$date] ?? [];
            $revenue = (float) ($sales['sold_revenue'] ?? 0);
            $cost = (float) ($sales['sold_cost'] ?? 0);

            return [
                'date' => $date,
                'received_qty' => (int) ($purchases['received_qty'] ?? 0),
                'purchase_cost' => round((float) ($purchases['purchase_cost'] ?? 0), 2),
                'bill_count' => (int) ($sales['bill_count'] ?? 0),
                'sold_revenue' => round($revenue, 2),
                'sold_cost' => round($cost, 2),
                'profit' => round($revenue - $cost, 2),
            ];
        });

        if ($period === 'yearly' || ($period === 'custom' && $start->diffInDays($end) > 90)) {
            $breakdown = $breakdown->groupBy(fn ($row) => substr($row['date'], 0, 7))
                ->map(fn ($rows, $month) => [
                    'date' => $month,
                    'received_qty' => $rows->sum('received_qty'),
                    'purchase_cost' => round($rows->sum('purchase_cost'), 2),
                    'bill_count' => $rows->sum('bill_count'),
                    'sold_revenue' => round($rows->sum('sold_revenue'), 2),
                    'sold_cost' => round($rows->sum('sold_cost'), 2),
                    'profit' => round($rows->sum('profit'), 2),
                ])->values();
        }

        $summary = [
            'received_qty' => $receivedQuantity,
            'purchase_cost' => round($breakdown->sum('purchase_cost'), 2),
            'bill_count' => (int) $breakdown->sum('bill_count'),
            'sold_revenue' => round($breakdown->sum('sold_revenue'), 2),
            'sold_cost' => round($breakdown->sum('sold_cost'), 2),
            'profit' => round($breakdown->sum('profit'), 2),
            'estimated_cost_lines' => $estimatedCostLines,
        ];
        $itemSummary = collect($salesByMedicine)
            ->map(fn ($item) => [
                'medicine_id' => $item['medicine_id'],
                'medicine_name' => $item['medicine_name'],
                'sold_qty' => round($item['sold_qty'], 2),
                'returned_qty' => round($item['returned_qty'], 2),
                'net_qty' => round(max(0, $item['sold_qty'] - $item['returned_qty']), 2),
                'sold_revenue' => round($item['sold_revenue'], 2),
                'sold_cost' => round($item['sold_cost'], 2),
                'profit' => round($item['sold_revenue'] - $item['sold_cost'], 2),
            ])
            ->sortByDesc('net_qty')
            ->values();
        $summary['item_count'] = $itemSummary->count();
        $summary['net_sold_qty'] = round($itemSummary->sum('net_qty'), 2);

        return Inertia::render('Backend/PharmacyProfitLossReport/Index', [
            'pageTitle' => 'Pharmacy Profit & Loss Report',
            'summary' => $summary,
            'breakdown' => $breakdown->values(),
            'itemSummary' => $itemSummary,
            'filters' => [
                'period' => $period,
                'date' => $start->toDateString(),
                'month' => $start->format('Y-m'),
                'year' => $start->year,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ]);
    }
}
