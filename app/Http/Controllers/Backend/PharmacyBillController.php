<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PharmacyBillRequest;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\MedicineCategory;
use App\Models\MedicineInventory;
use App\Models\Patient;
use App\Models\PharmacyBill;
use App\Models\Prescription;
use App\Models\IpdPatient;
use App\Models\IpdPrescription;
use App\Models\Payment;
use App\Models\DueCollection;
use App\Models\InvoiceDesign;
use App\Services\BillingService;
use App\Services\PatientService;
use App\Services\PharmacyBillService;
use App\Services\AdminService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class PharmacyBillController extends Controller
{
    use SystemTrait;

    protected $pharmacyBillService, $patientService, $billingService, $adminService;

    public function __construct(
        PharmacyBillService $pharmacyBillService,
        PatientService $patientService,
        BillingService $billingService,
        AdminService $adminService
    ) {
        $this->pharmacyBillService = $pharmacyBillService;
        $this->patientService = $patientService;
        $this->billingService = $billingService;
        $this->adminService = $adminService;

        $this->middleware('auth:admin');
        $this->middleware('permission:pharmacy-bill-list');
        $this->middleware('permission:pharmacy-bill-invoice');
        $this->middleware('permission:pharmacy-bill-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:pharmacy-bill-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:pharmacy-bill-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/PharmacyBill/Index',
            [
                'pageTitle' => 'Pharmacy Bill List',
                'tableHeaders' => $this->getTableHeaders(),
                'dataFields' => $this->dataFields(),
                'datas' => $this->getDatas(),
                'filters' => [
                    'pharmacy_no' => request()->get('pharmacy_no', ''),
                    'patient_name' => request()->get('patient_name', ''),
                    'date_from' => request()->get('date_from', ''),
                    'date_to' => request()->get('date_to', ''),
                    'payment_status' => request()->get('payment_status', ''),
                    'numOfData' => request()->get('numOfData', 10),
                ],
            ]
        );
    }

    public function export(Request $request, string $format)
    {
        $format = strtolower(trim($format));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            return redirect()->back()->with('errorMessage', 'Unsupported export format.');
        }

        $rows = $this->buildFilteredListQuery($request)
            ->with(['patient', 'billing'])
            ->orderByDesc('id')
            ->limit(50000)
            ->get();
        [$ipdPatientsById, $activeIpdPatientsByPatient] = $this->getIpdPatientsForBills($rows);

        $billingMap = Billing::query()
            ->whereIn('bill_number', $rows->pluck('bill_no')->filter()->values())
            ->orWhereIn('id', $rows->pluck('billing_id')->filter()->values())
            ->get()
            ->keyBy('bill_number');

        $filename = 'pharmacy_bill_list_' . now()->format('Ymd_His');

        if ($format === 'csv') {
            ActivityLogService::logDownload('Pharmacy Bill', $filename . '.csv', 'CSV');

            return response()->streamDownload(function () use ($rows, $billingMap, $ipdPatientsById, $activeIpdPatientsByPatient) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Pharmacy No', 'Bill No', 'Patient', 'Bed No', 'Date', 'Total', 'Paid', 'Due', 'Payment Status']);

                foreach ($rows as $row) {
                    $billing = $row->billing ?: $billingMap->get($row->bill_no);
                    $paid = (float) ($billing?->paid_amt ?? $row->payment_amount ?? 0);
                    $due = (float) ($billing?->due_amount ?? max((float) $row->net_amount - (float) ($row->payment_amount ?? 0), 0));
                    $status = strtolower((string) ($billing?->payment_status ?? ($due <= 0 ? 'paid' : 'due')));
                    $ipdPatient = $row->ipd_patient_id
                        ? $ipdPatientsById->get((int) $row->ipd_patient_id)
                        : $activeIpdPatientsByPatient->get((int) $row->patient_id);
                    if ($row->ipd_patient_id) {
                        $paid = min(
                            max((float) ($row->payment_amount ?? 0) - (float) ($row->return_amount ?? 0), 0),
                            (float) $row->net_amount
                        );
                        $due = 'See IPD';
                        $status = 'Collect in IPD';
                    }

                    fputcsv($handle, [
                        (string) $row->pharmacy_no,
                        (string) $row->bill_no,
                        $this->formatPatientName($row, $ipdPatient),
                        (string) ($ipdPatient?->bed?->name ?? 'N/A'),
                        (string) $row->date,
                        number_format((float) $row->net_amount, 2, '.', ''),
                        number_format($paid, 2, '.', ''),
                        is_numeric($due) ? number_format((float) $due, 2, '.', '') : $due,
                        ucfirst($status),
                    ]);
                }

                fclose($handle);
            }, $filename . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $htmlRows = $rows->map(function ($row) use ($billingMap, $ipdPatientsById, $activeIpdPatientsByPatient) {
            $billing = $row->billing ?: $billingMap->get($row->bill_no);
            $paid = (float) ($billing?->paid_amt ?? $row->payment_amount ?? 0);
            $due = (float) ($billing?->due_amount ?? max((float) $row->net_amount - (float) ($row->payment_amount ?? 0), 0));
            $status = strtolower((string) ($billing?->payment_status ?? ($due <= 0 ? 'paid' : 'due')));
            $ipdPatient = $row->ipd_patient_id
                ? $ipdPatientsById->get((int) $row->ipd_patient_id)
                : $activeIpdPatientsByPatient->get((int) $row->patient_id);
            if ($row->ipd_patient_id) {
                $paid = min(
                    max((float) ($row->payment_amount ?? 0) - (float) ($row->return_amount ?? 0), 0),
                    (float) $row->net_amount
                );
                $due = 'See IPD';
                $status = 'Collect in IPD';
            }

            return [
                'pharmacy_no' => (string) $row->pharmacy_no,
                'bill_no' => (string) $row->bill_no,
                'patient' => $this->formatPatientName($row, $ipdPatient),
                'bed_no' => (string) ($ipdPatient?->bed?->name ?? 'N/A'),
                'date' => (string) $row->date,
                'total' => number_format((float) $row->net_amount, 2),
                'paid' => number_format($paid, 2),
                'due' => is_numeric($due) ? number_format((float) $due, 2) : $due,
                'status' => ucfirst($status),
            ];
        });

        $html = view('backend.exports.pharmacy_bill_list_pdf', [
            'rows' => $htmlRows,
            'generated_at' => now()->format('d-M-Y h:i A'),
        ])->render();

        ActivityLogService::logDownload('Pharmacy Bill', $filename . '.pdf', 'PDF');

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($filename . '.pdf');
    }

    private function buildFilteredListQuery(Request $request)
    {
        $query = PharmacyBill::query();

        if ($request->filled('pharmacy_no')) {
            $searchNo = trim((string) $request->pharmacy_no);
            $query->where(function ($q) use ($searchNo) {
                $q->where('pharmacy_no', 'like', '%' . $searchNo . '%')
                    ->orWhere('bill_no', 'like', '%' . $searchNo . '%');
            });
        }

        if ($request->filled('patient_name')) {
            $searchPatient = trim((string) $request->patient_name);
            $ipdPatientIds = ctype_digit($searchPatient)
                ? IpdPatient::query()
                    ->where('id', (int) $searchPatient)
                    ->where('status', 'Active')
                    ->pluck('patient_id')
                : collect();

            $query->where(function ($query) use ($searchPatient, $ipdPatientIds) {
                if (ctype_digit($searchPatient)) {
                    $query->orWhere('patient_id', (int) $searchPatient)
                        ->orWhere('ipd_patient_id', (int) $searchPatient)
                        ->orWhereIn('patient_id', $ipdPatientIds);
                }

                $query->orWhereHas('patient', function ($q) use ($searchPatient) {
                    if (Schema::hasColumn('patients', 'name')) {
                        $q->where('name', 'like', '%' . $searchPatient . '%');
                    } else {
                        $q->where(function ($nameQuery) use ($searchPatient) {
                            if (Schema::hasColumn('patients', 'first_name')) {
                                $nameQuery->orWhere('first_name', 'like', '%' . $searchPatient . '%');
                            }
                            if (Schema::hasColumn('patients', 'last_name')) {
                                $nameQuery->orWhere('last_name', 'like', '%' . $searchPatient . '%');
                            }
                        });
                    }
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('payment_status')) {
            $status = Str::lower((string) $request->payment_status);
            $query->whereExists(function ($subQuery) use ($status) {
                $subQuery->select(DB::raw(1))
                    ->from('billings')
                    ->where(function ($billingQuery) {
                        $billingQuery->whereColumn('billings.bill_number', 'pharmacybills.bill_no')
                            ->orWhereColumn('billings.id', 'pharmacybills.billing_id');
                    });
                if ($status === 'due') {
                    $subQuery->whereRaw("LOWER(COALESCE(billings.payment_status, '')) IN ('due', 'pending')");
                } else {
                    $subQuery->whereRaw('LOWER(COALESCE(billings.payment_status, ?)) = ?', ['', $status]);
                }
            });
        }

        return $query;
    }

    private function getDatas()
    {
        $query = $this->buildFilteredListQuery(request());

        $datas = $query
            ->with(['patient', 'billing'])
            ->orderByDesc('id')
            ->orderByDesc('date')
            ->paginate(request()->numOfData ?? 10);
        $datas->appends(request()->query());
        [$ipdPatientsById, $activeIpdPatientsByPatient] = $this->getIpdPatientsForBills(collect($datas->items()));

        $formatedDatas = collect($datas->items())->values()->map(function ($data, $index) use ($ipdPatientsById, $activeIpdPatientsByPatient) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->row_id = null;
            $customData->row_type = 'billing';
            $customData->pharmacy_no = $data->pharmacy_no;
            $customData->bill_no = $data->bill_no;
            $ipdPatientId = $data->ipd_patient_id
                ?: $activeIpdPatientsByPatient->get($data->patient_id)?->id;
            $ipdPatient = $ipdPatientId
                ? $ipdPatientsById->get((int) $ipdPatientId)
                : null;
            $customData->patient_name = $this->formatPatientName($data, $ipdPatient, true);
            $customData->net_amount = number_format($data->net_amount, 2);
            $customData->paid_amount = number_format((float) ($data->payment_amount ?? 0), 2);
            $customData->due_amount = number_format(max((float) $data->net_amount - (float) ($data->payment_amount ?? 0), 0), 2);
            $customData->date = $data->date;
            $customData->status = getStatusText('Due');

            $billing = $data->billing ?: Billing::where('bill_number', $data->bill_no)->first();
            if ($billing && !$data->ipd_patient_id) {
                $customData->row_id = $billing->id;
                $customData->paid_amount = number_format((float) ($billing->paid_amt ?? 0), 2);
                $customData->due_amount = number_format((float) ($billing->due_amount ?? 0), 2);
                $paymentStatus = strtolower((string) $billing->payment_status);
                if ($paymentStatus === 'paid') {
                    $customData->status = getStatusText('Paid');
                } elseif ($paymentStatus === 'partial') {
                    $customData->status = getStatusText('Partial');
                } else {
                    $customData->status = getStatusText('Due');
                }
            }
            if ($data->ipd_patient_id) {
                $customData->paid_amount = number_format(
                    min(
                        max((float) ($data->payment_amount ?? 0) - (float) ($data->return_amount ?? 0), 0),
                        (float) $data->net_amount
                    ),
                    2
                );
                $customData->due_amount = 'See IPD';
                $customData->status = 'Collect in IPD';
            }

            $customData->hasLink = true;
            $user = auth('admin')->user();
            $gate = Gate::forUser($user);

            $customData->links = [];

            $hasDueAmount = $billing && !$data->ipd_patient_id && (float) ($billing->due_amount ?? 0) > 0;
            $paymentStatus = strtolower((string) ($billing->payment_status ?? ''));
            $isDueBill = $hasDueAmount && in_array($paymentStatus, ['due', 'partial', 'pending'], true);

            if ($isDueBill && $gate->allows('billing-due-collect')) {
                $customData->links[] = [
                    'linkClass' => 'bg-purple-600 text-white semi-bold',
                    'link' => route('backend.due.collect', ['id' => $billing->id, 'return_to' => request()->fullUrl()]),
                    'linkLabel' => getLinkLabel('Due Collect', null, null),
                    'action_name' => 'due-collect',
                    'action_id' => 'billing|' . $billing->id,
                ];
            }

            if (!$isDueBill && $gate->allows('pharmacy-bill-status') && !$data->ipd_patient_id) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.pharmacybill.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($gate->allows('pharmacy-bill-edit') && !$data->ipd_patient_id) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pharmacybill.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];

                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.pharmacybill.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            if ($gate->allows('pharmacy-bill-invoice')) {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.pharmacybill.invoice', ['id' => $data->id, 'auto_print' => 1]),
                    'linkLabel' => getLinkLabel('Print', null, null),
                    'target' => '_blank'
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function getIpdPatientsForBills($bills): array
    {
        $bills = collect($bills);
        $patientIds = $bills->pluck('patient_id')->filter()->unique()->values();
        $ipdPatientIds = $bills->pluck('ipd_patient_id')->filter()->unique()->values();
        if ($patientIds->isEmpty() && $ipdPatientIds->isEmpty()) {
            return [collect(), collect()];
        }

        $patients = IpdPatient::query()
            ->with('bed:id,name')
            ->where(function ($query) use ($patientIds, $ipdPatientIds) {
                if ($ipdPatientIds->isNotEmpty()) {
                    $query->whereIn('id', $ipdPatientIds);
                }
                if ($patientIds->isNotEmpty()) {
                    $method = $ipdPatientIds->isNotEmpty() ? 'orWhere' : 'where';
                    $query->{$method}(function ($activeQuery) use ($patientIds) {
                        $activeQuery->where('status', 'Active')->whereIn('patient_id', $patientIds);
                    });
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'patient_id', 'bed_id', 'status']);

        return [
            $patients->keyBy('id'),
            $patients->where('status', 'Active')->unique('patient_id')->keyBy('patient_id'),
        ];
    }

    private function formatPatientName(PharmacyBill $bill, ?IpdPatient $ipdPatient, bool $escapeHtml = false): string
    {
        $patientName = trim((string) ($bill->patient?->name ?? ''));
        if ($patientName === '') {
            $patientName = trim((string) (($bill->patient?->first_name ?? '') . ' ' . ($bill->patient?->last_name ?? '')));
        }

        $patientName = trim($patientName) !== '' ? $patientName : 'N/A';
        if ($escapeHtml) {
            $patientName = e($patientName);
        }
        if ($bill->patient_id) {
            $patientName .= ' (Patient #' . $bill->patient_id . ')';
        }
        if ($ipdPatient) {
            $patientName .= ' (IPD #' . $ipdPatient->id . ')';
            if ($ipdPatient->bed?->name) {
                $bedName = $escapeHtml ? e($ipdPatient->bed->name) : $ipdPatient->bed->name;
                $patientName .= ' (Bed: ' . $bedName . ')';
            }
        }

        return $patientName;
    }

    public function invoice($id)
    {
        $pharmacyBill = PharmacyBill::query()
            ->with(['patient', 'ipdPatient.bed'])
            ->findOrFail($id);

        $billing = $pharmacyBill->billing
            ?: Billing::query()->where('bill_number', $pharmacyBill->bill_no)->first();

        if ($billing) {
            return redirect()->route('backend.download.invoice', [
                'id' => $billing->id,
                'pharmacy_bill_id' => $pharmacyBill->id,
                'module' => 'pharmacy',
                'fast_open' => 1,
                'auto_print' => request()->boolean('auto_print') ? 1 : 0,
            ]);
        }

        $products = is_array($pharmacyBill->products)
            ? $pharmacyBill->products
            : json_decode((string) $pharmacyBill->products, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($products)) {
            throw new \UnexpectedValueException('Pharmacy invoice products must be a list.');
        }

        $previousDue = 0.0;
        if ($pharmacyBill->ipd_patient_id) {
            $previousBills = PharmacyBill::query()
                ->where('ipd_patient_id', $pharmacyBill->ipd_patient_id)
                ->where('id', '<', $pharmacyBill->id)
                ->orderBy('id')
                ->get(['id', 'net_amount', 'payment_amount', 'return_amount']);

            $previousBillIds = $previousBills->pluck('id');
            $paymentsByBill = $previousBillIds->isEmpty()
                ? collect()
                : Payment::query()
                    ->whereIn('pharmacy_bill_id', $previousBillIds)
                    ->where(function ($query) {
                        $query->whereNull('status')->orWhere('status', 'Active');
                    })
                    ->when(Schema::hasColumn('payments', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                    ->get(['pharmacy_bill_id', 'amount'])
                    ->groupBy('pharmacy_bill_id');
            $collectionsByBill = $previousBillIds->isEmpty()
                ? collect()
                : DueCollection::query()
                    ->whereIn('pharmacy_bill_id', $previousBillIds)
                    ->where('created_at', '<=', $pharmacyBill->created_at ?? now())
                    ->when(Schema::hasColumn('due_collections', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                    ->get(['pharmacy_bill_id', 'collected_amount'])
                    ->groupBy('pharmacy_bill_id');

            $previousTotal = 0.0;
            $previousPaid = 0.0;
            foreach ($previousBills as $previousBill) {
                $payments = $paymentsByBill->get($previousBill->id, collect());
                $paidFromPayments = $payments->isNotEmpty()
                    ? (float) $payments->sum('amount')
                    : max((float) $previousBill->payment_amount - (float) $previousBill->return_amount, 0);
                $paidFromCollections = (float) $collectionsByBill
                    ->get($previousBill->id, collect())
                    ->sum('collected_amount');

                $previousTotal += (float) $previousBill->net_amount;
                $previousPaid += min(
                    $paidFromPayments + $paidFromCollections,
                    (float) $previousBill->net_amount
                );
            }

            $previousDue = round(max($previousTotal - $previousPaid, 0), 2);
        }

        $currentPayments = Payment::query()
            ->where('pharmacy_bill_id', $pharmacyBill->id)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'Active');
            })
            ->when(Schema::hasColumn('payments', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->get(['amount']);
        $currentPaid = $currentPayments->isNotEmpty()
            ? (float) $currentPayments->sum('amount')
            : max((float) $pharmacyBill->payment_amount - (float) $pharmacyBill->return_amount, 0);
        $currentPaid = min($currentPaid, (float) $pharmacyBill->net_amount);
        $currentDue = round(max(
            $previousDue + (float) $pharmacyBill->net_amount - $currentPaid,
            0
        ), 2);

        $invoiceDesign = InvoiceDesign::resolveForModule('pharmacy');
        $webSetting = get_cached_web_setting();

        return view('backend.pharmacybill.invoice', [
            'pharmacyBill' => $pharmacyBill,
            'products' => $products,
            'patientName' => $this->formatPatientName($pharmacyBill, $pharmacyBill->ipdPatient),
            'previousDue' => $previousDue,
            'currentPaid' => $currentPaid,
            'currentDue' => $currentDue,
            'invoiceDesign' => $invoiceDesign,
            'headerHeight' => (int) ($invoiceDesign?->header_height ?? 115),
            'footerHeight' => (int) ($invoiceDesign?->footer_height ?? 70),
            'pharmacyTaxNumber' => trim((string) ($webSetting?->pharmacy_tax_number ?? '')),
            'autoPrint' => request()->boolean('auto_print'),
        ]);
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'pharmacy_no', 'class' => 'text-center'],
            ['fieldName' => 'bill_no', 'class' => 'text-center'],
            ['fieldName' => 'patient_name', 'class' => 'text-center'],
            ['fieldName' => 'date', 'class' => 'text-center'],
            ['fieldName' => 'net_amount', 'class' => 'text-center'],
            ['fieldName' => 'paid_amount', 'class' => 'text-center'],
            ['fieldName' => 'due_amount', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Pharmacy No',
            'Bill No',
            'Patient Name',
            'Date',
            'Net Amount',
            'Paid Amount',
            'Due Amount',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return redirect()->route('backend.billing.view', array_merge(
            request()->only(['prescription_id', 'opd_patient_id', 'ipd_patient_id', 'ipd_prescription_id']),
            ['pharmacy_mode' => 1]
        ));
    }

    public function searchMedicines(Request $request)
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
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
            ->when($validated['category'] ?? null, function ($query, $category) {
                $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', $category));
            })
            ->with('category:id,name')
            ->select('id', 'medicine_name', 'medicine_unit_selling_price as sale_price', 'medicine_quantity', 'medicine_category_id', 'expiry_date');
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

            $results = $results->concat(
                $prefixQuery->orderBy('medicine_name')->limit(30 - $results->count())->get()
            )->values();
        }

        return response()->json($results);
    }

    public function store(PharmacyBillRequest $request)
    {
        $data = $request->validated();
        if (!empty($data['ipd_patient_id'])) {
            $ipdPatient = IpdPatient::with(['patient', 'billing'])->findOrFail($data['ipd_patient_id']);
            if (!empty($data['patient_id']) && (int) $data['patient_id'] !== (int) $ipdPatient->patient_id) {
                return redirect()->back()->withErrors([
                    'patient_id' => 'Selected patient does not match the IPD admission.',
                ])->withInput();
            }
            if (!empty($data['ipd_prescription_id'])) {
                $prescriptionBelongsToAdmission = IpdPrescription::query()
                    ->whereKey($data['ipd_prescription_id'])
                    ->where('ipd_patient_id', $ipdPatient->id)
                    ->exists();
                if (!$prescriptionBelongsToAdmission) {
                    return redirect()->back()->withErrors([
                        'ipd_prescription_id' => 'Prescription does not belong to this IPD admission.',
                    ])->withInput();
                }
            }
            $data['patient_id'] = $ipdPatient->patient_id;
            $data['doctor_id'] = $data['doctor_id'] ?? $ipdPatient->consultant_doctor_id;
            $data['case_id'] = $ipdPatient->billing?->case_number
                ?? ('IPD-' . str_pad((string) $ipdPatient->id, 6, '0', STR_PAD_LEFT));
        }

        DB::beginTransaction();
        try {
            $data['products'] = $this->attachPurchaseCostSnapshots($data['products']);
            $grossPayment = (float) ($data['payment_amount'] ?? 0);
            $netAmount = (float) ($data['net_amount'] ?? 0);
            $maxReturnAmount = max($grossPayment - $netAmount, 0);
            $returnAmount = min(max((float) ($data['return_amount'] ?? 0), 0), $maxReturnAmount);
            $effectivePaidAmount = min(max($grossPayment - $returnAmount, 0), $netAmount);
            $data['return_amount'] = $returnAmount;
            $data['effective_paid_amount'] = $effectivePaidAmount;

            $pharmacyBillUpdateData = $data;
            unset($pharmacyBillUpdateData['effective_paid_amount']);

            $pharmacyBillData = [
                'pharmacy_no' => $data['pharmacy_no'],
                'bill_no' => $data['bill_no'],
                'case_id' => $data['case_id'],
                'date' => $data['date'],
                'patient_id' => $data['patient_id'],
                'ipd_patient_id' => $data['ipd_patient_id'] ?? null,
                'ipd_prescription_id' => $data['ipd_prescription_id'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
                'doctor_name' => $data['doctor_id'] ? ($this->adminService->find($data['doctor_id'])?->name) : null,
                'products' => json_encode($data['products']),
                'subtotal' => $data['subtotal'],
                'discount_percentage' => $data['discount_percentage'],
                'discount_amount' => $data['discount_amount'],
                'vat_percentage' => $data['vat_percentage'],
                'vat_amount' => $data['vat_amount'],
                'extra_discount' => $data['extra_discount'],
                'net_amount' => $data['net_amount'],
                'payment_mode' => $data['payment_mode'],
                'payment_amount' => $grossPayment,
                'return_amount' => $returnAmount,
                'note' => $data['note'],
                'created_by' => auth('admin')->user()->id,
            ];

            $pharmacyBill = $this->pharmacyBillService->create($pharmacyBillData);

            if ($pharmacyBill) {
                $this->assertSufficientStock($data['products']);

                foreach ($data['products'] as $product) {
                    $medicine = MedicineInventory::find($product['productId']);
                    if ($medicine && $medicine->medicine_quantity >= $product['quantity']) {
                        $beforeQty = (float) $medicine->medicine_quantity;
                        $deltaQty = (float) $product['quantity'];
                        $medicine->decrement('medicine_quantity', $product['quantity']);
                        $medicine->refresh();
                        $afterQty = (float) $medicine->medicine_quantity;
                        $this->logStockMovement('ISSUE', $pharmacyBill, $product, $beforeQty, -$deltaQty, $afterQty);
                    }
                }

                $billing = $this->createBillingRecord($pharmacyBill, $data);
                if (!$billing || !$billing->id) {
                    throw new Exception('Billing record could not be created.');
                }
                $pharmacyBill->billing_id = $billing->id;
                $pharmacyBill->save();

                if ($effectivePaidAmount > 0) {
                    $paymentData = [
                        'billing_id' => $billing->id,
                        'pharmacy_bill_id' => $pharmacyBill->id,
                        'amount' => $effectivePaidAmount,
                        'payment_method' => $data['payment_mode'],
                        'received_by' => auth('admin')->user()->id,
                        'payment_status' => $this->determinePaymentStatus($effectivePaidAmount, $netAmount),
                    ];
                    if (!empty($data['ipd_patient_id'])) {
                        $paymentData['ipd_patient_id'] = $data['ipd_patient_id'];
                        $paymentData['status'] = 'Active';
                    }
                    Payment::create($paymentData);
                }
                if (!empty($data['ipd_patient_id'])) {
                    $this->refreshIpdBilling($billing);
                }

                $message = 'Pharmacy Bill created successfully';
                $this->storeAdminWorkLog($pharmacyBill->id, 'pharmacybills', $message);
                ActivityLogService::logCreate(
                    'Pharmacy Bill',
                    $pharmacyBill->id,
                    $pharmacyBill->bill_no ?? ($pharmacyBill->pharmacy_no ?? ('PharmacyBill#' . $pharmacyBill->id)),
                    [
                        'pharmacy_no' => $pharmacyBill->pharmacy_no,
                        'bill_no' => $pharmacyBill->bill_no,
                        'case_id' => $pharmacyBill->case_id,
                        'patient_id' => $pharmacyBill->patient_id,
                        'doctor_id' => $pharmacyBill->doctor_id,
                        'net_amount' => $pharmacyBill->net_amount,
                        'payment_amount' => $pharmacyBill->payment_amount,
                    ]
                );
                DB::commit();

                // If caller supplied a print_token, map it to the created billing id for instant preview
                try {
                    $printToken = $request->input('print_token');
                    if (!empty($printToken) && is_string($printToken)) {
                        Cache::put('print_token_' . $printToken, $billing->id, now()->addMinutes(5));
                        Cache::put('print_token_pharmacy_bill_' . $printToken, $pharmacyBill->id, now()->addMinutes(5));
                    }
                } catch (\Throwable $e) {
                    // ignore caching errors
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing->id)
                    ->with('pharmacyBillId', $pharmacyBill->id);
            } else {
                DB::rollBack();
                $message = "Failed To create Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'store', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function createBillingRecord($pharmacyBill, $data)
    {
        if (!empty($data['ipd_patient_id'])) {
            return $this->appendToIpdBilling($pharmacyBill, $data);
        }

        $lastBilling = $this->billingService->getLastBilling();
        $billNumber = $this->generateBillNumber($lastBilling);
        $invoiceNumber = $this->generateInvoiceNumber($lastBilling);
        $caseNumber = $data['case_id'] ?: $this->generateCaseNumber($lastBilling);

        $patient = $this->patientService->find($data['patient_id']);

        $netAmount = (float) ($data['net_amount'] ?? 0);
        $grossPayment = (float) ($data['payment_amount'] ?? 0);
        $effectivePaidAmount = min(max((float) ($data['effective_paid_amount'] ?? $grossPayment), 0), $netAmount);

        $billingData = [
            'invoice_number' => $invoiceNumber,
            'bill_number' => $billNumber,
            'case_number' => $caseNumber,
            'patient_id' => $data['patient_id'],
            'patient_mobile' => $patient?->phone ?? '',
            'gender' => $patient?->gender ?? 'Male',
            'doctor_id' => $data['doctor_id'] ?? null,
            'doctor_name' => $pharmacyBill->doctor_name,
            'card_type' => $data['payment_mode'],
            'pay_mode' => $data['payment_mode'],
            'total' => $netAmount,
            'discount' => $data['discount_amount'],
            'discount_type' => 'flat',
            'payable_amount' => $netAmount,
            'paid_amt' => $effectivePaidAmount,
            'invoice_amount' => $effectivePaidAmount,
            'receiving_amt' => $grossPayment,
            'due_amount' => max($netAmount - $effectivePaidAmount, 0),
            'remarks' => $data['note'],
            'payment_status' => $this->determinePaymentStatus($effectivePaidAmount, $netAmount),
            'created_by' => auth('admin')->user()->id,
        ];

        $billing = $this->billingService->create($billingData);

        foreach ($data['products'] as $product) {
            BillItem::create([
                'billing_id' => $billing->id,
                'item_id' => $product['productId'],
                'item_name' => $product['productName'],
                'room_no' => $product['room_no'] ?? $product['roomNo'] ?? null,
                'category' => 'Medicine',
                'unit_price' => $product['rate'],
                'quantity' => $product['quantity'],
                'total_amount' => $product['amount'],
                'net_amount' => $product['amount'],
                'commissionable' => null,
                'commission_rate' => null,
            ]);
        }

        return $billing;
    }

    private function appendToIpdBilling(PharmacyBill $pharmacyBill, array $data): Billing
    {
        $ipdPatient = IpdPatient::query()->with(['patient', 'doctor'])->lockForUpdate()->findOrFail($data['ipd_patient_id']);
        if ((int) $ipdPatient->patient_id !== (int) $data['patient_id']) {
            throw new Exception('The selected patient does not match this IPD admission.');
        }

        $billing = $ipdPatient->billing_id
            ? Billing::query()->lockForUpdate()->find($ipdPatient->billing_id)
            : null;

        if (!$billing) {
            $patient = $ipdPatient->patient;
            $doctor = $ipdPatient->doctor;
            $caseNumber = 'IPD-' . str_pad((string) $ipdPatient->id, 6, '0', STR_PAD_LEFT);
            if (Billing::withTrashed()->where('case_number', $caseNumber)->exists()) {
                $caseNumber .= '-' . now()->format('His');
            }

            $billing = Billing::query()->create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'bill_number' => $this->generateBillNumber(),
                'case_number' => $caseNumber,
                'patient_id' => $patient?->id,
                'patient_mobile' => (string) ($patient?->mobile ?? $patient?->phone ?? ''),
                'gender' => in_array((string) ($patient?->gender ?? ''), ['Male', 'Female', 'Others'], true)
                    ? (string) $patient->gender
                    : 'Others',
                'doctor_id' => $doctor?->id,
                'doctor_type' => 'admin',
                'doctor_name' => $doctor?->name,
                'card_type' => $data['payment_mode'],
                'pay_mode' => $data['payment_mode'],
                'card_number' => null,
                'total' => 0,
                'discount' => 0,
                'extra_flat_discount' => 0,
                'discount_type' => 'flat',
                'payable_amount' => 0,
                'paid_amt' => 0,
                'change_amt' => 0,
                'receiving_amt' => 0,
                'due_amount' => 0,
                'delivery_date' => now(),
                'delivery_time' => null,
                'remarks' => 'IPD Running Bill | IPD#' . $ipdPatient->id,
                'commission_total' => 0,
                'physyst_amt' => 0,
                'commission_slider' => 0,
                'created_by' => auth('admin')->id(),
                'payment_status' => 'Pending',
                'status' => 'Active',
            ]);

            $ipdPatient->billing_id = $billing->id;
            $ipdPatient->save();
        }

        foreach ($data['products'] as $product) {
            BillItem::query()->create([
                'billing_id' => $billing->id,
                'pharmacy_bill_id' => $pharmacyBill->id,
                'item_id' => $product['productId'],
                'item_name' => $product['productName'],
                'category' => 'Medicine',
                'unit_price' => $product['rate'],
                'quantity' => $product['quantity'],
                'total_amount' => $product['amount'],
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $product['amount'],
                'status' => 'Active',
            ]);
        }

        Payment::query()
            ->where('ipd_patient_id', $ipdPatient->id)
            ->whereNull('billing_id')
            ->update(['billing_id' => $billing->id]);

        return $billing;
    }

    private function refreshIpdBilling(Billing $billing): void
    {
        $total = (float) $billing->billItems()->where('status', 'Active')->sum('net_amount');
        $payments = Payment::query()
            ->where('billing_id', $billing->id)
            ->where('status', 'Active');
        if (Schema::hasColumn('payments', 'deleted_at')) {
            $payments->whereNull('deleted_at');
        }
        $paid = (float) $payments->sum('amount');

        $dueCollections = DueCollection::query()
            ->where('billing_id', $billing->id)
            ->where(function ($query) {
                $query->whereNull('note')
                    ->orWhere('note', 'not like', '%Collected via IPD payment%');
            });
        if (Schema::hasColumn('due_collections', 'deleted_at')) {
            $dueCollections->whereNull('deleted_at');
        }
        $paid += (float) $dueCollections->sum('collected_amount');

        $discount = max(0, (float) ($billing->discount ?? 0));
        $discountAmount = ($billing->discount_type ?? 'flat') === 'percentage'
            ? $total * ($discount / 100)
            : $discount;
        $payable = max(0, round($total - $discountAmount - max(0, (float) ($billing->extra_flat_discount ?? 0)), 2));
        $due = max($payable - $paid, 0);

        $billing->fill([
            'total' => $total,
            'payable_amount' => $payable,
            'paid_amt' => $paid,
            'receiving_amt' => $paid,
            'due_amount' => $due,
            'change_amt' => max($paid - $payable, 0),
            'payment_status' => $due <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending'),
            'updated_by' => auth('admin')->id(),
        ])->save();
    }

    private function determinePaymentStatus($paidAmount, $totalAmount)
    {
        $paidAmount = floatval($paidAmount);
        $totalAmount = floatval($totalAmount);

        if ($paidAmount >= $totalAmount) {
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    public function edit($id)
    {
        $pharmacyBill = $this->pharmacyBillService->find($id);
        if (!$pharmacyBill) {
            return redirect()->route('backend.pharmacybill.index')
                ->with('errorMessage', 'Pharmacy bill not found.');
        }

        $billing = $pharmacyBill->billing
            ?: Billing::query()->where('bill_number', $pharmacyBill->bill_no)->first();

        if (!$billing) {
            return redirect()->route('backend.pharmacybill.index')
                ->with('errorMessage', 'The related billing record could not be found.');
        }

        return redirect()->route('backend.billing.edit', [
            'billing' => $billing->id,
            'pharmacy_mode' => 1,
        ]);
    }


    public function update(PharmacyBillRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['products'] = $this->attachPurchaseCostSnapshots($data['products']);
            $oldPharmacyBill = $this->pharmacyBillService->find($id);
            if ($oldPharmacyBill?->ipd_patient_id || !empty($data['ipd_patient_id'])) {
                DB::rollBack();
                return redirect()->back()->with('errorMessage', 'IPD pharmacy dispense records cannot be edited; use the due collection workflow for payments.');
            }

            $grossPayment = (float) ($data['payment_amount'] ?? 0);
            $netAmount = (float) ($data['net_amount'] ?? 0);
            $maxReturnAmount = max($grossPayment - $netAmount, 0);
            $returnAmount = min(max((float) ($data['return_amount'] ?? 0), 0), $maxReturnAmount);
            $effectivePaidAmount = min(max($grossPayment - $returnAmount, 0), $netAmount);
            $data['return_amount'] = $returnAmount;
            $data['effective_paid_amount'] = $effectivePaidAmount;

            if ($oldPharmacyBill && $oldPharmacyBill->products) {
                $oldProducts = is_string($oldPharmacyBill->products)
                    ? json_decode($oldPharmacyBill->products, true)
                    : $oldPharmacyBill->products;

                if (is_array($oldProducts)) {
                    foreach ($oldProducts as $oldProduct) {
                        $medicine = MedicineInventory::find($oldProduct['productId']);
                        if ($medicine) {
                            $beforeQty = (float) $medicine->medicine_quantity;
                            $deltaQty = (float) $oldProduct['quantity'];
                            $medicine->increment('medicine_quantity', $oldProduct['quantity']);
                            $medicine->refresh();
                            $afterQty = (float) $medicine->medicine_quantity;
                            $this->logStockMovement('RESTORE', $oldPharmacyBill, $oldProduct, $beforeQty, $deltaQty, $afterQty);
                        }
                    }
                }
            }

            $pharmacyBillUpdateData = $data;
            unset($pharmacyBillUpdateData['effective_paid_amount']);
            $data['updated_by'] = auth('admin')->user()->id;
            $pharmacyBillUpdateData['updated_by'] = $data['updated_by'];
            $dataInfo = $this->pharmacyBillService->update($pharmacyBillUpdateData, $id);

            if ($dataInfo->save()) {
                $this->assertSufficientStock($data['products']);

                foreach ($data['products'] as $product) {
                    $medicine = MedicineInventory::find($product['productId']);
                    if ($medicine && $medicine->medicine_quantity >= $product['quantity']) {
                        $beforeQty = (float) $medicine->medicine_quantity;
                        $deltaQty = (float) $product['quantity'];
                        $medicine->decrement('medicine_quantity', $product['quantity']);
                        $medicine->refresh();
                        $afterQty = (float) $medicine->medicine_quantity;
                        $this->logStockMovement('ISSUE', $dataInfo, $product, $beforeQty, -$deltaQty, $afterQty);
                    }
                }

                $billing = $this->updateBillingRecord($dataInfo, $data);

                $message = 'Pharmacy Bill updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacybills', $message);
                ActivityLogService::logUpdate(
                    'Pharmacy Bill',
                    $dataInfo->id,
                    $dataInfo->bill_no ?? ($dataInfo->pharmacy_no ?? ('PharmacyBill#' . $dataInfo->id)),
                    [
                        'pharmacy_no' => $dataInfo->pharmacy_no,
                        'bill_no' => $dataInfo->bill_no,
                        'case_id' => $dataInfo->case_id,
                        'patient_id' => $dataInfo->patient_id,
                        'doctor_id' => $dataInfo->doctor_id,
                        'net_amount' => $dataInfo->net_amount,
                        'payment_amount' => $dataInfo->payment_amount,
                    ],
                    [
                        'pharmacy_no' => $oldPharmacyBill?->pharmacy_no,
                        'bill_no' => $oldPharmacyBill?->bill_no,
                        'case_id' => $oldPharmacyBill?->case_id,
                        'patient_id' => $oldPharmacyBill?->patient_id,
                        'doctor_id' => $oldPharmacyBill?->doctor_id,
                        'net_amount' => $oldPharmacyBill?->net_amount,
                        'payment_amount' => $oldPharmacyBill?->payment_amount,
                    ]
                );
                DB::commit();

                // Map print_token -> billing id for preview tab if present
                try {
                    $printToken = $request->input('print_token');
                    if (!empty($printToken) && is_string($printToken)) {
                        Cache::put('print_token_' . $printToken, $billing?->id, now()->addMinutes(5));
                        Cache::put('print_token_pharmacy_bill_' . $printToken, $dataInfo->id, now()->addMinutes(5));
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing?->id)
                    ->with('pharmacyBillId', $dataInfo->id);
            } else {
                DB::rollBack();
                $message = "Failed To update Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'update', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function assertSufficientStock(array $products): void
    {
        $errors = [];

        foreach ($products as $product) {
            $medicineId = $product['productId'] ?? null;
            $requiredQty = (float) ($product['quantity'] ?? 0);
            $medicineName = (string) ($product['productName'] ?? 'Unknown medicine');

            if (!$medicineId || $requiredQty <= 0) {
                continue;
            }

            $medicine = MedicineInventory::query()->find($medicineId);
            $availableQty = (float) ($medicine?->medicine_quantity ?? 0);

            if (!$medicine || $availableQty < $requiredQty) {
                $errors[] = $medicineName . ' stock is insufficient. Available: ' . $availableQty . ', Required: ' . $requiredQty;
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode(' | ', $errors));
        }
    }

    private function attachPurchaseCostSnapshots(array $products): array
    {
        foreach ($products as &$product) {
            $medicineId = $product['productId'] ?? null;
            $medicine = $medicineId ? MedicineInventory::query()->find($medicineId) : null;
            if (!$medicine) {
                throw new Exception('Selected medicine is no longer available in inventory.');
            }

            $unitPurchasePrice = (float) $medicine->medicine_unit_purchase_price;
            if ($unitPurchasePrice <= 0) {
                $unitPurchasePrice = (float) DB::table('purchase_items')
                    ->join('medicine_purchases', 'medicine_purchases.id', '=', 'purchase_items.medicine_purchase_id')
                    ->where('medicine_purchases.status', '!=', 'cancelled')
                    ->where('purchase_items.received_quantity', '>', 0)
                    ->whereRaw('LOWER(TRIM(purchase_items.medicine_name)) = ?', [mb_strtolower(trim($medicine->medicine_name))])
                    ->selectRaw('SUM(GREATEST(0, purchase_items.received_quantity * purchase_items.unit_purchase_price - purchase_items.discount * purchase_items.received_quantity / GREATEST(purchase_items.quantity, 1))) / NULLIF(SUM(purchase_items.received_quantity), 0) as average_cost')
                    ->value('average_cost');
            }
            $product['unit_purchase_price'] = max(0, $unitPurchasePrice);
        }
        unset($product);

        return $products;
    }

    private function updateBillingRecord($pharmacyBill, $data)
    {
        // Find corresponding billing record by case number or bill number
        $billing = Billing::where('case_number', $data['case_id'])
            ->orWhere('bill_number', $data['bill_no'])
            ->first();

        if ($billing) {
            $patient = $this->patientService->find($data['patient_id']);

            $netAmount = (float) ($data['net_amount'] ?? 0);
            $grossPayment = (float) ($data['payment_amount'] ?? 0);
            $effectivePaidAmount = min(max((float) ($data['effective_paid_amount'] ?? $grossPayment), 0), $netAmount);

            $billing->update([
                'patient_id' => $data['patient_id'],
                'patient_mobile' => $patient?->phone ?? '',
                'gender' => $patient?->gender ?? 'Male',
                'doctor_id' => $data['doctor_id'] ?? null,
                'doctor_name' => $pharmacyBill->doctor_name,
                'total' => $netAmount,
                'discount' => $data['discount_amount'],
                'payable_amount' => $netAmount,
                'paid_amt' => $effectivePaidAmount,
                'invoice_amount' => $effectivePaidAmount,
                'receiving_amt' => $grossPayment,
                'due_amount' => max($netAmount - $effectivePaidAmount, 0),
                'remarks' => $data['note'],
                'payment_status' => $this->determinePaymentStatus($effectivePaidAmount, $netAmount),
                'updated_by' => auth('admin')->user()->id,
            ]);

            // Update bill items
            BillItem::where('billing_id', $billing->id)->delete();
            foreach ($data['products'] as $product) {
                BillItem::create([
                    'billing_id' => $billing->id,
                    'item_id' => $product['productId'],
                    'item_name' => $product['productName'],
                    'room_no' => $product['room_no'] ?? $product['roomNo'] ?? null,
                    'category' => 'Medicine',
                    'unit_price' => $product['rate'],
                    'quantity' => $product['quantity'],
                    'total_amount' => $product['amount'],
                    'net_amount' => $product['amount'],
                    'commissionable' => null,
                    'commission_rate' => null,
                ]);
            }

            return $billing;
        }

        return null;
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $pharmacyBill = $this->pharmacyBillService->find($id);
            if ($pharmacyBill?->ipd_patient_id) {
                DB::rollBack();
                return redirect()->back()->with('errorMessage', 'IPD pharmacy dispense records cannot be deleted because they are part of the running bill history.');
            }

            // Restore medicine quantities before deletion
            if ($pharmacyBill && $pharmacyBill->products) {
                $products = is_string($pharmacyBill->products)
                    ? json_decode($pharmacyBill->products, true)
                    : $pharmacyBill->products;

                if (is_array($products)) {
                    foreach ($products as $product) {
                        $medicine = MedicineInventory::find($product['productId']);
                        if ($medicine) {
                            $beforeQty = (float) $medicine->medicine_quantity;
                            $deltaQty = (float) $product['quantity'];
                            $medicine->increment('medicine_quantity', $product['quantity']);
                            $medicine->refresh();
                            $afterQty = (float) $medicine->medicine_quantity;
                            $this->logStockMovement('RESTORE', $pharmacyBill, $product, $beforeQty, $deltaQty, $afterQty);
                        }
                    }
                }
            }

            if ($this->pharmacyBillService->delete($id)) {
                // Delete corresponding billing record
                $billing = Billing::where('case_number', $pharmacyBill->case_id)->first();
                if ($billing) {
                    BillItem::where('billing_id', $billing->id)->delete();
                    $billing->delete();
                }

                $message = 'Pharmacy Bill deleted successfully';
                $this->storeAdminWorkLog($id, 'pharmacybills', $message);
                ActivityLogService::logDelete(
                    'Pharmacy Bill',
                    $id,
                    $pharmacyBill?->bill_no ?? ($pharmacyBill?->pharmacy_no ?? ('PharmacyBill#' . $id)),
                    [
                        'pharmacy_no' => $pharmacyBill?->pharmacy_no,
                        'bill_no' => $pharmacyBill?->bill_no,
                        'case_id' => $pharmacyBill?->case_id,
                        'patient_id' => $pharmacyBill?->patient_id,
                        'doctor_id' => $pharmacyBill?->doctor_id,
                        'net_amount' => $pharmacyBill?->net_amount,
                        'payment_amount' => $pharmacyBill?->payment_amount,
                    ]
                );
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To Delete Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'destroy', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function logStockMovement(string $action, $bill, array $product, float $beforeQty, float $deltaQty, float $afterQty): void
    {
        $medicineId = $product['productId'] ?? null;
        $medicine = $medicineId ? MedicineInventory::find($medicineId) : null;

        $billNumber = is_array($bill)
            ? ($bill['bill_no'] ?? 'N/A')
            : ($bill->bill_no ?? 'N/A');

        $medicineName = $medicine->medicine_name ?? ($product['name'] ?? 'Unknown medicine');
        $signedDelta = $deltaQty >= 0 ? '+' . $deltaQty : (string) $deltaQty;

        ActivityLogService::log(
            'Pharmacy Stock Movement',
            strtoupper($action),
            sprintf(
                'Bill: %s | Medicine: %s | Before: %.2f | Change: %s | After: %.2f',
                (string) $billNumber,
                (string) $medicineName,
                $beforeQty,
                $signedDelta,
                $afterQty
            ),
            [
                'bill_no' => (string) $billNumber,
                'medicine_name' => (string) $medicineName,
                'before' => $beforeQty,
                'change' => $deltaQty,
                'after' => $afterQty,
            ]
        );
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();
        try {
            $existingBill = $this->pharmacyBillService->find($id);
            if ($existingBill?->ipd_patient_id) {
                DB::rollBack();
                return redirect()->back()->with('errorMessage', 'IPD pharmacy dispense records cannot be deactivated because they are part of the running bill history.');
            }
            $dataInfo = $this->pharmacyBillService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Pharmacy Bill ' . $status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacybills', $message);
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To " . $status . " Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function nextSequentialPharmacyNumber(string $field, string $prefix, string $datePattern, int $digits): string
    {
        $base = Carbon::now()->format($datePattern);
        $like = $prefix . $base . '%';

        $lastValue = ($field === 'pharmacy_no' ? PharmacyBill::withTrashed() : Billing::withTrashed())
            ->where($field, 'like', $like)
            ->lockForUpdate()
            ->orderBy($field, 'desc')
            ->value($field);

        $lastNumber = $lastValue ? (int) substr((string) $lastValue, -$digits) : 0;

        return $prefix . $base . str_pad((string) ($lastNumber + 1), $digits, '0', STR_PAD_LEFT);
    }

    private function generatePharmacyNumber($lastPharmacy = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        return $this->nextSequentialPharmacyNumber('pharmacy_no', $prefix, 'Y', 4);
    }

    private function generateBillNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        return $this->nextSequentialPharmacyNumber('bill_number', $prefix, 'Ym', 4);
    }

    private function generateInvoiceNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        return $this->nextSequentialPharmacyNumber('invoice_number', $prefix, 'Ym', 4);
    }

    private function generateCaseNumber($lastBilling = null)
    {
        return $this->nextSequentialPharmacyNumber('case_number', 'CASE', 'Ym', 4);
    }

    private function prescriptionPrefill(int $prescriptionId, int $ipdPrescriptionId = 0, ?IpdPatient $ipdPatient = null)
    {
        if ($ipdPrescriptionId > 0) {
            $prescription = IpdPrescription::query()
                ->with(['medicines', 'patient', 'doctor'])
                ->find($ipdPrescriptionId);
            if (!$prescription || ($ipdPatient && (int) $prescription->ipd_patient_id !== (int) $ipdPatient->id)) {
                abort(404, 'IPD prescription not found for this admission.');
            }

            $medicineNames = $prescription->medicines
                ->pluck('medicine_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values();
            $inventory = MedicineInventory::query()
                ->where('status', 'Active')
                ->whereIn('medicine_name', $medicineNames)
                ->with('category:id,name')
                ->orderBy('id')
                ->get(['id', 'medicine_name', 'medicine_unit_selling_price as sale_price', 'medicine_quantity', 'medicine_category_id', 'expiry_date'])
                ->unique(fn ($medicine) => mb_strtolower(trim($medicine->medicine_name)))
                ->keyBy(fn ($medicine) => mb_strtolower(trim($medicine->medicine_name)));

            return [
                'id' => $prescription->id,
                'ipd_patient_id' => $prescription->ipd_patient_id,
                'patient_id' => $prescription->patient_id,
                'doctor_id' => $prescription->doctor_id,
                'unmatched' => $prescription->medicines
                    ->filter(fn ($item) => !$inventory->has(mb_strtolower(trim((string) $item->medicine_name))))
                    ->pluck('medicine_name')
                    ->values(),
                'rows' => $prescription->medicines->map(function ($item) use ($inventory) {
                    $name = trim((string) $item->medicine_name);
                    $matched = $inventory->get(mb_strtolower($name));

                    return [
                        'medicineCategory' => $matched?->category?->name ?? '',
                        'medicine' => $matched,
                        'name' => $name,
                        'quantity' => max((int) ($item->quantity ?? 1), 1),
                        'salePrice' => (float) ($matched?->sale_price ?? 0),
                        'availableQty' => (float) ($matched?->medicine_quantity ?? 0),
                    ];
                })->values(),
            ];
        }

        if ($prescriptionId < 1 || !Schema::hasTable('prescriptions')) {
            return null;
        }

        $prescription = Prescription::query()
            ->with(['medicines', 'patient', 'doctor'])
            ->find($prescriptionId);

        if (!$prescription) {
            return null;
        }

        $medicineNames = $prescription->medicines
            ->pluck('medicine_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();
        $inventory = MedicineInventory::query()
            ->where('status', 'Active')
            ->whereIn('medicine_name', $medicineNames)
            ->with('category:id,name')
            ->orderBy('id')
            ->get(['id', 'medicine_name', 'medicine_unit_selling_price', 'medicine_quantity', 'medicine_category_id'])
            ->unique(fn ($medicine) => mb_strtolower(trim($medicine->medicine_name)))
            ->keyBy(fn ($medicine) => mb_strtolower(trim($medicine->medicine_name)));

        $rows = $prescription->medicines->map(function ($item) use ($inventory) {
            $name = trim((string) ($item->medicine_name ?? ''));
            $matched = $inventory->get(mb_strtolower($name));

            return [
                'medicineCategory' => $matched?->category?->name ?? '',
                'medicine' => $matched,
                'name' => $name,
                'quantity' => 1,
                'salePrice' => (float) ($matched?->sale_price ?? 0),
                'availableQty' => (float) ($matched?->medicine_quantity ?? 0),
            ];
        })->values();

        return [
            'id' => $prescription->id,
            'patient_id' => $prescription->patient_id,
            'doctor_id' => $prescription->doctor_id,
            'rows' => $rows,
        ];
    }
}
