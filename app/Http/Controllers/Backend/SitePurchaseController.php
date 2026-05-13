<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\SitePurchase;
use App\Services\LedgerService;
use App\Traits\SystemTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SitePurchaseController extends Controller
{
    use SystemTrait;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
        $this->middleware('auth:admin');
        $this->middleware('permission:site-purchase-list');
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'site_name' => trim((string) $request->input('site_name', '')),
            'nature' => trim((string) $request->input('nature', '')),
            'numOfData' => max(1, (int) $request->input('numOfData', 10)),
        ];

        $purchases = SitePurchase::query()
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($subQuery) use ($filters) {
                    $subQuery->where('purchase_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('site_name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('vendor_name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('item_name', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['site_name'] !== '', function ($query) use ($filters) {
                $query->where('site_name', 'like', '%' . $filters['site_name'] . '%');
            })
            ->when(in_array($filters['nature'], ['asset', 'expense'], true), function ($query) use ($filters) {
                $query->where('purchase_nature', $filters['nature']);
            })
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate($filters['numOfData']);

        $purchases->appends($request->query());

        return Inertia::render('Backend/SitePurchase/Index', [
            'purchases' => $purchases,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/SitePurchase/Form', [
            'isEdit' => false,
            'purchase' => null,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);

        $purchase = DB::transaction(function () use ($payload) {
            $purchase = SitePurchase::query()->create(array_merge($payload, [
                'purchase_number' => $this->generatePurchaseNumber(),
            ]));

            $this->syncSitePurchaseLedger($purchase);

            return $purchase;
        });

        $this->storeAdminWorkLog(
            (int) $purchase->id,
            'site_purchases',
            'Site purchase created: ' . $purchase->purchase_number
        );

        return redirect()->route('backend.sitepurchase.index')->with('successMessage', 'Site purchase created successfully.');
    }

    public function show(SitePurchase $sitepurchase)
    {
        return Inertia::render('Backend/SitePurchase/Show', [
            'purchase' => $sitepurchase,
        ]);
    }

    public function edit(SitePurchase $sitepurchase)
    {
        return Inertia::render('Backend/SitePurchase/Form', [
            'isEdit' => true,
            'purchase' => $sitepurchase,
        ]);
    }

    public function update(Request $request, SitePurchase $sitepurchase)
    {
        $payload = $this->validatedPayload($request);

        DB::transaction(function () use ($payload, $sitepurchase) {
            $sitepurchase->update($payload);
            $fresh = $sitepurchase->fresh();
            if ($fresh) {
                $this->syncSitePurchaseLedger($fresh);
            }
        });

        $this->storeAdminWorkLog(
            (int) $sitepurchase->id,
            'site_purchases',
            'Site purchase updated: ' . $sitepurchase->purchase_number
        );

        return redirect()->route('backend.sitepurchase.index')->with('successMessage', 'Site purchase updated successfully.');
    }

    public function destroy(SitePurchase $sitepurchase)
    {
        $id = (int) $sitepurchase->id;
        $number = (string) $sitepurchase->purchase_number;

        DB::transaction(function () use ($sitepurchase, $id) {
            $this->removeSitePurchaseLedger($id);
            $sitepurchase->delete();
        });

        $this->storeAdminWorkLog($id, 'site_purchases', 'Site purchase deleted: ' . $number);

        return redirect()->route('backend.sitepurchase.index')->with('successMessage', 'Site purchase deleted successfully.');
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:150',
            'vendor_name' => 'nullable|string|max:150',
            'item_name' => 'required|string|max:200',
            'category_name' => 'nullable|string|max:120',
            'purchase_nature' => 'required|in:asset,expense',
            'purchase_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $quantity = round((float) ($validated['quantity'] ?? 0), 2);
        $unitPrice = round((float) ($validated['unit_price'] ?? 0), 2);
        $totalAmount = round($quantity * $unitPrice, 2);
        $paidAmount = round((float) ($validated['paid_amount'] ?? 0), 2);

        if ($paidAmount > $totalAmount) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid amount can not be greater than total amount.',
            ]);
        }

        $dueAmount = round(max(0, $totalAmount - $paidAmount), 2);

        $paymentStatus = 'pending';
        if ($dueAmount <= 0) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        }

        return [
            'site_name' => $validated['site_name'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'item_name' => $validated['item_name'],
            'category_name' => $validated['category_name'] ?? null,
            'purchase_nature' => $validated['purchase_nature'],
            'purchase_date' => $validated['purchase_date'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_status' => $paymentStatus,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function generatePurchaseNumber(): string
    {
        $prefix = web_setting_prefix('site_purchase_no_prefix', 'SPN');

        $lastPurchaseNumber = (string) SitePurchase::query()
            ->where('purchase_number', 'like', $prefix . '%')
            ->latest('id')
            ->value('purchase_number');

        $lastNumber = 0;
        if ($lastPurchaseNumber !== '' && preg_match('/(\d+)$/', $lastPurchaseNumber, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return $prefix . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function syncSitePurchaseLedger(SitePurchase $purchase): void
    {
        $this->removeSitePurchaseLedger((int) $purchase->id);

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

    private function removeSitePurchaseLedger(int $purchaseId): void
    {
        $existing = LedgerTransaction::query()
            ->where('reference_type', 'SitePurchase')
            ->where('reference_id', $purchaseId)
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
}
