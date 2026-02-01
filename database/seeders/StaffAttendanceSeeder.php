<?php

namespace Database\Seeders;

use App\Models\StaffAttendance;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class StaffAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            StaffAttendance::create($value);
        }
    }

    private function datas()
    {
        return [
            [
                'staff_id' => 1,
                'name' => 'Admin Admin',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:00:00',
                'out_time' => '17:00:00',
                'note' => 'On time',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 2,
                'name' => 'Staff First1 Staff Last1',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:15:00',
                'out_time' => '17:00:00',
                'note' => 'Late arrival',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 3,
                'name' => 'Staff First2 Staff Last2',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Absent',
                'in_time' => null,
                'out_time' => null,
                'note' => 'Sick leave',
                'status' => 'Inactive',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null, 
            ],
            [
                'staff_id' => 4,
                'name' => 'Staff First3 Staff Last3',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:00:00',
                'out_time' => '16:45:00',
                'note' => 'Left early',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 5,
                'name' => 'Staff First5 Staff Last5',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:10:00',
                'out_time' => '17:00:00',
                'note' => 'On time',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 6,
                'name' => 'Staff First6 Staff Last6',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:05:00',
                'out_time' => '17:00:00',
                'note' => 'On time',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 7,
                'name' => 'Staff First7 Staff Last7',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:20:00',
                'out_time' => '17:00:00',
                'note' => 'Late arrival',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 8,
                'name' => 'Staff First8 Staff Last8',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Absent',
                'in_time' => null,
                'out_time' => null,
                'note' => 'Personal leave',
                'status' => 'Inactive',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null 
            ],
            [
                'staff_id' => 9,
                'name' => 'Staff First9 Staff Last9',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:00:00',
                'out_time' => '17:00:00',
                'note' => 'On time',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
            [
                'staff_id' => 10,
                'name' => 'Staff First10 Staff Last10',
                'attendance_date' => '2024-10-01',
                'attendance_status' => 'Present',
                'in_time' => '09:05:00',
                'out_time' => '17:00:00',
                'note' => 'On time',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
        ];
    }
}
