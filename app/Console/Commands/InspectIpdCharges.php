<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IpdRoomRentCharge;
use App\Models\IpdBedCharge;
use App\Models\IpdNote;

class InspectIpdCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipd:inspect-charges {ipdId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show room rent and bed charge rows for an IPD (including deleted_at)';

    public function handle()
    {
        $ipdId = $this->argument('ipdId');
        if (empty($ipdId)) {
            $this->error('ipdId required');
            return 1;
        }

        $this->info("Inspecting charges for IPD#{$ipdId}");

        $roomRents = IpdRoomRentCharge::withTrashed()->where('ipd_patient_id', $ipdId)->get();
        $bedCharges = IpdBedCharge::withTrashed()->where('ipd_patient_id', $ipdId)->get();
        $bedHistoryNotes = IpdNote::where('ipd_patient_id', $ipdId)->where('type', 'bed_history')->get();

        $this->info('Room Rent Charges: ' . $roomRents->count());
        foreach ($roomRents as $r) {
            $this->line(sprintf("- id=%s started=%s ended=%s rate=%s deleted_at=%s notes=%s", $r->id, $r->started_at, $r->ended_at, $r->rate_per_day, $r->deleted_at ?? 'NULL', $r->notes ?? ''));
        }

        $this->info('Bed Charges: ' . $bedCharges->count());
        foreach ($bedCharges as $b) {
            $this->line(sprintf("- id=%s started=%s ended=%s rate=%s deleted_at=%s notes=%s", $b->id, $b->started_at, $b->ended_at, $b->rate_per_day, $b->deleted_at ?? 'NULL', $b->notes ?? ''));
        }

        $this->info('Bed History notes: ' . $bedHistoryNotes->count());
        foreach ($bedHistoryNotes as $n) {
            $content = preg_replace('/\s+/', ' ', trim((string) ($n->content ?? '')));
            $this->line(sprintf("- id=%s created_at=%s created_by=%s content=%s", $n->id, $n->created_at, $n->created_by ?? 'NULL', strlen($content) > 200 ? substr($content,0,200).'...' : $content));
        }

        return 0;
    }
}
