<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IpdRoomRentCharge;
use App\Models\IpdBedCharge;
use Illuminate\Support\Facades\DB;

class ListActiveIpdCharges extends Command
{
    protected $signature = 'ipd:list-active-charges';
    protected $description = 'List IPD patients that still have active (non-deleted) Room Rent or Bed charges';

    public function handle()
    {
        $this->info('Scanning for active (non-deleted) Room Rent and Bed charges...');

        $room = IpdRoomRentCharge::query()
            ->whereNull('deleted_at')
            ->select('ipd_patient_id', DB::raw('count(*) as cnt'))
            ->groupBy('ipd_patient_id')
            ->get();

        $bed = IpdBedCharge::query()
            ->whereNull('deleted_at')
            ->select('ipd_patient_id', DB::raw('count(*) as cnt'))
            ->groupBy('ipd_patient_id')
            ->get();

        $map = [];
        foreach ($room as $r) {
            $map[$r->ipd_patient_id]['room'] = $r->cnt;
        }
        foreach ($bed as $b) {
            $map[$b->ipd_patient_id]['bed'] = $b->cnt;
        }

        if (empty($map)) {
            $this->info('No active Room Rent or Bed charges found.');
            return 0;
        }

        $this->line('IPD_ID | RoomRentCount | BedChargeCount');
        foreach ($map as $ipdId => $counts) {
            $this->line(sprintf('%6s | %13s | %13s', $ipdId, $counts['room'] ?? 0, $counts['bed'] ?? 0));
        }

        return 0;
    }
}
