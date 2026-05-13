<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillOfMaterialRequest;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class BillOfMaterialController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/Bom/Index', [
            'pageTitle' => fn () => 'Bill of Materials',
            'breadcrumbs' => fn () => [
                ['link' => null, 'title' => 'Manufacturing'],
                ['link' => route('backend.bom.index'), 'title' => 'BOM List'],
            ],
            'datas' => fn () => BillOfMaterial::query()->paginate(request()->numOfData ?? 10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/Bom/Form', [
            'pageTitle' => fn () => 'Create BOM',
            'breadcrumbs' => fn () => [
                ['link' => null, 'title' => 'Manufacturing'],
                ['link' => route('backend.bom.create'), 'title' => 'Create BOM'],
            ],
        ]);
    }

    public function store(BillOfMaterialRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $items = $data['items'] ?? [];
            unset($data['items']);

            $bom = BillOfMaterial::create($data);

            foreach ($items as $it) {
                if (empty($it['component_id'])) continue;
                BomItem::create([
                    'bom_id' => $bom->id,
                    'component_id' => $it['component_id'],
                    'quantity' => $it['quantity'] ?? 0,
                    'unit_id' => $it['unit_id'] ?? null,
                    'waste_percentage' => $it['waste_percentage'] ?? 0,
                ]);
            }

            DB::commit();
            return redirect()->route('backend.bom.index')->with('successMessage', 'BOM created successfully');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to create BOM');
        }
    }

    public function edit($id)
    {
        $bom = BillOfMaterial::with('items')->findOrFail($id);
        return Inertia::render('Backend/Bom/Form', [
            'pageTitle' => fn () => 'Edit BOM',
            'breadcrumbs' => fn () => [
                ['link' => null, 'title' => 'Manufacturing'],
                ['link' => route('backend.bom.edit', $id), 'title' => 'Edit BOM'],
            ],
            'bom' => fn () => $bom,
            'id' => fn () => $id,
        ]);
    }

    public function update(BillOfMaterialRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $items = $data['items'] ?? [];
            unset($data['items']);

            $bom = BillOfMaterial::findOrFail($id);
            $bom->fill($data);
            $bom->save();

            // replace items for simplicity
            BomItem::where('bom_id', $bom->id)->delete();
            foreach ($items as $it) {
                if (empty($it['component_id'])) continue;
                BomItem::create([
                    'bom_id' => $bom->id,
                    'component_id' => $it['component_id'],
                    'quantity' => $it['quantity'] ?? 0,
                    'unit_id' => $it['unit_id'] ?? null,
                    'waste_percentage' => $it['waste_percentage'] ?? 0,
                ]);
            }

            DB::commit();
            return redirect()->route('backend.bom.index')->with('successMessage', 'BOM updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to update BOM');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $bom = BillOfMaterial::findOrFail($id);
            $bom->delete();
            DB::commit();
            return redirect()->back()->with('successMessage', 'BOM deleted');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to delete BOM');
        }
    }
}
