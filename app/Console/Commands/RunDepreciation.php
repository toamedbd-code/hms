<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FixedAsset;
use Carbon\Carbon;

class RunDepreciation extends Command
{
    protected $signature = 'assets:depreciate';
    protected $description = 'Run monthly depreciation for fixed assets (straight line)';

    public function handle()
    {
        $this->info('Running depreciation...');

        $assets = FixedAsset::where('status', 'active')->get();
        foreach ($assets as $asset) {
            $monthly = $asset->calculateStraightLineMonthlyDepreciation();
            $asset->accumulated_depreciation += $monthly;
            $asset->net_book_value = max(0, $asset->cost - $asset->accumulated_depreciation);
            $asset->save();
            $this->info("Asset {$asset->id} depreciated by {$monthly}");
        }

        $this->info('Depreciation complete.');
        return 0;
    }
}
