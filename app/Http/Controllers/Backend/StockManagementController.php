<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\MedicineInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockManagementController extends Controller
{
    public function index()
    {
        $medicines = MedicineInventory::with('medicineCategory', 'supplier')
            ->select('id', 'medicine_name', 'medicine_quantity', 'medicine_unit_purchase_price', 'medicine_unit_selling_price', 'status')
            ->paginate(10);

        return Inertia::render('Backend/StockManagement/Index', [
            'medicines' => $medicines,
        ]);
    }

    public function adjustments()
    {
        $adjustments = StockAdjustment::with('medicineInventory')->paginate(10);
        return Inertia::render('Backend/StockManagement/Adjustments', [
            'adjustments' => $adjustments,
        ]);
    }

    public function createAdjustment()
    {
        $medicines = MedicineInventory::where('status', 'Active')->get();
        return Inertia::render('Backend/StockManagement/AdjustmentForm', [
            'medicines' => $medicines,
            'isEdit' => false,
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'medicine_inventory_id' => 'required|exists:medicine_inventories,id',
            'adjustment_type' => 'required|in:increase,decrease',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'adjustment_date' => 'required|date',
        ]);

        DB::transaction(function () use ($request) {
            $adjustment = StockAdjustment::create($request->all());

            $medicine = MedicineInventory::find($request->medicine_inventory_id);
            if ($request->adjustment_type === 'increase') {
                $medicine->increment('medicine_quantity', $request->quantity);
            } else {
                $medicine->decrement('medicine_quantity', $request->quantity);
            }
        });

        return redirect()->route('backend.stock.adjustments')->with('success', 'Stock adjustment created successfully');
    }

    public function lowStockReport()
    {
        $lowStockMedicines = MedicineInventory::where('medicine_quantity', '<=', 10)
            ->where('status', 'Active')
            ->with('medicineCategory', 'supplier')
            ->get();

        return Inertia::render('Backend/StockManagement/LowStockReport', [
            'medicines' => $lowStockMedicines,
        ]);
    }

    public function stockMovementReport(Request $request)
    {
        $query = StockAdjustment::with('medicineInventory');

        if ($request->filled('medicine_id')) {
            $query->where('medicine_inventory_id', $request->medicine_id);
        }

        if ($request->filled('start_date')) {
            $query->where('adjustment_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('adjustment_date', '<=', $request->end_date);
        }

        $adjustments = $query->paginate(10);

        $medicines = MedicineInventory::where('status', 'Active')->get();

        return Inertia::render('Backend/StockManagement/StockMovementReport', [
            'adjustments' => $adjustments,
            'medicines' => $medicines,
        ]);
    }
}
