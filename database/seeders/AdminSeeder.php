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
        // Resolve role ids dynamically to avoid FK constraint issues
        $adminRole = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();
        $doctorRole = Role::where('name', 'Doctor')->where('guard_name', 'admin')->first();
        $developerRole = Role::where('name', 'developer')->where('guard_name', 'admin')->first();

        $admin = Admin::create([
            'first_name' => 'Toamed ',
            'last_name' => 'Developer ',
            'email' => 'admin@gmail.com',
            'phone' => '01612423280',
            'password' => 'asdasd',
            'role_id' => $adminRole?->id,
            'photo' => null,
            'address' => 'Toamed Head Office',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $allPermissions = Permission::pluck('name')->toArray();

        if ($admin) {
            $admin->assignRole('Admin');
        }

        if ($adminRole) {
            $adminRole->syncPermissions($allPermissions);
        }
        
        $doctor = Admin::create([
            'first_name' => 'doctor ',
            'last_name' => 'test ',
            'email' => 'doctor@gmail.com',
            'phone' => '01612423280',
            'password' => 'asdasd',
            'role_id' => $doctorRole?->id,
            'doctor_charge' => 1000.00,
            'photo' => null,
            'address' => 'Toamed Head Office',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($doctor) {
            $doctor->assignRole('Doctor');
        }

        // Ensure a default admin account exists so fresh databases
        // have a usable login. Email: toamedbd@gmail.com / Password: zxczxc
        try {
            $defaultEmail = 'toamedbd@gmail.com';
            if (!Admin::where('email', $defaultEmail)->exists()) {
                $default = Admin::create([
                    'first_name' => 'Toamed',
                    'last_name' => 'Admin',
                    'email' => $defaultEmail,
                    'phone' => '01700000000',
                    'password' => 'zxczxc',
                    'role_id' => $adminRole?->id,
                    'photo' => null,
                    'address' => 'Default seeded admin',
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Assign Admin and developer roles so the account has full permissions
                try {
                    $default->assignRole('Admin');
                    $default->assignRole('developer');

                    // Ensure developer role has all permissions
                    if ($developerRole) {
                        $developerRole->syncPermissions($allPermissions);
                    }
                } catch (\Exception $e) {
                    /* ignore role assignment errors */
                }
            }
        } catch (\Exception $e) {
            // ignore seeder-level failures to avoid breaking other seeds
        }

        // If you want doctors to have limited permissions, sync them here.
        // Example:
        // $doctorRole = Role::where('name', 'Doctor')->where('guard_name', 'admin')->first();
        // $doctorRole?->syncPermissions(['opd-patient-list']);
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
