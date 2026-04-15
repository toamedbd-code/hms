<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineSupplierRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\MedicineSupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class MedicineSupplierController extends Controller
{
    use SystemTrait;

    protected $medicinesupplierService;

    public function __construct(MedicineSupplierService $medicinesupplierService)
    {
        $this->medicinesupplierService = $medicinesupplierService;

        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-supplier-list');
        $this->middleware('permission:medicine-supplier-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-supplier-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-supplier-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-supplier-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/MedicineSupplier/Index',
            [
                'pageTitle' => fn() => 'Medicine Supplier List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->medicinesupplierService->list()
            ->withSum('medicinePurchases as purchase_total_amount', 'total_amount')
            ->withSum('medicinePurchases as purchase_paid_amount', 'paid_amount')
            ->withSum('medicinePurchases as purchase_due_amount', 'due_amount');

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $gate = Gate::forUser($user);
            $customData->id = $data->id;
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->phone = $data->phone;
            $customData->contact_person_name = $data->contact_person_name;
            $customData->contact_person_phone = $data->contact_person_phone;
            $customData->drug_lisence_no = $data->drug_lisence_no;
            $customData->address = $data->address;
            $customData->purchase_total_amount = number_format((float) ($data->purchase_total_amount ?? 0), 2);
            $customData->purchase_paid_amount = number_format((float) ($data->purchase_paid_amount ?? 0), 2);
            $customData->purchase_due_amount = number_format((float) ($data->purchase_due_amount ?? 0), 2);
            $dueAmount = (float) ($data->purchase_due_amount ?? 0);
            $customData->purchase_due_amount_numeric = $dueAmount;
            $customData->payment_status = $dueAmount > 0
                ? '<span class="px-2 py-1 text-xs rounded bg-amber-100 text-amber-700">Pending</span>'
                : '<span class="px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-700">Paid</span>';
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($dueAmount > 0 && $gate->allows('supplier-payment-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'bg-blue-600 text-white semi-bold',
                    'linkStyle' => 'background: linear-gradient(to right, #3b82f6, #60a5fa); color: #ffffff; border: 0;',
                    'link' => '#',
                    'linkLabel' => getLinkLabel('Due Payment', null, null),
                    'actionName' => 'supplier-due-payment',
                    'actionId' => $data->id,
                ];
            }

            if ($gate->allows('medicine-supplier-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicinesupplier.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($gate->allows('medicine-supplier-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicinesupplier.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($gate->allows('medicine-supplier-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.medicinesupplier.destroy', $data->id),
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
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'contact_person_name', 'class' => 'text-center'],
            ['fieldName' => 'contact_person_phone', 'class' => 'text-center'],
            ['fieldName' => 'drug_lisence_no', 'class' => 'text-center'],
            ['fieldName' => 'address', 'class' => 'text-center'],
            ['fieldName' => 'purchase_total_amount', 'class' => 'text-center'],
            ['fieldName' => 'purchase_paid_amount', 'class' => 'text-center'],
            ['fieldName' => 'purchase_due_amount', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Phone',
            'Contact Person Name',
            'Contact Person Phone',
            'Drug Lisence Number',
            'Address',
            'Purchase Total',
            'Paid',
            'Due',
            'Payment Status',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/MedicineSupplier/Form',
            [
                'pageTitle' => fn() => 'Medicine Supplier Create',
            ]
        );
    }


    public function store(MedicineSupplierRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->medicinesupplierService->create($data);

            if ($dataInfo) {
                $message = 'MedicineSupplier created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinesuppliers', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create MedicineSupplier.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineSupplierController', 'store', substr($err->getMessage(), 0, 1000));
            //dd($err);
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            // dd($message);
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $medicinesupplier = $this->medicinesupplierService->find($id);

        return Inertia::render(
            'Backend/MedicineSupplier/Form',
            [
                'pageTitle' => fn() => 'Medicine Supplier Edit',
                'medicinesupplier' => fn() => $medicinesupplier,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(MedicineSupplierRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $medicinesupplier = $this->medicinesupplierService->find($id);

            $dataInfo = $this->medicinesupplierService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'MedicineSupplier updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinesuppliers', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update medicinesuppliers.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineSupplierController', 'update', substr($err->getMessage(), 0, 1000));
            DB::commit();
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

            if ($this->medicinesupplierService->delete($id)) {
                $message = 'MedicineSupplier deleted successfully';
                $this->storeAdminWorkLog($id, 'medicinesuppliers', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete MedicineSupplier.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineSupplierController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->medicinesupplierService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineSupplier ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinesuppliers', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "MedicineSupplier.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineSupplierController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
