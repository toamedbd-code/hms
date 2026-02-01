<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admin = Admin::create([

            'first_name' => 'Toamed ',
            'last_name' => 'Developer ',
            'email' => 'admin@gmail.com',
            'phone' => '01612423280',
            'password' => 'asdasd',
            'role_id' => 1,
            'photo' => null,
            'address' => 'Toamed Head Office',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRolePermissions = [
            'Admin' => [
                Permission::pluck('name'),
            ]
        ];

        $admin->assignRole('Admin');

        foreach ($adminRolePermissions as $role => $permissions) {
            $roleInfo = Role::where('name', $role)->first();
            if ($roleInfo) {
                $roleInfo->syncPermissions($permissions);
            }
        }
        
        $doctor = Admin::create([

            'first_name' => 'doctor ',
            'last_name' => 'test ',
            'email' => 'doctor@gmail.com',
            'phone' => '01612423280',
            'password' => 'asdasd',
            'role_id' => 2,
            'doctor_charge' => 1000.00,
            'photo' => null,
            'address' => 'Toamed Head Office',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRolePermissions = [
            'Admin' => [
                Permission::pluck('name'),
            ]
        ];

        $doctor->assignRole('Doctor');

        foreach ($adminRolePermissions as $role => $permissions) {
            $roleInfo = Role::where('name', $role)->first();
            if ($roleInfo) {
                $roleInfo->syncPermissions($permissions);
            }
        }
    }

    protected function previousDatas()
    {

        DB::table('Admins')->insert([
            'company_id' => 1,
            'first_name' => 'Admin',
            'last_name' => 'Admin',
            'email' => 'admin@gmail.com',
            'phone' => '1234567890',
            'password' => bcrypt('asdasd'),
            'role_id' => null,
            'photo' => 'default.jpg',
            'address' => 'Address ',
            'sorting' => 1,
            'status' =>  'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $AdminCount = 10;

        for ($i = 1; $i <= $AdminCount; $i++) {
            DB::table('admins')->insert([
                'company_id' => 1,
                'first_name' => 'admin' . $i,
                'last_name' => 'Member' . $i,
                'email' => 'admin' . $i . '@example.com',
                'phone' => '1234567890',
                'password' => bcrypt('asdasd'),
                'role_id' => null,
                'photo' => 'default.jpg',
                'address' => 'Address ' . $i,
                'sorting' => $i,
                'status' =>  'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
