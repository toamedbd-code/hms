<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\MedicineSupplier;
use App\Models\MedicineInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ProductReturnController extends Controller
{
    public function index()
    {
        $returns = ProductReturn::with('supplier')->paginate(10);
        return Inertia::render('Backend/ProductReturn/Index', [
            'returns' => $returns,
        ]);
    }

    public function create()
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $medicines = MedicineInventory::where('status', 'Active')->get();
        return Inertia::render('Backend/ProductReturn/Form', [
            'suppliers' => $suppliers,
            'medicines' => $medicines,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_type' => 'required|in:customer,supplier',
            'supplier_id' => 'nullable|exists:medicinesuppliers,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_inventory_id' => 'required|exists:medicine_inventories,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good,damaged,expired',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $return = ProductReturn::create([
                'return_number' => 'RET-' . strtoupper(Str::random(8)),
                'return_type' => $request->return_type,
                'supplier_id' => $request->supplier_id,
                'return_date' => $request->return_date,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                ReturnItem::create([
                    'product_return_id' => $return->id,
                    'medicine_inventory_id' => $item['medicine_inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['quantity'] * $item['unit_price'],
                    'condition' => $item['condition'],
                ]);

                // Update inventory quantity
                $medicine = MedicineInventory::find($item['medicine_inventory_id']);
                $medicine->decrement('medicine_quantity', $item['quantity']);
            }
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return created successfully');
    }

    public function show(ProductReturn $productreturn)
    {
        $productreturn->load('supplier', 'returnItems.medicineInventory');
        return Inertia::render('Backend/ProductReturn/Show', [
            'return' => $productreturn,
        ]);
    }

    public function edit(ProductReturn $productreturn)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $medicines = MedicineInventory::where('status', 'Active')->get();
        $productreturn->load('returnItems');
        return Inertia::render('Backend/ProductReturn/Form', [
            'return' => $productreturn,
            'suppliers' => $suppliers,
            'medicines' => $medicines,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, ProductReturn $productreturn)
    {
        $request->validate([
            'return_type' => 'required|in:customer,supplier',
            'supplier_id' => 'nullable|exists:medicinesuppliers,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_inventory_id' => 'required|exists:medicine_inventories,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good,damaged,expired',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $productreturn) {
            // Restore previous quantities
            foreach ($productreturn->returnItems as $oldItem) {
                $medicine = MedicineInventory::find($oldItem->medicine_inventory_id);
                $medicine->increment('medicine_quantity', $oldItem->quantity);
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $productreturn->update([
                'return_type' => $request->return_type,
                'supplier_id' => $request->supplier_id,
                'return_date' => $request->return_date,
                'total_amount' => $totalAmount,
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]);

            // Delete existing items and recreate
            $productreturn->returnItems()->delete();
            foreach ($request->items as $item) {
                ReturnItem::create([
                    'product_return_id' => $productreturn->id,
                    'medicine_inventory_id' => $item['medicine_inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['quantity'] * $item['unit_price'],
                    'condition' => $item['condition'],
                ]);

                // Update inventory quantity
                $medicine = MedicineInventory::find($item['medicine_inventory_id']);
                $medicine->decrement('medicine_quantity', $item['quantity']);
            }
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return updated successfully');
    }

    public function destroy(ProductReturn $productreturn)
    {
        DB::transaction(function () use ($productreturn) {
            // Restore quantities
            foreach ($productreturn->returnItems as $item) {
                $medicine = MedicineInventory::find($item->medicine_inventory_id);
                $medicine->increment('medicine_quantity', $item->quantity);
            }
            $productreturn->delete();
        });

        return redirect()->route('backend.productreturn.index')->with('success', 'Return deleted successfully');
    }

    // Approve return
    public function approve(ProductReturn $productreturn)
    {
        $productreturn->update(['status' => 'approved']);
        return redirect()->back()->with('success', 'Return approved successfully');
    }

    // Process return
    public function process(ProductReturn $productreturn)
    {
        $productreturn->update(['status' => 'processed']);
        return redirect()->back()->with('success', 'Return processed successfully');
    }
}
