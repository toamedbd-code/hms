<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MedicinePurchase;
use App\Models\PurchaseItem;
use App\Models\MedicineSupplier;
use App\Models\MedicineCategory;
use App\Models\MedicineInventory;
use App\Models\SupplierPayment;
use App\Traits\SystemTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MedicinePurchaseController extends Controller
{
    use SystemTrait;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-purchase-list');
        $this->middleware('permission:medicine-purchase-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-purchase-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-purchase-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-purchase-list-status', ['only' => ['receiveItems']]);
    }

    private function generatePurchaseNumber(): string
    {
        $prefix = web_setting_prefix('pharmacy_purchase_no_prefix', 'PHPN');

        $lastPurchaseNumber = (string) MedicinePurchase::query()
            ->where('purchase_number', 'like', $prefix . '%')
            ->latest('id')
            ->value('purchase_number');

        $lastNumber = 0;
        if ($lastPurchaseNumber !== '' && preg_match('/(\d+)$/', $lastPurchaseNumber, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return $prefix . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $purchases = MedicinePurchase::query()
            ->with('supplier')
            ->addSelect([
                'sell_total_amount' => PurchaseItem::query()
                    ->selectRaw('COALESCE(SUM(quantity * unit_selling_price), 0)')
                    ->whereColumn('medicine_purchase_id', 'medicine_purchases.id'),
            ])
            ->paginate(10);

        return Inertia::render('Backend/MedicinePurchase/Index', [
            'purchases' => $purchases,
        ]);
    }

    public function create()
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        $categories = MedicineCategory::where('status', 'Active')->get();
        $medicines = $this->getMedicineOptions();

        return Inertia::render('Backend/MedicinePurchase/Form', [
            'suppliers' => $suppliers,
            'categories' => $categories,
            'medicines' => $medicines,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'purchase_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'initial_paid_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.medicine_category_id' => 'required|exists:medicinecategories,id',
            'items.*.medicine_name' => 'required|string',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_purchase_price' => 'required|numeric|min:0',
            'items.*.unit_selling_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->validateItemsAgainstInventory((int) $request->supplier_id, (array) $request->items);

        $purchase = DB::transaction(function () use ($request) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $gross = (float) $item['quantity'] * (float) $item['unit_purchase_price'];
                $discount = (float) ($item['discount'] ?? 0);

                if ($discount > $gross) {
                    throw ValidationException::withMessages([
                        'items' => 'Discount can not be greater than item total.',
                    ]);
                }

                $totalAmount += max(0, $gross - $discount);
            }

            $paidAmount = (float) ($request->initial_paid_amount ?? 0);
            if ($paidAmount > $totalAmount) {
                throw ValidationException::withMessages([
                    'initial_paid_amount' => 'Initial payment can not be greater than total amount.',
                ]);
            }

            $dueAmount = max(0, $totalAmount - $paidAmount);

            $purchase = MedicinePurchase::create([
                'supplier_id' => $request->supplier_id,
                'purchase_number' => $this->generatePurchaseNumber(),
                'purchase_date' => $request->purchase_date,
                'invoice_number' => $request->invoice_number,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'medicine_purchase_id' => $purchase->id,
                    'medicine_category_id' => $item['medicine_category_id'],
                    'medicine_name' => $item['medicine_name'],
                    'batch_no' => $item['batch_no'],
                    'expiry_date' => $item['expiry_date'],
                    'quantity' => $item['quantity'],
                    'unit_purchase_price' => $item['unit_purchase_price'],
                    'unit_selling_price' => $item['unit_selling_price'],
                    'discount' => $item['discount'] ?? 0,
                    'total_purchase_price' => max(0, ($item['quantity'] * $item['unit_purchase_price']) - ($item['discount'] ?? 0)),
                    'received_quantity' => 0,
                ]);
            }

            $this->upsertLinkedSupplierPayment($purchase, (string) $request->purchase_date);

            return $purchase;
        });

        $this->storeAdminWorkLog(
            $purchase->id,
            'medicine_purchases',
            'Medicine purchase created: ' . $purchase->purchase_number
        );

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
        $medicines = $this->getMedicineOptions();
        $medicinepurchase->load('purchaseItems');

        return Inertia::render('Backend/MedicinePurchase/Form', [
            'purchase' => $medicinepurchase,
            'suppliers' => $suppliers,
            'categories' => $categories,
            'medicines' => $medicines,
            'isEdit' => true,
        ]);
    }

    private function getMedicineOptions()
    {
        return MedicineInventory::query()
            ->select([
                'medicine_category_id',
                'medicine_name',
                DB::raw('SUM(medicine_quantity) as current_stock'),
                DB::raw('AVG(medicine_unit_purchase_price) as unit_purchase_price'),
                DB::raw('AVG(medicine_unit_selling_price) as unit_selling_price'),
            ])
            ->groupBy('medicine_category_id', 'medicine_name')
            ->orderBy('medicine_name')
            ->get();
    }

    public function update(Request $request, MedicinePurchase $medicinepurchase)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'purchase_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.medicine_category_id' => 'required|exists:medicinecategories,id',
            'items.*.medicine_name' => 'required|string',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_purchase_price' => 'required|numeric|min:0',
            'items.*.unit_selling_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->validateItemsAgainstInventory((int) $request->supplier_id, (array) $request->items);

        DB::transaction(function () use ($request, $medicinepurchase) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $gross = (float) $item['quantity'] * (float) $item['unit_purchase_price'];
                $discount = (float) ($item['discount'] ?? 0);

                if ($discount > $gross) {
                    throw ValidationException::withMessages([
                        'items' => 'Discount can not be greater than item total.',
                    ]);
                }

                $totalAmount += max(0, $gross - $discount);
            }

            $medicinepurchase->update([
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'invoice_number' => $request->invoice_number,
                'total_amount' => $totalAmount,
                'due_amount' => max(0, $totalAmount - (float) $medicinepurchase->paid_amount),
                'notes' => $request->notes,
            ]);

            // Delete existing items and recreate
            $medicinepurchase->purchaseItems()->delete();
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'medicine_purchase_id' => $medicinepurchase->id,
                    'medicine_category_id' => $item['medicine_category_id'],
                    'medicine_name' => $item['medicine_name'],
                    'batch_no' => $item['batch_no'],
                    'expiry_date' => $item['expiry_date'],
                    'quantity' => $item['quantity'],
                    'unit_purchase_price' => $item['unit_purchase_price'],
                    'unit_selling_price' => $item['unit_selling_price'],
                    'discount' => $item['discount'] ?? 0,
                    'total_purchase_price' => max(0, ($item['quantity'] * $item['unit_purchase_price']) - ($item['discount'] ?? 0)),
                    'received_quantity' => $item['received_quantity'] ?? 0,
                ]);
            }

            $medicinepurchase->refresh();
            $this->upsertLinkedSupplierPayment($medicinepurchase, (string) $request->purchase_date);
        });

        $this->storeAdminWorkLog(
            $medicinepurchase->id,
            'medicine_purchases',
            'Medicine purchase updated: ' . $medicinepurchase->purchase_number
        );

        return redirect()->route('backend.medicinepurchase.index')->with('success', 'Purchase updated successfully');
    }

    public function destroy(MedicinePurchase $medicinepurchase)
    {
        $purchaseNumber = (string) $medicinepurchase->purchase_number;
        $purchaseId = (int) $medicinepurchase->id;
        $medicinepurchase->delete();

        $this->storeAdminWorkLog(
            $purchaseId,
            'medicine_purchases',
            'Medicine purchase deleted: ' . $purchaseNumber
        );

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
                    $newReceivedQty = (int) $itemData['received_quantity'];
                    $oldReceivedQty = (int) $item->received_quantity;

                    if ($newReceivedQty < $oldReceivedQty) {
                        throw ValidationException::withMessages([
                            'items' => 'Received quantity can not be reduced after stock update.',
                        ]);
                    }

                    if ($newReceivedQty > (int) $item->quantity) {
                        throw ValidationException::withMessages([
                            'items' => 'Received quantity can not exceed ordered quantity.',
                        ]);
                    }

                    $delta = $newReceivedQty - $oldReceivedQty;
                    if ($delta > 0) {
                        $inventory = MedicineInventory::query()
                            ->where('supplier_id', $medicinepurchase->supplier_id)
                            ->where('medicine_category_id', $item->medicine_category_id)
                            ->where('medicine_name', $item->medicine_name)
                            ->where('status', 'Active')
                            ->orderByDesc('id')
                            ->first();

                        if ($inventory) {
                            $inventory->increment('medicine_quantity', $delta);
                            $inventory->refresh();
                            $unitPurchasePrice = (float) $item->unit_purchase_price;
                            $unitSellingPrice = (float) ($item->unit_selling_price ?? $unitPurchasePrice);
                            $updatedQty = (int) $inventory->medicine_quantity;

                            $inventory->update([
                                'medicine_unit_purchase_price' => $unitPurchasePrice,
                                'medicine_unit_selling_price' => $unitSellingPrice,
                                'medicine_total_purchase_price' => $unitPurchasePrice * $updatedQty,
                                'medicine_total_selling_price' => $unitSellingPrice * $updatedQty,
                                'expiry_date' => $item->expiry_date,
                            ]);
                        } else {
                            $qty = $delta;
                            $unitPurchasePrice = (float) $item->unit_purchase_price;
                            $unitSellingPrice = (float) ($item->unit_selling_price ?? $unitPurchasePrice);

                            MedicineInventory::create([
                                'supplier_id' => $medicinepurchase->supplier_id,
                                'medicine_category_id' => $item->medicine_category_id,
                                'medicine_name' => $item->medicine_name,
                                'medicine_unit_purchase_price' => $unitPurchasePrice,
                                'medicine_unit_selling_price' => $unitSellingPrice,
                                'medicine_total_purchase_price' => $unitPurchasePrice * $qty,
                                'medicine_total_selling_price' => $unitSellingPrice * $qty,
                                'medicine_quantity' => $qty,
                                'status' => 'Active',
                            ]);
                        }
                    }

                    $item->update(['received_quantity' => $newReceivedQty]);
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

        $this->storeAdminWorkLog(
            $medicinepurchase->id,
            'medicine_purchases',
            'Receive quantity updated for purchase: ' . $medicinepurchase->purchase_number
        );

        return redirect()->back()->with('success', 'Items received successfully');
    }

    private function validateItemsAgainstInventory(int $supplierId, array $items): void
    {
        foreach ($items as $index => $item) {
            $exists = MedicineInventory::query()
                ->where('medicine_category_id', $item['medicine_category_id'] ?? null)
                ->where('medicine_name', $item['medicine_name'] ?? '')
                ->exists();

            if (!$exists) {
                throw ValidationException::withMessages([
                    "items.$index.medicine_name" => 'Selected medicine does not match category inventory.',
                ]);
            }
        }
    }

    private function upsertLinkedSupplierPayment(MedicinePurchase $purchase, string $paymentDate): void
    {
        $totalAmount = (float) ($purchase->total_amount ?? 0);
        $paidAmount = min(max((float) ($purchase->paid_amount ?? 0), 0), $totalAmount);
        $dueAmount = max(0, $totalAmount - $paidAmount);
        $purchaseNote = 'Initial payment from purchase ' . $purchase->purchase_number;

        SupplierPayment::updateOrCreate(
            [
                'supplier_id' => $purchase->supplier_id,
                'notes' => $purchaseNote,
            ],
            [
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_date' => $paymentDate,
                'payment_type' => $dueAmount > 0 ? 'partial' : 'full',
                'status' => $dueAmount > 0 ? 'pending' : 'paid',
            ]
        );
    }
}
