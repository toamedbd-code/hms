<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MedicineInventory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PharmacyStockReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:stock-report-list');
    }

    public function index(Request $request)
    {
        $query = MedicineInventory::query()
            ->with(['supplier:id,name', 'category:id,name'])
            ->select([
                'id',
                'supplier_id',
                'medicine_category_id',
                'medicine_name',
                'medicine_quantity',
                'medicine_unit_purchase_price',
                'medicine_unit_selling_price',
                'expiry_date',
                'status',
            ])
            ->orderBy('medicine_name');

        if ($request->filled('name')) {
            $query->where('medicine_name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('status') && in_array($request->status, ['Active', 'Inactive'], true)) {
            $query->where('status', $request->status);
        }

        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(medicine_quantity), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(medicine_quantity * medicine_unit_purchase_price), 0) as total_purchase_value')
            ->selectRaw('COALESCE(SUM(medicine_quantity * medicine_unit_selling_price), 0) as total_selling_value')
            ->first();

        $items = $query->paginate((int) $request->get('per_page', 20))->withQueryString();

        return Inertia::render('Backend/PharmacyStockReport/Index', [
            'pageTitle' => 'Pharmacy Stock Report',
            'items' => $items,
            'summary' => [
                'total_qty' => (float) ($summary->total_qty ?? 0),
                'total_purchase_value' => (float) ($summary->total_purchase_value ?? 0),
                'total_selling_value' => (float) ($summary->total_selling_value ?? 0),
            ],
            'filters' => $request->only(['name', 'status', 'per_page']),
        ]);
    }
}
