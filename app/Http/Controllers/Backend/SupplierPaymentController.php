<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SupplierPayment;
use App\Models\MedicineSupplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    public function index()
    {
        $payments = SupplierPayment::with('supplier')->paginate(10);
        return Inertia::render('Backend/SupplierPayment/Index', [
            'payments' => $payments,
        ]);
    }

    public function create()
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        return Inertia::render('Backend/SupplierPayment/Form', [
            'suppliers' => $suppliers,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:full,partial',
            'notes' => 'nullable|string',
        ]);

        $paidAmount = $request->paid_amount ?? 0;
        $dueAmount = $request->total_amount - $paidAmount;
        $status = $dueAmount > 0 ? 'pending' : 'paid';

        SupplierPayment::create([
            'supplier_id' => $request->supplier_id,
            'total_amount' => $request->total_amount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_date' => $request->payment_date,
            'payment_type' => $request->payment_type,
            'status' => $status,
            'notes' => $request->notes,
        ]);

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment created successfully');
    }

    public function show(SupplierPayment $supplierpayment)
    {
        $supplierpayment->load('supplier');
        return Inertia::render('Backend/SupplierPayment/Show', [
            'payment' => $supplierpayment,
        ]);
    }

    public function edit(SupplierPayment $supplierpayment)
    {
        $suppliers = MedicineSupplier::where('status', 'Active')->get();
        return Inertia::render('Backend/SupplierPayment/Form', [
            'payment' => $supplierpayment,
            'suppliers' => $suppliers,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, SupplierPayment $supplierpayment)
    {
        $request->validate([
            'supplier_id' => 'required|exists:medicinesuppliers,id',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:full,partial',
            'notes' => 'nullable|string',
        ]);

        $paidAmount = $request->paid_amount ?? 0;
        $dueAmount = $request->total_amount - $paidAmount;
        $status = $dueAmount > 0 ? 'pending' : 'paid';

        $supplierpayment->update([
            'supplier_id' => $request->supplier_id,
            'total_amount' => $request->total_amount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_date' => $request->payment_date,
            'payment_type' => $request->payment_type,
            'status' => $status,
            'notes' => $request->notes,
        ]);

        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment updated successfully');
    }

    public function destroy(SupplierPayment $supplierpayment)
    {
        $supplierpayment->delete();
        return redirect()->route('backend.supplierpayment.index')->with('success', 'Payment deleted successfully');
    }

    // Partial payment method
    public function addPartialPayment(Request $request, SupplierPayment $supplierpayment)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0|max:' . $supplierpayment->due_amount,
        ]);

        DB::transaction(function () use ($request, $supplierpayment) {
            $supplierpayment->paid_amount += $request->amount;
            $supplierpayment->due_amount -= $request->amount;
            $supplierpayment->status = $supplierpayment->due_amount > 0 ? 'pending' : 'paid';
            $supplierpayment->save();
        });

        return redirect()->back()->with('success', 'Partial payment added successfully');
    }

    // Stock and Due Report
    public function stockDueReport()
    {
        $report = MedicineSupplier::with(['supplierPayments' => function ($query) {
            $query->where('status', '!=', 'paid');
        }])->get()->map(function ($supplier) {
            $totalDue = $supplier->supplierPayments->sum('due_amount');
            $stockValue = $supplier->medicines->sum(function ($medicine) {
                return $medicine->medicine_unit_purchase_price * $medicine->medicine_quantity;
            });
            return [
                'supplier_name' => $supplier->name,
                'total_due' => $totalDue,
                'stock_value' => $stockValue,
                'medicines' => $supplier->medicines->map(function ($medicine) {
                    return [
                        'name' => $medicine->medicine_name,
                        'quantity' => $medicine->medicine_quantity,
                        'unit_price' => $medicine->medicine_unit_purchase_price,
                        'total_value' => $medicine->medicine_unit_purchase_price * $medicine->medicine_quantity,
                    ];
                }),
            ];
        });

        return Inertia::render('Backend/SupplierPayment/StockDueReport', [
            'report' => $report,
        ]);
    }
}
