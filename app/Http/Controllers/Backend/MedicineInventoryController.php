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
use App\Services\MedicineInventoryService;
use App\Services\MedicineSupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

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
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->medicineinventoryService->list();

        // Search by medicine name
        if (request()->filled('name'))
            $query->where('medicine_name', 'like', '%' . request()->name . '%');

        // Optional: Add search by supplier or category if needed
        if (request()->filled('supplier_id'))
            $query->where('supplier_id', request()->supplier_id);

        if (request()->filled('medicine_category_id'))
            $query->where('medicine_category_id', request()->medicine_category_id);

        $query->orderBy('created_at', 'desc');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->supplier_id = $data?->supplier?->name ?? '';
            $customData->medicine_category_id = $data?->category?->name ?? '';
            $customData->medicine_name = $data->medicine_name;
            $customData->medicine_unit_purchase_price = $data->medicine_unit_purchase_price;
            $customData->medicine_unit_selling_price = $data->medicine_unit_selling_price;
            $customData->medicine_total_purchase_price = $data->medicine_total_purchase_price;
            $customData->medicine_total_selling_price = $data->medicine_total_selling_price;
            $customData->medicine_quantity = $data->medicine_quantity;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user?->can('medicine-inventory-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicineinventory.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user?->can('medicine-inventory-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicineinventory.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user?->can('medicine-inventory-list-delete')) {
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
                    $medicineInfo = [
                        'supplier_id' => $data['supplier_id'],
                        'medicine_category_id' => $data['medicine_category_id'],
                        'medicine_name' => $medicineData['medicine_name'],
                        'medicine_unit_purchase_price' => $medicineData['medicine_unit_purchase_price'],
                        'medicine_unit_selling_price' => $medicineData['medicine_unit_selling_price'],
                        'medicine_total_purchase_price' => $medicineData['medicine_total_purchase_price'],
                        'medicine_total_selling_price' => $medicineData['medicine_total_selling_price'],
                        'medicine_quantity' => $medicineData['medicine_quantity'],
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
                $medicineInfo = [
                    'supplier_id' => $data['supplier_id'],
                    'medicine_category_id' => $data['medicine_category_id'],
                    'medicine_name' => $data['medicine_name'],
                    'medicine_unit_purchase_price' => $data['medicine_unit_purchase_price'],
                    'medicine_unit_selling_price' => $data['medicine_unit_selling_price'],
                    'medicine_total_purchase_price' => $data['medicine_total_purchase_price'],
                    'medicine_total_selling_price' => $data['medicine_total_selling_price'],
                    'medicine_quantity' => $data['medicine_quantity'],
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
    


public function importCsv(Request $request)
{
    $request->validate([
        'supplier_id' => 'required',
        'medicine_category_id' => 'required',
        'csv_file' => 'required|file|mimes:csv,txt',
        'skip_duplicates' => 'nullable|boolean',
    ]);

    $file = $request->file('csv_file');

    // test
    if (!$file) {
        return response()->json([
            'message' => 'CSV not found'
        ], 422);
    }

    // continue import...

    $supplierId = $request->supplier_id;
    $categoryId = $request->medicine_category_id;
    $skipDuplicates = $request->boolean('skip_duplicates', false);

    $file = $request->file('csv_file');
    $rows = array_map('str_getcsv', file($file));

    // Header remove
    $header = array_map('trim', $rows[0]);
    unset($rows[0]);

    DB::beginTransaction();
    $errors = [];
    $skipped = [];
    $imported = 0;
    try {
        foreach ($rows as $row) {
            if (count($row) < 4) continue;

            $data = array_combine($header, $row);

            if (!$data || !isset($data['medicine_name'], $data['unit_purchase_price'], $data['unit_selling_price'], $data['quantity'])) {
                continue; // Skip invalid rows
            }

            // Check for duplicate medicine name
            if (MedicineInventory::where('medicine_name', $data['medicine_name'])->exists()) {
                if ($skipDuplicates) {
                    $skipped[] = $data['medicine_name'];
                    continue;
                } else {
                    $errors[] = "Medicine '{$data['medicine_name']}' already exists";
                    continue;
                }
            }

            MedicineInventory::create([
                'supplier_id' => $supplierId,
                'medicine_category_id' => $categoryId,
                'medicine_name' => $data['medicine_name'],
                'medicine_unit_purchase_price' => $data['unit_purchase_price'],
                'medicine_unit_selling_price' => $data['unit_selling_price'],
                'medicine_quantity' => $data['quantity'],
                'medicine_total_purchase_price' => $data['unit_purchase_price'] * $data['quantity'],
                'medicine_total_selling_price' => $data['unit_selling_price'] * $data['quantity'],
                'status' => 'Active',
            ]);
            $imported++;
        }

        if (!empty($errors)) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Import failed due to duplicates: ' . implode(', ', $errors)
            ], 422);
        }

        DB::commit();

        $message = 'Imported ' . $imported . ' medicines.';
        if (!empty($skipped)) {
            $message .= ' Skipped ' . count($skipped) . ' duplicates.';
        }

        return response()->json([
            'status' => true,
            'message' => $message
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}