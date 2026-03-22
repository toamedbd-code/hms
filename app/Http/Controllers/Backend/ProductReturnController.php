<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\MedicineSupplier;
use App\Models\MedicineInventory;
use App\Models\PharmacyBill;
use App\Models\Billing;
use App\Models\Expense;
use App\Models\ExpenseHead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ProductReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:product-return-list');
        $this->middleware('permission:product-return-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:product-return-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:product-return-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:product-return-list-status', ['only' => ['approve', 'process']]);
    }

    public function index()
    {
        $timezone = config('app.timezone', 'Asia/Dhaka');
        $search = trim((string) request()->get('search', ''));
        $numOfData = (int) request()->get('numOfData', 10);
        if ($numOfData <= 0) {
            $numOfData = 10;
        }

        /** @var LengthAwarePaginator $paginator */
        $query = ProductReturn::with('supplier');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('return_number', 'like', '%' . $search . '%')
                    ->orWhere('return_type', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($numOfData)->withQueryString();

        $formattedRows = collect($paginator->items())->map(function ($return) use ($timezone) {
                $datePart = optional($return->return_date)
                    ?->timezone($timezone)
                    ->format('d M Y');

                $timePart = optional($return->created_at)
                    ?->timezone($timezone)
                    ->format('h:i A');

                $return->return_datetime_local = trim(collect([$datePart, $timePart])->filter()->implode(', '));

                return $return;
            });

        $returns = $paginator->toArray();
        $returns['data'] = $formattedRows->values()->toArray();

        return Inertia::render('Backend/ProductReturn/Index', [
            'returns' => $returns,
            'filters' => [
                'search' => $search,
                'numOfData' => $numOfData,
            ],
        ]);
    }

    public function create()
    {
        $sourceBillNo = trim((string) request()->get('source_bill_no', ''));
        $sourceBill = null;
        $sourceBillingId = null;
        $sourceMedicineIds = [];
        $sourceCustomerName = '';
        $sourceBillItems = [];

        if ($sourceBillNo !== '') {
            $sourceBill = PharmacyBill::with('patient')->where('bill_no', $sourceBillNo)->first();

            if ($sourceBill) {
                $sourceCustomerName = trim((string) (
                    $sourceBill->patient?->name
                    ?? trim(($sourceBill->patient?->first_name ?? '') . ' ' . ($sourceBill->patient?->last_name ?? ''))
                    ?: ''
                ));

                $sourceBillingId = Billing::query()
                    ->where('bill_number', $sourceBillNo)
                    ->orWhere('case_number', $sourceBill->case_id)
                    ->orderByDesc('id')
                    ->value('id');
            }

            if ($sourceBill && $sourceBill->products) {
                $products = is_string($sourceBill->products)
                    ? json_decode($sourceBill->products, true)
                    : $sourceBill->products;

                if (is_array($products)) {
                    $sourceBillItems = collect($products)
                        ->map(function ($item) {
                            return [
                                'medicine_inventory_id' => (int) ($item['productId'] ?? 0),
                                'medicine_name' => (string) ($item['productName'] ?? ''),
                                'billed_quantity' => (float) ($item['quantity'] ?? 0),
                                'unit_price' => (float) ($item['rate'] ?? 0),
                            ];
                        })
                        ->filter(fn ($item) => $item['medicine_inventory_id'] > 0 && $item['billed_quantity'] > 0)
                        ->groupBy('medicine_inventory_id')
                        ->map(function ($items) {
                            $first = $items->first();
                            return [
                                'medicine_inventory_id' => $first['medicine_inventory_id'],
                                'medicine_name' => $first['medicine_name'],
                                'billed_quantity' => (float) $items->sum('billed_quantity'),
                                'unit_price' => (float) $first['unit_price'],
                            ];
                        })
                        ->values()
                        ->all();

                    $sourceMedicineIds = collect($products)
                        ->pluck('productId')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                }
            }
        }

        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $medicinesQuery = MedicineInventory::where('status', 'Active');

        if ($sourceBillNo === '') {
            $medicinesQuery->whereRaw('1 = 0');
        } else {
            if (!empty($sourceMedicineIds)) {
                $medicinesQuery->whereIn('id', $sourceMedicineIds);
            } else {
                $medicinesQuery->whereRaw('1 = 0');
            }
        }

        $medicines = $medicinesQuery->get();

        return Inertia::render('Backend/ProductReturn/Form', [
            'suppliers' => $suppliers,
            'medicines' => $medicines,
            'sourceBillNo' => $sourceBillNo,
            'sourceBillFound' => (bool) $sourceBill,
            'sourceBillingId' => $sourceBillingId,
            'sourceCustomerName' => $sourceCustomerName,
            'sourceBillItems' => $sourceBillItems,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_type' => 'required|in:customer',
            'supplier_id' => 'nullable|exists:medicinesuppliers,id',
            'source_bill_no' => 'required|exists:pharmacybills,bill_no',
            'billing_id' => 'nullable|exists:billings,id',
            'customer_name' => 'nullable|string|max:255',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_inventory_id' => 'required|exists:medicineinventories,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good,damaged,expired',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $sourceBill = PharmacyBill::where('bill_no', $request->source_bill_no)->first();
        if (!$sourceBill || !$sourceBill->products) {
            return redirect()->back()->withErrors([
                'source_bill_no' => 'Customer bill not found or no medicines were billed in this invoice.',
            ])->withInput();
        }

        $billProducts = is_string($sourceBill->products)
            ? json_decode($sourceBill->products, true)
            : $sourceBill->products;

        $billedQtyByMedicine = collect(is_array($billProducts) ? $billProducts : [])
            ->groupBy(fn ($item) => (int) ($item['productId'] ?? 0))
            ->map(fn ($items) => (float) $items->sum(fn ($item) => (float) ($item['quantity'] ?? 0)))
            ->filter(fn ($qty, $medicineId) => (int) $medicineId > 0 && $qty > 0);

        $alreadyReturnedByMedicine = ReturnItem::query()
            ->whereHas('productReturn', function ($query) use ($request) {
                $query->where('source_bill_no', $request->source_bill_no);
            })
            ->selectRaw('medicine_inventory_id, SUM(quantity) as returned_qty')
            ->groupBy('medicine_inventory_id')
            ->pluck('returned_qty', 'medicine_inventory_id');

        foreach ($request->items as $index => $item) {
            $medicineId = (int) ($item['medicine_inventory_id'] ?? 0);
            $requestedQty = (float) ($item['quantity'] ?? 0);
            $billedQty = (float) ($billedQtyByMedicine[$medicineId] ?? 0);
            $alreadyReturnedQty = (float) ($alreadyReturnedByMedicine[$medicineId] ?? 0);
            $remainingQty = max(0, $billedQty - $alreadyReturnedQty);

            if ($billedQty <= 0) {
                return redirect()->back()->withErrors([
                    "items.$index.medicine_inventory_id" => 'Selected medicine was not billed for this customer bill.',
                ])->withInput();
            }

            if ($requestedQty > $remainingQty) {
                return redirect()->back()->withErrors([
                    "items.$index.quantity" => 'Return quantity exceeds billed remaining quantity for this medicine.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($request) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $return = ProductReturn::create([
                'return_number' => 'RET-' . strtoupper(Str::random(8)),
                'return_type' => 'customer',
                'supplier_id' => $request->supplier_id,
                'source_bill_no' => $request->source_bill_no,
                'billing_id' => $request->billing_id,
                'customer_name' => $request->customer_name,
                'return_date' => $request->return_date,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                ReturnItem::create([
                    'product_return_id' => $return->id,
                    'medicine_inventory_id' => $item['medicine_inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['quantity'] * $item['unit_price'],
                    'condition' => $item['condition'],
                ]);

                // Update inventory quantity
                $medicine = MedicineInventory::find($item['medicine_inventory_id']);
                $medicine->decrement('medicine_quantity', $item['quantity']);
            }
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return created successfully');
    }

    public function show(ProductReturn $productreturn)
    {
        $productreturn->load('supplier', 'returnItems.medicineInventory');
        return Inertia::render('Backend/ProductReturn/Show', [
            'return' => $productreturn,
        ]);
    }

    public function edit(ProductReturn $productreturn)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $medicines = MedicineInventory::where('status', 'Active')->get();
        $productreturn->load('returnItems');
        return Inertia::render('Backend/ProductReturn/Form', [
            'return' => $productreturn,
            'returnItems' => $productreturn->returnItems,
            'suppliers' => $suppliers,
            'medicines' => $medicines,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, ProductReturn $productreturn)
    {
        $request->validate([
            'return_type' => 'required|in:customer,supplier',
            'supplier_id' => 'nullable|exists:medicinesuppliers,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_inventory_id' => 'required|exists:medicineinventories,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good,damaged,expired',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $productreturn) {
            // Restore previous quantities
            foreach ($productreturn->returnItems as $oldItem) {
                $medicine = MedicineInventory::find($oldItem->medicine_inventory_id);
                $medicine->increment('medicine_quantity', $oldItem->quantity);
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $productreturn->update([
                'return_type' => $request->return_type,
                'supplier_id' => $request->supplier_id,
                'return_date' => $request->return_date,
                'total_amount' => $totalAmount,
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]);

            // Delete existing items and recreate
            $productreturn->returnItems()->delete();
            foreach ($request->items as $item) {
                ReturnItem::create([
                    'product_return_id' => $productreturn->id,
                    'medicine_inventory_id' => $item['medicine_inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['quantity'] * $item['unit_price'],
                    'condition' => $item['condition'],
                ]);

                // Update inventory quantity
                $medicine = MedicineInventory::find($item['medicine_inventory_id']);
                $medicine->decrement('medicine_quantity', $item['quantity']);
            }
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return updated successfully');
    }

    public function destroy(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            // Restore quantities
            foreach ($productreturn->returnItems as $item) {
                $medicine = MedicineInventory::find($item->medicine_inventory_id);
                $medicine->increment('medicine_quantity', $item->quantity);
            }
            $productreturn->delete();
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return deleted successfully');
    }

    // Approve return
    public function approve(ProductReturn $productreturn)
    {
        $productreturn->update(['status' => 'approved']);
        return redirect()->back()->with('success', 'Return approved successfully');
    }

    // Process return
    public function process(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            $productreturn->update(['status' => 'processed']);

            $expenseBillNumber = 'RET-EXP-' . $productreturn->id;
            $alreadyLogged = Expense::query()->where('bill_number', $expenseBillNumber)->exists();

            if ($alreadyLogged || (float) $productreturn->total_amount <= 0) {
                return;
            }

            $expenseHead = ExpenseHead::query()
                ->where('name', 'Product Return')
                ->orWhere('name', 'Return Expense')
                ->orWhere('name', 'Refund')
                ->first();

            if (!$expenseHead) {
                $expenseHead = ExpenseHead::query()->first();
            }

            if (!$expenseHead) {
                $expenseHead = ExpenseHead::create([
                    'name' => 'Product Return',
                ]);
            }

            Expense::create([
                'expense_header_id' => $expenseHead->id,
                'bill_number' => $expenseBillNumber,
                'name' => 'Product return payment - ' . $productreturn->return_number,
                'description' => 'Auto-created from product return processing. Return No: ' . $productreturn->return_number . ', Source Bill: ' . ($productreturn->source_bill_no ?? 'N/A'),
                'amount' => (float) $productreturn->total_amount,
                'date' => optional($productreturn->return_date)->toDateString() ?? now()->toDateString(),
                'status' => 'Active',
            ]);
        });

        return redirect()->back()->with('success', 'Return processed successfully');
    }
}
