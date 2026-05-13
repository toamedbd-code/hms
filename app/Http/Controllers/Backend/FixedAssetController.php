<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAssetRequest;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class FixedAssetController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/FixedAsset/Index', [
            'pageTitle' => fn () => 'Fixed Assets',
            'datas' => fn () => FixedAsset::query()->paginate(request()->numOfData ?? 10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/FixedAsset/Form', ['pageTitle' => fn () => 'Create Fixed Asset']);
    }

    public function store(FixedAssetRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $asset = FixedAsset::create($data);
            DB::commit();
            return redirect()->route('backend.fixedasset.index')->with('successMessage', 'Fixed asset created');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to create fixed asset');
        }
    }

    public function edit($id)
    {
        $asset = FixedAsset::findOrFail($id);
        return Inertia::render('Backend/FixedAsset/Form', ['asset' => $asset, 'id' => $id]);
    }

    public function update(FixedAssetRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $asset = FixedAsset::findOrFail($id);
            $asset->fill($data);
            $asset->save();
            DB::commit();
            return redirect()->route('backend.fixedasset.index')->with('successMessage', 'Fixed asset updated');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to update fixed asset');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $asset = FixedAsset::findOrFail($id);
            $asset->delete();
            DB::commit();
            return redirect()->back()->with('successMessage', 'Fixed asset deleted');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to delete fixed asset');
        }
    }
}
