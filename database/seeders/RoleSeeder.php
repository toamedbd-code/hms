<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            Role::firstOrCreate(
                ['name' => $value['name'], 'guard_name' => $value['guard_name']],
                ['created_at' => $value['created_at'] ?? now()]
            );
        }
    }

    private function datas()
    {
        return [

            [
                'name' => 'Admin',
                'guard_name' => 'admin',
                'created_at' => now(),
            ],
            [
                'name' => 'Doctor',
                'guard_name' => 'admin',
                'created_at' => now(),
            ],

        ];
    }
}
