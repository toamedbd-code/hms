<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionOrderRequest;
use App\Models\ProductionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class ProductionOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/ProductionOrder/Index', [
            'pageTitle' => fn () => 'Production Orders',
            'datas' => fn () => ProductionOrder::query()->with('bom')->paginate(request()->numOfData ?? 10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/ProductionOrder/Form', ['pageTitle' => fn () => 'Create Production Order']);
    }

    public function store(ProductionOrderRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $po = ProductionOrder::create($data);
            DB::commit();
            return redirect()->route('backend.production-order.index')->with('successMessage', 'Production order created');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to create production order');
        }
    }
}
