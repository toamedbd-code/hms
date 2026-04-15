<?php

namespace App\Http\Controllers\Backend;

use App\Models\MedicineInventory;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineInventoryRequest;
use App\Models\MedicineCategory;
use App\Models\MedicineSupplier;
use App\Services\MedicineCategoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\MedicineInventoryService;
use App\Services\MedicineSupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use App\Models\OpdPrescriptionItem;
use App\Models\IpdPrescriptionMedicine;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MedicineInventoryController extends Controller
{
    use SystemTrait;

    protected $medicineinventoryService, $medicineCategorieService, $medicineSupplierService;

    public function __construct(MedicineInventoryService $medicineinventoryService, MedicineCategoryService $medicineCategorieService, MedicineSupplierService $medicineSupplierService)
    {
        $this->medicineinventoryService = $medicineinventoryService;
        $this->medicineCategorieService = $medicineCategorieService;
        $this->medicineSupplierService = $medicineSupplierService;

        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-inventory-list');
        $this->middleware('permission:medicine-inventory-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-inventory-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-inventory-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-inventory-list-status', ['only' => ['changeStatus']]);
        
            // Alias permissions for Pharmacy Management > Add Medicine permission tree.
            $this->middleware('permission:medicine-inventory-add-create', ['only' => ['create', 'store']]);
            $this->middleware('permission:medicine-inventory-add-edit', ['only' => ['edit', 'update']]);
            $this->middleware('permission:medicine-inventory-add-delete', ['only' => ['destroy']]);
            $this->middleware('permission:medicine-inventory-add-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/MedicineInventory/Index',
            [
                'pageTitle' => fn() => 'Medicine Inventory List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'filters' => fn() => [
                    'name' => request()->get('name', ''),
                    'numOfData' => request()->get('numOfData', 10),
                    'expiry_filter' => request()->get('expiry_filter', 'all'),
                ],
            ]
        );
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        $includeDefaults = $request->boolean('include_defaults');

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $medicineNames = MedicineInventory::query()
            ->where('status', 'Active')
            ->where('medicine_name', 'like', '%' . $query . '%')
            ->orderBy('medicine_name')
            ->limit(15)
            ->pluck('medicine_name')
            ->values();

        if (!$includeDefaults) {
            return response()->json(['results' => $medicineNames]);
        }

        $opdDefaultsByMedicine = OpdPrescriptionItem::query()
            ->whereIn('medicine_name', $medicineNames)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['medicine_name', 'dose', 'duration', 'frequency', 'instructions', 'created_at'])
            ->unique('medicine_name')
            ->keyBy('medicine_name');

        $ipdDefaultsByMedicine = IpdPrescriptionMedicine::query()
            ->whereIn('medicine_name', $medicineNames)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['medicine_name', 'dose', 'duration', 'frequency', 'instructions', 'created_at'])
            ->unique('medicine_name')
            ->keyBy('medicine_name');

        $results = $medicineNames->map(function ($name) use ($opdDefaultsByMedicine, $ipdDefaultsByMedicine) {
            $opdDefaults = $opdDefaultsByMedicine->get($name);
            $ipdDefaults = $ipdDefaultsByMedicine->get($name);

            $defaults = $ipdDefaults;

            if ($opdDefaults && $ipdDefaults) {
                $opdTime = $opdDefaults?->created_at ? strtotime((string) $opdDefaults->created_at) : 0;
                $ipdTime = $ipdDefaults?->created_at ? strtotime((string) $ipdDefaults->created_at) : 0;

                $defaults = $opdTime >= $ipdTime ? $opdDefaults : $ipdDefaults;
            } elseif ($opdDefaults) {
                $defaults = $opdDefaults;
            }

            return [
                'name' => $name,
                'dose' => $defaults?->dose ?? '',
                'duration' => $defaults?->duration ?? '',
                'frequency' => $defaults?->frequency ?? '',
                'instructions' => $defaults?->instructions ?? '',
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    private function getDatas()
    {
        $query = $this->medicineinventoryService->list();
        $webSetting = get_cached_web_setting();
        $configuredThreshold = (int) ($webSetting?->low_stock_threshold ?? 10);
        $lowStockThreshold = max($configuredThreshold, 0);

        // Search by medicine name
        if (request()->filled('name'))
            $query->where('medicine_name', 'like', '%' . request()->name . '%');

        // Optional: Add search by supplier or category if needed
        if (request()->filled('supplier_id'))
            $query->where('supplier_id', request()->supplier_id);

        if (request()->filled('medicine_category_id'))
            $query->where('medicine_category_id', request()->medicine_category_id);

        $expiryFilter = request()->get('expiry_filter', 'all');
        $today = Carbon::today()->toDateString();
        $soonDate = Carbon::today()->addDays(30)->toDateString();

        if ($expiryFilter === 'expired') {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', $today);
        } elseif ($expiryFilter === 'expiring_soon') {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $soonDate);
        } elseif ($expiryFilter === 'alerts') {
            $query->whereNotNull('expiry_date')
                ->where(function ($subQuery) use ($today, $soonDate) {
                    $subQuery->whereDate('expiry_date', '<', $today)
                        ->orWhere(function ($expiringSoonQuery) use ($today, $soonDate) {
                            $expiringSoonQuery->whereDate('expiry_date', '>=', $today)
                                ->whereDate('expiry_date', '<=', $soonDate);
                        });
                });
        }

        $query->orderBy('created_at', 'desc');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user, $lowStockThreshold) {
            $customData = new \stdClass();
            $gate = Gate::forUser($user);
            $customData->index = $index + 1;
            $customData->supplier_id = $data?->supplier?->name ?? '';
            $customData->medicine_category_id = $data?->category?->name ?? '';
            $customData->medicine_name = $data->medicine_name;
            $customData->medicine_unit_purchase_price = $data->medicine_unit_purchase_price;
            $customData->medicine_unit_selling_price = $data->medicine_unit_selling_price;
            $customData->medicine_total_purchase_price = $data->medicine_total_purchase_price;
            $customData->medicine_total_selling_price = $data->medicine_total_selling_price;
            $customData->medicine_quantity = $data->medicine_quantity;
            $customData->expiry_date = $data->expiry_date ? date('Y-m-d', strtotime((string) $data->expiry_date)) : 'N/A';

            $qty = (float) ($data->medicine_quantity ?? 0);
            if ($qty <= 0) {
                $customData->low_stock_alert = '<span class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded">Out of Stock</span>';
            } elseif ($qty <= $lowStockThreshold) {
                $customData->low_stock_alert = '<span class="px-2 py-1 text-xs font-semibold text-amber-700 bg-amber-100 rounded">Low Stock (' . $qty . ')</span>';
            } else {
                $customData->low_stock_alert = '<span class="px-2 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded">In Stock</span>';
            }

            $expiryAlert = 'N/A';
            if (!empty($data->expiry_date)) {
                $daysLeft = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($data->expiry_date)->startOfDay(), false);
                if ($daysLeft < 0) {
                    $expiryAlert = '<span class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded">Expired</span>';
                } elseif ($daysLeft <= 30) {
                    $expiryAlert = '<span class="px-2 py-1 text-xs font-semibold text-amber-700 bg-amber-100 rounded">Expiring in ' . $daysLeft . ' day(s)</span>';
                } else {
                    $expiryAlert = '<span class="px-2 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded">Safe (' . $daysLeft . ' days)</span>';
                }
            }
            $customData->expiry_alert = $expiryAlert;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($gate->allows('medicine-inventory-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicineinventory.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($gate->allows('medicine-inventory-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicineinventory.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($gate->allows('medicine-inventory-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.medicineinventory.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'supplier_id', 'class' => 'text-center'],
            ['fieldName' => 'medicine_category_id', 'class' => 'text-center'],
            ['fieldName' => 'medicine_name', 'class' => 'text-center'],
            ['fieldName' => 'medicine_unit_purchase_price', 'class' => 'text-center'],
            ['fieldName' => 'medicine_unit_selling_price', 'class' => 'text-center'],
            ['fieldName' => 'medicine_total_purchase_price', 'class' => 'text-center'],
            ['fieldName' => 'medicine_total_selling_price', 'class' => 'text-center'],
            ['fieldName' => 'medicine_quantity', 'class' => 'text-center'],
            ['fieldName' => 'low_stock_alert', 'class' => 'text-center'],
            ['fieldName' => 'expiry_date', 'class' => 'text-center'],
            ['fieldName' => 'expiry_alert', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Supplier Name',
            'Category',
            'Name',
            'Unit Purchase Price',
            'Unit Selling Price',
            'Purachse Total Price',
            'Selling TotalPrice',
            'Quantity',
            'Stock Alert',
            'Expiry Date',
            'Expiry Alert',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        $suppliers = $this->medicineSupplierService->activeList();
        $medicineCategories = $this->medicineCategorieService->activeList();

        return Inertia::render(
            'Backend/MedicineInventory/Form',
            [
                'pageTitle' => fn() => 'Medicine Inventory Create',
                'suppliers' => fn() => $suppliers,
                'medicineCategories' => fn() => $medicineCategories,
            ]
        );
    }


    public function store(MedicineInventoryRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            // Handle multiple medicines creation
            if (isset($data['medicines']) && is_array($data['medicines'])) {
                $createdMedicines = [];

                foreach ($data['medicines'] as $medicineData) {
                    if ($this->medicineExistsForSupplierCategoryName(
                        (int) $data['supplier_id'],
                        (int) $data['medicine_category_id'],
                        (string) ($medicineData['medicine_name'] ?? '')
                    )) {
                        throw ValidationException::withMessages([
                            'medicines' => 'Duplicate medicine is not allowed for this supplier and category: ' . ($medicineData['medicine_name'] ?? 'N/A'),
                        ]);
                    }

                    $medicineInfo = [
                        'supplier_id' => $data['supplier_id'],
                        'medicine_category_id' => $data['medicine_category_id'],
                        'medicine_name' => $medicineData['medicine_name'],
                        'medicine_unit_purchase_price' => $medicineData['medicine_unit_purchase_price'],
                        'medicine_unit_selling_price' => $medicineData['medicine_unit_selling_price'],
                        'medicine_total_purchase_price' => $medicineData['medicine_total_purchase_price'],
                        'medicine_total_selling_price' => $medicineData['medicine_total_selling_price'],
                        'medicine_quantity' => $medicineData['medicine_quantity'],
                        'expiry_date' => $medicineData['expiry_date'] ?? null,
                        'remarks' => $medicineData['remarks'] ?? null,
                        'status' => 'Active'
                    ];

                    $dataInfo = $this->medicineinventoryService->create($medicineInfo);

                    if ($dataInfo) {
                        $createdMedicines[] = $dataInfo;
                        $this->storeAdminWorkLog($dataInfo->id, 'medicineinventories', 'Medicine created: ' . $medicineData['medicine_name']);
                    } else {
                        throw new Exception('Failed to create medicine: ' . $medicineData['medicine_name']);
                    }
                }

                DB::commit();

                $message = count($createdMedicines) . ' Medicine(s) created successfully';
                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                // Handle single medicine creation (fallback)
                if ($this->medicineExistsForSupplierCategoryName(
                    (int) $data['supplier_id'],
                    (int) $data['medicine_category_id'],
                    (string) ($data['medicine_name'] ?? '')
                )) {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->with('errorMessage', 'Duplicate medicine is not allowed for this supplier and category.');
                }

                $medicineInfo = [
                    'supplier_id' => $data['supplier_id'],
                    'medicine_category_id' => $data['medicine_category_id'],
                    'medicine_name' => $data['medicine_name'],
                    'medicine_unit_purchase_price' => $data['medicine_unit_purchase_price'],
                    'medicine_unit_selling_price' => $data['medicine_unit_selling_price'],
                    'medicine_total_purchase_price' => $data['medicine_total_purchase_price'],
                    'medicine_total_selling_price' => $data['medicine_total_selling_price'],
                    'medicine_quantity' => $data['medicine_quantity'],
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'status' => 'Active'
                ];

                $dataInfo = $this->medicineinventoryService->create($medicineInfo);

                if ($dataInfo) {
                    $message = 'MedicineInventory created successfully';
                    $this->storeAdminWorkLog($dataInfo->id, 'medicineinventories', $message);

                    DB::commit();

                    return redirect()
                        ->back()
                        ->with('successMessage', $message);
                } else {
                    throw new Exception('Failed to create MedicineInventory');
                }
            }
        } catch (ValidationException $validationException) {
            DB::rollBack();

            return redirect()
                ->back()
                ->with('errorMessage', collect($validationException->errors())->flatten()->first() ?? 'Validation failed.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineInventoryController', 'store', substr($err->getMessage(), 0, 1000));

            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit(MedicineInventory $medicineinventory)
    {
        $suppliers = MedicineSupplier::select('id', 'name')->get();
        $medicineCategories = MedicineCategory::select('id', 'name')->get();

        return inertia('Backend/MedicineInventory/Form', [
            'pageTitle' => 'Medicine Inventory Edit',
            'medicine' => $medicineinventory->toArray(),
            'suppliers' => $suppliers,
            'medicineCategories' => $medicineCategories,
            'isEdit' => true,
        ]);
    }

    public function show(MedicineInventory $medicineinventory)
    {
        return $this->edit($medicineinventory);
    }


    public function update(MedicineInventoryRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $medicineinventory = $this->medicineinventoryService->find($id);

            if (!$medicineinventory) {
                DB::rollBack();
                return redirect()
                    ->route('backend.medicineinventory.index')
                    ->with('errorMessage', 'MedicineInventory not found.');
            }

            // For updates, we only handle single medicine updates
            // Remove _method if it exists in the data
            unset($data['_method']);

            // Recalculate totals based on unit prices and quantity
            $data['medicine_total_purchase_price'] = $data['medicine_unit_purchase_price'] * $data['medicine_quantity'];
            $data['medicine_total_selling_price'] = $data['medicine_unit_selling_price'] * $data['medicine_quantity'];

            if ($this->medicineExistsForSupplierCategoryName(
                (int) $data['supplier_id'],
                (int) $data['medicine_category_id'],
                (string) ($data['medicine_name'] ?? ''),
                (int) $id
            )) {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Duplicate medicine is not allowed for this supplier and category.');
            }

            $dataInfo = $this->medicineinventoryService->update($data, $id);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineInventory updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicineinventories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "No changes were made to the MedicineInventory.";
                return redirect()
                    ->back()
                    ->with('infoMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineInventoryController', 'update', substr($err->getMessage(), 0, 1000));

            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function destroy($id)
    {

        DB::beginTransaction();

        try {

            if ($this->medicineinventoryService->delete($id)) {
                $message = 'MedicineInventory deleted successfully';
                $this->storeAdminWorkLog($id, 'medicineinventories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete MedicineInventory.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineInventoryController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->medicineinventoryService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineInventory ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicineinventories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "MedicineInventory.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineInventoryController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
    


    public function downloadSampleCsv()
    {
        try {
            $defaultSupplierName = MedicineSupplier::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->orderBy('name')
                ->value('name') ?? 'Demo Supplier';

            $sampleCategories = MedicineCategory::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->orderBy('name')
                ->limit(2)
                ->pluck('name')
                ->values();

            $firstCategoryName = $sampleCategories->get(0) ?? 'Tablet';
            $secondCategoryName = $sampleCategories->get(1) ?? $firstCategoryName;

            $headers = [
                'supplier',
                'category',
                'medicine_name',
                'unit_purchase_price',
                'unit_selling_price',
                'quantity',
                'expiry_date',
            ];

            $rows = [
                [$defaultSupplierName, $firstCategoryName, 'Paracetamol 500mg', '1.50', '2.00', '120', '2027-12-31'],
                [$defaultSupplierName, $secondCategoryName, 'Omeprazole 20mg', '3.20', '4.50', '80', '2027-10-15'],
            ];

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="medicine_inventory_sample.csv"',
            ]);
        } catch (Exception $e) {
            Log::error('MedicineInventoryController::downloadSampleCsv failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate sample CSV.',
            ], 500);
        }
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:medicinesuppliers,id',
            'medicine_category_id' => 'nullable|exists:medicine_categories,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:102400',
            'skip_duplicates' => 'nullable|boolean',
        ]);

        $file = $request->file('csv_file');
        if (!$file) {
            return response()->json([
                'status' => false,
                'message' => 'CSV file not found.',
            ], 422);
        }

        $handle = @fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to read CSV file.',
            ], 422);
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false || !is_array($headerRow)) {
            fclose($handle);
            return response()->json([
                'status' => false,
                'message' => 'CSV is empty.',
            ], 422);
        }

        $header = array_map(fn ($value) => trim((string) $value), $headerRow);
        $normalizedHeader = array_map(fn ($value) => Str::lower(trim((string) $value)), $header);
        $requiredHeader = ['supplier', 'category', 'medicine_name', 'unit_purchase_price', 'unit_selling_price', 'quantity'];
        $missingHeaders = array_values(array_diff($requiredHeader, $normalizedHeader));

        if (!empty($missingHeaders)) {
            fclose($handle);
            return response()->json([
                'status' => false,
                'message' => 'Invalid CSV header. Required: supplier, category, medicine_name, unit_purchase_price, unit_selling_price, quantity (optional: expiry_date).',
            ], 422);
        }

        $fallbackSupplierId = $request->filled('supplier_id') ? (int) $request->supplier_id : null;
        $fallbackCategoryId = $request->filled('medicine_category_id') ? (int) $request->medicine_category_id : null;
        $skipDuplicates = $request->boolean('skip_duplicates', false);

        @set_time_limit(0);
        DB::connection()->disableQueryLog();

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $createdSuppliers = 0;
        $createdCategories = 0;
        $errors = [];

        $supplierCache = [];
        $categoryCache = [];
        $seenCsvSignatures = [];

        try {
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                $normalizedRow = array_pad($row, count($header), null);
                $data = array_combine($normalizedHeader, $normalizedRow);

                $supplierName = trim((string) ($data['supplier'] ?? ''));
                $categoryName = trim((string) ($data['category'] ?? ''));

                $medicineName = trim((string) ($data['medicine_name'] ?? ''));
                $purchasePrice = trim((string) ($data['unit_purchase_price'] ?? ''));
                $sellingPrice = trim((string) ($data['unit_selling_price'] ?? ''));
                $quantity = trim((string) ($data['quantity'] ?? ''));
                $expiryDate = trim((string) ($data['expiry_date'] ?? ''));

                // Ignore fully blank lines in CSV.
                if ($supplierName === '' && $categoryName === '' && $medicineName === '' && $purchasePrice === '' && $sellingPrice === '' && $quantity === '') {
                    continue;
                }

                $supplierId = $fallbackSupplierId;
                if ($supplierName !== '') {
                    $supplierKey = $this->normalizeLookupValue($supplierName);
                    if (array_key_exists($supplierKey, $supplierCache)) {
                        [$supplier, $wasCreated] = $supplierCache[$supplierKey];
                    } else {
                        [$supplier, $wasCreated] = $this->resolveOrCreateSupplierFromCsv($supplierName);
                        $supplierCache[$supplierKey] = [$supplier, $wasCreated];
                    }

                    if (!$supplier) {
                        $errors[] = "Line {$line}: supplier '{$supplierName}' could not be resolved.";
                        $failed++;
                        continue;
                    }

                    if ($wasCreated) {
                        $createdSuppliers++;
                    }

                    $supplierId = (int) $supplier->id;
                }

                $categoryId = $fallbackCategoryId;
                if ($categoryName !== '') {
                    $categoryKey = $this->normalizeLookupValue($categoryName);
                    if (array_key_exists($categoryKey, $categoryCache)) {
                        [$category, $wasCreated] = $categoryCache[$categoryKey];
                    } else {
                        [$category, $wasCreated] = $this->resolveOrCreateCategoryFromCsv($categoryName);
                        $categoryCache[$categoryKey] = [$category, $wasCreated];
                    }

                    if (!$category) {
                        $errors[] = "Line {$line}: category '{$categoryName}' could not be resolved.";
                        $failed++;
                        continue;
                    }

                    if ($wasCreated) {
                        $createdCategories++;
                    }

                    $categoryId = $category->id;
                }

                if (!$supplierId || !$categoryId) {
                    $errors[] = "Line {$line}: supplier and category are required in CSV (or set default supplier/category).";
                    $failed++;
                    continue;
                }

                if ($medicineName === '' || !is_numeric($purchasePrice) || !is_numeric($sellingPrice) || !is_numeric($quantity)) {
                    $errors[] = "Line {$line}: invalid data format.";
                    $failed++;
                    continue;
                }

                $quantityValue = (float) $quantity;
                $purchaseValue = (float) $purchasePrice;
                $sellingValue = (float) $sellingPrice;

                if ($quantityValue < 0 || $purchaseValue < 0 || $sellingValue < 0) {
                    $errors[] = "Line {$line}: negative values are not allowed.";
                    $failed++;
                    continue;
                }

                if ($expiryDate !== '' && !strtotime($expiryDate)) {
                    $errors[] = "Line {$line}: invalid expiry_date format. Use YYYY-MM-DD.";
                    $failed++;
                    continue;
                }

                $normalizedMedicine = $this->normalizeLookupValue($medicineName);
                $signature = $supplierId . '|' . $categoryId . '|' . $normalizedMedicine;

                if (isset($seenCsvSignatures[$signature])) {
                    $skipped++;
                    continue;
                }
                $seenCsvSignatures[$signature] = true;

                $duplicateExists = $this->medicineExistsForSupplierCategoryName($supplierId, $categoryId, $medicineName);

                if ($duplicateExists) {
                    if ($skipDuplicates) {
                        $skipped++;
                        continue;
                    }

                    $errors[] = "Line {$line}: '{$medicineName}' already exists.";
                    $failed++;
                    continue;
                }

                MedicineInventory::create([
                    'supplier_id' => $supplierId,
                    'medicine_category_id' => $categoryId,
                    'medicine_name' => $medicineName,
                    'medicine_unit_purchase_price' => $purchaseValue,
                    'medicine_unit_selling_price' => $sellingValue,
                    'medicine_quantity' => $quantityValue,
                    'expiry_date' => $expiryDate !== '' ? date('Y-m-d', strtotime($expiryDate)) : null,
                    'medicine_total_purchase_price' => $purchaseValue * $quantityValue,
                    'medicine_total_selling_price' => $sellingValue * $quantityValue,
                    'status' => 'Active',
                ]);

                $imported++;
            }
            fclose($handle);

            $status = $imported > 0;
            $errorPreview = array_slice($errors, 0, 200);

            return response()->json([
                'status' => $status,
                'message' => "Imported {$imported} medicine(s) successfully."
                    . ($skipped > 0 ? " Skipped {$skipped} duplicate row(s)." : '')
                    . ($failed > 0 ? " Failed {$failed} row(s)." : '')
                    . ($createdSuppliers > 0 ? " Auto-created {$createdSuppliers} supplier(s)." : '')
                    . ($createdCategories > 0 ? " Auto-created {$createdCategories} category(s)." : ''),
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
                'created_suppliers' => $createdSuppliers,
                'created_categories' => $createdCategories,
                'errors' => $errorPreview,
            ]);
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return response()->json([
                'status' => false,
                'message' => 'CSV import failed due to server error.',
            ], 500);
        }
    }

    private function normalizeLookupValue(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        return Str::lower((string) $normalized);
    }

    private function medicineExistsForSupplierCategoryName(int $supplierId, int $categoryId, string $medicineName, ?int $ignoreId = null): bool
    {
        $normalizedMedicineName = $this->normalizeLookupValue($medicineName);
        if ($normalizedMedicineName === '') {
            return false;
        }

        $query = MedicineInventory::query()
            ->where('supplier_id', $supplierId)
            ->where('medicine_category_id', $categoryId)
            ->whereRaw('LOWER(TRIM(medicine_name)) = ?', [$normalizedMedicineName]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function resolveOrCreateSupplierFromCsv(string $supplierName): array
    {
        $cleanName = trim($supplierName);
        $normalized = $this->normalizeLookupValue($cleanName);

        if ($normalized === '') {
            return [null, false];
        }

        $supplier = MedicineSupplier::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();

        if (!$supplier) {
            $candidate = MedicineSupplier::query()->select('id', 'name')->get()
                ->first(function ($row) use ($normalized) {
                    return $this->normalizeLookupValue((string) ($row->name ?? '')) === $normalized;
                });

            if ($candidate) {
                $supplier = MedicineSupplier::query()->find($candidate->id);
            }
        }

        if ($supplier) {
            return [$supplier, false];
        }

        $supplier = MedicineSupplier::query()->create([
            'name' => $cleanName,
            'phone' => 'N/A',
            'contact_person_name' => 'N/A',
            'contact_person_phone' => 'N/A',
            'drug_lisence_no' => 'N/A',
            'address' => 'N/A',
            'status' => 'Active',
        ]);

        return [$supplier, true];
    }

    private function resolveOrCreateCategoryFromCsv(string $categoryName): array
    {
        $cleanName = trim($categoryName);
        $normalized = $this->normalizeLookupValue($cleanName);

        if ($normalized === '') {
            return [null, false];
        }

        $category = MedicineCategory::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();

        if (!$category) {
            $candidate = MedicineCategory::query()->select('id', 'name')->get()
                ->first(function ($row) use ($normalized) {
                    return $this->normalizeLookupValue((string) ($row->name ?? '')) === $normalized;
                });

            if ($candidate) {
                $category = MedicineCategory::query()->find($candidate->id);
            }
        }

        if ($category) {
            return [$category, false];
        }

        $category = MedicineCategory::query()->create([
            'name' => $cleanName,
            'status' => 'Active',
        ]);

        return [$category, true];
    }
}