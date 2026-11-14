<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'Appointment',
            'OPD',
            'IPD',
            'Pathology',
            'Radiology',
            'Ultrasound',
            'Disposable',
            'Blood Bank',
            'Ambulance',
        ];

        foreach ($categories as $name) {
            $existing = DB::table('testcategories')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();

            if ($existing) {
                DB::table('testcategories')
                    ->where('id', $existing->id)
                    ->update([
                        'parent_id' => null,
                        'status' => 'Active',
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                continue;
            }

            DB::table('testcategories')->insert([
                'parent_id' => null,
                'name' => $name,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('testcategories')
            ->whereIn('name', [
                'Appointment', 'OPD', 'IPD', 'Pathology', 'Radiology',
                'ECG', 'Ultrasound', 'Disposable', 'Blood Bank', 'Ambulance',
            ])
            ->delete();
    }
};