<?php

namespace App\Console\Commands;

use App\Models\Billing;
use Illuminate\Console\Command;

class RecomputeRefundAmounts extends Command
{
    protected $signature = 'billing:recompute-refunds {--dry-run : Show rows without updating}';

    protected $description = 'Recompute refund amounts for active bills based on paid and payable amounts.';

    public function handle()
    {
        $query = Billing::query()
            ->where('status', 'Active')
            ->whereNotNull('id')
            ->where(function ($q) {
                $q->where('return_amt', '>', 0)
                    ->orWhere(function ($sub) {
                        $sub->whereColumn('paid_amt', '>', 'payable_amount')
                            ->where(function ($nested) {
                                $nested->whereNull('return_amt')
                                    ->orWhere('return_amt', '<=', 0);
                            });
                    });
            });

        $count = (clone $query)->count();
        $this->info("Found {$count} bill(s) with refund eligibility.");

        if ($this->option('dry-run')) {
            foreach ((clone $query)->orderBy('id')->get(['id', 'bill_number', 'paid_amt', 'payable_amount', 'return_amt']) as $billing) {
                $effective = $billing->getEffectiveRefundAmount();
                $this->line(sprintf('ID=%d Bill=%s current_return=%s effective_return=%s', $billing->id, $billing->bill_number, $billing->return_amt, $effective));
            }

            return Command::SUCCESS;
        }

        $updated = 0;
        foreach ((clone $query)->orderBy('id')->cursor() as $billing) {
            $effective = $billing->getEffectiveRefundAmount();
            $billing->return_amt = round($effective, 2);
            $billing->saveQuietly();
            $updated++;
        }

        $this->info("Updated {$updated} bill(s).");

        return Command::SUCCESS;
    }
}
