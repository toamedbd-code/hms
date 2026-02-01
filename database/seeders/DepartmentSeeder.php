<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            Department::create($value);
        }
    }

    private function datas()
    {
        return [

            [
                'name' => 'Test Department 1',
                'created_at' => now(),
            ],
            [
                'name' => 'Test Department 2',
                'created_at' => now(),
            ],

        ];
    }
}
