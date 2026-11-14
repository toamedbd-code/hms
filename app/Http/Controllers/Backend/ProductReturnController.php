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
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

class ProductReturnController extends Controller
{
    private function resolveReturnType(?string $returnType): string
    {
        $normalized = strtolower(trim((string) $returnType));
        return in_array($normalized, ['customer', 'supplier'], true) ? $normalized : 'supplier';
    }

    private function resolveSourceBill(?string $searchValue): ?PharmacyBill
    {
        $value = trim((string) $searchValue);
        if ($value === '') {
            return null;
        }

        return PharmacyBill::with('patient')
            ->where('bill_no', $value)
            ->orWhere('pharmacy_no', $value)
            ->orWhere('case_id', $value)
            ->first();
    }

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:product-return-list');
        $this->middleware('permission:product-return-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:product-return-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:product-return-list-delete', ['only' => ['destroy']]);
        $this->middleware(function (Request $request, \Closure $next) {
            $admin = $request->user('admin');
            abort_unless(
                $admin && ($admin->hasAnyRole(['Admin', 'developer']) || $admin->can('product-return-list-status')),
                403
            );

            return $next($request);
        })->only(['approve', 'process', 'reopen', 'pay']);
    }

    public function index()
    {
        $timezone = config('app.timezone', 'Asia/Dhaka');
        $search = trim((string) request()->get('search', ''));
        $requestedReturnType = strtolower(trim((string) request()->get('return_type', 'all')));
        $returnType = in_array($requestedReturnType, ['all', 'customer', 'supplier'], true)
            ? $requestedReturnType
            : 'all';
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');
        $numOfData = (int) request()->get('numOfData', 10);
        if ($numOfData <= 0) {
            $numOfData = 10;
        }

        /** @var LengthAwarePaginator $paginator */
        $query = ProductReturn::with('supplier');
        if ($returnType !== 'all') {
            $query->where('return_type', $returnType);
        }

        if ($dateFrom) {
            $query->whereDate('return_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('return_date', '<=', $dateTo);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('return_number', 'like', '%' . $search . '%')
                    ->orWhere('return_type', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('source_bill_no', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
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
                $return->due_amount = max(
                    0,
                    round((float) $return->total_amount - (float) ($return->paid_amount ?? 0), 2)
                );

                return $return;
            });

        $returns = $paginator->toArray();
        $returns['data'] = $formattedRows->values()->toArray();

        return Inertia::render('Backend/ProductReturn/Index', [
            'returns' => $returns,
            'returnType' => $returnType,
            'filters' => [
                'search' => $search,
                'numOfData' => $numOfData,
                'return_type' => $returnType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function printList(Request $request)
    {
        $validated = $request->validate([
            'return_type' => 'nullable|in:all,customer,supplier',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        $returnType = $validated['return_type'] ?? 'all';
        $query = ProductReturn::with(['supplier', 'returnItems.medicineInventory']);

        if ($returnType !== 'all') {
            $query->where('return_type', $returnType);
        }
        if (!empty($validated['date_from'])) {
            $query->whereDate('return_date', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('return_date', '<=', $validated['date_to']);
        }
        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('return_number', 'like', '%' . $search . '%')
                    ->orWhere('return_type', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('source_bill_no', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery
                        ->where('name', 'like', '%' . $search . '%'));
            });
        }

        $returns = $query->orderByDesc('id')->get();
        $webSetting = WebSetting::query()
            ->where('status', 'Active')
            ->orderByDesc('id')
            ->first();
        $html = view('backend.exports.product_return_list_pdf', [
            'returns' => $returns,
            'returnType' => $returnType,
            'dateFrom' => $validated['date_from'] ?? null,
            'dateTo' => $validated['date_to'] ?? null,
            'generatedAt' => now()->timezone(config('app.timezone', 'Asia/Dhaka')),
            'hospitalName' => $webSetting?->company_name ?? config('app.name', 'HMS'),
            'hospitalAddress' => $webSetting?->address ?? '',
        ])->render();

        $tempDirectory = storage_path('app/tmp');
        File::ensureDirectoryExists($tempDirectory);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'default_font' => 'dejavusanscondensed',
            'default_font_size' => 9,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'tempDir' => $tempDirectory,
        ]);
        $pdf->SetTitle('Product Return List');
        $pdf->SetAuthor(config('app.name'));
        $pdf->WriteHTML($html);

        $fileName = 'product-returns-' . now()->format('Ymd-His') . '.pdf';
        $pdfContent = $pdf->Output('', 'S');

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Content-Length' => strlen($pdfContent),
            'X-PDF-Page-Count' => $pdf->page,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function create()
    {
        $returnType = $this->resolveReturnType(request()->get('return_type', 'supplier'));
        $searchBillInput = trim((string) request()->get('source_bill_no', ''));
        $sourceBillNo = $searchBillInput;
        $sourceBill = null;
        $sourceBillingId = null;
        $sourceMedicineIds = [];
        $sourceCustomerName = '';
        $sourceBillItems = [];

        if ($returnType === 'customer' && $searchBillInput !== '') {
            $sourceBill = $this->resolveSourceBill($searchBillInput);

            if ($sourceBill) {
                $sourceBillNo = (string) ($sourceBill->bill_no ?? $searchBillInput);
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
                $sourceBillItems = $this->normalizeCustomerBillProducts($sourceBill->products);
                $sourceMedicineIds = collect($sourceBillItems)
                    ->pluck('medicine_inventory_id')
                    ->all();
            }
        }

        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $medicinesQuery = MedicineInventory::query();

        if ($returnType === 'customer') {
            if ($sourceBillNo === '') {
                $medicinesQuery->whereRaw('1 = 0');
            } else {
                if (!empty($sourceMedicineIds)) {
                    $medicinesQuery->whereIn('id', $sourceMedicineIds);
                } else {
                    $medicinesQuery->whereRaw('1 = 0');
                }
            }
        } else {
            $medicinesQuery->where('status', 'Active');
        }

        $medicines = $medicinesQuery->get();

        return Inertia::render('Backend/ProductReturn/Form', [
            'returnType' => $returnType,
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
        $returnType = $this->resolveReturnType($request->input('return_type', 'supplier'));

        if ($returnType === 'supplier') {
            $request->validate([
                'return_type' => 'required|in:supplier',
                'supplier_id' => 'required|exists:medicinesuppliers,id',
                'return_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.medicine_inventory_id' => 'required|exists:medicineinventories,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.condition' => 'required|in:damaged',
                'reason' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            DB::transaction(function () use ($request) {
                $totalAmount = 0;
                foreach ($request->items as $item) {
                    $totalAmount += $item['quantity'] * $item['unit_price'];
                }

                $return = ProductReturn::create([
                    'return_number' => 'RET-' . strtoupper(Str::random(8)),
                    'return_type' => 'supplier',
                    'supplier_id' => $request->supplier_id,
                    'source_bill_no' => null,
                    'billing_id' => null,
                    'customer_name' => null,
                    'return_date' => $request->return_date,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'payment_status' => 'unpaid',
                    'status' => 'pending',
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                ]);

                foreach ($request->items as $item) {
                    $returnItem = ReturnItem::create([
                        'product_return_id' => $return->id,
                        'medicine_inventory_id' => $item['medicine_inventory_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_amount' => $item['quantity'] * $item['unit_price'],
                        'condition' => 'damaged',
                    ]);

                    $this->applyReturnInventoryChange($returnItem, 'supplier');
                }
            });

            return redirect()->route('backend.productreturn.index')->with('success', 'Supplier damaged return created successfully');
        }

        $request->validate([
            'return_type' => 'required|in:customer',
            'supplier_id' => 'nullable|exists:medicinesuppliers,id',
            'source_bill_no' => 'required|string|max:255',
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

        $sourceBill = $this->resolveSourceBill($request->source_bill_no);
        if (!$sourceBill || !$sourceBill->products) {
            return redirect()->back()->withErrors([
                'source_bill_no' => 'Customer bill not found or no medicines were billed in this invoice.',
            ])->withInput();
        }

        $canonicalBillNo = (string) ($sourceBill->bill_no ?? $request->source_bill_no);

        $billedQtyByMedicine = collect($this->normalizeCustomerBillProducts($sourceBill->products))
            ->mapWithKeys(fn ($item) => [
                $item['medicine_inventory_id'] => $item['billed_quantity'],
            ]);

        $alreadyReturnedByMedicine = ReturnItem::query()
            ->whereHas('productReturn', function ($query) use ($canonicalBillNo) {
                $query->where('source_bill_no', $canonicalBillNo);
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

        DB::transaction(function () use ($request, $canonicalBillNo) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $return = ProductReturn::create([
                'return_number' => 'RET-' . strtoupper(Str::random(8)),
                'return_type' => 'customer',
                'supplier_id' => $request->supplier_id,
                'source_bill_no' => $canonicalBillNo,
                'billing_id' => $request->billing_id,
                'customer_name' => $request->customer_name,
                'return_date' => $request->return_date,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $returnItem = ReturnItem::create([
                    'product_return_id' => $return->id,
                    'medicine_inventory_id' => $item['medicine_inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['quantity'] * $item['unit_price'],
                    'condition' => $item['condition'],
                ]);

                $this->applyReturnInventoryChange($returnItem, 'customer');
            }
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return created successfully');
    }

    private function normalizeCustomerBillProducts($products): array
    {
        if (is_string($products)) {
            $products = json_decode($products, true);
        }

        if (!is_array($products)) {
            return [];
        }

        return collect($products)
            ->map(function ($item) {
                return [
                    'medicine_inventory_id' => (int) ($item['productId'] ?? $item['medicine_id'] ?? $item['medicine_inventory_id'] ?? 0),
                    'medicine_name' => (string) ($item['productName'] ?? $item['medicine_name'] ?? ''),
                    'billed_quantity' => (float) ($item['quantity'] ?? 0),
                    'unit_price' => (float) ($item['rate'] ?? $item['unit_price'] ?? 0),
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
    }

    private function applyReturnInventoryChange(ReturnItem $item, string $returnType, bool $reverse = false): void
    {
        if ($returnType === 'customer' && strtolower((string) $item->condition) !== 'good') {
            return;
        }

        $medicine = MedicineInventory::query()->findOrFail($item->medicine_inventory_id);
        $shouldIncrement = $returnType === 'supplier' ? $reverse : !$reverse;

        if ($shouldIncrement) {
            $medicine->increment('medicine_quantity', $item->quantity);
            return;
        }

        $medicine->decrement('medicine_quantity', $item->quantity);
    }

    public function show(ProductReturn $productreturn)
    {
        $productreturn->load('supplier', 'returnItems.medicineInventory');
        $admin = request()->user('admin');

        return Inertia::render('Backend/ProductReturn/Show', [
            'return' => $productreturn,
            'canManageStatus' => $admin
                && ($admin->hasAnyRole(['Admin', 'developer']) || $admin->can('product-return-list-status')),
        ]);
    }

    public function edit(ProductReturn $productreturn)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $medicines = MedicineInventory::where('status', 'Active')->get();
        $productreturn->load('returnItems');
        return Inertia::render('Backend/ProductReturn/Form', [
            'returnType' => $productreturn->return_type,
            'return' => $productreturn,
            'returnItems' => $productreturn->returnItems,
            'suppliers' => $suppliers,
            'medicines' => $medicines,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, ProductReturn $productreturn)
    {
        $returnType = $this->resolveReturnType($request->input('return_type', $productreturn->return_type));

        $request->validate([
            'return_type' => 'required|in:customer,supplier',
            'supplier_id' => $returnType === 'supplier' ? 'required|exists:medicinesuppliers,id' : 'nullable|exists:medicinesuppliers,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_inventory_id' => 'required|exists:medicineinventories,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => $returnType === 'supplier' ? 'required|in:damaged' : 'required|in:good,damaged,expired',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $previousReturnType = $productreturn->return_type;

        DB::transaction(function () use ($request, $productreturn, $returnType, $previousReturnType) {
            foreach ($productreturn->returnItems as $oldItem) {
                $this->applyReturnInventoryChange($oldItem, $previousReturnType, true);
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $productreturn->update([
                'return_type' => $returnType,
                'supplier_id' => $request->supplier_id,
                'return_date' => $request->return_date,
                'total_amount' => $totalAmount,
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]);

            // Delete existing items and recreate
            $productreturn->returnItems()->delete();
            foreach ($request->items as $item) {
                $returnItem = ReturnItem::create([
                    'product_return_id' => $productreturn->id,
                    'medicine_inventory_id' => $item['medicine_inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['quantity'] * $item['unit_price'],
                    'condition' => $returnType === 'supplier' ? 'damaged' : $item['condition'],
                ]);

                $this->applyReturnInventoryChange($returnItem, $returnType);
            }
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return updated successfully');
    }

    public function destroy(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            foreach ($productreturn->returnItems as $item) {
                $this->applyReturnInventoryChange($item, $productreturn->return_type, true);
            }
            $productreturn->delete();
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return deleted successfully');
    }

    // Approve return
    public function approve(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            $lockedReturn = ProductReturn::query()
                ->whereKey($productreturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReturn->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending returns can be approved.',
                ]);
            }

            $lockedReturn->update(['status' => 'approved']);
        });

        return redirect()->back()->with('success', 'Return approved successfully');
    }

    // Process return
    public function process(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            $lockedReturn = ProductReturn::query()
                ->whereKey($productreturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReturn->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Only approved returns can be processed.',
                ]);
            }

            $lockedReturn->update(['status' => 'processed']);
        });

        return redirect()->back()->with('success', 'Return processed successfully');
    }

    public function reopen(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            $lockedReturn = ProductReturn::query()
                ->whereKey($productreturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReturn->status !== 'processed') {
                throw ValidationException::withMessages([
                    'status' => 'Only processed returns can be reopened.',
                ]);
            }

            $lockedReturn->update(['status' => 'pending']);
        });

        return redirect()->back()->with('success', 'Return reopened for approval');
    }

    public function pay(Request $request, ProductReturn $productreturn)
    {
        $request->validate([
            'pay_amount' => 'required|numeric|min:0.01',
        ]);

        $inputPayAmount = round((float) $request->pay_amount, 2);

        DB::transaction(function () use ($productreturn, $inputPayAmount) {
            $lockedReturn = ProductReturn::query()->whereKey($productreturn->id)->lockForUpdate()->firstOrFail();

            $totalAmount = round((float) $lockedReturn->total_amount, 2);
            $alreadyPaid = round((float) ($lockedReturn->paid_amount ?? 0), 2);
            $remaining = max(0, round($totalAmount - $alreadyPaid, 2));

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'pay_amount' => 'This return is already fully paid.',
                ]);
            }

            if ($inputPayAmount > $remaining) {
                throw ValidationException::withMessages([
                    'pay_amount' => 'Pay amount cannot be greater than due amount.',
                ]);
            }

            $newPaidAmount = round($alreadyPaid + $inputPayAmount, 2);
            $isFullyPaid = $newPaidAmount >= $totalAmount;
            $paymentStatus = $isFullyPaid ? 'paid' : 'partial';

            $lockedReturn->update([
                'paid_amount' => $newPaidAmount,
                'payment_status' => $paymentStatus,
                'status' => $isFullyPaid ? 'processed' : $lockedReturn->status,
            ]);

            $this->createExpenseFromReturnPayment($lockedReturn, $inputPayAmount);
        });

        return redirect()->back()->with('success', 'Return payment collected successfully.');
    }

    private function getOrCreateReturnExpenseHead(): ExpenseHead
    {
        $expenseHead = ExpenseHead::query()
            ->where('name', 'Product Return')
            ->orWhere('name', 'Return Expense')
            ->orWhere('name', 'Refund')
            ->first();

        if ($expenseHead) {
            return $expenseHead;
        }

        $fallback = ExpenseHead::query()->first();
        if ($fallback) {
            return $fallback;
        }

        return ExpenseHead::create([
            'name' => 'Product Return',
        ]);
    }

    private function createExpenseFromReturnPayment(ProductReturn $productreturn, float $paidAmount): void
    {
        $expenseHead = $this->getOrCreateReturnExpenseHead();

        Expense::create([
            'expense_header_id' => $expenseHead->id,
            'bill_number' => 'RET-PAY-' . $productreturn->id . '-' . now()->format('YmdHisv'),
            'name' => 'Product return payment - ' . $productreturn->return_number,
            'description' => 'Payment collected for return. Return No: ' . $productreturn->return_number . ', Source Bill: ' . ($productreturn->source_bill_no ?? 'N/A'),
            'amount' => round($paidAmount, 2),
            'date' => now()->toDateString(),
            'status' => 'Active',
        ]);
    }
}
