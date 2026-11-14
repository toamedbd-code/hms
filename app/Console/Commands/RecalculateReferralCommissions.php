<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\Referral;
use Illuminate\Console\Command;

class RecalculateReferralCommissions extends Command
{
    protected $signature = 'referral:recompute-commissions {--dry-run : Show recalculated values without updating the database} {--id=* : Only recompute specific referral IDs}';

    protected $description = 'Sync referral commission totals with the saved billing invoice commissions.';

    public function handle(): int
    {
        $query = Referral::query()
            ->when($this->option('id'), function ($builder, $ids) {
                $builder->whereIn('id', array_map('intval', $ids));
            })
            ->with(['billing', 'payee']);

        $count = $query->count();
        $this->info("Found {$count} referral(s) to review.");

        $updated = 0;
        $skipped = 0;

        foreach ($query->orderBy('id')->cursor() as $referral) {
            $billing = $referral->billing;
            $payee = $referral->payee;

            if (!$billing || !$payee) {
                $skipped++;
                $this->warn(sprintf('Skipping referral #%d: missing billing or payee.', $referral->id));
                continue;
            }

            $total = round((float) ($billing->commission_total ?? 0), 2);
            $breakdown = $referral->category_commissions ?? [];
            $targetAmount = (float) ($billing->payable_amount ?? $billing->total ?? 0);

            $this->line(sprintf(
                'Referral #%d -> invoice commission: %.2f | current referral total: %.2f | billing: %s',
                $referral->id,
                $total,
                (float) ($referral->total_commission_amount ?? 0),
                $billing->bill_number ?? 'N/A'
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            $referral->update([
                'total_commission_amount' => $total,
                'category_commissions' => $breakdown,
                'total_bill_amount' => round((float) $targetAmount, 2),
            ]);

            if ($billing->exists) {
                $billing->update([
                    'commission_total' => $total,
                    'physyst_amt' => $total,
                ]);
            }

            $updated++;
        }

        $this->info(sprintf('Completed. Updated %d referral(s). Skipped %d.', $updated, $skipped));

        return self::SUCCESS;
    }
}
