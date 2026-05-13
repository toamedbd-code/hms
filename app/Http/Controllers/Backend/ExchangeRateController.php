<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateRequest;
use App\Models\ExchangeRate;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class ExchangeRateController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/Currency/ExchangeIndex', [
            'pageTitle' => fn () => 'Exchange Rates',
            'datas' => fn () => ExchangeRate::with(['fromCurrency', 'toCurrency'])->paginate(request()->numOfData ?? 10),
            'currencies' => fn () => Currency::all(),
        ]);
    }

    public function store(ExchangeRateRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            ExchangeRate::create($data);
            DB::commit();
            return redirect()->route('backend.exchange-rate.index')->with('successMessage', 'Exchange rate saved');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to save exchange rate');
        }
    }

    public function destroy(ExchangeRate $exchangeRate)
    {
        try {
            $exchangeRate->delete();
            return redirect()->route('backend.exchange-rate.index')->with('successMessage', 'Exchange rate deleted');
        } catch (Exception $e) {
            report($e);
            return redirect()->back()->with('errorMessage', 'Failed to delete exchange rate');
        }
    }
}
