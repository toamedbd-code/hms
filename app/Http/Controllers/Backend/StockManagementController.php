<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StoreItem;
use App\Models\StoreGrn;
use App\Models\StoreGrnItem;
use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\StoreStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class StockManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:stock-report-list');
        $this->middleware('permission:stock-report-list-create', ['only' => ['createAdjustment', 'storeAdjustment', 'createRequisition', 'storeRequisition', 'createItem', 'storeItem', 'createGrn', 'storeGrn']]);
        $this->middleware('permission:stock-report-list-edit', ['only' => ['requisitionDecision']]);
    }

    public function index()
    {
        $items = StoreItem::query()
            ->select('id', 'item_code', 'item_name', 'category', 'unit', 'current_stock', 'unit_cost', 'reorder_level', 'status')
            ->orderBy('item_name')
            ->paginate((int) request()->get('per_page', 20))
            ->withQueryString();

        $summary = [
            'total_items' => StoreItem::query()->count(),
            'active_items' => StoreItem::query()->where('status', 'Active')->count(),
            'total_quantity' => (float) StoreItem::query()->sum('current_stock'),
            'inventory_value' => (float) StoreItem::query()->selectRaw('SUM(current_stock * unit_cost) as total')->value('total'),
            'low_stock_items' => StoreItem::query()->whereColumn('current_stock', '<=', 'reorder_level')->where('status', 'Active')->count(),
        ];

        return Inertia::render('Backend/StockManagement/Index', [
            'pageTitle' => 'Store Dashboard',
            'items' => $items,
            'summary' => $summary,
            'filters' => request()->only(['per_page']),
        ]);
    }

    public function adjustments()
    {
        $adjustments = StoreStockMovement::with('storeItem')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate((int) request()->get('per_page', 20))
            ->withQueryString();

        return Inertia::render('Backend/StockManagement/Adjustments', [
            'pageTitle' => 'Store Adjustments',
            'adjustments' => $adjustments,
            'filters' => request()->only(['per_page']),
        ]);
    }

    public function createAdjustment()
    {
        $items = StoreItem::where('status', 'Active')
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name', 'current_stock', 'unit_cost']);

        return Inertia::render('Backend/StockManagement/AdjustmentForm', [
            'pageTitle' => 'Store Adjustment Entry',
            'items' => $items,
            'isEdit' => false,
        ]);
    }

    public function createItem()
    {
        return Inertia::render('Backend/StockManagement/ItemForm', [
            'pageTitle' => 'Store Item Setup',
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'nullable|string|max:50|unique:store_items,item_code',
            'item_name' => 'required|string|max:255',
            'category' => 'nullable|string|max:120',
            'unit' => 'required|string|max:50',
            'reorder_level' => 'required|numeric|min:0',
            'current_stock' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'status' => 'required|in:Active,Inactive',
            'notes' => 'nullable|string',
        ]);

        if (empty($data['item_code'])) {
            $data['item_code'] = 'ST-' . str_pad((string) (StoreItem::query()->max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        $item = StoreItem::create($data);

        if ((float) $item->current_stock > 0) {
            StoreStockMovement::create([
                'store_item_id' => $item->id,
                'movement_type' => 'increase',
                'quantity' => $item->current_stock,
                'unit_price' => $item->unit_cost,
                'reason' => 'Opening stock',
                'movement_date' => now()->toDateString(),
                'reference_no' => 'OPEN-' . $item->id,
            ]);
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
            'reason' => 'required|string',
            'movement_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($request) {
            $item = StoreItem::findOrFail($request->store_item_id);
            $quantity = (float) $request->quantity;
            $currentStock = (float) $item->current_stock;

            if ($request->adjustment_type === 'decrease' && $currentStock < $quantity) {
                abort(422, 'Adjustment quantity cannot be greater than current stock.');
            }

            StoreStockMovement::create([
                'store_item_id' => $request->store_item_id,
                'movement_type' => $request->adjustment_type,
                'quantity' => $quantity,
                'unit_price' => $request->unit_price ?? $item->unit_cost,
                'reason' => $request->reason,
                'movement_date' => $request->movement_date,
                'reference_no' => $request->reference_no,
                'department' => $request->department,
            ]);

            if ($request->adjustment_type === 'increase') {
                $item->increment('current_stock', $quantity);
                if ($request->filled('unit_price')) {
                    $item->unit_cost = (float) $request->unit_price;
                    $item->save();
                }
            } else {
                $item->decrement('current_stock', $quantity);
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

    public function requisitions(Request $request)
    {
        $query = StoreRequisition::query()->with(['items.storeItem'])->orderByDesc('id');

        if ($request->filled('status') && in_array($request->status, ['Pending', 'Approved', 'Rejected'], true)) {
            $query->where('status', $request->status);
        }

        $requisitions = $query->paginate((int) $request->get('per_page', 20))->withQueryString();

        return Inertia::render('Backend/StockManagement/Requisitions', [
            'pageTitle' => 'Department Requisitions',
            'requisitions' => $requisitions,
            'filters' => $request->only(['status', 'per_page']),
        ]);
    }

    public function createRequisition()
    {
        $items = StoreItem::query()
            ->where('status', 'Active')
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name', 'unit', 'current_stock']);

        return Inertia::render('Backend/StockManagement/RequisitionForm', [
            'pageTitle' => 'Create Department Requisition',
            'items' => $items,
        ]);
    }

    public function storeRequisition(Request $request)
    {
        $data = $request->validate([
            'department' => 'required|string|max:100',
            'needed_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|exists:store_items,id',
            'items.*.requested_qty' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            $nextId = (int) (StoreRequisition::query()->max('id') ?? 0) + 1;
            $requisition = StoreRequisition::create([
                'requisition_no' => 'SRQ-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT),
                'department' => $data['department'],
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

            foreach ($requisition->items as $row) {
                $storeItem = $row->storeItem;
                if (!$storeItem) {
                    abort(422, 'One or more requisition items are invalid.');
                }

                $requestedQty = (float) $row->requested_qty;
                $currentStock = (float) $storeItem->current_stock;

                if ($currentStock < $requestedQty) {
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
        ]);
    }

    public function requisitionIssueSlip($id)
    {
        $requisition = StoreRequisition::query()->with(['items.storeItem'])->findOrFail($id);

        return Inertia::render('Backend/StockManagement/RequisitionPrint', [
            'pageTitle' => 'Issue Slip',
            'requisition' => $requisition,
            'isIssueSlip' => true,
        ]);
    }

    public function grns(Request $request)
    {
        $grns = StoreGrn::query()
            ->with('items.storeItem')
            ->orderByDesc('receive_date')
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 20))
            ->withQueryString();

        return Inertia::render('Backend/StockManagement/Grns', [
            'pageTitle' => 'GRN / Purchase Receive',
            'grns' => $grns,
            'filters' => $request->only(['per_page']),
        ]);
    }

    public function createGrn()
    {
        $items = StoreItem::query()
            ->where('status', 'Active')
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name', 'unit', 'unit_cost']);

        return Inertia::render('Backend/StockManagement/GrnForm', [
            'pageTitle' => 'Create GRN Receive',
            'items' => $items,
        ]);
    }

    public function grnPrint($id)
    {
        $grn = StoreGrn::query()->with('items.storeItem')->findOrFail($id);

        return Inertia::render('Backend/StockManagement/GrnPrint', [
            'pageTitle' => 'GRN Receive Slip',
            'grn' => $grn,
        ]);
    }

    public function storeGrn(Request $request)
    {
        $data = $request->validate([
            'supplier_name' => 'nullable|string|max:150',
            'invoice_no' => 'nullable|string|max:100',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.store_item_id' => 'required|exists:store_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $request) {
            $nextId = (int) (StoreGrn::query()->max('id') ?? 0) + 1;
            $grn = StoreGrn::create([
                'grn_no' => 'GRN-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT),
                'supplier_name' => $data['supplier_name'] ?? null,
                'invoice_no' => $data['invoice_no'] ?? null,
                'receive_date' => $data['receive_date'],
                'notes' => $data['notes'] ?? null,
                'received_by' => optional($request->user('admin'))->id,
            ]);

            foreach ($data['items'] as $line) {
                $item = StoreItem::query()->lockForUpdate()->findOrFail($line['store_item_id']);
                $qty = (float) $line['quantity'];
                $cost = (float) $line['unit_cost'];

                StoreGrnItem::create([
                    'store_grn_id' => $grn->id,
                    'store_item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => $qty * $cost,
                ]);

                $item->increment('current_stock', $qty);
                $item->unit_cost = $cost;
                $item->save();

                StoreStockMovement::create([
                    'store_item_id' => $item->id,
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
        });

        return redirect()->route('backend.stock.grns')->with('success', 'GRN received and stock updated successfully');
    }

    public function monthlyClosingReport(Request $request)
    {
        $month = (string) $request->get('month', now()->format('Y-m'));
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $items = StoreItem::query()->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit', 'current_stock', 'unit_cost']);

        $rows = $items->map(function ($item) use ($start, $end) {
            $inQty = (float) StoreStockMovement::query()
                ->where('store_item_id', $item->id)
                ->where('movement_type', 'increase')
                ->whereBetween('movement_date', [$start, $end])
                ->sum('quantity');

            $outQty = (float) StoreStockMovement::query()
                ->where('store_item_id', $item->id)
                ->where('movement_type', 'decrease')
                ->whereBetween('movement_date', [$start, $end])
                ->sum('quantity');

            $closingQty = (float) $item->current_stock;
            $openingQty = $closingQty - $inQty + $outQty;
            $unitCost = (float) $item->unit_cost;

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
            ];
        })->values();

        if ($request->get('export') === 'csv') {
            return $this->exportMonthlyClosingCsv($rows, $month);
        }

        return Inertia::render('Backend/StockManagement/MonthlyClosing', [
            'pageTitle' => 'Monthly Closing & Valuation',
            'month' => $month,
            'rows' => $rows,
            'summary' => [
                'total_closing_value' => $rows->sum('closing_value'),
                'total_received_qty' => $rows->sum('received_qty'),
                'total_issued_qty' => $rows->sum('issued_qty'),
            ],
        ]);
    }

    private function exportMonthlyClosingCsv($rows, string $month): StreamedResponse
    {
        $fileName = 'store-monthly-closing-' . $month . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Item Code', 'Item Name', 'Unit', 'Opening Qty', 'Received Qty', 'Issued Qty', 'Closing Qty', 'Unit Cost', 'Closing Value']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['item_code'],
                    $row['item_name'],
                    $row['unit'],
                    number_format((float) $row['opening_qty'], 2, '.', ''),
                    number_format((float) $row['received_qty'], 2, '.', ''),
                    number_format((float) $row['issued_qty'], 2, '.', ''),
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
