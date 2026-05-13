<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExchangeRate;
use App\Models\Currency;
use Carbon\Carbon;

class ExchangeRateSeeder extends Seeder
{
    public function run()
    {
        $bdt = Currency::where('code', 'BDT')->first();
        $usd = Currency::where('code', 'USD')->first();
        $eur = Currency::where('code', 'EUR')->first();

        $today = Carbon::today()->toDateString();

        if ($bdt && $usd) {
            ExchangeRate::updateOrCreate([
                'from_currency_id' => $bdt->id,
                'to_currency_id' => $usd->id,
                'date' => $today,
            ], [
                'rate' => 0.0118,
            ]);
        }

        if ($bdt && $eur) {
            ExchangeRate::updateOrCreate([
                'from_currency_id' => $bdt->id,
                'to_currency_id' => $eur->id,
                'date' => $today,
            ], [
                'rate' => 0.0106,
            ]);
        }
    }
}
