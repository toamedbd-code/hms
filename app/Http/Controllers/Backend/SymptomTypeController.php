<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SymptomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\SystemTrait;
use Exception;

class SymptomTypeController extends Controller
{
    use SystemTrait;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:symptom_types,name',
        ]);

        DB::beginTransaction();
        try {
            $symptomType = SymptomType::create([
                'name' => $validated['name'],
                'status' => 'Active',
            ]);

            $this->storeAdminWorkLog($symptomType->id, 'symptom_types', 'Symptom type created successfully');

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'Symptom type created successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'SymptomTypeController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }
}
