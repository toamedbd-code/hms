<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class CurrencyController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/Currency/Index', [
            'pageTitle' => fn () => 'Currencies',
            'datas' => fn () => Currency::query()->paginate(request()->numOfData ?? 10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/Currency/Form', ['pageTitle' => fn () => 'Create Currency']);
    }

    public function store(CurrencyRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            Currency::create($data);
            DB::commit();
            return redirect()->route('backend.currency.index')->with('successMessage', 'Currency created');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to create currency');
        }
    }

    public function edit(Currency $currency)
    {
        return Inertia::render('Backend/Currency/Form', [
            'pageTitle' => fn () => 'Edit Currency',
            'currency' => $currency,
            'id' => $currency->id,
        ]);
    }

    public function update(CurrencyRequest $request, Currency $currency)
    {
        DB::beginTransaction();
        try {
            $currency->update($request->validated());
            DB::commit();
            return redirect()->route('backend.currency.index')->with('successMessage', 'Currency updated');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to update currency');
        }
    }

    public function destroy(Currency $currency)
    {
        try {
            $currency->delete();
            return redirect()->route('backend.currency.index')->with('successMessage', 'Currency deleted');
        } catch (Exception $e) {
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to delete currency');
        }
    }
}
