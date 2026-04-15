<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickAccessMenuPermissionSyncSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            Menu::query()->updateOrCreate(
                ['route' => 'backend.appoinment.website-inbox'],
                [
                    'parent_id' => null,
                    'name' => 'Website Inbox',
                    'icon' => 'inbox',
                    'description' => null,
                    'sorting' => 998,
                    'permission_name' => 'website-inbox',
                    'status' => 'Active',
                    'deleted_at' => null,
                ]
            );

            Menu::query()->updateOrCreate(
                ['route' => 'backend.doctor.portal.opd'],
                [
                    'parent_id' => null,
                    'name' => 'Doctor Portal',
                    'icon' => 'briefcase',
                    'description' => null,
                    'sorting' => 999,
                    'permission_name' => 'doctor-portal',
                    'status' => 'Active',
                    'deleted_at' => null,
                ]
            );
        });
    }
}
