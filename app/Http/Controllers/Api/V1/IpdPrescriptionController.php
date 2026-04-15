<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IpdPatient;
use App\Models\IpdPrescription;
use App\Models\IpdPrescriptionMedicine;
use App\Models\IpdPrescriptionTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpdPrescriptionController extends Controller
{
    public function show(IpdPatient $ipdpatient)
    {
        $prescription = IpdPrescription::query()
            ->where('ipd_patient_id', $ipdpatient->id)
            ->with(['medicines', 'tests'])
            ->latest()
            ->first();

        return response()->json([
            'ipd_patient_id' => $ipdpatient->id,
            'data' => $prescription,
        ]);
    }

    public function upsert(Request $request, IpdPatient $ipdpatient)
    {
        $validated = $request->validate([
            'doctor_id' => 'nullable|exists:admins,id',
            'complaints' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'advice' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'medicines' => 'nullable|array',
            'medicines.*.medicine_name' => 'nullable|string|max:255',
            'medicines.*.dose' => 'nullable|string|max:255',
            'medicines.*.frequency' => 'nullable|string|max:255',
            'medicines.*.duration' => 'nullable|string|max:255',
            'medicines.*.instructions' => 'nullable|string|max:255',
            'tests' => 'nullable|array',
            'tests.*' => 'nullable|string|max:255',
        ]);

        $medicineItems = collect($validated['medicines'] ?? [])
            ->filter(fn ($item) => trim((string) ($item['medicine_name'] ?? '')) !== '')
            ->values();

        $testItems = collect($validated['tests'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();

        if ($medicineItems->isEmpty() && $testItems->isEmpty()) {
            return response()->json([
                'message' => 'At least one medicine or one test is required.',
                'errors' => [
                    'medicines' => ['At least one medicine or one test is required.'],
                ],
            ], 422);
        }

        $doctorId = $validated['doctor_id'] ?? $ipdpatient->consultant_doctor_id;

        DB::beginTransaction();
        try {
            $existingPrescription = IpdPrescription::where('ipd_patient_id', $ipdpatient->id)
                ->latest()
                ->first();

            if ($existingPrescription) {
                $prescription = $existingPrescription;
                $prescription->doctor_id = $doctorId;
                $prescription->complaints = $validated['complaints'] ?? null;
                $prescription->diagnosis = $validated['diagnosis'] ?? null;
                $prescription->advice = $validated['advice'] ?? null;
                $prescription->follow_up_date = $validated['follow_up_date'] ?? null;
                $prescription->save();

                IpdPrescriptionMedicine::where('ipd_prescription_id', $prescription->id)->delete();
                IpdPrescriptionTest::where('ipd_prescription_id', $prescription->id)->delete();
            } else {
                $prescription = IpdPrescription::create([
                    'ipd_patient_id' => $ipdpatient->id,
                    'patient_id' => $ipdpatient->patient_id,
                    'doctor_id' => $doctorId,
                    'complaints' => $validated['complaints'] ?? null,
                    'diagnosis' => $validated['diagnosis'] ?? null,
                    'advice' => $validated['advice'] ?? null,
                    'follow_up_date' => $validated['follow_up_date'] ?? null,
                    'created_by' => $doctorId,
                    'updated_by' => $doctorId,
                ]);
            }

            foreach ($medicineItems as $item) {
                IpdPrescriptionMedicine::create([
                    'ipd_prescription_id' => $prescription->id,
                    'medicine_name' => trim((string) ($item['medicine_name'] ?? '')),
                    'dose' => trim((string) ($item['dose'] ?? '')) ?: null,
                    'frequency' => trim((string) ($item['frequency'] ?? '')) ?: null,
                    'duration' => trim((string) ($item['duration'] ?? '')) ?: null,
                    'instructions' => trim((string) ($item['instructions'] ?? '')) ?: null,
                ]);
            }

            foreach ($testItems as $testName) {
                IpdPrescriptionTest::create([
                    'ipd_prescription_id' => $prescription->id,
                    'test_name' => $testName,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Prescription saved successfully.',
                'data' => $prescription->load(['medicines', 'tests']),
            ]);
        } catch (\Throwable $err) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to save prescription.',
            ], 500);
        }
    }
}
