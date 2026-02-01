<?php

namespace Database\Seeders;

use App\Models\Specialist;
use Illuminate\Database\Seeder;

class SpecialistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            Specialist::create($value);
        }
    }

    private function datas()
    {
        return [

            [
                'name' => 'Test Specialist 1',
                'created_at' => now(),
            ],
            [
                'name' => 'Test Specialist 2',
                'created_at' => now(),
            ],

        ];
    }
}
