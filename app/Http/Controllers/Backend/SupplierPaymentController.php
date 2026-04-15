<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\MedicinePurchase;
use App\Models\SupplierPayment;
use App\Models\MedicineSupplier;
use App\Models\PharmacyBill;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:supplier-payment-list', ['only' => ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'addPartialPayment', 'payDueBySupplier']]);
        $this->middleware('permission:supplier-payment-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:supplier-payment-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:supplier-payment-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:supplier-payment-list-status', ['only' => ['addPartialPayment', 'payDueBySupplier']]);
        $this->middleware('permission:stock-report-list', ['only' => ['stockDueReport']]);
    }

    public function index(Request $request)
    {
        $filters = [
            'supplier_id' => $request->input('supplier_id'),
            'status' => $request->input('status'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'search' => trim((string) $request->input('search', '')),
            'numOfData' => (int) $request->input('numOfData', 10),
        ];

        $paymentsQuery = SupplierPayment::query()
            ->with('supplier')
            ->when(!empty($filters['supplier_id']), function ($query) use ($filters) {
                $query->where('supplier_id', $filters['supplier_id']);
            })
            ->when(!empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(!empty($filters['from_date']), function ($query) use ($filters) {
                $query->whereDate('payment_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($query) use ($filters) {
                $query->whereDate('payment_date', '<=', $filters['to_date']);
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->whereHas('supplier', function ($supplierQuery) use ($filters) {
                    $supplierQuery
                        ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->latest('payment_date')
            ->latest('id');

        $payments = $paymentsQuery->paginate($filters['numOfData']);
        $payments->appends($request->query());

        $suppliers = MedicineSupplier::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Backend/SupplierPayment/Index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();

        $prefill = null;
        $purchaseId = (int) $request->input('purchase_id', 0);
        $supplierId = (int) $request->input('supplier_id', 0);

        if ($purchaseId > 0) {
            $purchase = MedicinePurchase::query()->find($purchaseId);

            if ($purchase) {
                $linkedPayment = SupplierPayment::query()
                    ->where('supplier_id', $purchase->supplier_id)
                    ->where('notes', 'Initial payment from purchase ' . $purchase->purchase_number)
                    ->latest('id')
                    ->first();

                if ($linkedPayment) {
                    return redirect()->route('backend.supplierpayment.edit', $linkedPayment->id);
                }

                $prefill = [
                    'supplier_id' => $purchase->supplier_id,
                    'total_amount' => (float) ($purchase->total_amount ?? 0),
                    'paid_amount' => (float) ($purchase->paid_amount ?? 0),
                    'payment_date' => optional($purchase->purchase_date)->format('Y-m-d') ?? now()->toDateString(),
                    'payment_type' => (float) ($purchase->due_amount ?? 0) > 0 ? 'partial' : 'full',
                    'notes' => 'Initial payment from purchase ' . $purchase->purchase_number,
                ];
            }
        }

        if ($prefill === null && $supplierId > 0) {
            $supplier = MedicineSupplier::query()->find($supplierId);
            if ($supplier) {
                $prefill = [
                    'supplier_id' => $supplier->id,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'payment_date' => now()->toDateString(),
                    'payment_type' => 'partial',
                    'notes' => null,
                ];
            }
        }

        return Inertia::render('Backend/SupplierPayment/Form', [
            'suppliers' => $suppliers,
            'isEdit' => false,
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:full,partial',
            'notes' => 'nullable|string',
        ]);

        $paidAmount = $request->paid_amount ?? 0;
        $dueAmount = $request->total_amount - $paidAmount;
        $status = $dueAmount > 0 ? 'pending' : 'paid';

        DB::transaction(function () use ($request, $paidAmount, $dueAmount, $status) {
            $supplierPayment = SupplierPayment::create([
                'supplier_id' => $request->supplier_id,
                'total_amount' => $request->total_amount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_date' => $request->payment_date,
                'payment_type' => $request->payment_type,
                'status' => $status,
                'notes' => $request->notes,
            ]);

            $this->syncSupplierPaymentExpense($supplierPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($supplierPayment);
        });

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment created successfully');
    }

    public function show(SupplierPayment $supplierpayment)
    {
        $supplierpayment->load('supplier');
        return Inertia::render('Backend/SupplierPayment/Show', [
            'payment' => $supplierpayment,
        ]);
    }

    public function edit(SupplierPayment $supplierpayment)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        return Inertia::render('Backend/SupplierPayment/Form', [
            'payment' => $supplierpayment,
            'suppliers' => $suppliers,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, SupplierPayment $supplierpayment)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:full,partial',
            'notes' => 'nullable|string',
        ]);

        $paidAmount = $request->paid_amount ?? 0;
        $dueAmount = $request->total_amount - $paidAmount;
        $status = $dueAmount > 0 ? 'pending' : 'paid';

        DB::transaction(function () use ($request, $supplierpayment, $paidAmount, $dueAmount, $status) {
            $supplierpayment->update([
                'supplier_id' => $request->supplier_id,
                'total_amount' => $request->total_amount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_date' => $request->payment_date,
                'payment_type' => $request->payment_type,
                'status' => $status,
                'notes' => $request->notes,
            ]);

            $freshPayment = $supplierpayment->fresh();
            $this->syncSupplierPaymentExpense($freshPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($freshPayment);
        });

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment updated successfully');
    }

    public function destroy(SupplierPayment $supplierpayment)
    {
        $purchaseNumber = $this->extractPurchaseNumberFromNotes($supplierpayment->notes);

        DB::transaction(function () use ($supplierpayment) {
            Expense::where('bill_number', $this->supplierPaymentExpenseBillNumber($supplierpayment->id))->delete();
            $supplierpayment->delete();
        });

        if ($purchaseNumber !== null) {
            $this->syncPurchasePaymentByPurchaseNumber($purchaseNumber);
        }

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment deleted successfully');
    }

    // Partial payment method
    public function addPartialPayment(Request $request, SupplierPayment $supplierpayment)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0|max:' . $supplierpayment->due_amount,
        ]);

        DB::transaction(function () use ($request, $supplierpayment) {
            $supplierpayment->paid_amount += $request->amount;
            $supplierpayment->due_amount -= $request->amount;
            $supplierpayment->status = $supplierpayment->due_amount > 0 ? 'pending' : 'paid';
            $supplierpayment->save();

            $freshPayment = $supplierpayment->fresh();
            $this->syncSupplierPaymentExpense($freshPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($freshPayment);
        });

        return redirect()->back()->with('success', 'Partial payment added successfully');
    }

    public function payDueBySupplier(Request $request, MedicineSupplier $supplier)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $inputAmount = round((float) $request->input('amount', 0), 2);

        DB::transaction(function () use ($supplier, $inputAmount) {
            $payment = SupplierPayment::query()
                ->where('supplier_id', $supplier->id)
                ->where('due_amount', '>', 0)
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'amount' => 'No pending supplier due found for collection.',
                ]);
            }

            $dueAmount = round((float) $payment->due_amount, 2);

            if ($inputAmount > $dueAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot be greater than due amount.',
                ]);
            }

            $payment->paid_amount = round((float) $payment->paid_amount + $inputAmount, 2);
            $payment->due_amount = round($dueAmount - $inputAmount, 2);
            $payment->status = $payment->due_amount > 0 ? 'pending' : 'paid';
            $payment->save();

            $freshPayment = $payment->fresh();
            $this->syncSupplierPaymentExpense($freshPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($freshPayment);
        });

        return redirect()->back()->with('success', 'Supplier due payment added successfully');
    }

    // Stock and Due Report
    public function stockDueReport(Request $request)
    {
        $filters = [
            'supplier_id' => $request->input('supplier_id'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
        ];

        $suppliers = MedicineSupplier::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $soldByMedicineId = [];

        $pharmacyBills = PharmacyBill::query()
            ->where('status', 'Active')
            ->when(!empty($filters['from_date']), function ($query) use ($filters) {
                $query->whereDate('date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($query) use ($filters) {
                $query->whereDate('date', '<=', $filters['to_date']);
            })
            ->get(['products']);

        foreach ($pharmacyBills as $bill) {
            $products = is_string($bill->products)
                ? json_decode($bill->products, true)
                : $bill->products;

            if (!is_array($products)) {
                continue;
            }

            foreach ($products as $product) {
                $medicineId = (int) ($product['productId'] ?? 0);
                $soldQty = (float) ($product['quantity'] ?? 0);

                if ($medicineId <= 0 || $soldQty <= 0) {
                    continue;
                }

                $soldByMedicineId[$medicineId] = ($soldByMedicineId[$medicineId] ?? 0) + $soldQty;
            }
        }

        $report = MedicineSupplier::query()
            ->when(!empty($filters['supplier_id']), function ($query) use ($filters) {
                $query->where('id', $filters['supplier_id']);
            })
            ->with([
                'supplierPayments' => function ($query) use ($filters) {
                    $query->where('status', '!=', 'paid')
                        ->when(!empty($filters['from_date']), function ($subQuery) use ($filters) {
                            $subQuery->whereDate('payment_date', '>=', $filters['from_date']);
                        })
                        ->when(!empty($filters['to_date']), function ($subQuery) use ($filters) {
                            $subQuery->whereDate('payment_date', '<=', $filters['to_date']);
                        });
                },
                'medicines',
            ])
            ->get()
            ->map(function ($supplier) use ($soldByMedicineId) {
            $medicineDetails = $supplier->medicines
                ->groupBy('medicine_name')
                ->map(function ($rows, $medicineName) use ($soldByMedicineId) {
                    $stockQuantity = (float) $rows->sum('medicine_quantity');
                    $averageUnitPrice = (float) $rows->avg('medicine_unit_purchase_price');
                    $totalValue = (float) $rows->sum(function ($row) {
                        return (float) $row->medicine_unit_purchase_price * (float) $row->medicine_quantity;
                    });

                    $soldQuantity = (float) $rows->sum(function ($row) use ($soldByMedicineId) {
                        return (float) ($soldByMedicineId[$row->id] ?? 0);
                    });

                    return [
                        'medicine_name' => (string) $medicineName,
                        'stock_quantity' => $stockQuantity,
                        'sold_quantity' => $soldQuantity,
                        'unit_price' => $averageUnitPrice,
                        'total_value' => $totalValue,
                    ];
                })
                ->sortBy('medicine_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $totalDue = $supplier->supplierPayments->sum('due_amount');
            $stockValue = (float) $medicineDetails->sum('total_value');
            $totalSoldQuantity = (float) $medicineDetails->sum('sold_quantity');

            return [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'total_due' => $totalDue,
                'stock_value' => $stockValue,
                'total_sold_quantity' => $totalSoldQuantity,
                'medicines' => $medicineDetails,
            ];
        });

        return Inertia::render('Backend/SupplierPayment/StockDueReport', [
            'report' => $report,
            'suppliers' => $suppliers,
            'filters' => $filters,
        ]);
    }

    private function syncSupplierPaymentExpense(SupplierPayment $supplierpayment): void
    {
        $paidAmount = (float) ($supplierpayment->paid_amount ?? 0);
        $billNumber = $this->supplierPaymentExpenseBillNumber($supplierpayment->id);

        if ($paidAmount <= 0) {
            Expense::where('bill_number', $billNumber)->delete();
            return;
        }

        $expenseHeader = ExpenseHead::firstOrCreate(
            ['name' => 'Supplier Payment'],
            ['status' => 'Active']
        );

        $supplierpayment->loadMissing('supplier');
        $adminId = auth('admin')->id();

        Expense::updateOrCreate(
            ['bill_number' => $billNumber],
            [
                'expense_header_id' => $expenseHeader->id,
                'bill_number' => $billNumber,
                'case_id' => null,
                'name' => (string) ($supplierpayment->supplier->name ?? 'Supplier Payment'),
                'description' => 'Supplier payment expense (Payment #' . $supplierpayment->id . ')',
                'amount' => $paidAmount,
                'date' => optional($supplierpayment->payment_date)->format('Y-m-d') ?? now()->toDateString(),
                'status' => 'Active',
                'updated_by' => $adminId,
                'created_by' => $adminId,
            ]
        );
    }

    private function supplierPaymentExpenseBillNumber(int $supplierPaymentId): string
    {
        return 'SPAY-' . $supplierPaymentId;
    }

    private function syncLinkedPurchaseFromSupplierPayment(SupplierPayment $supplierpayment): void
    {
        $purchaseNumber = $this->extractPurchaseNumberFromNotes($supplierpayment->notes);
        if ($purchaseNumber === null) {
            return;
        }

        $this->syncPurchasePaymentByPurchaseNumber($purchaseNumber);
    }

    private function syncPurchasePaymentByPurchaseNumber(string $purchaseNumber): void
    {
        $purchase = MedicinePurchase::query()
            ->where('purchase_number', $purchaseNumber)
            ->first();

        if (!$purchase) {
            return;
        }

        $linkedPayment = SupplierPayment::query()
            ->where('notes', 'like', 'Initial payment from purchase ' . $purchaseNumber . '%')
            ->latest('id')
            ->first();

        $paidAmount = (float) ($linkedPayment?->paid_amount ?? 0);
        $dueAmount = max(0, (float) $purchase->total_amount - $paidAmount);

        $purchase->update([
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
        ]);
    }

    private function extractPurchaseNumberFromNotes(?string $notes): ?string
    {
        if (!is_string($notes) || trim($notes) === '') {
            return null;
        }

        if (preg_match('/Initial\s+payment\s+from\s+purchase\s+([A-Za-z0-9\-]+)/i', $notes, $matches) === 1) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }
}
