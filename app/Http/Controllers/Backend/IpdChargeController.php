<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\IpdBedCharge;
use App\Models\IpdOtCharge;
use App\Models\IpdDoctorVisitCharge;
use App\Models\IpdPatient;
use App\Models\IpdRoomRentCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpdChargeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:ipd-patient-edit');
    }

    public function storeRoomRent(Request $request, $ipdPatientId)
    {
        $ipdpatient = IpdPatient::query()->findOrFail($ipdPatientId);

        $validated = $request->validate([
            'bed_id' => 'nullable|exists:beds,id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after_or_equal:started_at',
            'rate_per_day' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            IpdRoomRentCharge::query()->create([
                'ipd_patient_id' => $ipdpatient->id,
                'bed_id' => $validated['bed_id'] ?? $ipdpatient->bed_id,
                'started_at' => $this->normalizeDatetimeLocal($validated['started_at']),
                'ended_at' => $this->normalizeDatetimeLocal($validated['ended_at'] ?? null),
                'rate_per_day' => $validated['rate_per_day'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
                'status' => 'Active',
            ]);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'Room rent charge added successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroyRoomRent(Request $request, $ipdPatientId, $chargeId)
    {
        $charge = IpdRoomRentCharge::query()
            ->where('ipd_patient_id', $ipdPatientId)
            ->findOrFail($chargeId);

        $charge->delete();

        return redirect()
            ->back()
            ->with('successMessage', 'Room rent charge deleted successfully.');
    }

    public function storeBedCharge(Request $request, $ipdPatientId)
    {
        $ipdpatient = IpdPatient::query()->findOrFail($ipdPatientId);

        $validated = $request->validate([
            'bed_id' => 'nullable|exists:beds,id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after_or_equal:started_at',
            'rate_per_day' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            IpdBedCharge::query()->create([
                'ipd_patient_id' => $ipdpatient->id,
                'bed_id' => $validated['bed_id'] ?? $ipdpatient->bed_id,
                'started_at' => $this->normalizeDatetimeLocal($validated['started_at']),
                'ended_at' => $this->normalizeDatetimeLocal($validated['ended_at'] ?? null),
                'rate_per_day' => $validated['rate_per_day'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
                'status' => 'Active',
            ]);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'Bed charge added successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroyBedCharge(Request $request, $ipdPatientId, $chargeId)
    {
        $charge = IpdBedCharge::query()
            ->where('ipd_patient_id', $ipdPatientId)
            ->findOrFail($chargeId);

        $charge->delete();

        return redirect()
            ->back()
            ->with('successMessage', 'Bed charge deleted successfully.');
    }

    public function storeOtCharge(Request $request, $ipdPatientId)
    {
        $ipdpatient = IpdPatient::query()->findOrFail($ipdPatientId);

        $validated = $request->validate([
            'charge_name' => 'required|string|max:255',
            'procedure_name' => 'nullable|string|max:255',
            'performed_at' => 'required|date',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:1',
        ]);

        $unitPrice = (float) $validated['unit_price'];
        $quantity = (float) $validated['quantity'];
        $total = $unitPrice * $quantity;

        DB::beginTransaction();
        try {
            IpdOtCharge::query()->create([
                'ipd_patient_id' => $ipdpatient->id,
                'charge_id' => null,
                'charge_name' => $validated['charge_name'],
                'procedure_name' => $validated['procedure_name'] ?? null,
                'performed_at' => $this->normalizeDatetimeLocal($validated['performed_at']),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_amount' => $total,
                'created_by' => auth('admin')->id(),
                'status' => 'Active',
            ]);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'OT charge added successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroyOtCharge(Request $request, $ipdPatientId, $chargeId)
    {
        $charge = IpdOtCharge::query()
            ->where('ipd_patient_id', $ipdPatientId)
            ->findOrFail($chargeId);

        $charge->delete();

        return redirect()
            ->back()
            ->with('successMessage', 'OT charge deleted successfully.');
    }

    public function storeDoctorVisitCharge(Request $request, $ipdPatientId)
    {
        $ipdpatient = IpdPatient::query()->findOrFail($ipdPatientId);

        $validated = $request->validate([
            'doctor_id' => 'nullable|exists:admins,id',
            'doctor_name' => 'nullable|string|max:255',
            'visited_at' => 'required|date',
            'fee_per_visit' => 'required|numeric|min:0',
            'visit_count' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $unit = (float) $validated['fee_per_visit'];
        $qty = (float) $validated['visit_count'];
        $total = $unit * $qty;

        DB::beginTransaction();
        try {
            IpdDoctorVisitCharge::query()->create([
                'ipd_patient_id' => $ipdpatient->id,
                'doctor_id' => $validated['doctor_id'] ?? $ipdpatient->consultant_doctor_id,
                'doctor_name' => $validated['doctor_name'] ?? null,
                'visited_at' => $this->normalizeDatetimeLocal($validated['visited_at']),
                'fee_per_visit' => $unit,
                'visit_count' => $qty,
                'total_amount' => $total,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
                'status' => 'Active',
            ]);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'Doctor visit charge added successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroyDoctorVisitCharge(Request $request, $ipdPatientId, $chargeId)
    {
        $charge = IpdDoctorVisitCharge::query()
            ->where('ipd_patient_id', $ipdPatientId)
            ->findOrFail($chargeId);

        $charge->delete();

        return redirect()
            ->back()
            ->with('successMessage', 'Doctor visit charge deleted successfully.');
    }

    private function normalizeDatetimeLocal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // HTML input[type=datetime-local] sends: 2026-02-21T10:30
        // MySQL expects: 2026-02-21 10:30:00
        if (str_contains($value, 'T')) {
            $value = str_replace('T', ' ', $value);
        }

        return $value;
    }
}
