<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StoreItem;
use App\Models\StoreGrn;
use App\Models\StoreGrnItem;
use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\StoreStockMovement;
use App\Models\StorePurchaseOrder;
use App\Models\StorePurchaseOrderItem;
use App\Models\StorePurchasePayment;
use App\Models\StoreSupplier;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class StockManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:stock-report-list');
        $this->middleware('permission:stock-report-list-create', ['only' => ['createAdjustment', 'storeAdjustment', 'createRequisition', 'storeRequisition', 'storeRequisitionDepartment', 'recordDepartmentInventoryUsage', 'createItem', 'storeItem', 'createGrn', 'storeGrn', 'updateReagentUsage', 'createPurchaseOrder', 'storePurchaseOrder', 'createPurchasePayment', 'storePurchasePayment', 'createProductReturn', 'storeProductReturn']]);
        $this->middleware('permission:stock-report-list-edit', ['only' => ['requisitionDecision']]);
    }

    public function purchaseOrders(Request $request)
    {
        $orders = StorePurchaseOrder::query()
            ->with('supplier:id,name')
            ->withCount(['items', 'grns', 'payments'])
            ->withSum('payments as paid_amount', 'amount')
            ->addSelect([
                'received_amount' => StoreGrnItem::query()
                    ->join('store_grns', 'store_grns.id', '=', 'store_grn_items.store_grn_id')
                    ->selectRaw('COALESCE(SUM(store_grn_items.line_total), 0)')
                    ->whereColumn('store_grns.store_purchase_order_id', 'store_purchase_orders.id'),
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('purchase_order_no', 'like', '%' . $search . '%')
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->latest('order_date')
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        $orders->through(function (StorePurchaseOrder $order) {
            $order->setAttribute('order_date_display', $order->getRawOriginal('order_date'));
            $order->setAttribute('expected_delivery_date_display', $order->getRawOriginal('expected_delivery_date'));
            $order->setAttribute('can_edit', $order->status === 'Ordered' && $order->grns_count === 0 && $order->payments_count === 0);
            return $order;
        });

        return Inertia::render('Backend/StockManagement/PurchaseOrders', [
            'pageTitle' => 'Purchase Orders',
            'orders' => $orders,
            'payableAccounts' => Account::query()->where('is_active', true)->where('type', 'asset')
                ->whereIn('code', ['CASH', 'BANK'])
                ->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $request->only(['status', 'search', 'per_page']),
        ]);
    }

    public function productReturns(Request $request)
    {
        $returns = \App\Models\StoreProductReturn::query()
            ->with(['supplier:id,name', 'items.storeItem:id,item_code,item_name,unit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('return_number', 'like', '%' . $search . '%')
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('items.storeItem', fn ($itemQuery) => $itemQuery->where('item_name', 'like', '%' . $search . '%'));
                });
            })
            ->latest('return_date')
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        return Inertia::render('Backend/StockManagement/ProductReturns', [
            'pageTitle' => 'Product Returns',
            'returns' => $returns,
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    public function createProductReturn()
    {
        return Inertia::render('Backend/StockManagement/ProductReturnForm', [
            'pageTitle' => 'Return Products to Supplier',
            'suppliers' => StoreSupplier::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'items' => StoreItem::query()
                ->where('status', 'Active')
                ->where('current_stock', '>', 0)
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'category', 'stock_behavior', 'unit', 'current_stock', 'unit_cost']),
        ]);
    }

    public function storeProductReturn(Request $request)
    {
        $data = $request->validate([
            'store_supplier_id' => 'required|exists:store_suppliers,id',
            'return_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|distinct|exists:store_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $productReturn = DB::transaction(function () use ($data, $request) {
            $supplier = StoreSupplier::query()
                ->where('status', 'Active')
                ->lockForUpdate()
                ->find($data['store_supplier_id']);
            if (!$supplier) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'store_supplier_id' => 'Select an active store supplier.',
                ]);
            }

            $returnNumber = 'STR-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
            $return = \App\Models\StoreProductReturn::query()->create([
                'return_number' => $returnNumber,
                'store_supplier_id' => $supplier->id,
                'return_date' => $data['return_date'],
                'total_amount' => 0,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => optional($request->user('admin'))->id,
            ]);

            $totalAmount = 0;
            foreach ($data['items'] as $index => $line) {
                $item = StoreItem::query()->lockForUpdate()->findOrFail($line['store_item_id']);
                $quantity = round((float) $line['quantity'], 2);
                $currentStock = (float) $item->current_stock;

                if ($item->status !== 'Active') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.store_item_id" => 'Select an active store item.',
                    ]);
                }
                if ($quantity <= 0 || $currentStock + 0.0001 < $quantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.quantity" => sprintf(
                            'Return quantity cannot exceed current stock for %s (%s available).',
                            $item->item_name,
                            number_format($currentStock, 2, '.', '')
                        ),
                    ]);
                }
                if (($item->stock_behavior ?? 'consumable') === 'reusable' && $quantity !== floor($quantity)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Reusable asset quantities must be whole numbers.',
                    ]);
                }

                $unitCost = round((float) $line['unit_cost'], 2);
                $lineTotal = round($quantity * $unitCost, 2);
                $totalAmount += $lineTotal;

                $return->items()->create([
                    'store_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);

                $allocations = app(\App\Services\InventoryLotService::class)->consumeFromLocation(
                    $item,
                    null,
                    $quantity,
                    $currentStock,
                    false
                );
                $item->decrement('current_stock', $quantity);

                foreach ($allocations as $allocation) {
                    StoreStockMovement::query()->create([
                        'store_item_id' => $item->id,
                        'inventory_lot_id' => $allocation['lot']->id,
                        'movement_type' => 'decrease',
                        'quantity' => $allocation['quantity'],
                        'unit_price' => $unitCost,
                        'reason' => 'Returned to supplier: ' . $data['reason'],
                        'movement_date' => $data['return_date'],
                        'reference_no' => $returnNumber,
                        'created_by' => optional($request->user('admin'))->id,
                    ]);
                }
            }

            $return->update(['total_amount' => round($totalAmount, 2)]);
            return $return;
        });

        return redirect()->route('backend.stock.product-returns')
            ->with('success', 'Product return saved and stock updated successfully.');
    }

    public function createPurchaseOrder(Request $request)
    {
        $reagentOnly = $request->boolean('reagent_only');
        $itemsQuery = StoreItem::query()->where('status', 'Active');
        if ($reagentOnly) {
            $itemsQuery->whereRaw('LOWER(category) = ?', ['reagent']);
        }
        if ($request->filled('store_item_id')) {
            $itemsQuery->whereKey($request->integer('store_item_id'));
        }

        return Inertia::render('Backend/StockManagement/PurchaseOrderForm', [
            'pageTitle' => $reagentOnly ? 'Purchase Reagents' : 'Create Purchase Order',
            'reagentOnly' => $reagentOnly,
            'initialStoreItemId' => $request->integer('store_item_id') ?: null,
            'suppliers' => StoreSupplier::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'items' => $itemsQuery->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'category', 'stock_behavior', 'unit', 'unit_cost']),
        ]);
    }

    public function storePurchaseOrder(Request $request)
    {
        $data = $request->validate([
            'store_supplier_id' => 'required|exists:store_suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'reagent_only' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|exists:store_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $supplier = StoreSupplier::query()->where('status', 'Active')->find($data['store_supplier_id']);
            if (!$supplier) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'store_supplier_id' => 'Select an active store supplier.',
                ]);
            }

            $total = 0;
            $lines = [];

            foreach ($data['items'] as $index => $line) {
                $item = StoreItem::query()->where('status', 'Active')->find($line['store_item_id']);
                if (!$item) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.store_item_id" => 'Select an active store item.',
                    ]);
                }
                if (!empty($data['reagent_only']) && strtolower(trim((string) $item->category)) !== 'reagent') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.store_item_id" => 'A reagent purchase order can only contain items in the Reagent category.',
                    ]);
                }

                $quantity = round((float) $line['quantity'], 2);
                if ($quantity <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Quantity must be at least 0.01.',
                    ]);
                }
                if (($item->stock_behavior ?? 'consumable') === 'reusable' && $quantity !== floor($quantity)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Reusable asset quantities must be whole numbers.',
                    ]);
                }

                $unitCost = round((float) $line['unit_cost'], 2);
                $lineTotal = round($quantity * $unitCost, 2);
                $total += $lineTotal;
                $lines[] = [
                    'store_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $order = StorePurchaseOrder::query()->create([
                'purchase_order_no' => 'PO-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'store_supplier_id' => $data['store_supplier_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'total_amount' => round($total, 2),
                'status' => 'Ordered',
                'notes' => $data['notes'] ?? null,
                'created_by' => optional($request->user('admin'))->id,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        return redirect()->route('backend.stock.purchase-orders.show', $order->id)
            ->with('success', 'Purchase order created successfully.');
    }

    public function editPurchaseOrder(int $id)
    {
        $order = StorePurchaseOrder::query()
            ->with(['items.storeItem:id,item_code,item_name,category,stock_behavior,unit,unit_cost'])
            ->findOrFail($id);

        if (!$this->purchaseOrderCanBeEdited($order)) {
            return redirect()->route('backend.stock.purchase-orders.show', $order->id)
                ->with('error', 'Only purchase orders with no delivery or payment records can be edited.');
        }

        $reagentOnly = $order->items->isNotEmpty()
            && $order->items->every(fn ($line) => strtolower(trim((string) $line->storeItem?->category)) === 'reagent');

        return Inertia::render('Backend/StockManagement/PurchaseOrderForm', [
            'pageTitle' => 'Edit Purchase Order',
            'order' => [
                'id' => $order->id,
                'purchase_order_no' => $order->purchase_order_no,
                'store_supplier_id' => $order->store_supplier_id,
                'order_date' => $order->getRawOriginal('order_date'),
                'expected_delivery_date' => $order->getRawOriginal('expected_delivery_date'),
                'notes' => $order->notes,
                'items' => $order->items->map(fn ($line) => [
                    'store_item_id' => $line->store_item_id,
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                ])->values(),
            ],
            'reagentOnly' => $reagentOnly,
            'initialStoreItemId' => null,
            'suppliers' => StoreSupplier::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'items' => StoreItem::query()->where('status', 'Active')
                ->when($reagentOnly, fn ($query) => $query->whereRaw('LOWER(category) = ?', ['reagent']))
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'category', 'stock_behavior', 'unit', 'unit_cost']),
        ]);
    }

    public function updatePurchaseOrder(Request $request, int $id)
    {
        $data = $request->validate([
            'store_supplier_id' => 'required|exists:store_suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'reagent_only' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|exists:store_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $request, $id) {
            $order = StorePurchaseOrder::query()->lockForUpdate()->findOrFail($id);
            if (!$this->purchaseOrderCanBeEdited($order)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'purchase_order' => 'This purchase order can no longer be edited because it has delivery or payment records.',
                ]);
            }

            $supplier = StoreSupplier::query()->where('status', 'Active')->find($data['store_supplier_id']);
            if (!$supplier) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'store_supplier_id' => 'Select an active store supplier.',
                ]);
            }

            $lines = [];
            $total = 0;
            foreach ($data['items'] as $index => $line) {
                $item = StoreItem::query()->where('status', 'Active')->find($line['store_item_id']);
                if (!$item) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.store_item_id" => 'Select an active store item.',
                    ]);
                }
                if (!empty($data['reagent_only']) && strtolower(trim((string) $item->category)) !== 'reagent') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.store_item_id" => 'A reagent purchase order can only contain items in the Reagent category.',
                    ]);
                }

                $quantity = round((float) $line['quantity'], 2);
                if (($item->stock_behavior ?? 'consumable') === 'reusable' && $quantity !== floor($quantity)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Reusable asset quantities must be whole numbers.',
                    ]);
                }

                $unitCost = round((float) $line['unit_cost'], 2);
                $lineTotal = round($quantity * $unitCost, 2);
                $total += $lineTotal;
                $lines[] = [
                    'store_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $order->update([
                'store_supplier_id' => $data['store_supplier_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'total_amount' => round($total, 2),
                'notes' => $data['notes'] ?? null,
            ]);
            $order->items()->delete();
            $order->items()->createMany($lines);
        });

        return redirect()->route('backend.stock.purchase-orders.show', $id)
            ->with('success', 'Purchase order updated successfully.');
    }

    private function purchaseOrderCanBeEdited(StorePurchaseOrder $order): bool
    {
        return $order->status === 'Ordered'
            && !$order->grns()->exists()
            && !$order->payments()->exists();
    }

    public function showPurchaseOrder(int $id)
    {
        $order = StorePurchaseOrder::query()
            ->with([
                'supplier:id,name,phone',
                'items.storeItem:id,item_code,item_name,unit,stock_behavior',
                'grns.items.storeItem:id,item_name,unit',
                'payments.paymentAccount:id,name,code',
            ])
            ->findOrFail($id);

        $order->setAttribute('received_amount', (float) $order->grns
            ->flatMap(fn ($grn) => $grn->items)
            ->sum('line_total'));
        $order->setAttribute('paid_amount', (float) $order->payments->sum('amount'));

        foreach ($order->items as $line) {
            $line->setAttribute('received_quantity', (float) $line->grnItems()->sum('quantity'));
        }
        $order->setAttribute('order_date_display', $order->getRawOriginal('order_date'));
        $order->setAttribute('expected_delivery_date_display', $order->getRawOriginal('expected_delivery_date'));
        $order->setAttribute('can_edit', $this->purchaseOrderCanBeEdited($order));

        return Inertia::render('Backend/StockManagement/PurchaseOrderShow', [
            'pageTitle' => 'Purchase Order Details',
            'order' => $order,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
            'payableAccounts' => Account::query()
                ->where('is_active', true)
                ->where('type', 'asset')
                ->whereIn('code', ['CASH', 'BANK'])
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function purchasePayments(Request $request)
    {
        $payments = StorePurchasePayment::query()
            ->with(['purchaseOrder.supplier:id,name', 'paymentAccount:id,name,code'])
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('payment_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('payment_date', '<=', $request->input('to_date')))
            ->latest('payment_date')
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        $outstandingOrders = StorePurchaseOrder::query()
            ->with(['supplier:id,name', 'grns.items', 'payments'])
            ->whereHas('grns')
            ->latest('order_date')
            ->get()
            ->map(function (StorePurchaseOrder $order) {
                $order->setAttribute('received_amount', (float) $order->grns
                    ->flatMap(fn ($grn) => $grn->items)
                    ->sum('line_total'));
                $order->setAttribute('paid_amount', (float) $order->payments->sum('amount'));
                $order->setAttribute('due_amount', max(0, (float) $order->received_amount - (float) $order->paid_amount));

                return $order;
            })
            ->filter(fn (StorePurchaseOrder $order) => (float) $order->due_amount > 0.005)
            ->values();

        return Inertia::render('Backend/StockManagement/PurchasePayments', [
            'pageTitle' => 'Purchase Payments',
            'payments' => $payments,
            'outstandingOrders' => $outstandingOrders,
            'payableAccounts' => Account::query()->where('is_active', true)->where('type', 'asset')
                ->whereIn('code', ['CASH', 'BANK'])
                ->orderBy('name')->get(['id', 'name', 'code']),
            'paymentRouteScope' => 'stock',
            'filters' => $request->only(['from_date', 'to_date', 'per_page']),
        ]);
    }

    public function createPurchasePayment(int $id)
    {
        $order = StorePurchaseOrder::query()->with('supplier:id,name')->findOrFail($id);
        $receivedAmount = (float) StoreGrnItem::query()
            ->whereHas('grn', fn ($query) => $query->where('store_purchase_order_id', $order->id))
            ->sum('line_total');
        $paidAmount = (float) $order->payments()->sum('amount');

        return Inertia::render('Backend/StockManagement/PurchasePaymentForm', [
            'pageTitle' => 'Record Purchase Payment',
            'order' => $order,
            'receivedAmount' => $receivedAmount,
            'paidAmount' => $paidAmount,
            'payableAccounts' => Account::query()->where('is_active', true)->where('type', 'asset')
                ->whereIn('code', ['CASH', 'BANK'])
                ->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function storePurchasePayment(Request $request, int $id)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_account_id' => 'required|integer|exists:accounts,id',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'return_to' => 'nullable|in:purchase-payments,purchase-orders',
        ]);

        app(\App\Services\StorePurchasePaymentService::class)->record(
            $id,
            $data,
            optional($request->user('admin'))->id
        );

        $redirect = match ($data['return_to'] ?? null) {
            'purchase-payments' => redirect()->route('backend.stock.purchase-payments'),
            'purchase-orders' => redirect()->route('backend.stock.purchase-orders'),
            default => redirect()->route('backend.stock.purchase-orders.show', $id),
        };

        return $redirect->with('successMessage', 'Purchase payment recorded successfully.');
    }

    public function index()
    {
        $purchasedQuantities = DB::table('store_grn_items')
            ->select('store_item_id')
            ->selectRaw('COALESCE(SUM(quantity), 0) as purchased_quantity')
            ->groupBy('store_item_id');
        $departmentQuantities = DB::table('department_reagent_stocks')
            ->select('store_item_id')
            ->selectRaw('COALESCE(SUM(current_stock), 0) as department_stock')
            ->groupBy('store_item_id');
        $openingQuantities = StoreStockMovement::query()
            ->where('movement_type', 'increase')
            ->where('reason', 'Opening stock')
            ->select('store_item_id')
            ->selectRaw('COALESCE(SUM(quantity), 0) as opening_quantity')
            ->groupBy('store_item_id');

        $items = StoreItem::query()
            ->leftJoinSub($purchasedQuantities, 'purchased_items', function ($join) {
                $join->on('purchased_items.store_item_id', '=', 'store_items.id');
            })
            ->leftJoinSub($openingQuantities, 'opening_items', function ($join) {
                $join->on('opening_items.store_item_id', '=', 'store_items.id');
            })
            ->leftJoinSub($departmentQuantities, 'department_items', function ($join) {
                $join->on('department_items.store_item_id', '=', 'store_items.id');
            })
            ->select('store_items.id', 'item_code', 'item_name', 'category', 'unit', 'unit_cost', 'reorder_level', 'status')
            ->selectRaw('COALESCE(opening_items.opening_quantity, 0) as opening_quantity')
            ->selectRaw('store_items.current_stock as central_stock')
            ->selectRaw('COALESCE(department_items.department_stock, 0) as department_stock')
            ->selectRaw('store_items.current_stock + COALESCE(department_items.department_stock, 0) as current_stock')
            ->selectRaw('COALESCE(purchased_items.purchased_quantity, 0) as purchased_quantity')
            ->orderBy('item_name')
            ->paginate((int) request()->get('per_page', 20))
            ->withQueryString();

        $departmentTotals = DB::table('department_reagent_stocks')
            ->join('store_items', 'store_items.id', '=', 'department_reagent_stocks.store_item_id')
            ->selectRaw('COALESCE(SUM(department_reagent_stocks.current_stock), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(department_reagent_stocks.current_stock * store_items.unit_cost), 0) as inventory_value')
            ->first();

        $summary = [
            'total_items' => StoreItem::query()->count(),
            'active_items' => StoreItem::query()->where('status', 'Active')->count(),
            'total_quantity' => (float) StoreItem::query()->sum('current_stock') + (float) ($departmentTotals->total_quantity ?? 0),
            'inventory_value' => (float) StoreItem::query()->selectRaw('SUM(current_stock * unit_cost) as total')->value('total') + (float) ($departmentTotals->inventory_value ?? 0),
            'low_stock_items' => StoreItem::query()->whereColumn('current_stock', '<=', 'reorder_level')->where('status', 'Active')->count(),
        ];

        $dailyUsageByDepartment = $this->dailyDepartmentUsageQuery(now()->toDateString());
        $dailyUsage = DB::query()
            ->fromSub($dailyUsageByDepartment, 'daily_usage')
            ->where('used_quantity', '>', 0)
            ->selectRaw('COUNT(DISTINCT store_item_id) as items_used, COALESCE(SUM(used_quantity), 0) as total_used_quantity')
            ->first();

        $activePurchaseOrders = StorePurchaseOrder::query()
            ->whereIn('status', ['Ordered', 'Partially Received']);
        $receivedByOrderItem = StoreGrnItem::query()
            ->select('store_purchase_order_item_id')
            ->selectRaw('SUM(quantity) as received_quantity')
            ->whereNotNull('store_purchase_order_item_id')
            ->groupBy('store_purchase_order_item_id');
        $purchaseQuantities = DB::table('store_purchase_order_items as order_items')
            ->join('store_purchase_orders as purchase_orders', 'purchase_orders.id', '=', 'order_items.store_purchase_order_id')
            ->leftJoinSub($receivedByOrderItem, 'received_items', function ($join) {
                $join->on('received_items.store_purchase_order_item_id', '=', 'order_items.id');
            })
            ->where('purchase_orders.status', '!=', 'Cancelled')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as ordered_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN order_items.quantity > COALESCE(received_items.received_quantity, 0) THEN order_items.quantity - COALESCE(received_items.received_quantity, 0) ELSE 0 END), 0) as pending_delivery_quantity')
            ->first();

        $receivedByOrder = DB::table('store_grn_items')
            ->join('store_grns', 'store_grns.id', '=', 'store_grn_items.store_grn_id')
            ->whereNotNull('store_grns.store_purchase_order_id')
            ->groupBy('store_grns.store_purchase_order_id')
            ->select('store_grns.store_purchase_order_id')
            ->selectRaw('SUM(store_grn_items.line_total) as received_amount');
        $paidByOrder = DB::table('store_purchase_payments')
            ->groupBy('store_purchase_order_id')
            ->select('store_purchase_order_id')
            ->selectRaw('SUM(amount) as paid_amount');
        $purchaseFinancials = DB::table('store_purchase_orders as purchase_orders')
            ->leftJoinSub($receivedByOrder, 'received', function ($join) {
                $join->on('received.store_purchase_order_id', '=', 'purchase_orders.id');
            })
            ->leftJoinSub($paidByOrder, 'paid', function ($join) {
                $join->on('paid.store_purchase_order_id', '=', 'purchase_orders.id');
            })
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(received.received_amount, 0) > COALESCE(paid.paid_amount, 0) THEN received.received_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END), 0) as purchase_due')
            ->selectRaw('COALESCE(SUM(COALESCE(paid.paid_amount, 0)), 0) as supplier_payments')
            ->first();
        $procurementSummary = [
            'open_purchase_orders' => (clone $activePurchaseOrders)->count(),
            'ordered_quantity' => (float) ($purchaseQuantities->ordered_quantity ?? 0),
            'pending_delivery_quantity' => (float) ($purchaseQuantities->pending_delivery_quantity ?? 0),
            'purchase_due' => (float) ($purchaseFinancials->purchase_due ?? 0),
            'supplier_payments' => (float) ($purchaseFinancials->supplier_payments ?? 0),
        ];

        return Inertia::render('Backend/StockManagement/Index', [
            'pageTitle' => 'Store Dashboard',
            'items' => $items,
            'summary' => $summary,
            'currentDate' => now()->toDateString(),
            'dailyUsageSummary' => [
                'items_used' => (int) ($dailyUsage->items_used ?? 0),
                'total_used_quantity' => (float) ($dailyUsage->total_used_quantity ?? 0),
            ],
            'procurementSummary' => $procurementSummary,
            'filters' => request()->only(['per_page']),
        ]);
    }

    public function adjustments()
    {
        $adjustments = StoreStockMovement::with(['storeItem', 'inventoryLot'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate((int) request()->get('per_page', 20))
            ->withQueryString();

        return Inertia::render('Backend/StockManagement/Adjustments', [
            'pageTitle' => 'Store Adjustments',
            'adjustments' => $adjustments,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
            'filters' => request()->only(['per_page']),
        ]);
    }

    public function createAdjustment()
    {
        $items = StoreItem::where('status', 'Active')
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name', 'stock_behavior', 'current_stock', 'unit_cost']);

        return Inertia::render('Backend/StockManagement/AdjustmentForm', [
            'pageTitle' => 'Manual Stock Adjustment',
            'items' => $items,
            'isEdit' => false,
        ]);
    }

    public function createItem(Request $request)
    {
        $createdItem = null;
        if ($request->boolean('notify_opener') && $request->filled('created_item_id')) {
            $createdItem = StoreItem::query()
                ->where('status', 'Active')
                ->find($request->integer('created_item_id'), [
                    'id',
                    'item_code',
                    'item_name',
                    'category',
                    'stock_behavior',
                    'unit',
                    'unit_cost',
                ]);
        }

        return Inertia::render('Backend/StockManagement/ItemForm', [
            'pageTitle' => 'Store Item Setup',
            'initialCategory' => $request->query('category', ''),
            'categories' => StoreItem::query()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'units' => StoreItem::query()
                ->whereNotNull('unit')
                ->where('unit', '!=', '')
                ->distinct()
                ->orderBy('unit')
                ->pluck('unit')
                ->values(),
            'itemNames' => StoreItem::query()
                ->whereNotNull('item_name')
                ->where('item_name', '!=', '')
                ->distinct()
                ->orderBy('item_name')
                ->pluck('item_name')
                ->values(),
            'createdItem' => $createdItem,
        ]);
    }

    public function reagents()
    {
        $reagents = StoreItem::query()->whereRaw('LOWER(category) = ?', ['reagent']);
        $labDepartmentId = \App\Models\Department::query()
            ->where('status', 'Active')
            ->whereRaw('LOWER(name) = ?', ['lab'])
            ->value('id');

        $summary = [
            'total_items' => (clone $reagents)->count(),
            'active_items' => (clone $reagents)->where('status', 'Active')->count(),
            'total_quantity' => (float) (clone $reagents)->sum('current_stock'),
            'inventory_value' => (float) (clone $reagents)->selectRaw('SUM(current_stock * unit_cost) as total')->value('total'),
            'low_stock_items' => (clone $reagents)->whereColumn('current_stock', '<=', 'reorder_level')->where('status', 'Active')->count(),
        ];

        return Inertia::render('Backend/StockManagement/Reagents', [
            'pageTitle' => 'Reagent Management',
            'labDepartmentId' => $labDepartmentId ? (int) $labDepartmentId : null,
            'items' => (clone $reagents)
                ->select('id', 'item_code', 'item_name', 'category', 'unit', 'current_stock', 'unit_cost', 'reorder_level', 'status')
                ->orderBy('item_name')
                ->paginate((int) request()->get('per_page', 20))
                ->withQueryString(),
            'summary' => $summary,
            'filters' => request()->only(['per_page']),
        ]);
    }

    public function departmentReagents(Request $request)
    {
        $query = \App\Models\DepartmentReagentStock::query()
            ->with(['department:id,name', 'storeItem:id,item_code,item_name,category,stock_behavior,unit,unit_cost,reorder_level'])
            ->addSelect(['available_stock' => \App\Models\StoreItemLot::query()
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('store_item_lots.store_item_id', 'department_reagent_stocks.store_item_id')
                ->whereColumn('store_item_lots.department_id', 'department_reagent_stocks.department_id')
                ->where(function ($lotQuery) {
                    $lotQuery->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()->toDateString());
                })])
            ->addSelect([
                'issued_quantity' => \App\Models\DepartmentReagentMovement::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('department_reagent_movements.department_reagent_stock_id', 'department_reagent_stocks.id')
                    ->where('movement_type', 'transfer_in'),
                'consumed_quantity' => \App\Models\ReagentBillConsumption::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('reagent_bill_consumptions.store_item_id', 'department_reagent_stocks.store_item_id')
                    ->whereColumn('reagent_bill_consumptions.department_id', 'department_reagent_stocks.department_id')
                    ->whereNull('reversed_at'),
                'department_use_quantity' => \App\Models\DepartmentReagentMovement::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('department_reagent_movements.department_reagent_stock_id', 'department_reagent_stocks.id')
                    ->where('movement_type', 'department_use'),
            ]);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        $stocks = $query->orderBy('department_id')->orderBy('store_item_id')
            ->paginate(50)
            ->withQueryString();

        $lotQuery = \App\Models\StoreItemLot::query()
            ->with(['department:id,name', 'storeItem:id,item_code,item_name,category,stock_behavior,unit'])
            ->where('quantity', '>', 0);
        if ($request->filled('department_id')) {
            $lotQuery->where('department_id', $request->integer('department_id'));
        }

        $movementQuery = \App\Models\DepartmentReagentMovement::query()
            ->with([
                'department:id,name',
                'storeItem:id,item_code,item_name,unit',
                'inventoryLot:id,batch_no,expiry_date,serial_number,asset_tag',
                'createdByAdmin:id,first_name,last_name',
            ]);
        if ($request->filled('department_id')) {
            $movementQuery->where('department_id', $request->integer('department_id'));
        }

        return Inertia::render('Backend/StockManagement/DepartmentReagents', [
            'pageTitle' => 'Department Inventory',
            'stocks' => $stocks,
            'lots' => $lotQuery->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->paginate(100, ['*'], 'lots_page')
                ->withQueryString(),
            'movements' => $movementQuery->orderByDesc('id')->limit(100)->get(),
            'departments' => \App\Models\Department::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
            'filters' => $request->only(['department_id']),
        ]);
    }

    public function recordDepartmentInventoryUsage(Request $request, int $id)
    {
        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'reference_no' => 'nullable|string|max:100',
        ]);

        app(\App\Services\DepartmentInventoryTransferService::class)->recordDepartmentUse(
            $id,
            (float) $data['quantity'],
            trim($data['reason']),
            filled($data['reference_no'] ?? null) ? trim($data['reference_no']) : null,
            optional($request->user('admin'))->id
        );

        $stock = \App\Models\DepartmentReagentStock::query()->findOrFail($id);

        return redirect()->route('backend.stock.department-reagents', [
            'department_id' => $stock->department_id,
        ])->with('success', 'Department usage recorded and stock balance updated.');
    }

    public function reagentUsage(int $id)
    {
        $reagent = StoreItem::query()->findOrFail($id);
        $isFilmItem = \App\Services\ReagentConsumptionService::isFilmItem($reagent);
        $categoryOptions = collect(['Pathology', 'Radiology', 'Imaging', 'ECG', 'Ultrasound', 'X-Ray']);

        $categoryOptions = $categoryOptions
            ->merge(\App\Models\Test::query()->where('status', 'Active')->distinct()->pluck('category_type'))
            ->merge(\App\Models\MainCategory::query()
                ->whereIn('id', \App\Models\Test::query()
                    ->where('status', 'Active')
                    ->whereNotNull('main_category_id')
                    ->select('main_category_id'))
                ->pluck('name'))
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->unique(fn ($name) => \App\Services\ReagentConsumptionService::normalizeCategoryKey($name))
            ->map(fn ($name) => [
                'category_key' => \App\Services\ReagentConsumptionService::normalizeCategoryKey($name),
                'label' => $name,
            ])
            ->filter(fn ($category) => $category['category_key'] !== '')
            ->values();

        return Inertia::render('Backend/StockManagement/ReagentUsage', [
            'pageTitle' => 'Configure Bill Usage',
            'reagent' => $reagent,
            'isFilmItem' => $isFilmItem,
            'tests' => \App\Models\Test::query()
                ->whereIn('category_type', ['Pathology', 'Radiology', 'Imaging', 'ECG', 'Ultrasound'])
                ->where('status', 'Active')
                ->orderBy('test_name')
                ->get(['id', 'test_name', 'category_type']),
            'categoryOptions' => $categoryOptions,
            'departments' => \App\Models\Department::query()
                ->where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'mappings' => \App\Models\ReagentTestUsage::query()
                ->where('store_item_id', $reagent->id)
                ->orderBy('id')
                ->get(['id', 'test_id', 'department_id', 'quantity_per_test']),
            'categoryMappings' => $isFilmItem
                ? []
                : \App\Models\ReagentCategoryUsage::query()
                    ->where('store_item_id', $reagent->id)
                    ->orderBy('id')
                    ->get(['id', 'category_key', 'department_id', 'quantity_per_test']),
        ]);
    }

    public function updateReagentUsage(Request $request, int $id)
    {
        $reagent = StoreItem::query()->findOrFail($id);

        $data = $request->validate([
            'mappings' => 'present|array',
            'mappings.*.test_id' => 'required|integer|distinct|exists:tests,id',
            'mappings.*.department_id' => 'required|integer|exists:departments,id',
            'mappings.*.quantity_per_test' => 'required|numeric|min:0.01|max:1000000',
            'category_mappings' => 'present|array',
            'category_mappings.*.category_key' => 'required|string|distinct|max:100',
            'category_mappings.*.department_id' => 'required|integer|exists:departments,id',
            'category_mappings.*.quantity_per_test' => 'required|numeric|min:0.01|max:1000000',
        ]);

        $isFilmItem = \App\Services\ReagentConsumptionService::isFilmItem($reagent);
        if ($isFilmItem && !empty($data['category_mappings'])) {
            return back()->withErrors([
                'category_mappings' => 'Film usage must be configured per test so each test deducts the correct film size.',
            ]);
        }
        if ($isFilmItem) {
            $data['category_mappings'] = [];
            $data['mappings'] = collect($data['mappings'])
                ->map(fn ($mapping) => array_merge($mapping, ['quantity_per_test' => 1]))
                ->all();
        }

        $testIds = collect($data['mappings'])->pluck('test_id')->all();
        if ($isFilmItem && $testIds !== []) {
            $duplicateFilmUsage = \App\Models\ReagentTestUsage::query()
                ->whereIn('test_id', $testIds)
                ->where('store_item_id', '!=', $reagent->id)
                ->whereHas('storeItem', function ($query) {
                    $query->whereRaw('LOWER(category) LIKE ?', ['%film%'])
                        ->orWhereRaw('LOWER(item_name) LIKE ?', ['%film%']);
                })
                ->exists();

            if ($duplicateFilmUsage) {
                return back()->withErrors([
                    'mappings' => 'A test can use only one film size. Remove its existing mapping from the other film item first.',
                ]);
            }
        }

        $categoryKeys = collect($data['category_mappings'])
            ->pluck('category_key')
            ->map(fn ($key) => \App\Services\ReagentConsumptionService::normalizeCategoryKey((string) $key));
        $validCategoryKeys = collect($this->reagentUsageCategoryKeys());

        if ($categoryKeys->contains(fn ($key) => $key === '') || $categoryKeys->diff($validCategoryKeys)->isNotEmpty() || $categoryKeys->unique()->count() !== $categoryKeys->count()) {
            return back()->withErrors(['category_mappings' => 'Select a valid test category for each usage mapping.']);
        }

        $departmentIds = collect($data['mappings'])
            ->pluck('department_id')
            ->merge(collect($data['category_mappings'])->pluck('department_id'))
            ->unique()
            ->all();
        $validTestCount = \App\Models\Test::query()
            ->whereIn('id', $testIds)
            ->whereIn('category_type', ['Pathology', 'Radiology', 'Imaging', 'ECG', 'Ultrasound'])
            ->where('status', 'Active')
            ->count();

        $validDepartmentCount = \App\Models\Department::query()
            ->whereIn('id', $departmentIds)
            ->where('status', 'Active')
            ->count();

        if ($validTestCount !== count($testIds) || $validDepartmentCount !== count($departmentIds)) {
            return back()->withErrors(['mappings' => 'Select active diagnostic tests and active departments.']);
        }

        DB::transaction(function () use ($reagent, $data, $testIds, $categoryKeys) {
            $usageQuery = \App\Models\ReagentTestUsage::query()->where('store_item_id', $reagent->id);
            if (empty($testIds)) {
                $usageQuery->delete();
            } else {
                $usageQuery->whereNotIn('test_id', $testIds)->delete();
            }

            foreach ($data['mappings'] as $mapping) {
                \App\Models\ReagentTestUsage::updateOrCreate(
                    ['store_item_id' => $reagent->id, 'test_id' => $mapping['test_id']],
                    [
                        'department_id' => $mapping['department_id'],
                        'quantity_per_test' => $mapping['quantity_per_test'],
                    ]
                );
            }

            $categoryUsageQuery = \App\Models\ReagentCategoryUsage::query()->where('store_item_id', $reagent->id);
            if ($categoryKeys->isEmpty()) {
                $categoryUsageQuery->delete();
            } else {
                $categoryUsageQuery->whereNotIn('category_key', $categoryKeys->all())->delete();
            }

            foreach ($data['category_mappings'] as $mapping) {
                \App\Models\ReagentCategoryUsage::updateOrCreate(
                    [
                        'store_item_id' => $reagent->id,
                        'category_key' => \App\Services\ReagentConsumptionService::normalizeCategoryKey($mapping['category_key']),
                    ],
                    [
                        'department_id' => $mapping['department_id'],
                        'quantity_per_test' => $mapping['quantity_per_test'],
                    ]
                );
            }
        });

        return redirect()->route('backend.stock.index')->with('success', 'Bill usage configuration saved successfully.');
    }

    private function reagentUsageCategoryKeys(): array
    {
        return collect(['Pathology', 'Radiology', 'Imaging', 'ECG', 'Ultrasound', 'X-Ray'])
            ->merge(\App\Models\Test::query()->where('status', 'Active')->distinct()->pluck('category_type'))
            ->merge(\App\Models\MainCategory::query()
                ->whereIn('id', \App\Models\Test::query()
                    ->where('status', 'Active')
                    ->whereNotNull('main_category_id')
                    ->select('main_category_id'))
                ->pluck('name'))
            ->filter()
            ->map(fn ($name) => \App\Services\ReagentConsumptionService::normalizeCategoryKey((string) $name))
            ->unique()
            ->values()
            ->all();
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'nullable|string|max:50|unique:store_items,item_code',
            'item_name' => 'required|string|max:255',
            'category' => 'nullable|string|max:120',
            'stock_behavior' => 'required|in:consumable,reusable',
            'batch_no' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'serial_number' => 'nullable|string|max:120|unique:store_item_lots,serial_number',
            'asset_tag' => 'nullable|string|max:120|unique:store_item_lots,asset_tag',
            'unit' => 'required|string|max:50',
            'reorder_level' => 'required|numeric|min:0',
            'current_stock' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'status' => 'required|in:Active,Inactive',
            'notes' => 'nullable|string',
        ]);

        if ($request->boolean('notify_opener') && $data['status'] !== 'Active') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Choose Active status to add this item to the purchase order.',
            ]);
        }

        if ($data['stock_behavior'] === 'reusable' && (float) $data['current_stock'] > 0
            && ((float) $data['current_stock'] !== 1.0 || (empty($data['serial_number']) && empty($data['asset_tag'])))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'serial_number' => 'Opening reusable stock must be one identified device. Add each device separately with a serial number or asset tag.',
            ]);
        }

        if (empty($data['item_code'])) {
            $data['item_code'] = 'ST-' . str_pad((string) (StoreItem::query()->max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        $item = StoreItem::create($data);

        if ((float) $item->current_stock > 0) {
            $lot = app(\App\Services\InventoryLotService::class)->receive(
                $item->id,
                null,
                (float) $item->current_stock,
                (float) $item->unit_cost,
                $data['batch_no'] ?? 'OPEN-' . $item->id,
                $data['expiry_date'] ?? null,
                $data['serial_number'] ?? null,
                $data['asset_tag'] ?? null
            );
            StoreStockMovement::create([
                'store_item_id' => $item->id,
                'inventory_lot_id' => $lot->id,
                'movement_type' => 'increase',
                'quantity' => $item->current_stock,
                'unit_price' => $item->unit_cost,
                'reason' => 'Opening stock',
                'movement_date' => now()->toDateString(),
                'reference_no' => 'OPEN-' . $item->id,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'item' => $item->only([
                    'id',
                    'item_code',
                    'item_name',
                    'category',
                    'stock_behavior',
                    'unit',
                    'current_stock',
                    'unit_cost',
                ]),
            ], 201);
        }

        if ($request->boolean('notify_opener')) {
            return redirect()->route('backend.stock.item.create', [
                'notify_opener' => 1,
                'created_item_id' => $item->id,
            ])->with('success', 'Store item created successfully');
        }

        return redirect()->route('backend.stock.index')->with('success', 'Store item created successfully');
    }

    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'store_item_id' => 'required|exists:store_items,id',
            'adjustment_type' => 'required|in:increase,decrease',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'batch_no' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after_or_equal:movement_date',
            'serial_number' => 'nullable|string|max:120|unique:store_item_lots,serial_number',
            'asset_tag' => 'nullable|string|max:120|unique:store_item_lots,asset_tag',
            'reason' => 'required|string',
            'movement_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($request) {
            $item = StoreItem::findOrFail($request->store_item_id);
            $quantity = (float) $request->quantity;
            $currentStock = (float) $item->current_stock;

            if ($request->adjustment_type === 'increase' && ($item->stock_behavior ?? 'consumable') === 'reusable'
                && ($quantity !== 1.0 || (!$request->filled('serial_number') && !$request->filled('asset_tag')))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'serial_number' => 'Reusable assets must be stocked one per adjustment with a serial number or asset tag.',
                ]);
            }

            if ($request->adjustment_type === 'decrease' && $currentStock < $quantity) {
                abort(422, 'Adjustment quantity cannot be greater than current stock.');
            }

            if ($request->adjustment_type === 'increase') {
                $unitCost = (float) ($request->unit_price ?? $item->unit_cost);
                $lot = app(\App\Services\InventoryLotService::class)->receive(
                    $item->id,
                    null,
                    $quantity,
                    $unitCost,
                    $request->batch_no ?: ($request->reference_no ?: 'ADJ-' . $item->id . '-' . now()->format('YmdHis')),
                    $request->expiry_date,
                    $request->serial_number,
                    $request->asset_tag,
                    $request->movement_date
                );
                $item->increment('current_stock', $quantity);
                StoreStockMovement::create([
                    'store_item_id' => $item->id,
                    'inventory_lot_id' => $lot->id,
                    'movement_type' => 'increase',
                    'quantity' => $quantity,
                    'unit_price' => $unitCost,
                    'reason' => $request->reason,
                    'movement_date' => $request->movement_date,
                    'reference_no' => $request->reference_no,
                    'department' => $request->department,
                ]);
                if ($request->filled('unit_price')) {
                    $item->unit_cost = (float) $request->unit_price;
                    $item->save();
                }
            } else {
                $allocations = app(\App\Services\InventoryLotService::class)->consumeFromLocation(
                    $item,
                    null,
                    $quantity,
                    $currentStock,
                    false
                );
                $item->decrement('current_stock', $quantity);
                foreach ($allocations as $allocation) {
                    StoreStockMovement::create([
                        'store_item_id' => $item->id,
                        'inventory_lot_id' => $allocation['lot']->id,
                        'movement_type' => 'decrease',
                        'quantity' => $allocation['quantity'],
                        'unit_price' => $request->unit_price ?? $allocation['lot']->unit_cost,
                        'reason' => $request->reason,
                        'movement_date' => $request->movement_date,
                        'reference_no' => $request->reference_no,
                        'department' => $request->department,
                    ]);
                }
            }
        });

        return redirect()->route('backend.stock.adjustments')->with('success', 'Stock adjustment created successfully');
    }

    public function lowStockReport()
    {
        $defaultThreshold = (int) (get_cached_web_setting()?->low_stock_threshold ?? 10);
        $threshold = (float) request()->get('threshold', $defaultThreshold);

        $lowStockItems = StoreItem::where('current_stock', '<=', $threshold)
            ->where('status', 'Active')
            ->orderBy('current_stock')
            ->get();

        return Inertia::render('Backend/StockManagement/LowStockReport', [
            'pageTitle' => 'Low Stock Report',
            'items' => $lowStockItems,
            'threshold' => $threshold,
        ]);
    }

    public function stockReport(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:120',
            'status' => 'nullable|in:Active,Inactive',
            'stock_level' => 'nullable|in:all,low,out,available',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $filters['stock_level'] = $filters['stock_level'] ?? 'all';

        $query = StoreItem::query()
            ->when(!empty($filters['search']), function ($builder) use ($filters) {
                $search = trim($filters['search']);
                $builder->where(function ($itemQuery) use ($search) {
                    $itemQuery->where('item_name', 'like', '%' . $search . '%')
                        ->orWhere('item_code', 'like', '%' . $search . '%')
                        ->orWhere('category', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($filters['category']), fn ($builder) => $builder->where('category', $filters['category']))
            ->when(!empty($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when($filters['stock_level'] === 'low', fn ($builder) => $builder->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'reorder_level'))
            ->when($filters['stock_level'] === 'out', fn ($builder) => $builder->where('current_stock', '<=', 0))
            ->when($filters['stock_level'] === 'available', fn ($builder) => $builder->where('current_stock', '>', 0));

        if ($request->get('export') === 'csv') {
            return $this->exportStockReportCsv($query);
        }

        $summary = (clone $query)->selectRaw(
            'COUNT(*) as item_count, COALESCE(SUM(current_stock), 0) as total_quantity, COALESCE(SUM(current_stock * unit_cost), 0) as total_value'
        )->first();

        $orderedQuery = $query->orderBy('category')->orderBy('item_name')->orderBy('id');
        $items = $request->boolean('print')
            ? $orderedQuery->get()
            : $orderedQuery->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
        $this->addLabTestCountsToStockReport(
            $items instanceof \Illuminate\Pagination\AbstractPaginator
                ? $items->getCollection()
                : $items
        );

        return Inertia::render('Backend/StockManagement/StockReport', [
            'pageTitle' => 'Stock Report',
            'items' => $items,
            'printMode' => $request->boolean('print'),
            'summary' => [
                'item_count' => (int) ($summary->item_count ?? 0),
                'total_quantity' => (float) ($summary->total_quantity ?? 0),
                'total_value' => (float) ($summary->total_value ?? 0),
            ],
            'categories' => StoreItem::query()->whereNotNull('category')->where('category', '!=', '')
                ->distinct()->orderBy('category')->pluck('category'),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
                'stock_level' => $filters['stock_level'],
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
        ]);
    }

    private function addLabTestCountsToStockReport(\Illuminate\Support\Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $mappings = \App\Models\ReagentTestUsage::query()
            ->whereIn('store_item_id', $items->modelKeys())
            ->whereNotNull('department_id')
            ->get(['store_item_id', 'test_id', 'department_id', 'quantity_per_test']);
        if ($mappings->isEmpty()) {
            return;
        }

        $storeItemIds = $mappings->pluck('store_item_id')->unique()->values();
        $departmentIds = $mappings->pluck('department_id')->unique()->values();
        $stockKey = fn ($storeItemId, $departmentId) => $storeItemId . ':' . $departmentId;

        $departmentStocks = \App\Models\DepartmentReagentStock::query()
            ->whereIn('store_item_id', $storeItemIds)
            ->whereIn('department_id', $departmentIds)
            ->get(['store_item_id', 'department_id', 'current_stock'])
            ->keyBy(fn ($stock) => $stockKey($stock->store_item_id, $stock->department_id));

        $unexpiredLots = \App\Models\StoreItemLot::query()
            ->selectRaw('store_item_id, department_id, COALESCE(SUM(quantity), 0) AS available_stock')
            ->whereIn('store_item_id', $storeItemIds)
            ->whereIn('department_id', $departmentIds)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()->toDateString()))
            ->groupBy('store_item_id', 'department_id')
            ->get()
            ->keyBy(fn ($lot) => $stockKey($lot->store_item_id, $lot->department_id));

        $consumedBillItems = DB::table('reagent_bill_consumptions')
            ->whereIn('store_item_id', $storeItemIds)
            ->whereNull('reversed_at')
            ->select('store_item_id', 'department_id', 'bill_item_id')
            ->distinct();
        $testsDone = DB::table('bill_items as bill_items')
            ->joinSub($consumedBillItems, 'consumed_items', function ($join) {
                $join->on('bill_items.id', '=', 'consumed_items.bill_item_id');
            })
            ->whereNull('bill_items.deleted_at')
            ->selectRaw('consumed_items.store_item_id, consumed_items.department_id, bill_items.item_id AS test_id, SUM(bill_items.quantity) AS tests_done')
            ->groupBy('consumed_items.store_item_id', 'consumed_items.department_id', 'bill_items.item_id')
            ->get()
            ->keyBy(fn ($row) => $row->store_item_id . ':' . $row->department_id . ':' . $row->test_id);

        $countsByItem = [];
        foreach ($mappings as $mapping) {
            $itemId = (int) $mapping->store_item_id;
            $departmentId = (int) $mapping->department_id;
            $testId = (int) $mapping->test_id;
            $key = $stockKey($itemId, $departmentId);
            $quantityPerTest = (float) $mapping->quantity_per_test;
            $testKey = $itemId . ':' . $departmentId . ':' . $testId;
            $itemCounts = $countsByItem[$itemId] ?? [
                'tests_done' => 0.0,
                'tests_remaining' => null,
                'has_test_usage' => true,
            ];

            $itemCounts['tests_done'] += (float) ($testsDone->get($testKey)?->tests_done ?? 0);
            if ($quantityPerTest > 0) {
                $departmentStock = (float) ($departmentStocks->get($key)?->current_stock ?? 0);
                $lotStock = (float) ($unexpiredLots->get($key)?->available_stock ?? 0);
                $usableStock = min($departmentStock, $lotStock);
                $remainingForTest = (int) floor(($usableStock + 0.000001) / $quantityPerTest);
                $itemCounts['tests_remaining'] = $itemCounts['tests_remaining'] === null
                    ? $remainingForTest
                    : min($itemCounts['tests_remaining'], $remainingForTest);
            }

            $countsByItem[$itemId] = $itemCounts;
        }

        foreach ($items as $item) {
            $counts = $countsByItem[$item->id] ?? null;
            $item->setAttribute('has_test_usage', (bool) $counts);
            $item->setAttribute('lab_tests_done', (float) ($counts['tests_done'] ?? 0));
            $item->setAttribute('lab_tests_remaining', (int) ($counts['tests_remaining'] ?? 0));
        }
    }

    private function exportStockReportCsv($query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }

            if (fwrite($handle, "\xEF\xBB\xBF") === false) {
                throw new \RuntimeException('Unable to write the CSV encoding marker.');
            }
            fputcsv($handle, ['Item Code', 'Item Name', 'Category', 'Behavior', 'Unit', 'Main Store Stock', 'Lab Tests Done', 'Lab Tests Remaining', 'Unit Cost', 'Stock Value', 'Reorder Level', 'Status']);
            $query->orderBy('category')->orderBy('item_name')->orderBy('id')->chunk(500, function ($items) use ($handle) {
                $this->addLabTestCountsToStockReport($items);
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->item_code,
                        $item->item_name,
                        $item->category,
                        $item->stock_behavior ?? 'consumable',
                        $item->unit,
                        $item->current_stock,
                        $item->has_test_usage ? $item->lab_tests_done : '',
                        $item->has_test_usage ? $item->lab_tests_remaining : '',
                        $item->unit_cost,
                        round((float) $item->current_stock * (float) $item->unit_cost, 2),
                        $item->reorder_level,
                        $item->status,
                    ]);
                }
            });

            fclose($handle);
        }, 'store-stock-report-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function stockMovementReport(Request $request)
    {
        $query = StoreStockMovement::with('storeItem');

        if ($request->filled('store_item_id')) {
            $query->where('store_item_id', $request->store_item_id);
        }

        if ($request->filled('start_date')) {
            $query->where('movement_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('movement_date', '<=', $request->end_date);
        }

        $adjustments = $query->orderByDesc('movement_date')->orderByDesc('id')->paginate(10)->withQueryString();

        $items = StoreItem::where('status', 'Active')->orderBy('item_name')->get(['id', 'item_name', 'item_code']);

        return Inertia::render('Backend/StockManagement/StockMovementReport', [
            'pageTitle' => 'Stock Movement Report',
            'adjustments' => $adjustments,
            'items' => $items,
            'filters' => $request->only(['store_item_id', 'start_date', 'end_date']),
        ]);
    }

    public function dailyUsageReport(Request $request)
    {
        $date = (string) ($request->validate([
            'date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
        ])['date'] ?? now()->toDateString());

        $dailyUsageByDepartment = $this->dailyDepartmentUsageQuery($date);
        $summary = DB::query()
            ->fromSub($dailyUsageByDepartment, 'daily_usage')
            ->where('used_quantity', '>', 0)
            ->selectRaw('COUNT(DISTINCT store_item_id) as items_used')
            ->selectRaw('COUNT(DISTINCT department_id) as departments_using')
            ->selectRaw('COALESCE(SUM(used_quantity), 0) as total_used_quantity')
            ->first();

        $rows = DB::query()
            ->fromSub($dailyUsageByDepartment, 'daily_usage')
            ->join('store_items as items', 'items.id', '=', 'daily_usage.store_item_id')
            ->leftJoin('departments', 'departments.id', '=', 'daily_usage.department_id')
            ->leftJoin('department_reagent_stocks as department_stocks', function ($join) {
                $join->on('department_stocks.store_item_id', '=', 'daily_usage.store_item_id')
                    ->on('department_stocks.department_id', '=', 'daily_usage.department_id');
            })
            ->where('daily_usage.used_quantity', '>', 0)
            ->groupBy(
                'daily_usage.store_item_id',
                'items.item_code',
                'items.item_name',
                'items.unit',
                'daily_usage.department_id',
                'departments.name',
                'department_stocks.current_stock'
            )
            ->select([
                'daily_usage.store_item_id',
                'items.item_code',
                'items.item_name',
                'items.unit',
                'daily_usage.department_id',
                'departments.name as department_name',
                'department_stocks.current_stock',
            ])
            ->selectRaw('daily_usage.used_quantity as used_quantity')
            ->orderBy('items.item_name')
            ->orderBy('departments.name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Backend/StockManagement/DailyUsage', [
            'pageTitle' => 'Daily Stock Usage',
            'date' => $date,
            'currentDate' => now()->toDateString(),
            'summary' => [
                'total_items' => StoreItem::query()->count(),
                'items_used' => (int) ($summary->items_used ?? 0),
                'departments_using' => (int) ($summary->departments_using ?? 0),
                'total_used_quantity' => (float) ($summary->total_used_quantity ?? 0),
            ],
            'rows' => $rows,
        ]);
    }

    private function dailyDepartmentUsageQuery(string $date)
    {
        return DB::table('department_reagent_movements')
            ->whereDate('movement_date', $date)
            ->whereIn('movement_type', ['department_use', 'consumption', 'reversal'])
            ->groupBy('store_item_id', 'department_id')
            ->select('store_item_id', 'department_id')
            ->selectRaw(
                'SUM(CASE WHEN movement_type = ? THEN -quantity ELSE quantity END) as used_quantity',
                ['reversal']
            );
    }

    public function requisitions(Request $request)
    {
        $query = StoreRequisition::query()
            ->with([
                'items.storeItem',
                'requestedByAdmin:id,first_name,last_name',
                'approvedByAdmin:id,first_name,last_name',
            ])
            ->orderByDesc('id');

        if ($request->filled('status') && in_array($request->status, ['Pending', 'Approved', 'Rejected'], true)) {
            $query->where('status', $request->status);
        }

        $requisitions = $query->paginate((int) $request->get('per_page', 20))->withQueryString();

        return Inertia::render('Backend/StockManagement/Requisitions', [
            'pageTitle' => 'Department Requisitions',
            'requisitions' => $requisitions,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
            'filters' => $request->only(['status', 'per_page']),
        ]);
    }

    public function createRequisition(Request $request)
    {
        $reagentOnly = $request->boolean('reagent_only');
        $itemsQuery = StoreItem::query()
            ->where('status', 'Active')
            ->orderBy('item_name');
        if ($reagentOnly) {
            $itemsQuery->whereRaw('LOWER(category) = ?', ['reagent']);
        }
        if ($request->filled('store_item_id')) {
            $itemsQuery->whereKey($request->integer('store_item_id'));
        }

        $adminId = (int) ($request->user('admin')?->id ?? 0);
        $requestedDepartmentId = $request->integer('department_id');
        $requestedActiveDepartmentId = $requestedDepartmentId
            ? \App\Models\Department::query()->where('status', 'Active')->whereKey($requestedDepartmentId)->value('id')
            : null;
        $defaultDepartmentId = $requestedActiveDepartmentId
            ?: \App\Models\AdminDetail::query()->where('admin_id', $adminId)->value('department_id');

        return Inertia::render('Backend/StockManagement/RequisitionForm', [
            'pageTitle' => $reagentOnly ? 'Create Reagent Indent' : 'Create Department Requisition',
            'items' => $itemsQuery->get(['id', 'item_code', 'item_name', 'category', 'unit', 'current_stock']),
            'departments' => \App\Models\Department::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'defaultDepartmentId' => $defaultDepartmentId,
            'reagentOnly' => $reagentOnly,
            'initialStoreItemId' => $request->integer('store_item_id') ?: null,
        ]);
    }

    public function storeRequisition(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'reagent_only' => 'nullable|boolean',
            'needed_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|exists:store_items,id',
            'items.*.requested_qty' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        $department = \App\Models\Department::query()
            ->where('status', 'Active')
            ->findOrFail($data['department_id']);
        if (!empty($data['reagent_only'])) {
            $requestedItemIds = collect($data['items'])->pluck('store_item_id')->unique();
            $reagentCount = StoreItem::query()
                ->whereIn('id', $requestedItemIds)
                ->whereRaw('LOWER(category) = ?', ['reagent'])
                ->count();
            if ($reagentCount !== $requestedItemIds->count()) {
                return back()->withErrors(['items' => 'A reagent indent can only contain reagent items.']);
            }
        }

        DB::transaction(function () use ($data, $request, $department) {
            $nextId = (int) (StoreRequisition::query()->max('id') ?? 0) + 1;
            $requisition = StoreRequisition::create([
                'requisition_no' => 'SRQ-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT),
                'department' => $department->name,
                'department_id' => $department->id,
                'requested_by' => optional($request->user('admin'))->id,
                'needed_date' => $data['needed_date'] ?? null,
                'status' => 'Pending',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                StoreRequisitionItem::create([
                    'store_requisition_id' => $requisition->id,
                    'store_item_id' => $item['store_item_id'],
                    'requested_qty' => (float) $item['requested_qty'],
                    'issued_qty' => 0,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->route('backend.stock.requisitions')->with('success', 'Requisition submitted successfully');
    }

    public function storeRequisitionDepartment(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('departments', 'name')->whereNull('deleted_at'),
            ],
        ]);

        $department = \App\Models\Department::query()->create([
            'name' => trim($data['name']),
            'status' => 'Active',
        ]);

        return response()->json([
            'department' => $department->only(['id', 'name']),
        ], 201);
    }

    public function requisitionDecision(Request $request, $id)
    {
        $payload = $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        DB::transaction(function () use ($payload, $id, $request) {
            $requisition = StoreRequisition::query()->with(['items.storeItem'])->lockForUpdate()->findOrFail($id);

            if ($requisition->status !== 'Pending') {
                abort(422, 'Only pending requisitions can be processed.');
            }

            if ($payload['action'] === 'reject') {
                $requisition->status = 'Rejected';
                $requisition->approved_by = optional($request->user('admin'))->id;
                $requisition->approved_at = now();
                $requisition->save();
                return;
            }

            $department = $requisition->department_id
                ? \App\Models\Department::query()->find($requisition->department_id)
                : \App\Models\Department::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($requisition->department))])->first();

            foreach ($requisition->items as $row) {
                $storeItem = StoreItem::query()->lockForUpdate()->find($row->store_item_id);
                if (!$storeItem) {
                    abort(422, 'One or more requisition items are invalid.');
                }

                $requestedQty = (float) $row->requested_qty;

                if (!$department && strtolower((string) $storeItem->category) === 'reagent') {
                    abort(422, 'Select a valid department before issuing reagent stock.');
                }

                if ($department) {
                    app(\App\Services\DepartmentInventoryTransferService::class)->issueFromCentral(
                        $storeItem->id,
                        $department->id,
                        $requestedQty,
                        $requisition->requisition_no,
                        optional($request->user('admin'))->id
                    );
                } else {
                    if ((float) $storeItem->current_stock < $requestedQty) {
                        abort(422, 'Insufficient stock for item: ' . $storeItem->item_name);
                    }

                    $storeItem->decrement('current_stock', $requestedQty);
                    StoreStockMovement::create([
                        'store_item_id' => $storeItem->id,
                        'movement_type' => 'decrease',
                        'quantity' => $requestedQty,
                        'unit_price' => $storeItem->unit_cost,
                        'reason' => 'Issued via requisition ' . $requisition->requisition_no,
                        'movement_date' => now()->toDateString(),
                        'reference_no' => $requisition->requisition_no,
                        'department' => $requisition->department,
                        'created_by' => optional($request->user('admin'))->id,
                    ]);
                }

                $row->issued_qty = $requestedQty;
                $row->save();
            }

            $requisition->status = 'Approved';
            $requisition->approved_by = optional($request->user('admin'))->id;
            $requisition->approved_at = now();
            $requisition->save();
        });

        return redirect()->route('backend.stock.requisitions')->with('success', 'Requisition processed successfully');
    }

    public function requisitionPrint($id)
    {
        $requisition = StoreRequisition::query()->with(['items.storeItem'])->findOrFail($id);

        return Inertia::render('Backend/StockManagement/RequisitionPrint', [
            'pageTitle' => 'Requisition Slip',
            'requisition' => $requisition,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
        ]);
    }

    public function requisitionIssueSlip($id)
    {
        $requisition = StoreRequisition::query()->with(['items.storeItem'])->findOrFail($id);

        return Inertia::render('Backend/StockManagement/RequisitionPrint', [
            'pageTitle' => 'Issue Slip',
            'requisition' => $requisition,
            'isIssueSlip' => true,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
        ]);
    }

    public function grns(Request $request)
    {
        $grns = StoreGrn::query()
            ->with(['items.storeItem', 'purchaseOrder'])
            ->orderByDesc('receive_date')
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 20))
            ->withQueryString();

        return Inertia::render('Backend/StockManagement/Grns', [
            'pageTitle' => 'Purchase Delivery (GRN)',
            'grns' => $grns,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
            'filters' => $request->only(['per_page']),
        ]);
    }

    public function createGrn(Request $request)
    {
        $items = StoreItem::query()
            ->where('status', 'Active')
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name', 'category', 'stock_behavior', 'unit', 'unit_cost']);

        $purchaseOrders = StorePurchaseOrder::query()
            ->whereIn('status', ['Ordered', 'Partially Received'])
            ->with(['supplier:id,name', 'items.storeItem:id,item_code,item_name,category,stock_behavior,unit'])
            ->orderByDesc('order_date')
            ->get();

        foreach ($purchaseOrders as $order) {
            foreach ($order->items as $line) {
                $line->setAttribute('received_quantity', (float) $line->grnItems()->sum('quantity'));
            }
        }

        $selectedOrderId = $request->integer('purchase_order_id');
        $selectedPurchaseOrder = $purchaseOrders->firstWhere('id', $selectedOrderId);

        return Inertia::render('Backend/StockManagement/GrnForm', [
            'pageTitle' => 'Record Purchase Delivery',
            'items' => $items,
            'purchaseOrders' => $purchaseOrders,
            'selectedPurchaseOrder' => $selectedPurchaseOrder,
        ]);
    }

    public function grnPrint($id)
    {
        $grn = StoreGrn::query()->with('items.storeItem')->findOrFail($id);

        return Inertia::render('Backend/StockManagement/GrnPrint', [
            'pageTitle' => 'GRN Receive Slip',
            'grn' => $grn,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
        ]);
    }

    public function storeGrn(Request $request)
    {
        $data = $request->validate([
            'store_purchase_order_id' => 'nullable|exists:store_purchase_orders,id',
            'supplier_name' => 'nullable|string|max:150',
            'invoice_no' => 'nullable|string|max:100',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|exists:store_items,id',
            'items.*.store_purchase_order_item_id' => 'nullable|exists:store_purchase_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date|after_or_equal:receive_date',
            'items.*.serial_number' => 'nullable|string|max:120|unique:store_item_lots,serial_number',
            'items.*.asset_tag' => 'nullable|string|max:120|unique:store_item_lots,asset_tag',
        ]);

        foreach (['serial_number' => 'Serial number', 'asset_tag' => 'Asset tag'] as $field => $label) {
            $identifiers = collect($data['items'])
                ->pluck($field)
                ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                ->filter()
                ->values();
            if ($identifiers->count() !== $identifiers->unique()->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Each reusable device must have a unique ' . $label . ' in the GRN.',
                ]);
            }
        }

        $grn = DB::transaction(function () use ($data, $request) {
            $purchaseOrder = null;
            if (!empty($data['store_purchase_order_id'])) {
                $purchaseOrder = StorePurchaseOrder::query()
                    ->with('supplier:id,name')
                    ->lockForUpdate()
                    ->findOrFail($data['store_purchase_order_id']);

                if (!in_array($purchaseOrder->status, ['Ordered', 'Partially Received'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'store_purchase_order_id' => 'This purchase order is not open for delivery.',
                    ]);
                }
            }

            $nextId = (int) (StoreGrn::query()->max('id') ?? 0) + 1;
            $grn = StoreGrn::create([
                'grn_no' => 'GRN-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT),
                'store_purchase_order_id' => $purchaseOrder?->id,
                'supplier_name' => $purchaseOrder?->supplier?->name ?? ($data['supplier_name'] ?? null),
                'invoice_no' => $data['invoice_no'] ?? null,
                'receive_date' => $data['receive_date'],
                'notes' => $data['notes'] ?? null,
                'received_by' => optional($request->user('admin'))->id,
            ]);

            foreach ($data['items'] as $lineIndex => $line) {
                $item = StoreItem::query()->lockForUpdate()->findOrFail($line['store_item_id']);
                $qty = (float) $line['quantity'];
                $cost = (float) $line['unit_cost'];

                $purchaseOrderItem = null;
                if ($purchaseOrder) {
                    $purchaseOrderItem = StorePurchaseOrderItem::query()
                        ->where('store_purchase_order_id', $purchaseOrder->id)
                        ->where('store_item_id', $item->id)
                        ->lockForUpdate()
                        ->find($line['store_purchase_order_item_id'] ?? 0);

                    if (!$purchaseOrderItem) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "items.{$lineIndex}.store_purchase_order_item_id" => 'Select an item from this purchase order.',
                        ]);
                    }

                    $receivedBefore = (float) $purchaseOrderItem->grnItems()->sum('quantity');
                    if (round($receivedBefore + $qty, 2) > (float) $purchaseOrderItem->quantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "items.{$lineIndex}.quantity" => 'Delivery quantity exceeds the remaining quantity on the purchase order.',
                        ]);
                    }
                } elseif (!empty($line['store_purchase_order_item_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$lineIndex}.store_purchase_order_item_id" => 'A purchase order item requires a linked purchase order.',
                    ]);
                }

                if (($item->stock_behavior ?? 'consumable') === 'reusable'
                    && ($qty !== 1.0 || (empty($line['serial_number']) && empty($line['asset_tag'])))) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$lineIndex}.serial_number" => 'Reusable devices must be received one asset per row with a serial number or asset tag.',
                    ]);
                }

                $batchNo = trim((string) ($line['batch_no'] ?? ''));
                if ($batchNo === '') {
                    $batchNo = $grn->grn_no . '-' . str_pad((string) ($lineIndex + 1), 3, '0', STR_PAD_LEFT);
                }

                $lot = app(\App\Services\InventoryLotService::class)->receive(
                    $item->id,
                    null,
                    $qty,
                    $cost,
                    $batchNo,
                    $line['expiry_date'] ?? null,
                    $line['serial_number'] ?? null,
                    $line['asset_tag'] ?? null,
                    $data['receive_date']
                );

                StoreGrnItem::create([
                    'store_grn_id' => $grn->id,
                    'store_purchase_order_item_id' => $purchaseOrderItem?->id,
                    'store_item_id' => $item->id,
                    'inventory_lot_id' => $lot->id,
                    'batch_no' => $batchNo,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'serial_number' => $line['serial_number'] ?? null,
                    'asset_tag' => $line['asset_tag'] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => $qty * $cost,
                ]);

                $item->increment('current_stock', $qty);
                $item->unit_cost = $cost;
                $item->save();

                StoreStockMovement::create([
                    'store_item_id' => $item->id,
                    'inventory_lot_id' => $lot->id,
                    'movement_type' => 'increase',
                    'quantity' => $qty,
                    'unit_price' => $cost,
                    'reason' => 'GRN receive ' . $grn->grn_no,
                    'movement_date' => $data['receive_date'],
                    'reference_no' => $grn->grn_no,
                    'department' => 'Store',
                    'created_by' => optional($request->user('admin'))->id,
                ]);
            }

            if ($purchaseOrder) {
                $this->refreshPurchaseOrderStatus($purchaseOrder);
                $receivedTotal = (float) $grn->items()->sum('line_total');
                if ($receivedTotal > 0) {
                    $inventoryCode = $this->ensureStoreAccount('INV', 'Inventory', 'asset', 'ASSET');
                    $payableCode = $this->ensureStoreAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY');
                    app(LedgerService::class)->recordTransaction(
                        [
                            ['account_code' => $inventoryCode, 'entry_type' => 'debit', 'amount' => $receivedTotal],
                            ['account_code' => $payableCode, 'entry_type' => 'credit', 'amount' => $receivedTotal],
                        ],
                        'Store purchase delivery ' . $purchaseOrder->purchase_order_no . ' / ' . $grn->grn_no,
                        $data['receive_date'],
                        'StoreGrn',
                        $grn->id,
                        optional($request->user('admin'))->id
                    );
                }
            }

            return $grn;
        });

        $destination = $grn->store_purchase_order_id
            ? redirect()->route('backend.stock.purchase-orders.show', $grn->store_purchase_order_id)
            : redirect()->route('backend.stock.grns');

        return $destination->with('success', 'Delivery recorded and central store stock updated successfully');
    }

    private function refreshPurchaseOrderStatus(StorePurchaseOrder $order): void
    {
        $received = (float) $order->items()->get()->sum(function ($line) {
            return (float) $line->grnItems()->sum('quantity');
        });
        $ordered = (float) $order->items()->sum('quantity');
        $order->status = $received >= $ordered ? 'Received' : ($received > 0 ? 'Partially Received' : 'Ordered');
        $order->save();
    }

    private function ensureStoreAccount(string $code, string $name, string $type, string $group): string
    {
        Account::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'account_group' => $group,
                'is_profit_loss' => false,
                'is_active' => true,
            ]
        );

        return $code;
    }

    public function monthlyClosingReport(Request $request)
    {
        $month = (string) ($request->validate([
            'month' => 'nullable|date_format:Y-m',
        ])['month'] ?? now()->format('Y-m'));
        if ($month > now()->format('Y-m')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'month' => 'Monthly closing is available only for the current or a previous month.',
            ]);
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $items = StoreItem::query()->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit', 'current_stock', 'unit_cost']);

        $movementTotals = StoreStockMovement::query()
            ->whereDate('movement_date', '>=', $start)
            ->groupBy('store_item_id')
            ->select('store_item_id')
            ->selectRaw(
                'SUM(CASE WHEN movement_date <= ? AND movement_type = ? THEN quantity ELSE 0 END) as received_qty',
                [$end, 'increase']
            )
            ->selectRaw(
                'SUM(CASE WHEN movement_date <= ? AND movement_type = ? THEN quantity ELSE 0 END) as issued_qty',
                [$end, 'decrease']
            )
            ->selectRaw(
                'SUM(CASE WHEN movement_type = ? THEN quantity ELSE -quantity END) as net_qty_since_start',
                ['increase']
            )
            ->selectRaw(
                'SUM(CASE WHEN movement_date <= ? AND movement_type = ? THEN quantity * unit_price ELSE 0 END) as received_value',
                [$end, 'increase']
            )
            ->selectRaw(
                'SUM(CASE WHEN movement_date <= ? AND movement_type = ? THEN quantity * unit_price ELSE 0 END) as issued_value',
                [$end, 'decrease']
            )
            ->get()
            ->keyBy('store_item_id');

        $rows = $items->map(function ($item) use ($movementTotals) {
            $movement = $movementTotals->get($item->id);
            $inQty = (float) ($movement?->received_qty ?? 0);
            $outQty = (float) ($movement?->issued_qty ?? 0);
            $unitCost = (float) $item->unit_cost;
            $openingQty = (float) $item->current_stock - (float) ($movement?->net_qty_since_start ?? 0);
            $closingQty = $openingQty + $inQty - $outQty;

            return [
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'unit' => $item->unit,
                'opening_qty' => $openingQty,
                'received_qty' => $inQty,
                'issued_qty' => $outQty,
                'closing_qty' => $closingQty,
                'unit_cost' => $unitCost,
                'closing_value' => $closingQty * $unitCost,
                'received_value' => (float) ($movement?->received_value ?? 0),
                'issued_value' => (float) ($movement?->issued_value ?? 0),
            ];
        })->values();

        if ($request->get('export') === 'csv') {
            return $this->exportMonthlyClosingCsv($rows, $month);
        }

        return Inertia::render('Backend/StockManagement/MonthlyClosing', [
            'pageTitle' => 'Monthly Closing & Valuation',
            'month' => $month,
            'currentMonth' => now()->format('Y-m'),
            'rows' => $rows,
            'summary' => [
                'total_opening_qty' => $rows->sum('opening_qty'),
                'total_closing_value' => $rows->sum('closing_value'),
                'total_received_qty' => $rows->sum('received_qty'),
                'total_issued_qty' => $rows->sum('issued_qty'),
                'total_closing_qty' => $rows->sum('closing_qty'),
                'total_received_value' => $rows->sum('received_value'),
                'total_issued_value' => $rows->sum('issued_value'),
            ],
        ]);
    }

    private function exportMonthlyClosingCsv($rows, string $month): StreamedResponse
    {
        $fileName = 'store-monthly-closing-' . $month . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Item Code', 'Item Name', 'Unit', 'Opening Qty', 'Received Qty', 'Received Value', 'Issued / Used Qty', 'Issued Value', 'Closing Qty', 'Unit Cost', 'Closing Value']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['item_code'],
                    $row['item_name'],
                    $row['unit'],
                    number_format((float) $row['opening_qty'], 2, '.', ''),
                    number_format((float) $row['received_qty'], 2, '.', ''),
                    number_format((float) $row['received_value'], 2, '.', ''),
                    number_format((float) $row['issued_qty'], 2, '.', ''),
                    number_format((float) $row['issued_value'], 2, '.', ''),
                    number_format((float) $row['closing_qty'], 2, '.', ''),
                    number_format((float) $row['unit_cost'], 2, '.', ''),
                    number_format((float) $row['closing_value'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
