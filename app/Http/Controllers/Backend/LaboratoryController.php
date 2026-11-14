<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Services\LabPurchaseLedgerService;
use App\Services\LabAccountingService;
use App\Services\ModuleManagementSettings;

class LaboratoryController extends Controller
{
    public function __construct(
        private LabAccountingService $labAccountingService,
        private ModuleManagementSettings $moduleManagementSettings
    )
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:lab-management');
    }

    public function dashboard(Request $request)
    {
        $items = DB::table('lab_inventory_items')
            ->select('lab_inventory_items.*')
            ->selectSub(fn ($query) => $query->from('lab_test_inventory_items')
                ->whereColumn('lab_test_inventory_items.lab_inventory_item_id', 'lab_inventory_items.id')
                ->selectRaw('COUNT(DISTINCT lab_test_id)'), 'configured_test_count')
            ->orderBy('lab_inventory_items.name')
            ->paginate(max(1, min((int) $request->input('per_page', 20), 100)))
            ->withQueryString();

        return Inertia::render('Backend/Laboratory/Dashboard', [
            'pageTitle' => 'Lab Dashboard',
            'labManagementManaged' => $this->moduleManagementSettings->isLabManaged(),
            'items' => $items,
            'filters' => $request->only('per_page'),
            'summary' => [
                'total_items' => DB::table('lab_inventory_items')->count(),
                'active_items' => DB::table('lab_inventory_items')->where('is_active', true)->count(),
                'total_quantity' => (float) DB::table('lab_inventory_items')->where('is_active', true)->sum('current_stock'),
                'configured_tests' => DB::table('lab_test_inventory_items')->distinct('lab_test_id')->count('lab_test_id'),
                'low_stock_items' => DB::table('lab_inventory_items')
                    ->where('is_active', true)
                    ->whereColumn('current_stock', '<=', 'reorder_level')
                    ->count(),
            ],
        ]);
    }

    public function patients(Request $request)
    {
        $patients = DB::table('lab_patients')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->input('search'));
                $query->where(function ($subQuery) use ($term) {
                    $subQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('patient_no', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Backend/Laboratory/Patients', [
            'pageTitle' => 'Lab Patients',
            'patients' => $patients,
            'filters' => $request->only('search'),
        ]);
    }

    public function storePatient(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'gender' => 'nullable|in:Male,Female,Other',
            'address' => 'nullable|string|max:2000',
        ]);

        $id = DB::table('lab_patients')->insertGetId([
            ...$data,
            'patient_no' => 'LP-' . now()->format('ymd') . '-' . Str::upper(Str::random(5)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('backend.lab.patients')->with('successMessage', 'Lab patient registered: ' . DB::table('lab_patients')->where('id', $id)->value('patient_no'));
    }

    public function tests(Request $request)
    {
        return Inertia::render('Backend/Laboratory/Tests', [
            'pageTitle' => 'Lab Test Catalog',
            'tests' => DB::table('lab_tests')
                ->when($request->filled('search'), fn ($query) => $query
                        ->where(fn ($subQuery) => $subQuery
                            ->where('name', 'like', '%' . trim((string) $request->input('search')) . '%')
                            ->orWhere('test_code', 'like', '%' . trim((string) $request->input('search')) . '%')))
                ->select('lab_tests.*')
                ->selectSub(fn ($query) => $query->from('lab_test_inventory_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('lab_test_inventory_items.lab_test_id', 'lab_tests.id'), 'reagent_count')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'inventoryItems' => DB::table('lab_inventory_items')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'current_stock']),
            'filters' => $request->only('search'),
        ]);
    }

    public function storeTest(Request $request)
    {
        $data = $request->validate([
            'test_code' => 'nullable|string|max:40|unique:lab_tests,test_code',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'specimen' => 'nullable|string|max:100',
            'method' => 'nullable|string|max:150',
            'unit' => 'nullable|string|max:50',
            'reference_range' => 'nullable|string|max:150',
            'price' => 'required|numeric|min:0',
            'turnaround_hours' => 'required|integer|min:1|max:8760',
            'inventory_items' => 'nullable|array',
            'inventory_items.*.lab_inventory_item_id' => 'required|integer|distinct|exists:lab_inventory_items,id',
            'inventory_items.*.quantity_per_test' => 'required|numeric|min:0.01|max:100000',
        ]);

        DB::transaction(function () use ($data) {
            $testData = collect($data)->except('inventory_items')->all();
            $testId = DB::table('lab_tests')->insertGetId([
                ...$testData,
                'test_code' => $data['test_code'] ?: 'LT-' . Str::upper(Str::random(7)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['inventory_items'] ?? [] as $inventoryUse) {
                $inventoryItem = DB::table('lab_inventory_items')
                    ->where('id', $inventoryUse['lab_inventory_item_id'])
                    ->where('is_active', true)
                    ->exists();
                if (!$inventoryItem) {
                    throw ValidationException::withMessages(['inventory_items' => 'Selected reagent or supply is inactive.']);
                }

                DB::table('lab_test_inventory_items')->insert([
                    'lab_test_id' => $testId,
                    'lab_inventory_item_id' => $inventoryUse['lab_inventory_item_id'],
                    'quantity_per_test' => $inventoryUse['quantity_per_test'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('backend.lab.tests')->with('successMessage', 'Lab test added successfully.');
    }

    public function toggleTest(int $testId)
    {
        $test = DB::table('lab_tests')->where('id', $testId)->first();
        abort_unless($test, 404);

        DB::table('lab_tests')->where('id', $testId)->update([
            'is_active' => !$test->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('successMessage', 'Lab test status updated.');
    }

    public function orders(Request $request)
    {
        return Inertia::render('Backend/Laboratory/Orders', [
            'pageTitle' => 'Lab Orders & Worklist',
            'orders' => $this->orderListQuery($request)->paginate(20)->withQueryString(),
            'patients' => DB::table('lab_patients')->orderBy('name')->get(['id', 'patient_no', 'name', 'phone']),
            'tests' => DB::table('lab_tests')->where('is_active', true)->orderBy('name')->get(['id', 'test_code', 'name', 'specimen', 'price']),
            'staff' => DB::table('admins')->where('status', 'Active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'lab_patient_id' => 'required|integer|exists:lab_patients,id',
            'assigned_to' => 'nullable|integer|exists:admins,id',
            'notes' => 'nullable|string|max:5000',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => 'required|integer|distinct|exists:lab_tests,id',
            'tests.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $orderId = DB::transaction(function () use ($data, $request) {
            $tests = DB::table('lab_tests')
                ->whereIn('id', collect($data['tests'])->pluck('lab_test_id'))
                ->where('is_active', true)
                ->get()
                ->keyBy('id');
            if ($tests->count() !== count($data['tests'])) {
                throw ValidationException::withMessages(['tests' => 'One or more selected lab tests are inactive or invalid.']);
            }

            $total = 0;
            foreach ($data['tests'] as $line) {
                $total += (float) $tests[$line['lab_test_id']]->price * (float) $line['quantity'];
            }

            $orderId = DB::table('lab_orders')->insertGetId([
                'order_no' => 'LAB-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'lab_patient_id' => $data['lab_patient_id'],
                'status' => 'Registered',
                'ordered_at' => now(),
                'assigned_to' => $data['assigned_to'] ?? null,
                'total_amount' => round($total, 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user('admin')?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['tests'] as $line) {
                DB::table('lab_order_items')->insert([
                    'lab_order_id' => $orderId,
                    'lab_test_id' => $line['lab_test_id'],
                    'quantity' => $line['quantity'],
                    'price' => $tests[$line['lab_test_id']]->price,
                    'sample_code' => 'S-' . Str::upper(Str::random(10)),
                    'sample_status' => 'Pending',
                    'result_status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $orderId;
        });

        return redirect()->route('backend.lab.orders.show', $orderId)->with('successMessage', 'Lab order registered successfully.');
    }

    public function showOrder(int $id)
    {
        $order = DB::table('lab_orders')
            ->join('lab_patients', 'lab_patients.id', '=', 'lab_orders.lab_patient_id')
            ->leftJoin('admins', 'admins.id', '=', 'lab_orders.assigned_to')
            ->where('lab_orders.id', $id)
            ->select('lab_orders.*', 'lab_patients.patient_no', 'lab_patients.name as patient_name', 'lab_patients.phone as patient_phone', 'lab_patients.gender as patient_gender', 'lab_patients.date_of_birth', 'admins.first_name as staff_first_name', 'admins.last_name as staff_last_name')
            ->first();
        abort_unless($order, 404);

        $order->items = DB::table('lab_order_items')
            ->join('lab_tests', 'lab_tests.id', '=', 'lab_order_items.lab_test_id')
            ->leftJoin('admins as collectors', 'collectors.id', '=', 'lab_order_items.collected_by')
            ->leftJoin('admins as result_enterers', 'result_enterers.id', '=', 'lab_order_items.entered_by')
            ->leftJoin('admins as verifiers', 'verifiers.id', '=', 'lab_order_items.verified_by')
            ->where('lab_order_id', $id)
            ->select(
                'lab_order_items.*',
                'lab_tests.test_code',
                'lab_tests.name as test_name',
                'lab_tests.specimen',
                'lab_tests.method',
                'lab_tests.unit',
                'lab_tests.reference_range',
                'collectors.first_name as collector_first_name',
                'collectors.last_name as collector_last_name',
                'result_enterers.first_name as enterer_first_name',
                'result_enterers.last_name as enterer_last_name',
                'verifiers.first_name as verifier_first_name',
                'verifiers.last_name as verifier_last_name'
            )
            ->orderBy('lab_order_items.id')
            ->get();
        $order->payments = DB::table('lab_payments')->where('lab_order_id', $id)->latest('payment_date')->get();
        $order->paid_amount = (float) $order->payments->sum('amount');
        $order->due_amount = max(0, (float) $order->total_amount - $order->paid_amount);

        return Inertia::render('Backend/Laboratory/OrderShow', [
            'pageTitle' => 'Lab Order Details',
            'order' => $order,
        ]);
    }

    public function collectSample(Request $request, int $itemId)
    {
        $item = DB::table('lab_order_items')->where('id', $itemId)->first();
        abort_unless($item, 404);
        if ($item->sample_status !== 'Pending') {
            throw ValidationException::withMessages(['sample' => 'Only pending samples can be collected.']);
        }

        DB::transaction(function () use ($item, $request) {
            DB::table('lab_order_items')->where('id', $item->id)->update([
                'sample_status' => 'Collected',
                'collected_at' => now(),
                'collected_by' => $request->user('admin')?->id,
                'updated_at' => now(),
            ]);
            $this->refreshOrderStatus((int) $item->lab_order_id);
        });

        return back()->with('successMessage', 'Sample marked as collected.');
    }

    public function saveResult(Request $request, int $itemId)
    {
        $data = $request->validate(['result_text' => 'required|string|max:20000']);
        $item = DB::table('lab_order_items')->where('id', $itemId)->first();
        abort_unless($item, 404);
        if ($item->sample_status !== 'Collected' || $item->result_status === 'Verified') {
            throw ValidationException::withMessages(['result_text' => 'Collect the sample first; verified results cannot be edited.']);
        }

        DB::transaction(function () use ($item, $data, $request) {
            DB::table('lab_order_items')->where('id', $item->id)->update([
                'result_text' => $data['result_text'],
                'result_status' => 'Entered',
                'entered_by' => $request->user('admin')?->id,
                'verified_by' => null,
                'verified_at' => null,
                'updated_at' => now(),
            ]);
            $this->refreshOrderStatus((int) $item->lab_order_id);
        });

        return back()->with('successMessage', 'Result saved and sent for verification.');
    }

    public function verifyResult(Request $request, int $itemId)
    {
        DB::transaction(function () use ($itemId, $request) {
            $item = DB::table('lab_order_items')->where('id', $itemId)->lockForUpdate()->first();
            abort_unless($item, 404);
            if ($item->result_status !== 'Entered') {
                throw ValidationException::withMessages(['result' => 'Only entered results can be verified.']);
            }

            $order = DB::table('lab_orders')->where('id', $item->lab_order_id)->first();
            $test = DB::table('lab_tests')->where('id', $item->lab_test_id)->first();
            if ($this->moduleManagementSettings->isLabManaged()) {
                $inventoryUses = DB::table('lab_test_inventory_items')
                    ->where('lab_test_id', $item->lab_test_id)
                    ->orderBy('lab_inventory_item_id')
                    ->get();

                foreach ($inventoryUses as $inventoryUse) {
                    $inventoryItem = DB::table('lab_inventory_items')
                        ->where('id', $inventoryUse->lab_inventory_item_id)
                        ->lockForUpdate()
                        ->first();
                    $required = round((float) $inventoryUse->quantity_per_test * (float) $item->quantity, 2);
                    if (!$inventoryItem || !$inventoryItem->is_active || (float) $inventoryItem->current_stock < $required) {
                        throw ValidationException::withMessages([
                            'result' => 'Insufficient or inactive inventory for ' . ($inventoryItem->name ?? 'a configured reagent') . '. Restock it before verifying this result.',
                        ]);
                    }

                    DB::table('lab_inventory_items')->where('id', $inventoryItem->id)->update([
                        'current_stock' => DB::raw('current_stock - ' . $required),
                        'updated_at' => now(),
                    ]);
                    $this->recordInventoryMovement(
                        (int) $inventoryItem->id,
                        'out',
                        $required,
                        'Consumed for lab test ' . $test->test_code . ' (' . $test->name . ')',
                        $request->user('admin')?->id,
                        $order->order_no
                    );
                }
            }

            DB::table('lab_order_items')->where('id', $item->id)->update([
                'result_status' => 'Verified',
                'verified_by' => $request->user('admin')?->id,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
            $this->refreshOrderStatus((int) $item->lab_order_id);
        });

        return back()->with('successMessage', 'Result verified.');
    }

    public function storePayment(Request $request, int $orderId)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:Cash,Card,Bank,Mobile Banking,Other',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($data, $orderId, $request) {
            $order = DB::table('lab_orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404);
            $paid = (float) DB::table('lab_payments')->where('lab_order_id', $orderId)->sum('amount');
            $amount = round((float) $data['amount'], 2);
            if ($amount > (float) $order->total_amount - $paid + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Payment amount cannot exceed the remaining due.']);
            }

            $paymentId = DB::table('lab_payments')->insertGetId([
                'lab_order_id' => $orderId,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user('admin')?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->labAccountingService->recordPayment(
                $paymentId,
                $orderId,
                $amount,
                $data['payment_method'],
                $data['payment_date'],
                $request->user('admin')?->id
            );
        });

        return back()->with('successMessage', 'Lab payment recorded.');
    }

    public function suppliers()
    {
        return Inertia::render('Backend/Laboratory/Suppliers', [
            'suppliers' => DB::table('lab_suppliers')->orderBy('name')->paginate(30)->withQueryString(),
        ]);
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:40',
            'contact_person' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:190',
            'address' => 'nullable|string|max:2000',
        ]);
        $supplierId = DB::table('lab_suppliers')->insertGetId([
            ...$data,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            $supplier = DB::table('lab_suppliers')->where('id', $supplierId)->first();
            return response()->json(['supplier' => $supplier], 201);
        }

        return redirect()->route('backend.lab.suppliers')->with('successMessage', 'Lab supplier added.');
    }

    public function toggleSupplier(int $supplierId)
    {
        $supplier = DB::table('lab_suppliers')->where('id', $supplierId)->first();
        abort_unless($supplier, 404);
        DB::table('lab_suppliers')->where('id', $supplierId)->update([
            'is_active' => !$supplier->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('successMessage', 'Lab supplier status updated.');
    }

    public function purchaseOrders(Request $request)
    {
        $orders = DB::table('lab_purchase_orders')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->select('lab_purchase_orders.*', 'lab_suppliers.name as supplier_name')
            ->selectSub(fn ($query) => $query->from('lab_purchase_order_items')
                ->selectRaw('COUNT(*)')
                ->whereColumn('lab_purchase_order_items.lab_purchase_order_id', 'lab_purchase_orders.id'), 'item_count')
            ->selectSub(fn ($query) => $query->from('lab_purchase_receipts')
                ->join('lab_purchase_receipt_items', 'lab_purchase_receipt_items.lab_purchase_receipt_id', '=', 'lab_purchase_receipts.id')
                ->selectRaw('COALESCE(SUM(lab_purchase_receipt_items.line_total), 0)')
                ->whereColumn('lab_purchase_receipts.lab_purchase_order_id', 'lab_purchase_orders.id'), 'received_amount')
            ->selectSub(fn ($query) => $query->from('lab_purchase_payments')
                ->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('lab_purchase_payments.lab_purchase_order_id', 'lab_purchase_orders.id'), 'paid_amount')
            ->when($request->filled('search'), fn ($query) => $query->where(function ($searchQuery) use ($request) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $searchQuery->where('lab_purchase_orders.order_no', 'like', $term)
                    ->orWhere('lab_suppliers.name', 'like', $term);
            }))
            ->latest('lab_purchase_orders.order_date')
            ->latest('lab_purchase_orders.id')
            ->paginate(20)
            ->withQueryString();

        $receipts = DB::table('lab_purchase_receipts')
            ->join('lab_purchase_orders', 'lab_purchase_orders.id', '=', 'lab_purchase_receipts.lab_purchase_order_id')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->select('lab_purchase_receipts.*', 'lab_purchase_orders.order_no', 'lab_suppliers.name as supplier_name')
            ->latest('lab_purchase_receipts.received_date')
            ->latest('lab_purchase_receipts.id')
            ->limit(30)
            ->get()
            ->map(function ($receipt) {
                $receipt->items = DB::table('lab_purchase_receipt_items')
                    ->join('lab_purchase_order_items', 'lab_purchase_order_items.id', '=', 'lab_purchase_receipt_items.lab_purchase_order_item_id')
                    ->join('lab_inventory_items', 'lab_inventory_items.id', '=', 'lab_purchase_order_items.lab_inventory_item_id')
                    ->where('lab_purchase_receipt_items.lab_purchase_receipt_id', $receipt->id)
                    ->get([
                        'lab_inventory_items.name',
                        'lab_inventory_items.unit',
                        'lab_purchase_receipt_items.quantity',
                        'lab_purchase_receipt_items.batch_no',
                        'lab_purchase_receipt_items.expiry_date',
                    ]);
                return $receipt;
            });

        return Inertia::render('Backend/Laboratory/PurchaseOrders', [
            'orders' => $orders,
            'receipts' => $receipts,
            'suppliers' => DB::table('lab_suppliers')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => DB::table('lab_inventory_items')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit', 'current_stock']),
            'filters' => $request->only('search'),
        ]);
    }

    public function storePurchaseOrder(Request $request)
    {
        $data = $request->validate([
            'lab_supplier_id' => 'required|integer|exists:lab_suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.lab_inventory_item_id' => 'required|integer|distinct|exists:lab_inventory_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $orderId = DB::transaction(function () use ($data, $request) {
            $supplier = DB::table('lab_suppliers')->where('id', $data['lab_supplier_id'])->lockForUpdate()->first();
            if (!$supplier || !$supplier->is_active) {
                throw ValidationException::withMessages(['lab_supplier_id' => 'Choose an active Lab supplier.']);
            }

            $total = 0.0;
            $items = [];
            foreach ($data['items'] as $index => $line) {
                $item = DB::table('lab_inventory_items')->where('id', $line['lab_inventory_item_id'])->lockForUpdate()->first();
                if (!$item || !$item->is_active) {
                    throw ValidationException::withMessages(["items.{$index}.lab_inventory_item_id" => 'Choose an active Lab inventory item.']);
                }
                $quantity = round((float) $line['quantity'], 2);
                $unitCost = round((float) $line['unit_cost'], 2);
                $lineTotal = round($quantity * $unitCost, 2);
                $items[] = ['item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'line_total' => $lineTotal];
                $total += $lineTotal;
            }

            $orderNo = 'LAB-PO-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
            $now = now();
            $orderId = DB::table('lab_purchase_orders')->insertGetId([
                'order_no' => $orderNo,
                'lab_supplier_id' => $supplier->id,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'total_amount' => round($total, 2),
                'status' => 'Ordered',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user('admin')?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($items as $line) {
                DB::table('lab_purchase_order_items')->insert([
                    'lab_purchase_order_id' => $orderId,
                    'lab_inventory_item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $orderId;
        });

        return redirect()->route('backend.lab.purchase-orders')->with('successMessage', 'Lab purchase order created.');
    }

    public function createPurchaseReceipt(int $orderId)
    {
        $order = DB::table('lab_purchase_orders')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->where('lab_purchase_orders.id', $orderId)
            ->select('lab_purchase_orders.*', 'lab_suppliers.name as supplier_name')
            ->first();
        abort_unless($order, 404);

        $items = DB::table('lab_purchase_order_items')
            ->join('lab_inventory_items', 'lab_inventory_items.id', '=', 'lab_purchase_order_items.lab_inventory_item_id')
            ->where('lab_purchase_order_items.lab_purchase_order_id', $orderId)
            ->select('lab_purchase_order_items.*', 'lab_inventory_items.name', 'lab_inventory_items.unit')
            ->selectSub(fn ($query) => $query->from('lab_purchase_receipt_items')
                ->whereColumn('lab_purchase_receipt_items.lab_purchase_order_item_id', 'lab_purchase_order_items.id')
                ->selectRaw('COALESCE(SUM(quantity), 0)'), 'received_quantity')
            ->get()
            ->map(function ($item) {
                $item->remaining_quantity = max(0, round((float) $item->quantity - (float) $item->received_quantity, 2));
                return $item;
            });

        return Inertia::render('Backend/Laboratory/PurchaseReceiptForm', [
            'order' => $order,
            'items' => $items,
        ]);
    }

    public function storePurchaseReceipt(Request $request, int $orderId, LabPurchaseLedgerService $ledgerService)
    {
        $data = $request->validate([
            'received_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.lab_purchase_order_item_id' => 'required|integer|distinct|exists:lab_purchase_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($data, $orderId, $request, $ledgerService) {
            $order = DB::table('lab_purchase_orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->status === 'Cancelled') {
                throw ValidationException::withMessages(['items' => 'A cancelled order cannot be received.']);
            }

            $receiptLines = [];
            foreach ($data['items'] as $index => $line) {
                $quantity = round((float) $line['quantity'], 2);
                if ($quantity <= 0) {
                    continue;
                }
                $orderItem = DB::table('lab_purchase_order_items')
                    ->where('lab_purchase_order_id', $orderId)
                    ->where('id', $line['lab_purchase_order_item_id'])
                    ->lockForUpdate()
                    ->first();
                if (!$orderItem) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'This item does not belong to the selected purchase order.']);
                }
                $received = (float) DB::table('lab_purchase_receipt_items')
                    ->where('lab_purchase_order_item_id', $orderItem->id)
                    ->sum('quantity');
                if ($quantity > (float) $orderItem->quantity - $received + 0.0001) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'Received quantity exceeds the remaining quantity.']);
                }
                $receiptLines[] = [
                    'order_item' => $orderItem,
                    'quantity' => $quantity,
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                ];
            }
            if ($receiptLines === []) {
                throw ValidationException::withMessages(['items' => 'Enter a quantity for at least one received item.']);
            }

            $now = now();
            $receiptId = DB::table('lab_purchase_receipts')->insertGetId([
                'receipt_no' => 'LAB-GRN-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'lab_purchase_order_id' => $orderId,
                'received_date' => $data['received_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user('admin')?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $receiptAmount = 0.0;
            foreach ($receiptLines as $line) {
                $orderItem = $line['order_item'];
                $quantity = $line['quantity'];
                $lineTotal = round($quantity * (float) $orderItem->unit_cost, 2);
                $receiptAmount += $lineTotal;
                DB::table('lab_purchase_receipt_items')->insert([
                    'lab_purchase_receipt_id' => $receiptId,
                    'lab_purchase_order_item_id' => $orderItem->id,
                    'quantity' => $quantity,
                    'unit_cost' => $orderItem->unit_cost,
                    'line_total' => $lineTotal,
                    'batch_no' => $line['batch_no'],
                    'expiry_date' => $line['expiry_date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('lab_inventory_items')->where('id', $orderItem->lab_inventory_item_id)->increment('current_stock', $quantity, ['updated_at' => $now]);
                $this->recordInventoryMovement(
                    (int) $orderItem->lab_inventory_item_id,
                    'in',
                    $quantity,
                    'Received against Lab purchase order ' . $order->order_no,
                    $request->user('admin')?->id,
                    DB::table('lab_purchase_receipts')->where('id', $receiptId)->value('receipt_no'),
                    $data['received_date']
                );
            }

            $remaining = DB::table('lab_purchase_order_items')
                ->where('lab_purchase_order_id', $orderId)
                ->whereRaw('quantity > (SELECT COALESCE(SUM(lab_purchase_receipt_items.quantity), 0) FROM lab_purchase_receipt_items WHERE lab_purchase_receipt_items.lab_purchase_order_item_id = lab_purchase_order_items.id)')
                ->exists();
            DB::table('lab_purchase_orders')->where('id', $orderId)->update([
                'status' => $remaining ? 'Partially Received' : 'Received',
                'updated_at' => $now,
            ]);

            $ledgerService->recordReceipt($receiptId, $receiptAmount, $data['received_date'], $request->user('admin')?->id);
        });

        return redirect()->route('backend.lab.purchase-orders')->with('successMessage', 'Goods received and Lab inventory updated.');
    }

    public function purchasePayments(Request $request)
    {
        $payments = DB::table('lab_purchase_payments')
            ->join('lab_purchase_orders', 'lab_purchase_orders.id', '=', 'lab_purchase_payments.lab_purchase_order_id')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'lab_purchase_payments.payment_account_id')
            ->select('lab_purchase_payments.*', 'lab_purchase_orders.order_no', 'lab_suppliers.name as supplier_name', 'accounts.name as account_name')
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('lab_purchase_payments.payment_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('lab_purchase_payments.payment_date', '<=', $request->input('to_date')))
            ->latest('lab_purchase_payments.payment_date')
            ->latest('lab_purchase_payments.id')
            ->paginate(30)
            ->withQueryString();

        $orders = DB::table('lab_purchase_orders')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->whereIn('lab_purchase_orders.status', ['Partially Received', 'Received'])
            ->select('lab_purchase_orders.id', 'lab_purchase_orders.order_no', 'lab_suppliers.name as supplier_name')
            ->selectSub(fn ($query) => $query->from('lab_purchase_receipts')
                ->join('lab_purchase_receipt_items', 'lab_purchase_receipt_items.lab_purchase_receipt_id', '=', 'lab_purchase_receipts.id')
                ->selectRaw('COALESCE(SUM(lab_purchase_receipt_items.line_total), 0)')
                ->whereColumn('lab_purchase_receipts.lab_purchase_order_id', 'lab_purchase_orders.id'), 'received_amount')
            ->selectSub(fn ($query) => $query->from('lab_purchase_payments')
                ->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('lab_purchase_payments.lab_purchase_order_id', 'lab_purchase_orders.id'), 'paid_amount')
            ->get()
            ->filter(fn ($order) => (float) $order->received_amount > (float) $order->paid_amount)
            ->values();

        return Inertia::render('Backend/Laboratory/PurchasePayments', [
            'payments' => $payments,
            'outstandingOrders' => $orders,
            'filters' => $request->only('from_date', 'to_date'),
        ]);
    }

    public function createPurchasePayment(int $orderId)
    {
        $order = DB::table('lab_purchase_orders')
            ->join('lab_suppliers', 'lab_suppliers.id', '=', 'lab_purchase_orders.lab_supplier_id')
            ->where('lab_purchase_orders.id', $orderId)
            ->select('lab_purchase_orders.*', 'lab_suppliers.name as supplier_name')
            ->first();
        abort_unless($order, 404);
        $order->received_amount = (float) DB::table('lab_purchase_receipts')
            ->join('lab_purchase_receipt_items', 'lab_purchase_receipt_items.lab_purchase_receipt_id', '=', 'lab_purchase_receipts.id')
            ->where('lab_purchase_receipts.lab_purchase_order_id', $orderId)
            ->sum('lab_purchase_receipt_items.line_total');
        $order->paid_amount = (float) DB::table('lab_purchase_payments')->where('lab_purchase_order_id', $orderId)->sum('amount');
        abort_if($order->received_amount <= $order->paid_amount, 404);

        return Inertia::render('Backend/Laboratory/PurchasePaymentForm', [
            'order' => $order,
            'accounts' => DB::table('accounts')->where('is_active', true)->whereIn('code', ['CASH', 'BANK', 'MOBILE_BANK'])->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function storePurchasePayment(Request $request, int $orderId, LabPurchaseLedgerService $ledgerService)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_account_id' => 'required|integer|exists:accounts,id',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($data, $orderId, $request, $ledgerService) {
            $order = DB::table('lab_purchase_orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404);
            $received = (float) DB::table('lab_purchase_receipts')
                ->join('lab_purchase_receipt_items', 'lab_purchase_receipt_items.lab_purchase_receipt_id', '=', 'lab_purchase_receipts.id')
                ->where('lab_purchase_receipts.lab_purchase_order_id', $orderId)
                ->sum('lab_purchase_receipt_items.line_total');
            $paid = (float) DB::table('lab_purchase_payments')->where('lab_purchase_order_id', $orderId)->sum('amount');
            $amount = round((float) $data['amount'], 2);
            if ($amount > $received - $paid + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the received goods balance.']);
            }
            $account = DB::table('accounts')->where('id', $data['payment_account_id'])->where('is_active', true)->first();
            if (!$account || !in_array($account->code, ['CASH', 'BANK', 'MOBILE_BANK'], true) || strtolower((string) $account->type) !== 'asset') {
                throw ValidationException::withMessages(['payment_account_id' => 'Choose an active cash or bank account.']);
            }
            $paymentId = DB::table('lab_purchase_payments')->insertGetId([
                'lab_purchase_order_id' => $orderId,
                'payment_account_id' => $account->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user('admin')?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ledgerService->recordPayment($paymentId, $orderId, $amount, $account->id, $data['payment_date'], $request->user('admin')?->id);
        });

        return redirect()->route('backend.lab.purchase-payments')->with('successMessage', 'Lab supplier payment recorded.');
    }

    public function inventory()
    {
        return Inertia::render('Backend/Laboratory/Inventory', [
            'pageTitle' => 'Lab Inventory',
            'items' => DB::table('lab_inventory_items')->orderBy('name')->paginate(30)->withQueryString(),
            'movements' => DB::table('lab_inventory_movements')
                ->join('lab_inventory_items', 'lab_inventory_items.id', '=', 'lab_inventory_movements.lab_inventory_item_id')
                ->select('lab_inventory_movements.*', 'lab_inventory_items.name as item_name', 'lab_inventory_items.unit')
                ->latest('lab_inventory_movements.id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function storeInventoryItem(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:40',
            'reorder_level' => 'required|numeric|min:0',
            'opening_stock' => 'required|numeric|min:0',
        ]);

        $itemId = DB::transaction(function () use ($data, $request) {
            $itemId = DB::table('lab_inventory_items')->insertGetId([
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'unit' => $data['unit'],
                'current_stock' => $data['opening_stock'],
                'reorder_level' => $data['reorder_level'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ((float) $data['opening_stock'] > 0) {
                $this->recordInventoryMovement($itemId, 'in', (float) $data['opening_stock'], 'Opening stock', $request->user('admin')?->id);
            }

            return $itemId;
        });

        if ($request->expectsJson() || $request->ajax()) {
            $item = DB::table('lab_inventory_items')->where('id', $itemId)->first();
            return response()->json(['item' => $item], 201);
        }

        return redirect()->route('backend.lab.inventory')->with('successMessage', 'Lab inventory item added.');
    }

    public function adjustInventory(Request $request, int $itemId)
    {
        $data = $request->validate([
            'movement_type' => 'required|in:in,out',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'reference_no' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data, $itemId, $request) {
            $item = DB::table('lab_inventory_items')->where('id', $itemId)->lockForUpdate()->first();
            abort_unless($item && $item->is_active, 404);
            $quantity = round((float) $data['quantity'], 2);
            if ($data['movement_type'] === 'out' && (float) $item->current_stock < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient lab inventory stock.']);
            }

            DB::table('lab_inventory_items')->where('id', $itemId)->update([
                'current_stock' => DB::raw('current_stock ' . ($data['movement_type'] === 'in' ? '+' : '-') . ' ' . $quantity),
                'updated_at' => now(),
            ]);
            $this->recordInventoryMovement($itemId, $data['movement_type'], $quantity, $data['reason'], $request->user('admin')?->id, $data['reference_no'] ?? null);
        });

        return back()->with('successMessage', 'Lab inventory updated.');
    }

    private function orderListQuery(Request $request)
    {
        return DB::table('lab_orders')
            ->join('lab_patients', 'lab_patients.id', '=', 'lab_orders.lab_patient_id')
            ->leftJoin('admins', 'admins.id', '=', 'lab_orders.assigned_to')
            ->select(
                'lab_orders.id',
                'lab_orders.order_no',
                'lab_orders.status',
                'lab_orders.ordered_at',
                'lab_orders.total_amount',
                'lab_patients.patient_no',
                'lab_patients.name as patient_name',
                'admins.first_name as staff_first_name',
                'admins.last_name as staff_last_name'
            )
            ->selectSub(fn ($query) => $query->from('lab_payments')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('lab_payments.lab_order_id', 'lab_orders.id'), 'paid_amount')
            ->when($request->filled('status'), fn ($query) => $query->where('lab_orders.status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $query->where(fn ($subQuery) => $subQuery
                    ->where('lab_orders.order_no', 'like', $term)
                    ->orWhere('lab_patients.patient_no', 'like', $term)
                    ->orWhere('lab_patients.name', 'like', $term)
                    ->orWhere('lab_patients.phone', 'like', $term));
            })
            ->latest('lab_orders.id');
    }

    private function refreshOrderStatus(int $orderId): void
    {
        $counts = DB::table('lab_order_items')
            ->where('lab_order_id', $orderId)
            ->selectRaw('COUNT(*) as total, SUM(result_status = ?) as verified, SUM(sample_status = ?) as collected, SUM(result_status = ?) as entered', ['Verified', 'Collected', 'Entered'])
            ->first();
        $status = (int) $counts->total > 0 && (int) $counts->verified === (int) $counts->total
            ? 'Completed'
            : ((int) $counts->collected > 0 || (int) $counts->entered > 0 ? 'Processing' : 'Registered');
        DB::table('lab_orders')->where('id', $orderId)->update(['status' => $status, 'updated_at' => now()]);
    }

    private function recordInventoryMovement(int $itemId, string $type, float $quantity, string $reason, ?int $createdBy, ?string $reference = null, ?string $movementDate = null): void
    {
        DB::table('lab_inventory_movements')->insert([
            'lab_inventory_item_id' => $itemId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'reference_no' => $reference,
            'reason' => $reason,
            'movement_date' => $movementDate ?? today(),
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
