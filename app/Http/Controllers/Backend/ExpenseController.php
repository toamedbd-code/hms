<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Account;
use App\Models\Billing;
use App\Models\Expense;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use App\Models\ExpenseHead;
use App\Services\ExpenseService;
use App\Services\LedgerService;
use Exception;

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

    public function index()
    {
        return Inertia::render(
            'Backend/Expense/Index',
            [
                'pageTitle' => fn() => 'Expense List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->expenseService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        if (request()->filled('bill_number'))
            $query->where('bill_number', 'like', request()->bill_number . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->expense_head = $data->expenseHead->name ?? 'N/A';
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

        return Inertia::render(
            'Backend/Expense/Form',
            [
                'pageTitle' => fn() => 'Expense Create',
                'expenseHeads' => fn() => $expenseHeads,
            ]
        );
    }

    public function store(ExpenseRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

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
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $expense = $this->expenseService->find($id);
        $expenseHeads = ExpenseHead::where('status', 'Active')->get();

        return Inertia::render(
            'Backend/Expense/Form',
            [
                'pageTitle' => fn() => 'Expense Edit',
                'expense' => fn() => $expense,
                'expenseHeads' => fn() => $expenseHeads,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ExpenseRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $expense = $this->expenseService->find($id);

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
        $expense = $this->expenseService->find($id);

        return view('backend.expense.print', [
            'expense' => $expense,
        ]);
    }

    public function destroy($id)
    {
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
