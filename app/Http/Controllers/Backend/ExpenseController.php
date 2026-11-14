<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Account;
use App\Models\Billing;
use App\Models\Expense;
use App\Models\LedgerTransaction;
use App\Models\StorePurchasePayment;
use App\Models\SupplierPayment;
use App\Models\WebSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use App\Models\ExpenseHead;
use App\Models\Department;
use App\Services\ExpenseService;
use App\Services\LedgerService;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseController extends Controller
{
    use SystemTrait;

    protected $expenseService;
    protected LedgerService $ledgerService;

    public function __construct(ExpenseService $expenseService, LedgerService $ledgerService)
    {
        $this->expenseService = $expenseService;
        $this->ledgerService = $ledgerService;

        $this->middleware('auth:admin');
        $this->middleware('permission:expense-list');
        $this->middleware('permission:expense-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:expense-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:expense-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:expense-list-status', ['only' => ['changeStatus']]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return Inertia::render(
            'Backend/Expense/Index',
            [
                'pageTitle' => fn() => 'Expense List',
                'filters' => fn() => $request->only(['numOfData', 'name', 'date_from', 'date_to']),
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'supplierPayments' => fn() => $this->getSupplierPaymentDatas(),
                'expenseTotals' => fn() => $this->getExpenseTotals(),
            ]
        );
    }

    private function getExpenseTotals(): array
    {
        $departmentScope = user_dashboard_department_slug(auth('admin')->user());
        $regularExpensesQuery = Expense::query()->where('status', 'Active');
        if ($departmentScope !== null) {
            $regularExpensesQuery->where('department_id', $this->currentDepartmentId() ?? 0);
        }
        $regularExpenses = (float) $regularExpensesQuery->sum('amount');
        $storePayments = $departmentScope === null
            ? (float) StorePurchasePayment::query()->sum('amount')
            : 0.0;
        $pharmacyPayments = $departmentScope === null || $departmentScope === 'pharmacy'
            ? (float) SupplierPayment::query()->where('paid_amount', '>', 0)->sum('paid_amount')
            : 0.0;
        $labPayments = $departmentScope === null
            ? (float) DB::table('lab_purchase_payments')->sum('amount')
            : 0.0;

        return [
            'regular' => $regularExpenses,
            'store' => $storePayments,
            'pharmacy' => $pharmacyPayments,
            'lab' => $labPayments,
            'total' => round($regularExpenses + $storePayments + $pharmacyPayments + $labPayments, 2),
            'printed_at' => now()->format('d M, Y h:i A'),
        ];
    }

    private function getSupplierPaymentDatas(): LengthAwarePaginator
    {
        $name = trim((string) request()->input('name', ''));
        $billNumber = trim((string) request()->input('bill_number', ''));

        $storePayments = StorePurchasePayment::query()
            ->with(['purchaseOrder.supplier', 'paymentAccount'])
            ->where('amount', '>', 0)
            ->when($name !== '', fn ($query) => $query->whereHas('purchaseOrder.supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', '%' . $name . '%')))
            ->when($billNumber !== '', fn ($query) => $query->whereHas('purchaseOrder', fn ($orderQuery) => $orderQuery->where('purchase_order_no', 'like', '%' . $billNumber . '%')))
            ->get()
            ->map(fn (StorePurchasePayment $payment) => [
                'key' => 'store-' . $payment->id,
                'date' => $payment->payment_date->format('Y-m-d'),
                'display_date' => $payment->payment_date->format('d M, Y'),
                'supplier' => $payment->purchaseOrder?->supplier?->name ?? 'N/A',
                'source' => 'Store',
                'bill_number' => $payment->purchaseOrder?->purchase_order_no ?? 'N/A',
                'reference' => $payment->reference_no ?: 'N/A',
                'account' => $payment->paymentAccount?->name ?? 'Cash',
                'amount' => (float) $payment->amount,
                'sort_id' => $payment->id,
            ]);

        $pharmacyPayments = SupplierPayment::query()
            ->with(['supplier', 'paymentAccount'])
            ->where('paid_amount', '>', 0)
            ->when($name !== '', fn ($query) => $query->whereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', '%' . $name . '%')))
            ->when($billNumber !== '', function ($query) use ($billNumber) {
                $paymentId = preg_replace('/^SUP-/i', '', $billNumber);
                if (ctype_digit($paymentId)) {
                    $query->whereKey((int) $paymentId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->get()
            ->map(fn (SupplierPayment $payment) => [
                'key' => 'pharmacy-' . $payment->id,
                'date' => $payment->payment_date->format('Y-m-d'),
                'display_date' => $payment->payment_date->format('d M, Y'),
                'supplier' => $payment->supplier?->name ?? 'N/A',
                'source' => 'Pharmacy',
                'bill_number' => 'SUP-' . $payment->id,
                'reference' => $payment->notes ?: 'N/A',
                'account' => $payment->paymentAccount?->name ?? 'Cash',
                'amount' => (float) $payment->paid_amount,
                'sort_id' => $payment->id,
            ]);

        $labPayments = DB::table('lab_purchase_payments')
            ->join('lab_purchase_orders', 'lab_purchase_orders.id', '=', 'lab_purchase_payments.lab_purchase_order_id')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'lab_purchase_payments.payment_account_id')
            ->where('lab_purchase_payments.amount', '>', 0)
            ->when($name !== '', fn ($query) => $query->where('lab_suppliers.name', 'like', '%' . $name . '%'))
            ->when($billNumber !== '', fn ($query) => $query->where('lab_purchase_orders.order_no', 'like', '%' . $billNumber . '%'))
            ->get([
                'lab_purchase_payments.id',
                'lab_purchase_payments.payment_date',
                'lab_purchase_payments.reference_no',
                'lab_purchase_payments.amount',
                'lab_purchase_orders.order_no',
                'lab_suppliers.name as supplier_name',
                'accounts.name as account_name',
            ])
            ->map(fn ($payment) => [
                'key' => 'lab-' . $payment->id,
                'date' => $payment->payment_date,
                'display_date' => Carbon::parse($payment->payment_date)->format('d M, Y'),
                'supplier' => $payment->supplier_name,
                'source' => 'Laboratory',
                'bill_number' => $payment->order_no,
                'reference' => $payment->reference_no ?: 'N/A',
                'account' => $payment->account_name ?? 'Cash',
                'amount' => (float) $payment->amount,
                'sort_id' => $payment->id,
            ]);

        $departmentScope = user_dashboard_department_slug(auth('admin')->user());
        if ($departmentScope !== null) {
            $payments = $departmentScope === 'pharmacy' ? $pharmacyPayments : collect();
            $perPage = max(1, min((int) request()->input('numOfData', 10), 500));
            $currentPage = LengthAwarePaginator::resolveCurrentPage('supplier_payments_page');
            return new LengthAwarePaginator(
                $payments->forPage($currentPage, $perPage)->values(),
                $payments->count(),
                $perPage,
                $currentPage,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => 'supplier_payments_page',
                ]
            );
        }

        $payments = $storePayments
            ->concat($pharmacyPayments)
            ->concat($labPayments)
            ->sortByDesc(fn (array $payment) => $payment['date'] . '-' . str_pad((string) $payment['sort_id'], 12, '0', STR_PAD_LEFT))
            ->values();

        $perPage = max(1, min((int) request()->input('numOfData', 10), 500));
        $currentPage = LengthAwarePaginator::resolveCurrentPage('supplier_payments_page');
        $paginator = new LengthAwarePaginator(
            $payments->forPage($currentPage, $perPage)->values(),
            $payments->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'supplier_payments_page',
            ]
        );
        $paginator->appends(request()->query());

        return $paginator;
    }

    private function getDatas()
    {
        $query = $this->expenseService->list();
        if (user_dashboard_department_slug(auth('admin')->user()) !== null) {
            $query->where('department_id', $this->currentDepartmentId() ?? 0);
        }

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        if (request()->filled('bill_number'))
            $query->where('bill_number', 'like', request()->bill_number . '%');

        if (request()->filled('date_from'))
            $query->whereDate('date', '>=', request()->date_from);

        if (request()->filled('date_to'))
            $query->whereDate('date', '<=', request()->date_to);

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->expense_head = $data->expenseHead->name ?? 'N/A';
            $customData->department = $data->department->name ?? 'N/A';
            $customData->bill_number = $data->bill_number;
            $customData->name = $data->name;
            $customData->amount = '৳ ' . number_format($data->amount, 2);
            $customData->date = date('d M, Y', strtotime($data->date));
            $customData->document = $data->document
                ? '<a href="' . $data->document . '" target="_blank" class="text-blue-600 hover:underline">View</a>'
                : 'No file';
            $customData->status = getStatusText($data->status);

            $existsInBilling = Billing::where('bill_number', $data->bill_number)->exists();

            $customData->links = [];

            if (!$existsInBilling) {
                if ($user->can('expense-list-status')) {
                    $customData->links[] = [
                        'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                        'link' => route('backend.expense.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                        'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                    ];
                }

                if ($user->can('expense-list-edit')) {
                    $customData->links[] = [
                        'linkClass' => 'bg-yellow-400 text-black semi-bold',
                        'link' => route('backend.expense.edit',  $data->id),
                        'linkLabel' => getLinkLabel('Edit', null, null)
                    ];
                }

                if ($user->can('expense-list-delete')) {
                    $customData->links[] = [
                        'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                        'link' => route('backend.expense.destroy', $data->id),
                        'linkLabel' => getLinkLabel('Delete', null, null)
                    ];
                }
            }

            $customData->links[] = [
                'linkClass' => 'bg-slate-600 text-white semi-bold',
                'link' => route('backend.expense.print', $data->id),
                'linkLabel' => getLinkLabel('Print', null, null),
                'target' => '_blank',
            ];

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }


    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'expense_head', 'class' => 'text-center'],
            ['fieldName' => 'department', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'amount', 'class' => 'text-center'],
            ['fieldName' => 'date', 'class' => 'text-center'],
            ['fieldName' => 'document', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Expense Head',
            'Department',
            'Invoice Number',
            'Name',
            'Amount',
            'Date',
            'Document',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        $expenseHeads = ExpenseHead::where('status', 'Active')->get();
        $departments = Department::where('status', 'Active')->orderBy('name')->get(['id', 'name']);
        $departmentScope = user_dashboard_department_slug(auth('admin')->user());
        $defaultDepartmentId = $this->currentDepartmentId();

        return Inertia::render(
            'Backend/Expense/Form',
            [
                'pageTitle' => fn() => 'Expense Create',
                'expenseHeads' => fn() => $expenseHeads,
                'departments' => fn() => $departments,
                'defaultDepartmentId' => fn() => $defaultDepartmentId,
                'departmentScope' => fn() => $departmentScope,
            ]
        );
    }

    public function store(ExpenseRequest $request)
    {
        $data = $request->validated();

        if (empty($data['department_id']) && !empty($request->input('department_name'))) {
            $departmentName = trim((string) $request->input('department_name'));
            if ($departmentName !== '') {
                $department = Department::firstOrCreate([
                    'name' => $departmentName,
                ], [
                    'status' => 'Active',
                ]);
                $data['department_id'] = $department->id;
            }
        }

        if (empty($data['expense_header_id']) && !empty($request->input('expense_head_name'))) {
            $expenseHeadName = trim((string) $request->input('expense_head_name'));
            if ($expenseHeadName !== '') {
                $expenseHead = ExpenseHead::firstOrCreate([
                    'name' => $expenseHeadName,
                ], [
                    'status' => 'Active',
                ]);
                $data['expense_header_id'] = $expenseHead->id;
            }
        }

        if (user_dashboard_department_slug(auth('admin')->user()) !== null) {
            $data['department_id'] = $this->currentDepartmentId();
            if (!$data['department_id']) {
                return redirect()->back()->withErrors([
                    'department_id' => 'A department must be assigned to this account before recording expenses.',
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            if (empty($data['bill_number'])) {
                $data['bill_number'] = 'Bill-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }

            if ($request->hasFile('document'))
                $data['document'] = $this->fileUpload($request->file('document'), '/expenses');

            $dataInfo = $this->expenseService->create($data);

            if ($dataInfo) {
                $this->syncExpenseLedger($dataInfo);

                $message = 'Expense created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'expenses', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Expense.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseController', 'store', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $expense = $this->findAccessibleExpense($id);
        $expenseHeads = ExpenseHead::where('status', 'Active')->get();
        $departments = Department::where('status', 'Active')->orderBy('name')->get(['id', 'name']);
        $departmentScope = user_dashboard_department_slug(auth('admin')->user());

        return Inertia::render(
            'Backend/Expense/Form',
            [
                'pageTitle' => fn() => 'Expense Edit',
                'expense' => fn() => $expense,
                'expenseHeads' => fn() => $expenseHeads,
                'departments' => fn() => $departments,
                'departmentScope' => fn() => $departmentScope,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ExpenseRequest $request, $id)
    {
        $expense = $this->findAccessibleExpense($id);
        $data = $request->validated();

        if (empty($data['department_id']) && !empty($request->input('department_name'))) {
            $departmentName = trim((string) $request->input('department_name'));
            if ($departmentName !== '') {
                $department = Department::firstOrCreate([
                    'name' => $departmentName,
                ], [
                    'status' => 'Active',
                ]);
                $data['department_id'] = $department->id;
            }
        }

        if (empty($data['expense_header_id']) && !empty($request->input('expense_head_name'))) {
            $expenseHeadName = trim((string) $request->input('expense_head_name'));
            if ($expenseHeadName !== '') {
                $expenseHead = ExpenseHead::firstOrCreate([
                    'name' => $expenseHeadName,
                ], [
                    'status' => 'Active',
                ]);
                $data['expense_header_id'] = $expenseHead->id;
            }
        }

        if (user_dashboard_department_slug(auth('admin')->user()) !== null) {
            $data['department_id'] = $this->currentDepartmentId();
            if (!$data['department_id']) {
                return redirect()->back()->withErrors([
                    'department_id' => 'A department must be assigned to this account before recording expenses.',
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('document')) {
                $data['document'] = $this->fileUpload($request->file('document'), '/expenses');

                if ($expense->document) {
                    $path = str_replace(env('APP_URL') . '/public/storage/', 'storage/', $expense->document);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            } else {
                $data['document'] = str_replace(env('APP_URL') . '/public/storage/', '', $expense->document ?? '');
            }

            $dataInfo = $this->expenseService->update($data, $id);

            if ($dataInfo->wasChanged()) {
                $this->syncExpenseLedger($dataInfo);

                $message = 'Expense updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'expenses', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update expense.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseController', 'update', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function print($id)
    {
        $expense = $this->findAccessibleExpense($id);
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();

        return view('backend.expense.print', [
            'expense' => $expense,
            'websetting' => $websetting,
        ]);
    }

    public function destroy($id)
    {
        $this->findAccessibleExpense($id);
        DB::beginTransaction();

        try {
            $this->removeExpenseLedger($id);

            if ($this->expenseService->delete($id)) {
                $message = 'Expense deleted successfully';
                $this->storeAdminWorkLog($id, 'expenses', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Expense.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseController', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus(Request $request, $id, $status)
    {
        $this->findAccessibleExpense($id);
        DB::beginTransaction();

        try {
            $dataInfo = $this->expenseService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                if ($status === 'Active') {
                    $this->syncExpenseLedger($dataInfo);
                } else {
                    $this->removeExpenseLedger($dataInfo->id);
                }

                $message = 'Expense ' . $status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'expenses', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . $status . " Expense.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function currentDepartmentId(): ?int
    {
        $user = auth('admin')->user();
        $departmentScope = user_dashboard_department_slug($user);
        if ($departmentScope === null) {
            return null;
        }

        $departmentId = $user?->details?->department_id;
        if ($departmentId) {
            return (int) $departmentId;
        }

        $departmentId = Department::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%' . $departmentScope . '%'])
            ->value('id');

        return $departmentId ? (int) $departmentId : null;
    }

    private function findAccessibleExpense($id): Expense
    {
        $expense = $this->expenseService->find($id);
        if (user_dashboard_department_slug(auth('admin')->user()) !== null) {
            abort_unless(
                $this->currentDepartmentId() !== null
                && (int) $expense->department_id === $this->currentDepartmentId(),
                403
            );
        }

        return $expense;
    }

    private function syncExpenseLedger(Expense $expense): void
    {
        // Keep exactly one ledger transaction per expense row.
        $this->removeExpenseLedger((int) $expense->id);

        if (($expense->status ?? 'Active') !== 'Active') {
            return;
        }

        $amount = round((float) ($expense->amount ?? 0), 2);
        if ($amount <= 0) {
            return;
        }

        $cashCode = $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);
        $expenseCode = $this->ensureExpenseAccountForHead($expense);

        $description = trim((string) ($expense->description ?? ''));
        if ($description === '') {
            $description = 'Expense #' . $expense->id . ' - ' . (($expense->name ?? '') ?: 'General Expense');
        }

        $date = $expense->date ? date('Y-m-d', strtotime((string) $expense->date)) : now()->toDateString();
        $createdBy = auth('admin')->id();

        $this->ledgerService->recordExpense(
            $expenseCode,
            $cashCode,
            $amount,
            $description,
            $date,
            'Expense',
            $expense->id,
            $createdBy
        );
    }

    private function removeExpenseLedger(int $expenseId): void
    {
        $existing = LedgerTransaction::query()
            ->where('reference_type', 'Expense')
            ->where('reference_id', $expenseId)
            ->first();

        if ($existing) {
            $this->ledgerService->deleteTransaction($existing->id);
        }
    }

    private function ensureExpenseAccountForHead(Expense $expense): string
    {
        $headId = (int) ($expense->expense_header_id ?? 0);
        $headName = trim((string) ($expense->expenseHead?->name ?? 'General Expense'));

        $baseCode = $headId > 0 ? ('EXP_HEAD_' . $headId) : 'GEN_EXP';
        $baseCode = strtoupper((string) preg_replace('/[^A-Z0-9_]/', '_', $baseCode));
        $code = substr($baseCode, 0, 50);
        $name = $headName !== '' ? ('Expense - ' . $headName) : 'General Expense';

        return $this->ensureSystemAccount($code, $name, 'expense', 'EXPENSE', true);
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
