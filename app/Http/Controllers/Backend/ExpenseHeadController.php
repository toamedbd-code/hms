<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseHeadRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ExpenseHeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ExpenseHeadController extends Controller
{
    use SystemTrait;

    protected $expenseheadService;

    public function __construct(ExpenseHeadService $expenseheadService)
    {
        $this->expenseheadService = $expenseheadService;

        $this->middleware('auth:admin');
        $this->middleware('permission:expensehead-list');
        $this->middleware('permission:expensehead-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:expensehead-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:expensehead-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:expensehead-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/ExpenseHead/Index',
            [
                'pageTitle' => fn() => 'Expense Head List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->expenseheadService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('expensehead-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.expensehead.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('expensehead-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.expensehead.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('expensehead-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.expensehead.destroy', $data->id),
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
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/ExpenseHead/Form',
            [
                'pageTitle' => fn() => 'Expense Head Create',
            ]
        );
    }


    public function store(ExpenseHeadRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->expenseheadService->create($data);

            if ($dataInfo) {
                $message = 'ExpenseHead created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'expenseheads', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create ExpenseHead.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseHeadController', 'store', substr($err->getMessage(), 0, 1000));
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
        $expensehead = $this->expenseheadService->find($id);

        return Inertia::render(
            'Backend/ExpenseHead/Form',
            [
                'pageTitle' => fn() => 'Expense Head Edit',
                'expensehead' => fn() => $expensehead,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ExpenseHeadRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $expensehead = $this->expenseheadService->find($id);

            $dataInfo = $this->expenseheadService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'ExpenseHead updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'expenseheads', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update expenseheads.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseHeadController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->expenseheadService->delete($id)) {
                $message = 'ExpenseHead deleted successfully';
                $this->storeAdminWorkLog($id, 'expenseheads', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete ExpenseHead.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseHeadController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->expenseheadService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'ExpenseHead ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'expenseheads', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "ExpenseHead.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ExpenseHeadController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
