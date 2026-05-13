<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MedicinePurchase;
use App\Models\SitePurchase;
use App\Models\SupplierPayment;
use App\Models\MedicineSupplier;
use App\Models\PharmacyBill;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
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
        // Backfill Site Purchase dues into SupplierPayment so vendor search works consistently.
        $this->syncPendingSitePurchasePayments();

        $filters = [
            'supplier_id' => $request->input('supplier_id'),
            'status' => $request->input('status'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'search' => trim((string) $request->input('search', '')),
            'numOfData' => (int) $request->input('numOfData', 10),
        ];

        $paymentsQuery = SupplierPayment::query()
            ->with(['supplier', 'paymentAccount:id,name,code'])
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
        $payments->getCollection()->transform(function ($payment) {
            $linkedPurchase = $this->resolveLinkedPurchaseForSupplierPayment($payment);

            $payment->setAttribute('linked_purchase', $linkedPurchase ? [
                'id' => $linkedPurchase->id,
                'purchase_number' => $linkedPurchase->purchase_number,
                'status' => $linkedPurchase->status,
                'purchase_date' => optional($linkedPurchase->purchase_date)->format('Y-m-d'),
            ] : null);

            return $payment;
        });

        $suppliers = MedicineSupplier::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $payableAccounts = $this->payableAccounts();

        return Inertia::render('Backend/SupplierPayment/Index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'payableAccounts' => $payableAccounts,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $payableAccounts = $this->payableAccounts();

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
                    'payment_account_id' => null,
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
                    'payment_account_id' => null,
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
            'payableAccounts' => $payableAccounts,
            'isEdit' => false,
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'payment_account_id' => 'nullable|integer|exists:accounts,id',
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
                'payment_account_id' => $request->payment_account_id,
                'total_amount' => $request->total_amount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_date' => $request->payment_date,
                'payment_type' => $request->payment_type,
                'status' => $status,
                'notes' => $request->notes,
            ]);

            $this->syncSupplierPaymentLedger($supplierPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($supplierPayment);
        });

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment created successfully');
    }

    public function show(SupplierPayment $supplierpayment)
    {
        $supplierpayment->load(['supplier', 'paymentAccount:id,name,code']);
        $linkedPurchase = $this->resolveLinkedPurchaseForSupplierPayment($supplierpayment);

        return Inertia::render('Backend/SupplierPayment/Show', [
            'payment' => $supplierpayment,
            'linkedPurchase' => $linkedPurchase ? [
                'id' => $linkedPurchase->id,
                'purchase_number' => $linkedPurchase->purchase_number,
                'status' => $linkedPurchase->status,
                'purchase_date' => optional($linkedPurchase->purchase_date)->format('Y-m-d'),
            ] : null,
        ]);
    }

    public function edit(SupplierPayment $supplierpayment)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $payableAccounts = $this->payableAccounts();
        return Inertia::render('Backend/SupplierPayment/Form', [
            'payment' => $supplierpayment,
            'suppliers' => $suppliers,
            'payableAccounts' => $payableAccounts,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, SupplierPayment $supplierpayment)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'payment_account_id' => 'nullable|integer|exists:accounts,id',
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
                'payment_account_id' => $request->payment_account_id,
                'total_amount' => $request->total_amount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_date' => $request->payment_date,
                'payment_type' => $request->payment_type,
                'status' => $status,
                'notes' => $request->notes,
            ]);

            $freshPayment = $supplierpayment->fresh();
            $this->syncSupplierPaymentLedger($freshPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($freshPayment);
        });

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment updated successfully');
    }

    public function destroy(SupplierPayment $supplierpayment)
    {
        $purchaseNumber = $this->extractPurchaseNumberFromNotes($supplierpayment->notes);
        $sitePurchaseNumber = $this->extractSitePurchaseNumberFromNotes($supplierpayment->notes);

        DB::transaction(function () use ($supplierpayment) {
            $this->removeSupplierPaymentLedger((int) $supplierpayment->id);
            Expense::where('bill_number', $this->supplierPaymentExpenseBillNumber($supplierpayment->id))->delete();
            $supplierpayment->delete();
        });

        if ($purchaseNumber !== null) {
            $this->syncPurchasePaymentByPurchaseNumber($purchaseNumber);
        }

        if ($sitePurchaseNumber !== null) {
            $this->syncSitePurchasePaymentByPurchaseNumber($sitePurchaseNumber, false);
        }

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment deleted successfully');
    }

    // Partial payment method
    public function addPartialPayment(Request $request, SupplierPayment $supplierpayment)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0|max:' . $supplierpayment->due_amount,
            'payment_account_id' => 'nullable|integer|exists:accounts,id',
        ]);

        DB::transaction(function () use ($request, $supplierpayment) {
            $supplierpayment->paid_amount += $request->amount;
            $supplierpayment->due_amount -= $request->amount;
            $supplierpayment->status = $supplierpayment->due_amount > 0 ? 'pending' : 'paid';
            if ($request->filled('payment_account_id')) {
                $supplierpayment->payment_account_id = (int) $request->payment_account_id;
            }
            $supplierpayment->save();

            $freshPayment = $supplierpayment->fresh();
            $this->syncSupplierPaymentLedger($freshPayment);
            $this->syncLinkedPurchaseFromSupplierPayment($freshPayment);
        });

        return redirect()->back()->with('success', 'Partial payment added successfully');
    }

    public function payDueBySupplier(Request $request, MedicineSupplier $supplier)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'nullable|integer|exists:accounts,id',
        ]);

        $inputAmount = round((float) $request->input('amount', 0), 2);
        $paymentAccountId = $request->filled('payment_account_id') ? (int) $request->input('payment_account_id') : null;

        DB::transaction(function () use ($supplier, $inputAmount, $paymentAccountId) {
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
            if ($paymentAccountId !== null) {
                $payment->payment_account_id = $paymentAccountId;
            }
            $payment->save();

            $freshPayment = $payment->fresh();
            $this->syncSupplierPaymentLedger($freshPayment);
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

    private function syncSupplierPaymentLedger(SupplierPayment $supplierpayment): void
    {
        $this->removeSupplierPaymentLedger((int) $supplierpayment->id);
        Expense::where('bill_number', $this->supplierPaymentExpenseBillNumber((int) $supplierpayment->id))->delete();

        if ($this->extractPurchaseNumberFromNotes($supplierpayment->notes) !== null) {
            // Purchase-linked supplier payments are represented by purchase ledger transaction.
            return;
        }

        if ($this->extractSitePurchaseNumberFromNotes($supplierpayment->notes) !== null) {
            // Site-purchase-linked supplier payments are represented by SitePurchase ledger transaction.
            return;
        }

        $paidAmount = round((float) ($supplierpayment->paid_amount ?? 0), 2);
        if ($paidAmount <= 0) {
            return;
        }

        $cashCode = $this->resolvePaymentAccountCode($supplierpayment);
        $payableCode = $this->ensureSystemAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false);
        $date = optional($supplierpayment->payment_date)->format('Y-m-d') ?? now()->toDateString();
        $createdBy = auth('admin')->id();

        $this->ledgerService->recordTransaction(
            [
                ['account_code' => $payableCode, 'entry_type' => 'debit', 'amount' => $paidAmount],
                ['account_code' => $cashCode, 'entry_type' => 'credit', 'amount' => $paidAmount],
            ],
            'Supplier payment #' . $supplierpayment->id,
            $date,
            'SupplierPayment',
            $supplierpayment->id,
            $createdBy
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
            $sitePurchaseNumber = $this->extractSitePurchaseNumberFromNotes($supplierpayment->notes);
            if ($sitePurchaseNumber === null) {
                return;
            }

            $this->syncSitePurchasePaymentByPurchaseNumber($sitePurchaseNumber, false);
            return;
        }

        $this->syncPurchasePaymentByPurchaseNumber($purchaseNumber);
    }

    private function resolveLinkedPurchaseForSupplierPayment(SupplierPayment $supplierpayment): ?MedicinePurchase
    {
        $purchaseNumber = $this->extractPurchaseNumberFromNotes($supplierpayment->notes);
        if ($purchaseNumber === null) {
            return null;
        }

        return MedicinePurchase::query()
            ->where('purchase_number', $purchaseNumber)
            ->first();
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

        $purchase->refresh();
        $this->syncLinkedPurchaseLedger($purchase);
    }

    private function syncLinkedPurchaseLedger(MedicinePurchase $purchase): void
    {
        $this->removeLinkedPurchaseLedger((int) $purchase->id);

        $totalAmount = round((float) ($purchase->total_amount ?? 0), 2);
        if ($totalAmount <= 0) {
            return;
        }

        $paidAmount = min(max(round((float) ($purchase->paid_amount ?? 0), 2), 0), $totalAmount);
        $dueAmount = round(max(0, $totalAmount - $paidAmount), 2);

        $inventoryCode = $this->ensureSystemAccount('PHARM_INV', 'Pharmacy Inventory', 'asset', 'ASSET', false);
        $cashCode = $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);
        $payableCode = $this->ensureSystemAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false);

        $lines = [
            ['account_code' => $inventoryCode, 'entry_type' => 'debit', 'amount' => $totalAmount],
        ];

        if ($paidAmount > 0) {
            $lines[] = ['account_code' => $cashCode, 'entry_type' => 'credit', 'amount' => $paidAmount];
        }

        if ($dueAmount > 0) {
            $lines[] = ['account_code' => $payableCode, 'entry_type' => 'credit', 'amount' => $dueAmount];
        }

        $date = $purchase->purchase_date ? date('Y-m-d', strtotime((string) $purchase->purchase_date)) : now()->toDateString();
        $createdBy = auth('admin')->id();

        $this->ledgerService->recordTransaction(
            $lines,
            'Medicine purchase ' . ((string) ($purchase->purchase_number ?? $purchase->id)),
            $date,
            'MedicinePurchase',
            $purchase->id,
            $createdBy
        );
    }

    private function removeLinkedPurchaseLedger(int $purchaseId): void
    {
        $existing = LedgerTransaction::query()
            ->where('reference_type', 'MedicinePurchase')
            ->where('reference_id', $purchaseId)
            ->first();

        if ($existing) {
            $this->ledgerService->deleteTransaction($existing->id);
        }
    }

    private function removeSupplierPaymentLedger(int $supplierPaymentId): void
    {
        $existing = LedgerTransaction::query()
            ->where('reference_type', 'SupplierPayment')
            ->where('reference_id', $supplierPaymentId)
            ->first();

        if ($existing) {
            $this->ledgerService->deleteTransaction($existing->id);
        }
    }

    private function ensureSystemAccount(string $code, string $name, string $type, string $group, bool $isProfitLoss): string
    {
        Account::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'account_group' => $group,
                'is_profit_loss' => $isProfitLoss,
                'is_active' => true,
            ]
        );

        return $code;
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

    private function extractSitePurchaseNumberFromNotes(?string $notes): ?string
    {
        if (!is_string($notes) || trim($notes) === '') {
            return null;
        }

        if (preg_match('/Initial\s+payment\s+from\s+site\s+purchase\s+([A-Za-z0-9\-]+)/i', $notes, $matches) === 1) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    private function syncPendingSitePurchasePayments(): void
    {
        $sitePurchases = SitePurchase::query()
            ->whereRaw('TRIM(COALESCE(vendor_name, "")) <> ""')
            ->where(function ($query) {
                $query->where('total_amount', '>', 0)
                    ->orWhere('paid_amount', '>', 0)
                    ->orWhere('due_amount', '>', 0);
            })
            ->get(['id', 'purchase_number', 'vendor_name', 'purchase_date', 'total_amount', 'paid_amount', 'due_amount', 'notes']);

        foreach ($sitePurchases as $sitePurchase) {
            $this->syncSitePurchasePaymentByPurchaseNumber((string) $sitePurchase->purchase_number, true);
        }
    }

    private function syncSitePurchasePaymentByPurchaseNumber(string $purchaseNumber, bool $createIfMissing = false): void
    {
        $purchase = SitePurchase::query()
            ->where('purchase_number', $purchaseNumber)
            ->first();

        if (!$purchase) {
            return;
        }

        $notePrefix = 'Initial payment from site purchase ';
        $exactNote = $notePrefix . $purchaseNumber;

        $linkedPayment = SupplierPayment::query()
            ->where('notes', 'like', $notePrefix . $purchaseNumber . '%')
            ->latest('id')
            ->first();

        if (!$linkedPayment && $createIfMissing) {
            $vendorName = trim((string) ($purchase->vendor_name ?? ''));
            if ($vendorName !== '') {
                $supplier = $this->findOrCreateSupplierByVendorName($vendorName);

                if ($supplier) {
                    $linkedPayment = SupplierPayment::query()->create([
                        'supplier_id' => $supplier->id,
                        'payment_account_id' => null,
                        'total_amount' => (float) ($purchase->total_amount ?? 0),
                        'paid_amount' => (float) ($purchase->paid_amount ?? 0),
                        'due_amount' => (float) ($purchase->due_amount ?? 0),
                        'payment_date' => optional($purchase->purchase_date)->format('Y-m-d') ?? now()->toDateString(),
                        'payment_type' => (float) ($purchase->due_amount ?? 0) > 0 ? 'partial' : 'full',
                        'status' => (float) ($purchase->due_amount ?? 0) > 0 ? 'pending' : 'paid',
                        'notes' => $exactNote,
                    ]);
                }
            }
        }

        $paidAmount = (float) ($linkedPayment?->paid_amount ?? 0);
        $dueAmount = max(0, (float) $purchase->total_amount - $paidAmount);

        $paymentStatus = 'pending';
        if ($dueAmount <= 0) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        }

        $purchase->update([
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_status' => $paymentStatus,
        ]);

        $freshPurchase = $purchase->fresh();
        if ($freshPurchase) {
            $this->syncLinkedSitePurchaseLedger($freshPurchase);
        }
    }

    private function findOrCreateSupplierByVendorName(string $vendorName): ?MedicineSupplier
    {
        $normalizedName = trim(preg_replace('/\s+/', ' ', $vendorName));
        if ($normalizedName === '') {
            return null;
        }

        $supplier = MedicineSupplier::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($normalizedName)])
            ->first();

        if ($supplier) {
            return $supplier;
        }

        return MedicineSupplier::query()->create([
            'name' => $normalizedName,
            'phone' => '-',
            'contact_person_name' => $normalizedName,
            'contact_person_phone' => '-',
            'drug_lisence_no' => '-',
            'address' => 'N/A',
            'status' => 'Active',
        ]);
    }

    private function syncLinkedSitePurchaseLedger(SitePurchase $purchase): void
    {
        $this->removeLinkedSitePurchaseLedger((int) $purchase->id);

        $totalAmount = round((float) ($purchase->total_amount ?? 0), 2);
        if ($totalAmount <= 0) {
            return;
        }

        $paidAmount = min(max(round((float) ($purchase->paid_amount ?? 0), 2), 0), $totalAmount);
        $dueAmount = round(max(0, $totalAmount - $paidAmount), 2);

        $debitAccount = $purchase->purchase_nature === 'asset'
            ? $this->ensureSystemAccount('SITE_PURCHASE_ASSET', 'Site Purchase Asset', 'asset', 'ASSET', false)
            : $this->ensureSystemAccount('SITE_PURCHASE_EXP', 'Site Purchase Expense', 'expense', 'EXPENSE', true);

        $cashCode = $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);
        $payableCode = $this->ensureSystemAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false);

        $lines = [
            ['account_code' => $debitAccount, 'entry_type' => 'debit', 'amount' => $totalAmount],
        ];

        if ($paidAmount > 0) {
            $lines[] = ['account_code' => $cashCode, 'entry_type' => 'credit', 'amount' => $paidAmount];
        }

        if ($dueAmount > 0) {
            $lines[] = ['account_code' => $payableCode, 'entry_type' => 'credit', 'amount' => $dueAmount];
        }

        $description = 'Site purchase ' . ((string) ($purchase->purchase_number ?? $purchase->id))
            . ' (' . (string) ($purchase->site_name ?? 'N/A') . ')';

        $date = $purchase->purchase_date
            ? date('Y-m-d', strtotime((string) $purchase->purchase_date))
            : now()->toDateString();

        $this->ledgerService->recordTransaction(
            $lines,
            $description,
            $date,
            'SitePurchase',
            $purchase->id,
            auth('admin')->id()
        );
    }

    private function removeLinkedSitePurchaseLedger(int $sitePurchaseId): void
    {
        $existing = LedgerTransaction::query()
            ->where('reference_type', 'SitePurchase')
            ->where('reference_id', $sitePurchaseId)
            ->first();

        if ($existing) {
            $this->ledgerService->deleteTransaction($existing->id);
        }
    }

    private function payableAccounts()
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function resolvePaymentAccountCode(SupplierPayment $supplierpayment): string
    {
        $fallbackCode = $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);
        $paymentAccountId = (int) ($supplierpayment->payment_account_id ?? 0);

        if ($paymentAccountId <= 0) {
            return $fallbackCode;
        }

        $accountCode = (string) Account::query()
            ->where('id', $paymentAccountId)
            ->where('is_active', true)
            ->value('code');

        return trim($accountCode) !== '' ? $accountCode : $fallbackCode;
    }
}
