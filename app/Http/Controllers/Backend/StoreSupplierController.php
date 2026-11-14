<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StoreSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StoreSupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:store-suppliers-list');
        $this->middleware('permission:stock-report-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:stock-report-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:stock-report-list-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $suppliers = StoreSupplier::query()
            ->withCount('purchaseOrders')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($supplierQuery) use ($search) {
                    $supplierQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('contact_person_name', 'like', '%' . $search . '%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        return Inertia::render('Backend/StoreSupplier/Index', [
            'pageTitle' => 'Store Suppliers',
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status', 'per_page']),
            'createdSupplier' => $request->boolean('notify_opener')
                ? StoreSupplier::query()->find($request->integer('created_supplier_id'))
                : null,
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/StoreSupplier/Form', [
            'pageTitle' => 'Add Store Supplier',
            'supplier' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['status'] = 'Active';
        $supplier = StoreSupplier::query()->create($data);

        return redirect()->route('backend.storesupplier.index', [
            'notify_opener' => 1,
            'created_supplier_id' => $supplier->id,
        ])->with('successMessage', 'Store supplier created successfully.');
    }

    public function storeQuick(Request $request)
    {
        $data = $this->validatedData($request);
        $data['status'] = 'Active';
        $supplier = StoreSupplier::query()->create($data);

        return response()->json([
            'supplier' => $supplier->only(['id', 'name', 'phone', 'contact_person_name']),
        ], 201);
    }

    public function edit(StoreSupplier $storesupplier)
    {
        return Inertia::render('Backend/StoreSupplier/Form', [
            'pageTitle' => 'Edit Store Supplier',
            'supplier' => $storesupplier,
        ]);
    }

    public function update(Request $request, StoreSupplier $storesupplier)
    {
        $storesupplier->update($this->validatedData($request, true));

        return redirect()->route('backend.storesupplier.index')->with('successMessage', 'Store supplier updated successfully.');
    }

    public function destroy(StoreSupplier $storesupplier)
    {
        if ($storesupplier->purchaseOrders()->exists()) {
            return redirect()->back()->with('errorMessage', 'This supplier has purchase orders and cannot be deleted.');
        }

        DB::transaction(fn () => $storesupplier->delete());

        return redirect()->route('backend.storesupplier.index')->with('successMessage', 'Store supplier deleted successfully.');
    }

    private function validatedData(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => $updating ? 'required|in:Active,Inactive' : 'sometimes|in:Active,Inactive',
        ]);
    }
}
