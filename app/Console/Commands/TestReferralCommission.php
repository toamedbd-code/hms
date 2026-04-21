<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Referral;
use App\Models\Billing;
use App\Models\ReferralPerson;
use App\Models\ReferralCategory;
use App\Models\Admin;
use App\Services\ReferralService;

class TestReferralCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:referral {--id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate referral commission payment and show related expense/ledger entries';

    public function handle()
    {
        $id = $this->option('id');

        if ($id) {
            $referral = Referral::with('billing', 'payee')->find($id);
        } else {
            $referral = Referral::whereNull('deleted_at')
                ->where('status', 'Active')
                ->whereColumn('total_commission_amount', '>', 'paid_amount')
                ->with('billing', 'payee')
                ->first();
        }

        if (!$referral) {
            $this->info('No suitable referral found — creating a temporary test referral...');

            try {
                DB::beginTransaction();

                // Ensure a referral category exists
                $category = ReferralCategory::firstOrCreate(
                    ['name' => 'Default Referral Category'],
                    ['status' => 'Active']
                );

                // Ensure an admin exists to satisfy foreign keys
                $admin = Admin::first();
                if (!$admin) {
                    $admin = Admin::create([
                        'first_name' => 'Test',
                        'last_name' => 'Admin',
                        'email' => 'test-admin@example.com',
                        'password' => 'password',
                        'status' => 'Active',
                    ]);
                }

                $payee = ReferralPerson::create([
                    'name' => 'Test Payee ' . time(),
                    'phone' => '01700000000',
                    'category_id' => $category->id,
                ]);

                $billing = Billing::create([
                    'patient_mobile' => '01711111111',
                    'gender' => 'Male',
                    'card_type' => 'Cash',
                    'pay_mode' => 'Cash',
                    'total' => 1000,
                    'payable_amount' => 1000,
                    'paid_amt' => 0,
                    'receiving_amt' => 0,
                    'due_amount' => 1000,
                    'payment_status' => 'Pending',
                    'status' => 'Active',
                    'created_by' => $admin->id,
                ]);

                $ref = Referral::create([
                    'billing_id' => $billing->id,
                    'payee_id' => $payee->id,
                    'total_commission_amount' => 100.00,
                    'paid_amount' => 0,
                    'paid_status' => 'Unpaid',
                    'date' => now()->toDateString(),
                    'total_bill_amount' => 1000,
                    'status' => 'Active',
                ]);

                DB::commit();

                $referral = Referral::with('billing', 'payee')->find($ref->id);
                $this->info('Created test referral id: ' . $referral->id . ' bill: ' . ($referral->billing->bill_number ?? 'none'));
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('Failed to create test referral: ' . $e->getMessage());
                return 1;
            }
        }

        $this->info('Found referral id: ' . $referral->id . ' bill: ' . ($referral->billing->bill_number ?? 'none'));

        try {
            app(ReferralService::class)->recordCommissionPayment($referral->id, 'paid');
            $this->info('recordCommissionPayment executed.');

            $billNumber = $referral->billing->bill_number ?? null;

            if ($billNumber) {
                $expense = DB::table('expenses')->where('bill_number', $billNumber)->first();
                $this->info('Expense: ' . json_encode($expense));

                $ledgerTx = DB::table('ledger_transactions')
                    ->where('reference_type', 'Referral')
                    ->where('reference_id', $referral->id)
                    ->orderByDesc('id')
                    ->first();

                $this->info('LedgerTransaction: ' . json_encode($ledgerTx));

                $ledgerEntries = [];
                if ($ledgerTx && isset($ledgerTx->id)) {
                    $ledgerEntries = DB::table('ledger_entries')->where('transaction_id', $ledgerTx->id)->get();
                }

                $this->info('LedgerEntries: ' . json_encode($ledgerEntries));
            } else {
                $this->info('Referral billing has no bill_number; cannot locate expense.');
            }

        } catch (\Exception $e) {
            $this->error('Error while testing referral commission: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
