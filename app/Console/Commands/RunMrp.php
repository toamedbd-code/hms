<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductionOrder;
use App\Models\WorkOrder;
use Carbon\Carbon;

class RunMrp extends Command
{
    protected $signature = 'mrp:run';
    protected $description = 'Simple MRP runner: release scheduled production orders and create work orders';

    public function handle()
    {
        $this->info('Starting MRP run...');

        $orders = ProductionOrder::where('status', 'planned')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', Carbon::now());
            })
            ->get();

        foreach ($orders as $order) {
            $order->status = 'released';
            $order->save();

            // create a simple work order for this production order
            WorkOrder::create([
                'production_order_id' => $order->id,
                'operation' => 'Assembly',
                'planned_start' => Carbon::now(),
                'planned_end' => Carbon::now()->addHours(2),
                'status' => 'pending',
            ]);
            $this->info("Released and created work order for PO #{$order->id}");
        }

        $this->info('MRP run complete.');
        return 0;
    }
}
