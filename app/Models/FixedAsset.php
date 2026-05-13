<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $table = 'fixed_assets';
    protected $fillable = [
        'asset_tag', 'name', 'company_id', 'purchase_date', 'cost', 'salvage_value',
        'useful_life_months', 'depreciation_method', 'accumulated_depreciation',
        'net_book_value', 'location', 'status', 'disposed_at'
    ];

    public function calculateStraightLineMonthlyDepreciation(): float
    {
        if (!$this->useful_life_months || $this->useful_life_months <= 0) {
            return 0.0;
        }

        $depreciable = (float)$this->cost - (float)$this->salvage_value;
        return $depreciable / (float)$this->useful_life_months;
    }
}
