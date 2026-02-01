<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferralPersonRequest;
use App\Services\ReferralCategoryService;
use Illuminate\Support\Facades\DB;
use App\Services\ReferralPersonService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ReferralPersonController extends Controller
{
    use SystemTrait;

    protected $referralpersonService, $referralCategoryService;

    public function __construct(ReferralPersonService $referralpersonService, ReferralCategoryService $referralCategoryService)
    {
        $this->referralpersonService = $referralpersonService;
        $this->referralCategoryService = $referralCategoryService;

        $this->middleware('auth:admin');
        $this->middleware('permission:referral-person-list');
        $this->middleware('permission:referral-person-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:referral-person-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:referral-person-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:referral-person-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/ReferralPerson/Index',
            [
                'pageTitle' => fn() => 'Referral List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->referralpersonService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->category_id = $data->category_id;
            $commissionParts = [];

            if (!empty($data->opd_commission) && $data->opd_commission > 0) {
                $commissionParts[] = 'OPD -' . number_format($data->opd_commission, 0) . '%' . '<br>';
            }
            if (!empty($data->ipd_commission) && $data->ipd_commission > 0) {
                $commissionParts[] = 'IPD -' . number_format($data->ipd_commission, 0) . '%' . '<br>';
            }
            if (!empty($data->pharmacy_commission) && $data->pharmacy_commission > 0) {
                $commissionParts[] = 'Pharmacy -' . number_format($data->pharmacy_commission, 0) . '%' . '<br>';
            }
            if (!empty($data->pathology_commission) && $data->pathology_commission > 0) {
                $commissionParts[] = 'Pathology -' . number_format($data->pathology_commission, 0) . '%' . '<br>';
            }
            if (!empty($data->radiology_commission) && $data->radiology_commission > 0) {
                $commissionParts[] = 'Radiology -' . number_format($data->radiology_commission, 0) . '%' . '<br>';
            }
            if (!empty($data->blood_bank_commission) && $data->blood_bank_commission > 0) {
                $commissionParts[] = 'Blood Bank -' . number_format($data->blood_bank_commission, 0) . '%' . '<br>';
            }
            if (!empty($data->ambulance_commission) && $data->ambulance_commission > 0) {
                $commissionParts[] = 'Ambulance -' . number_format($data->ambulance_commission, 0) . '%' . '<br>';
            }

            $customData->commission = !empty($commissionParts) ? implode(' ', $commissionParts) : 'No Commission';

            $customData->phone = $data->phone;
            $customData->contact_person_name = $data->contact_person_name;
            $customData->contact_person_phone = $data->contact_person_phone;
            $customData->address = $data->address;

            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('referral-person-list-status-change')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.referralperson.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('referral-person-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.referralperson.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('referral-person-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.referralperson.destroy', $data->id),
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
            ['fieldName' => 'category_id', 'class' => 'text-center'],
            [
                'fieldName' => 'commission',
                'class' => 'text-left whitespace-nowrap',
                'colspan' => 2
            ],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'contact_person_name', 'class' => 'text-center'],
            ['fieldName' => 'contact_person_phone', 'class' => 'text-center'],
            ['fieldName' => 'address', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Referrer Name',
            'Category',
            'Commission',
            'Referrer Phone',
            'Contact Person Name',
            'Contact Person Phone',
            'Address',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/ReferralPerson/Form',
            [
                'pageTitle' => fn() => 'Referral Create',
                'categories' => fn() => $this->referralCategoryService->activeList()
            ]
        );
    }


    public function store(ReferralPersonRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->referralpersonService->create($data);

            if ($dataInfo) {
                $message = 'ReferralPerson created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'referralpeople', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create ReferralPerson.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralPersonController', 'store', substr($err->getMessage(), 0, 1000));
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
        $referralperson = $this->referralpersonService->find($id);

        return Inertia::render(
            'Backend/ReferralPerson/Form',
            [
                'pageTitle' => fn() => 'Referral Edit',
                'referralperson' => fn() => $referralperson,
                'id' => fn() => $id,
                'categories' => fn() => $this->referralCategoryService->activeList()
            ]
        );
    }

    public function update(ReferralPersonRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $referralperson = $this->referralpersonService->find($id);

            $dataInfo = $this->referralpersonService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'ReferralPerson updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'referralpeople', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update referralpeople.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralPersonController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->referralpersonService->delete($id)) {
                $message = 'ReferralPerson deleted successfully';
                $this->storeAdminWorkLog($id, 'referralpeople', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete ReferralPerson.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralPersonController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->referralpersonService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'ReferralPerson ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'referralpeople', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "ReferralPerson.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralPersonController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
