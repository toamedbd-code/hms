<?php

namespace App\Services;

use App\Models\IpdBedCharge;
use App\Models\IpdPatient;
use App\Models\IpdRoomRentCharge;
use Carbon\Carbon;

class IpdAutoChargeService
{
    /**
     * Ensure IPD patient has running room rent + bed charge entries for the currently selected bed.
     *
     * - On first admission: creates 1 open (ended_at = null) row per charge type.
     * - On bed change: closes the previous open row and opens a new row from $asOf.
     */
    public function syncAdmissionCharges(IpdPatient $ipdpatient, ?int $actorId = null, ?Carbon $asOf = null): void
    {
        $actorId = $actorId ?: (int) (auth('admin')->id() ?? 0);
        $asOf = $asOf ?: now();

        $ipdpatient->loadMissing(['bed.bedType']);

        $admissionAt = $this->safeCarbon($ipdpatient->admission_date) ?? $this->safeCarbon($ipdpatient->created_at) ?? $asOf;

        $bedId = $ipdpatient->bed_id;
        $bedType = $ipdpatient->bed?->bedType;

        $roomRentRate = (float) ($bedType?->room_rent_rate_per_day ?? 0);
        $bedChargeRate = (float) ($bedType?->bed_charge_rate_per_day ?? 0);

        $this->syncRunningCharge(
            modelClass: IpdRoomRentCharge::class,
            ipdpatient: $ipdpatient,
            bedId: $bedId,
            defaultStart: $admissionAt,
            defaultRatePerDay: $roomRentRate,
            actorId: $actorId,
            asOf: $asOf,
        );

        $this->syncRunningCharge(
            modelClass: IpdBedCharge::class,
            ipdpatient: $ipdpatient,
            bedId: $bedId,
            defaultStart: $admissionAt,
            defaultRatePerDay: $bedChargeRate,
            actorId: $actorId,
            asOf: $asOf,
        );
    }

    private function syncRunningCharge(
        string $modelClass,
        IpdPatient $ipdpatient,
        $bedId,
        Carbon $defaultStart,
        float $defaultRatePerDay,
        int $actorId,
        Carbon $asOf,
    ): void {
        if (empty($ipdpatient->id)) {
            return;
        }

        // Find latest open charge (ended_at is null).
        $open = $modelClass::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->first();

        if (!$open) {
            // First time create.
            $modelClass::query()->create([
                'ipd_patient_id' => $ipdpatient->id,
                'bed_id' => $bedId,
                'started_at' => $defaultStart,
                'ended_at' => null,
                'rate_per_day' => $defaultRatePerDay,
                'notes' => 'Auto',
                'created_by' => $actorId > 0 ? $actorId : null,
                'status' => 'Active',
            ]);

            return;
        }

        // If bed changed => close previous and open new.
        if (!empty($bedId) && (int) $open->bed_id !== (int) $bedId) {
            $open->ended_at = $asOf;
            $open->save();

            $modelClass::query()->create([
                'ipd_patient_id' => $ipdpatient->id,
                'bed_id' => $bedId,
                'started_at' => $asOf,
                'ended_at' => null,
                'rate_per_day' => $defaultRatePerDay,
                'notes' => 'Auto (Bed Change)',
                'created_by' => $actorId > 0 ? $actorId : null,
                'status' => 'Active',
            ]);

            return;
        }

        // If rate wasn't set before, but we now have a default rate => update the open row.
        if ((float) ($open->rate_per_day ?? 0) <= 0 && $defaultRatePerDay > 0) {
            $open->rate_per_day = $defaultRatePerDay;
            $open->save();
        }
    }

    private function safeCarbon($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $err) {
            return null;
        }
    }
}
