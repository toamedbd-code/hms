<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class CurrencyService
{
    /**
     * Convert amount from one currency to another using the latest available rate on or before the date.
     */
    public function convert(float $amount, string $fromCode, string $toCode, ?string $date = null): float
    {
        if ($fromCode === $toCode) {
            return $amount;
        }

        $date = $date ?: Carbon::today()->toDateString();

        $from = Currency::where('code', $fromCode)->first();
        $to = Currency::where('code', $toCode)->first();

        if (!$from || !$to) {
            throw new \InvalidArgumentException("Unknown currency code: {$fromCode} or {$toCode}");
        }

        $rate = ExchangeRate::where('from_currency_id', $from->id)
            ->where('to_currency_id', $to->id)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($rate) {
            return round($amount * (float)$rate->rate, 2);
        }

        // try reverse
        $reverse = ExchangeRate::where('from_currency_id', $to->id)
            ->where('to_currency_id', $from->id)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($reverse && (float)$reverse->rate != 0.0) {
            return round($amount / (float)$reverse->rate, 2);
        }

        throw new \RuntimeException('Exchange rate not found for conversion');
    }
}
