<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingRequest;
use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillingDoctor;
use App\Models\BillItem;
use App\Models\Expense;
use App\Models\MedicineInventory;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Radiology;
use App\Models\Referral;
use App\Models\ReferralPerson;
use App\Models\Test;
use App\Models\TestCategory;
use App\Models\OpdPrescription;
use App\Models\IpdPrescription;
use App\Models\Prescription;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\Charge;
use App\Models\InvoiceDesign;
use App\Models\DepartmentReagentStock;
use App\Models\ReagentTestUsage;
use App\Models\StoreItemLot;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use App\Services\BillingService;
use App\Services\BillingLedgerService;
use App\Services\IpdDischargeBillingService;

use App\Services\MedicineInventoryService;
use App\Services\PatientService;
use App\Services\ReferralPersonService;
use App\Services\ReferralCommissionCalculator;
use App\Services\LedgerService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use App\Support\DefaultDeveloperManager;
use Exception;
use App\Models\PharmacyBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BillingController extends Controller
{
    use SystemTrait;

    protected $billingService, $medicineInventoryService, $adminService, $patientService, $referrerService;
    protected $ledgerService;
    protected $billingLedgerService;

    public function __construct(BillingService $billingService, MedicineInventoryService $medicineInventoryService, AdminService $adminService, PatientService $patientService, ReferralPersonService $referrerService, LedgerService $ledgerService, BillingLedgerService $billingLedgerService)
    {
        $this->billingService = $billingService;
        $this->medicineInventoryService = $medicineInventoryService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;
        $this->referrerService = $referrerService;
        $this->ledgerService = $ledgerService;
        $this->billingLedgerService = $billingLedgerService;

        $this->middleware('auth:admin');

        // Add permission middleware
        $this->middleware('permission:billing', ['only' => ['index']]);
        $this->middleware('permission:billing|billing-create|billing-add|pharmacy-bill-create', ['only' => ['create', 'billing', 'store', 'searchPrescription', 'searchPrescriptionSuggestions']]);
        $this->middleware('permission:billing-create|billing-add|pharmacy-bill-create', ['only' => ['billingPage']]);
        $this->middleware('permission:billing|billing-create|billing-add|billing-edit|pharmacy-bill-edit', ['only' => ['searchBillingMedicines']]);
        $this->middleware('permission:billing-delete', ['only' => ['destroy']]);
        $this->middleware('permission:billing-edit|pharmacy-bill-edit', ['only' => ['edit', 'update']]);
    }



    public function index(Request $request)
    {
        if (user_is_pharmacy_only(auth('admin')->user())) {
            $pharmacyFilters = array_filter([
                'pharmacy_no' => $request->get('search'),
                'numOfData' => $request->get('numOfData', 10),
            ], static fn ($value) => $value !== null && $value !== '');

            return redirect()->route('backend.pharmacybill.index', $pharmacyFilters);
        }

        $requestedPerPage = $request->get('numOfData', 10);
        $perPage = strtolower((string) $requestedPerPage) === 'all'
            ? 'all'
            : max(1, (int) $requestedPerPage);
        $isIpdBilling = $request->filled('ipd');

        return Inertia::render('Backend/Billing/Index', [
            'pageTitle' => fn () => $isIpdBilling ? 'IPD Bill List' : 'Billing List',
            'tableHeaders' => fn() => $this->getTableHeaders($isIpdBilling),
            'dataFields' => fn() => $this->dataFields($isIpdBilling),
            'actionColumnFirst' => false,
            'datas' => fn () => $this->getDatas($perPage),
            'filters' => fn () => ['numOfData' => $perPage, 'user' => request()->user ?? '', 'ipd' => $request->ipd ?? '', 'category' => $request->category ?? ''],
            'isIpdBilling' => fn () => $isIpdBilling,
            'isDeveloper' => fn () => DefaultDeveloperManager::isDeveloper(auth('admin')->user()),
            'userStats' => fn() => $this->getUserStats(request()),
        ]);
    }

    public function exportAllBillsCsv()
    {
        $this->ensureDeveloperBillingCsvAccess();

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Bill Number',
                'Patient',
                'Total',
                'Paid',
                'Due',
                'Payment Status',
                'Billing Data JSON',
                'Bill Items JSON',
                'Payments JSON',
                'Ledger JSON',
            ], ',', '"', '');

            Billing::query()
                ->whereNull('deleted_at')
                ->where('case_number', 'not like', 'IPD-%')
                ->whereNotIn('id', PharmacyBill::query()
                    ->whereNotNull('billing_id')
                    ->select('billing_id'))
                ->orderBy('id')
                ->chunkById(250, function ($billings) use ($output) {
                    $billIds = $billings->pluck('id')->all();
                    $itemsByBill = DB::table('bill_items')->whereIn('billing_id', $billIds)->get()->groupBy('billing_id');
                    $paymentsByBill = DB::table('payments')->whereIn('billing_id', $billIds)->get()->groupBy('billing_id');
                    $transactions = DB::table('ledger_transactions')
                        ->where('reference_type', 'Billing')
                        ->whereIn('reference_id', $billIds)
                        ->get()
                        ->groupBy('reference_id');
                    $transactionIds = $transactions->flatten()->pluck('id')->all();
                    $entriesByTransaction = DB::table('ledger_entries')
                        ->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
                        ->whereIn('ledger_entries.transaction_id', $transactionIds)
                        ->select('ledger_entries.*', 'accounts.code as account_code')
                        ->get()
                        ->groupBy('transaction_id');

                    foreach ($billings as $billing) {
                        $patientName = (string) ($billing->patient_name ?? '');
                        if (preg_match('/^[\t\r\n ]*[=+\-@]/', $patientName)) {
                            $patientName = "'" . $patientName;
                        }

                        $ledgerData = ($transactions->get($billing->id) ?? collect())
                            ->map(function ($transaction) use ($entriesByTransaction) {
                                return [
                                    'transaction' => (array) $transaction,
                                    'entries' => ($entriesByTransaction->get($transaction->id) ?? collect())
                                        ->map(static fn ($entry) => [
                                            'account_code' => $entry->account_code,
                                            'amount' => $entry->amount,
                                            'entry_type' => $entry->entry_type,
                                            'narration' => $entry->narration ?? null,
                                        ])->values()->all(),
                                ];
                            })->values()->all();

                        fputcsv($output, [
                            $billing->bill_number,
                            $patientName,
                            $billing->total,
                            $billing->paid_amt,
                            $billing->due_amount,
                            $billing->payment_status,
                            json_encode($billing->getAttributes(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            json_encode(($itemsByBill->get($billing->id) ?? collect())->map(static fn ($item) => (array) $item)->values()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            json_encode(($paymentsByBill->get($billing->id) ?? collect())->map(static fn ($payment) => (array) $payment)->values()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            json_encode($ledgerData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ], ',', '"', '');
                    }
                });

            fclose($output);
        }, 'billing-list-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importBillsCsv(Request $request)
    {
        $this->ensureDeveloperBillingCsvAccess();

        $validator = Validator::make($request->all(), [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('backend.billing.list')
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();

        $handle = fopen($validated['csv_file']->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->route('backend.billing.list')
                ->withErrors(['csv_file' => 'The uploaded CSV file could not be opened.']);
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        $expectedHeaders = [
            'Bill Number', 'Patient', 'Total', 'Paid', 'Due', 'Payment Status',
            'Billing Data JSON', 'Bill Items JSON', 'Payments JSON', 'Ledger JSON',
        ];
        if (!is_array($headers)) {
            fclose($handle);
            return redirect()->route('backend.billing.list')
                ->withErrors(['csv_file' => 'The CSV file is empty or invalid.']);
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        if ($headers !== $expectedHeaders) {
            fclose($handle);
            return redirect()->route('backend.billing.list')
                ->withErrors(['csv_file' => 'Please upload a CSV downloaded from the Billing List page.']);
        }

        $rows = [];
        $seenBillNumbers = [];
        $lineNumber = 1;
        while (($columns = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lineNumber++;
            if (count(array_filter($columns, static fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($columns) !== count($expectedHeaders)) {
                fclose($handle);
                return redirect()->route('backend.billing.list')
                    ->withErrors(['csv_file' => "CSV row {$lineNumber} has an invalid number of columns."]);
            }

            try {
                $billingData = json_decode((string) $columns[6], true, 512, JSON_THROW_ON_ERROR);
                $billItems = json_decode((string) $columns[7], true, 512, JSON_THROW_ON_ERROR);
                $payments = json_decode((string) $columns[8], true, 512, JSON_THROW_ON_ERROR);
                $ledgerData = json_decode((string) $columns[9], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                fclose($handle);
                return redirect()->route('backend.billing.list')
                    ->withErrors(['csv_file' => "CSV row {$lineNumber} contains invalid bill data."]);
            }

            if (!is_array($billingData) || !is_array($billItems) || !is_array($payments) || !is_array($ledgerData)) {
                fclose($handle);
                return redirect()->route('backend.billing.list')
                    ->withErrors(['csv_file' => "CSV row {$lineNumber} is incomplete or inconsistent."]);
            }
            $billNumber = trim((string) ($billingData['bill_number'] ?? ''));
            $caseNumber = trim((string) ($billingData['case_number'] ?? ''));
            if ($billNumber === '' || str_starts_with($caseNumber, 'IPD-') || $billNumber !== trim((string) $columns[0])) {
                fclose($handle);
                return redirect()->route('backend.billing.list')
                    ->withErrors(['csv_file' => "CSV row {$lineNumber} is incomplete or inconsistent."]);
            }
            if (isset($seenBillNumbers[$billNumber])) {
                fclose($handle);
                return redirect()->route('backend.billing.list')
                    ->withErrors(['csv_file' => "Bill {$billNumber} appears more than once in the CSV."]);
            }

            $seenBillNumbers[$billNumber] = true;
            $rows[] = compact('billingData', 'billItems', 'payments', 'ledgerData', 'billNumber');
            if (count($rows) > 5000) {
                fclose($handle);
                return redirect()->route('backend.billing.list')
                    ->withErrors(['csv_file' => 'A maximum of 5,000 bills can be imported at a time.']);
            }
        }
        fclose($handle);

        if ($rows === []) {
            return redirect()->route('backend.billing.list')
                ->withErrors(['csv_file' => 'The CSV file contains no bill records.']);
        }

        $imported = 0;
        $skipped = 0;
        $importingAdminId = (int) auth('admin')->id();
        DB::transaction(function () use ($rows, $importingAdminId, &$imported, &$skipped) {
            $billingColumns = array_flip(Schema::getColumnListing('billings'));
            $billItemColumns = array_flip(Schema::getColumnListing('bill_items'));
            $paymentColumns = array_flip(Schema::getColumnListing('payments'));

            foreach ($rows as $row) {
                if (DB::table('billings')->where('bill_number', $row['billNumber'])->exists()) {
                    $skipped++;
                    continue;
                }

                $billingData = array_intersect_key($row['billingData'], $billingColumns);
                unset($billingData['id']);
                $billingData['created_by'] = $importingAdminId;
                $billingData['updated_by'] = null;
                $billingId = DB::table('billings')->insertGetId($billingData);

                foreach ($row['billItems'] as $item) {
                    if (!is_array($item)) {
                        throw new \UnexpectedValueException("Bill {$row['billNumber']} contains an invalid item.");
                    }
                    $item = array_intersect_key($item, $billItemColumns);
                    unset($item['id']);
                    $item['billing_id'] = $billingId;
                    foreach (['sample_collected_by', 'reported_by', 'delivered_by'] as $adminField) {
                        if (array_key_exists($adminField, $item)) {
                            $item[$adminField] = null;
                        }
                    }
                    if (
                        !empty($item['department_id'])
                        && !DB::table('departments')->where('id', $item['department_id'])->exists()
                    ) {
                        $item['department_id'] = null;
                    }
                    DB::table('bill_items')->insert($item);
                }

                foreach ($row['payments'] as $payment) {
                    if (!is_array($payment)) {
                        throw new \UnexpectedValueException("Bill {$row['billNumber']} contains an invalid payment.");
                    }
                    $payment = array_intersect_key($payment, $paymentColumns);
                    unset($payment['id']);
                    $payment['billing_id'] = $billingId;
                    if (array_key_exists('received_by', $payment)) {
                        $payment['received_by'] = $importingAdminId;
                    }
                    DB::table('payments')->insert($payment);
                }

                foreach ($row['ledgerData'] as $ledgerRecord) {
                    if (!is_array($ledgerRecord) || !is_array($ledgerRecord['transaction'] ?? null) || !is_array($ledgerRecord['entries'] ?? null)) {
                        throw new \UnexpectedValueException("Bill {$row['billNumber']} contains invalid accounting data.");
                    }
                    $transaction = $ledgerRecord['transaction'];
                    $lines = [];
                    foreach ($ledgerRecord['entries'] as $entry) {
                        if (!is_array($entry) || empty($entry['account_code'])) {
                            throw new \UnexpectedValueException("Bill {$row['billNumber']} contains an invalid ledger entry.");
                        }
                        $lines[] = [
                            'account_code' => $entry['account_code'],
                            'amount' => $entry['amount'] ?? null,
                            'entry_type' => $entry['entry_type'] ?? null,
                            'narration' => $entry['narration'] ?? null,
                        ];
                    }
                    app(LedgerService::class)->recordTransaction(
                        $lines,
                        (string) ($transaction['description'] ?? ''),
                        $transaction['date'] ?? null,
                        (string) ($transaction['reference_type'] ?? 'Billing'),
                        $billingId,
                        $importingAdminId
                    );
                }

                $imported++;
            }
        });

        return redirect()->route('backend.billing.list')
            ->with('successMessage', "Imported {$imported} bills; skipped {$skipped} bills already present.");
    }

    private function ensureDeveloperBillingCsvAccess(): void
    {
        abort_unless(DefaultDeveloperManager::isDeveloper(auth('admin')->user()), 403);
    }

    private function getDatas(int|string $perPage)
    {
        $query = Billing::query()->with(['patient', 'creator']);

        if (request()->filled('ipd')) {
            $query->where('case_number', 'like', 'IPD-%');
        } else {
            $query->where('case_number', 'not like', 'IPD-%');
            $query->whereNotIn('id', PharmacyBill::query()
                ->whereNotNull('billing_id')
                ->select('billing_id'));
        }

        // Newest bills should appear first. `id desc` is more reliable for
        // sequential billing order than `created_at` when edits preserve old timestamps.
        $query->orderBy('id', 'desc');

        // Apply simple search from request (bill_number, case_number, patient name via relation, patient_mobile)
        if (request()->filled('search')) {
            $s = trim(request()->search);
            // match billing fields directly and also patient name via relation
            $query->where(function ($q) use ($s) {
                $q->where('bill_number', 'like', "%{$s}%")
                  ->orWhere('case_number', 'like', "%{$s}%")
                  ->orWhere('patient_mobile', 'like', "%{$s}%")
                  ->orWhereHas('patient', function ($p) use ($s) {
                      $p->where('name', 'like', "%{$s}%");
                  });
            });
        }

        if (request()->filled('user')) {
            // match admin by first/last name or full name (Admin model has computed `name`)
            $userName = trim(request()->user);
            $adminIdsQuery = \App\Models\Admin::query()
                ->where('first_name', 'like', "%{$userName}%")
                ->orWhere('last_name', 'like', "%{$userName}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$userName}%"]);

            $adminIds = $adminIdsQuery->pluck('id')->toArray();
            if (count($adminIds)) {
                $query->whereIn('created_by', $adminIds);
            }
        }

        if (request()->filled('category')) {
            $category = trim((string) request()->category);
            $query->whereHas('billItems', function ($itemQuery) use ($category) {
                $itemQuery->whereRaw('LOWER(category) = ?', [strtolower($category)]);
            });
        }

        if (request()->filled('main_category')) {
            $mainCategory = \App\Models\MainCategory::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) request()->main_category))])
                ->first();

            $testIds = $mainCategory
                ? \App\Models\Test::query()
                    ->where('main_category_id', $mainCategory->id)
                    ->pluck('id')
                : collect();

            $query->whereHas('billItems', function ($itemQuery) use ($testIds) {
                $itemQuery->whereIn('item_id', $testIds);
            });
        }

        $pageSize = $perPage === 'all'
            ? max(1, (clone $query)->count())
            : max(1, $perPage);
        $datas = $query->paginate($pageSize)->withQueryString();

        $ipdPatients = collect();
        $billingIds = $datas->pluck('id')->filter()->values()->all();
        if (!empty($billingIds)) {
            $ipdPatients = IpdPatient::query()
                ->whereIn('billing_id', $billingIds)
                ->get()
                ->keyBy('billing_id');
        }

        $formated = $datas->map(function ($item, $index) use ($datas, $ipdPatients) {
            $row = new \stdClass();
            $row->index = ($datas->currentPage() - 1) * $datas->perPage() + $index + 1;
            $row->bill_number = $item->bill_number;
            $row->patient_id = $item->patient_name ?? ($item->patient?->name ?? 'N/A');
            $grossTotal = (float) ($item->total ?? 0);
            $row->gross_amount = number_format($grossTotal, 2);
            $discount = strtolower((string) ($item->discount_type ?? '')) === 'percentage'
                ? $grossTotal * (float) ($item->discount ?? 0) / 100
                : (float) ($item->discount ?? 0);
            $netPayable = $item->payable_amount
                ?? max(0, $grossTotal - $discount - (float) ($item->extra_flat_discount ?? 0));
            $row->total = number_format((float) $netPayable, 2);
            $row->paid_amt = number_format($item->paid_amt ?? 0, 2);
            // raw numeric due amount (for modal logic) and formatted display
            $row->due_amount = isset($item->due_amount) ? (float) $item->due_amount : 0.0;
            $row->due_amount_display = number_format($row->due_amount ?? 0, 2);

            // identification used by frontend actions/modals
            $row->row_type = 'billing';
            $row->row_id = $item->id;
            if (!empty($item->delivery_date)) {
                try {
                    $row->delivery_date = ($item->delivery_date instanceof \DateTime)
                        ? $item->delivery_date->format('d-m-Y h:i A')
                        : Carbon::parse($item->delivery_date)->format('d-m-Y h:i A');
                } catch (\Throwable $e) {
                    $row->delivery_date = (string) $item->delivery_date;
                }
            } else {
                $row->delivery_date = '';
            }
            $row->created_by = $item->creator?->name ?? ($item->created_by ? (string)$item->created_by : 'N/A');
            $row->payment_status = $item->payment_status ?? '';
            if (!empty($item->created_at)) {
                try {
                    $row->created_at = ($item->created_at instanceof \DateTime)
                        ? $item->created_at->format('d-m-Y h:i A')
                        : Carbon::parse($item->created_at)->format('d-m-Y h:i A');
                } catch (\Throwable $e) {
                    $row->created_at = (string) $item->created_at;
                }
            } else {
                $row->created_at = '';
            }

            $row->ipd_invoice_number = '';
            $row->ipd_patient_id = null;
            if (str_starts_with((string) ($item->case_number ?? ''), 'IPD-')) {
                $ipdpatient = $ipdPatients->get($item->id);
                if ($ipdpatient) {
                    $row->ipd_patient_id = $ipdpatient->id;
                    $ipdInvoiceNumber = function_exists('prefixed_serial')
                        ? prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4)
                        : ('IPD' . str_pad((string) $ipdpatient->id, 4, '0', STR_PAD_LEFT));

                    $row->ipd_invoice_number = '<a href="' . route('backend.print.ipd.invoice', ['id' => $ipdpatient->id, 'billing_id' => $item->id]) . '" target="_blank" rel="noopener">' . e($ipdInvoiceNumber) . '</a>';
                }
            }

            $row->hasLink = true;
            $row->links = [];

            // Due / New Collect button when there's a due and user has permission
            if (
                ($item->payment_status ?? '') !== 'Paid' &&
                (float) ($item->due_amount ?? 0) > 0 &&
                \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-due-collect')
            ) {
                $row->links[] = [
                    'linkClass' => 'bg-purple-600 text-white semi-bold',
                    'link' => url('/due-collect/' . $item->id),
                    'linkLabel' => getLinkLabel('Due Collect', null, null),
                    'action_name' => 'due-collect',
                    'action_id' => 'billing|' . $item->id,
                ];
            }

            // basic action buttons (use URLs to avoid missing named routes)
            // Use public download endpoint with query param as a robust fallback
            // (avoids named-route or route-prefix issues and works when route cache
            // may be stale). This endpoint maps to InvoiceController::downloadInvoice.
            if (!empty($row->ipd_patient_id)) {
                // For IPD bills, make the Invoice button open the IPD Final Bill print view
                // so it behaves like the "Open in New Tab" print action.
                try {
                    $printUrl = route('backend.print.ipd.final-bill', ['id' => $row->ipd_patient_id, 'fast_open' => 1]);
                } catch (\Throwable $e) {
                    // fallback to a robust URL using the public endpoint
                    $printUrl = url('/print/ipd/final-bill?id=' . urlencode((string) $row->ipd_patient_id) . '&fast_open=1');
                }

                $row->links[] = [
                    'linkClass' => 'bg-green-600 text-white',
                    'link' => $printUrl,
                    'linkLabel' => getLinkLabel('Invoice', null, null),
                    'target' => '_blank',
                ];
            } else {
                $row->links[] = [
                    'linkClass' => 'bg-green-600 text-white',
                    'link' => url('/download-invoice?id=' . $item->id . '&module=billing&fast_open=1'),
                    'linkLabel' => getLinkLabel('Invoice', null, null),
                    'target' => '_blank',
                ];
            }

            if (\Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-edit')) {
                if (!empty($row->ipd_patient_id)) {
                    // For IPD-linked bills, the Edit action should take the user
                    // to the IPD patient show page so they can view/edit the
                    // admission details. Open in the same tab for smoother flow.
                    try {
                        $editUrl = route('backend.ipdpatient.show', $row->ipd_patient_id);
                    } catch (\Throwable $e) {
                        $editUrl = url('/ipdpatient/' . $row->ipd_patient_id);
                    }
                } else {
                    $editUrl = url('/billing/' . $item->id . '/edit');
                }

                $row->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black',
                    'link' => $editUrl,
                    'linkLabel' => getLinkLabel('Edit', null, null),
                ];
            }

            if (\Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-delete')) {
                $row->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white',
                    'link' => url('/billing/' . $item->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            return $row;
        });

        return regeneratePagination($formated, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function getUserStats(Request $request)
    {
        if (! $request->filled('user')) {
            return [];
        }

        $userName = trim($request->user);
        $isIpd = $request->filled('ipd');
        $adminIds = \App\Models\Admin::query()
            ->where('first_name', 'like', "%{$userName}%")
            ->orWhere('last_name', 'like', "%{$userName}%")
            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$userName}%"])
            ->pluck('id')
            ->toArray();
        if (!count($adminIds)) return [];

        $query = Billing::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->whereIn('created_by', $adminIds)
            ->when($isIpd, function ($q) {
                $q->where('case_number', 'like', 'IPD-%');
            }, function ($q) {
                $q->where('case_number', 'not like', 'IPD-%');
            })
            ->selectRaw(
                "DATE(created_at) as date, COUNT(*) as count, SUM(total) as total_amount, SUM(COALESCE(paid_amt, 0) - COALESCE(return_amt, 0)) as total_net_income, SUM(COALESCE(due_amount, 0)) as due_pending_income, SUM(CASE WHEN payable_amount IS NOT NULL THEN GREATEST(0, COALESCE(total, 0) - GREATEST(0, COALESCE(payable_amount, 0) - COALESCE(vat_amount, 0))) ELSE (CASE WHEN discount_type = 'percentage' THEN COALESCE(total, 0) * COALESCE(discount, 0) / 100 ELSE COALESCE(discount, 0) END + COALESCE(extra_flat_discount, 0)) END) as total_discount"
            )
            ->groupBy('date');

        try {
            $today = Carbon::today()->toDateString();
            // Put today's date on top, then order remaining by date descending
            $query->orderByRaw("(DATE(created_at) = ?) DESC", [$today])
                  ->orderBy('date', 'desc');
        } catch (\Throwable $e) {
            // Fallback to default ordering if DB doesn't support functions
            $query->orderBy('date', 'desc');
        }

        $stats = $query->get()
            ->map(function ($r) {
                return [
                    'date' => $r->date,
                    'count' => (int) $r->count,
                    'total_amount' => isset($r->total_amount) ? (float) $r->total_amount : 0.0,
                    'total_discount' => isset($r->total_discount) ? (float) $r->total_discount : 0.0,
                    'due_pending_income' => isset($r->due_pending_income) ? (float) $r->due_pending_income : 0.0,
                    'total_net_income' => isset($r->total_net_income) ? (float) $r->total_net_income : 0.0,
                ];
            })->toArray();

        return $stats;
    }

    private function parseBillingSearchDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Ignore invalid date formats and continue trying.
            }
        }

        return null;
    }

    private function isDisposableBillingItem(array $item): bool
    {
        $category = strtolower(trim((string) ($item['category'] ?? '')));
        if ($category === 'disposable') {
            return true;
        }

        if ($category !== 'pathology') {
            return false;
        }

        $name = strtolower((string) ($item['name'] ?? ''));
        return str_contains($name, 'disposable')
            || str_contains($name, 'tube')
            || str_contains($name, 'butterfly')
            || str_contains($name, 'needle');
    }

    private function isDiscountExemptBillingItem(array $item): bool
    {
        if ((bool) ($item['discount_exempt'] ?? false)) {
            return true;
        }

        // Locked at 0% means Discount Off — never apply bill percentage discount.
        return (bool) ($item['discount_rate_locked'] ?? false)
            && (float) ($item['discount_percentage'] ?? 0) <= 0;
    }

    private function resolveBillingItemDiscountPercent(array $item, float $basePercent): float
    {
        if ($this->isDisposableBillingItem($item) || $this->isDiscountExemptBillingItem($item)) {
            return 0.0;
        }

        $basePercent = max(0.0, min(100.0, $basePercent));
        $itemLimit = max(0.0, min(100.0, (float) ($item['discount_percentage'] ?? 0)));

        try {
            $test = Test::find($item['id'] ?? null);
            if ($test && (float) ($test->discount_percentage ?? 0) > 0) {
                $itemLimit = max(0.0, min(100.0, (float) $test->discount_percentage));
            }
        } catch (\Throwable $e) {
            // Keep payload-provided item limit when test lookup fails.
        }

        // Discount Off is handled above. Otherwise Item Discount % itself applies;
        // a bill % can only lower it (cap), not replace a missing bill discount with 0.
        $effectivePercent = $itemLimit > 0
            ? ($basePercent > 0 ? min($basePercent, $itemLimit) : $itemLimit)
            : $basePercent;

        $categoryCap = $this->resolveCategoryBillingDiscountCap((string) ($item['category'] ?? ''));
        if ($categoryCap !== null && $effectivePercent > 0) {
            $effectivePercent = min($effectivePercent, $categoryCap);
        }

        return max(0.0, min(100.0, $effectivePercent));
    }

    private function resolveCategoryBillingDiscountCap(string $category): ?float
    {
        if (!function_exists('get_cached_web_setting')) {
            return null;
        }

        $setting = get_cached_web_setting();
        $options = $setting?->attendance_device_options ?? [];
        if (is_string($options)) {
            $options = json_decode($options, true);
        }
        if (!is_array($options) || !($options['billing_discount_cap_by_category_enabled'] ?? false)) {
            return null;
        }

        $caps = $options['billing_category_discount_caps'] ?? [];
        if (!is_array($caps)) {
            return null;
        }
        foreach ($caps as $key => $value) {
            if (strcasecmp(trim((string) $key), trim($category)) === 0 && is_numeric($value)) {
                return max(0.0, min(100.0, (float) $value));
            }
        }

        return null;
    }

    private function resolveCategoryBillingRoomNo(string $category): ?string
    {
        if (!function_exists('get_cached_web_setting')) {
            return null;
        }

        $options = get_cached_web_setting()?->attendance_device_options ?? [];
        if (is_string($options)) {
            $options = json_decode($options, true);
        }
        if (!is_array($options) || !($options['billing_room_no_by_category_enabled'] ?? false)) {
            return null;
        }

        $roomNumbers = $options['billing_category_room_numbers'] ?? [];
        if (!is_array($roomNumbers)) {
            return null;
        }
        foreach ($roomNumbers as $key => $value) {
            if (strcasecmp(trim((string) $key), trim($category)) === 0) {
                $roomNumber = trim((string) $value);
                return $roomNumber !== '' ? $roomNumber : null;
            }
        }

        return null;
    }

    private function dataFields(bool $includeIpdInvoice = false)
    {
        $fields = [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
        ];

        if ($includeIpdInvoice) {
            $fields[] = ['fieldName' => 'ipd_invoice_number', 'class' => 'text-center whitespace-nowrap'];
        }

        $fields = array_merge($fields, [
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'gross_amount', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'due_amount_display', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ['fieldName' => 'delivery_date', 'class' => 'text-center whitespace-nowrap'],
            ['fieldName' => 'created_by', 'class' => 'text-center'],
            ['fieldName' => 'created_at', 'class' => 'text-center whitespace-nowrap'],
        ]);

        return $fields;
    }
    private function getTableHeaders(bool $includeIpdInvoice = false)
    {
        $headers = [
            'Sl/No',
            'Bill Number',
        ];

        if ($includeIpdInvoice) {
            $headers[] = 'IPD Invoice';
        }

        return array_merge($headers, [
            'Patient',
            'Total Amount',
            'Payable Amount',
            'Paid Amount',
            'Due Amount',
            'Payment Status',
            'Delivery Date',
            'Created By',
            'Created Date',
            'Action',
        ]);
    }

        public function searchShow(Request $request)
    {
        $request->validate([
            'case_id' => 'required|string',
            // When true, we are allowed to create an IPD auto bill if no billing exists yet.
            // We keep it false for debounced (auto) searching.
            'auto_create' => 'nullable|boolean',
        ]);

        $caseId = trim((string) $request->case_id);
        $autoCreate = (bool) $request->boolean('auto_create');

        $mapBilling = function (Billing $billing) {
            return [
                'id' => $billing->id,
                'case_number' => $billing->case_number,
                'patient_name' => $billing->patient?->name ?? 'N/A',
                'patient_mobile' => $billing->patient?->phone ?? 'N/A',
                'created_at' => $billing->created_at?->format('d M Y h:i A') ?? '',
                'status' => $billing->status,
                'payment_status' => $billing->payment_status,
            ];
        };

        $results = Billing::query()
            ->where(function ($q) use ($caseId) {
                $q->where('case_number', 'like', '%' . $caseId . '%')
                  ->orWhere('bill_number', 'like', '%' . $caseId . '%')
                  ->orWhere('invoice_number', 'like', '%' . $caseId . '%');
            })
            ->with(['patient', 'doctor'])
            ->limit(10)
            ->get()
            ->map($mapBilling);

        // If nothing found and user explicitly asked => try IPD auto billing.
        if ($results->isEmpty() && $autoCreate) {
            $ipdId = $this->extractIpdIdFromSearch($caseId);

            if ($ipdId) {
                $ipdpatient = IpdPatient::query()->find($ipdId);

                if ($ipdpatient) {
                    /** @var IpdDischargeBillingService $service */
                    $service = app(IpdDischargeBillingService::class);

                    // Works both for Discharged & Active patients.
                    // For Active patients, it uses "now" as dischargeAt to build a provisional bill.
                    $billing = $service->createOrGetForDischarge($ipdpatient, auth('admin')->id());

                    if (empty($ipdpatient->billing_id)) {
                        $ipdpatient->billing_id = $billing->id;
                        $ipdpatient->save();
                    }

                    $billing->loadMissing(['patient', 'doctor']);

                    $results = collect([$mapBilling($billing)]);
                }
            }
        }

        return response()->json($results);
    }

    private function extractIpdIdFromSearch(string $caseId): ?int
    {
        $caseId = trim($caseId);

        // Accept: "123", "IPD-000123", "ipd 123"
        if (preg_match('/^ipd\s*[-_]?\s*0*(\d+)$/i', $caseId, $m)) {
            return (int) $m[1];
        }

        if (ctype_digit($caseId)) {
            return (int) $caseId;
        }

        return null;
    }



    public function create()
    {
        return Inertia::render(
            'Backend/Billing/Form',
            [
                'pageTitle' => fn() => 'Billing Create',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Billing Manage'],
                    ['link' => route('backend.billing.create'), 'title' => 'Billing Create'],
                ],
            ]
        );
    }

    public function billing()
    {
        return Inertia::render(
            'Backend/Billing/Billing',
            [
                'pageTitle' => fn() => 'Billing Create',
                // 'breadcrumbs' => fn() => [
                //     ['link' => null, 'title' => 'Billing Manage'],
                //     ['link' => route('backend.billing.create'), 'title' => 'Billing Create'],
                // ],
            ]
        );
    }

    public function billingPage(Request $request)
    {
        $lastPathology = Pathology::latest()->first();
        $lastBillNumber = $lastPathology ? $lastPathology->bill_no : null;

        $authInfo = $this->adminService->getAuthInfo();
        $invoiceModule = $request->boolean('pharmacy_mode') ? 'pharmacy' : 'billing';
        $invoiceDesign = $this->resolveFastInvoiceDesign($invoiceModule);

        return Inertia::render(
            'Backend/Billing/BillingPage',
            [
                'pageTitle' => fn() => $request->boolean('pharmacy_mode') ? 'Pharmacy Billing' : 'Billing Page',
                'prescriptionSearchValue' => fn() => $this->pharmacyPrescriptionSearchValue($request),
                'billnumber' => fn() => $lastBillNumber,
                'pathologyAndRadiologyTests' => Inertia::defer(fn() => $this->getBillingTests($this->getBillingCategoryTypes(), 'billing:tests:all:v2'), 'billing-catalog'),
                'reagentTestAvailability' => Inertia::defer(fn() => $this->getBillingReagentAvailability(), 'billing-catalog'),
                'hospitalCharges' => Inertia::defer(fn() => $this->getHospitalCharges(), 'billing-catalog'),
                'departmentStoreItems' => Inertia::defer(fn() => $this->getBillingDepartmentStoreItems(), 'billing-catalog'),
                'billingCatalogLoaded' => Inertia::defer(fn() => true, 'billing-catalog'),
                'isPharmacyDepartment' => fn() => user_is_pharmacy_only(auth('admin')->user()) || $request->boolean('pharmacy_mode'),
                'canAccessPharmacyBillList' => fn() => auth('admin')->user()->can('pharmacy-bill-list'),
                'doctors' => Inertia::defer(fn() => $this->getBillingDoctors(), 'billing-support'),
                'patients' => Inertia::defer(fn() => $this->getBillingPatients(), 'billing-support'),
                'referrers' => Inertia::defer(fn() => $this->getBillingReferrers(), 'billing-support'),
                'authInfo' => fn() => $authInfo,
                'offlineInvoiceDesign' => fn() => [
                    'header_image' => $this->fastInvoiceImageUrl($invoiceDesign?->header_photo_path),
                    'footer_image' => $this->fastInvoiceImageUrl($invoiceDesign?->footer_photo_path),
                    'full_page_pad_image' => $this->fastInvoiceImageUrl($invoiceDesign?->full_page_pad_path),
                    'full_page_pad_content_offset' => (int) ($invoiceDesign?->full_page_pad_content_offset ?? 0),
                    'footer_content' => (string) ($invoiceDesign?->footer_content ?? ''),
                    'footer_content_offset' => (int) ($invoiceDesign?->footer_content_offset ?? 0),
                    'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below'], true)
                        ? strtolower((string) $invoiceDesign->footer_content_position)
                        : 'above',
                    'footer_font_size' => max(6, min(72, (int) ($invoiceDesign?->footer_font_size ?? 14))),
                    'footer_fallback_line' => trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638')),
                    'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
                    'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
                    'active_pad_type' => (string) ($invoiceDesign?->active_pad_type ?? 'header'),
                ],
                'testCategories' => fn() => \App\Models\TestCategory::query()->whereNull('deleted_at')->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'categoryTypeOptions' => fn() => app(\App\Services\TestCategoryService::class)->activeNames(),
                'mainCategories' => fn() => \App\Models\MainCategory::query()->whereNull('deleted_at')->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'pathologyUnits' => fn() => app(\App\Services\PathologyUnitService::class)->activeList(),
                'testParameters' => fn() => app(\App\Services\PathologyParameterService::class)->activeList(),
                'chargeTypes' => fn() => \App\Models\ChargeType::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'chargeCategories' => fn() => \App\Models\ChargeCategory::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'chargeUnits' => fn() => \App\Models\ChargeUnitType::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'taxCategories' => fn() => \App\Models\ChargeTaxCategory::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            ]
        );
    }

    /**
     * Return normalized hospital charges as JSON for frontend fetch.
     */
    public function hospitalChargesList()
    {
        return response()->json($this->getHospitalCharges());
    }

    private function getBillingTests(array $categories, string $cacheKey): array
    {
        $version = (int) Cache::get('billing:tests:cache_version', 1);
        $cacheKey .= ':v' . $version . ':' . md5(implode('|', $categories));

        return Cache::remember($cacheKey, 90, function () use ($categories) {
            return Test::whereIn('category_type', $categories)
                ->where('status', 'Active')
                ->select('id', 'category_type', 'test_name', 'test_short_name', 'report_days', 'room_no', 'tax', 'standard_charge', 'amount', 'referral_percentage', 'discount_percentage', 'discount_rate_locked')
                ->orderBy('test_name')
                ->get()
                ->map(function ($test) {
                    return [
                        'id' => $test->id,
                        'category_type' => $test->category_type,
                        'test_name' => $test->test_name,
                        'test_short_name' => $test->test_short_name,
                        'report_days' => $test->report_days,
                        'room_no' => $test->room_no ?? null,
                        'tax' => $test->tax,
                        'standard_charge' => $test->standard_charge,
                        'amount' => $test->amount,
                        'referral_percentage' => $test->referral_percentage ?? 0,
                        'discount_percentage' => (float) ($test->discount_percentage ?? 0),
                        'discount_rate_locked' => (bool) $test->discount_rate_locked,
                    ];
                })
                ->toArray();
        });
    }

    private function getBillingReagentAvailability(): array
    {
        $testUsages = ReagentTestUsage::query()
            ->with('storeItem:id,item_name,category,unit,status,stock_behavior')
            ->whereHas('test', fn ($query) => $query->where('status', 'Active')->whereNull('deleted_at'))
            ->get(['test_id', 'store_item_id', 'department_id', 'quantity_per_test'])
            ->filter(fn ($usage) => $usage->storeItem && ($usage->storeItem->stock_behavior ?? 'consumable') !== 'reusable')
            ->values();

        $categoryUsages = \App\Models\ReagentCategoryUsage::query()
            ->with('storeItem:id,item_name,category,unit,status,stock_behavior')
            ->whereHas('storeItem', fn ($query) => $query->where('status', 'Active'))
            ->get(['category_key', 'store_item_id', 'department_id', 'quantity_per_test'])
            ->filter(fn ($usage) => $usage->storeItem
                && ($usage->storeItem->stock_behavior ?? 'consumable') !== 'reusable')
            ->values();

        if ($categoryUsages->isNotEmpty()) {
            $configuredTestItems = $testUsages
                ->groupBy('test_id')
                ->map(fn ($usages) => $usages->pluck('store_item_id')->map(fn ($id) => (int) $id)->all());
            $configuredFilmTestIds = $testUsages
                ->filter(fn ($usage) => $usage->storeItem && \App\Services\ReagentConsumptionService::isFilmItem($usage->storeItem))
                ->pluck('test_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
            $categoryUsagesByItem = $categoryUsages->groupBy('store_item_id');
            $categoryService = app(\App\Services\ReagentConsumptionService::class);

            Test::query()
                ->with(['mainCategory:id,name', 'pathologyCategory:id,name'])
                ->where('status', 'Active')
                ->get(['id', 'category_type', 'main_category_id', 'test_category_id'])
                ->each(function (Test $test) use ($categoryUsagesByItem, $categoryService, $configuredTestItems, $configuredFilmTestIds, $testUsages) {
                    $categoryKeys = $categoryService->categoryKeysForTest($test);
                    foreach ($categoryUsagesByItem as $storeItemId => $itemUsages) {
                        $configuredItem = $itemUsages->first()?->storeItem;
                        if (
                            in_array((int) $test->id, $configuredFilmTestIds, true)
                            && $configuredItem
                            && \App\Services\ReagentConsumptionService::isFilmItem($configuredItem)
                        ) {
                            continue;
                        }
                        if (in_array((int) $storeItemId, $configuredTestItems->get($test->id, []), true)) {
                            continue;
                        }

                        $usage = collect($categoryKeys)
                            ->map(fn ($categoryKey) => $itemUsages->firstWhere('category_key', $categoryKey))
                            ->filter()
                            ->first();
                        if ($usage) {
                            $categoryUsage = clone $usage;
                            $categoryUsage->setAttribute('test_id', $test->id);
                            $testUsages->push($categoryUsage);
                        }
                    }
                });
        }

        $usages = $testUsages->values();

        if ($usages->isEmpty()) {
            return [];
        }

        $storeItemIds = $usages->pluck('store_item_id')->unique()->values();
        $departmentIds = $usages->pluck('department_id')->filter()->unique()->values();
        $stockKey = fn ($departmentId, $storeItemId) => $departmentId . ':' . $storeItemId;

        $departmentStocks = DepartmentReagentStock::query()
            ->whereIn('store_item_id', $storeItemIds)
            ->whereIn('department_id', $departmentIds)
            ->get(['department_id', 'store_item_id', 'current_stock'])
            ->keyBy(fn ($stock) => $stockKey($stock->department_id, $stock->store_item_id));

        $lotStocks = StoreItemLot::query()
            ->selectRaw('department_id, store_item_id, COALESCE(SUM(quantity), 0) AS available_stock')
            ->whereIn('store_item_id', $storeItemIds)
            ->whereIn('department_id', $departmentIds)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()->toDateString()))
            ->groupBy('department_id', 'store_item_id')
            ->get()
            ->keyBy(fn ($lot) => $stockKey($lot->department_id, $lot->store_item_id));

        $availability = [];
        foreach ($usages as $usage) {
            $testId = (int) $usage->test_id;
            $quantityPerTest = (float) $usage->quantity_per_test;
            if ($quantityPerTest <= 0) {
                continue;
            }

            $isFilm = \App\Services\ReagentConsumptionService::isFilmItem($usage->storeItem);
            $effectiveQuantityPerTest = $isFilm ? 1 : $quantityPerTest;
            $remainingTests = null;
            $usableStock = 0;
            if ($usage->department_id && $usage->storeItem->status === 'Active') {
                $key = $stockKey($usage->department_id, $usage->store_item_id);
                $departmentStock = (float) ($departmentStocks->get($key)?->current_stock ?? 0);
                $unexpiredLotStock = (float) ($lotStocks->get($key)?->available_stock ?? 0);
                $usableStock = min($departmentStock, $unexpiredLotStock);
                if (!$isFilm) {
                    $remainingTests = (int) floor(($usableStock + 0.000001) / $effectiveQuantityPerTest);
                }
            }

            if (!isset($availability[$testId])) {
                $availability[$testId] = [
                    'configured' => true,
                    'remaining_tests' => $isFilm ? null : $remainingTests,
                    'stock_items' => [],
                ];
            } elseif (!$isFilm) {
                $availability[$testId]['remaining_tests'] = min(
                    $availability[$testId]['remaining_tests'] ?? $remainingTests,
                    $remainingTests
                );
            }

            $availability[$testId]['stock_items'][] = [
                'id' => (int) $usage->store_item_id,
                'name' => $usage->storeItem->item_name,
                'unit' => $usage->storeItem->unit,
                'available_quantity' => round($usableStock, 2),
                'quantity_per_test' => round($effectiveQuantityPerTest, 2),
                'remaining_tests' => $remainingTests,
                'is_film' => $isFilm,
            ];
        }

        $testsById = Test::query()
            ->with(['mainCategory:id,name', 'pathologyCategory:id,name'])
            ->whereIn('id', $usages->pluck('test_id')->unique())
            ->get(['id', 'category_type', 'main_category_id', 'test_category_id'])
            ->keyBy('id');
        $filmStocksByCategory = [];
        $countedFilmStocks = [];

        foreach ($usages as $usage) {
            if (!$usage->storeItem || !\App\Services\ReagentConsumptionService::isFilmItem($usage->storeItem)) {
                continue;
            }

            $test = $testsById->get((int) $usage->test_id);
            if (!$test || !$usage->department_id) {
                continue;
            }

            $categoryKey = $usage->category_key
                ?? (app(\App\Services\ReagentConsumptionService::class)->categoryKeysForTest($test)[0] ?? null);
            if (!$categoryKey) {
                continue;
            }

            $categoryLabel = $test->mainCategory?->name
                ?: $test->pathologyCategory?->name
                ?: $test->category_type;
            $locationKey = $stockKey($usage->department_id, $usage->store_item_id);
            $stock = min(
                (float) ($departmentStocks->get($locationKey)?->current_stock ?? 0),
                (float) ($lotStocks->get($locationKey)?->available_stock ?? 0)
            );
            $categoryStockKey = $categoryKey . ':' . $usage->store_item_id . ':' . $usage->department_id;

            if (!isset($countedFilmStocks[$categoryStockKey])) {
                $countedFilmStocks[$categoryStockKey] = true;
                $filmStocksByCategory[$categoryKey] ??= [
                    'key' => $categoryKey,
                    'label' => $categoryLabel,
                    'available_quantity' => 0,
                    'unit' => $usage->storeItem->unit,
                ];
                $filmStocksByCategory[$categoryKey]['available_quantity'] += $stock;
            }
        }

        foreach ($testsById as $testId => $test) {
            $categoryKey = app(\App\Services\ReagentConsumptionService::class)
                ->categoryKeysForTest($test)[0] ?? null;
            if (!$categoryKey || !isset($filmStocksByCategory[$categoryKey], $availability[$testId])) {
                continue;
            }

            $availability[$testId]['film_category'] = $filmStocksByCategory[$categoryKey];
            $availability[$testId]['film_usage_quantity'] = 1;
        }

        return $availability;
    }

    private function getBillingCategoryTypes(): array
    {
        return TestCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($category) => strtolower($category))
            ->sort()
            ->values()
            ->all();
    }

    private function requiresSampleCollection(array $item, string $category, bool $isDisposable): bool
    {
        if ($isDisposable || in_array($category, ['medicine', 'pharmacy', 'opd', 'ipd', 'appointment', 'service', 'store inventory', 'blood bank', 'ambulance'], true)) {
            return false;
        }

        if (in_array($category, ['pathology', 'radiology', 'ecg', 'ultrasound', 'xray', 'ultrasonogram', 'ultrasonography'], true)) {
            return true;
        }

        $master = Test::find($item['id'] ?? null);
        if (!$master) {
            return false;
        }

        $parameters = $master->test_parameters;
        if (is_string($parameters)) {
            $parameters = json_decode($parameters, true);
        }

        return is_array($parameters) && count(array_filter($parameters)) > 0;
    }

    public function searchBillingMedicines(Request $request)
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:100'],
        ]);
        $search = trim($validated['search']);

        if ($search === '') {
            return response()->json([]);
        }

        $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $fullTextTokens = array_values(array_filter(array_map(
            static fn (string $token): string => preg_replace('/[^\p{L}\p{N}]+/u', '', $token) ?? '',
            $tokens
        ), static fn (string $token): bool => mb_strlen($token) >= 3));
        $booleanSearch = implode(' ', array_map(static fn (string $token): string => '+' . $token . '*', $fullTextTokens));
        $escapedPrefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';

        $baseQuery = MedicineInventory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->select('id', 'medicine_name', 'medicine_unit_selling_price', 'medicine_quantity', 'status');
        $results = collect();

        if ($booleanSearch !== '' && DB::connection()->getDriverName() === 'mysql') {
            $results = (clone $baseQuery)
                ->whereRaw('MATCH (medicine_name) AGAINST (? IN BOOLEAN MODE)', [$booleanSearch])
                ->orderBy('medicine_name')
                ->limit(30)
                ->get();
        }

        if ($results->count() < 30) {
            $prefixQuery = (clone $baseQuery)
                ->whereRaw("medicine_name LIKE ? ESCAPE '!'", [$escapedPrefix]);

            if ($results->isNotEmpty()) {
                $prefixQuery->whereNotIn('id', $results->modelKeys());
            }

            $prefixResults = $prefixQuery
                ->orderBy('medicine_name')
                ->limit(30 - $results->count())
                ->get();
            $results = $results->concat($prefixResults)->values();
        }

        return response()->json($results);
    }

    private function getBillingDepartmentStoreItems(): array
    {
        $adminId = (int) (auth('admin')->id() ?? 0);
        if ($adminId <= 0) {
            return [];
        }

        $departmentId = \App\Models\AdminDetail::query()
            ->where('admin_id', $adminId)
            ->value('department_id');
        if (!$departmentId) {
            return [];
        }

        return \App\Models\DepartmentReagentStock::query()
            ->with(['storeItem', 'department:id,name'])
            ->addSelect(['available_stock' => \App\Models\StoreItemLot::query()
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('store_item_lots.store_item_id', 'department_reagent_stocks.store_item_id')
                ->whereColumn('store_item_lots.department_id', 'department_reagent_stocks.department_id')
                ->where(function ($query) {
                    $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()->toDateString());
                })])
            ->where('department_id', $departmentId)
            ->where('current_stock', '>', 0)
            ->whereHas('storeItem', function ($query) {
                $query->where('status', 'Active')
                    ->where('stock_behavior', 'consumable');
            })
            ->orderBy('store_item_id')
            ->get()
            ->map(function ($stock) use ($departmentId) {
                $item = $stock->storeItem;

                return [
                    'id' => (int) $item->id,
                    'name' => $item->item_name,
                    'category' => 'Store Inventory',
                    'stock_category' => $item->category,
                    'unit_price' => (float) $item->unit_cost,
                    'stock' => (float) ($stock->available_stock ?? 0),
                    'stock_unit' => $item->unit,
                    'department_id' => (int) $departmentId,
                    'department_name' => $stock->department?->name,
                    'type' => 'store',
                ];
            })
            ->filter(fn ($item) => $item['stock'] > 0)
            ->values()
            ->all();
    }

    private function getBillingDoctors(): array
    {
        return Cache::remember('billing:doctors_active', 90, function () {
            return Admin::whereNull('deleted_at')
                ->where('status', 'Active')
                ->whereHas('role', function ($query) {
                    $query->where('name', 'Doctor');
                })
                ->select('id', 'first_name', 'last_name')
                ->get()
                ->map(function ($doctor) {
                    return [
                        'id' => $doctor->id,
                        'name' => $doctor->name,
                    ];
                })
                ->toArray();
        });
    }

    private function getBillingPatients(): array
    {
        return Cache::remember('billing:patients_active', 90, function () {
            return Patient::whereNull('deleted_at')
                ->where('status', 'Active')
                ->select('id', 'name', 'phone', 'gender', 'dob')
                ->orderByRaw('LOWER(COALESCE(name, "")) ASC')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($patient) {
                    return [
                        'id' => $patient->id,
                        'name' => $patient->name,
                        'phone' => $patient->phone,
                        'gender' => $patient->gender,
                        'dob' => $patient->dob,
                    ];
                })
                ->toArray();
        });
    }

    private function getBillingReferrers(): array
    {
        return Cache::remember('billing:referrers_active', 90, function () {
            return ReferralPerson::whereNull('deleted_at')
                ->where('status', 'Active')
                ->select('id', 'name', 'phone', 'standard_commission', 'pathology_commission', 'radiology_commission', 'pharmacy_commission', 'ecg_commission', 'ultrasound_commission', 'status')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($referrer) {
                    return [
                        'id' => $referrer->id,
                        'name' => $referrer->name,
                        'phone' => $referrer->phone,
                        'standard_commission' => $referrer->standard_commission,
                        'pathology_commission' => $referrer->pathology_commission,
                        'radiology_commission' => $referrer->radiology_commission,
                        'pharmacy_commission' => $referrer->pharmacy_commission,
                        'ecg_commission' => $referrer->ecg_commission,
                        'ultrasound_commission' => $referrer->ultrasound_commission,
                        'status' => $referrer->status,
                    ];
                })
                ->toArray();
        });
    }

    private function getHospitalCharges(): array
    {
        $hospitalChargesEnv = env('HOSPITAL_CHARGES_ENABLED', null);
        $hospitalChargesEnabled = true;
        if ($hospitalChargesEnv !== null && $hospitalChargesEnv !== '') {
            $hospitalChargesEnabled = filter_var($hospitalChargesEnv, FILTER_VALIDATE_BOOLEAN);
        }

        if (!$hospitalChargesEnabled) {
            return [];
        }

        return Cache::remember('billing:hospital_charges', 90, function () {
            $rawCharges = Charge::with('chargeType:id,modules')
                ->whereNull('deleted_at')
                ->select('id', 'name', 'module', 'standard_charge', 'charge_type_id')
                ->get();

            return $rawCharges->map(function ($ch) {
                $modules = [];
                if (!empty($ch->module)) {
                    $modules = is_array($ch->module) ? $ch->module : preg_split('/\s*,\s*/', trim($ch->module));
                }

                if (empty($modules) && !empty($ch->chargeType?->modules)) {
                    $ct = $ch->chargeType->modules;
                    $decoded = null;
                    try {
                        $decoded = json_decode($ct, true);
                    } catch (\Throwable $e) {
                        $decoded = null;
                    }

                    if (is_array($decoded) && count($decoded) > 0) {
                        $modules = $decoded;
                    } else {
                        $modules = preg_split('/\s*,\s*/', trim((string)$ct));
                    }
                }

                $modules = array_values(array_filter(array_map(function ($m) {
                    return trim((string) $m);
                }, (array) $modules)));

                return [
                    'id' => $ch->id,
                    'name' => $ch->name,
                    'module' => count($modules) ? json_encode($modules) : '',
                    'amount' => $ch->standard_charge ?? 0,
                ];
            })->toArray();
        });
    }

    private function normalizeReferralCommissionCategory(?string $category, ?string $itemName = null): string
    {
        $cat = strtolower(trim((string) ($category ?? '')));
        $name = strtolower(trim((string) ($itemName ?? '')));
        $combined = trim($cat . ' ' . $name);

        if ($cat === '') {
            return '';
        }

        if (in_array($cat, ['pathology', 'pathological'], true)) {
            return 'pathology';
        }

        if (in_array($cat, ['medicine', 'pharmacy', 'drug'], true)) {
            return 'medicine';
        }

        if (in_array($cat, ['ecg', 'ekg'], true)) {
            return 'ecg';
        }

        if (preg_match('/\b(usg|ultrasound|ultrasonogram|ultrasonography|sonography|sono)\b/', $combined) === 1) {
            return 'ultrasound';
        }

        if (in_array($cat, ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg', 'sono'], true)) {
            return 'ultrasound';
        }

        if (in_array($cat, ['radiology', 'xray', 'x-ray', 'ct', 'mri'], true)) {
            return 'radiology';
        }

        if (in_array($cat, ['opd', 'appointment'], true)) {
            return $cat === 'appointment' ? 'appointment' : 'opd';
        }

        if ($cat === 'ipd') {
            return 'ipd';
        }

        return $cat;
    }

    private function resolveReferrerCommissionRate(
        $referrer,
        ?string $category,
        ?int $itemId = null,
        ?string $itemName = null,
        ?float $itemReferralRate = null
    ): float {
        $normalizedCategory = $this->normalizeReferralCommissionCategory($category, $itemName);
        $commissionRate = 0.0;

        switch ($normalizedCategory) {
            case 'pathology':
                $commissionRate = (float) ($referrer->pathology_commission ?? 0);
                break;
            case 'radiology':
                $commissionRate = (float) ($referrer->radiology_commission ?? 0);
                break;
            case 'ecg':
                $commissionRate = (float) ($referrer->ecg_commission ?? 0);
                break;
            case 'ultrasound':
                $commissionRate = (float) ($referrer->ultrasound_commission ?? $referrer->radiology_commission ?? 0);
                break;
            case 'medicine':
                $commissionRate = (float) ($referrer->pharmacy_commission ?? 0);
                break;
            case 'opd':
            case 'appointment':
                $commissionRate = (float) ($referrer->opd_commission ?? 0);
                break;
            case 'ipd':
                $commissionRate = (float) ($referrer->ipd_commission ?? 0);
                break;
        }

        if ($itemReferralRate !== null) {
            return $itemReferralRate > 0 ? $itemReferralRate : $commissionRate;
        }

        if ($itemId) {
            $itemReferralRate = null;
            try {
                if (in_array($normalizedCategory, ['pathology', 'radiology', 'ecg', 'ultrasound'], true)) {
                    $master = Test::find($itemId);
                    if ($master && isset($master->referral_percentage) && (float) $master->referral_percentage > 0) {
                        $itemReferralRate = (float) $master->referral_percentage;
                    }
                } elseif ($normalizedCategory === 'medicine') {
                    $medicine = MedicineInventory::find($itemId);
                    if ($medicine && isset($medicine->referral_percentage) && (float) $medicine->referral_percentage > 0) {
                        $itemReferralRate = (float) $medicine->referral_percentage;
                    }
                }
            } catch (\Throwable $e) {
                $itemReferralRate = null;
            }

            if ($itemReferralRate !== null) {
                $commissionRate = $itemReferralRate;
            }
        }

        return (float) $commissionRate;
    }

    public function store(BillingRequest $request)
    {
        $data = $request->validated();
        $adminUser = auth('admin')->user();
        $hasGeneralBillingAccess = $adminUser->can('billing')
            || $adminUser->can('billing-create')
            || $adminUser->can('billing-add');
        $pharmacyMode = (bool) ($data['pharmacy_mode'] ?? false) || user_is_pharmacy_only($adminUser);

        if (!$hasGeneralBillingAccess && !$pharmacyMode) {
            abort(403);
        }

        if ($pharmacyMode && collect($data['items'])->contains(
            fn ($item) => strtolower(trim((string) ($item['category'] ?? ''))) !== 'medicine'
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'Pharmacy billing only supports medicine items.',
            ]);
        }

        DB::beginTransaction();
        try {
            if ($request->header('X-Offline-Replay') === '1') {
                $medicineItems = collect($data['items'] ?? [])
                    ->filter(fn ($item) => strtolower(trim((string) ($item['category'] ?? ''))) === 'medicine')
                    ->groupBy(fn ($item) => (string) ($item['id'] ?? ''));

                foreach ($medicineItems as $medicineId => $items) {
                    $medicine = MedicineInventory::query()
                        ->whereKey($medicineId)
                        ->lockForUpdate()
                        ->first();

                    if (!$medicine) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => 'A medicine in this offline bill is no longer available. Review the bill before retrying.',
                        ]);
                    }

                    $requestedQuantity = (float) $items->sum(fn ($item) => (float) ($item['quantity'] ?? 0));
                    $currentPrice = round((float) $medicine->medicine_unit_selling_price, 2);
                    $hasStalePrice = $items->contains(
                        fn ($item) => round((float) ($item['unit_price'] ?? 0), 2) !== $currentPrice
                    );

                    if ($hasStalePrice || $requestedQuantity > (float) $medicine->medicine_quantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => sprintf(
                                'Medicine "%s" price or available stock changed while offline. Review the bill before retrying.',
                                $medicine->medicine_name
                            ),
                        ]);
                    }
                }
            }

            $expectsJsonResponse = $request->wantsJson()
                || $request->ajax()
                || $request->expectsJson()
                || $request->hasHeader('X-Inertia')
                || $request->header('X-Requested-With') === 'XMLHttpRequest';

            $adminId = (int) ($adminUser?->id ?? 0);
            $adminName = trim((string) ($adminUser?->name ?? ''));
            if (!$this->hasOpenCounterSessionForAdmin($adminId, $adminName)) {
                $message = 'No active counter session found for your login. Please Counter Start first.';
                if ($expectsJsonResponse) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'errors' => ['counter' => $message],
                    ], 422);
                }

                return redirect()->back()->with('errorMessage', $message);
            }

            $doctorInfo = $this->handleDoctor($data['doctor_name'] ?? null);

            // Use unified patient handler
            $patientResult = $this->handlePatientData($data);
            $patientId = $patientResult['patient_id'];
            $data = $patientResult['processed_data'];
            $data = array_merge($data, $this->normalizeBillingPaymentData($data));

            // Normalize item categories: map ECG/Ultrasound/USG variants to Radiology.
            // Also inspect item name because some frontend/test data may have
            // radiology keywords in the name while category is incorrectly set.
            if (!empty($data['items']) && is_array($data['items'])) {
                $data['items'] = array_map(function ($it) {
                    $cat = strtolower(trim((string) ($it['category'] ?? '')));
                    $name = strtolower(trim((string) ($it['name'] ?? '')));

                    foreach (['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg', 'sono', 'sonography'] as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'Ultrasound';
                            return $it;
                        }
                    }

                    foreach (['ecg', 'ekg', 'electrocardiogram'] as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'ECG';
                            return $it;
                        }
                    }

                    foreach (['radiology', 'xray', 'x-ray', 'x ray', 'imaging', 'ct', 'mri', 'radiolog'] as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'Radiology';
                            return $it;
                        }
                    }

                    return $it;
                }, $data['items']);
            }

            // Allow multiple same-day bills for the same patient.
            // Hospitals may need separate bills for additional tests/services on the same day.

            $totalBillAmountBeforeDiscount = collect($data['items'])->sum('total_amount');
            $discountableBillAmount = (($data['discount_type'] ?? 'percentage') === 'percentage')
                ? collect($data['items'])
                    ->reject(fn ($item) => $this->isDisposableBillingItem($item))
                    ->reject(fn ($item) => $this->isDiscountExemptBillingItem($item))
                    ->sum('total_amount')
                : collect($data['items'])
                    ->reject(fn ($item) => $this->isDisposableBillingItem($item))
                    ->reject(fn ($item) => $this->isDiscountExemptBillingItem($item))
                    ->sum('total_amount');
            $totalDiscountAmount = 0;
            if (($data['discount_type'] ?? 'percentage') === 'percentage') {
                $totalDiscountAmount = collect($data['items'])->sum(
                    fn ($item) => ((float) ($item['total_amount'] ?? 0) * $this->resolveBillingItemDiscountPercent($item, (float) ($data['discount'] ?? 0))) / 100
                );
            } else {
                $totalDiscountAmount = min(
                    max(0, (float) ($data['discount'] ?? 0)),
                    $discountableBillAmount
                );
            }

            $vatPercentage = 0.0;
            $vatAmount = 0.0;
            try {
                $ws = \App\Models\WebSetting::query()
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->first();

                if (!$ws) {
                    $ws = \App\Models\WebSetting::query()->orderByDesc('id')->first();
                }

                if (!empty($ws) && ($ws->vat_enabled ?? false)) {
                    $vatPercentage = (float) ($ws->vat_percent ?? 0);
                }
            } catch (\Throwable $e) {
                $vatPercentage = 0.0;
            }

            $extraFlat = (float) ($data['extra_flat_discount'] ?? 0);
            $netBeforeVat = max(0, $totalBillAmountBeforeDiscount - $totalDiscountAmount - $extraFlat);
            if ($vatPercentage > 0 && $netBeforeVat > 0) {
                $vatAmount = ($netBeforeVat * $vatPercentage) / 100.0;
            }

            $referrer = isset($data['referrer_id']) ? $this->referrerService->find($data['referrer_id']) : null;

            $billingData = [
                // invoice_number/bill_number/case_number are set below in a retry-safe way
                'patient_id' => $patientId,
                'patient_mobile' => $data['patient_mobile'],
                'gender' => $data['gender'],
                'doctor_id' => $doctorInfo['doctor_id'],
                'doctor_type' => $doctorInfo['doctor_type'],
                'doctor_name' => $doctorInfo['doctor_name'],
                'referrer_id' => $data['referrer_id'] ?? null,
                'card_type' => $data['card_type'],
                'pay_mode' => $data['pay_mode'],
                'card_number' => $data['card_number'] ?? null,
                'total' => $data['total'],
                'discount' => $data['discount'] ?? 0,
                'extra_flat_discount' => $data['extra_flat_discount'] ?? 0,
                'vat_percentage' => $vatPercentage,
                'vat_amount' => $vatAmount,
                'discount_type' => $data['discount_type'] ?? 'percentage',
                // Compute payable_amount from discounted total plus VAT so server and UI stay aligned
                'payable_amount' => max(0, round($netBeforeVat + $vatAmount, 2)),
                'paid_amt' => $data['paid_amt'],
                'invoice_amount' => $data['paid_amt'],
                'change_amt' => $data['change_amt'] ?? 0,
                'due_amount' => $data['due_amount'] ?? 0,
                'receiving_amt' => $data['receiving_amt'] ?? 0,
                'return_amt' => $data['return_amt'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'commission_total' => $data['commission_total'] ?? 0,
                'physyst_amt' => $data['physyst_amt'] ?? 0,
                'commission_slider' => $data['commission_slider'] ?? 0,
                'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt'], $data['return_amt'] ?? 0),
                'created_by' => auth('admin')->user()->id,
            ];

            $billing = null;
            $attempts = 0;

            // Prevent duplicate concurrent bills for the same patient.
            // If a billing record for the same patient (and same actor) was
            // created very recently (within the last 10 seconds) with the
            // same total, reuse it instead of creating a new one. We use
            // a SELECT ... FOR UPDATE (lockForUpdate) inside the existing
            // transaction to avoid race conditions.
            try {
                $recentWindow = Carbon::now()->subSeconds(10);
                $maybe = Billing::query()
                    ->where('patient_id', $patientId)
                    ->where('created_by', auth('admin')->id())
                    ->where('total', $billingData['total'])
                    ->where('created_at', '>=', $recentWindow->toDateTimeString())
                    ->lockForUpdate()
                    ->first();

                if ($maybe) {
                    // reuse recent billing to avoid duplicate
                    $billing = $maybe;
                    Log::info('billing.store.reused_recent', ['billing_id' => $billing->id, 'patient_id' => $patientId]);
                }
            } catch (\Throwable $e) {
                // If locking or query fails for any reason, ignore and proceed
                // to create normally. We log at debug level to help future
                // troubleshooting without breaking the flow.
                Log::debug('billing.store.reuse_check_failed', ['error' => $e->getMessage()]);
            }

            while (!$billing && $attempts < 5) {
                $attempts++;

                $billingData['bill_number'] = $this->generateBillNumber();
                $billingData['invoice_number'] = $this->generateInvoiceNumber();
                $billingData['case_number'] = $this->generateCaseNumber();

                try {
                    // If frontend provided a billing date/time, set created_at
                    // so the bill is recorded on that datetime.
                    if (!empty($data['billing_date'])) {
                        $time = $data['billing_time'] ?? '00:00:00';
                        try {
                            $billingData['created_at'] = Carbon::parse($data['billing_date'] . ' ' . $time)->toDateTimeString();
                        } catch (\Throwable $e) {
                        }
                    }

                    $billing = $this->billingService->create($billingData);

                    // Ensure created_at persisted even if model is not mass-assignable
                    if (!empty($billingData['created_at']) && $billing) {
                        try {
                            $billing->created_at = $billingData['created_at'];
                            $billing->save();
                        } catch (\Throwable $e) {
                        }
                    }
                } catch (\Illuminate\Database\QueryException $e) {
                    // Retry on duplicate key (bill_number/invoice_number/case_number)
                    if (($e->errorInfo[0] ?? null) === '23000') {
                        usleep(random_int(10000, 50000));
                        continue;
                    }

                    throw $e;
                }
            }

            if (!$billing) {
                throw new Exception('Failed to create billing record (duplicate number)');
            }


            // Rest of your store method remains the same...
            $totalBillAmountBeforeDiscount = collect($data['items'])->sum('total_amount');
            $discountableBillAmount = ($data['discount_type'] === 'percentage')
                ? collect($data['items'])
                    ->reject(fn ($item) => $this->isDisposableBillingItem($item))
                    ->reject(fn ($item) => $this->isDiscountExemptBillingItem($item))
                    ->sum('total_amount')
                : collect($data['items'])
                    ->reject(fn ($item) => $this->isDisposableBillingItem($item))
                    ->reject(fn ($item) => $this->isDiscountExemptBillingItem($item))
                    ->sum('total_amount');
            $totalDiscountAmount = 0;

            if ($data['discount_type'] === 'percentage') {
                $totalDiscountAmount = collect($data['items'])->sum(
                    fn ($item) => ((float) ($item['total_amount'] ?? 0) * $this->resolveBillingItemDiscountPercent($item, (float) ($data['discount'] ?? 0))) / 100
                );
            } else {
                $totalDiscountAmount = min(
                    max(0, (float) ($data['discount'] ?? 0)),
                    $discountableBillAmount
                );
            }

            $discountFactor = ($discountableBillAmount > 0) ? ($totalDiscountAmount / $discountableBillAmount) : 0;
            $itemCollection = collect($data['items']);
            $testIds = $itemCollection
                ->filter(fn ($item) => in_array(strtolower((string) ($item['category'] ?? '')), ['pathology', 'radiology'], true))
                ->pluck('id')
                ->filter()
                ->unique()
                ->values();
            $testMasters = Test::query()->whereKey($testIds)->get()->keyBy('id');
            $medicineIds = $itemCollection
                ->filter(fn ($item) => strtolower((string) ($item['category'] ?? '')) === 'medicine')
                ->pluck('id')
                ->filter()
                ->unique()
                ->values();
            $medicineInventories = MedicineInventory::query()->whereKey($medicineIds)->get()->keyBy('id');
            $reagentConsumptionService = app(\App\Services\ReagentConsumptionService::class);
            $reagentConsumptionService->prepareForBillItems($data['items']);

            if ($data['referrer_id']) {
                $commissionResult = app(ReferralCommissionCalculator::class)->calculate(
                    $data['items'],
                    (float) $billingData['payable_amount'],
                    function ($item) use ($referrer) {
                        $category = $this->normalizeReferralCommissionCategory($item['category'] ?? '', $item['name'] ?? '');

                        return [
                            'category' => $category,
                            'rate' => $this->resolveReferrerCommissionRate(
                                $referrer,
                                $category,
                                isset($item['id']) ? (int) $item['id'] : null,
                                (string) ($item['name'] ?? ''),
                                array_key_exists('referral_percentage', $item)
                                    ? (float) $item['referral_percentage']
                                    : null
                            ),
                        ];
                    }
                );
                $totalCommission = $commissionResult['total'];
                $categoryCommissions = $commissionResult['categories'];
                $data['commission_total'] = $totalCommission;
                $data['physyst_amt'] = $totalCommission;
                $billing->commission_total = $totalCommission;
                $billing->physyst_amt = $totalCommission;
                $billing->save();

                $refData = [
                    'billing_id' => $billing->id,
                    'payee_id' => $data['referrer_id'],
                    'total_commission_amount' => $totalCommission,
                    'category_commissions' => $categoryCommissions,
                    'date' => ($billing->created_at ? Carbon::parse($billing->created_at)->toDateString() : now()->toDateString()),
                    'total_bill_amount' => $billingData['payable_amount'],
                    'status' => 'Active'
                ];

                if ($billing->created_at) {
                    $refData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
                }

                Referral::create($refData);

            }

            foreach ($data['items'] as $item) {
                $itemProportionalDiscount = ($this->isDisposableBillingItem($item) || $this->isDiscountExemptBillingItem($item)
                    || ((bool) ($item['discount_rate_locked'] ?? false) && (float) ($item['discount_percentage'] ?? 0) <= 0))
                    ? 0
                    : (($data['discount_allocation'] ?? 'proportional') === 'equal'
                        ? (float) ($item['discount'] ?? 0)
                        : (($data['discount_type'] === 'percentage')
                            ? ($item['total_amount'] * $this->resolveBillingItemDiscountPercent($item, (float) $data['discount'])) / 100
                            : (float) ($item['discount'] ?? 0)));
                $itemNetAmount = $item['total_amount'] - $itemProportionalDiscount;

                // snapshot item-level commission settings when available
                $commissionable = null;
                $commission_rate = null;
                $master = null;
                $catLower = strtolower($item['category'] ?? '');
                $isDisposable = false;
                $itemNameLower = strtolower($item['name'] ?? '');
                if ($catLower === 'pathology') {
                    if (str_contains($itemNameLower, 'disposable') || str_contains($itemNameLower, 'tube') || str_contains($itemNameLower, 'butterfly') || str_contains($itemNameLower, 'needle')) {
                        $isDisposable = true;
                    }
                }
                if (in_array($catLower, ['pathology', 'radiology'])) {
                    $master = $testMasters->get($item['id']);
                    if ($master) {
                        $commissionable = $master->commissionable ?? null;
                        $commission_rate = $master->commission_rate ?? null;

                        // If item has referral_percentage, prefer it for commission_rate snapshot
                        try {
                            if (isset($master->referral_percentage) && $master->referral_percentage > 0) {
                                $commission_rate = (float) $master->referral_percentage;
                            }
                        } catch (\Exception $e) {
                            // ignore and keep commission_rate
                        }

                    }
                }

                $isRadiologyItem = in_array($catLower, ['radiology', 'ecg', 'ultrasound', 'xray', 'ultrasonogram', 'ultrasonography']);

                $billItem = BillItem::create([
                    'billing_id' => $billing->id,
                    'department_id' => $item['department_id'] ?? null,
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'room_no' => $this->resolveCategoryBillingRoomNo((string) $item['category'])
                        ?? ($item['room_no'] ?? $item['roomNo'] ?? (isset($master) ? ($master->room_no ?? null) : null)),
                    'category' => $item['category'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'discount' => $itemProportionalDiscount,
                    'discount_exempt' => $this->isDiscountExemptBillingItem($item),
                    'rugound' => $item['rugound'] ?? 0,
                    'net_amount' => $itemNetAmount,
                    // Only pathology items require sample collection. Ensure
                    // radiology/other categories are not marked for sample.
                    'requires_sample' => $this->requiresSampleCollection($item, $catLower, $isDisposable),
                    // For radiology-like items set sample_collected_at so they
                    // appear immediately in Reporting lists.
                    'sample_collected_at' => $isRadiologyItem ? ($billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now()) : null,
                    'created_at' => $billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now(),
                    'sample_collected_by' => $isRadiologyItem ? auth('admin')->id() : null,
                    'commissionable' => $commissionable,
                    'commission_rate' => $commission_rate,
                ]);

                $reagentConsumptionService->consumeForBillItem($billItem);

                if (strtolower($item['category']) === 'medicine') {
                    $medicine = $medicineInventories->get($item['id']);
                    if ($medicine) {
                        $newQuantity = $medicine->medicine_quantity - $item['quantity'];
                        $medicine->update(['medicine_quantity' => max(0, $newQuantity)]);
                    }
                }
            }

            if ($data['paid_amt'] > 0) {
                $paymentData = [
                    'billing_id' => $billing->id,
                    'amount' => $data['paid_amt'],
                    'payment_method' => $data['pay_mode'],
                    'transaction_id' => $data['card_number'] ?? null,
                    'notes' => $data['remarks'] ?? null,
                    'received_by' => auth('admin')->user()->id,
                    'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt'], $data['return_amt'] ?? 0),
                ];

                if ($billing->created_at) {
                    $paymentData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
                }

                Payment::create($paymentData);
            }

            // Commission expense is recorded on referral payment

            $pathologyItems = collect($data['items'])->where('category', 'Pathology');
            if ($pathologyItems->isNotEmpty()) {
                $this->createPathologyRecord($billing, $pathologyItems, $data);
            }

            $radiologyItems = collect($data['items'])->filter(function ($item) {
                $cat = strtolower((string) ($item['category'] ?? ''));
                $normalized = preg_replace('/[^a-z0-9]/', '', $cat);
                return in_array($normalized, ['radiology', 'ultrasound', 'xray', 'ecg', 'ultrasonogram', 'ultrasonography']);
            });
            if ($radiologyItems->isNotEmpty()) {
                $this->createRadiologyRecord($billing, $radiologyItems, $data);
            }

            $medicineItems = collect($data['items'])->where('category', 'Medicine');
            if ($medicineItems->isNotEmpty()) {
                $this->createPharmacyBillRecord($billing, $medicineItems, $data);
            }

            // Record ledger postings: split payable into paid (cash/bank) and due (AR)
            try {
                $paidAmount = (float) ($billing->paid_amt ?? 0);
                $dueAmount = (float) ($billing->due_amount ?? 0);
                $createdBy = auth('admin')->user()->id ?? null;
                $date = $billing->created_at ? $billing->created_at->toDateString() : now()->toDateString();

                $this->billingLedgerService->recordNewBill(
                    $data['items'] ?? [],
                    $paidAmount,
                    $dueAmount,
                    $data['pay_mode'] ?? null,
                    (string) ($billing->bill_number ?? $billing->id),
                    $date,
                    (int) $billing->id,
                    $createdBy
                );
            } catch (\Exception $e) {
                $this->storeSystemError('Backend', 'BillingController', 'store->ledger', substr($e->getMessage(), 0, 1000));
            }

            // Try to link this billing to an existing IpdPatient record so
            // Dashboard IPD aggregation can find related bills. Previously
            // linkage was only attempted when an `IPD` item was present; make
            // it more robust by linking whenever the patient has an active
            // IpdPatient without a billing_id.
            try {
                if (!empty($billing->patient_id)) {
                    $ipdpatient = IpdPatient::query()
                        ->where('patient_id', $billing->patient_id)
                        ->whereNull('billing_id')
                        ->whereIn('status', ['Active', 'Inactive'])
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($ipdpatient) {
                        $ipdpatient->billing_id = $billing->id;
                        $ipdpatient->save();

                        // Ensure any IPD-scoped payments are associated with this billing
                        try {
                            Payment::query()
                                ->whereNull('deleted_at')
                                ->where('status', 'Active')
                                ->where('ipd_patient_id', $ipdpatient->id)
                                ->whereNull('billing_id')
                                ->update(['billing_id' => $billing->id]);
                        } catch (\Throwable $_e) {
                            // ignore payment update failures
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('BillingController: failed to link billing to ipdpatient', ['billing_id' => $billing->id, 'error' => $e->getMessage()]);
            }

            $message = 'Billing created successfully with Bill No: ' . ($billing->bill_number ?? ''); 

            $printToken = (string) ($request->input('print_token') ?? '');

            $this->storeAdminWorkLog($billing->id, 'billings', $message);
                        ActivityLogService::logCreate(
                            'Billing',
                            $billing->id,
                            $billing->bill_number ?? ('Billing#' . $billing->id),
                            ActivityLogService::buildBillingDeleteMeta($billing)
                        );

            DB::commit();

            // Resolve the preview token only after the bill is visible to the invoice request.
            try {
                if ($printToken !== '') {
                    Cache::put('print_token_' . $printToken, $billing->id, now()->addMinutes(10));
                }
            } catch (\Throwable $e) {
                // ignore token mapping failures; normal invoice open path still works.
            }

            // If request expects JSON (Inertia/AJAX), return JSON with billId so
            // the frontend can immediately navigate/open invoice without relying
            // on session flash propagation.
            // Also detect Inertia XHRs which send the `X-Inertia` header so that
            // the frontend (Inertia/useForm) receives a direct JSON payload
            // containing the created bill id.
            // If this is an AJAX/Inertia request, return JSON so frontend handlers run.
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                $invoiceUrl = '';
                try {
                    $invoiceUrl = route('backend.download.invoice', ['id' => $billing->id, 'module' => 'billing', 'fast_open' => 1, 'auto_print' => 1]);
                } catch (\Throwable $e) {
                    $invoiceUrl = url('/download-invoice?id=' . $billing->id . '&module=billing&fast_open=1&auto_print=1');
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'billId' => $billing->id,
                    'invoiceUrl' => $invoiceUrl,
                ]);
            }

            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (\Illuminate\Validation\ValidationException $err) {
            DB::rollBack();
            throw $err;
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'store', substr($err->getMessage(), 0, 1000));

            $message = "Server error occurred: " . $err->getMessage();
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'errors' => $message,
                ], 500);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', $message);
        }
    }

    private function buildFastOpenInvoiceHtml(Billing $billing, Request $request): string
    {
        $module = trim((string) $request->input('module', ''));
        if ($module === '') {
            $module = $request->boolean('pharmacy_mode') ? 'pharmacy' : 'billing';
        }

        $items = $request->input('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        $billItems = collect($items)
            ->filter(fn($item) => is_array($item))
            ->map(function (array $item) {
                $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                $unitPrice = (float) ($item['amount'] ?? $item['unitPrice'] ?? $item['unit_price'] ?? 0);
                $totalAmount = (float) ($item['total_amount'] ?? $item['totalAmount'] ?? $item['net_amount'] ?? ($qty * $unitPrice));

                return (object) [
                    'item_name' => (string) ($item['name'] ?? $item['itemName'] ?? 'Item'),
                    'description' => (string) ($item['description'] ?? ''),
                    'category' => (string) ($item['category'] ?? ''),
                    'room_no' => (string) ($item['room_no'] ?? $item['roomNo'] ?? ''),
                    'quantity' => $qty,
                    'qty' => $qty,
                    'amount' => $unitPrice,
                    'discount' => (float) ($item['discount'] ?? 0),
                    'total_amount' => $totalAmount,
                ];
            })
            ->values();

        $patient = null;
        if (!empty($billing->patient_id)) {
            $patient = Patient::query()->find($billing->patient_id);
        }

        $invoiceDesign = $this->resolveFastInvoiceDesign($module);

        $requestTotal = (float) ($request->input('total') ?? 0);
        $requestNetPayable = (float) ($request->input('payable_amount') ?? 0);
        $requestPaid = (float) ($request->input('paid_amt') ?? $request->input('receiving_amt') ?? 0);
        $requestDue = (float) ($request->input('due_amount') ?? 0);

        $totalAmount = $requestTotal > 0
            ? $requestTotal
            : (float) ($billing->total ?? $billItems->sum('total_amount'));
        $netPayable = $requestNetPayable > 0
            ? $requestNetPayable
            : (float) ($billing->invoice_amount ?? $totalAmount);
        $paidAmount = max(0, $requestPaid > 0 ? $requestPaid : (float) ($billing->receiving_amt ?? 0));
        $dueAmount = $requestDue >= 0
            ? max(0, $requestDue)
            : max(0, $netPayable - $paidAmount);

        $requestedReturn = (float) ($request->input('return_amount') ?? $request->input('return_amt') ?? 0);
        $persistedReturn = (float) ($billing->return_amt ?? $billing->return_amount ?? 0);
        $cashReturnFallback = max(0, $paidAmount - $netPayable);
        $returnAmount = $requestedReturn > 0
            ? $requestedReturn
            : ($persistedReturn > 0 ? $persistedReturn : $cashReturnFallback);
        $adjustedDue = max(0, $dueAmount - $returnAmount);

        $discountType = (string) ($request->input('discount_type') ?? $billing->discount_type ?? 'amount');
        $discountValue = (float) ($request->input('discount') ?? $billing->discount ?? 0);
        $extraFlatDiscount = (float) ($request->input('extra_flat_discount') ?? $billing->extra_flat_discount ?? 0);

        // Avoid expensive QR generation in fast-open HTML while keeping the same visual layout.
        $billing->patient_id = 0;
        $billing->setRelation('patient', $patient);
        $billing->setRelation('billItems', $billItems);
        $billing->setRelation('dueCollections', collect());
        $billing->setRelation('payments', collect());

        if (!$billing->relationLoaded('admin')) {
            $billing->load('admin');
        }

        $footerContent = (string) ($invoiceDesign?->footer_content ?? '');
        $footerContent = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $footerContent) ?? $footerContent;

        $fontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        $fontPath = is_file($fontFile) ? str_replace('\\', '/', $fontFile) : '';

        $data = [
            'billing' => $billing,
            'bill_number' => $billing->bill_number ?? '',
            'invoiceDateTime' => $billing->created_at ? $billing->created_at->format('d-M-Y h:i:s A') : now()->format('d-M-Y h:i:s A'),
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
            'patient_name' => trim((string) ($request->input('patient_name') ?? $request->input('name') ?? ($patient->name ?? 'N/A'))),
            'age' => trim((string) ($request->input('patient_age') ?? ($patient->age ?? 'N/A'))),
            'contact_no' => trim((string) ($request->input('patientMobile') ?? $request->input('patient_phone') ?? $billing->patient_mobile ?? ($patient->phone ?? ''))),
            'gender' => (string) ($request->input('patient_gender') ?? $billing->gender ?? ($patient->gender ?? '')),
            'refd_by' => (string) ($request->input('doctor_name') ?? $billing->doctor_name ?? 'N/A'),
            'bill_items' => $billItems,
            'total_amount' => round($totalAmount, 2),
            'vat' => 0,
            'net_payable' => round($netPayable, 2),
            'discount' => $discountType === 'percentage'
                ? $discountValue
                : max(0, $discountValue),
            'discount_type' => $discountType,
            'extra_flat_discount' => max(0, $extraFlatDiscount),
            'paid' => round($paidAmount, 2),
            'paid_at_invoice' => round($paidAmount, 2),
            'due' => round($dueAmount, 2),
            'return_amount' => round($returnAmount, 2),
            'adjusted_due' => round($adjustedDue, 2),
            'delivery_date' => $billing->delivery_date,
            'remarks' => (string) ($billing->remarks ?? ''),
            'prepared_by' => (string) ($billing?->admin?->name ?? ''),
            'amount_in_words' => 'In Words: ' . number_format($netPayable, 2) . ' Taka Only',
            'header_image' => $this->fastInvoiceImageUrl($invoiceDesign?->header_photo_path),
            'footer_image' => $this->fastInvoiceImageUrl($invoiceDesign?->footer_photo_path),
            'invoiceDesign' => $invoiceDesign,
            'fullPagePadImage' => $this->fastInvoiceImageUrl($invoiceDesign?->full_page_pad_path),
            'footer_content' => $footerContent,
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below'], true)
                ? strtolower((string) $invoiceDesign->footer_content_position)
                : 'above',
            'footer_font_size' => max(6, min(72, (int) ($invoiceDesign?->footer_font_size ?? 14))),
            'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
            'barcode' => '',
            'module' => $module,
            'banglaFontUrl' => is_file($fontFile) ? asset('fonts/NotoSansBengali-Regular.ttf') : '',
            'banglaFontPath' => $fontPath,
            'showHeaderFooter' => true,
            'show_header_footer' => true,
            'reportHeaderHeight' => (int) ($invoiceDesign?->header_height ?? 115),
            'reportFooterHeight' => (int) ($invoiceDesign?->footer_height ?? 70),
        ];

        return view('frontend.invoice.pdf', $data)->render();
    }

    private function resolveFastInvoiceDesign(string $module): ?InvoiceDesign
    {
        return InvoiceDesign::resolveForModule($module);
    }

    private function fastInvoiceImageUrl(?string $publicStorageUrl): string
    {
        if (!$publicStorageUrl) {
            return '';
        }

        $rawPath = trim((string) $publicStorageUrl);
        if ($rawPath === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $rawPath) === 1 || str_starts_with($rawPath, 'data:image/')) {
            return $rawPath;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $rawPath), '/');
        $normalizedPath = preg_replace('#^(?:storage/app/public/|public/storage/|storage/)#', '', $normalizedPath);
        $existsInPublicStorage = $normalizedPath !== ''
            && (Storage::disk('public')->exists($normalizedPath)
                || is_file(public_path('storage/' . $normalizedPath))
                || is_file(public_path($normalizedPath)));

        if (!$existsInPublicStorage) {
            return '';
        }

        $resolvedUrl = publicStorageUrl($rawPath);
        if ($resolvedUrl) {
            return $resolvedUrl;
        }

        return asset($normalizedPath);
    }


    public function searchDoctors(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1'
        ]);

        $search = $request->search;

        $doctors = BillingDoctor::where('name', 'like', '%' . $search . '%')
            ->where('status', 'Active')
            ->select('id', 'name')
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                ];
            });

        return response()->json($doctors);
    }

    public function searchPrescription(Request $request)
    {
        $validated = $request->validate([
            'prescription_id' => 'required|string',
        ]);

        $rawInput = trim((string) $validated['prescription_id']);
        $prescriptionId = $this->parsePrescriptionId($rawInput);
        if (!$prescriptionId) {
            return response()->json([
                'message' => 'Please provide a valid prescription, OPD, or IPD id.',
                'tests' => [],
                'medicines' => [],
            ], 422);
        }

        $isIpdPrescription = (bool) preg_match('/^IPD[\s:_-]*RX[\s:_-]*/i', $rawInput);
        $isOpdPrescription = (bool) preg_match('/^OPD[\s:_-]*RX[\s:_-]*/i', $rawInput);
        $isIpdAdmission = (bool) preg_match('/^IPD[\s:_-]*\d/i', $rawInput) && !$isIpdPrescription;
        $isOpdAdmission = (bool) preg_match('/^OPD[\s:_-]*\d/i', $rawInput) && !$isOpdPrescription;

        if ($isIpdAdmission) {
            $ipdPatient = IpdPatient::query()->with(['patient', 'latestPrescription.medicines', 'latestPrescription.tests'])->find($prescriptionId);
            if ($ipdPatient) {
                $prescription = $ipdPatient->latestPrescription;
                return $this->prescriptionBillingResponse(
                    'IPD',
                    $prescription?->id,
                    $ipdPatient->patient,
                    $prescription?->tests?->pluck('test_name') ?? collect(),
                    $prescription?->medicines ?? collect(),
                    $ipdPatient
                );
            }
        }

        if ($isOpdAdmission) {
            $opdPatient = OpdPatient::query()->with(['patient', 'latestPrescription.items'])->find($prescriptionId);
            if ($opdPatient) {
                $prescription = $opdPatient->latestPrescription;
                $items = $prescription?->items ?? collect();
                return $this->prescriptionBillingResponse(
                    'OPD',
                    $prescription?->id,
                    $opdPatient->patient,
                    $items->pluck('test_name'),
                    $items->whereNotNull('medicine_name')->where('medicine_name', '<>', '')->values(),
                    null
                );
            }
        }

        if ($isIpdPrescription) {
            $ipdPrescription = IpdPrescription::query()
                ->with(['medicines', 'tests', 'ipdPatient.patient'])
                ->find($prescriptionId);
            if ($ipdPrescription) {
                return $this->prescriptionBillingResponse(
                    'IPD',
                    $ipdPrescription->id,
                    $ipdPrescription->ipdPatient?->patient,
                    $ipdPrescription->tests->pluck('test_name'),
                    $ipdPrescription->medicines,
                    $ipdPrescription->ipdPatient
                );
            }
        }

        if ($isOpdPrescription) {
            $opdPrescription = OpdPrescription::query()
                ->with(['items', 'opdPatient.patient'])
                ->find($prescriptionId);
            if ($opdPrescription) {
                $items = $opdPrescription->items;
                return $this->prescriptionBillingResponse(
                    'OPD',
                    $opdPrescription->id,
                    $opdPrescription->opdPatient?->patient,
                    $items->pluck('test_name'),
                    $items->whereNotNull('medicine_name')->where('medicine_name', '<>', '')->values()
                );
            }
        }

        if (preg_match('/^RX[\s:_-]*/i', $rawInput)) {
            $standalone = Prescription::query()
                ->with(['tests', 'medicines', 'patient'])
                ->find($prescriptionId);
            if ($standalone) {
                return $this->prescriptionBillingResponse(
                    'Prescription',
                    $standalone->id,
                    $standalone->patient,
                    $standalone->tests->pluck('test_name'),
                    $standalone->medicines
                );
            }
        }

        $opdPrescription = OpdPrescription::query()
            ->with(['items', 'opdPatient.patient'])
            ->find($prescriptionId);

        if ($opdPrescription) {
            $items = $opdPrescription->items;
            return $this->prescriptionBillingResponse(
                'OPD',
                $opdPrescription->id,
                $opdPrescription->opdPatient?->patient,
                $items->pluck('test_name'),
                $items->whereNotNull('medicine_name')->where('medicine_name', '<>', '')->values()
            );
        }

        $ipdPrescription = IpdPrescription::query()
            ->with(['tests', 'ipdPatient.patient'])
            ->find($prescriptionId);

        if ($ipdPrescription) {
            return $this->prescriptionBillingResponse(
                'IPD',
                $ipdPrescription->id,
                $ipdPrescription->ipdPatient?->patient,
                $ipdPrescription->tests->pluck('test_name'),
                $ipdPrescription->medicines,
                $ipdPrescription->ipdPatient
            );
        }

        $standalone = Prescription::query()
            ->with(['tests', 'medicines', 'patient'])
            ->where(function ($query) use ($prescriptionId) {
                $query->where('id', $prescriptionId)
                    ->orWhere('prescription_no', 'like', '%' . $prescriptionId . '%');
            })
            ->first();

        if ($standalone) {
            return $this->prescriptionBillingResponse(
                'Prescription',
                $standalone->id,
                $standalone->patient,
                $standalone->tests->pluck('test_name'),
                $standalone->medicines
            );
        }

        $ipdPatient = IpdPatient::query()->with(['patient', 'latestPrescription.medicines', 'latestPrescription.tests'])->find($prescriptionId);
        if ($ipdPatient) {
            $prescription = $ipdPatient->latestPrescription;
            return $this->prescriptionBillingResponse(
                'IPD',
                $prescription?->id,
                $ipdPatient->patient,
                $prescription?->tests?->pluck('test_name') ?? collect(),
                $prescription?->medicines ?? collect(),
                $ipdPatient
            );
        }

        $opdPatient = OpdPatient::query()->with(['patient', 'latestPrescription.items'])->find($prescriptionId);
        if ($opdPatient) {
            $prescription = $opdPatient->latestPrescription;
            $items = $prescription?->items ?? collect();
            return $this->prescriptionBillingResponse(
                'OPD',
                $prescription?->id,
                $opdPatient->patient,
                $items->pluck('test_name'),
                $items->whereNotNull('medicine_name')->where('medicine_name', '<>', '')->values()
            );
        }

        return response()->json([
            'message' => 'Prescription, OPD, or IPD record not found.',
            'tests' => [],
            'medicines' => [],
        ], 404);
    }

    private function prescriptionBillingResponse(string $source, ?int $prescriptionId, $patient, $tests, $prescribedMedicines, ?IpdPatient $ipdPatient = null)
    {
        $medicineRows = collect($prescribedMedicines)
            ->map(function ($medicine) {
                return [
                    'medicine_name' => trim((string) ($medicine->medicine_name ?? '')),
                    'generic_name' => $medicine->generic_name ?? null,
                    'quantity' => max(1, (float) ($medicine->quantity ?? 1)),
                ];
            })
            ->filter(fn ($medicine) => $medicine['medicine_name'] !== '')
            ->values();

        $medicineNames = $medicineRows->pluck('medicine_name')->unique()->values();
        $inventoryByName = $medicineNames->isEmpty()
            ? collect()
            : MedicineInventory::query()
                ->where('status', 'Active')
                ->whereIn('medicine_name', $medicineNames)
                ->get(['id', 'medicine_name', 'medicine_unit_selling_price', 'medicine_quantity'])
                ->unique(fn ($item) => mb_strtolower(trim((string) $item->medicine_name)))
                ->keyBy(fn ($item) => mb_strtolower(trim((string) $item->medicine_name)));

        $formattedMedicines = $medicineRows->map(function ($medicine) use ($inventoryByName) {
            $inventory = $inventoryByName->get(mb_strtolower($medicine['medicine_name']));

            return [
                ...$medicine,
                'medicine_id' => $inventory?->id,
                'sale_price' => (float) ($inventory?->medicine_unit_selling_price ?? 0),
                'available_quantity' => (float) ($inventory?->medicine_quantity ?? 0),
            ];
        });

        return response()->json([
            'source' => $source,
            'prescription_id' => $prescriptionId,
            'ipd_patient_id' => $ipdPatient?->id,
            'ipd_prescription_id' => $source === 'IPD' ? $prescriptionId : null,
            'patient' => [
                'id' => $patient?->id,
                'name' => $patient?->name ?: trim((string) (($patient?->first_name ?? '') . ' ' . ($patient?->last_name ?? ''))),
                'phone' => $patient?->phone,
                'gender' => $patient?->gender,
                'dob' => $patient?->dob,
            ],
            'tests' => collect($tests)
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values(),
            'medicines' => $formattedMedicines,
        ]);
    }

    private function pharmacyPrescriptionSearchValue(Request $request): string
    {
        if ($request->filled('ipd_prescription_id')) {
            return 'IPD-RX-' . (int) $request->input('ipd_prescription_id');
        }

        if ($request->filled('ipd_patient_id')) {
            return 'IPD-' . (int) $request->input('ipd_patient_id');
        }

        if ($request->filled('opd_patient_id')) {
            return 'OPD-' . (int) $request->input('opd_patient_id');
        }

        if ($request->filled('prescription_id')) {
            return 'RX-' . (int) $request->input('prescription_id');
        }

        return '';
    }

    public function searchPrescriptionSuggestions(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $normalizedSearch = preg_replace('/\D+/', '', $search) ?: $search;

        $opd = OpdPrescription::query()
            ->with(['opdPatient.patient'])
            ->where('id', 'like', '%' . $normalizedSearch . '%')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function ($prescription) {
                return [
                    'id' => $prescription->id,
                    'source' => 'OPD',
                    'patient_name' => $prescription->opdPatient?->patient?->name,
                    'label' => 'OPD-RX-' . $prescription->id,
                    'search_value' => 'OPD-RX-' . $prescription->id,
                ];
            });

        $ipd = IpdPrescription::query()
            ->with(['ipdPatient.patient'])
            ->where('id', 'like', '%' . $normalizedSearch . '%')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function ($prescription) {
                return [
                    'id' => $prescription->id,
                    'source' => 'IPD',
                    'patient_name' => $prescription->ipdPatient?->patient?->name,
                    'label' => 'IPD-RX-' . $prescription->id,
                    'search_value' => 'IPD-RX-' . $prescription->id,
                ];
            });

        $standalone = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('prescriptions')) {
            $standalone = Prescription::query()
                ->with(['patient'])
                ->where(function ($query) use ($search, $normalizedSearch) {
                    $query->where('id', 'like', '%' . $normalizedSearch . '%')
                        ->orWhere('prescription_no', 'like', '%' . $search . '%');
                })
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(function ($prescription) {
                    return [
                        'id' => $prescription->id,
                        'source' => 'Prescription',
                        'patient_name' => $prescription->patient?->name,
                        'label' => $prescription->prescription_no ?: ('RX-' . $prescription->id),
                        'search_value' => 'RX-' . $prescription->id,
                    ];
                });
        }

        $opdAdmissions = OpdPatient::query()
            ->with('patient')
            ->where('id', 'like', '%' . $normalizedSearch . '%')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($opdPatient) => [
                'id' => $opdPatient->id,
                'source' => 'OPD Patient',
                'patient_name' => $opdPatient->patient?->name,
                'label' => 'OPD-' . $opdPatient->id,
                'search_value' => 'OPD-' . $opdPatient->id,
            ]);

        $ipdAdmissions = IpdPatient::query()
            ->with('patient')
            ->where('id', 'like', '%' . $normalizedSearch . '%')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($ipdPatient) => [
                'id' => $ipdPatient->id,
                'source' => 'IPD Patient',
                'patient_name' => $ipdPatient->patient?->name,
                'label' => 'IPD-' . $ipdPatient->id,
                'search_value' => 'IPD-' . $ipdPatient->id,
            ]);

        $suggestions = collect()
            ->merge($opd)
            ->merge($ipd)
            ->merge($standalone)
            ->merge($opdAdmissions)
            ->merge($ipdAdmissions)
            ->sortByDesc('id')
            ->take(12)
            ->values();

        return response()->json($suggestions);
    }

    private function parsePrescriptionId(string $rawInput): ?int
    {
        $rawInput = trim($rawInput);
        if ($rawInput === '') {
            return null;
        }

        if (ctype_digit($rawInput)) {
            return max(1, (int) $rawInput);
        }

        if (preg_match('/(?:opd|ipd)?\s*[-_:#]?\s*0*(\d+)/i', $rawInput, $matches)) {
            $id = (int) ($matches[1] ?? 0);
            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function handleDoctor($doctorName)
    {
        if (empty($doctorName)) {
            return [
                'doctor_id' => null,
                'doctor_type' => null,
                'doctor_name' => null
            ];
        }

        $doctor = BillingDoctor::where('name', $doctorName)
            ->where('status', 'Active')
            ->first();

        if (!$doctor) {
            $doctor = BillingDoctor::create([
                'name' => $doctorName,
                'status' => 'Active'
            ]);
        }

        return [
            'doctor_id' => $doctor->id,
            'doctor_type' => 'billing',
            'doctor_name' => $doctor->name
        ];
    }

    private function handleDoctorSelection($data)
    {
        $doctorId = null;
        $doctorType = null;
        $doctorName = null;

        if (!empty($data['doctor_id'])) {
            $doctor = BillingDoctor::find($data['doctor_id']);
            if ($doctor) {
                $doctorId = $doctor->id;
                $doctorType = 'billing';
                $doctorName = $doctor->name;
            }
        } elseif (!empty($data['doctor_name'])) {
            $doctor = BillingDoctor::create([
                'name' => $data['doctor_name'],
                'status' => 'Active'
            ]);
            $doctorId = $doctor->id;
            $doctorType = 'billing';
            $doctorName = $doctor->name;
        }

        return [
            'doctor_id' => $doctorId,
            'doctor_type' => $doctorType,
            'doctor_name' => $doctorName
        ];
    }

    private function updateOrCreateExpenseRecord($billing, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $commissionAmount = $data['physyst_amt'] ?? $data['commission_total'] ?? 0;

        $categories = collect($data['items'])->pluck('category')->map(function ($category) {
            return strtolower($category);
        })->unique()->toArray();

        $expenseHeaderName = count($categories) > 1 ? 'billing' : $categories[0];

        $categoryMap = [
            'medicine' => 'pharmacy',
            'pathology' => 'pathology',
            'radiology' => 'radiology',
            'billing' => 'billing'
        ];

        $headerName = $categoryMap[$expenseHeaderName] ?? 'billing';

        $expenseHeader = \App\Models\ExpenseHead::where('name', ucfirst($headerName))->first();

        if (!$expenseHeader) {
            $expenseHeader = \App\Models\ExpenseHead::create([
                'name' => ucfirst($headerName),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $expenseData = [
            'expense_header_id' => $expenseHeader->id,
            'bill_number' => $billing->bill_number,
            'case_id' => $billing->case_number,
            'name' => auth('admin')->user()->name ?? '',
            'description' => 'Commission expense for ' . implode(', ', $categories) . ' services',
            'amount' => $commissionAmount,
            'date' => $billDate->toDateString(),
            'status' => 'Active'
        ];

        $expenseData['updated_by'] = auth('admin')->user()->id;
        $expenseData['created_by'] = auth('admin')->user()->id;

        Expense::updateOrCreate(
            ['bill_number' => $billing->bill_number],
            $expenseData
        );
    }

    private function createPathologyRecord($billing, $pathologyItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $totalPathologyDiscount = $pathologyItems->sum('discount');
        $pathologyNetAmount = $pathologyItems->sum('net_amount');

        $existingPathology = Pathology::where('bill_no', $billing->bill_number)->first();

        if ($existingPathology) {
            $existingPathology->update([
                'patient_id' => $billing->patient_id,
                'apply_tpa' => false,
                'payee_id' => $data['referrer_id'],
                'date' => $billDate->format('Y-m-d'),
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'commission_percentage' => $data['commission_slider'] ?? 0,
                'commission_amount' => $data['physyst_amt'] ?? 0,
                'tests' => json_encode($this->formatPathologyItemsForMachine($pathologyItems)),
                'subtotal' => $pathologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalPathologyDiscount,
                'net_amount' => $pathologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $pathologyItems->sum('net_amount'),
                'note' => $data['remarks'],
                'updated_by' => auth('admin')->user()->id,
            ]);

            return $existingPathology;
        } else {
            $lastPathology = Pathology::withTrashed()->orderby('id', 'desc')->first();
            $pathologyNo = $this->generatePathologyNumber($lastPathology);

            $pathologyData = [
                'pathology_no' => $pathologyNo,
                'patient_id' => $billing->patient_id,
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'apply_tpa' => false,
                'payee_id' => $data['referrer_id'],
                'date' => $billDate->format('Y-m-d'),
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'commission_percentage' => $data['commission_slider'] ?? 0,
                'commission_amount' => $data['physyst_amt'] ?? 0,
                'tests' => json_encode($this->formatPathologyItemsForMachine($pathologyItems)),
                'subtotal' => $pathologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalPathologyDiscount,
                'net_amount' => $pathologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $pathologyItems->sum('net_amount'),
                'note' => $data['remarks'],
                'created_by' => auth('admin')->user()->id
            ];

            if ($billing->created_at) {
                $pathologyData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return Pathology::create($pathologyData);
        }
    }

    private function formatPathologyItemsForMachine($pathologyItems): array
    {
        $testsById = Test::query()
            ->whereIn('id', $pathologyItems->pluck('id')->filter()->unique())
            ->get(['id', 'test_name', 'test_short_name'])
            ->keyBy('id');

        return $pathologyItems->map(function ($item) use ($testsById) {
            $test = $testsById->get($item['id'] ?? null);
            $testCode = trim((string) ($test?->test_short_name ?? ''));

            return [
                'test_id' => $item['id'],
                'test_name' => $item['name'],
                'test_short_name' => $testCode,
                'test_code' => $testCode !== '' ? $testCode : (string) ($item['name'] ?? ''),
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'total_amount' => $item['total_amount'],
                'net_amount' => $item['net_amount'],
            ];
        })->values()->all();
    }

    private function generatePathologyNumber($lastPathology = null)
    {
        $prefix = web_setting_prefix('pathology_bill_prefix', 'Bill');

        if ($lastPathology && $lastPathology->pathology_no) {
            $lastNumber = (int) substr($lastPathology->pathology_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $newNumber;
    }

    private function createRadiologyRecord($billing, $radiologyItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();

        $totalRadiologyDiscount = $radiologyItems->sum('discount');
        $radiologyNetAmount = $radiologyItems->sum('net_amount');

        $existingRadiology = Radiology::withTrashed()->where('bill_no', $billing->bill_number)->first();

        if ($existingRadiology) {
            if ($existingRadiology->trashed()) {
                $existingRadiology->restore();
            }
            $existingRadiology->update([
                'patient_id' => $billing->patient_id,
                'referral_doctor_id' => $billing->doctor_id,
                'note' => $data['remarks'],
                'test_details' => json_encode($radiologyItems->map(function ($item) {
                    return [
                        'test_id' => $item['id'],
                        'test_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'total_amount' => $radiologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalRadiologyDiscount,
                'net_amount' => $radiologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $radiologyItems->sum('net_amount'),
                'updated_by' => auth('admin')->user()->id,
            ]);
        } else {
            $lastRadiology = Radiology::withTrashed()->orderby('id', 'desc')->first();
            $radiologyNo = $this->generateRadiologyNumber($lastRadiology);

            $lastBilling = $this->billingService->getLastBilling();
            $billNumber = $this->generateBillNumber($lastBilling);

            $caseNumber = $this->generateCaseNumber($lastBilling);

            $radiologyData = [
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'radiology_no' => $radiologyNo,
                'patient_id' => $billing->patient_id,
                'referral_doctor_id' => $billing->doctor_id,
                'note' => $data['remarks'],
                'test_details' => json_encode($radiologyItems->map(function ($item) {
                    return [
                        'test_id' => $item['id'],
                        'test_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'total_amount' => $radiologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalRadiologyDiscount,
                'net_amount' => $radiologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $radiologyItems->sum('net_amount'),
                'created_by' => auth('admin')->user()->id
            ];

            // set created_at to billing date/time when backdated
            if ($billing->created_at) {
                $radiologyData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return Radiology::create($radiologyData);
        }
    }

    private function normalizeBillingPaymentData(array $data): array
    {
        $payableAmount = max(0, (float) ($data['payable_amount'] ?? $data['total'] ?? 0));
        $requestedPaid = max(0, (float) ($data['paid_amt'] ?? 0));

        $receivingAmount = max(0, (float) ($data['receiving_amt'] ?? 0));
        $requestedReturn = max(0, (float) ($data['return_amt'] ?? 0));

        $effectivePaid = min($payableAmount, $requestedPaid);

        $hasExplicitReceiving = $receivingAmount > 0.0001;
        $hasExplicitReturn = $requestedReturn > 0.0001;
        $preserveExistingReturnOnEdit = !$hasExplicitReceiving && $hasExplicitReturn;

        if ($preserveExistingReturnOnEdit) {
            $grossReceived = max($requestedPaid, $requestedPaid + $requestedReturn);
        } else {
            $grossReceived = $hasExplicitReceiving ? max($receivingAmount, $requestedPaid) : max($requestedPaid, $receivingAmount);
        }

        $maxReturn = max(0, $grossReceived - $effectivePaid);
        $returnAmount = $hasExplicitReturn ? min($requestedReturn, $maxReturn) : $maxReturn;
        if ($returnAmount <= 0.0001 && $maxReturn > 0.0001) {
            $returnAmount = $maxReturn;
        }

        $effectivePaid = max(0, min($payableAmount, $grossReceived - $returnAmount));
        $dueAmount = max(0, round($payableAmount - $effectivePaid, 2));

        return [
            'payable_amount' => round($payableAmount, 2),
            'paid_amt' => round($effectivePaid, 2),
            'invoice_amount' => round($effectivePaid, 2),
            'receiving_amt' => round($grossReceived, 2),
            'change_amt' => round(max(0, (float) ($data['change_amt'] ?? 0)), 2),
            'due_amount' => round($dueAmount, 2),
            'return_amt' => round($returnAmount, 2),
        ];
    }

    private function determinePaymentStatus($paidAmount, $payableAmount, $total, $recevingAmount, $returnAmount = 0)
    {
        $paidAmount = floatval($paidAmount);
        $payableAmount = floatval($payableAmount);
        $returnAmount = floatval($returnAmount);

        if ($paidAmount >= $payableAmount) {
            if ($returnAmount > 0.0001) {
                return 'Partial';
            }
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    private function createPharmacyBillRecord($billing, $medicineItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $totalMedicineDiscount = $medicineItems->sum('discount');
        $medicineNetAmount = $medicineItems->sum('net_amount');
        $existingPharmacyBill = PharmacyBill::withTrashed()->where('bill_no', $billing->bill_number)->first();
        $ipdPatientQuery = IpdPatient::query()
            ->where('patient_id', $billing->patient_id);

        if (!empty($data['ipd_patient_id'])) {
            $ipdPatientQuery->whereKey((int) $data['ipd_patient_id']);
        } elseif ($existingPharmacyBill?->ipd_patient_id) {
            $ipdPatientQuery->whereKey((int) $existingPharmacyBill->ipd_patient_id);
        } else {
            $ipdPatientQuery->where('status', 'Active');
        }

        $ipdPatient = $ipdPatientQuery->latest('id')->first();
        $ipdPrescriptionId = null;
        if ($ipdPatient) {
            $preferredIpdPrescriptionId = $data['ipd_prescription_id']
                ?? $existingPharmacyBill?->ipd_prescription_id;
            $ipdPrescriptionQuery = IpdPrescription::query()
                ->where('ipd_patient_id', $ipdPatient->id);
            if ($preferredIpdPrescriptionId) {
                $ipdPrescriptionQuery->whereKey((int) $preferredIpdPrescriptionId);
            }

            $ipdPrescriptionId = $ipdPrescriptionQuery->value('id');

            if (!$ipdPrescriptionId) {
                $ipdPrescriptionId = IpdPrescription::query()
                    ->where('ipd_patient_id', $ipdPatient->id)
                    ->latest('id')
                    ->value('id');
            }
        }
        $ipdLinkData = [
            'ipd_patient_id' => $ipdPatient?->id,
            'ipd_prescription_id' => $ipdPrescriptionId,
            'billing_id' => $billing->id,
        ];

        if ($existingPharmacyBill) {
            if ($existingPharmacyBill->trashed()) {
                $existingPharmacyBill->restore();
            }
            $existingPharmacyBill->update([
                'patient_id' => $billing->patient_id,
                ...$ipdLinkData,
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'products' => json_encode($medicineItems->map(function ($item) {
                    return [
                        'medicine_id' => $item['id'],
                        'medicine_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'discount' => $item['discount'] ?? 0,
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'subtotal' => $medicineItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalMedicineDiscount,
                'net_amount' => $medicineNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $medicineNetAmount,
                'note' => $data['remarks'],
                'updated_by' => auth('admin')->user()->id,
            ]);

            return $existingPharmacyBill;
        } else {
            $pharmacyNo = $this->generatePharmacyNumber();

            $vatPercentage = 0.0;
            try {
                $ws = \App\Models\WebSetting::query()
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->first();

                if (!$ws) {
                    $ws = \App\Models\WebSetting::query()->orderByDesc('id')->first();
                }

                if (!empty($ws) && ($ws->vat_enabled ?? false)) {
                    $vatPercentage = (float) ($ws->vat_percent ?? 0);
                }
            } catch (\Throwable $e) {
                $vatPercentage = 0.0;
            }
            $vatAmount = ($medicineNetAmount * $vatPercentage) / 100.0;

            $pharmacyBillData = [
                ...$ipdLinkData,
                'pharmacy_no' => $pharmacyNo,
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'date' => $billDate->format('Y-m-d'),
                'patient_id' => $billing->patient_id,
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'products' => json_encode($medicineItems->map(function ($item) {
                    return [
                        'medicine_id' => $item['id'],
                        'medicine_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'discount' => $item['discount'] ?? 0,
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'subtotal' => $medicineItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalMedicineDiscount,
                'vat_percentage' => $vatPercentage,
                'vat_amount' => $vatAmount,
                'extra_discount' => $data['extra_flat_discount'] ?? 0,
                'net_amount' => $medicineNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $medicineNetAmount,
                'note' => $data['remarks'],
                'created_by' => auth('admin')->user()->id,
                'status' => 'Active',
            ];

            // if billing was backdated, set pharmacy created_at accordingly
            if ($billing->created_at) {
                $pharmacyBillData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return PharmacyBill::create($pharmacyBillData);
        }
    }

    protected function generatePharmacyNumber()
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        $year = date('Y');
        $lastPharmacyNumber = PharmacyBill::withTrashed()
            ->where('pharmacy_no', 'like', $prefix . $year . '%')
            ->lockForUpdate()
            ->orderByDesc('pharmacy_no')
            ->value('pharmacy_no');
        $lastNumber = $lastPharmacyNumber ? (int) substr((string) $lastPharmacyNumber, -4) : 0;
        $newNumber = str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);

        return $prefix . $year . $newNumber;
    }

    public function edit(Request $request, $id)
    {
        $billing = $this->billingService->find($id);

        if (!$billing) {
            return redirect()
                ->route('backend.billing.list')
                ->with('errorMessage', 'Billing not found.');
        }

        $isPharmacyMode = $request->boolean('pharmacy_mode') || user_is_pharmacy_only(auth('admin')->user());
        $hasGeneralBillingEdit = auth('admin')->user()->can('billing-edit');
        $hasLinkedPharmacyBill = PharmacyBill::query()
            ->where('billing_id', $billing->id)
            ->orWhere('bill_no', $billing->bill_number)
            ->exists();

        if (($isPharmacyMode && !$hasLinkedPharmacyBill) || (!$hasGeneralBillingEdit && !$isPharmacyMode)) {
            abort(403);
        }

        $billing->load('billItems');

        $hospitalCharges = $this->getHospitalCharges();

        $doctors = $this->getBillingDoctors();
        $patients = $this->getBillingPatients();
        $referrers = $this->getBillingReferrers();

        $editData = [
            'patient_id' => $billing->patient_id,
            'doctor_id' => $billing->doctor_id,
            'doctor_name' => $billing->doctor_name,
            'patient_mobile' => $billing->patient_mobile,
            'gender' => $billing->gender,
            'card_type' => $billing->card_type,
            'pay_mode' => $billing->pay_mode,
            'payment_type' => $billing->payment_type ?? $billing->pay_mode,
            'card_number' => $billing->card_number,
            'total' => $billing->total,
            'discount' => $billing->discount,
            'extra_flat_discount' => $billing->extra_flat_discount ?? '',
            'discount_type' => $billing->discount_type,
            'payable_amount' => $billing->payable_amount,
            'paid_amt' => $billing->paid_amt,
            'change_amt' => $billing->change_amt,
            'due_amount' => $billing->due_amount,
            'receiving_amt' => $billing->receiving_amt,
            'return_amt' => $billing->return_amt,
            'delivery_date' => $billing->delivery_date,
            'remarks' => $billing->remarks,
            'commission_total' => $billing->commission_total,
            'physyst_amt' => $billing->physyst_amt,
            'commission_slider' => $billing->commission_slider,
            'referrer_id' => $billing->referrer_id,
            'items' => $billing->billItems->map(function ($item) {
                $masterTest = Test::query()->find($item->item_id);
                $isDiscountRateLocked = (bool) ($masterTest?->discount_rate_locked ?? false);
                $lineTotal = (float) ($item->total_amount ?? 0);
                $savedDiscount = (float) ($item->discount ?? 0);
                $savedDiscountPercentage = $lineTotal > 0
                    ? round(($savedDiscount / $lineTotal) * 100, 4)
                    : 0;

                return [
                    'id' => $item->item_id,
                    'name' => $item->item_name,
                    'category' => $item->category,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'total_amount' => $item->total_amount,
                    'discount' => $item->discount,
                    'rugound' => $item->rugound,
                    'net_amount' => $item->net_amount,
                    'discount_exempt' => (bool) $item->discount_exempt,
                    'discount_percentage' => $isDiscountRateLocked
                        ? (float) ($masterTest?->discount_percentage ?? $savedDiscountPercentage)
                        : 0,
                    'discount_rate_locked' => $isDiscountRateLocked,
                    'room_no' => $item->room_no,
                ];
            })->toArray()
        ];

        // Expose billing date/time so frontend can pre-populate/backdate when editing
        if (!empty($billing->created_at)) {
            try {
                $editData['billing_date'] = $billing->created_at->format('Y-m-d');
                $editData['billing_time'] = $billing->created_at->format('H:i:s');
            } catch (\Throwable $e) {
                $editData['billing_date'] = null;
                $editData['billing_time'] = null;
            }
        } else {
            $editData['billing_date'] = null;
            $editData['billing_time'] = null;
        }

        if ($billing->doctor_id) {
            $doctorPrefix = $billing->doctor_type === 'billing' ? 'billing_' : 'admin_';
            $editData['doctor_id'] = $doctorPrefix . $billing->doctor_id;
        }

        $referrers = $this->referrerService->activeList();
        return Inertia::render(
            'Backend/Billing/BillingPage',
            [
                'pageTitle' => fn() => ($isPharmacyMode ? 'Edit Pharmacy Bill - ' : 'Edit Billing - ') . $billing->bill_number,
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Billing Manage'],
                    ['link' => route('backend.billing.list'), 'title' => 'Billing List'],
                    ['link' => route('backend.billing.edit', $id), 'title' => 'Edit Billing'],
                ],
                'billing' => fn() => $billing,
                'editData' => fn() => $editData,
                'isPharmacyDepartment' => fn() => $isPharmacyMode,
                'canAccessPharmacyBillList' => fn() => auth('admin')->user()->can('pharmacy-bill-list'),
                'pathologyAndRadiologyTests' => Inertia::defer(fn() => $this->getBillingTests(
                    ['Pathology', 'Radiology', 'ECG', 'Ultrasound'], 
                    'billing:tests:edit:v2'
                ), 'billing-catalog'),
                'billingCatalogLoaded' => Inertia::defer(fn() => true, 'billing-catalog'),
                'doctors' => Inertia::defer(fn() => $this->getBillingDoctors(), 'billing-support'),
                'patients' => Inertia::defer(fn() => $this->getBillingPatients(), 'billing-support'),
                'id' => fn() => $id,
                'referrers' => Inertia::defer(fn() => $referrers, 'billing-support'),
                'testCategories' => fn() => \App\Models\TestCategory::query()->whereNull('deleted_at')->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'categoryTypeOptions' => fn() => app(\App\Services\TestCategoryService::class)->activeNames(),
                'mainCategories' => fn() => \App\Models\MainCategory::query()->whereNull('deleted_at')->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'pathologyUnits' => fn() => app(\App\Services\PathologyUnitService::class)->activeList(),
                'testParameters' => fn() => app(\App\Services\PathologyParameterService::class)->activeList(),
                'chargeTypes' => fn() => \App\Models\ChargeType::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'chargeCategories' => fn() => \App\Models\ChargeCategory::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'chargeUnits' => fn() => \App\Models\ChargeUnitType::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
                'taxCategories' => fn() => \App\Models\ChargeTaxCategory::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            ]
        );
    }

    protected static function resolvePersistedCreatedAt($existingCreatedAt, array $data): ?string
    {
        if (!empty($data['billing_date'])) {
            $time = trim((string) ($data['billing_time'] ?? ''));

            if ($time === '' && $existingCreatedAt) {
                try {
                    $time = Carbon::parse($existingCreatedAt)->format('H:i:s');
                } catch (\Throwable $e) {
                    $time = '00:00:00';
                }
            }

            if ($time === '') {
                $time = '00:00:00';
            }

            try {
                return Carbon::parse($data['billing_date'] . ' ' . $time)->toDateTimeString();
            } catch (\Throwable $e) {
                return $existingCreatedAt ? Carbon::parse($existingCreatedAt)->toDateTimeString() : null;
            }
        }

        return $existingCreatedAt ? Carbon::parse($existingCreatedAt)->toDateTimeString() : null;
    }

    public function update(BillingRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $adminUser = auth('admin')->user();
            $pharmacyMode = (bool) ($data['pharmacy_mode'] ?? false) || user_is_pharmacy_only($adminUser);
            $hasGeneralBillingEdit = $adminUser->can('billing-edit');
            $linkedPharmacyBill = PharmacyBill::query()
                ->where('billing_id', $id)
                ->orWhereIn('bill_no', Billing::query()->whereKey($id)->select('bill_number'))
                ->exists();

            if (($pharmacyMode && !$linkedPharmacyBill) || (!$hasGeneralBillingEdit && !$pharmacyMode)) {
                abort(403);
            }

            if ($pharmacyMode && collect($data['items'])->contains(
                fn ($item) => strtolower(trim((string) ($item['category'] ?? ''))) !== 'medicine'
            )) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Pharmacy billing only supports medicine items.',
                ]);
            }

            $expectsJsonResponse = $request->wantsJson()
                || $request->ajax()
                || $request->expectsJson()
                || $request->hasHeader('X-Inertia')
                || $request->header('X-Requested-With') === 'XMLHttpRequest';

            $adminId = (int) ($adminUser?->id ?? 0);
            $adminName = trim((string) ($adminUser?->name ?? ''));
            if (!$this->hasOpenCounterSessionForAdmin($adminId, $adminName)) {
                $message = 'No active counter session found for your login. Please Counter Start first.';
                if ($expectsJsonResponse) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'errors' => ['counter' => $message],
                    ], 422);
                }

                return redirect()->back()->with('errorMessage', $message);
            }

            $doctorInfo = $this->handleDoctor($data['doctor_name'] ?? null);
            $billing = $this->billingService->find($id);


            if (!$billing) {
                return redirect()
                    ->route('backend.billing.list')
                    ->with('errorMessage', 'Billing not found.');
            }

            $previousRefundBalance = (float) ($billing->return_amt ?? 0);

            // Use unified patient handler with existing billing
            $patientResult = $this->handlePatientData($data, $billing);
            $patientId = $patientResult['patient_id'];
            $data = $patientResult['processed_data'];

            // Preserve existing return amount when edit does not include a new receipt.
            $incomingReturn = max(0, (float) ($data['return_amt'] ?? $data['return_amount'] ?? 0));
            $incomingReceiving = max(0, (float) ($data['receiving_amt'] ?? 0));
            if ($incomingReturn <= 0 && $incomingReceiving <= 0 && !empty($billing->return_amt)) {
                $data['return_amt'] = $billing->return_amt;
            }

            if ($incomingReturn <= 0.0001 && $incomingReceiving <= 0.0001 && !empty($billing->return_amt)) {
                $data['paid_amt'] = max(0, (float) ($billing->paid_amt ?? 0));
            }

            $data = array_merge($data, $this->normalizeBillingPaymentData($data));

            // Keep radiology-related subtypes distinct so Ultrasound/ECG do not
            // collapse into the Radiology bucket during bill edits.
            if (!empty($data['items']) && is_array($data['items'])) {
                $data['items'] = array_map(function ($it) {
                    $cat = strtolower(trim((string) ($it['category'] ?? '')));
                    $name = strtolower(trim((string) ($it['name'] ?? '')));

                    foreach (['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg', 'sono', 'sonography'] as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'Ultrasound';
                            return $it;
                        }
                    }

                    foreach (['ecg', 'ekg', 'electrocardiogram'] as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'ECG';
                            return $it;
                        }
                    }

                    foreach (['radiology', 'xray', 'x-ray', 'x ray', 'imaging', 'ct', 'mri', 'radiolog'] as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'Radiology';
                            return $it;
                        }
                    }

                    return $it;
                }, $data['items']);
            }

            // Store old quantities for medicine inventory rollback
            $oldBillItems = BillItem::where('billing_id', $id)->get();
            $oldBillItemSnapshots = $oldBillItems->map(function ($item) {
                return [
                    'id' => $item->item_id ?? $item->id,
                    'item_id' => $item->item_id ?? $item->id,
                    'item_name' => $item->item_name ?? $item->name ?? 'Item',
                    'total_amount' => $item->total_amount ?? $item->net_amount ?? $item->amount ?? 0,
                ];
            })->all();

            // Rollback medicine quantities from old items
            foreach ($oldBillItems as $oldItem) {
                if (strtolower($oldItem->category) === 'medicine') {
                    $medicine = MedicineInventory::find($oldItem->item_id);
                    if ($medicine) {
                        $medicine->increment('medicine_quantity', $oldItem->quantity);
                    }
                }
            }

            // Prepare updated billing data
            $billingData = [
                'patient_id' => $patientId,
                'patient_mobile' => $data['patient_mobile'],
                'gender' => $data['gender'],
                'referrer_id' => $data['referrer_id'] ?? null,
                'card_type' => $data['card_type'],
                'pay_mode' => $data['pay_mode'],
                'card_number' => $data['card_number'] ?? null,
                'total' => $data['total'],
                'discount' => $data['discount'] ?? 0,
                'extra_flat_discount' => $data['extra_flat_discount'] ?? 0,
                'discount_type' => $data['discount_type'] ?? 'percentage',
                // compute VAT for updated billing if applicable
                'vat_percentage' => 0.0,
                'vat_amount' => 0.0,
                'payable_amount' => $data['payable_amount'] ?? $data['total'],
                'paid_amt' => $data['paid_amt'],
                'invoice_amount' => $data['paid_amt'],
                'change_amt' => $data['change_amt'] ?? 0,
                'due_amount' => $data['due_amount'] ?? 0,
                'receiving_amt' => $data['receiving_amt'] ?? 0,
                'return_amt' => $data['return_amt'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'commission_total' => $data['commission_total'] ?? 0,
                'physyst_amt' => $data['physyst_amt'] ?? 0,
                'commission_slider' => $data['commission_slider'] ?? 0,
                'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt'], $data['return_amt'] ?? 0),
                'updated_by' => auth('admin')->user()->id,
                'doctor_id' => $doctorInfo['doctor_id'],
                'doctor_type' => $doctorInfo['doctor_type'],
                'doctor_name' => $doctorInfo['doctor_name'],
            ];

            // Use the requested billing date/time for backdated bills when
            // provided, otherwise preserve the existing created_at value.
            $billingData['created_at'] = self::resolvePersistedCreatedAt($billing->created_at ?? null, $data);

            // Update billing record
            $updatedBilling = $this->billingService->update($billingData, $id);

            // Persist created_at if provided so downstream records use the backdate
            if (!empty($billingData['created_at']) && $updatedBilling) {
                try {
                    $updatedBilling->created_at = $billingData['created_at'];
                    $updatedBilling->save();
                } catch (\Throwable $e) {
                }
            }
            // Replace the in-memory $billing with the updated model so
            // subsequent operations use the persisted created_at.
            $billing = $updatedBilling;
            if (!$updatedBilling) {
                throw new Exception('Failed to update billing record');
            }

            app(\App\Services\ReagentConsumptionService::class)->restoreForBilling((int) $id);

            // Delete existing billing items
            BillItem::where('billing_id', $id)->delete();

            // Recompute totals for update path to calculate VAT
            $totalBillAmountBeforeDiscount = collect($data['items'])->sum('total_amount');
            $discountableBillAmount = (($data['discount_type'] ?? '') === 'percentage')
                ? collect($data['items'])
                    ->reject(fn ($item) => $this->isDisposableBillingItem($item))
                    ->reject(fn ($item) => $this->isDiscountExemptBillingItem($item))
                    ->sum('total_amount')
                : collect($data['items'])
                    ->reject(fn ($item) => $this->isDisposableBillingItem($item))
                    ->reject(fn ($item) => $this->isDiscountExemptBillingItem($item))
                    ->sum('total_amount');
            $totalDiscountAmount = 0;
            if (($data['discount_type'] ?? '') === 'percentage') {
                $totalDiscountAmount = collect($data['items'])->sum(
                    fn ($item) => ((float) ($item['total_amount'] ?? 0) * $this->resolveBillingItemDiscountPercent($item, (float) ($data['discount'] ?? 0))) / 100
                );
            } else {
                $totalDiscountAmount = min(
                    max(0, (float) ($data['discount'] ?? 0)),
                    $discountableBillAmount
                );
            }

            $discountFactor = ($discountableBillAmount > 0)
                ? ($totalDiscountAmount / $discountableBillAmount)
                : 0;

            $vatPercentage = 0.0;
            $vatAmount = 0.0;
            try {
                $ws = \App\Models\WebSetting::query()
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->first();

                if (!$ws) {
                    $ws = \App\Models\WebSetting::query()->orderByDesc('id')->first();
                }

                if (!empty($ws) && ($ws->vat_enabled ?? false)) {
                    $vatPercentage = (float) ($ws->vat_percent ?? 0);
                }
            } catch (\Throwable $e) {
                $vatPercentage = 0.0;
            }
            $extraFlat = (float) ($data['extra_flat_discount'] ?? 0);
            $netBeforeVat = max(0, $totalBillAmountBeforeDiscount - $totalDiscountAmount - $extraFlat);
            if ($vatPercentage > 0 && $netBeforeVat > 0) {
                $vatAmount = ($netBeforeVat * $vatPercentage) / 100.0;
            }

            // Update billingData vat values and payable_amount from the discounted base amount
            $billingData['vat_percentage'] = $vatPercentage;
            $billingData['vat_amount'] = $vatAmount;
            $billingData['payable_amount'] = max(0, round($netBeforeVat + $vatAmount, 2));
            $billingData['due_amount'] = max(0, round($billingData['payable_amount'] - $billingData['paid_amt'], 2));
            $billingData['payment_status'] = $this->determinePaymentStatus(
                $billingData['paid_amt'],
                $billingData['payable_amount'],
                $data['total'],
                $billingData['receiving_amt'],
                $billingData['return_amt'] ?? 0
            );

            $newBillItemSnapshots = [];

            // Create new billing items and update medicine inventory
            foreach ($data['items'] as $item) {
                $commissionable = null;
                $commission_rate = null;
                $catLower = strtolower($item['category'] ?? '');
                if (in_array($catLower, ['pathology', 'radiology'])) {
                    $master = \App\Models\Test::find($item['id']);
                    if ($master) {
                        $commissionable = $master->commissionable ?? null;
                        $commission_rate = $master->commission_rate ?? null;
                    }
                }

                    $isDisposable = false;
                    $itemNameLower = strtolower($item['name'] ?? '');
                    if ($catLower === 'pathology') {
                        if (str_contains($itemNameLower, 'disposable') || str_contains($itemNameLower, 'tube') || str_contains($itemNameLower, 'butterfly') || str_contains($itemNameLower, 'needle')) {
                            $isDisposable = true;
                        }
                    }

                $isRadiologyItem = in_array($catLower, ['radiology', 'ecg', 'ultrasound', 'xray', 'ultrasonogram', 'ultrasonography']);
                $itemDiscount = ($this->isDisposableBillingItem($item) || $this->isDiscountExemptBillingItem($item)
                    || ((bool) ($item['discount_rate_locked'] ?? false) && (float) ($item['discount_percentage'] ?? 0) <= 0))
                    ? 0
                    : (($data['discount_allocation'] ?? 'proportional') === 'equal'
                        ? (float) ($item['discount'] ?? 0)
                        : (($data['discount_type'] ?? '') === 'percentage'
                            ? ($item['total_amount'] * $this->resolveBillingItemDiscountPercent($item, (float) ($data['discount'] ?? 0))) / 100
                            : (float) ($item['discount'] ?? 0)));
                $itemNetAmount = ($data['discount_type'] ?? '') === 'percentage'
                    ? ($item['total_amount'] - $itemDiscount)
                    : ($item['net_amount'] ?? $item['total_amount']);

                $newBillItemSnapshots[] = [
                    'id' => $item['id'],
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'total_amount' => $item['total_amount'] ?? $item['net_amount'] ?? 0,
                ];

                $billItem = BillItem::create([
                    'billing_id' => $id,
                    'department_id' => $item['department_id'] ?? null,
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'room_no' => $this->resolveCategoryBillingRoomNo((string) $item['category'])
                        ?? ($item['room_no'] ?? $item['roomNo'] ?? (isset($master) ? ($master->room_no ?? null) : null)),
                    'category' => $item['category'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'discount' => $itemDiscount,
                    'discount_exempt' => $this->isDiscountExemptBillingItem($item),
                    'rugound' => $item['rugound'] ?? 0,
                    'net_amount' => $itemNetAmount,
                    'requires_sample' => $this->requiresSampleCollection($item, $catLower, $isDisposable),
                    'sample_collected_at' => $isRadiologyItem ? ($billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now()) : null,
                    'sample_collected_by' => $isRadiologyItem ? auth('admin')->id() : null,
                    'created_at' => $billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now(),
                    'status' => 'Active',
                    'commissionable' => $commissionable,
                    'commission_rate' => $commission_rate,
                ]);

                app(\App\Services\ReagentConsumptionService::class)->consumeForBillItem($billItem);

                // Update medicine inventory for new quantities
                if (strtolower($item['category']) === 'medicine') {
                    $medicine = MedicineInventory::find($item['id']);
                    if ($medicine) {
                        $newQuantity = $medicine->medicine_quantity - $item['quantity'];
                        $medicine->update(['medicine_quantity' => max(0, $newQuantity)]);
                    }
                }
            }

            $itemChanges = ActivityLogService::buildBillingItemChangeSummary($oldBillItemSnapshots, $newBillItemSnapshots);

            $this->updatePaymentRecords($id, $data);

            // When an already-paid bill is edited and an item is removed, the
            // reduced payable amount becomes a pending refundable balance.
            // It must not affect Net Income until Refund List processing.
            $billing = Billing::findOrFail($id);
            $processedRefundAmount = (float) DB::table('refund_transactions')
                ->where('billing_id', $id)
                ->sum('refund_amount');
            $refundableBalance = max(
                0,
                round((float) ($billing->paid_amt ?? 0) - (float) ($billing->payable_amount ?? 0) - $processedRefundAmount, 2)
            );
            if (abs($refundableBalance - $previousRefundBalance) > 0.009) {
                $billing->return_amt = $refundableBalance;
                $billing->save();
            }
            // Update related records (pathology, radiology, pharmacy)
            $pathologyItems = collect($data['items'])->where('category', 'Pathology');
            if ($pathologyItems->isNotEmpty()) {
                $this->createOrUpdatePathologyRecord($billing, $pathologyItems, $data);
            } else {
                Pathology::where('bill_no', $billing->bill_number)->delete();
            }

            $radiologyItems = collect($data['items'])->filter(function ($item) {
                $cat = strtolower((string) ($item['category'] ?? ''));
                $normalized = preg_replace('/[^a-z0-9]/', '', $cat);
                return in_array($normalized, ['radiology', 'ultrasound', 'xray', 'ecg', 'ultrasonogram', 'ultrasonography']);
            });
            if ($radiologyItems->isNotEmpty()) {
                Radiology::where('case_id', $billing->case_number . '-RAD')->delete();
                $this->createRadiologyRecord($billing, $radiologyItems, $data);
            } else {
                Radiology::where('case_id', $billing->case_number . '-RAD')->delete();
            }

            $medicineItems = collect($data['items'])->where('category', 'Medicine');
            if ($medicineItems->isNotEmpty()) {
                PharmacyBill::where('bill_no', $billing->bill_number)->delete();
                $this->createPharmacyBillRecord($billing, $medicineItems, $data);
            } else {
                PharmacyBill::where('bill_no', $billing->bill_number)->delete();
            }

            // Handle referral commission
            if ($data['referrer_id']) {
                Referral::where('billing_id', $id)->delete();

                $referrer = $this->referrerService->find($data['referrer_id']);
                $commissionResult = app(ReferralCommissionCalculator::class)->calculate(
                    $data['items'],
                    (float) $billingData['payable_amount'],
                    function ($item) use ($referrer) {
                        $category = $this->normalizeReferralCommissionCategory($item['category'] ?? '', $item['name'] ?? '');

                        return [
                            'category' => $category,
                            'rate' => $this->resolveReferrerCommissionRate(
                                $referrer,
                                $category,
                                isset($item['id']) ? (int) $item['id'] : null,
                                (string) ($item['name'] ?? ''),
                                array_key_exists('referral_percentage', $item)
                                    ? (float) $item['referral_percentage']
                                    : null
                            ),
                        ];
                    }
                );
                $totalCommission = $commissionResult['total'];
                $categoryCommissions = $commissionResult['categories'];
                $data['commission_total'] = $totalCommission;
                $data['physyst_amt'] = $totalCommission;
                $billing->commission_total = $totalCommission;
                $billing->physyst_amt = $totalCommission;
                $billing->save();

                $refData = [
                    'billing_id' => $id,
                    'payee_id' => $data['referrer_id'],
                    'total_commission_amount' => $totalCommission,
                    'category_commissions' => $categoryCommissions,
                    'date' => ($billing->created_at ? Carbon::parse($billing->created_at)->toDateString() : now()->toDateString()),
                    'total_bill_amount' => $billingData['payable_amount'],
                    'status' => 'Active'
                ];

                if ($billing->created_at) {
                    $refData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
                }

                Referral::create($refData);

            } else {
                Referral::where('billing_id', $id)->delete();
            }

            if (!$data['referrer_id']) {
                Expense::where('bill_number', $billing->bill_number)->delete();
            }

            $message = 'Billing updated successfully with Bill No: ' . $billing->bill_number;
            $this->storeAdminWorkLog($id, 'billings', $message);
            ActivityLogService::logUpdate(
                'Billing',
                $billing->id,
                $billing->bill_number ?? ('Billing#' . $billing->id),
                [
                    'changes' => [
                        'bill_number' => $billing->bill_number,
                        'invoice_number' => $billing->invoice_number ?? null,
                        'case_number' => $billing->case_number ?? null,
                        'patient_id' => $billing->patient_id,
                        'total_amount' => $billing->total,
                        'payable_amount' => $billing->payable_amount,
                        'paid_amt' => $billing->paid_amt,
                        'due_amount' => $billing->due_amount,
                        'payment_status' => $billing->payment_status,
                        'item_changes' => $itemChanges,
                    ],
                    'snapshot' => ActivityLogService::buildBillingDeleteMeta($billing),
                ],
                []
            );

            DB::commit();

            $printToken = (string) ($request->input('print_token') ?? '');
            if ($printToken !== '') {
                try {
                    Cache::put('print_token_' . $printToken, $billing->id, now()->addMinutes(10));
                } catch (\Throwable $e) {
                    Log::debug('billing.update.print_token_cache_failed', ['id' => $id, 'print_token' => $printToken, 'error' => $e->getMessage()]);
                }
            }

            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                try {
                    $invoiceUrl = route('backend.download.invoice', ['id' => $billing->id, 'module' => 'billing', 'fast_open' => 1, 'auto_print' => 1]);
                } catch (\Throwable $e) {
                    $invoiceUrl = url('/download-invoice?id=' . $billing->id . '&module=billing&fast_open=1&auto_print=1');
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'billId' => $billing->id,
                    'invoiceUrl' => $invoiceUrl,
                ]);
            }

            return redirect()
                ->route('backend.billing.list')
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (\Illuminate\Validation\ValidationException $err) {
            DB::rollBack();
            throw $err;
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'update', substr($err->getMessage(), 0, 1000));

            $message = "Server error occurred: " . $err->getMessage();
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'errors' => $message,
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', $message);
        }
    }

    private function createOrUpdatePathologyRecord($billing, $pathologyItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $totalPathologyDiscount = $pathologyItems->sum('discount');
        $pathologyNetAmount = $pathologyItems->sum('net_amount');

        $pathologyData = [
            'patient_id' => $billing->patient_id,
            'apply_tpa' => false,
            'payee_id' => $data['referrer_id'] ?? null,
            'date' => $billDate->format('Y-m-d'),
            'doctor_id' => $billing->doctor_id,
            'doctor_name' => $billing->doctor_name,
            'commission_percentage' => $data['commission_slider'] ?? 0,
            'commission_amount' => $data['physyst_amt'] ?? 0,
            'tests' => json_encode($this->formatPathologyItemsForMachine($pathologyItems)),
            'subtotal' => $pathologyItems->sum('total_amount'),
            'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
            'discount_amount' => $totalPathologyDiscount,
            'net_amount' => $pathologyNetAmount,
            'payment_mode' => $data['pay_mode'],
            'payment_amount' => $pathologyItems->sum('net_amount'),
            'note' => $data['remarks'],
            'updated_by' => auth('admin')->user()->id,
        ];

        $existingPathology = Pathology::withTrashed()->where('bill_no', $billing->bill_number)->first();

        if ($existingPathology) {
            if ($existingPathology->trashed()) {
                $existingPathology->restore();
            }
            $existingPathology->update($pathologyData);
            return $existingPathology;
        } else {
            $lastPathology = Pathology::withTrashed()->orderby('id', 'desc')->first();
            $pathologyNo = $this->generatePathologyNumber($lastPathology);

            $pathologyData['pathology_no'] = $pathologyNo;
            $pathologyData['bill_no'] = $billing->bill_number;
            $pathologyData['case_id'] = $billing->case_number;
            $pathologyData['created_by'] = auth('admin')->user()->id;
            if ($billing->created_at) {
                $pathologyData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return Pathology::create($pathologyData);
        }
    }

    // Helper method for updating payment records
    private function updatePaymentRecords($billingId, $data)
    {
        // Edit mode must preserve the existing payment baseline. A new save request
        // without a fresh receiving amount should not create duplicate payments or
        // silently reset the bill to a different paid total.
        $incomingPaid = floatval($data['paid_amt'] ?? 0);
        $billing = Billing::find($billingId);
        $baselinePaid = $billing ? max(0, (float) ($billing->paid_amt ?? 0)) : 0;
        $baselineReturn = $billing ? max(0, (float) ($billing->return_amt ?? 0)) : 0;
        $baselineGrossReceived = max(0, $baselinePaid - $baselineReturn);

        if ($incomingPaid <= 0) {
            if ($billing) {
                $billing->paid_amt = $baselineGrossReceived;
                $billing->payable_amount = max(0, (float) ($data['payable_amount'] ?? $billing->payable_amount ?? $billing->total ?? 0));
                $billing->due_amount = max(0, round($billing->payable_amount - $billing->paid_amt, 2));
                $billing->payment_status = $this->determinePaymentStatus($billing->paid_amt, $billing->payable_amount, $billing->total ?? 0, $data['receiving_amt'] ?? 0, $billing->return_amt ?? 0);
                $billing->invoice_amount = $billing->paid_amt;
                $billing->save();
            }
            return;
        }

        $existingPaymentsSum = (float) Payment::where('billing_id', $billingId)->whereNull('deleted_at')->sum('amount');
        $existingDueCollected = (float) \App\Models\DueCollection::where('billing_id', $billingId)->sum('collected_amount');
        $existingPaid = $existingPaymentsSum + $existingDueCollected;
        $delta = $incomingPaid - $existingPaid;

        if ($delta > 0.0001) {
            $receivingAmount = floatval($data['receiving_amt'] ?? 0);
            if ($receivingAmount > 0.0001) {
                $paymentAmount = round(min($delta, $receivingAmount), 2);
                if ($paymentAmount > 0.0001) {
                    $paymentData = [
                        'billing_id' => $billingId,
                        'amount' => $paymentAmount,
                        'payment_method' => $data['pay_mode'] ?? null,
                        'transaction_id' => $data['card_number'] ?? null,
                        'notes' => $data['remarks'] ?? null,
                        'received_by' => auth('admin')->user()->id,
                        'payment_status' => $this->determinePaymentStatus($incomingPaid, $data['payable_amount'] ?? 0, $data['total'] ?? 0, $data['receiving_amt'] ?? 0, $data['return_amt'] ?? 0),
                    ];

                    $billingForPayment = Billing::find($billingId);
                    if ($billingForPayment && $billingForPayment->created_at) {
                        $paymentData['created_at'] = Carbon::parse($billingForPayment->created_at)->toDateTimeString();
                    }

                    Payment::create($paymentData);

                    try {
                        $payableBefore = round(max(0, (float) ($data['payable_amount'] ?? $billing?->payable_amount ?? 0)), 2);
                        $dueBefore = max(0, round($payableBefore - $existingPaid, 2));
                        $counterAccount = $this->ledgerService->resolvePaymentAccountCode($data['pay_mode'] ?? null);
                        $createdBy = auth('admin')->user()->id ?? null;
                        $billLabel = $billing?->bill_number ?? $billingId;
                        $ledgerDate = $billingForPayment?->created_at
                            ? Carbon::parse($billingForPayment->created_at)->toDateString()
                            : now()->toDateString();

                        $arAmount = round(min($paymentAmount, $dueBefore), 2);
                        $incomeAmount = round(max(0, $paymentAmount - $arAmount), 2);

                        if ($arAmount > 0) {
                            $this->ledgerService->recordArCollection(
                                $counterAccount,
                                $arAmount,
                                'Billing due collection for ' . $billLabel,
                                $ledgerDate,
                                'Billing',
                                $billingId,
                                $createdBy
                            );
                        }

                        if ($incomeAmount > 0) {
                            $this->ledgerService->recordIncome(
                                'DIAG_INC',
                                $counterAccount,
                                $incomeAmount,
                                'Billing additional payment for ' . $billLabel,
                                $ledgerDate,
                                'Billing',
                                $billingId,
                                $createdBy
                            );
                        }
                    } catch (\Exception $e) {
                        $this->storeSystemError('Backend', 'BillingController', 'updatePaymentRecords->ledger', substr($e->getMessage(), 0, 1000));
                    }
                }
            }
        }

        $billing = Billing::find($billingId);
        if ($billing) {
            $paymentsSum = (float) Payment::where('billing_id', $billingId)->whereNull('deleted_at')->sum('amount');
            $dueCollected = (float) \App\Models\DueCollection::where('billing_id', $billingId)->sum('collected_amount');
            $totalPaid = round($paymentsSum + $dueCollected, 2);

            $billing->paid_amt = $totalPaid;
            $payable = round(max(0, (float) ($data['payable_amount'] ?? $billing->payable_amount ?? $billing->total ?? 0)), 2);
            $billing->payable_amount = $payable;
            $billing->due_amount = max(0, round($payable - $billing->paid_amt, 2));
            $billing->payment_status = $this->determinePaymentStatus($billing->paid_amt, $payable, $billing->total ?? 0, $data['receiving_amt'] ?? 0, $billing->return_amt ?? 0);
            $billing->invoice_amount = $billing->paid_amt;

            $requestedReturn = max(0, (float) ($data['return_amt'] ?? $data['return_amount'] ?? 0));
            $billing->return_amt = round($requestedReturn, 2);
            if ($billing->return_amt <= 0.0001 && (float) ($data['paid_amt'] ?? 0) > (float) ($payable)) {
                $billing->return_amt = round(max(0, (float) ($data['paid_amt'] ?? 0) - (float) ($payable)), 2);
            }

            $billing->save();
        }
    }

    private function generateRadiologyNumber($lastRadiology = null)
    {
        $prefix = web_setting_prefix('radiology_bill_prefix', 'RADB');
        $year = date('Y');

        if ($lastRadiology && $lastRadiology->radiology_no) {
            $lastNumber = (int) substr($lastRadiology->radiology_no, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }

        private function nextSequentialBillingNumber(string $field, string $prefix, int $digits): string
    {
        $ym = now()->format('Ym');
        $like = $prefix . $ym . '%';

        $lastValue = Billing::withTrashed()
            ->where($field, 'like', $like)
            ->lockForUpdate()
            ->orderBy($field, 'desc')
            ->value($field);

        $lastNumber = $lastValue ? (int) substr($lastValue, -$digits) : 0;

        return $prefix . $ym . str_pad((string) ($lastNumber + 1), $digits, '0', STR_PAD_LEFT);
    }

    private function generateBillNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('billing_bill_prefix', 'BILL');
        return $this->nextSequentialBillingNumber('bill_number', $prefix, 4);
    }

    private function generateInvoiceNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('billing_bill_prefix', 'BILL');
        // Keep the existing compact format: PREFIXYYYYMMxxxxx
        return $this->nextSequentialBillingNumber('invoice_number', $prefix, 5);
    }

    private function generateCaseNumber($lastBilling = null)
    {
        return $this->nextSequentialBillingNumber('case_number', 'CASE', 4);
    }


    public function destroy($id)
    {

        DB::beginTransaction();

        try {
            $billingInfo = Billing::find($id);

            if ($this->billingService->deleteBIllingWithPathoRadioPharm($id)) {
                $message = 'Billing deleted successfully';
                $this->storeAdminWorkLog($id, 'billings', $message);
                ActivityLogService::logDelete(
                    'Billing',
                    $id,
                    $billingInfo?->bill_number ?? ('Billing#' . $id),
                    ActivityLogService::buildBillingDeleteMeta($billingInfo)
                );

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Billing.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->billingService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Billing ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billings', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Billing.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function pendingList()
    {
        return Inertia::render(
            'Backend/Billing/PendingList',
            [
                'pageTitle' => fn() => 'Pending Billing List',
                'tableHeaders' => fn() => $this->getpendingListTableHeaders(),
                'dataFields' => fn() => $this->datapendingListFields(),
                'datas' => fn() => $this->getpendingListDatas(),
            ]
        );
    }

    private function getpendingListDatas()
    {
        $nameFilter = trim((string) request('name', ''));

        $billingRows = $this->billingService->pendingList()
            ->when($nameFilter !== '', function ($query) use ($nameFilter) {
                $query->whereHas('patient', function ($patientQuery) use ($nameFilter) {
                    $patientQuery->where('name', 'like', '%' . $nameFilter . '%');
                });
            })
            ->get()
            ->map(function ($data) {
                $customData = new \stdClass();
                $customData->sort_at = $data->created_at;
                $customData->bill_number = $data->bill_number;
                $customData->row_id = $data->id;
                $customData->row_type = 'billing';
                $customData->case_number = $data->case_number;
                $customData->patient_id = $data?->patient?->name ?? '';
                $customData->total = number_format((float) ($data->total ?? 0), 2);
                $customData->paid_amt = number_format((float) ($data->paid_amt ?? 0), 2);
                $customData->due_amount = (float) ($data->due_amount ?? 0);
                $customData->due_amount_display = number_format((float) ($data->due_amount ?? 0), 2);
                if (!empty($data->delivery_date)) {
                    try {
                        $customData->delivery_date = ($data->delivery_date instanceof \DateTime)
                            ? $data->delivery_date->format('d-m-Y h:i A')
                            : Carbon::parse($data->delivery_date)->format('d-m-Y h:i A');
                    } catch (\Throwable $e) {
                        $customData->delivery_date = (string) $data->delivery_date;
                    }
                } else {
                    $customData->delivery_date = '';
                }
                $customData->created_by = $data?->admin?->name ?? '';
                $customData->created_at_display = $data->created_at instanceof \DateTime
                    ? $data->created_at->format('d-m-Y h:i A')
                    : (string) $data->created_at;
                $customData->payment_status = $data->payment_status;
                $customData->hasLink = true;

                $links = [];

                if (
                    $data->payment_status !== 'Paid' &&
                    (float) $data->due_amount > 0 &&
                    \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-due-collect')
                ) {
                    $links[] = [
                        'linkClass' => 'bg-purple-600 text-white semi-bold',
                        'link' => route('backend.due.collect', $data->id),
                        'linkLabel' => 'Due Collect',
                        'action_name' => 'due-collect',
                        'action_id' => 'billing|' . $data->id,
                    ];
                }

                $links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.invoice', [
                        'id' => $data->id,
                        'module' => 'billing',
                        'fast_open' => 1,
                    ]),
                    'linkLabel' => 'Invoice',
                    'target' => '_blank',
                ];

                $customData->links = $links;

                return $customData;
            });

        $opdRows = OpdPatient::query()
            ->with(['patient', 'doctor'])
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('payment_status', '!=', 'Paid')
            ->where('balance_amount', '>', 0)
            ->when($nameFilter !== '', function ($query) use ($nameFilter) {
                $query->whereHas('patient', function ($patientQuery) use ($nameFilter) {
                    $patientQuery->where('name', 'like', '%' . $nameFilter . '%');
                });
            })
            ->get()
            ->map(function ($data) {
                $customData = new \stdClass();
                $customData->sort_at = $data->created_at;
                $customData->bill_number = 'OPD-' . str_pad((string) $data->id, 4, '0', STR_PAD_LEFT);
                $customData->row_id = $data->id;
                $customData->row_type = 'opd';
                $customData->case_number = 'OPD';
                $customData->patient_id = $data?->patient?->name ?? '';
                $customData->total = number_format((float) ($data->amount ?? 0), 2);
                $customData->paid_amt = number_format((float) ($data->paid_amount ?? 0), 2);
                $customData->due_amount = (float) ($data->balance_amount ?? 0);
                $customData->due_amount_display = number_format((float) ($data->balance_amount ?? 0), 2);
                if (!empty($data->appointment_date)) {
                    try {
                        $customData->delivery_date = ($data->appointment_date instanceof \DateTime)
                            ? $data->appointment_date->format('d-m-Y h:i A')
                            : Carbon::parse($data->appointment_date)->format('d-m-Y h:i A');
                    } catch (\Throwable $e) {
                        $customData->delivery_date = (string) $data->appointment_date;
                    }
                } else {
                    $customData->delivery_date = '';
                }
                $customData->created_by = $data?->doctor?->name ?? '';
                $customData->created_at_display = $data->created_at instanceof \DateTime
                    ? $data->created_at->format('d-m-Y h:i A')
                    : (string) $data->created_at;
                $customData->payment_status = $data->payment_status;
                $customData->hasLink = true;

                $links = [];

                if (\Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-due-collect')) {
                    $links[] = [
                        'linkClass' => 'bg-purple-600 text-white semi-bold',
                        'link' => route('backend.opd.due.collect', $data->id),
                        'linkLabel' => 'Due Collect',
                        'action_name' => 'due-collect',
                        'action_id' => 'opd|' . $data->id,
                    ];
                }

                $links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.opd.bill', [
                        'id' => $data->id,
                        'module' => 'opd'
                    ]),
                    'linkLabel' => 'Invoice',
                    'target' => '_blank',
                ];

                $customData->links = $links;

                return $customData;
            });

        $mergedRows = $billingRows
            ->concat($opdRows)
            ->sortByDesc(function ($row) {
                return $row->sort_at;
            })
            ->values();

        $perPage = (int) (request()->numOfData ?? 10);
        $currentPage = (int) request()->get('page', 1);
        $offset = max(0, ($currentPage - 1) * $perPage);

        $pageRows = $mergedRows
            ->slice($offset, $perPage)
            ->values()
            ->map(function ($row, $index) use ($offset) {
                $row->index = $offset + $index + 1;
                unset($row->sort_at);
                return $row;
            });

        return regeneratePagination($pageRows, $mergedRows->count(), $perPage, $currentPage);
    }

    public function refundList()
    {
        return Inertia::render(
            'Backend/Billing/RefundList',
            [
                'pageTitle' => function () {
                    return 'Refund Billing List';
                },
                'tableHeaders' => function () {
                    return $this->getRefundListTableHeaders();
                },
                'dataFields' => function () {
                    return $this->dataRefundListFields();
                },
                'datas' => function () {
                    return $this->getRefundListDatas();
                },
                'filters' => function () {
                    return [
                        'name' => trim((string) request('name', '')),
                        'numOfData' => (int) request()->get('numOfData', 10),
                    ];
                },
            ]
        );
    }

    private function getRefundListDatas()
    {
        $nameFilter = trim((string) request('name', ''));

        $billingRows = Billing::with(['patient', 'payments'])
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('return_amt', '>', 0.0001)
            ->where('total', '>', 0)
            ->whereNotNull('created_at')
            ->whereRaw("TRIM(COALESCE(bill_number, '')) <> ''")
            ->when($nameFilter !== '', function ($query) use ($nameFilter) {
                $query->where(function ($q) use ($nameFilter) {
                    $q->where('bill_number', 'like', '%' . $nameFilter . '%')
                        ->orWhere('invoice_number', 'like', '%' . $nameFilter . '%')
                        ->orWhereHas('patient', function ($patientQuery) use ($nameFilter) {
                            $patientQuery->where('name', 'like', '%' . $nameFilter . '%');
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($data) {
                return $this->buildRefundListRowData($data);
            });

        $perPage = (int) (request()->numOfData ?? 10);
        $currentPage = (int) request()->get('page', 1);
        $offset = max(0, ($currentPage - 1) * $perPage);

        $pageRows = $billingRows
            ->slice($offset, $perPage)
            ->values()
            ->map(function ($row, $index) use ($offset) {
                $row->index = $offset + $index + 1;
                unset($row->sort_at);
                return $row;
            });

        return regeneratePagination($pageRows, $billingRows->count(), $perPage, $currentPage);
    }

    private function buildRefundListRowData($data): \stdClass
    {
        $payments = $this->resolveRefundRowValue($data, 'payments');

        $latestPayment = null;
        if ($payments instanceof \Illuminate\Support\Collection) {
            $latestPayment = $payments->sortByDesc('created_at')->first();
        } elseif (is_array($payments) && count($payments) > 0) {
            $latestPayment = collect($payments)->sortByDesc('created_at')->first();
        } elseif (is_object($payments) && method_exists($payments, 'sortByDesc')) {
            $latestPayment = $payments->sortByDesc('created_at')->first();
        }

        $paidAt = $latestPayment?->created_at ?? null;

        $patient = $this->resolveRefundRowValue($data, 'patient');
        $patientName = '';
        if (is_object($patient)) {
            $patientName = trim((string) ($this->resolveRefundRowValue($patient, 'name', '') ?? ''));
        } elseif (is_array($patient)) {
            $patientName = trim((string) ($patient['name'] ?? ''));
        }

        $billId = $this->resolveRefundRowValue($data, 'id');
        $billNumber = trim((string) ($this->resolveRefundRowValue($data, 'bill_number', '') ?? ''));
        $createdAt = $this->resolveRefundRowValue($data, 'created_at');

        $total = (float) ($this->resolveRefundRowValue($data, 'total', 0) ?? 0);
        $paidAmount = (float) ($this->resolveRefundRowValue($data, 'paid_amt', 0) ?? 0);
        $payableAmount = (float) ($this->resolveRefundRowValue($data, 'payable_amount', $total) ?? $total);

        $returnAmount = 0.0;
        if (is_object($data) && method_exists($data, 'getEffectiveRefundAmount')) {
            $returnAmount = (float) $data->getEffectiveRefundAmount();
        } else {
            $returnAmount = (float) ($this->resolveRefundRowValue($data, 'return_amt', 0) ?? 0);
            if ($returnAmount <= 0.0001) {
                $returnAmount = (float) ($this->resolveRefundRowValue($data, 'return_amount', 0) ?? 0);
            }
        }

        $paymentStatus = 'Pending';
        if (is_object($data) && method_exists($data, 'getPaymentStatus')) {
            $paymentStatus = (string) $data->getPaymentStatus();
        } else {
            if ($paidAmount >= $payableAmount) {
                $paymentStatus = $returnAmount > 0.0001 ? 'Partial' : 'Paid';
            } elseif ($paidAmount > 0.0001) {
                $paymentStatus = 'Partial';
            } else {
                $paymentStatus = 'Pending';
            }
        }

        $customData = new \stdClass();
        $customData->sort_at = $createdAt;
        $customData->bill_number = $billNumber !== '' ? $billNumber : 'N/A';
        $customData->row_id = $billId;
        $customData->row_type = 'billing';
        $customData->patient_name = $patientName !== '' ? $patientName : 'N/A';
        $customData->total = number_format($total, 2);
        $customData->paid_amt = number_format($paidAmount, 2);
        $customData->return_amt = $returnAmount;
        $customData->return_amt_display = number_format($returnAmount, 2);
        $customData->payment_status = $paymentStatus;
        $customData->paid_at_display = $paidAt instanceof \DateTime
            ? $paidAt->format('d-m-Y h:i A')
            : ($paidAt ? (string) $paidAt : '');
        $customData->created_at_display = $createdAt
            ? Carbon::parse($createdAt)->format('d-m-Y h:i A')
            : 'N/A';
        $customData->hasLink = true;

        $invoiceLink = '#';
        if ($billId) {
            try {
                if (function_exists('route')) {
                    $invoiceLink = route('backend.download.invoice', [
                        'id' => $billId,
                        'module' => 'billing',
                        'fast_open' => 1,
                    ]);
                }
            } catch (\Throwable $e) {
                $invoiceLink = '/download-invoice?id=' . urlencode((string) $billId) . '&module=billing&fast_open=1';
            }
        }

        $customData->links = [
            [
                'linkClass' => 'bg-orange-600 text-white semi-bold',
                'linkLabel' => 'Refund',
                'action_name' => 'refund',
                'action_id' => $billId,
            ],
            [
                'linkClass' => 'bg-teal-500 text-white semi-bold',
                'link' => $invoiceLink,
                'linkLabel' => 'Invoice',
                'target' => '_blank',
            ],
        ];

        return $customData;
    }

    private function resolveRefundRowValue($data, string $key, $default = null)
    {
        if (is_array($data)) {
            return array_key_exists($key, $data) ? $data[$key] : $default;
        }

        if (is_object($data)) {
            if (method_exists($data, 'getAttribute')) {
                try {
                    $value = $data->getAttribute($key);
                    if ($value !== null) {
                        return $value;
                    }
                } catch (\Throwable $e) {
                    // fallback to direct access below
                }
            }

            return $data->{$key} ?? $default;
        }

        return $default;
    }

    private function dataRefundListFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'patient_name', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ['fieldName' => 'paid_at_display', 'class' => 'text-center whitespace-nowrap px-4 py-2'],
            ['fieldName' => 'return_amt_display', 'class' => 'text-center'],
            ['fieldName' => 'created_at_display', 'class' => 'text-center whitespace-nowrap px-4 py-2'],
        ];
    }

    private function getRefundListTableHeaders()
    {
        return [
            'Sl/No',
            'Bill Number',
            'Patient',
            'Total',
            'Paid Amount',
            'Payment Status',
            'Paid Date & Time',
            'Refund Amount',
            'Created Date & Time',
            'Action',
        ];
    }

    private function datapendingListFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'case_number', 'class' => 'text-center'],
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'due_amount_display', 'class' => 'text-center'],
            ['fieldName' => 'delivery_date', 'class' => 'text-center'],
            ['fieldName' => 'created_at_display', 'class' => 'text-center'],
            ['fieldName' => 'created_by', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
        ];
    }
    private function getpendingListTableHeaders()
    {
        return [
            'Sl/No',
            'Bill Number',
            'Case Number',
            'Patient',
            'Total',
            'Paid Amount',
            'Due Amount',
            'Delivery Date',
            'Created Date & Time',
            'Created By',
            'Payment Status',
            'Action',
        ];
    }

    private function handlePatientData($data, $billing = null)
    {
        $patientId = $billing ? $billing->patient_id : null;
        $processedData = $data;

        if ($data['is_new_patient'] && !empty($data['patient_name'])) {
            $patient = Patient::create([
                'name' => $data['patient_name'],
                'phone' => $data['patient_phone'],
                'gender' => $data['patient_gender'],
                'dob' => $data['dob'] ?? null,
                'age' => $data['patient_age'] ?? null,
            ]);

            $patientId = $patient->id;
            $processedData['patient_mobile'] = $data['patient_phone'];
            $processedData['gender'] = $data['patient_gender'];
        } elseif (!empty($data['patient_id']) && (!$billing || $data['patient_id'] != $billing->patient_id)) {
            $patientId = $data['patient_id'];

            $patient = Patient::find($patientId);
            if ($patient) {
                $updateData = [];

                if (isset($data['patient_name']) && $data['patient_name'] != $patient->name) {
                    $updateData['name'] = $data['patient_name'];
                }
                if (isset($data['patient_phone']) && $data['patient_phone'] != $patient->phone) {
                    $updateData['phone'] = $data['patient_phone'];
                    $processedData['patient_mobile'] = $data['patient_phone'];
                }
                if (isset($data['patient_mobile']) && $data['patient_mobile'] != $patient->phone) {
                    $updateData['phone'] = $data['patient_mobile'];
                    $processedData['patient_mobile'] = $data['patient_mobile'];
                }
                if (isset($data['patient_gender']) && $data['patient_gender'] != $patient->gender) {
                    $updateData['gender'] = $data['patient_gender'];
                    $processedData['gender'] = $data['patient_gender'];
                }
                if (isset($data['gender']) && $data['gender'] != $patient->gender) {
                    $updateData['gender'] = $data['gender'];
                    $processedData['gender'] = $data['gender'];
                }
                if (isset($data['dob']) && $data['dob'] != $patient->dob) {
                    $updateData['dob'] = $data['dob'];
                }
                if (isset($data['patient_age']) && $data['patient_age'] != $patient->age) {
                    $updateData['age'] = $data['patient_age'];
                }

                if (!empty($updateData)) {
                    $patient->update($updateData);
                }
            }
        } elseif (!empty($data['patient_id']) && $billing && $data['patient_id'] == $billing->patient_id) {
            $patientId = $data['patient_id'];

            $patient = Patient::find($patientId);
            if ($patient) {
                $updateData = [];

                if (isset($data['patient_name']) && $data['patient_name'] != $patient->name) {
                    $updateData['name'] = $data['patient_name'];
                }
                if (isset($data['patient_phone']) && $data['patient_phone'] != $patient->phone) {
                    $updateData['phone'] = $data['patient_phone'];
                    $processedData['patient_mobile'] = $data['patient_phone'];
                }
                if (isset($data['patient_mobile']) && $data['patient_mobile'] != $patient->phone) {
                    $updateData['phone'] = $data['patient_mobile'];
                    $processedData['patient_mobile'] = $data['patient_mobile'];
                }
                if (isset($data['patient_gender']) && $data['patient_gender'] != $patient->gender) {
                    $updateData['gender'] = $data['patient_gender'];
                    $processedData['gender'] = $data['patient_gender'];
                }
                if (isset($data['gender']) && $data['gender'] != $patient->gender) {
                    $updateData['gender'] = $data['gender'];
                    $processedData['gender'] = $data['gender'];
                }
                if (isset($data['dob']) && $data['dob'] != $patient->dob) {
                    $updateData['dob'] = $data['dob'];
                }
                if (isset($data['patient_age']) && $data['patient_age'] != $patient->age) {
                    $updateData['age'] = $data['patient_age'];
                }

                if (!empty($updateData)) {
                    $patient->update($updateData);
                }
            }
        } elseif (empty($data['patient_id']) && !$data['is_new_patient']) {
            $patient = Patient::create([
                'name' => $data['patient_name'] ?? 'Walk-in Patient',
                'phone' => $data['patient_mobile'] ?? 'N/A',
                'gender' => $data['gender'] ?? 'Others',
                'dob' => $data['dob'] ?? null,
                'age' => $data['patient_age'] ?? null,
            ]);

            $patientId = $patient->id;
            $processedData['patient_mobile'] = $data['patient_mobile'] ?? 'N/A';
            $processedData['gender'] = $data['gender'] ?? 'Others';
        }

        return [
            'patient_id' => $patientId,
            'processed_data' => $processedData
        ];
    }

    private function hasOpenCounterSessionForAdmin(int $adminId, string $adminName = ''): bool
    {
        return app(\App\Services\CashCounterService::class)->findOpenSessionForAdmin($adminId, $adminName) !== null;
    }
}
