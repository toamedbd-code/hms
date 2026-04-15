<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert a test attendance device (identifier + secret)
        $deviceData = [
            'name' => 'Test Device 1',
            'identifier' => 'test-device-1',
            'secret' => 'DEVICE_SECRET',
            'description' => 'Seeded test device for local testing',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!Schema::hasColumn('attendance_devices', 'description')) {
            unset($deviceData['description']);
        }

        DB::table('attendance_devices')->updateOrInsert(
            ['identifier' => 'test-device-1'],
            $deviceData
        );

        // Insert a default attendance shift. Table may be per-employee or global.
        if (Schema::hasColumn('attendance_shifts', 'name')) {
            DB::table('attendance_shifts')->updateOrInsert(
                ['name' => 'Default Shift'],
                [
                    'name' => 'Default Shift',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } else {
            DB::table('attendance_shifts')->updateOrInsert(
                ['employee_code' => '__default__'],
                [
                    'employee_code' => '__default__',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
