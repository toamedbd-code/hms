<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run()
    {
        Currency::updateOrCreate(['code' => 'BDT'], ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'is_base' => true]);
        Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => false]);
        Currency::updateOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'is_base' => false]);
    }
}
