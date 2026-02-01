<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            Designation::create($value);
        }
    }

    private function datas()
    {
        return [

            [
                'name' => 'Test Designation 1',
                'created_at' => now(),
            ],
            [
                'name' => 'Test Designation 2',
                'created_at' => now(),
            ],

        ];
    }
}
