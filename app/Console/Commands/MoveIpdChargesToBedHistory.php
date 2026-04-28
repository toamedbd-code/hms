<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\IpdPatient;
use App\Models\IpdRoomRentCharge;
use App\Models\IpdBedCharge;
use App\Models\IpdNote;

class MoveIpdChargesToBedHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipd:move-charges-to-bed-history {ipdId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move existing Room Rent and Bed charges into Ipd Notes (bed_history) and delete the original charges';

    public function handle()
    {
        $ipdId = $this->argument('ipdId');

        $patientsQuery = IpdPatient::query();
        if ($ipdId) {
            $patientsQuery->where('id', $ipdId);
        }

        $patients = $patientsQuery->get();
        if ($patients->isEmpty()) {
            $this->info('No IPD patients found for the given criteria.');
            return 0;
        }

        $totalMoved = 0;

        foreach ($patients as $patient) {
            DB::beginTransaction();
            try {
                $moved = 0;

                $roomRents = IpdRoomRentCharge::query()->where('ipd_patient_id', $patient->id)->get();
                foreach ($roomRents as $r) {
                    $content = sprintf(
                        'Moved Room Rent: Start %s; End %s; Rate/day %s; Bed %s; Notes: %s',
                        $r->started_at ? $r->started_at : 'N/A',
                        $r->ended_at ? $r->ended_at : 'N/A',
                        $r->rate_per_day,
                        $r->bed?->name ?? '',
                        $r->notes ?? ''
                    );

                    IpdNote::create([
                        'ipd_patient_id' => $patient->id,
                        'type' => 'bed_history',
                        'content' => $content,
                        'created_by' => auth('admin')->id() ?? null,
                        'status' => 'Active',
                    ]);

                    $r->delete();
                    $moved++;
                }

                $bedCharges = IpdBedCharge::query()->where('ipd_patient_id', $patient->id)->get();
                foreach ($bedCharges as $b) {
                    $content = sprintf(
                        'Moved Bed Charge: Start %s; End %s; Rate/day %s; Bed %s; Notes: %s',
                        $b->started_at ? $b->started_at : 'N/A',
                        $b->ended_at ? $b->ended_at : 'N/A',
                        $b->rate_per_day,
                        $b->bed?->name ?? '',
                        $b->notes ?? ''
                    );

                    IpdNote::create([
                        'ipd_patient_id' => $patient->id,
                        'type' => 'bed_history',
                        'content' => $content,
                        'created_by' => auth('admin')->id() ?? null,
                        'status' => 'Active',
                    ]);

                    $b->delete();
                    $moved++;
                }

                DB::commit();

                $this->info("IPD#{$patient->id}: moved {$moved} charge(s) to bed_history.");
                $totalMoved += $moved;
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("IPD#{$patient->id}: failed to move charges: " . $e->getMessage());
            }
        }

        $this->info("Done. Total moved: {$totalMoved}");

        return 0;
    }
}
