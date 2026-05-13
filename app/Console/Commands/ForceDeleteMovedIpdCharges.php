<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IpdRoomRentCharge;
use App\Models\IpdBedCharge;
use Illuminate\Support\Facades\DB;

class ForceDeleteMovedIpdCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipd:force-delete-moved-charges {ipdId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete soft-deleted Room Rent and Bed charges (optionally for a single IPD)';

    public function handle()
    {
        $ipdId = $this->argument('ipdId');

        $this->info('Starting permanent deletion of soft-deleted charges' . ($ipdId ? " for IPD#{$ipdId}" : ' for all IPDs'));

        DB::beginTransaction();
        try {
            $roomQuery = IpdRoomRentCharge::onlyTrashed();
            $bedQuery = IpdBedCharge::onlyTrashed();

            if ($ipdId) {
                $roomQuery->where('ipd_patient_id', $ipdId);
                $bedQuery->where('ipd_patient_id', $ipdId);
            }

            $roomRents = $roomQuery->get();
            $bedCharges = $bedQuery->get();

            $this->info('Found room rent soft-deleted rows: ' . $roomRents->count());
            foreach ($roomRents as $r) {
                try {
                    $this->line("- permanently deleting RoomRent id={$r->id} ipd={$r->ipd_patient_id}");
                    $r->forceDelete();
                } catch (\Throwable $e) {
                    $this->error("Failed to permanently delete RoomRent id={$r->id}: " . $e->getMessage());
                }
            }

            $this->info('Found bed charge soft-deleted rows: ' . $bedCharges->count());
            foreach ($bedCharges as $b) {
                try {
                    $this->line("- permanently deleting BedCharge id={$b->id} ipd={$b->ipd_patient_id}");
                    $b->forceDelete();
                } catch (\Throwable $e) {
                    $this->error("Failed to permanently delete BedCharge id={$b->id}: " . $e->getMessage());
                }
            }

            DB::commit();
            $this->info('Permanent deletion completed.');
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Operation failed: ' . $e->getMessage());
            return 1;
        }
    }
}
