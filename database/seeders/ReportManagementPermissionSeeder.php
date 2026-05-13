<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReportManagementPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $reportParentPermission = Permission::query()->firstOrCreate([
                'name' => 'report-management',
                'guard_name' => 'admin',
            ]);

            $desiredNames = ['report-management', 'report-list', 'report-delivery'];

            $existing = Permission::query()
                ->whereIn('name', $desiredNames)
                ->where('guard_name', 'admin')
                ->pluck('name')
                ->all();

            if (!in_array('report-management', $existing)) {
                $existing[] = 'report-management';
            }

            // Assign to core roles
            $roles = Role::query()->whereIn('name', ['Admin', 'developer'])->get();

            foreach ($roles as $role) {
                try {
                    $role->givePermissionTo($existing);
                } catch (\Throwable $e) {
                    // ignore assignment errors to avoid breaking seeder
                }
            }

            // Backfill roles that already have report-related permissions
            $backfillRoles = Role::query()
                ->whereHas('permissions', function ($q) {
                    $q->whereIn('name', ['report-list', 'report-delivery']);
                })->get();

            foreach ($backfillRoles as $role) {
                try {
                    $role->givePermissionTo($existing);
                } catch (\Throwable $e) {
                }
            }
        });
    }
}
