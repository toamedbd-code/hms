<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MedicinePurchase;
use App\Models\PurchaseItem;
use App\Models\MedicineSupplier;
use App\Models\MedicineCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class MedicinePurchaseController extends Controller
{
    public function index()
    {
        $purchases = MedicinePurchase::with('supplier')->paginate(10);
        return Inertia::render('Backend/MedicinePurchase/Index', [
            'purchases' => $purchases,
        ]);
    }

    public function create()
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $categories = MedicineCategory::where('status', 'Active')->get();
        return Inertia::render('Backend/MedicinePurchase/Form', [
            'suppliers' => $suppliers,
            'categories' => $categories,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_category_id' => 'required|exists:medicinecategories,id',
            'items.*.medicine_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_purchase_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_purchase_price'];
            }

            $purchase = MedicinePurchase::create([
                'supplier_id' => $request->supplier_id,
                'purchase_number' => 'PUR-' . strtoupper(Str::random(8)),
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'due_amount' => $totalAmount,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'medicine_purchase_id' => $purchase->id,
                    'medicine_category_id' => $item['medicine_category_id'],
                    'medicine_name' => $item['medicine_name'],
                    'quantity' => $item['quantity'],
                    'unit_purchase_price' => $item['unit_purchase_price'],
                    'total_purchase_price' => $item['quantity'] * $item['unit_purchase_price'],
                    'received_quantity' => 0,
                ]);
            }
        });

        return redirect()->route('backend.medicinepurchase.index')->with('success', 'Purchase created successfully');
    }

    public function show(MedicinePurchase $medicinepurchase)
    {
        $medicinepurchase->load('supplier', 'purchaseItems.medicineCategory');
        return Inertia::render('Backend/MedicinePurchase/Show', [
            'purchase' => $medicinepurchase,
        ]);
    }

    public function edit(MedicinePurchase $medicinepurchase)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $categories = MedicineCategory::where('status', 'Active')->get();
        $medicinepurchase->load('purchaseItems');
        return Inertia::render('Backend/MedicinePurchase/Form', [
            'purchase' => $medicinepurchase,
            'suppliers' => $suppliers,
            'categories' => $categories,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, MedicinePurchase $medicinepurchase)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_category_id' => 'required|exists:medicinecategories,id',
            'items.*.medicine_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_purchase_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $medicinepurchase) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_purchase_price'];
            }

            $medicinepurchase->update([
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'due_amount' => $totalAmount - $medicinepurchase->paid_amount,
                'notes' => $request->notes,
            ]);

            // Delete existing items and recreate
            $medicinepurchase->purchaseItems()->delete();
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'medicine_purchase_id' => $medicinepurchase->id,
                    'medicine_category_id' => $item['medicine_category_id'],
                    'medicine_name' => $item['medicine_name'],
                    'quantity' => $item['quantity'],
                    'unit_purchase_price' => $item['unit_purchase_price'],
                    'total_purchase_price' => $item['quantity'] * $item['unit_purchase_price'],
                    'received_quantity' => $item['received_quantity'] ?? 0,
                ]);
            }
        });

        return redirect()->route('backend.medicinepurchase.index')->with('success', 'Purchase updated successfully');
    }

    public function destroy(MedicinePurchase $medicinepurchase)
    {
        $medicinepurchase->delete();
        return redirect()->route('backend.medicinepurchase.index')->with('success', 'Purchase deleted successfully');
    }

    // Receive purchase items
    public function receiveItems(Request $request, MedicinePurchase $medicinepurchase)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:purchase_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $medicinepurchase) {
            foreach ($request->items as $itemData) {
                $item = PurchaseItem::find($itemData['id']);
                if ($item->medicine_purchase_id == $medicinepurchase->id) {
                    $item->update(['received_quantity' => $itemData['received_quantity']]);
                }
            }

            // Check if all items are received
            $allReceived = $medicinepurchase->purchaseItems->every(function ($item) {
                return $item->received_quantity >= $item->quantity;
            });

            if ($allReceived) {
                $medicinepurchase->update(['status' => 'received']);
            }
        });

        return redirect()->back()->with('success', 'Items received successfully');
    }
}
