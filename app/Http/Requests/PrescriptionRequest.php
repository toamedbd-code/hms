<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'nullable|exists:admins,id',
            'doctor_designation' => 'nullable|string|max:2000',
            'nibp' => 'nullable|string|max:255',
            'weight' => 'nullable|string|max:255',
            'pulse' => 'nullable|string|max:255',
            'temperature' => 'nullable|string|max:255',
            'complaints' => 'nullable|string',
            'examinations' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'advice' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'doctor_signature' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'doctor_seal' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'medicines' => 'nullable|array',
            'medicines.*.medicine_name' => 'nullable|string|max:255',
            'medicines.*.generic_name' => 'nullable|string|max:255',
            'medicines.*.dose' => 'nullable|string|max:255',
            'medicines.*.frequency' => 'nullable|string|max:255',
            'medicines.*.duration' => 'nullable|string|max:255',
            'medicines.*.instructions' => 'nullable|string|max:255',
            'tests' => 'nullable|array',
            'tests.*' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $seen = [];
            $medicines = $this->input('medicines', []);
            if (!is_array($medicines)) {
                return;
            }

            foreach ($medicines as $index => $medicine) {
                if (!is_array($medicine)) {
                    continue;
                }
                $name = trim((string) ($medicine['medicine_name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $normalizedName = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? $name);
                if (isset($seen[$normalizedName])) {
                    $validator->errors()->add(
                        "medicines.$index.medicine_name",
                        "The medicine \"{$name}\" has already been added."
                    );
                    continue;
                }

                $seen[$normalizedName] = true;
            }
        });
    }
}
