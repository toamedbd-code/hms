<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrderRequest;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class WorkOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/WorkOrder/Index', [
            'pageTitle' => fn () => 'Work Orders',
            'datas' => fn () => WorkOrder::query()->with('productionOrder')->paginate(request()->numOfData ?? 10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/WorkOrder/Form', ['pageTitle' => fn () => 'Create Work Order']);
    }

    public function store(WorkOrderRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $wo = WorkOrder::create($data);
            DB::commit();
            return redirect()->route('backend.work-order.index')->with('successMessage', 'Work order created');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to create work order');
        }
    }
}
